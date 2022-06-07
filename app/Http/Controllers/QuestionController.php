<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionGroup;

class QuestionController extends Controller
{

    private function getGroupUnanswered($groupName) {
        $question = Question::where('question_group_id', '=', QuestionGroup::where('name','=',$groupName)->first()->id)->doesntHave('answer')->with('group')->inRandomOrder()->first();
        return $question ? $question : null;
    }

    private function getGroupDesiredIdUnanswered($groupName, $desiredId) {
        $question = Question::where('question_group_id', '=', QuestionGroup::where('name','=',$groupName)->first()->id)->doesntHave('answer')->with('group')->where('id', '=', $desiredId)->first();
        return $question ? $question : null;
    }

    private function getGroupNotIdUnanswered($groupName, $notId) {
        $question = Question::where('question_group_id', '=', QuestionGroup::where('name','=',$groupName)->first()->id)->doesntHave('answer')->with('group')->where('id', '!=', $notId)->inRandomOrder()->first();
        return $question ? $question : null;
    }

    private function displayQuestionView($question, $nextQuestion) {
        return view($question->group->layout, [
            // TODO don't ignore groups
            'qu' => $question,
            'next' => $nextQuestion,
        ]);
    }

    private function showGroupUnanswered($groupName) {
        $question = $this->getGroupUnanswered($groupName);
        if (!$question) {
            return view('no-question');
        }
        return $this->displayQuestionView($question, $this->getGroupNotIdUnanswered($groupName, $question->id));
    }

    public function showGroupDesiredOrUnanswered($groupName, $desiredId = null) {
        if(!$desiredId) {
            return $this->showGroupUnanswered($groupName);
        }
        $question = $this->getGroupDesiredIdUnanswered($groupName, $desiredId);
        if (!$question) {
            return redirect('/questions/' . $groupName);
        }
        return $this->displayQuestionView($question, $this->getGroupNotIdUnanswered($groupName, $question->id));
    }

}
