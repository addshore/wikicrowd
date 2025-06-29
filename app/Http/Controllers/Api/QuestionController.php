<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionController extends Controller
{
    private function getGroupUnanswered($groupName, array $seenIds = [])
    {
        $groupId = QuestionGroup::where('name', '=', $groupName)->firstOrFail()->id;
        return Question::where('question_group_id', '=', $groupId)
            ->doesntHave('answer')
            ->when(count($seenIds) > 0, function ($query) use ($seenIds) {
                return $query->whereNotIn('id', $seenIds);
            })
            ->with('group.parentGroup') // Eager load relationships
            ->inRandomOrder()
            ->first();
    }

    private function getGroupDesiredIdUnanswered($groupName, $desiredId, array $seenIds = []) // seenIds might be used for its next_question
    {
        \Log::info('getGroupDesiredIdUnanswered called', [
            'groupName' => $groupName,
            'desiredId' => $desiredId,
            'seenIds' => $seenIds,
        ]);
        $groupId = QuestionGroup::where('name', '=', $groupName)->firstOrFail()->id;
        return Question::where('question_group_id', '=', $groupId)
            ->doesntHave('answer')
            ->with('group.parentGroup') // Eager load relationships
            ->where('id', '=', $desiredId)
            ->first(); // seenIds don't apply to the desired question itself, but to its next
    }

    private function getGroupNotIdUnanswered($groupName, $notId, array $seenIds = [])
    {
        $groupId = QuestionGroup::where('name', '=', $groupName)->firstOrFail()->id;
        $allSeenAndNotIds = array_unique(array_merge($seenIds, [$notId])); // Combine $notId with $seenIds

        return Question::where('question_group_id', '=', $groupId)
            ->doesntHave('answer')
            ->whereNotIn('id', $allSeenAndNotIds) // Exclude $notId and all $seenIds
            ->with('group.parentGroup') // Eager load relationships
            ->inRandomOrder()
            ->first();
    }

    /**
     * Display a listing of the resource.
     *
     * @param  string  $groupName
     * @param  int|null  $desiredId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $groupName, int $desiredId = null)
    {
        \Log::info('Api/QuestionController@show called', [
            'groupName' => $groupName,
            'desiredId' => $desiredId,
            'user_id' => optional(\Auth::user())->id,
            'params' => $request->all(),
        ]);
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
    public function getGroupQuestions(Request $request, $groupName, $desiredId = null)
    {
        \Log::info('Api/QuestionController@getGroupQuestions called', [
            'groupName' => $groupName,
            'desiredId' => $desiredId,
            'user_id' => optional(\Auth::user())->id,
            'params' => $request->all(),
        ]);
        $seenIdsInput = $request->input('seen_ids'); // Get raw input as string (e.g., "1,2,3") or null
        $seenIds = [];
        if (is_string($seenIdsInput) && !empty($seenIdsInput)) {
            $seenIds = explode(',', $seenIdsInput);
            $seenIds = array_filter(array_map('intval', $seenIds)); // Convert to int, remove non-ints
        } elseif (is_array($seenIdsInput)) { // Should not happen with current JS, but good for robustness
            $seenIds = array_filter(array_map('intval', $seenIdsInput));
        }

        $count = intval($request->input('count', 0));
        $group = QuestionGroup::where('name', '=', $groupName)->first();
        if (!$group) {
            return response()->json(['message' => 'Question group not found'], 404);
        }

        if ($count > 0) {
            // Batch mode: return up to $count unanswered questions, skipping seenIds
            $questions = Question::where('question_group_id', '=', $group->id)
                ->doesntHave('answer')
                ->when(count($seenIds) > 0, function ($query) use ($seenIds) {
                    return $query->whereNotIn('id', $seenIds);
                })
                ->with('group.parentGroup')
                ->inRandomOrder()
                ->limit($count)
                ->get();
            return response()->json(['questions' => $questions]);
        }

        $question = null;
        $nextQuestion = null;

        if ($desiredId) {
            $question = $this->getGroupDesiredIdUnanswered($groupName, $desiredId, $seenIds);
            if ($question) {
                // Pass seenIds to get the next question, excluding the current one and seen ones
                $nextQuestion = $this->getGroupNotIdUnanswered($groupName, $question->id, $seenIds);
            }
        } else {
            $question = $this->getGroupUnanswered($groupName, $seenIds);
            if ($question) {
                // Pass seenIds to get the next question, excluding the current one and seen ones
                $nextQuestion = $this->getGroupNotIdUnanswered($groupName, $question->id, $seenIds);
            }
        }

        if (!$question) {
            return response()->json(['message' => 'No suitable questions found.'], 404);
        }

        // The API returns the current question and the next one (which respects seen_ids)
        return response()->json([
            'question' => $question,
            'next_question' => $nextQuestion,
        ]);
    }
}

