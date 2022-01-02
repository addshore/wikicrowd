<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\AddDepicts;
use Illuminate\Support\Facades\Auth;
use App\Models\Answer;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionGroupController;
use App\Models\Question;

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
});

Route::get('/groups', [QuestionGroupController::class, 'showTopLevelGroups'])->name('groups');

Route::middleware('auth:sanctum')->get('/questions/{groupName}', [QuestionController::class, 'showGroupUnanswered'])
    ->where('groupName', '(.*)');

Route::middleware('auth:sanctum')->name('answers')->post('/answers', function (Request $request) {

    $v = Validator::make($request->all(), [
        'question' => 'required|exists:App\Models\Question,id',
        // TODO make this generic (from group?)
        'answer' => 'required|in:yes,no,skip',
    ]);
    if ($v->fails()) {
        die("unexpected submission");
    }

    $question = $request->input('question');
    $answer = $request->input('answer');
    $user = Auth::user();

    // Get the question
    $question = Question::find($question);

    // Store the answer
    $storedAnswer = Answer::create([
        'question_id' => $question->id,
        'user_id' => $user->id,
        'answer' => $answer,
    ]);

    // Dispatch the edit job..
    if($answer === 'yes') {
        // TODO make a generic answer -> edit job?
        AddDepicts::dispatch($storedAnswer->id);
    }

    // Show the next one!
    return redirect('/questions/' . $question->group->name);
});
