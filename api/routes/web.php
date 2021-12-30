<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuDepictsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\QuDepictsAnswer;
use App\Jobs\AddDepicts;

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
    return view('welcome');
});

Route::middleware('auth:sanctum')->get('/edit', [QuDepictsController::class, 'show'])->name('edit');

Route::middleware('auth:sanctum')->post('/edit', function (Request $request) {

    $v = Validator::make($request->all(), [
        'question' => 'required|exists:App\Models\QuDepicts,id',
        'answer' => 'required|in:yes,no,skip',
    ]);
    if ($v->fails()) {
        die("unexpected submission");
    }

    $question = $request->input('question');
    $answer = $request->input('answer');
    $user = Auth::user();

    // Store the answer
    $storedAnswer = QuDepictsAnswer::create([
        'qu_depicts_id' => $question,
        'user_id' => $user->id,
        'answer' => $answer,
    ]);

    // Dispatch the edit job..
    if($answer === 'yes') {
        AddDepicts::dispatch($storedAnswer->id);
    }

    return QuDepictsController::show();
});
