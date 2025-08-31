<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Question;
use App\Models\Answer;
use App\Jobs\AddDepicts;

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
        \Log::info('Api/AnswerController@store called', [
            'user_id' => optional(\Auth::user())->id,
            'params' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'question_id' => 'required|exists:App\Models\Question,id',
            'answer' => 'required|in:yes,no,skip,yes-preferred',
            'remove_superclasses' => 'boolean',
            'edit_group_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'The given data was invalid.',
 'errors' => $validator->errors()], 422);
        }

        $questionId = $request->input('question_id');
        $answerValue = $request->input('answer');
        $user = Auth::user();

        // Find the question and load necessary relationships
        $question = Question::with('group.parentGroup')->find($questionId);

        if (!$question) {
            // This case should ideally be caught by 'exists' validation, but as
 a safeguard:
            return response()->json(['message' => 'Question not found.'], 404);
        }

        $storedAnswer = Answer::create([
            'question_id' => $question->id,
            'user_id' => $user->id,
            'answer' => $answerValue,
        ]);

        // Dispatch the edit job if the answer is 'yes' or 'yes-preferred'
        if ($answerValue === 'yes' || $answerValue === 'yes-preferred') {
            // Ensure group and parentGroup are loaded. The 'with' above should
handle this.
            if ($question->group && $question->group->parentGroup) {
                $parentGroupName = $question->group->parentGroup->name;
                $rank = ($answerValue === 'yes-preferred') ? 'preferred' : null;
                $removeSuperclasses = $request->boolean('remove_superclasses', f
alse);
                if ($parentGroupName === 'depicts') {
                    $editGroupId = $request->input('edit_group_id');
                    dispatch(new AddDepicts(
                        $storedAnswer->id,
                        $question->properties['mediainfo_id'],
                        $question->properties['depicts_id'],
                        $rank,
                        $removeSuperclasses,
                        $editGroupId
                    ));
                }
            } else {
                // Log if group or parentGroup is missing, as jobs might not be
dispatched correctly
                \Log::warning("Question {$question->id} is missing group or pare
ntGroup information. Answer ID: {$storedAnswer->id}");
            }
        }

        return response()->json([
            'message' => 'Answer submitted successfully.',
            'answer' => $storedAnswer
        ], 201);
    }

    /**
     * Store multiple answers in bulk.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkStore(Request $request)
    {
        \Log::info('Api/AnswerController@bulkStore called', [
            'user_id' => optional(\Auth::user())->id,
            'params' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|exists:App\\Models\\Question,id
',
            'answers.*.answer' => 'required|in:yes,no,skip,yes-preferred',
            'remove_superclasses' => 'boolean',
            'edit_group_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'The given data was invalid.',
 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $answersData = $request->input('answers');
        $now = now();
        $insertData = [];
        $questionIds = [];
        foreach ($answersData as $answerData) {
            $insertData[] = [
                'question_id' => $answerData['question_id'],
                'user_id' => $user->id,
                'answer' => $answerData['answer'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $questionIds[] = $answerData['question_id'];
        }
        // Insert all, ignore duplicates
        Answer::insertOrIgnore($insertData);
        // Fetch all answers for this user and these questions
        $answers = Answer::whereIn('question_id', $questionIds)
            ->where('user_id', $user->id)
            ->get();
        $createdAnswers = [];
        foreach ($answers as $storedAnswer) {
            // Find the original answer data for this question
            $answerData = collect($answersData)->firstWhere('question_id', $stor
edAnswer->question_id);
            if (!$answerData) continue;
            $question = Question::with('group.parentGroup')->find($storedAnswer-
>question_id);
            if ($answerData['answer'] === 'yes' || $answerData['answer'] === 'ye
s-preferred') {
                if ($question && $question->group && $question->group->parentGro
up) {
                    $parentGroupName = $question->group->parentGroup->name;
                    $rank = ($answerData['answer'] === 'yes-preferred') ? 'prefe
rred' : null;
                    $removeSuperclasses = $request->boolean('remove_superclasses
', false);
                    if ($parentGroupName === 'depicts') {
                        $editGroupId = $request->input('edit_group_id');
                        dispatch(new AddDepicts(
                            $storedAnswer->id,
                            $question->properties['mediainfo_id'],
                            $question->properties['depicts_id'],
                            $rank,
                            $removeSuperclasses,
                            $editGroupId
                        ));
                    }
                } else if ($question) {
                    \Log::warning("Question {$question->id} is missing group or
parentGroup information. Answer ID: {$storedAnswer->id}");
                }
            }
            $createdAnswers[] = $storedAnswer;
        }
        return response()->json([
            'message' => 'Bulk answers submitted successfully.',
            'answers' => $createdAnswers
        ], 201);
    }
}
