<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Jobs\ValidateQuestions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuestionValidationController extends Controller
{
    public function validateAndCleanup(Request $request)
    {
        $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => 'integer|exists:questions,id',
            'reason' => 'required|string|in:already_depicts,deleted_image'
        ]);

        $questionIds = $request->input('question_ids');
        $reason = $request->input('reason');

        // Dispatch the validation job to the low priority queue
        dispatch(new ValidateQuestions($questionIds, $reason))->onQueue('low');

        Log::info("Question validation job dispatched", [
            'question_count' => count($questionIds),
            'reason' => $reason,
            'queue' => 'low'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Question validation job has been queued',
            'question_count' => count($questionIds),
            'reason' => $reason,
            'queue' => 'low'
        ]);
    }

}
