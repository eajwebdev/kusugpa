<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public const MIN = 8;
    public const MAX = 64;

    /**
     * At least 8 characters with upper and lower case letters, a number and a symbol
     */
    public static function strength(bool $checkBreaches = true): Password
    {
        $rule = Password::min(self::MIN)->letters()->mixedCase()->numbers()->symbols();

        // The breach lookup calls the Have I Been Pwned API, so only production pays for it
        return $checkBreaches && app()->isProduction() ? $rule->uncompromised() : $rule;
    }

    /**
     * Validation rules for a new password; pass the account's username so it cannot appear in the password
     */
    public static function rules(?string $username = null, bool $checkBreaches = true): array
    {
        return [
            'string',
            'max:' . self::MAX,
            self::strength($checkBreaches),
            function ($attribute, $value, $fail) use ($username) {
                if (filled($username) && mb_strlen($username) >= 3
                    && str_contains(mb_strtolower((string) $value), mb_strtolower($username))) {
                    $fail('The password must not contain your username.');
                }
            },
        ];
    }

    /**
     * Whether a password typed at sign-in falls short of the current rules (e.g. a default or legacy password)
     */
    public static function isWeak(string $password, ?string $username = null): bool
    {
        // Skip the breach lookup here so signing in never waits on an outside service
        return Validator::make(['password' => $password], ['password' => self::rules($username, false)])->fails();
    }

    /**
     * After a successful sign-in, require a password change when the password used is weak
     */
    public static function flagIfWeak(User $user, string $password): void
    {
        if ($user->must_change_password || $user->isDemoAccount() || !self::isWeak($password, $user->username)) {
            return;
        }

        User::query()->whereKey($user->getKey())->update(['must_change_password' => true]);
        $user->setAttribute('must_change_password', true);
    }
}
