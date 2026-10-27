@extends('layouts.main')

@section('title', 'Change password')

@section('content')
<style>
    .kp-pw-card { overflow: hidden; }
    .kp-pw-head { display: flex; align-items: center; gap: 14px; padding: 20px 24px; border-bottom: 1px solid var(--kp-border); background: var(--kp-surface-2); }
    .kp-pw-badge { display: grid; place-items: center; flex: none; width: 44px; height: 44px; border-radius: 12px; background: var(--kp-emerald); color: #fff; font-size: 18px; box-shadow: 0 4px 12px -4px rgba(0, 63, 32, 0.45); }
    .kp-pw-body { padding: 24px; display: grid; gap: 20px; }
    .kp-pw-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--kp-text); }
    .kp-pw-field { position: relative; }
    .kp-pw-field > .kp-pw-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--kp-faint); pointer-events: none; font-size: 14px; }
    .kp-content .kp-pw-input {
        display: block; width: 100%; height: 46px; padding: 0 46px 0 42px;
        font-size: 15px; color: var(--kp-text); background: var(--kp-surface);
        border: 1.5px solid var(--kp-border-strong); border-radius: 10px; box-shadow: var(--kp-shadow-sm);
    }
    .kp-content .kp-pw-input:hover { border-color: var(--kp-faint); }
    .kp-content .kp-pw-input.is-invalid { border-color: #d14343 !important; }
    .kp-content .kp-pw-input.is-valid { border-color: var(--kp-emerald); }
    .kp-pw-toggle { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border-radius: 8px; color: var(--kp-muted); display: grid; place-items: center; }
    .kp-pw-toggle:hover { background: var(--kp-hover); color: var(--kp-text); }
    .kp-pw-toggle:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--kp-ring); }
    .kp-pw-error { display: flex; align-items: flex-start; gap: 6px; margin-top: 6px; font-size: 13px; color: #c53030; }
    .dark .kp-pw-error { color: #f5a3a3; }
    .kp-pw-meter { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 10px; }
    .kp-pw-meter span { height: 6px; border-radius: 999px; background: var(--kp-border); transition: background-color 0.2s ease; }
    .kp-pw-rules { padding: 14px 16px; border: 1px solid var(--kp-border); border-radius: 10px; background: var(--kp-surface-2); }
    .kp-pw-rules ul { display: grid; gap: 8px; margin-top: 10px; font-size: 13px; }
    @media (min-width: 640px) { .kp-pw-rules ul { grid-template-columns: 1fr 1fr; } }
    .kp-pw-rules li { display: flex; align-items: center; gap: 8px; transition: color 0.15s ease; }
    .kp-pw-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--kp-border); background: var(--kp-surface-2); }
    .kp-pw-actions .kp-btn { height: 42px; padding: 0 18px; }
    @media (max-width: 639px) {
        .kp-pw-head, .kp-pw-body, .kp-pw-actions { padding-left: 16px; padding-right: 16px; }
        .kp-pw-actions .kp-btn { flex: 1 1 auto; justify-content: center; }
    }
</style>

