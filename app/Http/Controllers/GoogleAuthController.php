<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\SingleSession;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function settings()
    {
        return view('account.google');
    }

    public function redirectToGoogle(Request $request)
    {
        if (!$this->isConfigured()) {
            return redirect()->route('getLogin')->with('error', 'Google sign-in is not configured yet. Please use your username and password.');
        }

        $request->session()->put('google_oauth_intent', 'login');
        $request->session()->forget('google_oauth_link_user_id');

        return Socialite::driver('google')->redirect();
    }

    public function linkGoogle(Request $request)
    {
        if (!$this->isConfigured()) {
            return redirect()->route('account.google')->with('error', 'Google sign-in is not configured yet.');
        }

        $request->session()->put('google_oauth_intent', 'link');
        $request->session()->put('google_oauth_link_user_id', $request->user()->id);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        $intent = $request->session()->pull('google_oauth_intent');
        $linkUserId = $request->session()->pull('google_oauth_link_user_id');

        if (!in_array($intent, ['login', 'link'], true) || !$this->isConfigured()) {
            return redirect()->route('getLogin')->with('error', 'Please start Google sign-in again.');
        }

        $destination = $intent === 'link' ? 'account.google' : 'getLogin';

        if ($request->filled('error')) {
            return redirect()->route($destination)->with('error', 'Google sign-in was cancelled.');
        }

        try {
            // Socialite validates the OAuth state stored in this browser session.
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed', ['exception' => get_class($exception)]);
            return redirect()->route($destination)->with('error', 'Google sign-in could not be completed. Please try again.');
        }

        $googleId = (string) $googleUser->getId();
        if ($googleId === '') {
            return redirect()->route($destination)->with('error', 'Google did not return an account ID. Please try again.');
        }

        if ($intent === 'link') {
            $user = $request->user();
            if (!$user || !$linkUserId || (int) $user->id !== (int) $linkUserId) {
                return redirect()->route('getLogin')->with('error', 'Your session changed. Sign in and connect Google again.');
            }

            if (User::where('google_id', $googleId)->whereKeyNot($user->id)->exists()) {
                return redirect()->route('account.google')->with('error', 'This Google account is already connected to another user.');
            }

            try {
                User::whereKey($user->id)->update(['google_id' => $googleId]);
            } catch (QueryException $exception) {
                return redirect()->route('account.google')->with('error', 'This Google account could not be connected. Please try again.');
            }

            $user->setAttribute('google_id', $googleId);
            AuditLog::log('update', 'auth', 'Connected Google sign-in');

            return redirect()->route('account.google')->with('success', 'Your Google account is connected. You can now sign in with Google.');
        }

        $user = User::where('google_id', $googleId)->first();
        if (!$user || !$user->hasLoginRole()) {
            return redirect()->route('getLogin')->with('error', 'This Google account is not connected to a user. Sign in with your username and password, then connect Google from your account menu.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        SingleSession::establish($user, $request);
        AuditLog::log('login', 'auth', 'Signed in with Google');

        return redirect()->route('dashboard')->with('success', 'You have successfully logged in with Google.');
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
