<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QuestionGroupController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\AnswerController;
use App\Http\Controllers\Api\YamlSpecController;
use App\Http\Controllers\Api\QuestionValidationController;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Edit;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::post('token', [\App\Http\Controllers\ApiAuthController::class, 'requestToken']);

Route::get('/groups', [QuestionGroupController::class, 'index'])->name('api.groups.index');
Route::get('/depicts/yaml-spec', [YamlSpecController::class, 'index'])->name('api.depicts.yaml-spec');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/questions/{groupName}/{desiredId?}', [QuestionController::class, 'getGroupQuestions'])
        ->where('groupName', '(.*)')
        ->name('api.questions.show');
    Route::post('/answers', [AnswerController::class, 'store'])->name('api.answers.store');
    Route::post('/answers/bulk', [\App\Http\Controllers\Api\AnswerController::class, 'bulkStore']);
    Route::post('/regenerate-question', [\App\Http\Controllers\Api\RegenerateQuestionController::class, 'regenerate'])
    ->name('api.regenerate-question');
    Route::post('/clear-unanswered', [\App\Http\Controllers\Api\RemoveUnansweredQuestionsController::class, 'clear'])
    ->name('api.clear-unanswered');
    Route::post('/manual-question/answer', [\App\Http\Controllers\Api\ManualQuestionController::class, 'createAndAnswer']);
    Route::post('/manual-question/bulk-answer', [\App\Http\Controllers\Api\ManualQuestionController::class, 'bulkCreateAndAnswer']);
    Route::post('/questions/validate-and-cleanup', [\App\Http\Controllers\Api\QuestionValidationController::class, 'validateAndCleanup'])
        ->name('api.questions.validate-and-cleanup');
});

Route::get('/stats', function () {
    $queues = ['high', 'default', 'low'];
    $jobCounts = [];
    foreach ($queues as $queue) {
        $jobCounts[$queue] = \Queue::connection()->getRedis()->llen('queues:' . $queue);
    }
    return response()->json([
        'questions' => Question::count(),
        'answers' => Answer::count(),
        'edits' => Edit::count(),
        'users' => User::count(),
        'jobs_high' => $jobCounts['high'],
        'jobs_default' => $jobCounts['default'],
        'jobs_low' => $jobCounts['low'],
    ]);
});
