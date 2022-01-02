<?php

namespace App\Http\Controllers;

use App\Models\Question;

class QuestionController extends Controller
{

    public function show()
    {
        return view('edit', [
            // Find questions with no previous answer
            // TODO don't ignore groups
            'qu' => Question::doesntHave('answer')->with('group')->first(),
        ]);
    }

}
