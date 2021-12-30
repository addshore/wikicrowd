<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Redirect;

class OAuthUserController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->only(['login', 'callback']);
        $this->middleware('auth')->only('logout');
    }

    public function login(Request $request)
    {
        $referer = $request->headers->get('referer');
        Redirect::setIntendedUrl($referer);

        return Socialite::driver('mediawiki')->redirect();
    }

    public function callback()
    {
        $socialiteUser = Socialite::driver('mediawiki')->user();

        $user = User::where('username',$socialiteUser->name)->where('mw_userid',$socialiteUser->id)->first();

        if($user) {
            $user->update([
                'token' => $socialiteUser->token,
                'token_secret' => $socialiteUser->tokenSecret,
            ]);
        } else {
            $user = User::create([
                'username' => $socialiteUser->name,
                'mw_userid' => $socialiteUser->id,
                'token' => $socialiteUser->token,
                'token_secret' => $socialiteUser->tokenSecret,
            ]);
        }

        Auth::login($user, false);
        return redirect()->intended();
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'token' => null,
            'token_secret' => null,
        ]);

        $referer = $request->headers->get('referer');
        Auth::guard()->logout();

        $request->session()->invalidate();

        return redirect('/');
    }
}