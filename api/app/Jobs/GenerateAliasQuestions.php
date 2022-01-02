<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Question;
use App\Models\QuestionGroup;
use Wikibase\DataModel\Entity\ItemId;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;

/**
 * Inspired by https://bitbucket.org/magnusmanske/wikidata-game/src/master/tools/bold_aliases.php
 */
class GenerateAliasQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const WIKIDATA = 'www.wikidata.org';

    private $limit;
    private $done = 0;
    private $targetGroup;

    public function __construct( int $limit )
    {
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
        while($this->done < $this->limit) {
            $this->handleIteration();
        }
    }

    private function handleIteration() {

        $wmFactory = new \Addwiki\Wikimedia\Api\WikimediaFactory();
        $enwikiApi = $wmFactory ->newMediawikiApiForDomain( 'en.wikipedia.org' );
        $wbServices = $wmFactory->newWikibaseFactoryForDomain( self::WIKIDATA );

        // https://en.wikipedia.org/w/api.php?action=query&prop=extracts|pageterms&exlimit=20&exintro=1&generator=random&grnnamespace=0&grnlimit=20
        $listResponse =  $enwikiApi->request(
            ActionRequest::simpleGet(
                'query',
                [
                    'prop' => 'extracts|pageterms',
                    'exlimit' => '20',
                    'exintro' => '1',
                    'generator' => 'random',
                    'grnnamespace' => '0',
                    'grnlimit' => '20',
                ]
            )
        );
        foreach( $listResponse['query']['pages'] as $pageData ) {

            $h = $pageData['extract'];
            $h = preg_replace ( '/\s+/m' , ' ' , $h ) ;
            if ( !preg_match_all ( '/<b>\s*(.+?)\s*<\/b>/' , $h , $m ) ) continue ;

            $label = $pageData['terms']['label'][0] ?? null ;
            $aliases = $pageData['terms']['alias'] ?? [];
            $existing = array_merge( [ $label ] , $aliases );
            $existing = array_map('strtolower', $existing);

            $candidates = [];
            foreach ( $m[1] as $alias ) {
                if ( preg_match ( '/</' , $alias ) ) continue ; // HTML
                if ( in_array(strtolower($alias), $existing) ) continue ;
                $candidates[strtolower($alias)] = $alias ;
            }

            if ( count($candidates) === 0 ) continue ;

            $itemRevision = $wbServices->newRevisionGetter()->getFromSiteAndTitle( 'enwiki', $pageData['title'] );
            if ( $itemRevision === null ){
                echo "No item revision for {$pageData['title']}\n";
                continue;
            }
            $item = $itemRevision->getContent()->getData();

            foreach( $candidates as $candidate ) {
                $uniq = $this->uniqueID( $item->getId(), $candidate );

                if (Question::where('question_group_id', '=', $this->targetGroup)
                    ->where('unique_id', '=', $uniq)
                    ->exists()
                ) {
                    // question already found
                    echo "Question exists\n";
                    continue;
                }

                echo "Creating question for {$candidate}\n";
                Question::create([
                    'question_group_id' => $this->targetGroup,
                    'unique_id' => $uniq,
                    'properties' => [
                        'item' => $item->getId()->getSerialization(),
                        'label' => $label,
                        'aliases' => $aliases,
                        'suggestion' => $candidate,
                        'html_context' => $h,
                    ]
                ]);
                $this->done++;
            }
        }
    }

    private function createQuestionGroups() {
        $parentGroup = QuestionGroup::firstOrCreate(
            ['name' => 'aliases'],
            [
                'display_name' => 'Aliases',
                'layout' => 'grid',
            ]
        );
        // TODO at some point don't just do English
        $subGroup = QuestionGroup::firstOrCreate(
            ['name' => 'aliases/en'],
            [
                'display_name' => "English",
                'layout' => 'html-focus',
                'parent' => $parentGroup->id,
            ]
        );
        $this->targetGroup = $subGroup->id;
    }

    private function uniqueID( ItemId $itemId, string $suggested ) : string {
        return $itemId->getSerialization() . '/aliases/en/' . $suggested;
    }

}
