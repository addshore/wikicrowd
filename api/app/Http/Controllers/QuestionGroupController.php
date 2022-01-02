<?php

namespace App\Http\Controllers;

use App\Models\QuestionGroup;

class QuestionGroupController extends Controller
{

    public function showTopLevelGroups()
    {
        return view('groups', [
            'groups' => QuestionGroup::whereNull('parent')->with(['subGroups' => function($query){
                $query->withCount(['question as unanswered' => function($query){
                    $query->doesntHave('answer');
                }])
                ->having('unanswered', '>', 0);
            }])->get(),
            'stats' => [
                'questions' => \App\Models\Question::count(),
                'answers' => \App\Models\Answer::count(),
                'edits' => \App\Models\Edit::count(),
                'users' => \App\Models\User::count(),
            ]
        ]);
    }

}
