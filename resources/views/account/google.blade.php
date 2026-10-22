@extends('layouts.main')

@section('title', 'Google sign-in')

@section('content')
<div class="max-w-2xl mx-auto space-y-6 kp-fade-in">
    <header class="kp-page-header">
        <div>
            <p class="kp-eyebrow">Account security</p>
            <h1 class="kp-page-title mt-1">Google sign-in</h1>
            <p class="kp-page-sub">Connect a Google account to sign in without entering your username and password next time.</p>
        </div>
    </header>

    @if(session('success'))
        <div class="p-4 rounded-lg border border-green-200 bg-green-50 text-green-800" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-lg border border-red-200 bg-red-50 text-red-800" role="alert">{{ session('error') }}</div>
    @endif

    <section class="kp-card p-6 space-y-5" aria-label="Google account connection">
        <div>
            <p class="text-sm font-semibold" style="color: var(--kp-text)">{{ auth()->user()->fname }} {{ auth()->user()->lname }}</p>
            <p class="text-sm" style="color: var(--kp-muted)">Username: {{ auth()->user()->username }}</p>
        </div>

        @if(auth()->user()->google_id)
            <p class="text-sm" style="color: var(--kp-forest)"><i class="fas fa-check-circle mr-1" aria-hidden="true"></i> A Google account is connected.</p>
        @else
            <p class="text-sm" style="color: var(--kp-muted)">No Google account is connected yet. Your username and password still work.</p>
        @endif

        @if(config('services.google.client_id') && config('services.google.client_secret') && config('services.google.redirect'))
            <a href="{{ route('google.link') }}" class="kp-btn kp-btn-primary inline-flex items-center gap-2">
                <i class="fab fa-google" aria-hidden="true"></i>
                {{ auth()->user()->google_id ? 'Change connected Google account' : 'Connect Google account' }}
            </a>
            <p class="text-xs" style="color: var(--kp-muted)">You will be sent to Google to choose and approve an account. Only one user can connect each Google account.</p>
        @else
            <p class="text-sm" style="color: var(--kp-muted)">Google sign-in is awaiting administrator setup.</p>
        @endif
    </section>
</div>
@endsection
