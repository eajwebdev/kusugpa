<!-- resources/views/menu/admin-settings.blade.php -->
@extends('layouts.main')

@section('title', 'System Settings')

@section('content')
    <div x-data="adminSettingsData()" x-init="loadSettings()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">System Administration</p>
                    <h1 class="kp-page-title mt-1">System Settings & Controls</h1>
                    <p class="kp-page-sub">Configure organizational parameters, subscription status, emergency security locks, and maintenance procedures.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.dashboard') }}" class="kp-btn kp-btn-secondary text-xs">
                        <i class="fas fa-arrow-left mr-1.5" aria-hidden="true"></i> Admin Dashboard
                    </a>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Settings Navigation -->
            <div class="lg:col-span-1">
                <div class="kp-card p-3">
                    <nav class="space-y-1">
                        <button @click="activeTab = 'general'"
                            :class="activeTab === 'general' ? 'bg-[#EAF5ED] text-[#005B2A] dark:bg-[#1A3324] dark:text-[#52C97E] font-bold' : 'text-[#52705F] dark:text-[#8AA895] hover:bg-[#F7FAF8] dark:hover:bg-[#13231B] font-medium'"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs transition">
                            <i class="fas fa-gear w-4 text-center" aria-hidden="true"></i> General System
                        </button>
                        <button @click="activeTab = 'subscription'"
                            :class="activeTab === 'subscription' ? 'bg-[#EAF5ED] text-[#005B2A] dark:bg-[#1A3324] dark:text-[#52C97E] font-bold' : 'text-[#52705F] dark:text-[#8AA895] hover:bg-[#F7FAF8] dark:hover:bg-[#13231B] font-medium'"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs transition">
                            <i class="fas fa-credit-card w-4 text-center" aria-hidden="true"></i> Subscription License
                        </button>
                        <button @click="activeTab = 'security'"
                            :class="activeTab === 'security' ? 'bg-[#EAF5ED] text-[#005B2A] dark:bg-[#1A3324] dark:text-[#52C97E] font-bold' : 'text-[#52705F] dark:text-[#8AA895] hover:bg-[#F7FAF8] dark:hover:bg-[#13231B] font-medium'"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs transition">
                            <i class="fas fa-shield-halved w-4 text-center" aria-hidden="true"></i> Security & Lockouts
                        </button>
                        <button @click="activeTab = 'maintenance'"
                            :class="activeTab === 'maintenance' ? 'bg-[#EAF5ED] text-[#005B2A] dark:bg-[#1A3324] dark:text-[#52C97E] font-bold' : 'text-[#52705F] dark:text-[#8AA895] hover:bg-[#F7FAF8] dark:hover:bg-[#13231B] font-medium'"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs transition">
                            <i class="fas fa-wrench w-4 text-center" aria-hidden="true"></i> Maintenance Procedures
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="lg:col-span-3 space-y-6">
                <!-- General Settings -->
                <div x-show="activeTab === 'general'" class="kp-card p-6" x-transition>
                    <div class="flex items-center gap-2.5 pb-3 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm">
                            <i class="fas fa-sliders" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">General Configuration</h3>
                            <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Core identity and application metadata.</p>
                        </div>
                    </div>

                    <div class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Organization / System Name</label>
                            <input type="text" x-model="settings.system_name"
                                class="kp-input w-full">
                            <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mt-1">Displayed across printable vouchers, ledger headers, and audit trails.</p>
                        </div>
                        <div>
                            <button type="button" @click="saveGeneralSettings()"
                                class="kp-btn kp-btn-primary">
                                <i class="fas fa-save mr-1.5" aria-hidden="true"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Subscription Settings -->
                <div x-show="activeTab === 'subscription'" class="kp-card p-6" x-transition x-cloak>
                    <div class="flex items-center gap-2.5 pb-3 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm">
                            <i class="fas fa-credit-card" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Subscription License & Billing</h3>
                            <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Manage active tenancy, billing reminders, and automatic system lockdown.</p>
                        </div>
                    </div>

                    <div class="space-y-5 max-w-xl">
                        <div class="flex items-center justify-between p-4 rounded-xl bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B]">
                            <div>
                                <p class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">License Status</p>
                                <p class="text-xs mt-1">
                                    <span x-show="settings.subscription_status === 'active'" class="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active Tenancy — Non-admin access permitted
                                    </span>
                                    <span x-show="settings.subscription_status !== 'active'" class="text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1.5" x-cloak>
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                        Locked Tenancy — Restricted to admin accounts
                                    </span>
                                </p>
                            </div>
                            <button type="button" @click="toggleSubscription()" :disabled="saving"
                                :class="settings.subscription_status === 'active' ? 'bg-[#005B2A]' : 'bg-rose-600'"
                                class="relative w-14 h-7 rounded-full transition-colors duration-200 disabled:opacity-50">
                                <span class="absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-transform duration-200"
                                    :class="settings.subscription_status === 'active' ? 'translate-x-7' : 'translate-x-0'"></span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Monthly Tenancy Fee (₱)</label>
                                <input type="number" x-model="settings.subscription_amount" min="0" step="0.01"
                                    class="kp-input w-full">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Next Renewal Due Date</label>
                                <input type="date" x-model="settings.subscription_due_date"
                                    class="kp-input w-full">
                            </div>
                        </div>

                        <div class="rounded-xl p-4 border"
                            :class="getDaysRemaining() > 30 ? 'bg-[#EAF5ED] border-[#CFDCD3] dark:bg-[#1A3324] dark:border-[#203A2B]' : getDaysRemaining() > 7 ? 'bg-amber-50 border-amber-200 dark:bg-amber-950/30 dark:border-amber-900/40' : 'bg-rose-50 border-rose-200 dark:bg-rose-950/30 dark:border-rose-900/40'">
                            <p class="text-xs font-semibold"
                                :class="getDaysRemaining() > 30 ? 'text-[#005B2A] dark:text-[#52C97E]' : getDaysRemaining() > 7 ? 'text-amber-800 dark:text-amber-300' : 'text-rose-800 dark:text-rose-300'">
                                <i class="fas mr-1.5" :class="getDaysRemaining() > 30 ? 'fa-check-circle' : getDaysRemaining() > 7 ? 'fa-clock' : 'fa-triangle-exclamation'"></i>
                                <span x-text="getDaysRemaining() + ' calendar days remaining before subscription expiry'"></span>
                            </p>
                        </div>

                        <button type="button" @click="saveSubscription()"
                            class="kp-btn kp-btn-primary">
                            <i class="fas fa-save mr-1.5" aria-hidden="true"></i> Save Subscription Parameters
                        </button>
                    </div>
                </div>

                <!-- Security & Lock Settings -->
                <div x-show="activeTab === 'security'" class="space-y-6" x-transition x-cloak>
                    <!-- Emergency Lock -->
                    <div class="kp-card p-6">
                        <div class="flex items-center gap-2.5 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <div class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center text-sm">
                                <i class="fas fa-lock" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Emergency System Lockdown</h3>
                                <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Immediately restrict access to all non-administrative users.</p>
                            </div>
                        </div>

                        <div class="space-y-4 max-w-xl">
                            <div>
                                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Broadcast Reason for Lockout</label>
                                <input type="text" x-model="settings.lock_reason"
                                    class="kp-input w-full"
                                    placeholder="e.g. Off-season system reconciliation or emergency data sync...">
                            </div>

                            <div class="flex items-center justify-between p-3.5 bg-[#F7FAF8] dark:bg-[#13231B] rounded-xl border border-[#E3EBE5] dark:border-[#203A2B]">
                                <span class="text-xs font-medium text-[#52705F] dark:text-[#8AA895]">Lockout Status:</span>
                                <span class="text-xs font-bold" 
                                      :class="settings.system_locked ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                                      x-text="settings.system_locked ? '● SYSTEM CURRENTLY LOCKED' : '● SYSTEM CURRENTLY OPEN'"></span>
                            </div>

                            <button type="button" @click="toggleSystemLock()" :disabled="saving"
                                :class="settings.system_locked ? 'kp-btn-primary' : 'kp-btn-danger'"
                                class="kp-btn w-full justify-center">
                                <i class="fas mr-1.5" :class="settings.system_locked ? 'fa-lock-open' : 'fa-lock'"></i>
                                <span x-text="settings.system_locked ? 'Disengage Lockdown & Restore Access' : 'Engage Immediate Lockdown'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Scheduled Lock -->
                    <div class="kp-card p-6">
                        <div class="flex items-center gap-2.5 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm">
                                <i class="fas fa-calendar-xmark" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Scheduled Planned Downtime</h3>
                                <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Pre-schedule a recurring or date-bound lock window for annual audits.</p>
                            </div>
                        </div>

                        <div class="space-y-4 max-w-xl">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Lock Start Date</label>
                                    <input type="date" x-model="settings.lock_start_date"
                                        class="kp-input w-full">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Lock End Date</label>
                                    <input type="date" x-model="settings.lock_end_date"
                                        class="kp-input w-full">
                                </div>
                            </div>
                            <button type="button" @click="saveScheduledLock()"
                                class="kp-btn kp-btn-secondary">
                                <i class="fas fa-calendar-check mr-1.5" aria-hidden="true"></i> Save Downtime Schedule
                            </button>
                        </div>
                    </div>

                    <!-- Security Access Key Vault -->
                    <div class="kp-card p-6">
                        <div class="flex items-center gap-2.5 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#D9B54A] flex items-center justify-center text-sm">
                                <i class="fas fa-key" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Master Security Access Key</h3>
                                <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Tier 1 credential required to authenticate on the administrative portal.</p>
                            </div>
                        </div>

                        <div x-data="{
                            showKey: false,
                            passcode: '',
                            passcodeError: false,
                            attempts: 0,
                            locked: false,
                            verifyPasscode() {
                                if (this.passcode === 'KUSUG-ADMIN-VERIFY-2024') {
                                    this.showKey = true;
                                    this.passcodeError = false;
                                    this.attempts = 0;
                                } else {
                                    this.passcodeError = true;
                                    this.attempts++;
                                    if (this.attempts >= 3) {
                                        this.locked = true;
                                        setTimeout(() => { this.locked = false; this.attempts = 0; }, 300000);
                                    }
                                }
                            }
                        }" class="max-w-xl">
                            <div x-show="!showKey && !locked" class="space-y-3">
                                <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 rounded-xl p-3.5 text-xs text-amber-800 dark:text-amber-300">
                                    <p class="font-bold uppercase tracking-wider">Protected Verification Required</p>
                                    <p class="mt-0.5 opacity-90">Enter supervisory authorization passcode to view the live security key.</p>
                                </div>
                                <div class="flex gap-2">
                                    <input type="password" x-model="passcode" @keyup.enter="verifyPasscode()"
                                        class="kp-input flex-1"
                                        placeholder="Enter authorization passcode (hint: KUSUG-ADMIN-VERIFY-2024)">
                                    <button type="button" @click="verifyPasscode()"
                                        class="kp-btn kp-btn-primary">
                                        <i class="fas fa-unlock" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <p x-show="passcodeError" class="text-xs text-rose-600 dark:text-rose-400" x-cloak>
                                    Invalid passcode. <span x-text="3 - attempts + ' attempts remaining'"></span>
                                </p>
                            </div>

                            <div x-show="locked" class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/40 rounded-xl p-4 text-xs text-rose-700 dark:text-rose-300" x-cloak>
                                <p class="font-bold">Vault Locked</p>
                                <p class="mt-0.5">Too many failed verification attempts. Key inspection suspended for 5 minutes.</p>
                            </div>

                            <div x-show="showKey" x-transition class="space-y-3" x-cloak>
                                <div class="bg-[#EAF5ED] dark:bg-[#1A3324] border border-[#CFDCD3] dark:border-[#203A2B] rounded-xl p-3 text-xs text-[#005B2A] dark:text-[#52C97E] font-medium">
                                    <i class="fas fa-check-circle mr-1"></i> Authorized to inspect master security key.
                                </div>
                                <div class="relative">
                                    <input type="text" value="{{ config('app.admin_security_key', 'KUSUG-ADMIN-2024') }}" readonly
                                        class="kp-input w-full font-mono pr-12 text-xs">
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ config('app.admin_security_key', 'KUSUG-ADMIN-2024') }}')"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 text-[#52705F] hover:text-[#005B2A] dark:hover:text-[#52C97E]">
                                        <i class="fas fa-copy" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <button type="button" @click="showKey = false; passcode = ''" class="kp-btn kp-btn-secondary text-xs">
                                    <i class="fas fa-eye-slash mr-1" aria-hidden="true"></i> Conceal Key
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Settings -->
                <div x-show="activeTab === 'maintenance'" class="space-y-6" x-transition x-cloak>
                    <div class="kp-card p-6">
                        <div class="flex items-center gap-2.5 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 flex items-center justify-center text-sm">
                                <i class="fas fa-wrench" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">System Maintenance Mode</h3>
                                <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Show the maintenance screen to Super Admin, Manager, and other portal users. Administrator access stays available.</p>
                            </div>
                        </div>

                        <div class="space-y-4 max-w-xl">
                            <div>
                                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Maintenance Notice Message</label>
                                <textarea x-model="settings.maintenance_message" rows="3"
                                    class="kp-input w-full"
                                    placeholder="System is undergoing routine server maintenance. Please check back shortly."></textarea>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-[#F7FAF8] dark:bg-[#13231B] rounded-xl border border-[#E3EBE5] dark:border-[#203A2B]">
                                <span class="text-xs font-medium text-[#52705F] dark:text-[#8AA895]">Current Operational Mode:</span>
                                <span class="text-xs font-bold" 
                                      :class="settings.maintenance_mode ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                                      x-text="settings.maintenance_mode ? '● MAINTENANCE ACTIVE' : '● NORMAL ONLINE STATE'"></span>
                            </div>
                            <button type="button" @click="toggleMaintenance()" :disabled="saving"
                                :class="settings.maintenance_mode ? 'kp-btn-danger' : 'kp-btn-primary'"
                                class="kp-btn w-full justify-center">
                                <i class="fas mr-1.5" :class="settings.maintenance_mode ? 'fa-play' : 'fa-pause'"></i>
                                <span x-text="settings.maintenance_mode ? 'Deactivate Maintenance & Restore Normal Service' : 'Activate Maintenance Mode'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Database & Cache Maintenance -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="kp-card p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-broom text-amber-600 dark:text-amber-400"></i>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-[#172B20] dark:text-[#EAF5ED]">Cache Flush</h4>
                                </div>
                                <p class="text-xs text-[#52705F] dark:text-[#8AA895] mb-4">Purge all compiled blade views, dynamic route manifests, and application configurations.</p>
                            </div>
                            <button type="button" @click="clearSystemCache()"
                                class="kp-btn kp-btn-secondary text-xs w-full justify-center">
                                <i class="fas fa-broom mr-1.5" aria-hidden="true"></i> Flush System Cache
                            </button>
                        </div>

                        <div class="kp-card p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-hard-drive text-blue-600 dark:text-blue-400"></i>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-[#172B20] dark:text-[#EAF5ED]">Database Snapshot</h4>
                                </div>
                                <p class="text-xs text-[#52705F] dark:text-[#8AA895] mb-4">Export a complete SQL dump and archive copy of system records.</p>
                            </div>
                            <button type="button" @click="createSystemBackup()"
                                class="kp-btn kp-btn-secondary text-xs w-full justify-center">
                                <i class="fas fa-cloud-arrow-up mr-1.5" aria-hidden="true"></i> Generate Backup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function adminSettingsData() {
                return {
                    activeTab: 'general',
                    saving: false,
                    settings: {
                        system_name: 'KUSUG-PA',
                        system_locked: false,
                        lock_reason: '',
                        lock_start_date: '',
                        lock_end_date: '',
                        maintenance_mode: false,
                        maintenance_message: '',
                        subscription_status: 'active',
                        subscription_amount: '0.00',
                        subscription_due_date: '',
                    },

                    async loadSettings() {
                        try {
                            const response = await fetch('{{ route("admin.settings.data") }}', {
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                            });
                            const data = await response.json();
                            this.settings.system_locked = data.system_locked === true || data.system_locked === '1' || data.system_locked === 1;
                            this.settings.maintenance_mode = data.maintenance_mode === true || data.maintenance_mode === '1' || data.maintenance_mode === 1;
                            this.settings.subscription_status = data.subscription_status || 'active';
                            this.settings.subscription_amount = data.subscription_amount || '0.00';
                            this.settings.subscription_due_date = data.subscription_due_date || '';
                            this.settings.lock_reason = data.lock_reason || '';
                            this.settings.lock_start_date = data.lock_start_date || '';
                            this.settings.lock_end_date = data.lock_end_date || '';
                            this.settings.maintenance_message = data.maintenance_message || '';
                            this.settings.system_name = data.system_name || 'KUSUG-PA';
                        } catch (error) {
                            console.error('Error loading settings:', error);
                        }
                    },

                    async saveSetting(key, value) {
                        this.saving = true;
                        try {
                            const response = await fetch('{{ route("admin.settings.update") }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                                body: JSON.stringify({ key: key, value: value })
                            });
                            const data = await response.json();
                            Swal.fire({ icon: 'success', title: 'Settings Updated', text: data.message, timer: 2000, showConfirmButton: false });
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to save settings' });
                        } finally {
                            this.saving = false;
                        }
                    },

                    toggleSystemLock() {
                        const newLockState = !this.settings.system_locked;
                        Swal.fire({
                            title: newLockState ? 'Engage Immediate Lockdown?' : 'Disengage System Lockdown?',
                            text: newLockState ? 'All non-admin sessions will be terminated and barred.' : 'Normal access will be restored for all planters and staff.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: newLockState ? 'Yes, engage lock' : 'Yes, restore access',
                            confirmButtonColor: '#005B2A'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.settings.system_locked = newLockState;
                                this.saveSetting('system_locked', newLockState ? '1' : '0');
                                if (newLockState) this.saveSetting('lock_reason', this.settings.lock_reason);
                            }
                        });
                    },

                    toggleSubscription() {
                        const newStatus = this.settings.subscription_status === 'active' ? 'inactive' : 'active';
                        this.settings.subscription_status = newStatus;
                        this.saveSetting('subscription_status', newStatus);
                    },

                    toggleMaintenance() {
                        const newMode = !this.settings.maintenance_mode;
                        this.settings.maintenance_mode = newMode;
                        this.saveSetting('maintenance_mode', newMode ? '1' : '0');
                        if (newMode) this.saveSetting('maintenance_message', this.settings.maintenance_message);
                    },

                    saveSubscription() {
                        this.saving = true;
                        Promise.all([
                            this.saveSetting('subscription_status', this.settings.subscription_status),
                            this.saveSetting('subscription_amount', this.settings.subscription_amount),
                            this.saveSetting('subscription_due_date', this.settings.subscription_due_date)
                        ]).finally(() => { this.saving = false; });
                    },

                    saveScheduledLock() {
                        this.saving = true;
                        Promise.all([
                            this.saveSetting('lock_start_date', this.settings.lock_start_date),
                            this.saveSetting('lock_end_date', this.settings.lock_end_date)
                        ]).finally(() => { this.saving = false; });
                    },

                    saveGeneralSettings() {
                        this.saveSetting('system_name', this.settings.system_name);
                    },

                    getDaysRemaining() {
                        if (!this.settings.subscription_due_date) return 0;
                        const dueDate = new Date(this.settings.subscription_due_date);
                        const today = new Date();
                        return Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
                    },

                    clearSystemCache() {
                        Swal.fire({
                            title: 'Flush Cache?',
                            text: 'Clear all compiled blade views, dynamic routes, and runtime configuration cache.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Flush Cache',
                            confirmButtonColor: '#005B2A'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch('{{ route("admin.cache.clear") }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                                }).then(r => r.json()).then(d => {
                                    Swal.fire({ icon: 'success', title: 'Success', text: d.message, timer: 2000, showConfirmButton: false });
                                });
                            }
                        });
                    },

                    createSystemBackup() {
                        Swal.fire({
                            title: 'Generate Snapshot?',
                            text: 'Create a full SQL archive and asset backup.',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Generate Backup',
                            confirmButtonColor: '#005B2A'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch('{{ route("admin.backup.create") }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                                }).then(r => r.json()).then(d => {
                                    Swal.fire({ icon: 'success', title: 'Backup Complete', text: d.message, timer: 2000, showConfirmButton: false });
                                });
                            }
                        });
                    }
                }
            }
        </script>
    @endpush
@endsection
