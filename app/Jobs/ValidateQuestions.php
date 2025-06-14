<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Question;
use App\Services\SparqlQueryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ValidateQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $questionIds;
    private $reason;

    public function __construct(array $questionIds, string $reason)
    {
        $this->questionIds = $questionIds;
        $this->reason = $reason;
    }

    public function handle(SparqlQueryService $sparqlService)
    {
        $deletedCount = 0;

        foreach ($this->questionIds as $questionId) {
            $question = Question::find($questionId);
            if (!$question) {
                Log::warning("Question {$questionId} not found during validation job");
                continue;
            }

            $shouldDelete = false;
            $validationResult = null;

            try {
                if ($this->reason === 'already_depicts') {
                    $validationResult = $this->validateAlreadyDepicts($question, $sparqlService);
                    $shouldDelete = $validationResult['already_depicts'] ?? false;
                } elseif ($this->reason === 'deleted_image') {
                    $validationResult = $this->validateImageExists($question);
                    $shouldDelete = !($validationResult['image_exists'] ?? true);
                }

                if ($shouldDelete) {
                    $question->delete();
                    $deletedCount++;
                    Log::info("Question {$questionId} deleted due to {$this->reason}", [
                        'question_id' => $questionId,
                        'reason' => $this->reason,
                        'validation_result' => $validationResult
                    ]);
                }

            } catch (\Exception $e) {
                Log::error("Error validating question {$questionId}: " . $e->getMessage(), [
                    'question_id' => $questionId,
                    'reason' => $this->reason,
                    'exception' => $e
                ]);
            }
        }

        Log::info("Question validation job completed", [
            'reason' => $this->reason,
            'processed' => count($this->questionIds),
            'deleted' => $deletedCount
        ]);
    }

    private function validateAlreadyDepicts(Question $question, SparqlQueryService $sparqlService): array
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
            $qidArray = $sparqlService->getSubclassesAndInstances($targetDepictsId);
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
            $response = Http::withOptions(['allow_redirects' => true])->get($url, [
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
            $response = Http::withOptions(['allow_redirects' => true])->get($url, [
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
