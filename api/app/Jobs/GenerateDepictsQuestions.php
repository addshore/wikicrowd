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
use Wikibase\DataModel\Entity\EntityId;
use App\Models\Question;
use App\Models\QuestionGroup;
use Wikibase\MediaInfo\DataModel\MediaInfoId;

class GenerateDepictsQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const DEPICTS_PROPERTY = 'P180';
    const COMMONS = 'commons.wikimedia.org';

    private $category;
    private $depictItemId;
    private $depictName;
    private $targetGroup;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        string $category,
        string $depictItemId,
        string $depictName
        )
    {
        $this->category = $category;
        $this->depictItemId = $depictItemId;
        $this->depictName = $depictName;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->createQuestionGroups();

        $mwServices = (new \Addwiki\Wikimedia\Api\WikimediaFactory())->newMediawikiFactoryForDomain( self::COMMONS );

        // Recursively descend the category looking for files
        $traverser = $mwServices->newCategoryTraverser();
        $traverser->addCallback( CategoryTraverser::CALLBACK_CATEGORY, function( Page $member, Page $rootCat ) {
            echo "Processing category: " . $member->getPageIdentifier()->getTitle()->getText() . "\n";
        } );
        $traverser->addCallback( CategoryTraverser::CALLBACK_PAGE, function( Page $member, Page $rootCat ) {
            $pageIdentifier = $member->getPageIdentifier();
            // Skip all non files
            if( $pageIdentifier->getTitle()->getNs() !== 6 ) {
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

    private function processFilePage( PageIdentifier $filePageIdentifier ) : bool {
        $wmFactory = (new \Addwiki\Wikimedia\Api\WikimediaFactory());
        $wbServices = $wmFactory->newWikibaseFactoryForDomain( self::COMMONS );
        $depictsProperty = new \Wikibase\DataModel\Entity\PropertyId( self::DEPICTS_PROPERTY );
        $depictsValue = new \Wikibase\DataModel\Entity\ItemId( $this->depictItemId );

        $mid = new MediaInfoId( "M" . $filePageIdentifier->getId() );

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
            echo "Already has depicts" . PHP_EOL;
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
            echo "=D Question added for " . $mid->getSerialization() . "!" . PHP_EOL;
            return true;
        }
    }
}
