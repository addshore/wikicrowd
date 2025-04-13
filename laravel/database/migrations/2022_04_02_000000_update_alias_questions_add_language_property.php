<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\QuestionGroup;
use App\Models\Question;

/**
 * One off migration to enable the fix for https://github.com/addshore/wikicrowd/issues/45 to propogate to all previously generated questions.
 */
class UpdateAliasQuestionsAddLanguageProperty extends Migration
{

    private $aliasLanguagesAtThisPointInTime = [
        'en',
        'de',
        'pl',
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
                $question->properties = array_merge($question->properties, ['language' => $lang]);
                $question->save();
            }
        }
    }

    public function down()
    {
        foreach ( $this->aliasLanguagesAtThisPointInTime as $lang ) {
            $questionGroup = QuestionGroup::where('name', '=', 'aliases/' . $lang)->first();
            if($questionGroup === null) {
                continue;
            }
            $questions = Question::where('question_group_id', '=', $questionGroup->id)->get();
            foreach( $questions as $question ) {
                $question->properties = $this->arrayWithKeyRemoved( $question->properties, 'language' );
                $question->save();
            }
        }
    }

    private function arrayWithKeyRemoved( array $array, string $key ) : array {
        unset($array[$key]);
        return $array;
    }

}
