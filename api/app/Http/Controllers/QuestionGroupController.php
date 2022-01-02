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
        ]);
    }

}