<div class="max-w-2xl mx-auto space-y-5 kp-fade-in">
    <header class="kp-page-header">
        <div>
            <p class="kp-eyebrow">Account security</p>
            <h1 class="kp-page-title mt-1">Change password</h1>
            <p class="kp-page-sub">Choose a strong password that you do not use anywhere else.</p>
        </div>
    </header>

    @if($forced)
        <div class="flex gap-3 p-4 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:border-amber-500/40 dark:text-amber-200" role="alert">
            <i class="fas fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
            <div>
                <p class="font-semibold">Password change required</p>
                <p class="text-sm mt-0.5">Your account is using a default or weak password. Set a new password to continue using the system.</p>
            </div>
        </div>
    @endif
    @if(session('success'))
        <div class="flex gap-3 p-4 rounded-xl border border-green-300 bg-green-50 text-green-900 dark:bg-green-500/10 dark:border-green-500/40 dark:text-green-200" role="status">
            <i class="fas fa-circle-check mt-0.5" aria-hidden="true"></i>
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error') && !$forced)
        <div class="flex gap-3 p-4 rounded-xl border border-red-300 bg-red-50 text-red-900 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-200" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
            <p class="text-sm">{{ session('error') }}</p>
        </div>
    @endif

    <section class="kp-card kp-pw-card" aria-labelledby="pw-card-title"
        x-data="passwordForm(@js(auth()->user()->username), {{ \App\Support\PasswordPolicy::MIN }})">
        <div class="kp-pw-head">
            <span class="kp-pw-badge"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
            <div class="min-w-0">
                <h2 id="pw-card-title" class="text-base font-semibold" style="color: var(--kp-text)">{{ auth()->user()->fname }} {{ auth()->user()->lname }}</h2>
                <p class="text-sm truncate" style="color: var(--kp-muted)">
                    Signed in as <span class="font-medium" style="color: var(--kp-text)">{{ auth()->user()->username }}</span>
                    @if(auth()->user()->password_changed_at)
                        · last changed {{ auth()->user()->password_changed_at->diffForHumans() }}
                    @endif
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('account.password.update') }}" @submit="submitting = true">
            @csrf
            @method('PUT')

            <div class="kp-pw-body">
                <div>
                    <label for="current_password" class="kp-pw-label">Current password</label>
                    <div class="kp-pw-field">
                        <i class="fas fa-lock kp-pw-icon" aria-hidden="true"></i>
                        <input id="current_password" name="current_password" :type="showCurrent ? 'text' : 'password'" required
                            autocomplete="current-password" placeholder="Enter your current password"
                            class="kp-pw-input @error('current_password') is-invalid @enderror"
                            @error('current_password') aria-invalid="true" aria-describedby="current-password-error" @enderror>
                        <button type="button" class="kp-pw-toggle" @click="showCurrent = !showCurrent"
                            :aria-label="showCurrent ? 'Hide current password' : 'Show current password'">
                            <i :class="showCurrent ? 'fas fa-eye-slash' : 'fas fa-eye'" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('current_password')
                        <p id="current-password-error" class="kp-pw-error"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i>{{ $message }}</p>
                    @enderror
                </div>

                <div class="border-t" style="border-color: var(--kp-border)"></div>

                <div>
                    <label for="password" class="kp-pw-label">New password</label>
                    <div class="kp-pw-field">
                        <i class="fas fa-key kp-pw-icon" aria-hidden="true"></i>
                        <input id="password" name="password" :type="showNew ? 'text' : 'password'" required autocomplete="new-password"
                            maxlength="{{ \App\Support\PasswordPolicy::MAX }}" x-model="password" placeholder="Create a new password"
                            class="kp-pw-input @error('password') is-invalid @enderror" :class="{ 'is-valid': allOk() }"
                            aria-describedby="password-strength password-rules">
                        <button type="button" class="kp-pw-toggle" @click="showNew = !showNew"
                            :aria-label="showNew ? 'Hide new password' : 'Show new password'">
                            <i :class="showNew ? 'fas fa-eye-slash' : 'fas fa-eye'" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('password')
                        @foreach($errors->get('password') as $error)
                            <p class="kp-pw-error"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i>{{ $error }}</p>
                        @endforeach
                    @enderror

                    <div class="kp-pw-meter" aria-hidden="true">
                        <template x-for="i in 4" :key="i">
                            <span :style="i <= strength().bars ? `background:${strength().color}` : ''"></span>
                        </template>
                    </div>
                    <p id="password-strength" class="mt-1.5 text-xs font-medium" :style="`color:${password ? strength().color : 'var(--kp-faint)'}`"
                        x-text="password ? `Strength: ${strength().label}` : 'Start typing to check strength'"></p>
                </div>

                <div id="password-rules" class="kp-pw-rules">
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color: var(--kp-muted)">Your password must have</p>
                    <ul role="list">
                        <template x-for="rule in rules" :key="rule.label">
                            <li :style="rule.ok() ? 'color: var(--kp-emerald)' : 'color: var(--kp-muted)'">
                                <i :class="rule.ok() ? 'fas fa-circle-check' : 'far fa-circle'" aria-hidden="true"></i>
                                <span x-text="rule.label"></span>
                                <span class="sr-only" x-text="rule.ok() ? '(met)' : '(not met)'"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div>
                    <label for="password_confirmation" class="kp-pw-label">Confirm new password</label>
                    <div class="kp-pw-field">
                        <i class="fas fa-key kp-pw-icon" aria-hidden="true"></i>
                        <input id="password_confirmation" name="password_confirmation" :type="showNew ? 'text' : 'password'" required
                            autocomplete="new-password" x-model="confirmation" placeholder="Type the new password again"
                            class="kp-pw-input"
                            :class="{ 'is-invalid': confirmation && confirmation !== password, 'is-valid': confirmation && confirmation === password && allOk() }">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm" x-show="confirmation" x-cloak
                            :style="confirmation === password ? 'color: var(--kp-emerald)' : 'color: #d14343'">
                            <i :class="confirmation === password ? 'fas fa-circle-check' : 'fas fa-circle-xmark'" aria-hidden="true"></i>
                        </span>
                    </div>
                    <p x-show="confirmation && confirmation !== password" x-cloak class="kp-pw-error">
                        <i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i>The passwords do not match.
                    </p>
                </div>
            </div>

            <div class="kp-pw-actions">
                @if($forced)
                    <a href="{{ route('logout') }}" class="kp-btn kp-btn-secondary inline-flex items-center gap-2">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign out
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" class="kp-btn kp-btn-secondary inline-flex items-center">Cancel</a>
                @endif
                <button type="submit" class="kp-btn kp-btn-primary inline-flex items-center gap-2"
                    :disabled="submitting || !allOk() || password !== confirmation">
                    <i :class="submitting ? 'fas fa-circle-notch fa-spin' : 'fas fa-check'" aria-hidden="true"></i>
                    <span x-text="submitting ? 'Saving…' : 'Update password'">Update password</span>
                </button>
            </div>
        </form>
    </section>
