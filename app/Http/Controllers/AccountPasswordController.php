<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\PasswordPolicy;
use App\Support\SingleSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountPasswordController extends Controller
{
    public function edit(Request $request)
    {
        return view('account.password', [
            'forced' => $request->user()->mustChangePassword(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', ...PasswordPolicy::rules($user->username)],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'The new password must be different from your current password.',
        ]);

        $wasForced = $user->mustChangePassword();

        $user->forceFill([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // New session ID after a credential change; keep this browser as the account's single active session
        $request->session()->regenerate();
        SingleSession::establish($user, $request);

        AuditLog::log('update', 'auth', $wasForced ? 'Changed required default password' : 'Changed own password');

        if ($wasForced) {
            return redirect()->route('dashboard')->with('success', 'Your password has been changed. Welcome!');
        }

        return redirect()->route('account.password.edit')->with('success', 'Your password has been changed.');
    }
}
