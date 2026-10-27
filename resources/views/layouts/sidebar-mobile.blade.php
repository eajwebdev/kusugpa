<!-- resources/views/layouts/sidebar-mobile.blade.php -->
<div class="kp-sidebar" style="box-shadow: 0 0 40px rgba(0,0,0,.35)">
    <div class="flex items-center justify-between pr-3">
        <a href="{{ route('dashboard') }}" class="kp-brand" aria-label="KUSUG-PA — go to dashboard">
            <img src="{{ asset('img/logo-128.webp') }}" width="40" height="40" alt="" class="kp-brand-logo">
            <span class="kp-brand-text">
                <span class="kp-brand-name block">KUSUG-PA</span>
                <span class="kp-brand-sub block">Planters Association</span>
            </span>
        </a>
        <button type="button" @click="sidebarOpen = false" class="kp-icon-btn" style="color:#CFE5D6" aria-label="Close menu">
            <i class="fas fa-times text-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="kp-nodes" aria-hidden="true"></div>

    <div class="kp-nav-scroll">
        <nav class="kp-nav" aria-label="Main">
            <ul role="list">
                <li>
                    <ul role="list">
                        @include('layouts.sidebar-items')
                    </ul>
                </li>
            </ul>
        </nav>
    </div>

    <!-- User profile (menu opens upward) -->
    <div class="kp-profile" x-data="profileDropdown()">
        <div x-show="profileOpen" x-cloak @click.away="profileOpen = false" @keydown.escape.window="profileOpen = false"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="kp-menu absolute bottom-full left-3 right-3 mb-2 z-50"
            role="menu" style="display: none;">

            <div class="kp-menu-head">
                <div class="flex items-center gap-3">
                    <span class="kp-avatar">{{ strtoupper(substr(auth()->user()->fname, 0, 1)) }}{{ strtoupper(substr(auth()->user()->lname, 0, 1)) }}</span>
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--kp-text)">{{ auth()->user()->fname }} {{ auth()->user()->lname }}</p>
                        <p class="text-xs capitalize" style="color: var(--kp-muted)">{{ auth()->user()->role }}</p>
                    </div>
                </div>
            </div>

            <div class="py-1">
                <a href="{{ route('account.google') }}" role="menuitem">
                    <i class="fab fa-google w-5 text-center" style="color: var(--kp-emerald)"></i>
                    <span>Google sign-in</span>
                </a>
                <a href="{{ route('account.password.edit') }}" role="menuitem">
                    <i class="fas fa-key w-5 text-center" style="color: var(--kp-emerald)"></i>
                    <span>Change password</span>
                </a>
                @if (auth()->user()->role === 'Administrator')
                    <a href="{{ route('admin.dashboard') }}" role="menuitem">
                        <i class="fas fa-shield-halved w-5 text-center" style="color: var(--kp-emerald)"></i>
                        <span>System Panel</span>
                    </a>
                @endif

                <button type="button" @click="toggleTheme()" class="kp-menu-item justify-between" role="menuitemcheckbox"
                    :aria-checked="isDark.toString()">
                    <span class="flex items-center gap-3">
                        <i :class="isDark ? 'fas fa-sun w-5 text-center' : 'fas fa-moon w-5 text-center'"
                            :style="isDark ? 'color:#E7C469' : 'color:var(--kp-faint)'"></i>
                        <span>Dark mode</span>
                    </span>
                    <span class="relative inline-block h-5 w-9 rounded-full transition-colors duration-200"
                        :style="isDark ? 'background:var(--kp-emerald)' : 'background:var(--kp-border-strong)'">
                        <span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform duration-200"
                            :class="isDark ? 'translate-x-4' : 'translate-x-0'"></span>
                    </span>
                </button>

                <div class="my-1 border-t" style="border-color: var(--kp-border)"></div>

                <a href="{{ route('logout') }}" class="kp-danger" role="menuitem">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i>
                    <span>Sign out</span>
                </a>
            </div>
        </div>

        <div class="p-3">
            <button type="button" @click="profileOpen = !profileOpen" class="kp-profile-btn" aria-haspopup="menu"
                :aria-expanded="profileOpen.toString()" aria-label="Account menu">
                <span class="kp-avatar">{{ strtoupper(substr(auth()->user()->fname, 0, 1)) }}{{ strtoupper(substr(auth()->user()->lname, 0, 1)) }}</span>
                <span class="kp-profile-text min-w-0 flex-1">
                    <span class="kp-profile-name block truncate">{{ auth()->user()->fname }} {{ auth()->user()->lname }}</span>
                    <span class="kp-profile-role block truncate">{{ auth()->user()->role }}</span>
                </span>
                <i class="fas fa-chevron-up text-xs transition-transform duration-200" style="color: #8FBBA0"
                    :class="{ 'rotate-180': profileOpen }" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>
