<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CheckExistingAnswersController extends Controller
{
    /**
     * Check for existing answers for manual questions based on category, qid, and mediainfo_ids.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkManualAnswers(Request $request)
    {
        \Log::info('Api/CheckExistingAnswersController@checkManualAnswers called', [
            'user_id' => optional(\Auth::user())->id,
            'params' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'category' => 'required|string',
            'qid' => 'required|string|regex:/^Q[0-9]+$/',
            'mediainfo_ids' => 'required|array|min:1',
            'mediainfo_ids.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $category = $request->input('category');
        $qid = $request->input('qid');
        $mediainfoIds = $request->input('mediainfo_ids');

        $existingAnswers = [];

        foreach ($mediainfoIds as $mediainfoId) {
            $uniqueId = $mediainfoId . '/depicts/' . $qid;
            
            // Find the question by unique_id
            $question = Question::where('unique_id', $uniqueId)->first();
            
            if ($question) {
                $answer = Answer::where('question_id', $question->id)->first();
                
                if ($answer) {
                    $existingAnswers[$mediainfoId] = $answer->answer;
                }
            }
        }

        return response()->json([
            'existing_answers' => $existingAnswers
        ]);
    }
}
