<!-- resources/views/login/login.blade.php -->
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KUSUG-PA | Sign In</title>
    <script>
        (function() {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @include('layouts.partials.assets')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full" x-data="{ showPassword: false, loading: false }">
    <div class="kp-auth">
        <aside class="kp-auth-aside">
            <div>
                <div class="kp-auth-seal grid place-items-center overflow-hidden mb-7">
                    <picture>
                        <source srcset="{{ asset('img/logo-128.webp') }}" type="image/webp">
                        <img src="{{ asset('img/logo-128.png') }}" alt="KUSUG-PA logo" width="64" height="64" class="w-16 h-16 object-contain">
                    </picture>
                </div>
                <h1 class="kp-auth-h1">KUSUG-PA</h1>
                <p class="text-sm mt-3" style="color:#CFE5D6; max-width:34ch;">
                    Sugarcane Crop Management &amp; Recording System for the Planters Association.
                </p>

                <div class="mt-8">
                    <div class="kp-auth-fact">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <strong class="block text-sm text-white">Real-time crop tracking</strong>
                            <span class="text-xs" style="color:#CFE5D6;">Monitor production, cane tons and weekly throughput.</span>
                        </div>
                    </div>
                    <div class="kp-auth-fact">
                        <i class="fas fa-file-invoice"></i>
                        <div>
                            <strong class="block text-sm text-white">Automated vouchers</strong>
                            <span class="text-xs" style="color:#CFE5D6;">Generate, print and audit delivery vouchers end-to-end.</span>
                        </div>
                    </div>
                    <div class="kp-auth-fact">
                        <i class="fas fa-users"></i>
                        <div>
                            <strong class="block text-sm text-white">Planter management</strong>
                            <span class="text-xs" style="color:#CFE5D6;">Profiles, cash advances, loans and associations.</span>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-xs" style="color:#8AA895;">
                &copy; {{ date('Y') }} KUSUG-PA Planters Association &middot; v1.0
            </p>
        </aside>

        <main class="kp-auth-main">
            <div class="kp-auth-panel">
                <p class="text-sm font-medium" style="color:var(--kp-muted);">Planter / Staff Portal</p>
                <h2 class="kp-auth-title mt-1">Welcome back</h2>
                <p class="text-sm mt-2" style="color:var(--kp-muted);">Sign in to continue to your account.</p>

                @if(session('error'))
                <div class="mt-5 bg-red-50 border border-red-100 rounded-lg p-3.5 flex items-start gap-3" x-data="{ show: true }" x-show="show">
                    <i class="fas fa-exclamation-circle text-red-400 mt-0.5"></i>
                    <p class="text-sm text-red-700 flex-1">{{ session('error') }}</p>
                    <button @click="show = false" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                </div>
                @endif

                @if(session('success'))
                <div class="mt-5 bg-green-50 border border-green-100 rounded-lg p-3.5 flex items-start gap-3" x-data="{ show: true }" x-show="show">
                    <i class="fas fa-check-circle text-green-400 mt-0.5"></i>
                    <p class="text-sm text-green-700 flex-1">{{ session('success') }}</p>
                    <button @click="show = false" class="text-green-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                </div>
                @endif

                <form class="mt-6 space-y-5" action="{{ route('postLogin') }}" method="POST" @submit="loading = true">
                    @csrf

                    <div class="kp-field">
                        <label for="username" class="kp-label">Username</label>
                        <div class="kp-control">
                            <i class="fas fa-user kp-lead"></i>
                            <input id="username" name="username" type="text" required
                                   placeholder="Enter your username"
                                   value="{{ old('username') }}"
                                   autocomplete="username">
                        </div>
                        @error('username')
                            <p class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                                <i class="fas fa-info-circle"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="kp-field">
                        <label for="password" class="kp-label">Password</label>
                        <div class="kp-control">
                            <i class="fas fa-lock kp-lead"></i>
                            <input id="password" name="password"
                                   :type="showPassword ? 'text' : 'password'"
                                   required
                                   placeholder="Enter your password"
                                   autocomplete="current-password"
                                   class="has-trail">
                            <span class="kp-trail">
                                <button type="button" @click="showPassword = !showPassword"
                                        class="w-9 h-9 grid place-items-center rounded text-gray-400 hover:text-gray-600"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                    <i class="fas text-sm" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </span>
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                                <i class="fas fa-info-circle"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 cursor-pointer select-none" style="color:var(--kp-muted);">
                            <input id="remember-me" name="remember-me" type="checkbox"
                                   class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            Remember me
                        </label>
                    </div>

                    <button type="submit" class="kp-submit" :disabled="loading">
                        <i class="fas fa-sign-in-alt"></i>
                        <span x-show="!loading">Sign in</span>
                        <span x-show="loading" x-cloak class="flex items-center gap-2">
                            <i class="fas fa-circle-notch fa-spin"></i> Signing in...
                        </span>
                    </button>
                </form>

                @if(config('demo.enabled'))
                <div class="kp-demo">
                    <div class="relative text-center">
                        <span class="text-xs font-semibold uppercase tracking-wide rounded-full px-3 py-1"
                              style="color:var(--kp-forest); background:var(--kp-soft);">
                            <i class="fas fa-flask mr-1"></i> Demo mode &mdash; one-click sign in
                        </span>
                    </div>
                    <div class="kp-demo-grid">
                        @foreach(config('demo.accounts') as $demo)
                        <form action="{{ route('demoLogin') }}" method="POST">
                            @csrf
                            <input type="hidden" name="username" value="{{ $demo['username'] }}">
                            <button type="submit" class="kp-demo-btn">
                                <span class="kp-demo-ic"><i class="fas fa-user-shield"></i></span>
                                <span class="min-w-0">
                                    <span class="kp-demo-name block truncate">{{ $demo['lname'] }}</span>
                                    <span class="kp-demo-role block truncate">{{ $demo['role'] }}</span>
                                </span>
                            </button>
                        </form>
                        @endforeach
                    </div>
                </div>
                @endif

                <p class="mt-8 text-center text-xs" style="color:var(--kp-faint);">
                    KUSUG-PA Check and Voucher System &copy; {{ date('Y') }}
                </p>
            </div>
        </main>
    </div>
</body>
</html>
