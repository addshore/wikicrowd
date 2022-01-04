<?php

namespace App\Http\Controllers;

use App\Models\QuestionGroup;

class QuestionGroupController extends Controller
{

    public function getTopLevelGroups() {
        return QuestionGroup::whereNull('parent')->with(['subGroups' => function($query){
            $query->withCount(['question as unanswered' => function($query){
                $query->doesntHave('answer');
            }])
            ->having('unanswered', '>', 0);
        }])->get();
    }

    public function showTopLevelGroups()
    {
        return view('groups', [
            'groups' => $this->getTopLevelGroups(),
            'stats' => [
                'questions' => \App\Models\Question::count(),
                'answers' => \App\Models\Answer::count(),
                'edits' => \App\Models\Edit::count(),
                'users' => \App\Models\User::count(),
            ]
        ]);
    }

}
