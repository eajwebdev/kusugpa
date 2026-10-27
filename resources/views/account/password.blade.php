@extends('layouts.main')

@section('title', 'Change password')

@section('content')
<div class="max-w-2xl mx-auto space-y-6 kp-fade-in">
    <header class="kp-page-header">
        <div>
            <p class="kp-eyebrow">Account security</p>
            <h1 class="kp-page-title mt-1">Change password</h1>
            <p class="kp-page-sub">Choose a strong password that you do not use anywhere else.</p>
        </div>
    </header>

    @if($forced)
        <div class="p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-800" role="alert">
            <i class="fas fa-triangle-exclamation mr-1" aria-hidden="true"></i>
            Your account is using a default or weak password. Set a new password to continue using the system.
        </div>
    @endif
    @if(session('success'))
        <div class="p-4 rounded-lg border border-green-200 bg-green-50 text-green-800" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error') && !$forced)
        <div class="p-4 rounded-lg border border-red-200 bg-red-50 text-red-800" role="alert">{{ session('error') }}</div>
    @endif

    <section class="kp-card p-6" aria-label="Change password"
        x-data="passwordForm(@js(auth()->user()->username), {{ \App\Support\PasswordPolicy::MIN }})">
        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-5" @submit="submitting = true">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-sm font-semibold mb-1" style="color: var(--kp-text)">Current password</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                    class="kp-input w-full">
                @error('current_password')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold mb-1" style="color: var(--kp-text)">New password</label>
                <div class="relative">
                    <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="new-password"
                        maxlength="{{ \App\Support\PasswordPolicy::MAX }}" x-model="password" class="kp-input w-full pr-10"
                        aria-describedby="password-rules">
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3"
                        style="color: var(--kp-muted)" :aria-label="show ? 'Hide password' : 'Show password'">
                        <i :class="show ? 'fas fa-eye-slash' : 'fas fa-eye'" aria-hidden="true"></i>
                    </button>
                </div>
                @error('password')
                    @foreach($errors->get('password') as $error)
                        <p class="text-sm text-red-600 mt-1">{{ $error }}</p>
                    @endforeach
                @enderror

                <ul id="password-rules" class="mt-3 grid gap-1 sm:grid-cols-2 text-sm" role="list">
                    <template x-for="rule in rules" :key="rule.label">
                        <li class="flex items-center gap-2" :style="rule.ok() ? 'color: var(--kp-forest)' : 'color: var(--kp-muted)'">
                            <i :class="rule.ok() ? 'fas fa-check-circle' : 'far fa-circle'" aria-hidden="true"></i>
                            <span x-text="rule.label"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold mb-1" style="color: var(--kp-text)">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" required
                    autocomplete="new-password" x-model="confirmation" class="kp-input w-full">
                <p x-show="confirmation && confirmation !== password" x-cloak class="text-sm text-red-600 mt-1">The passwords do not match.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                @unless($forced)
                    <a href="{{ route('dashboard') }}" class="kp-btn kp-btn-secondary">Cancel</a>
                @endunless
                @if($forced)
                    <a href="{{ route('logout') }}" class="kp-btn kp-btn-secondary">Sign out</a>
                @endif
                <button type="submit" class="kp-btn kp-btn-primary disabled:opacity-50"
                    :disabled="submitting || !allOk() || password !== confirmation">
                    <i class="fas fa-key mr-1" aria-hidden="true"></i> Change password
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
            show: false,
            submitting: false,
            get rules() {
                const p = this.password;
                return [
                    { label: `At least ${min} characters`, ok: () => p.length >= min },
                    { label: 'An uppercase letter', ok: () => /\p{Lu}/u.test(p) },
                    { label: 'A lowercase letter', ok: () => /\p{Ll}/u.test(p) },
                    { label: 'A number', ok: () => /\p{N}/u.test(p) },
                    { label: 'A symbol (e.g. ! @ # $)', ok: () => /[\p{Z}\p{S}\p{P}]/u.test(p) },
                    { label: 'Does not contain your username', ok: () => p !== '' && (username.length < 3 || !p.toLowerCase().includes(username.toLowerCase())) },
                ];
            },
            allOk() {
                return this.rules.every(rule => rule.ok());
            },
        };
    }
</script>
@endsection
