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

/**
 * Generates questions from a Wikimedia Commons Category for a given depict statement taget Item.
 * Will not generate a question if:
 *  - The MediaInfo entity has a depicts statement of exactly the terget item, or an instance of or subclass of.
 *  - A matching question already exists.
 *  - Something else goes wrong.
 * 
 * GenerateDepictsQuestions Fog Q37477 Fog
 * GenerateDepictsQuestions Badminton Q7291 Badminton
 * GenerateDepictsQuestions Bridges Q12280 Bridge
 */
class GenerateDepictsQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const DEPICTS_PROPERTY = 'P180';
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
    private $depictItemId;
    private $depictName;
    private $targetGroup;
    private $instancesOfAndSubclassesOf;
    private $limit;
    private $added = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        string $category,
        string $depictItemId,
        string $depictName,
        int $limit
        )
    {
        $this->category = $category;
        $this->depictItemId = $depictItemId;
        $this->depictName = $depictName;
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->createQuestionGroups();
        $this->instancesOfAndSubclassesOf = $this->instancesOfAndSubclassesOf( $this->depictItemId );

        $mwServices = (new \Addwiki\Wikimedia\Api\WikimediaFactory())->newMediawikiFactoryForDomain( self::COMMONS );

        // Recursively descend the category looking for files
        $traverser = $mwServices->newCategoryTraverser();
        $traverser->addCallback( CategoryTraverser::CALLBACK_CATEGORY, function( Page $member, Page $rootCat ) {
            // Terrible limiting, but the only way to do it right now..
            if( $this->added >= $this->limit ) {
                echo "Limit reached\n";
                return;
            }
            echo "Processing category: " . $member->getPageIdentifier()->getTitle()->getText() . "\n";
        } );
        $traverser->addCallback( CategoryTraverser::CALLBACK_PAGE, function( Page $member, Page $rootCat ) {
            // Terrible limiting, but the only way to do it right now..
            if( $this->added >= $this->limit ) {
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
            // Skip pages we already generated a question for
            if (Question::where('question_group_id', '=', $this->targetGroup)
                    ->where('unique_id', '=', $this->uniqueID( $pageIdentifier ))
                    ->exists()
                ) {
                // question already found
                echo "Question exists\n";
                return;
             }
            $this->processFilePage( $pageIdentifier );
        } );
        $traverser->descend( new Page( new PageIdentifier( new Title( "Category:{$this->category}", 14 ) ) ) );
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
        $this->targetGroup = $depictsSubGroup->id;
    }

    private function uniqueID( PageIdentifier $filePageIdentifier ) : string {
        return "M" . $filePageIdentifier->getId() . '/depicts/' . $this->depictItemId;
    }

    private function instancesOfAndSubclassesOf( string $itemId ) : array {
        $query = (new \Addwiki\Wikibase\Query\WikibaseQueryFactory(
            "https://query.wikidata.org/sparql",
            PrefixSets::WIKIDATA
        ))->newWikibaseQueryService();
        $result = $query->query( "SELECT DISTINCT ?i WHERE{?i wdt:P31/wdt:P279* wd:${itemId} }" );

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
        $wbServices = $wmFactory->newWikibaseFactoryForDomain( self::COMMONS );
        $depictsProperty = new \Wikibase\DataModel\Entity\PropertyId( self::DEPICTS_PROPERTY );
        $depictsValue = new \Wikibase\DataModel\Entity\ItemId( $this->depictItemId );

        $mid = new MediaInfoId( "M" . $filePageIdentifier->getId() );

        /** @var \Wikibase\MediaInfo\DataModel\MediaInfo $entity */
        $entity = $wbServices->newEntityLookup()->getEntity( $mid );
        if($entity === null) {
            // TODO could still create statements for this condition...
            echo "MediaInfo entity not found\n";
            return false;
        }
        $foundDepicts = false;
        foreach( $entity->getStatements()->getByPropertyId( $depictsProperty )->toArray() as $statement ) {
            // Skip non value statements
            if( $statement->getMainSnak()->getType() !== 'value' ) {
                continue;
            }

            $entityId = $statement->getMainSnak()->getDataValue()->getEntityId();

            // Skip exact depicts matches
            if( $entityId->equals( $depictsValue ) ) {
                $foundDepicts = 'exact';
                break;
            }

            // and inherited matches
            if( in_array( $entityId->getSerialization(), $this->instancesOfAndSubclassesOf ) ) {
                $foundDepicts = 'inherited';
                break;
            }
        }

        if($foundDepicts !== false) {
            echo "Already has ${foundDepicts} depicts" . PHP_EOL;
            return false;
        } else {
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
                echo "ERROR: Failed getting thumb url: " . $filePageIdentifier->getTitle()->getText() . PHP_EOL;
                return false;
            }

            // Create the question
            Question::create([
                'question_group_id' => $this->targetGroup,
                'unique_id' => $this->uniqueID( $filePageIdentifier ),
                'properties' => [
                    'mediainfo_id' => $mid->getSerialization(),
                    'depicts_id' => $this->depictItemId,
                    'depicts_name' => $this->depictName,
                    'img_url' => $thumbUrl,
                ]
            ]);
            $this->added++;
            echo "=D Question added for " . $mid->getSerialization() . "!" . PHP_EOL;
            return true;
        }
    }
}
