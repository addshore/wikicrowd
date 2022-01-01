<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Addwiki\Mediawiki\DataModel\Page;
use Addwiki\Mediawiki\DataModel\PageIdentifier;
use Addwiki\Mediawiki\DataModel\Title;
use Addwiki\Mediawiki\Api\Service\CategoryTraverser;
use Wikibase\DataModel\Entity\EntityId;
use App\Models\QuDepicts;

class GenerateQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $category;
    private $depictItem;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct( string $category, string $depictItem )
    {
        $this->category = $category;
        $this->depictItem = $depictItem;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $mwServices = (new \Addwiki\Wikimedia\Api\WikimediaFactory())->newMediawikiFactoryForDomain( 'commons.wikimedia.org' );

        // Recursively descend the category looking for files
        $traverser = $mwServices->newCategoryTraverser();
        $traverser->addCallback( CategoryTraverser::CALLBACK_PAGE, function( Page $member, Page $rootCat ) {
            // Skip all non files
            if( $member->getPageIdentifier()->getTitle()->getNs() !== 6 ) {
                return;
            }
            $this->processFilePage( $member->getPageIdentifier() );
        } );
        $traverser->descend( new Page( new PageIdentifier( new Title( "Category:{$this->category}", 14 ) ) ) );
    }

    private function processFilePage( PageIdentifier $filePageIdentifier ) : bool {
        $wmFactory = (new \Addwiki\Wikimedia\Api\WikimediaFactory());
        $wbServices = $wmFactory->newWikibaseFactoryForDomain( 'commons.wikimedia.org' );
        $depictsProperty = new \Wikibase\DataModel\Entity\PropertyId( 'P180' );
        $depictsValue = new \Wikibase\DataModel\Entity\ItemId( $this->depictItem );

        echo "Processing page=" . $filePageIdentifier->getTitle()->getText() . PHP_EOL;
        // XXX: It would be nice if there were a package for this....
        $mid = new class("M" . $filePageIdentifier->getId()) extends EntityId {
            public function getEntityType() { return "mediainfo"; }
            public function serialize() { return $this->serialization; }
            public function unserialize( $data ) { return self::__construct( $data ); }
        };

        /** @var \Wikibase\MediaInfo\DataModel\MediaInfo $entity */
        $entity = $wbServices->newEntityLookup()->getEntity( $mid );
        $foundDepicts = false;
        foreach( $entity->getStatements()->getByPropertyId( $depictsProperty )->toArray() as $statement ) {
            if(
                // Snak type is a value
                $statement->getMainSnak()->getType() === 'value' &&
                // And it is of the type we want to be setting
                $statement->getMainSnak()->getDataValue()->getEntityId()->equals( $depictsValue ) 
            ) {
                $foundDepicts = true;
                break;
            }
        }

        if($foundDepicts) {
            echo "Found depicts statement, skipping" . PHP_EOL;
            return false;
        } else {
            $mwApi = $wmFactory->newMediawikiApiForDomain('commons.wikimedia.org');
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
                echo "Failed getting thumb url, skipping" . PHP_EOL;
                return false;
            }

            // Create the question
            // TODO don't create if already created... (unique indexes?)
            QuDepicts::create([
                'mediainfo_id' => $mid->getSerialization(),
                'depicts_id' => $this->depictItem,
                'img_url' => $thumbUrl,
            ]);
            return true;
        }
    }
}
