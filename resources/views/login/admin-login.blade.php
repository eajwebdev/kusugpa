<!-- resources/views/login/admin-login.blade.php -->
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>KUSUG-PA | System Administration</title>
    @include('layouts.partials.assets')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @if(config('services.turnstile.site_key') && config('services.turnstile.secret_key'))
    <script defer src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&amp;onload=kpAdminTurnstileReady"></script>
    @endif
    <style>
        .admin-bg-pattern {
            background-color: #0A160F;
            background-image: 
                radial-gradient(circle at 20% 25%, rgba(8, 122, 61, 0.18) 0%, transparent 45%),
                radial-gradient(circle at 80% 75%, rgba(217, 181, 74, 0.10) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(0, 91, 42, 0.12) 0%, transparent 70%);
        }
        .admin-grid-lines {
            background-image: 
                linear-gradient(to right, rgba(234, 245, 237, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(234, 245, 237, 0.03) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .admin-input {
            background: rgba(14, 30, 21, 0.7);
            border: 1px solid rgba(82, 112, 95, 0.35);
            color: #EAF5ED;
            transition: all 0.2s ease;
        }
        .admin-input:focus {
            background: rgba(14, 30, 21, 0.95);
            border-color: #52C97E;
            box-shadow: 0 0 0 3px rgba(8, 122, 61, 0.25);
            outline: none;
        }
        .admin-input::placeholder {
            color: #52705F;
        }
        .shake {
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
    </style>
</head>
<body class="h-full admin-bg-pattern text-[#EAF5ED] flex items-center justify-center min-h-screen p-4 sm:p-6"
      x-data="{ 
          showPassword: false, 
          loading: false, 
          step: 1,
          securityKey: ''
      }">

    @include('layouts.partials.manual-help-button', ['manualHelpClass' => 'kp-manual-help--fixed kp-manual-help--on-dark'])

    <div class="fixed inset-0 admin-grid-lines pointer-events-none"></div>

    <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-12 rounded-3xl overflow-hidden border border-[#203A2B] bg-[#0E1E15]/90 shadow-2xl relative z-10 backdrop-blur-xl">
        
        <!-- Left Side: Administration Branding -->
        <div class="lg:col-span-5 bg-gradient-to-br from-[#003F20] to-[#0A1C12] p-8 sm:p-10 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-[#203A2B] relative overflow-hidden">
            <div class="absolute -right-16 -bottom-16 w-56 h-56 rounded-full bg-[#005B2A]/20 blur-3xl pointer-events-none"></div>

            <div>
                <div class="flex items-center gap-3 mb-6">
                    <picture>
                        <source srcset="{{ asset('img/logo-64.webp') }}" type="image/webp">
                        <img src="{{ asset('img/logo-64.png') }}"
                             alt="KUSUG-PA Seal"
                             width="48" height="48"
                             class="w-12 h-12 object-contain rounded-xl p-1 bg-white/10 border border-white/20">
                    </picture>
                    <div>
                        <span class="block text-xs uppercase font-bold tracking-widest text-[#D9B54A]">KUSUG-PA System</span>
                        <h2 class="font-display text-xl font-bold text-white tracking-tight">Security Vault</h2>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-300 border border-rose-500/30 mb-6">
                    <span class="w-2 h-2 rounded-full bg-rose-400 animate-pulse"></span>
                    Restricted Area • Tier 1 Access
                </div>

                <p class="text-xs text-[#8AA895] leading-relaxed mb-6">
                    System Administration & Configuration Portal. Authorized personnel only. All access, failed authentications, and parameter modifications are cryptographically signed in the immutable audit trail.
                </p>

                <div class="space-y-3">
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/5 text-xs text-[#EAF5ED]">
                        <i class="fas fa-sliders text-[#52C97E] text-sm w-4 text-center"></i>
                        <span>System parameters & fee schedules</span>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/5 text-xs text-[#EAF5ED]">
                        <i class="fas fa-users-gear text-[#D9B54A] text-sm w-4 text-center"></i>
                        <span>Role delegations & RBAC controls</span>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/5 text-xs text-[#EAF5ED]">
                        <i class="fas fa-database text-blue-400 text-sm w-4 text-center"></i>
                        <span>Disaster recovery & data control</span>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-white/10 flex items-center justify-between text-[11px] text-[#52705F]">
                <span>&copy; {{ date('Y') }} KUSUG-PA Planters Assn.</span>
                <span class="flex items-center gap-1.5 text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Core Active
                </span>
            </div>
        </div>

        <!-- Right Side: Two-Step Verification Form -->
        <div class="lg:col-span-7 p-8 sm:p-10 flex flex-col justify-center bg-[#0C1A12]/80">
            <div class="max-w-md w-full mx-auto">
                
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <h1 class="text-xl font-bold font-display text-white">Administrator Access</h1>
                        <!-- Progress indicator -->
                        <div class="flex items-center gap-1.5">
                            <span class="h-1.5 rounded-full transition-all duration-300" 
                                  :class="step === 1 ? 'w-6 bg-[#52C97E]' : 'w-2 bg-[#203A2B]'"></span>
                            <span class="h-1.5 rounded-full transition-all duration-300" 
                                  :class="step === 2 ? 'w-6 bg-[#52C97E]' : 'w-2 bg-[#203A2B]'"></span>
                        </div>
                    </div>
                    <p class="text-xs text-[#8AA895]">
                        <span x-show="step === 1">Step 1: Enter your security access key.</span>
                        <span x-show="step === 2" x-cloak>Step 2: Sign in with master administrator credentials.</span>
                    </p>
                </div>

                <!-- Alert Messages -->
                @if(session('admin_error'))
                <div class="mb-5 bg-rose-500/10 border border-rose-500/30 rounded-xl p-3.5 flex items-start gap-3" x-data="{ show: true }" x-show="show">
                    <i class="fas fa-shield-cat text-rose-400 mt-0.5 text-sm"></i>
                    <p class="text-xs text-rose-300 flex-1">{{ session('admin_error') }}</p>
                    <button @click="show = false" class="text-rose-400 hover:text-rose-200 text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                @endif

                @if($errors->any())
                <div class="mb-5 bg-rose-500/10 border border-rose-500/30 rounded-xl p-3.5 flex items-start gap-3" x-data="{ show: true }" x-show="show">
                    <i class="fas fa-triangle-exclamation text-rose-400 mt-0.5 text-sm"></i>
                    <div class="text-xs text-rose-300 flex-1 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                    <button @click="show = false" class="text-rose-400 hover:text-rose-200 text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                @endif

                <!-- Step 1: Security Key Verification -->
                <div x-show="step === 1" x-transition>
                    <form @submit.prevent="verifySecurityKey" class="space-y-4">
                        <div>
                            <label for="security_key" class="block text-xs font-semibold text-[#8AA895] uppercase tracking-wider mb-2">
                                <i class="fas fa-key text-[#D9B54A] mr-1.5"></i> Master Security Access Key
                            </label>
                            <div class="relative group">
                                <input id="security_key" x-model="securityKey" :type="showPassword ? 'text' : 'password'" required
                                       class="admin-input block w-full pl-4 pr-11 py-3 rounded-xl text-sm"
                                       placeholder="Enter security access key" autocomplete="off" autofocus>
                                <button type="button" @click="showPassword = !showPassword" 
                                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#52705F] hover:text-[#EAF5ED]">
                                    <i class="fas text-xs" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" 
                                class="w-full flex items-center justify-center py-3 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-[#005B2A] to-[#087A3D] hover:from-[#087A3D] hover:to-[#005B2A] border border-[#52C97E]/30 shadow-lg shadow-black/40 transition">
                            <span>Proceed to Admin Sign-In</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </button>
                    </form>
                </div>

                <!-- Step 2: Admin Credentials -->
                <div x-show="step === 2" x-transition x-cloak>
                    <form class="space-y-4" action="{{ route('admin.postLogin') }}" method="POST" @submit="loading = true">
                        @csrf
                        <input type="hidden" name="security_key" :value="securityKey">

                        <!-- Username -->
                        <div>
                            <label for="username" class="block text-xs font-semibold text-[#8AA895] uppercase tracking-wider mb-2">
                                <i class="fas fa-user-shield text-[#52C97E] mr-1.5"></i> Admin Username
                            </label>
                            <input id="username" name="username" type="text" required
                                   class="admin-input block w-full px-4 py-3 rounded-xl text-sm"
                                   placeholder="Enter admin username" value="{{ old('username') }}" autocomplete="off" autofocus>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-xs font-semibold text-[#8AA895] uppercase tracking-wider mb-2">
                                <i class="fas fa-lock text-[#52C97E] mr-1.5"></i> Admin Password
                            </label>
                            <div class="relative">
                                <input id="password" name="password" 
                                       :type="showPassword ? 'text' : 'password'"
                                       required
                                       class="admin-input block w-full px-4 pr-11 py-3 rounded-xl text-sm"
                                       placeholder="Enter password" autocomplete="off">
                                <button type="button" @click="showPassword = !showPassword" 
                                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#52705F] hover:text-[#EAF5ED]">
                                    <i class="fas text-xs" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <button type="button" @click="step = 1" 
                                    class="text-[#8AA895] hover:text-white text-xs flex items-center gap-1 transition">
                                <i class="fas fa-arrow-left text-[10px]"></i> Change Security Key
                            </button>
                        </div>

                        @if(config('services.turnstile.site_key') && config('services.turnstile.secret_key'))
                        <div class="flex justify-center">
                            <div id="kp-admin-turnstile" class="w-full" data-action="admin_login"></div>
                        </div>
                        @endif

                        <!-- Submit Button -->
                        <button type="submit" 
                                :disabled="loading"
                                class="w-full flex items-center justify-center py-3 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-[#005B2A] to-[#087A3D] hover:from-[#087A3D] hover:to-[#005B2A] border border-[#52C97E]/30 shadow-lg shadow-black/40 transition disabled:opacity-50">
                            <span x-show="!loading" class="flex items-center gap-2">
                                <i class="fas fa-right-to-bracket text-xs"></i> Enter Admin Panel
                            </span>
                            <span x-show="loading" class="flex items-center gap-2" x-cloak>
                                <i class="fas fa-circle-notch fa-spin text-xs"></i> Authenticating...
                            </span>
                        </button>
                    </form>
                </div>

                <!-- Back to regular login -->
                <div class="mt-6 pt-5 border-t border-[#203A2B] text-center">
                    <a href="{{ route('getLogin') }}" class="text-xs text-[#8AA895] hover:text-[#52C97E] transition flex items-center justify-center gap-1.5">
                        <i class="fas fa-arrow-left text-[10px]"></i> Return to Planter/Staff Portal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function verifySecurityKey() {
            if (this.securityKey.trim()) {
                this.step = 2;
                this.showPassword = false;
                if (window.kpRenderAdminTurnstile) setTimeout(window.kpRenderAdminTurnstile, 0);
            }
        }
    </script>
    @if(config('services.turnstile.site_key') && config('services.turnstile.secret_key'))
    <script>
        window.kpRenderAdminTurnstile = function () {
            var container = document.getElementById('kp-admin-turnstile');
            if (!window.turnstile || !container || !container.offsetParent || window.kpAdminWidgetId !== undefined) return;
            window.kpAdminWidgetId = turnstile.render(container, {
                sitekey: @json(config('services.turnstile.site_key')),
                action: 'admin_login',
                size: 'flexible',
                theme: 'dark'
            });
        };
        window.kpAdminTurnstileReady = window.kpRenderAdminTurnstile;
    </script>
    @endif
</body>
</html>
