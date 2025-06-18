<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Answer;
use App\Models\Edit;
use Addwiki\Wikimedia\Api\WikimediaFactory;
use Addwiki\Mediawiki\Api\MediawikiFactory;
use Addwiki\Mediawiki\DataModel\PageIdentifier;
use Wikibase\MediaInfo\DataModel\MediaInfoId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\ItemId;
use Addwiki\Wikibase\Query\PrefixSets;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\EntityIdValue;
use App\Services\SparqlQueryService;
use Addwiki\Mediawiki\DataModel\EditInfo;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementId;

class AddDepicts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $answerId;
    private $instancesOfAndSubclassesOf;
    private ?string $rank;
    private bool $removeSuperclasses;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        int $answerId,
        ?string $rank = null,
        bool $removeSuperclasses = false
    )
    {
        $this->answerId = $answerId;
        $this->rank = $rank;
        $this->removeSuperclasses = $removeSuperclasses;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $this->handleInner();
        } catch (\Exception $e) {
            $jobDetails = [
                'job_class' => self::class,
                'answer_id' => $this->answerId,
                'rank' => $this->rank,
                'remove_superclasses' => $this->removeSuperclasses,
            ];
            
            \Log::error("AddDepicts job failed with exception", [
                'job_details' => $jobDetails,
                'exception' => $e,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            throw $e; // Re-throw to maintain job failure behavior
        }
    }

    /**
     * Execute the job inner logic.
     *
     * @return void
     */
    private function handleInner()
    {
        $answer = Answer::with('question')->with('user')->with('question.edit')->find($this->answerId);
        $question = $answer->question;
        $user = $answer->user;

        // Edit already happened in this system
        if( $question->edit->count() > 0 ) {
            //return;
        }

        if($user->token === null || $user->token_secret === null) {
            // TODO deal with this?
            \Log::error("No token for user (must be logged out)");
            return;
        }

        // And write to some mw instance
        // TODO make this actually hit mediainfo?
        $mwAuth = new \Addwiki\Mediawiki\Api\Client\Auth\OAuthOwnerConsumer(
            config('services.mediawiki.identifier'),
            config('services.mediawiki.secret'),
            $user->token,
            $user->token_secret
        );

        $wm = new WikimediaFactory();
        $mwApi = $wm->newMediawikiApiForDomain("commons.wikimedia.org", $mwAuth);
        $wbServices = $wm->newWikibaseFactoryForDomain("commons.wikimedia.org", $mwAuth);

        $mid = new MediaInfoId( $question->properties['mediainfo_id'] );
        $depictsProperty = new PropertyId( 'P180' );
        $depictsValue = new ItemId( $question->properties['depicts_id'] );


        // TODO code reuse section start
        /** @var \Wikibase\MediaInfo\DataModel\MediaInfo $entity */
        $entity = $wbServices->newEntityLookup()->getEntity( $mid );
        if($entity === null) {
            // TODO could still create statements for this condition...
            \Log::error("{$mid} MediaInfo entity not found");
            return;
        }
        $this->instancesOfAndSubclassesOf = $this->instancesOfAndSubclassesOf( $depictsValue->getSerialization() );
        
        // Get parent classes (superclasses) if removal option is enabled
        $parentClasses = [];
        if($this->removeSuperclasses) {
            $parentClasses = $this->getParentClasses( $depictsValue->getSerialization() );
        }
        
        $foundDepicts = false;
        $superclassStatements = [];
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
            
            // Collect superclass statements for potential removal
            if( $this->removeSuperclasses && in_array( $entityId->getSerialization(), $parentClasses ) ) {
                $superclassStatements[] = $statement;
            }
        }
        // TODO code reuse section end

        if($foundDepicts !== false) {
            \Log::info("{$mid} already has {$foundDepicts} depicts");
            return;
        } else {
            // Remove superclass statements if option is enabled and we found any
            if($this->removeSuperclasses && !empty($superclassStatements)) {
                $editInfo = new EditInfo("Removing superclass depicts before adding more specific one");
                foreach($superclassStatements as $superclassStatement) {
                    \Log::info("Removing superclass depicts: " . $superclassStatement->getMainSnak()->getDataValue()->getEntityId()->getSerialization());
                    $wbServices->newStatementRemover()->remove( $superclassStatement, $editInfo );
                    sleep(1); // Sleep 1 second between each edit
                }
            }
            
            // Build custom summary if manual
            $editInfo = null;
            if (!empty($question->properties['manual']) && !empty($question->properties['category']) && !empty($question->properties['depicts_id'])) {
                $cat = $question->properties['category'];
                $qid = $question->properties['depicts_id'];
                $editInfo = new EditInfo("From custom inputs [[:$cat]] and [[wikidata:$qid]]");
            }
            $snak = new PropertyValueSnak( $depictsProperty, new EntityIdValue( $depictsValue ) );
            $createdClaimGuid = $wbServices->newStatementCreator()->create( $snak, $mid, $editInfo );

            sleep(1); // Sleep 1 second between each edit

            $mwServices = new MediawikiFactory( $mwApi );

            $pageIdentifier = new PageIdentifier( null, str_replace( 'M', '', $mid->getSerialization() ) );
            
            // Track revision IDs for all edits made
            $revisionIds = [];
            
            // Get revision ID after removals (if any were done)
            if($this->removeSuperclasses && !empty($superclassStatements)) {
                $revId = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier )->getRevisions()->getLatest()->getId();
                $revisionIds[] = (int)$revId;
            }
            
            // Get revision ID after adding the new statement
            $revId = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier )->getRevisions()->getLatest()->getId();
            $revisionIds[] = (int)$revId;

            if ($this->rank === 'preferred' && $createdClaimGuid !== null) {
                // Prepend preferred rank note to summary
                $preferredPrefix = 'Marking prominent (preferred rank). ';
                if ($editInfo !== null) {
                    $summary = $editInfo->getSummary();
                    $editInfo = new EditInfo($preferredPrefix . $summary, true);
                } else {
                    $editInfo = new EditInfo($preferredPrefix, true);
                }
                $statement = new Statement($snak, null, null, $createdClaimGuid);
                $statement->setRank(Statement::RANK_PREFERRED);
                $statementSetter = $wbServices->newStatementSetter();
                $statementSetter->set($statement, $editInfo);

                sleep(1); // Sleep 1 second between each edit

                $revId2 = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier )->getRevisions()->getLatest()->getId();
                $revisionIds[] = (int)$revId2;
            }

            // Create Edit records for all revisions
            foreach($revisionIds as $revId) {
                Edit::create([
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'revision_id' => $revId,
                ]);
            }
        }
    }

    private function instancesOfAndSubclassesOf( string $itemId ) : array {
        $sparqlService = new SparqlQueryService();
        return $sparqlService->instancesOfAndSubclassesOf($itemId);
    }

    private function getParentClasses( string $itemId ) : array {
        $sparqlService = new SparqlQueryService();
        $parentClassesWithLabels = $sparqlService->getParentClassesWithLabels($itemId);
        
        // Extract just the QIDs from the result
        $parentQids = [];
        foreach($parentClassesWithLabels as $item) {
            $parentQids[] = $item['qid'];
        }
        
        return $parentQids;
    }
}
