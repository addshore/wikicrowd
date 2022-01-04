<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::get('/groups', [\App\Http\Controllers\QuestionGroupController::class, 'getTopLevelGroups'])->name('groups');

Route::middleware('auth:sanctum')
    ->get('/questions/{groupName}', [\App\Http\Controllers\QuestionController::class, 'getGroupUnanswered'])
    ->where('groupName', '(.*)');