</div>

<script>
    function passwordForm(username, min) {
        return {
            password: '',
            confirmation: '',
            showCurrent: false,
            showNew: false,
            submitting: false,
            get rules() {
                const p = this.password;
                return [
                    { label: `At least ${min} characters`, ok: () => p.length >= min },
                    { label: 'An uppercase letter (A–Z)', ok: () => /\p{Lu}/u.test(p) },
                    { label: 'A lowercase letter (a–z)', ok: () => /\p{Ll}/u.test(p) },
                    { label: 'A number (0–9)', ok: () => /\p{N}/u.test(p) },
                    { label: 'A symbol (e.g. ! @ # $)', ok: () => /[\p{Z}\p{S}\p{P}]/u.test(p) },
                    { label: 'Not your username', ok: () => p !== '' && (username.length < 3 || !p.toLowerCase().includes(username.toLowerCase())) },
                ];
            },
            allOk() {
                return this.rules.every(rule => rule.ok());
            },
            strength() {
                const met = this.rules.filter(rule => rule.ok()).length;
                if (this.allOk() && this.password.length >= 12) return { bars: 4, label: 'Strong', color: 'var(--kp-emerald)' };
                if (this.allOk()) return { bars: 3, label: 'Good', color: 'var(--kp-emerald)' };
                if (met >= 4) return { bars: 2, label: 'Fair', color: '#c79520' };
                return { bars: 1, label: 'Weak', color: '#d14343' };
            },
        };
    }
</script>
@endsection
