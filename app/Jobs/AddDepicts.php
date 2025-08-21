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
use Illuminate\Contracts\Queue\ShouldBeUnique;

class AddDepicts implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $answerId;
    private ?string $rank;
    private bool $removeSuperclasses;
    private string $mediainfoId;
    private string $depictsId;
    private array $logContext;
    private $instancesOfAndSubclassesOf;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        int $answerId,
        string $mediainfoId,
        string $depictsId,
        ?string $rank = null,
        bool $removeSuperclasses = false
    ) {
        $this->answerId = $answerId;
        $this->rank = $rank;
        $this->removeSuperclasses = $removeSuperclasses;
        $this->mediainfoId = $mediainfoId;
        $this->depictsId = $depictsId;

        // Initialize logging context
        $this->logContext = [
            'answer' => $this->answerId,
            'rank' => $this->rank,
            'rm_superclasses' => $this->removeSuperclasses,
            'mediainfo_id' => $this->mediainfoId,
            'depicts_id' => $this->depictsId,
        ];
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->mediainfoId . '-' . $this->depictsId;
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
            \Log::error("AddDepicts job failed with exception", [
                'context' => $this->logContext,
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

        // Add question context
        $this->logContext['question'] = $question->id;
        $this->logContext['user'] = $user->id;

        // Edit already happened in this system
        if( $question->edit->count() > 0 ) {
            //return;
        }

        if($user->token === null || $user->token_secret === null) {
            // TODO deal with this?
            \Log::error("No token for user (must be logged out)", $this->logContext);
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

        $mid = new MediaInfoId( $this->mediainfoId );
        $depictsProperty = new PropertyId( 'P180' );
        $depictsValue = new ItemId( $this->depictsId );

        $this->logContext['mid'] = $mid->getSerialization();
        $this->logContext['qid'] = $depictsValue->getSerialization();

        // TODO code reuse section start
        /** @var \Wikibase\MediaInfo\DataModel\MediaInfo $entity */
        $entity = $wbServices->newEntityLookup()->getEntity( $mid );
        if($entity === null) {
            // If entity is not found, skip existing statement checks and proceed to add the statement if possible
            $parentClasses = [];
            $foundDepicts = false;
            $superclassStatements = [];
        } else {
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
        }
        // TODO code reuse section end

        if($foundDepicts !== false) {
            $this->logContext['found_depicts_type'] = $foundDepicts;
            \Log::info("MediaInfo already has depicts", $this->logContext);
            return;
        } else {
            // Remove superclass statements if option is enabled and we found any
            if($this->removeSuperclasses && !empty($superclassStatements)) {
                $this->logContext['superclass_statements'] = count($superclassStatements);
                $editInfo = new EditInfo("Removing superclass depicts before adding more specific one");
                foreach($superclassStatements as $superclassStatement) {
                    $superclassId = $superclassStatement->getMainSnak()->getDataValue()->getEntityId()->getSerialization();
                    $this->logContext['removing_superclass'] = $superclassId;
                    \Log::info("Removing superclass depicts", $this->logContext);
                    try {
                        $wbServices->newStatementRemover()->remove( $superclassStatement, $editInfo );
                    } catch (\Throwable $e) {
                        \Log::error("Failed to remove superclass statement", array_merge($this->logContext, [
                            'operation' => 'remove_superclass',
                            'superclass_id' => $superclassId,
                            'exception' => $e,
                            'message' => $e->getMessage(),
                        ]));
                        throw $e;
                    }
                    unset($this->logContext['removing_superclass']);
                    sleep(1); // Sleep 1 second between each edit
                }
            }
            
            // Build custom summary if manual
            $editInfo = null;
            if (!empty($question->properties['manual']) && !empty($question->properties['category']) && !empty($question->properties['depicts_id'])) {
                $cat = $question->properties['category'];
                if (stripos($cat, 'Category:') !== 0) {
                    $cat = 'Category:' . $cat;
                }
                $qid = $question->properties['depicts_id'];
                $this->logContext['manual_cat'] = $cat;
                $editInfo = new EditInfo("From custom inputs [[:$cat]] and [[wikidata:$qid]]");
            }
            
            \Log::info("Creating new depicts statement", $this->logContext);
            $snak = new PropertyValueSnak( $depictsProperty, new EntityIdValue( $depictsValue ) );
            try {
                $createdClaimGuid = $wbServices->newStatementCreator()->create( $snak, $mid, $editInfo );
            } catch (\Throwable $e) {
                \Log::error("Failed to create new depicts statement", array_merge($this->logContext, [
                    'operation' => 'create_statement',
                    'exception' => $e,
                    'message' => $e->getMessage(),
                ]));
                throw $e;
            }

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
                \Log::info("Setting statement to preferred rank", $this->logContext);
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
                try {
                    $statementSetter->set($statement, $editInfo);
                } catch (\Throwable $e) {
                    \Log::error("Failed to set statement rank to preferred", array_merge($this->logContext, [
                        'operation' => 'set_statement_rank',
                        'statement_guid' => $createdClaimGuid,
                        'exception' => $e,
                        'message' => $e->getMessage(),
                    ]));
                    throw $e;
                }
                sleep(1); // Sleep 1 second between each edit

                $revId2 = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier )->getRevisions()->getLatest()->getId();
                $revisionIds[] = (int)$revId2;
            }

            // Create Edit records for all revisions
            $this->logContext['revids'] = $revisionIds;
            $this->logContext['revs'] = count($revisionIds);
            \Log::info("Creating Edit records for revisions", $this->logContext);
            
            foreach($revisionIds as $revId) {
                Edit::create([
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'revision_id' => $revId,
                ]);
            }
            
            \Log::info("AddDepicts job completed successfully", $this->logContext);
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
