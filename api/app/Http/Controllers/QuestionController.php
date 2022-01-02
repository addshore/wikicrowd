<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionGroup;

class QuestionController extends Controller
{

    public function showGroupUnanswered($groupName) {
        return view('question', [
            // Find questions with no previous answer
            // TODO don't ignore groups
            'qu' => Question::where('question_group_id', '=', QuestionGroup::where('name','=',$groupName)->first()->id)->doesntHave('answer')->with('group')->inRandomOrder()->first(),
        ]);
    }

}
