<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\QuestionGroup;
use App\Models\Question;

/**
 * PT 2: One off migration to enable alignment of previously generated questions for the glider ID fix https://github.com/addshore/wikicrowd/commit/7949f43170ad8ef4b5a4589abde5adb511a36e22
 */
class UpdateDepictsQuestionsForGliderItemIdFixAgain extends Migration
{

    private $oldId = 'Q8492796';
    private $newId = 'Q2165278';

    public function up()
    {
        // At the time of writing this there was only depicts questions, so only rename the refine group!
        $depictsRefineGroup = QuestionGroup::where('name', '=', 'depicts-refine/' . $this->oldId)->first();
        if ($depictsRefineGroup === null) {
            return;
        }
        $depictsRefineGroup->name = str_replace($this->oldId,$this->newId, $depictsRefineGroup->name);
        $depictsRefineGroup->save();
    }

    public function down()
    {
        // No way back for this one...
    }

}
