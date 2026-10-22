<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    public function enabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            || filled(config('services.turnstile.secret_key'));
    }

    public function configured(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    public function verify(Request $request): bool
    {
        if (!$this->configured()) {
            return false;
        }

        $testSecrets = [
            '1x0000000000000000000000000000000AA',
            '2x0000000000000000000000000000000AA',
            '3x0000000000000000000000000000000AA',
        ];
        $usingTestSecret = in_array(config('services.turnstile.secret_key'), $testSecrets, true);
        if ($usingTestSecret && config('app.env') === 'production') {
            return false;
        }

        $token = $request->input('cf-turnstile-response');
        if (!is_string($token) || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->connectTimeout(2)->timeout(5)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]
            );
        } catch (ConnectionException $exception) {
            return false;
        }

        if (!$response->successful() || $response->json('success') !== true) {
            return false;
        }

        $expectedAction = $request->routeIs('admin.postLogin') ? 'admin_login' : 'login';
        // Cloudflare's dummy local-development tokens do not carry the widget action.
        if (!$usingTestSecret && $response->json('action') !== $expectedAction) {
            return false;
        }

        $expectedHostname = config('services.turnstile.expected_hostname');

        return !$expectedHostname || hash_equals($expectedHostname, (string) $response->json('hostname'));
    }
}
