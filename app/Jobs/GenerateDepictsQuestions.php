<?php

    namespace App\Jobs;

    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Addwiki\Mediawiki\DataModel\Page;
    use Addwiki\Mediawiki\DataModel\PageIdentifier;
    use Addwiki\Mediawiki\DataModel\Title;
    use Addwiki\Mediawiki\Api\Service\CategoryTraverser;
    use App\Models\Question;
    use App\Models\QuestionGroup;
    use Wikibase\MediaInfo\DataModel\MediaInfoId;
    use Addwiki\Wikibase\Query\PrefixSets;
    use Illuminate\Contracts\Queue\ShouldBeUnique;
    use Symfony\Component\Yaml\Yaml;

    class GenerateDepictsQuestions implements ShouldQueue, ShouldBeUnique
    {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        private $depictsSubGroup;
        private $depictsRefineSubGroup;
        private $instancesOfAndSubclassesOf;
        private $parentInstancesOfAndSubclassesOf;
        private $limit;
        private $got = 0;
        private $yamlFile = null; // Add property

        /**
         * Create a new job instance.
         *
         * @return void
         */
        public function __construct(
            string $category = "",
            string $ignoreCategories = "",
            string $ignoreCategoriesRegex = "",
            string $depictItemId = "",
            string $depictName = "",
            int $limit = 0,
            string $yamlFile = null // Add parameter
            )
        {
            $this->category = $category;
            $this->ignoreCategories = explode('|||', $ignoreCategories);
            $this->ignoreCategoriesRegex = $ignoreCategoriesRegex;
            $this->depictItemId = $depictItemId;
            $this->depictName = $depictName;
            $this->limit = $limit;
            $this->yamlFile = $yamlFile; // Set property
        }

        /**
         * The unique ID of the job.
         *
         * @return string
         */
        public function uniqueId()
        {
            return $this->depictItemId;
        }

        /**
         * Execute the job.
         *
         * @return void
         */
        public function handle() {
            // If we were constructed with values, handle this one instance
            // Otherwise, query the YAML and run each job
            if ($this->depictItemId !== "") {
                $this->handleOne();
                return;
            }

            // If a specific YAML file is provided, only process that file
            if ($this->yamlFile !== null) {
                $depictsYamlFiles = [ $this->yamlFile ];
            } else {
                $depictsYamlDir =__DIR__ . '/../../spec/';
                $depictsYamlFiles = $this->getRecursiveYamlFilesInDirectory( $depictsYamlDir );
            }

            $depictsJobs = [];
            foreach( $depictsYamlFiles as $file ) {
                $file = Yaml::parse(file_get_contents($file), Yaml::PARSE_OBJECT_FOR_MAP);
                if ( $file === false ) {
                    \Log::error("Failed to parse YAML file: " . $file);
                    continue;
                }
                if ($file === null ) {
                    \Log::error("YAML file is empty or invalid: " . $file);
                    continue;
                }
                if( is_array( $file ) ) {
                    $depictsJobs = array_merge( $depictsJobs, $file );
                } else {
                    $depictsJobs[] = $file;
                }
            }

            // Remove .all.yml from the list of jobs if it exists
            $depictsJobs = array_filter( $depictsJobs, function( $job ) {
                return !isset( $job->all ) || !$job->all;
            } );

            var_dump( $depictsJobs );

            // Sort the jobs
            usort( $depictsJobs, function( $a, $b ) {
                // Sort by category first
                return strcmp( $a->category, $b->category );
            } );
            // Then write them back into a new file called "all.yml"
            $allYamlFile = __DIR__ . '/../../spec/depicts/.all.yml';
            file_put_contents( $allYamlFile, Yaml::dump( $depictsJobs, 10, 2, Yaml::DUMP_OBJECT_AS_MAP ) );
            \Log::info("Wrote all jobs to: " . $allYamlFile);

            // Randomize the order of the jobs
            shuffle( $depictsJobs );

            foreach( $depictsJobs as $job ) {
                // Make sure that job is an object
                if( !is_object( $job ) ) {
                    \Log::info("Job is not an object");
                    continue;
                }
                // Make sure it has the required fields
                if( !isset( $job->category ) || !isset( $job->depictsId ) || !isset( $job->name ) || !isset( $job->limit ) ) {
                    \Log::info("Job is missing required fields");
                    continue;
                }

                $inner = new self(
                    $job->category,
                    isset($job->exclude) ? implode('|||', $job->exclude) : "",
                    isset($job->excludeRegex) ? $job->excludeRegex : "",
                    $job->depictsId,
                    $job->name,
                    $job->limit
                );
                $inner->handle();
            }
        }

        public function handleOne() {
            $this->createQuestionGroups();

            // Figure out how many questions for the category we already have
            $depictGroupCount = QuestionGroup::where('id','=',$this->depictsSubGroup)
                ->withCount(['question as unanswered' => function($query){
                $query->doesntHave('answer');
                }])->first()->unanswered;
            $depictRefineGroupCount = QuestionGroup::where('id','=',$this->depictsRefineSubGroup)
                ->withCount(['question as unanswered' => function($query){
                $query->doesntHave('answer');
                }])->first()->unanswered;
            $this->got = $depictGroupCount + $depictRefineGroupCount;
            \Log::info("Already got $this->got questions");
            if($this->got >= $this->limit) {
                \Log::info("Already got enough questions");
                return;
            }

            $this->instancesOfAndSubclassesOf = $this->instancesOfAndSubclassesOf( $this->depictItemId );
            $this->parentInstancesOfAndSubclassesOf = $this->parentInstancesOfAndSubclassesOf( $this->depictItemId );

            $mwServices = (new \Addwiki\Wikimedia\Api\WikimediaFactory())->newMediawikiFactoryForDomain( self::COMMONS );

            // Recursively descend the category looking for files
            $traverser = $mwServices->newCategoryTraverser();
            $traverser->addCallback( CategoryTraverser::CALLBACK_CATEGORY, function( Page $member, Page $rootCat ) {
                if( $this->got >= $this->limit ) {
                    \Log::info("Limit reached");
                    return false;
                }
                $memberText = $member->getPageIdentifier()->getTitle()->getText();
                $rootText = $rootCat->getPageIdentifier()->getTitle()->getText();
                if(in_array($memberText, $this->ignoreCategories)) {
                    \Log::info("Ignoring text match $memberText");
                    return false;
                }
                if($this->ignoreCategoriesRegex !== "" && preg_match($this->ignoreCategoriesRegex, $memberText)) {
                    \Log::info("Ignoring regex match $memberText");
                    return false;
                }
                \Log::info("Processing: " . $memberText . ", Parent was: " . $rootText);
            } );
            $traverser->addCallback( CategoryTraverser::CALLBACK_PAGE, function( Page $member, Page $rootCat ) {
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
                    \Log::info("Non image");
                    return;
                }
                // Skip pages we already generated a question for of any depicts type
                if (Question::whereIn('question_group_id', [ $this->depictsSubGroup, $this->depictsRefineSubGroup] )
                        ->whereIn('unique_id', [ $this->uniqueQuestionID( 'depicts', $pageIdentifier ), $this->uniqueQuestionID( 'depicts-refine', $pageIdentifier ) ])
                        ->exists()
                    ) {
                    // question already found
                    \Log::info("Question exists");
                    return;
                }
                $this->processFilePage( $pageIdentifier );
            } );
            $traverser->descend( new Page( new PageIdentifier( new Title( $this->category, 14 ) ) ) );
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

            $depictsRefineGroup = QuestionGroup::firstOrCreate(
                ['name' => 'depicts-refine'],
                [
                    'display_name' => 'Depicts Refinement',
                    'display_description' => 'Refining high level depicts statements to more specific statements.',
                    'layout' => 'grid',
                ]
            );
            $depictsRefineSubGroup = QuestionGroup::firstOrCreate(
                ['name' => 'depicts-refine/' . $this->depictItemId],
                [
                    'display_name' => $this->depictName,
                    'layout' => 'image-focus',
                    'parent' => $depictsRefineGroup->id,
                ]
            );
            $this->depictsRefineSubGroup = $depictsRefineSubGroup->id;
        }

        private function uniqueQuestionID( string $groupName, PageIdentifier $filePageIdentifier ) : string {
            return "M" . $filePageIdentifier->getId() . '/' . $groupName . '/' . $this->depictItemId;
        }

        private function instancesOfAndSubclassesOf( string $itemId ) : array {
            $query = (new \Addwiki\Wikibase\Query\WikibaseQueryFactory(
                "https://query.wikidata.org/sparql",
                PrefixSets::WIKIDATA
            ))->newWikibaseQueryService();
            $result = $query->query( "SELECT DISTINCT ?i WHERE{?i wdt:P31/wdt:P279*|wdt:P279/wdt:P279* wd:${itemId} }" );

            $ids = [];
            foreach ( $result['results']['bindings'] as $binding ) {
                $ids[] = $this->getLastPartOfUrlPath( $binding['i']['value'] );
            }
            return $ids;
        }

        private function parentInstancesOfAndSubclassesOf( string $itemId ) : array {
            $query = (new \Addwiki\Wikibase\Query\WikibaseQueryFactory(
                "https://query.wikidata.org/sparql",
                PrefixSets::WIKIDATA
            ))->newWikibaseQueryService();
            $result = $query->query( "SELECT DISTINCT ?i WHERE{wd:${itemId} wdt:P279+ ?i }" );

            $ids = [];
            foreach ( $result['results']['bindings'] as $binding ) {
                $ids[] = $this->getLastPartOfUrlPath( $binding['i']['value'] );
            }
            return $ids;
        }

        private function getLastPartOfUrlPath( string $urlPath ): string {
            // Assume that the last part is always the ID?
            $parts = explode( '/', $urlPath );
            return end( $parts );
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
                'lessSpecific' => 0,
            ];
            $lessSpecificValue = null;
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
                if( in_array( $entityId->getSerialization(), $this->parentInstancesOfAndSubclassesOf ) ) {
                    $lessSpecificValue = $entityId;
                    $foundDepicts['lessSpecific']++;
                    continue;
                }
            }

            if($foundDepicts['exact'] > 0) {
                \Log::info("Exact depicts found");
                return false;
            }
            if($foundDepicts['moreSpecific'] > 0) {
                \Log::info("More specific depicts found");
                return false;
            }

            $thumbUrl = $this->getThumbUrl( $filePageIdentifier );
            if($thumbUrl===null) {
                \Log::error("ERROR: Failed getting thumb url: " . $filePageIdentifier->getTitle()->getText());
                return false;
            }

            if($foundDepicts['lessSpecific'] > 0) {
                if($foundDepicts['lessSpecific'] !== 1) {
                    \Log::info("BAIL: I'm scared, as multiple less specific statements were found...");
                    return false;
                }

                $wikidataWbServices = $wmFactory->newWikibaseFactoryForDomain( self::WIKIDATA );
                $lessSpecificItem = $wikidataWbServices->newItemLookup()->getItemForId( $lessSpecificValue );
                if(!$lessSpecificItem) {
                    \Log::error("ERROR: Failed to get less specific item");
                    return false;
                }
                // TODO don't harcode to en?
                if(!$lessSpecificItem->getLabels()->hasTermForLanguage( 'en' )) {
                    \Log::error("ERROR: Less specific item has no label in English");
                    return false;
                }
                $lessSpecificItemLabel = $lessSpecificItem->getLabels()->getByLanguage( 'en' );

                Question::create([
                    'question_group_id' => $this->depictsRefineSubGroup,
                    // TODO don't harcode group name?
                    'unique_id' => $this->uniqueQuestionID( 'depicts-refine', $filePageIdentifier ),
                    'properties' => [
                        'mediainfo_id' => $mid->getSerialization(),
                        'old_depicts_id' => $lessSpecificValue->getSerialization(),
                        'old_depicts_name' => $lessSpecificItemLabel->getText(),
                        'depicts_id' => $this->depictItemId,
                        'depicts_name' => $this->depictName,
                        'img_url' => $thumbUrl,
                    ]
                ]);
                $this->got++;
                \Log::info("=D Depict Refine question added for " . $mid->getSerialization() . "!");

                return true;
            }

            // Create the add depicts questions
            Question::create([
                'question_group_id' => $this->depictsSubGroup,
                // TODO don't harcode group name?
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

    }
