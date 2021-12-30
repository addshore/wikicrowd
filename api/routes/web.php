<?php

use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/edit', function () {
    return view('edit');
})->name('edit');

Route::middleware('auth:sanctum')->post('/edit', function () {
    $user = Auth::user();
    $mwAuth = new \Addwiki\Mediawiki\Api\Client\Auth\OAuthOwnerConsumer(
        config('services.mediawiki.identifier'),
        config('services.mediawiki.secret'),
        $user->token,
        $user->token_secret
    );

    $mw = \Addwiki\Mediawiki\Api\Client\MediaWiki::newFromEndpoint( 'https://addshore-alpha.wiki.opencura.com/w/api.php', $mwAuth );
    $mwServices = new \Addwiki\Mediawiki\Api\MediawikiFactory( $mw->action() );

    $result = $mwServices->newRevisionSaver()->save( new \Addwiki\Mediawiki\DataModel\Revision(
        new \Addwiki\Mediawiki\DataModel\Content( 'Hello World (from laravel)' ),
        new \Addwiki\Mediawiki\DataModel\PageIdentifier( new \Addwiki\Mediawiki\DataModel\Title( 'From Laravel Via OAuth' ) )
    ) );

    return "posted and got result ${result}!";
});
