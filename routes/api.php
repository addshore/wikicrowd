<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QuestionGroupController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\AnswerController;
use App\Http\Controllers\Api\YamlSpecController;

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
});
