<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\AddDepicts;
use Illuminate\Support\Facades\Auth;
use App\Models\Answer;
use App\Http\Controllers\QuestionController;
use App\Models\Question;
use App\Jobs\AddAlias;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('/groups');
})->name('home');

Route::get('/groups', function () {
    return view('groups', [
        'apiToken' => auth()->user()?->createToken('web')->plainTextToken ?? null,
    ]);
})->name('groups');

Route::middleware('auth:sanctum')->get('/questions/depicts/custom', function () {
    if (!Auth::check()) {
        return redirect('/login');
    }
    $apiToken = Auth::user()->createToken('custom-grid', ['submit-answers'])->plainTextToken;
    return view('questions.depicts-custom', ['apiToken' => $apiToken]);
});

Route::middleware('auth:sanctum')->get('/questions/{groupName}/{desiredId?}', [QuestionController::class, 'showGroupDesiredOrUnanswered'])
    ->where('groupName', '([^\/]*\/[^\/]*)');

Route::middleware('auth:sanctum')->get('/manual-question-grid', function () {
    return view('manual-question-grid');
});
