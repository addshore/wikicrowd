<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  string  $groupName
     * @param  int|null  $desiredId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $groupName, int $desiredId = null)
    {
        $user = Auth::user();
        $question = null;

        $group = QuestionGroup::where('name', '=', $groupName)->first();

        if (!$group) {
            return response()->json(['message' => 'Question group not found'], 404);
        }

        if ($desiredId) {
            $question = Question::where('question_group_id', '=', $group->id)
                ->where('id', '=', $desiredId)
                ->with('group.parentGroup')
                ->first();
        }

        if (!$question) {
            $question = Question::where('question_group_id', '=', $group->id)
                ->doesntHave('answer')
                ->with('group.parentGroup')
                ->inRandomOrder()
                ->first();
        }

        if (!$question) {
            return response()->json(['message' => 'No unanswered questions found in this group.'], 404);
        }

        // Determine next question ID for convenience
        $nextQuestion = Question::where('question_group_id', '=', $group->id)
            ->where('id', '!=', $question->id)
            ->doesntHave('answer')
            ->inRandomOrder()
            ->first(['id']);

        return response()->json([
            'question' => $question,
            'next_question_id' => $nextQuestion ? $nextQuestion->id : null,
        ]);
    }

    /**
     * Get questions for a specific group, optionally starting with a desired ID.
     *
     * @param string $groupName The name of the question group.
     * @param int|null $desiredId The ID of a desired question to start with (optional).
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGroupQuestions(string $groupName, int $desiredId = null)
    {
        $group = QuestionGroup::where('name', $groupName)->first();

        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $currentQuestion = null;

        if ($desiredId) {
            $currentQuestion = Question::where('question_group_id', $group->id)
                                     ->with('group.parentGroup')
                                     ->doesntHave('answer') // Ensure it's unanswered
                                     ->find($desiredId);
        }

        // If no desiredId provided, or if the desired question was not found/is answered, get a random one
        if (!$currentQuestion) {
            $currentQuestion = Question::where('question_group_id', $group->id)
                                     ->with('group.parentGroup')
                                     ->doesntHave('answer')
                                     ->inRandomOrder()
                                     ->first();
        }

        if (!$currentQuestion) {
            return response()->json(['message' => 'No unanswered questions in this group.'], 404);
        }

        // Get another random unanswered question for 'next_question_id', excluding the current one
        $nextQuestion = Question::where('question_group_id', $group->id)
            ->where('id', '!=', $currentQuestion->id)
            ->with('group.parentGroup') // Eager load for consistency if needed by frontend
            ->doesntHave('answer')
            ->inRandomOrder()
            ->first();

        return response()->json([
            'question' => $currentQuestion,
            // Return the full next question object, or null if none
            'next_question' => $nextQuestion,
        ]);
    }
}

