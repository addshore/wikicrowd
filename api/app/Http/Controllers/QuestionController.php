<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionGroup;

class QuestionController extends Controller
{

    public function showGroupUnanswered($groupName) {
        $question = Question::where('question_group_id', '=', QuestionGroup::where('name','=',$groupName)->first()->id)->doesntHave('answer')->with('group')->inRandomOrder()->first();

        if($question) {
            return view($question->group->layout, [
                // Find questions with no previous answer
                // TODO don't ignore groups
                'qu' => $question,
            ]);
        }

        return view('no-question');
    }

}
