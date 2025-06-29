<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Jobs\RemoveUnansweredQuestions;

class RemoveUnansweredQuestionsController extends Controller
{
    /**
     * Trigger removal of unanswered questions for a specific group name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clear(Request $request)
    {
        \Log::info('Api/RemoveUnansweredQuestionsController@clear called', [
            'user_id' => optional(\Auth::user())->id,
            'params' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'groupName' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid groupName', 'errors' => $validator->errors()], 422);
        }
        $groupName = $request->input('groupName');
        dispatch((new RemoveUnansweredQuestions($groupName))->onQueue('high')); // High queue, as this is a quick cleanup job
        Log::info("RemoveUnansweredQuestions job dispatched for groupName: $groupName");
        return response()->json([
            'message' => "RemoveUnansweredQuestions job dispatched for groupName: $groupName",
        ], 202);
    }
}
