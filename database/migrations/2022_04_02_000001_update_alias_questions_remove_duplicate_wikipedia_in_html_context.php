<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\QuestionGroup;
use App\Models\Question;

/**
 * One off migration to enable the fix for https://github.com/addshore/wikicrowd/issues/17 to propogate to all previously generated questions.
 */
class UpdateAliasQuestionsRemoveDuplicateWikipediaInHtmlContext extends Migration
{

    private $aliasLanguagesAtThisPointInTime = [
        'en',
        'de',
        'pl',
    ];

    private $duplicateStringsAtThisPointInTime = [
        '<p>English Wikipedia:</p></br>',
        '<p>German Wikipedia:</p></br>',
        '<p>Polish Wikipedia:</p></br>',
    ];

    public function up()
    {
        foreach ( $this->aliasLanguagesAtThisPointInTime as $lang ) {
            $questionGroup = QuestionGroup::where('name', '=', 'aliases/' . $lang)->first();
            if($questionGroup === null) {
                continue;
            }
            $questions = Question::where('question_group_id', '=', $questionGroup->id)->get();
            foreach( $questions as $question ) {
                $question->properties = $this->arrayWithCallbackApplieOnKey($question->properties, 'html_context', function($htmlContext) {
                    foreach ( $this->duplicateStringsAtThisPointInTime as $duplicateString ) {
                        while ( strpos($htmlContext, $duplicateString.$duplicateString) !== false ) {
                            $htmlContext = str_replace($duplicateString.$duplicateString, $duplicateString, $htmlContext);
                        }
                    }
                    return $htmlContext;
                });
                $question->save();
            }
        }
    }

    public function down()
    {
        // No way back for this one...
    }

    private function arrayWithCallbackApplieOnKey( array $array, string $key, $callback ) : array {
        $array[$key] = $callback($array[$key]);
        return $array;
    }

}
