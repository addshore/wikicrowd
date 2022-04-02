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
use App\Jobs\AddAlias;
use App\Models\Edit;
use App\Models\User;
use App\Jobs\SwapDepicts;
use LaravelDaily\LaravelCharts\Classes\LaravelChart;
use Illuminate\Support\Facades\Cache;

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

Route::get('/about', function () {
    $user = Auth::user();
    if($user) {
        $userStats = [
            'answers' => Answer::where('user_id','=',$user->id)->count(),
            'edits' => Edit::where('user_id','=',$user->id)->count(),
        ];
    }

    return view('about', [
        'rcurls' => [
            "Commons" => "https://commons.wikimedia.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2",
            "Wikidata" => "https://www.wikidata.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2",
        ],
        'stats' => [
            'questions' => Question::count(),
            'answers' => Answer::count(),
            'edits' => Edit::count(),
            'users' => User::count(),
        ],
        'userstats' => $userStats ?? [],
    ]);
})->name('about');

Route::get('/groups', [QuestionGroupController::class, 'showTopLevelGroups'])->name('groups');

Route::middleware('auth:sanctum')->get('/questions/{groupName}', [QuestionController::class, 'showGroupUnanswered'])
    ->where('groupName', '(.*)');

// TODO also move this to the API
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
        if($question->group->parentGroup->name === 'depicts') {
            dispatch(new AddDepicts($storedAnswer->id));
        }
        if($question->group->parentGroup->name === 'depicts-refine') {
            dispatch(new SwapDepicts($storedAnswer->id));
        }
        if($question->group->parentGroup->name === 'aliases') {
            dispatch(new AddAlias($storedAnswer->id));
        }
    }

    // Show the next one!
    return redirect('/questions/' . $question->group->name);
});
