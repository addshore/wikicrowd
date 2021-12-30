<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuDepictsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\QuDepictsAnswer;

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
    QuDepictsAnswer::create([
        'qu_depicts_id' => $question,
        'user_id' => $user->id,
        'answer' => $answer,
    ]);

    // And write to some mw instance
    // TODO make this actually hit mediainfo?
    // TODO make this defer
    $mwAuth = new \Addwiki\Mediawiki\Api\Client\Auth\OAuthOwnerConsumer(
        config('services.mediawiki.identifier'),
        config('services.mediawiki.secret'),
        $user->token,
        $user->token_secret
    );

    $mw = \Addwiki\Mediawiki\Api\Client\MediaWiki::newFromEndpoint( 'https://addshore-alpha.wiki.opencura.com/w/api.php', $mwAuth );
    $mwServices = new \Addwiki\Mediawiki\Api\MediawikiFactory( $mw->action() );

    $result = $mwServices->newRevisionSaver()->save( new \Addwiki\Mediawiki\DataModel\Revision(
        new \Addwiki\Mediawiki\DataModel\Content( "Question answered: " . json_encode(['user' => $user->id, 'question' => $question, 'answer' => $answer]) ),
        new \Addwiki\Mediawiki\DataModel\PageIdentifier( new \Addwiki\Mediawiki\DataModel\Title( 'From Laravel Via OAuth' ) )
    ) );

    // TODO redirct to the next question...
    return "posted and got result ${result}!";
});
