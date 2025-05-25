<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Question;
use App\Models\Answer;

class ManualQuestionController extends Controller
{
    /**
     * Create (if needed) and answer a manual question.
     */
    public function createAndAnswer(Request $request)
    {
        $v = Validator::make($request->all(), [
            'category' => 'required|string',
            'qid' => 'required|string|regex:/^Q[0-9]+$/',
            'mediainfo_id' => 'required|string',
            'img_url' => 'required|url',
            'answer' => 'required|in:yes,no,skip',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Invalid input', 'errors' => $v->errors()], 422);
        }
        $user = Auth::user();
        $uniqueId = $request->input('mediainfo_id') . '/depicts/' . $request->input('qid');
        $question = Question::firstOrCreate(
            [
                'unique_id' => $uniqueId,
            ],
            [
                'question_group_id' => 0,
                'properties' => [
                    'mediainfo_id' => $request->input('mediainfo_id'),
                    'depicts_id' => $request->input('qid'),
                    'img_url' => $request->input('img_url'),
                    'manual' => true,
                    'category' => $request->input('category'),
                    'user' => $user ? $user->username : null, // Store Wikimedia username
                ],
            ]
        );
        // Create answer
        $answer = Answer::create([
            'question_id' => $question->id,
            'user_id' => $user ? $user->id : null,
            'answer' => $request->input('answer'),
        ]);

        // Dispatch the edit job if the answer is 'yes' (for depicts)
        if ($request->input('answer') === 'yes') {
            // For custom/manual, always treat as 'depicts' job
            dispatch(new \App\Jobs\AddDepicts($answer->id));
        }

        return response()->json(['message' => 'Question answered', 'question_id' => $question->id, 'answer_id' => $answer->id]);
    }
}
