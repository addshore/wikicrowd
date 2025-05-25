<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
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
        $validator = Validator::make($request->all(), [
            'depictsId' => 'required|string|regex:/^Q[0-9]+$/',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid depictsId', 'errors' => $validator->errors()], 422);
        }
        $depictsId = $request->input('depictsId');
        // Optionally allow YAML override and jobLimit for future flexibility
        // $yamlUrl = $request->input('yamlUrl', null);
        // $jobLimit = intval($request->input('jobLimit', 0));
        // Queue the job (async)
        dispatch(new GenerateDepictsQuestionsFromYaml($depictsId, "", 0, false));
        Log::info("Regeneration job dispatched for depictsId: $depictsId");
        return response()->json([
            'message' => "Regeneration job dispatched for depictsId: $depictsId",
        ], 202);
    }
}
