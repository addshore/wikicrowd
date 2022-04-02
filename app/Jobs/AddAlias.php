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
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\AliasGroup;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;

class AddAlias implements ShouldQueue
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
            die("No token for user (must be logged out)");
        }

        $mwAuth = new \Addwiki\Mediawiki\Api\Client\Auth\OAuthOwnerConsumer(
            config('services.mediawiki.identifier'),
            config('services.mediawiki.secret'),
            $user->token,
            $user->token_secret
        );

        $itemIdString = $question->properties['item'];
        $inUseLanguage = $question->properties['language'];

        $wm = new WikimediaFactory();
        $mwApi = $wm->newMediawikiApiForDomain("www.wikidata.org", $mwAuth);
        $wbServices = $wm->newWikibaseFactoryForDomain("www.wikidata.org", $mwAuth);

        $item = $wbServices->newItemLookup()->getItemForId(new ItemId($itemIdString));
        if($item === null) {
            die("Item not found");
        }

        if ( $item->getAliasGroups()->hasGroupForLanguage($inUseLanguage) ) {
            $aliasGroup = $item->getAliasGroups()->getByLanguage($inUseLanguage);
        } else {
            $aliasGroup = new AliasGroup($inUseLanguage);
        }

        $existing = $aliasGroup->getAliases();
        if( $item->getLabels()->hasTermForLanguage($inUseLanguage) ) {
            $existing[] = $item->getLabels()->getByLanguage($inUseLanguage)->getText();
        }
        $existing = array_map('strtolower', $existing);

        // Stop if label or alias exists
        if(in_array(strtolower($question->properties['suggestion']), $existing)) {
            return;
        }

        $result = $mwApi->request( ActionRequest::simplePost(
            'wbsetaliases',
            [
                'id' => $itemIdString,
                'language' => $inUseLanguage,
                'add' => $question->properties['suggestion'],
                'token' => $mwApi->getToken(),
            ]
        ) );

        $success = $result['success'];
        if(!$success) {
            die("Failed to add alias");
        }

        Edit::create([
            'question_id' => $question->id,
            'user_id' => $user->id,
            'revision_id' => (int)$result['entity']['lastrevid'],
        ]);
	}
}
