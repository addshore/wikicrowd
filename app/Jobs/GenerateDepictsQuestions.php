<?php

    namespace App\Jobs;

    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Addwiki\Mediawiki\DataModel\Page;
    use Addwiki\Mediawiki\DataModel\PageIdentifier;
    use Addwiki\Mediawiki\DataModel\Title;
    use Addwiki\Mediawiki\Api\Service\CategoryTraverser;
    use App\Models\Question;
    use App\Models\QuestionGroup;
    use App\Models\SkippedQuestion;
    use Wikibase\MediaInfo\DataModel\MediaInfoId;
    use Addwiki\Wikibase\Query\PrefixSets;
    use Symfony\Component\Yaml\Yaml;
    use Illuminate\Bus\Batchable;
    use Illuminate\Support\Facades\Bus;
    use Illuminate\Support\Facades\Cache;
    use App\Services\SparqlQueryService;

    class GenerateDepictsQuestions implements ShouldQueue, ShouldBeUnique
    {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

        /**
         * The number of seconds the job can run before timing out.
         *
         * @var int
         */
        public $timeout = 300;

        const DEPICTS_PROPERTY = 'P180';
        const WIKIDATA = 'www.wikidata.org';
        const COMMONS = 'commons.wikimedia.org';
        const IMAGE_FILE_EXTENSIONS = [
            // https://www.mediawiki.org/wiki/Help:Images#Supported_media_types_for_images
            'jpg',
            'jpeg',
            'png',
            'gif',
            'svg',
            'tiff',
        ];

        private $category;
        private $ignoreCategories;
        private $ignoreCategoriesRegex;
        private $depictItemId;
        private $depictName;
        private $limit;
        private $recursionDepth;
        private $got;
        private $depictsSubGroup;
        private $instancesOfAndSubclassesOf;

        /**
         * Create a new job instance for a single depicts question job.
         *
         * @param string $category
         * @param string|array $ignoreCategories (JSON array string or array)
         * @param string $ignoreCategoriesRegex
         * @param string $depictItemId
         * @param string $depictName
         * @param int $limit
         */
        public function __construct(
            string $category,
            $ignoreCategories = [],
            string $ignoreCategoriesRegex = '',
            string $depictItemId = '',
            string $depictName = '',
            int $limit = 0,
            int $recursionDepth = 0
        )
        {
            $this->category = $category;
            if (is_string($ignoreCategories)) {
                $decoded = json_decode($ignoreCategories, true);
                $ignoreCategoriesArr = is_array($decoded) ? $decoded : [];
            } else {
                $ignoreCategoriesArr = $ignoreCategories;
            }
            // Normalize and validate ignoreCategories
            $normalized = [];
            foreach ($ignoreCategoriesArr as $cat) {
                $norm = self::normalizeCategoryName($cat);
                if ($norm !== null) {
                    $normalized[] = $norm;
                } else {
                    \Log::warning("Invalid ignoreCategory entry: '" . print_r($cat, true) . "'");
                }
            }
            $this->ignoreCategories = $normalized;
            $this->ignoreCategoriesRegex = $ignoreCategoriesRegex;
            $this->depictItemId = $depictItemId;
            $this->depictName = $depictName;
            $this->limit = $limit;
            $this->recursionDepth = $recursionDepth;
        }

        /**
         * The unique ID of the job.
         * Prevents duplicate jobs for the same category + depictItemId combination.
         */
        public function uniqueId()
        {
            return 'depicts:' . $this->depictItemId . ':' . md5($this->category);
        }

        /**
         * The number of seconds after which the job's unique lock will be released.
         */
        public function uniqueFor(): int
        {
            return 3600; // 1 hour - longer than individual job timeout
        }

        /**
         * Execute the job.
         *
         * @return void
         */
        public function handle() {
            // Check if this job is part of a batch and if the batch has been cancelled
            if ($this->batch() && $this->batch()->cancelled()) {
                \Log::info("Batch has been cancelled, skipping job for depictItemId: {$this->depictItemId}");
                return;
            }

            $batchId = $this->batch() ? $this->batch()->id : 'standalone';
            \Log::info("Starting depicts job for category: " . $this->category . ", depictsId: " . $this->depictItemId . ", batchId: " . $batchId);

                // Ensure category is prefixed with 'Category:'
                $categoryName = $this->category;
                if (stripos($categoryName, 'Category:') !== 0) {
                    $categoryName = 'Category:' . $categoryName;
                }
                $this->createQuestionGroups();

                // Figure out how many questions for the category we already have
                $depictGroupCount = QuestionGroup::where('id','=',$this->depictsSubGroup)
                    ->withCount(['question as unanswered' => function($query){
                    $query->doesntHave('answer');
                    }])->first()->unanswered;
                $this->got = $depictGroupCount;
                \Log::info("Already got $this->got questions");
                if($this->got >= $this->limit) {
                    \Log::info("Already got enough questions ({$this->got}), not processing category: $categoryName");
                    return;
                }

                $this->instancesOfAndSubclassesOf = $this->instancesOfAndSubclassesOf( $this->depictItemId );

                $mwServices = (new \Addwiki\Wikimedia\Api\WikimediaFactory())->newMediawikiFactoryForDomain( self::COMMONS );
                $traverser = $mwServices->newCategoryTraverser();

                // Add this category to the ignore list to prevent recursion
                $currentIgnore = $this->ignoreCategories;
                if (!in_array($categoryName, $currentIgnore)) {
                    $currentIgnore[] = $categoryName;
                }

                $subJobs = [];

                $traverser->addCallback( \Addwiki\Mediawiki\Api\Service\CategoryTraverser::CALLBACK_CATEGORY, function( $member, $rootCat ) use (&$subJobs, $currentIgnore ) {
                    if( $this->got >= $this->limit ) {
                        \Log::info("Limit reached");
                        return false;
                    }
                    if ($this->recursionDepth >= 100) {
                        \Log::info("Max recursion depth reached ({$this->recursionDepth}), not scheduling sub-job for category: " . $member->getPageIdentifier()->getTitle()->getText());
                        return false;
                    }
                    $memberText = $member->getPageIdentifier()->getTitle()->getText();
                    if(in_array($memberText, $currentIgnore)) {
                        \Log::info("Ignoring text match $memberText");
                        return false;
                    }
                    if($this->ignoreCategoriesRegex !== "" && preg_match($this->ignoreCategoriesRegex, $memberText)) {
                        \Log::info("Ignoring regex match $memberText");
                        return false;
                    }
                    \Log::info("Dispatching sub-job for category: $memberText at recursion depth " . ($this->recursionDepth + 1));
                    // Dispatch a new job for this subcategory
                    $subJobs[] = new GenerateDepictsQuestions(
                        $memberText,
                        $currentIgnore,
                        $this->ignoreCategoriesRegex,
                        $this->depictItemId,
                        $this->depictName,
                        $this->limit,
                        $this->recursionDepth + 1
                    );
                    // Don't descend further in this job
                    return false;
                } );
                $traverser->addCallback( \Addwiki\Mediawiki\Api\Service\CategoryTraverser::CALLBACK_PAGE, function( $member, $rootCat ) {
                    // Terrible limiting, but the only way to do it right now..
                    if( $this->got >= $this->limit ) {
                        \Log::info("Limit reached");
                        return;
                    }
                    $pageIdentifier = $member->getPageIdentifier();
                    // Skip all non files
                    if( $pageIdentifier->getTitle()->getNs() !== 6 ) {
                        return;
                    }
                    // Skip anything that is not an image
                    if( !in_array( $this->getFileExtensionFromName( $pageIdentifier->getTitle()->getText() ), self::IMAGE_FILE_EXTENSIONS ) ) {
                        \Log::info("Non image file: " . $pageIdentifier->getTitle()->getText());
                        return;
                    }
                    // Skip pages we already generated a question for of any depicts type
                    if (\App\Models\Question::whereIn('question_group_id', [ $this->depictsSubGroup ] )
                            ->whereIn('unique_id', [ $this->uniqueQuestionID( 'depicts', $pageIdentifier ), $this->uniqueQuestionID( 'depicts-refine', $pageIdentifier ) ])
                            ->exists()
                        ) {
                        // question already found
                        \Log::info("Question exists");
                        return;
                    }
                    $this->processFilePage( $pageIdentifier );
                } );
                $traverser->descend( new \Addwiki\Mediawiki\DataModel\Page( new \Addwiki\Mediawiki\DataModel\PageIdentifier( new \Addwiki\Mediawiki\DataModel\Title( $categoryName, 14 ) ) ) );

                // Dispatch all subcategory jobs as a batch if any
                if (!empty($subJobs)) {
                    if ($this->batch()) {
                        // Add sub-jobs to the current batch
                        $this->batch()->add($subJobs);
                        \Log::info("Added " . count($subJobs) . " sub-jobs to existing batch: " . $this->batch()->id);
                    } else {
                        // Create a new batch if this job is not part of one
                        Bus::batch($subJobs)
                            ->name('depicts:' . $this->depictItemId)
                            ->onQueue('low') // once we get going, we can use the low queue
                            ->dispatch();
                        \Log::info("Created new batch with " . count($subJobs) . " sub-jobs for depicts: " . $this->depictItemId);
                    }
                }
        }

        private function getFileExtensionFromName( string $name ){
            $parts = explode( '.', $name );
            return end( $parts );
        }

        private function createQuestionGroups() {
            $depictsGroup = QuestionGroup::firstOrCreate(
                ['name' => 'depicts'],
                [
                    'display_name' => 'Depicts',
                    'layout' => 'grid',
                ]
            );
            $depictsSubGroup = QuestionGroup::firstOrCreate(
                ['name' => 'depicts/' . $this->depictItemId],
                [
                    'display_name' => $this->depictName,
                    'layout' => 'image-focus',
                    'parent' => $depictsGroup->id,
                ]
            );
            $this->depictsSubGroup = $depictsSubGroup->id;
        }

        private function uniqueQuestionID( string $groupName, PageIdentifier $filePageIdentifier ) : string {
            return "M" . $filePageIdentifier->getId() . '/' . $groupName . '/' . $this->depictItemId;
        }

        private function instancesOfAndSubclassesOf( string $itemId ) : array {
            $sparqlService = new SparqlQueryService();
            return $sparqlService->getSubclassesAndInstances($itemId);
        }

        private function processFilePage( PageIdentifier $filePageIdentifier ) : bool {
            $wmFactory = (new \Addwiki\Wikimedia\Api\WikimediaFactory());
            $commonsWbServices = $wmFactory->newWikibaseFactoryForDomain( self::COMMONS );
            $depictsProperty = new \Wikibase\DataModel\Entity\PropertyId( self::DEPICTS_PROPERTY );
            $depictsValue = new \Wikibase\DataModel\Entity\ItemId( $this->depictItemId );

            $mid = new MediaInfoId( "M" . $filePageIdentifier->getId() );

            /** @var \Wikibase\MediaInfo\DataModel\MediaInfo $entity */
            $entity = $commonsWbServices->newEntityLookup()->getEntity( $mid );
            if($entity === null) {
                // TODO could still create statements for this condition...
                \Log::error("MediaInfo entity not found");
                return false;
            }

            $foundDepicts = [
                'exact' => 0,
                'moreSpecific' => 0,
            ];
            foreach( $entity->getStatements()->getByPropertyId( $depictsProperty )->toArray() as $statement ) {
                // Skip non value statements
                if( $statement->getMainSnak()->getType() !== 'value' ) {
                    continue;
                }

                $entityId = $statement->getMainSnak()->getDataValue()->getEntityId();

                if( $entityId->equals( $depictsValue ) ) {
                    $foundDepicts['exact']++;
                    continue;
                }
                if( in_array( $entityId->getSerialization(), $this->instancesOfAndSubclassesOf ) ) {
                    $foundDepicts['moreSpecific']++;
                    continue;
                }
            }

            $uniqueDepictsId = $this->uniqueQuestionID( 'depicts', $filePageIdentifier );
            // Check if already skipped
            if (SkippedQuestion::whereIn('unique_id', [ $uniqueDepictsId ])->exists()) {
                \Log::info("Question generation already skipped for $uniqueDepictsId");
                return false;
            }

            if($foundDepicts['exact'] > 0) {
                \Log::info("Exact depicts found for " . $filePageIdentifier->getTitle()->getText());
                // Record as skipped
                SkippedQuestion::firstOrCreate(['unique_id' => $uniqueDepictsId]);
                return false;
            }
            if($foundDepicts['moreSpecific'] > 0) {
                \Log::info("More specific depicts found for " . $filePageIdentifier->getTitle()->getText());
                // Record as skipped
                SkippedQuestion::firstOrCreate(['unique_id' => $uniqueDepictsId]);
                return false;
            }

            $thumbUrl = $this->getThumbUrl( $filePageIdentifier );
            if($thumbUrl===null) {
                \Log::error("ERROR: Failed getting thumb url: " . $filePageIdentifier->getTitle()->getText());
                return false;
            }

            // Create the add depicts questions
            Question::create([
                'question_group_id' => $this->depictsSubGroup,
                'unique_id' => $this->uniqueQuestionID( 'depicts', $filePageIdentifier ),
                'properties' => [
                    'mediainfo_id' => $mid->getSerialization(),
                    'depicts_id' => $this->depictItemId,
                    'depicts_name' => $this->depictName,
                    'img_url' => $thumbUrl,
                ]
            ]);
            $this->got++;
            \Log::info("=D Depict Add question added for " . $mid->getSerialization() . "!");
            return true;
        }

        private function getThumbUrl( PageIdentifier $filePageIdentifier ) : ?string {
            // TODO factor into addwiki mediawiki library?
            $wmFactory = (new \Addwiki\Wikimedia\Api\WikimediaFactory());
            $mwApi = $wmFactory->newMediawikiApiForDomain(self::COMMONS);
            $response = $mwApi->request(
                \Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest::simpleGet(
                    'query',
                    [
                        'titles' => $filePageIdentifier->getTitle()->getText(),
                        'prop' => 'imageinfo',
                        'iiprop' => 'url',
                        'iiurlwidth' => '960',
                        'iiurlheight' => '960',
                        'format' => 'json',
                    ]
                ));
            $thumbUrl = $response['query']['pages'][$filePageIdentifier->getId()]['imageinfo'][0]['thumburl'];
            if(empty($thumbUrl)) {
                return null;
            }
            return $thumbUrl;
        }

        /**
         * TODO move this function elsewhere?
         */
        private function getRecursiveYamlFilesInDirectory(string $directory){
            $files = [];
            $dir = new \RecursiveDirectoryIterator($directory);
            $iterator = new \RecursiveIteratorIterator($dir);
            foreach ($iterator as $file) {
                if ( $file->isFile() ) {
                    $files[] = realpath($file->getPathname());
                }
            }
            return $files;
        }

        /**
         * Normalize a category name: strip links, colons, whitespace, and ensure single 'Category:' prefix.
         * @param string $cat
         * @return string|null Returns normalized name or null if invalid
         */
        public static function normalizeCategoryName($cat) {
            if (!is_string($cat) || $cat === '') return null;
            // Remove [[...]]
            $cat = preg_replace('/^\[\[|\]\]$/', '', trim($cat));
            // Remove leading colon(s)
            $cat = ltrim($cat, ':');
            // If it's a link like [[:Category:Foo]], extract inside
            if (preg_match('/^Category:(.+)$/i', $cat, $m)) {
                $cat = 'Category:' . trim($m[1]);
            } elseif (preg_match('/^Category:(.+)$/i', $cat)) {
                // already normalized
            } else {
                $cat = 'Category:' . $cat;
            }
            // Remove double prefix if present
            $cat = preg_replace('/^(Category:)+/', 'Category:', $cat);
            $cat = trim($cat);
            // Validate: must start with Category: and not be empty after
            if (!preg_match('/^Category:[^:]+/', $cat)) return null;
            return $cat;
        }

    }
