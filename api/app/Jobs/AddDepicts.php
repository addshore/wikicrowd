<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Answer;
use App\Models\Edit;

class AddDepicts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $answerId;

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
        // TODO make sure an edit didnt already happen?
        $answer = Answer::with('question')->with('user')->find($this->answerId);
        $question = $answer->question;
        $user = $answer->user;

        if($user->token === null || $user->token_secret === null) {
            // TODO deal with this?
            die("No token for user (must be logged out)");
        }

        // And write to some mw instance
        // TODO make this actually hit mediainfo?
        $mwAuth = new \Addwiki\Mediawiki\Api\Client\Auth\OAuthOwnerConsumer(
            config('services.mediawiki.identifier'),
            config('services.mediawiki.secret'),
            $user->token,
            $user->token_secret
        );

        // TODO commons
        $mw = \Addwiki\Mediawiki\Api\Client\MediaWiki::newFromEndpoint( 'https://addshore-alpha.wiki.opencura.com/w/api.php', $mwAuth );
        $mwServices = new \Addwiki\Mediawiki\Api\MediawikiFactory( $mw->action() );

        $pageIdentifier = new \Addwiki\Mediawiki\DataModel\PageIdentifier( new \Addwiki\Mediawiki\DataModel\Title( 'From Laravel Via OAuth' ) );
        $content = new \Addwiki\Mediawiki\DataModel\Content( "Edit made?: " . json_encode(['user' => $user->username, 'mediainfo' => $question->properties['mediainfo_id'], 'depicts' => $question->properties['depicts_id']]) );

        $page = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier );

        // Skip if page is already correct TODO change to checking if depicts is already set..
        if($page->getRevisions()->getLatest()->getContent()->getData() === $content->getData()) {
            return;
        }

        $result = $mwServices->newRevisionSaver()->save( new \Addwiki\Mediawiki\DataModel\Revision(
            $content,
            $pageIdentifier
        ) );

        if(!$result) {
            throw new \RuntimeException("Something went wrong making the edit");
        }

        $page = $mwServices->newPageGetter()->getFromPageIdentifier( $pageIdentifier );

        Edit::create([
            'question_id' => $question->id,
            'user_id' => $user->id,
            'revision_id' => (int)$page->getRevisions()->getLatest()->getId(),
        ]);

    }
}
