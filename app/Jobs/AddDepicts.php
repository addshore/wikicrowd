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
use Illuminate\Support\Facades\Cache;
use Addwiki\Mediawiki\DataModel\EditInfo;

class AddDepicts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $answerId;
    private $instancesOfAndSubclassesOf;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        int $answerId
    )
    {
        $this->answerId = $answerId;
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
        // TODO code reuse section end

        if($foundDepicts !== false) {
            \Log::info("{$mid} already has {$foundDepicts} depicts");
            return;
        } else {
            // Build custom summary if manual
            $editInfo = null;
            if (!empty($question->properties['manual']) && !empty($question->properties['category']) && !empty($question->properties['depicts_id'])) {
                $cat = $question->properties['category'];
                $qid = $question->properties['depicts_id'];
                $editInfo = new EditInfo("From custom inputs [[:$cat]] and [[wikidata:$qid]]");
            }
            $wbServices->newStatementCreator()->create(
                new PropertyValueSnak( $depictsProperty, new EntityIdValue( $depictsValue ) ),
                $mid,
                $editInfo
            );

            $mwServices = new MediawikiFactory( $mwApi );

            $pageIdentifier = new PageIdentifier( null, str_replace( 'M', '', $mid->getSerialization() ) );
            $page = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier );
            $revId = $page->getRevisions()->getLatest()->getId();

            Edit::create([
                'question_id' => $question->id,
                'user_id' => $user->id,
                'revision_id' => (int)$revId,
            ]);
        }
    }

    private function instancesOfAndSubclassesOf( string $itemId ) : array {
        // TODO code reuse
        return Cache::remember('instancesOfAndSubclassesOf:' . $itemId, 60*2, function () use ($itemId) {
            $query = (new \Addwiki\Wikibase\Query\WikibaseQueryFactory(
                "https://query.wikidata.org/sparql",
                PrefixSets::WIKIDATA
            ))->newWikibaseQueryService();
            $result = $query->query( "SELECT DISTINCT ?i WHERE{?i wdt:P31/wdt:P279*|wdt:P279/wdt:P279* wd:{$itemId} }" );

            $ids = [];
            foreach ( $result['results']['bindings'] as $binding ) {
                $ids[] = $this->getLastPartOfUrlPath( $binding['i']['value'] );
            }
            return $ids;
        });
    }

    private function getLastPartOfUrlPath( string $urlPath ): string {
        // TODO code reuse
		// Assume that the last part is always the ID?
		$parts = explode( '/', $urlPath );
		return end( $parts );
	}
}
