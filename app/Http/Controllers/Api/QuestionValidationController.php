<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Services\SparqlQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class QuestionValidationController extends Controller
{
    private $sparqlService;

    public function __construct(SparqlQueryService $sparqlService)
    {
        $this->sparqlService = $sparqlService;
    }

    public function validateAndCleanup(Request $request)
    {
        $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => 'integer|exists:questions,id',
            'reason' => 'required|string|in:already_depicts,deleted_image'
        ]);

        $questionIds = $request->input('question_ids');
        $reason = $request->input('reason');
        $results = [];
        $deletedCount = 0;

        foreach ($questionIds as $questionId) {
            $question = Question::find($questionId);
            if (!$question) {
                $results[] = [
                    'question_id' => $questionId,
                    'status' => 'not_found',
                    'deleted' => false
                ];
                continue;
            }

            $shouldDelete = false;
            $validationResult = null;

            try {
                if ($reason === 'already_depicts') {
                    $validationResult = $this->validateAlreadyDepicts($question);
                    $shouldDelete = $validationResult['already_depicts'] ?? false;
                } elseif ($reason === 'deleted_image') {
                    $validationResult = $this->validateImageExists($question);
                    $shouldDelete = !($validationResult['image_exists'] ?? true);
                }

                if ($shouldDelete) {
                    $question->delete();
                    $deletedCount++;
                    Log::info("Question {$questionId} deleted due to {$reason}", [
                        'question_id' => $questionId,
                        'reason' => $reason,
                        'validation_result' => $validationResult
                    ]);
                }

                $results[] = [
                    'question_id' => $questionId,
                    'status' => 'validated',
                    'deleted' => $shouldDelete,
                    'reason' => $reason,
                    'validation_result' => $validationResult
                ];

            } catch (\Exception $e) {
                Log::error("Error validating question {$questionId}: " . $e->getMessage(), [
                    'question_id' => $questionId,
                    'reason' => $reason,
                    'exception' => $e
                ]);

                $results[] = [
                    'question_id' => $questionId,
                    'status' => 'error',
                    'deleted' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'processed' => count($questionIds),
            'deleted' => $deletedCount,
            'results' => $results
        ]);
    }

    private function validateAlreadyDepicts(Question $question): array
    {
        $properties = $question->properties ?? [];
        $mediaInfoId = $properties['mediainfo_id'] ?? null;
        $targetDepictsId = $properties['depicts_id'] ?? null;

        if (!$mediaInfoId || !$targetDepictsId) {
            return [
                'already_depicts' => false,
                'reason' => 'Missing mediainfo_id or depicts_id',
                'mediainfo_id' => $mediaInfoId,
                'target_depicts_id' => $targetDepictsId
            ];
        }

        try {
            // Get target QID + subclasses/instances
            $qidArray = $this->sparqlService->getSubclassesAndInstances($targetDepictsId);
            $qidSet = collect($qidArray)->push($targetDepictsId); // Add the target QID itself
            
            // Get current depicts for the media item
            $currentDepicts = $this->fetchDepictsForMediaInfoId($mediaInfoId);
            
            // Check if any current depicts match target or its subclasses/instances
            $alreadyDepicts = false;
            $matchedQids = [];
            foreach ($currentDepicts as $qid) {
                if ($qidSet->contains($qid)) {
                    $alreadyDepicts = true;
                    $matchedQids[] = $qid;
                }
            }

            return [
                'already_depicts' => $alreadyDepicts,
                'mediainfo_id' => $mediaInfoId,
                'target_depicts_id' => $targetDepictsId,
                'current_depicts' => $currentDepicts,
                'target_qid_set_size' => $qidSet->count(),
                'matched_qids' => $matchedQids
            ];

        } catch (\Exception $e) {
            throw new \Exception("Failed to validate depicts: " . $e->getMessage());
        }
    }

    private function validateImageExists(Question $question): array
    {
        $properties = $question->properties ?? [];
        $mediaInfoId = $properties['mediainfo_id'] ?? null;

        if (!$mediaInfoId) {
            return [
                'image_exists' => false,
                'reason' => 'Missing mediainfo_id'
            ];
        }

        try {
            // Check if the media item exists on Commons
            $url = "https://commons.wikimedia.org/w/api.php";
            $response = Http::get($url, [
                'action' => 'wbgetentities',
                'ids' => $mediaInfoId,
                'format' => 'json',
                'props' => 'info'
            ]);

            $data = $response->json();
            $exists = !isset($data['entities'][$mediaInfoId]['missing']);

            return [
                'image_exists' => $exists,
                'mediainfo_id' => $mediaInfoId,
                'api_response' => $data
            ];

        } catch (\Exception $e) {
            throw new \Exception("Failed to check image existence: " . $e->getMessage());
        }
    }

    private function fetchDepictsForMediaInfoId(string $mediaInfoId): array
    {
        try {
            $url = "https://commons.wikimedia.org/w/api.php";
            $response = Http::get($url, [
                'action' => 'wbgetclaims',
                'entity' => $mediaInfoId,
                'property' => 'P180', // depicts property
                'format' => 'json'
            ]);

            $data = $response->json();
            $depicts = [];

            if (isset($data['claims']['P180'])) {
                foreach ($data['claims']['P180'] as $claim) {
                    if (isset($claim['mainsnak']['datavalue']['value']['id'])) {
                        $depicts[] = $claim['mainsnak']['datavalue']['value']['id'];
                    }
                }
            }

            return $depicts;

        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch depicts for {$mediaInfoId}: " . $e->getMessage());
        }
    }
}
