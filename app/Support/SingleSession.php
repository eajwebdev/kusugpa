<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class SingleSession
{
    public static function establish(User $user, Request $request): void
    {
        $hash = self::hash($request);

        User::query()->whereKey($user->getKey())->update(['active_session_hash' => $hash]);
        $user->setAttribute('active_session_hash', $hash);
    }

    public static function isCurrent(User $user, Request $request): bool
    {
        $storedHash = User::query()->whereKey($user->getKey())->value('active_session_hash');

        return is_string($storedHash) && hash_equals($storedHash, self::hash($request));
    }

    public static function clearIfCurrent(User $user, Request $request): void
    {
        User::query()
            ->whereKey($user->getKey())
            ->where('active_session_hash', self::hash($request))
            ->update(['active_session_hash' => null]);
    }

    private static function hash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
