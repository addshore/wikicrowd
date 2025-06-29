<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Jobs\GenerateDepictsQuestionsFromYaml;

class RegenerateQuestionController extends Controller
{
    /**
     * Trigger regeneration of depicts questions for a specific depictsId (Qid).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerate(Request $request)
    {
        \Log::info('Api/RegenerateQuestionController@regenerate called', [
            'user_id' => optional(\Auth::user())->id,
            'params' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'depictsId' => 'required|string|regex:/^Q[0-9]+$/',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid depictsId', 'errors' => $validator->errors()], 422);
        }
        $depictsId = $request->input('depictsId');
        
        // Queue the job (async) - deduplication is handled by the job itself
        $job = new GenerateDepictsQuestionsFromYaml($depictsId, "", 0, false);
        dispatch($job->onQueue('high')); // High queue, as this just makes more jobs anyway..
        Log::info("Regeneration job dispatched for depictsId: $depictsId");
        
        return response()->json([
            'message' => "Regeneration job dispatched for depictsId: $depictsId",
        ], 202);
    }
}
