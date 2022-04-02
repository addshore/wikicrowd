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

class GenerateDepictsQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        string $category,
        string $ignoreCategories,
        string $ignoreCategoriesRegex,
        string $depictItemId,
        string $depictName,
        int $limit
        )
    {
        $this->category = $category;
        $this->ignoreCategories = explode('|||', $ignoreCategories);
        $this->ignoreCategoriesRegex = $ignoreCategoriesRegex;
        $this->depictItemId = $depictItemId;
        $this->depictName = $depictName;
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
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
        echo "Already got $this->got questions\n";
        if($this->got >= $this->limit) {
            echo "Already got enough questions\n";
            return;
        }

        $this->instancesOfAndSubclassesOf = $this->instancesOfAndSubclassesOf( $this->depictItemId );
        $this->parentInstancesOfAndSubclassesOf = $this->parentInstancesOfAndSubclassesOf( $this->depictItemId );

        $mwServices = (new \Addwiki\Wikimedia\Api\WikimediaFactory())->newMediawikiFactoryForDomain( self::COMMONS );

        // Recursively descend the category looking for files
        $traverser = $mwServices->newCategoryTraverser();
        $traverser->addCallback( CategoryTraverser::CALLBACK_CATEGORY, function( Page $member, Page $rootCat ) {
            if( $this->got >= $this->limit ) {
                echo "Limit reached\n";
                return false;
            }
            $memberText = $member->getPageIdentifier()->getTitle()->getText();
            $rootText = $rootCat->getPageIdentifier()->getTitle()->getText();
            if(in_array($memberText, $this->ignoreCategories)) {
                echo "Ignoring text match $memberText\n";
                return false;
            }
            if($this->ignoreCategoriesRegex !== "" && preg_match($this->ignoreCategoriesRegex, $memberText)) {
                echo "Ignoring regex match $memberText\n";
                return false;
            }
            echo "Processing: " . $memberText . ", Parent was: " . $rootText . "\n";
        } );
        $traverser->addCallback( CategoryTraverser::CALLBACK_PAGE, function( Page $member, Page $rootCat ) {
            // Terrible limiting, but the only way to do it right now..
            if( $this->got >= $this->limit ) {
                echo "Limit reached\n";
                return;
            }
            $pageIdentifier = $member->getPageIdentifier();
            // Skip all non files
            if( $pageIdentifier->getTitle()->getNs() !== 6 ) {
                return;
            }
            // Skip anything that is not an image
            if( !in_array( $this->getFileExtensionFromName( $pageIdentifier->getTitle()->getText() ), self::IMAGE_FILE_EXTENSIONS ) ) {
                echo "Non image\n";
                return;
            }
            // Skip pages we already generated a question for of any depicts type
            if (Question::whereIn('question_group_id', [ $this->depictsSubGroup, $this->depictsRefineSubGroup] )
                    ->whereIn('unique_id', [ $this->uniqueID( 'depicts', $pageIdentifier ), $this->uniqueID( 'depicts-refine', $pageIdentifier ) ])
                    ->exists()
                ) {
                // question already found
                echo "Question exists\n";
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

    private function uniqueID( string $groupName, PageIdentifier $filePageIdentifier ) : string {
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
            echo "MediaInfo entity not found\n";
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
            echo "Exact depicts found\n";
            return false;
        }
        if($foundDepicts['moreSpecific'] > 0) {
            echo "More specific depicts found\n";
            return false;
        }

        $thumbUrl = $this->getThumbUrl( $filePageIdentifier );
        if($thumbUrl===null) {
            echo "ERROR: Failed getting thumb url: " . $filePageIdentifier->getTitle()->getText() . PHP_EOL;
            return false;
        }

        if($foundDepicts['lessSpecific'] > 0) {
            if($foundDepicts['lessSpecific'] !== 1) {
                echo "BAIL: I'm scared, as multiple less specific statements were found...\n";
                return false;
            }

            $wikidataWbServices = $wmFactory->newWikibaseFactoryForDomain( self::WIKIDATA );
            $lessSpecificItem = $wikidataWbServices->newItemLookup()->getItemForId( $lessSpecificValue );
            if(!$lessSpecificItem) {
                echo "ERROR: Failed to get less specific item\n";
                return false;
            }
            // TODO don't harcode to en?
            if(!$lessSpecificItem->getLabels()->hasTermForLanguage( 'en' )) {
                echo "ERROR: Less specific item has no label in English\n";
                return false;
            }
            $lessSpecificItemLabel = $lessSpecificItem->getLabels()->getByLanguage( 'en' );

            Question::create([
                'question_group_id' => $this->depictsRefineSubGroup,
                // TODO don't harcode group name?
                'unique_id' => $this->uniqueID( 'depicts-refine', $filePageIdentifier ),
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
            echo "=D Depict Refine question added for " . $mid->getSerialization() . "!" . PHP_EOL;

            return true;
        }

        // Create the add depicts questions
        Question::create([
            'question_group_id' => $this->depictsSubGroup,
            // TODO don't harcode group name?
            'unique_id' => $this->uniqueID( 'depicts', $filePageIdentifier ),
            'properties' => [
                'mediainfo_id' => $mid->getSerialization(),
                'depicts_id' => $this->depictItemId,
                'depicts_name' => $this->depictName,
                'img_url' => $thumbUrl,
            ]
        ]);
        $this->got++;
        echo "=D Depict Add question added for " . $mid->getSerialization() . "!" . PHP_EOL;
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
                    'iiurlwidth' => '800',
                    'iiurlheight' => '800',
                    'format' => 'json',
                ]
            ));
        $thumbUrl = $response['query']['pages'][$filePageIdentifier->getId()]['imageinfo'][0]['thumburl'];
        if(empty($thumbUrl)) {
            return null;
        }
        return $thumbUrl;
    }
}
