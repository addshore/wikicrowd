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

class SwapDepicts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $answerId;
    private $instancesOfAndSubclassesOf;
    private ?string $rank;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        int $answerId,
        ?string $rank = null
    )
    {
        $this->answerId = $answerId;
        $this->rank = $rank;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
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
        $depictsValueOld = new ItemId( $question->properties['old_depicts_id'] );
        $depictsValue = new ItemId( $question->properties['depicts_id'] );


        // TODO code reuse section start
        /** @var \Wikibase\MediaInfo\DataModel\MediaInfo $entity */
        $entity = $wbServices->newEntityLookup()->getEntity( $mid );
        if($entity === null) {
            // TODO could still create statements for this condition...
            \Log::error("MediaInfo entity not found");
            return;
        }
        $this->instancesOfAndSubclassesOf = $this->instancesOfAndSubclassesOf( $depictsValue->getSerialization() );


        $foundDepicts = [
            'old-exact' => 0,
            'new-exact' => 0,
            'new-moreSpecific' => 0,
        ];
        $oldStatement = null;
        foreach( $entity->getStatements()->getByPropertyId( $depictsProperty )->toArray() as $statement ) {
            // Skip non value statements
            if( $statement->getMainSnak()->getType() !== 'value' ) {
                continue;
            }

            $entityId = $statement->getMainSnak()->getDataValue()->getEntityId();

            if( $entityId->equals( $depictsValue ) ) {
                $foundDepicts['new-exact']++;
                continue;
            }
            if( $entityId->equals( $depictsValueOld ) ) {
                $foundDepicts['old-exact']++;
                $oldStatement = $statement;
                continue;
            }
            if( in_array( $entityId->getSerialization(), $this->instancesOfAndSubclassesOf ) ) {
                $foundDepicts['new-moreSpecific']++;
                continue;
            }
        }

        if($foundDepicts['new-exact'] > 0) {
            \Log::info("Exact new depicts found");
            return;
        }
        if($foundDepicts['new-moreSpecific'] > 0) {
            \Log::info("More specific depicts already found");
            return;
        }

        if($foundDepicts['old-exact'] !== 1) {
            \Log::info("BAIL: Not found exactly 1 of the depicts that we are looking for");
            return;
        }

        $pageIdentifier = new PageIdentifier( null, str_replace( 'M', '', $mid->getSerialization() ) );

        // Build custom summary if manual
        $editInfo = null;
        if (!empty($question->properties['manual']) && !empty($question->properties['category']) && !empty($question->properties['depicts_id'])) {
            $cat = $question->properties['category'];
            $qid = $question->properties['depicts_id'];
            $editInfo = new EditInfo("From custom inputs [[:$cat]] and [[wikidata:$qid]]");
        }

        $wbServices->newStatementRemover()->remove( $oldStatement, $editInfo );

        // TODO fix the DUMB way of getting the new revision IDs here..
        // TODO this probably requires fixes in addwiki (if we are to continue using it)
        $mwServices = new MediawikiFactory( $mwApi );
        $revId1 = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier )->getRevisions()->getLatest()->getId();

        $snak = new PropertyValueSnak( $depictsProperty, new EntityIdValue( $depictsValue ) );
        $newlyCreatedClaimGuid = $wbServices->newStatementCreator()->create( $snak, $mid, $editInfo);
        $revId2 = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier )->getRevisions()->getLatest()->getId();
    
        if ($this->rank === 'preferred' && $newlyCreatedClaimGuid !== null) {
            // Prepend preferred rank note to summary
            $preferredPrefix = 'Marking prominent (preferred rank). ';
            if ($editInfo !== null) {
                $summary = $editInfo->getSummary();
                $editInfo = new EditInfo($preferredPrefix . $summary, true);
            } else {
                $editInfo = new EditInfo($preferredPrefix, true);
            }
            $statement = new Statement($snak, null, null, $newlyCreatedClaimGuid);
            $statement->setRank(Statement::RANK_PREFERRED);
            $statementSetter = $wbServices->newStatementSetter();
            $statementSetter->set($statement, $editInfo);
            $revId3 = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier )->getRevisions()->getLatest()->getId();
        }

        Edit::create([
            'question_id' => $question->id,
            'user_id' => $user->id,
            'revision_id' => (int)$revId1,
        ]);
        Edit::create([
            'question_id' => $question->id,
            'user_id' => $user->id,
            'revision_id' => (int)$revId2,
        ]);
        if (isset($revId3)) {
            Edit::create([
                'question_id' => $question->id,
                'user_id' => $user->id,
                'revision_id' => (int)$revId3,
            ]);
        }
    }

    private function instancesOfAndSubclassesOf( string $itemId ) : array {
        $sparqlService = new SparqlQueryService();
        return $sparqlService->instancesOfAndSubclassesOf($itemId);
    }
}
