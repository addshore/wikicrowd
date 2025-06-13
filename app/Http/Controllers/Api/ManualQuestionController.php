<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Question;
use App\Models\Answer;
use Addwiki\Wikimedia\Api\WikimediaFactory;
use Addwiki\Mediawiki\Api\MediawikiFactory;
use Wikibase\MediaInfo\DataModel\MediaInfoId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\ItemId;
use Addwiki\Wikibase\Query\PrefixSets;
use Illuminate\Support\Facades\Cache;

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
            'answer' => 'required|in:yes,no,skip,yes-preferred',
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

        // Dispatch the edit job if the answer is 'yes' or 'yes-preferred' (for depicts)
        if ($request->input('answer') === 'yes' || $request->input('answer') === 'yes-preferred') {
            // Check for superclass depicts
            $oldQid = $this->getSuperclassDepictsQid($request->input('mediainfo_id'), $request->input('qid'));
            $rank = ($request->input('answer') === 'yes-preferred') ? 'preferred' : null;
            if ($oldQid) {
                // Add old_depicts_id to question properties for SwapDepicts
                $question->properties = array_merge($question->properties, ['old_depicts_id' => $oldQid]);
                $question->save();
                dispatch(new \App\Jobs\SwapDepicts($answer->id, $rank));
            } else {
                dispatch(new \App\Jobs\AddDepicts($answer->id, $rank));
            }
        }

        return response()->json(['message' => 'Question answered', 'question_id' => $question->id, 'answer_id' => $answer->id]);
    }

    /**
     * Bulk create (if needed) and answer manual questions.
     */
    public function bulkCreateAndAnswer(Request $request)
    {
        $v = Validator::make($request->all(), [
            'answers' => 'required|array|min:1',
            'answers.*.category' => 'required|string',
            'answers.*.qid' => 'required|string|regex:/^Q[0-9]+$/',
            'answers.*.mediainfo_id' => 'required|string',
            'answers.*.img_url' => 'required|url',
            'answers.*.answer' => 'required|in:yes,no,skip,yes-preferred',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Invalid input', 'errors' => $v->errors()], 422);
        }
        $user = Auth::user();
        $results = [];
        foreach ($request->input('answers') as $input) {
            $uniqueId = $input['mediainfo_id'] . '/depicts/' . $input['qid'];
            $question = Question::firstOrCreate(
                [ 'unique_id' => $uniqueId ],
                [
                    'question_group_id' => 0,
                    'properties' => [
                        'mediainfo_id' => $input['mediainfo_id'],
                        'depicts_id' => $input['qid'],
                        'img_url' => $input['img_url'],
                        'manual' => true,
                        'category' => $input['category'],
                        'user' => $user ? $user->username : null,
                    ],
                ]
            );
            $answer = Answer::updateOrCreate(
                [
                    'question_id' => $question->id,
                    'user_id' => $user ? $user->id : null,
                ],
                [
                    'answer' => $input['answer'],
                ]
            );

            if (!$answer->wasRecentlyCreated && $answer->wasChanged()) {
                Log::info('Answer already existed and was updated.', [
                    'question_id' => $question->id,
                    'user_id' => $user ? $user->id : null,
                    'new_answer' => $input['answer']
                ]);
            } elseif (!$answer->wasRecentlyCreated && !$answer->wasChanged()) {
                Log::info('Answer already existed and was not changed.', [
                    'question_id' => $question->id,
                    'user_id' => $user ? $user->id : null,
                    'existing_answer' => $input['answer']
                ]);
            }

            if ($input['answer'] === 'yes' || $input['answer'] === 'yes-preferred') {
                $oldQid = $this->getSuperclassDepictsQid($input['mediainfo_id'], $input['qid']);
                $rank = ($input['answer'] === 'yes-preferred') ? 'preferred' : null;
                if ($oldQid) {
                    $question->properties = array_merge($question->properties, ['old_depicts_id' => $oldQid]);
                    $question->save();
                    dispatch(new \App\Jobs\SwapDepicts($answer->id, $rank));
                } else {
                    dispatch(new \App\Jobs\AddDepicts($answer->id, $rank));
                }
            }
            $results[] = [
                'question_id' => $question->id,
                'answer_id' => $answer->id,
            ];
        }
        return response()->json([
            'message' => 'Bulk manual questions answered',
            'results' => $results
        ]);
    }

    /**
     * Helper to determine if a depicts swap is needed, and return the old QID if so.
     * Returns null if no swap is needed, or the old QID if a superclass is present.
     */
    private function getSuperclassDepictsQid($mediainfoId, $depictsQid)
    {
        // Setup MediaWiki API client
        $mwAuth = new \Addwiki\Mediawiki\Api\Client\Auth\OAuthOwnerConsumer(
            config('services.mediawiki.identifier'),
            config('services.mediawiki.secret'),
            Auth::user()->token,
            Auth::user()->token_secret
        );
        $wm = new WikimediaFactory();
        $wbServices = $wm->newWikibaseFactoryForDomain("commons.wikimedia.org", $mwAuth);
        $mid = new MediaInfoId($mediainfoId);
        $depictsProperty = new PropertyId('P180');
        $depictsValue = new ItemId($depictsQid);
        // Get all superclasses of the new QID
        $superclasses = Cache::remember('instancesOfAndSubclassesOf:' . $depictsQid, 60*2, function () use ($depictsQid) {
            $query = (new \Addwiki\Wikibase\Query\WikibaseQueryFactory(
                "https://query.wikidata.org/sparql",
                PrefixSets::WIKIDATA
            ))->newWikibaseQueryService();
            $result = $query->query("SELECT DISTINCT ?i WHERE{?i wdt:P31/wdt:P279*|wdt:P279/wdt:P279* wd:{$depictsQid} }");
            $ids = [];
            foreach ($result['results']['bindings'] as $binding) {
                $ids[] = $this->getLastPartOfUrlPath($binding['i']['value']);
            }
            return $ids;
        });
        // Lookup entity
        $entity = $wbServices->newEntityLookup()->getEntity($mid);
        if ($entity === null) {
            return null;
        }
        foreach ($entity->getStatements()->getByPropertyId($depictsProperty)->toArray() as $statement) {
            if ($statement->getMainSnak()->getType() !== 'value') {
                continue;
            }
            $entityId = $statement->getMainSnak()->getDataValue()->getEntityId();
            $entityQid = $entityId->getSerialization();
            if (in_array($entityQid, $superclasses)) {
                return $entityQid;
            }
        }
        return null;
    }

    private function getLastPartOfUrlPath(string $urlPath): string {
        $parts = explode('/', $urlPath);
        return end($parts);
    }
}
