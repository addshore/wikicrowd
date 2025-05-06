<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Question;
use App\Models\Answer;
use App\Jobs\AddDepicts;
use App\Jobs\SwapDepicts;
use App\Jobs\AddAlias;

class AnswerController extends Controller
{
    /**
     * Store a newly created answer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|exists:App\Models\Question,id',
            'answer' => 'required|in:yes,no,skip',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $validator->errors()], 422);
        }

        $questionId = $request->input('question_id');
        $answerValue = $request->input('answer');
        $user = Auth::user();

        // Find the question and load necessary relationships
        $question = Question::with('group.parentGroup')->find($questionId);

        if (!$question) {
            // This case should ideally be caught by 'exists' validation, but as a safeguard:
            return response()->json(['message' => 'Question not found.'], 404);
        }

        $storedAnswer = Answer::create([
            'question_id' => $question->id,
            'user_id' => $user->id,
            'answer' => $answerValue,
        ]);

        // Dispatch the edit job if the answer is 'yes'
        if ($answerValue === 'yes') {
            // Ensure group and parentGroup are loaded. The 'with' above should handle this.
            if ($question->group && $question->group->parentGroup) {
                $parentGroupName = $question->group->parentGroup->name;
                if ($parentGroupName === 'depicts') {
                    dispatch(new AddDepicts($storedAnswer->id));
                } elseif ($parentGroupName === 'depicts-refine') {
                    dispatch(new SwapDepicts($storedAnswer->id));
                } elseif ($parentGroupName === 'aliases') {
                    dispatch(new AddAlias($storedAnswer->id));
                }
            } else {
                // Log if group or parentGroup is missing, as jobs might not be dispatched correctly
                \Log::warning("Question {$question->id} is missing group or parentGroup information. Answer ID: {$storedAnswer->id}");
            }
        }

        return response()->json([
            'message' => 'Answer submitted successfully.',
            'answer' => $storedAnswer
        ], 201);
    }
}
