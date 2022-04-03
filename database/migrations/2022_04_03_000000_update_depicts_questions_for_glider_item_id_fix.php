<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\QuestionGroup;
use App\Models\Question;

/**
 * PT 1: One off migration to enable alignment of previously generated questions for the glider ID fix https://github.com/addshore/wikicrowd/commit/7949f43170ad8ef4b5a4589abde5adb511a36e22
 */
class UpdateDepictsQuestionsForGliderItemIdFix extends Migration
{

    private $oldId = 'Q8492796';
    private $newId = 'Q2165278';

    public function up()
    {
        $depictsGroup = QuestionGroup::where('name', '=', 'depicts/' . $this->oldId)->first();
        $depictsGroup->name = str_replace($this->oldId,$this->newId, $depictsGroup->name);
        $depictsGroup->save();
        if($depictsGroup !== null) {
            $questions = Question::where('question_group_id', '=', $depictsGroup->id)->get();
            foreach( $questions as $question ) {
                $question->unique_id = str_replace($this->oldId,$this->newId, $question->unique_id);
                $question->properties = $this->arrayWithCallbackApplieOnKey($question->properties, 'depicts_id', function($did) {
                    return $this->newId;
                });
                $question->save();
            }
        }

        // At the time of writing this there was only depicts questions, so only rename the refine group!
        // XXX: Had a bug, so moved to the next migration... Lol :D
        // $depictsRefineGroup = QuestionGroup::where('name', '=', 'depicts-refine/' . $this->oldId)->first();
        // $depictsGroup->name = str_replace($this->oldId,$this->newId, $depictsGroup->name);
        // $depictsRefineGroup->save();
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
