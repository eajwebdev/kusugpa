<!-- resources/views/menu/admin-dashboard.blade.php -->
@extends('layouts.main')

@section('title', 'Admin Panel')

@section('content')
<div x-data="adminDashboardData()" class="space-y-6 kp-fade-in">
    <!-- Admin Header -->
    <header>
        <div class="kp-page-header">
            <div>
                <p class="kp-eyebrow">System Administration</p>
                <h1 class="kp-page-title mt-1">Admin Control Panel</h1>
                <p class="kp-page-sub">Supervisory controls, health diagnostics, data retention, and administrative operations.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="kp-badge kp-badge--forest text-xs py-1.5 px-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-1.5"></span>
                    System Core Active
                </span>
                <a href="{{ route('admin.settings') }}" class="kp-btn kp-btn-secondary text-xs">
                    <i class="fas fa-sliders mr-1.5" aria-hidden="true"></i> Settings
                </a>
            </div>
        </div>
        <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
    </header>

    <!-- KPI Metric Cards -->
    <section aria-label="Administrative system overview" class="kp-kpis">
        <div class="kp-kpi">
            <p class="kp-kpi-label"><i class="fas fa-users" aria-hidden="true"></i> Registered Users</p>
            <p class="kp-kpi-value kp-num">{{ $stats['totalUsers'] }}</p>
            <p class="kp-kpi-sub">Active staff accounts</p>
        </div>
        <div class="kp-kpi">
            <p class="kp-kpi-label"><i class="fas fa-seedling" aria-hidden="true"></i> Planter Roster</p>
            <p class="kp-kpi-value kp-num" style="color: var(--kp-emerald)">{{ $stats['totalPlanters'] }}</p>
            <p class="kp-kpi-sub">Registered cane planters</p>
        </div>
        <div class="kp-kpi">
            <p class="kp-kpi-label"><i class="fas fa-database" aria-hidden="true"></i> Total Production Records</p>
            <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">{{ $stats['totalRecords'] }}</p>
            <p class="kp-kpi-sub">Ledger transactions</p>
        </div>
        <div class="kp-kpi">
            <p class="kp-kpi-label"><i class="fas fa-cloud-arrow-up" aria-hidden="true"></i> Disaster Backup</p>
            <p class="kp-kpi-value text-base font-semibold truncate mt-1 text-[#172B20] dark:text-[#EAF5ED]">{{ $stats['latestBackup'] }}</p>
            <p class="kp-kpi-sub">Last backup timestamp</p>
        </div>
    </section>

    <!-- Admin Operations Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <!-- System Settings -->
        <a href="{{ route('admin.settings') }}" class="kp-card p-6 hover:border-[#005B2A] dark:hover:border-[#52C97E] transition-all duration-200 group flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-xl flex-shrink-0 group-hover:bg-[#005B2A] group-hover:text-white transition-colors duration-200">
                <i class="fas fa-sliders" aria-hidden="true"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider group-hover:text-[#005B2A] dark:group-hover:text-[#52C97E] transition-colors">System Settings</h3>
                <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-1 leading-relaxed">Configure system parameters, lockout schedule, and maintenance toggles.</p>
            </div>
        </a>

        <!-- User Management -->
        <a href="{{ route('user-management') }}" class="kp-card p-6 hover:border-[#005B2A] dark:hover:border-[#52C97E] transition-all duration-200 group flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-xl flex-shrink-0 group-hover:bg-[#005B2A] group-hover:text-white transition-colors duration-200">
                <i class="fas fa-users-gear" aria-hidden="true"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider group-hover:text-[#005B2A] dark:group-hover:text-[#52C97E] transition-colors">User Management</h3>
                <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-1 leading-relaxed">Manage system operators, assign permission scopes, and reset credentials.</p>
            </div>
        </a>

        <!-- Activity Logs -->
        <a href="{{ route('audit-logs.index') }}" class="kp-card p-6 hover:border-[#005B2A] dark:hover:border-[#52C97E] transition-all duration-200 group flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-xl flex-shrink-0 group-hover:bg-[#005B2A] group-hover:text-white transition-colors duration-200">
                <i class="fas fa-file-shield" aria-hidden="true"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider group-hover:text-[#005B2A] dark:group-hover:text-[#52C97E] transition-colors">Activity Compliance Logs</h3>
                <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-1 leading-relaxed">Immutable chronological audit trail of all staff logins and record modifications.</p>
            </div>
        </a>

        <!-- Clear Cache -->
        <button type="button" onclick="clearSystemCache()" class="kp-card p-6 hover:border-[#005B2A] dark:hover:border-[#52C97E] transition-all duration-200 group flex items-start gap-4 text-left w-full">
            <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 flex items-center justify-center text-xl flex-shrink-0 group-hover:bg-amber-600 group-hover:text-white transition-colors duration-200">
                <i class="fas fa-broom" aria-hidden="true"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">Flush System Cache</h3>
                <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-1 leading-relaxed">Clear compiled views, configuration states, and route cache for immediate sync.</p>
            </div>
        </button>

        <!-- Create Backup -->
        <button type="button" onclick="createSystemBackup()" class="kp-card p-6 hover:border-[#005B2A] dark:hover:border-[#52C97E] transition-all duration-200 group flex items-start gap-4 text-left w-full">
            <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 flex items-center justify-center text-xl flex-shrink-0 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-200">
                <i class="fas fa-hard-drive" aria-hidden="true"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">Create Snapshot Backup</h3>
                <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-1 leading-relaxed">Trigger an immediate archival snapshot of production databases and files.</p>
            </div>
        </button>

        <!-- Price Registry Shortcut -->
        <a href="{{ route('prices.registry') }}" class="kp-card p-6 hover:border-[#005B2A] dark:hover:border-[#52C97E] transition-all duration-200 group flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-xl flex-shrink-0 group-hover:bg-[#005B2A] group-hover:text-white transition-colors duration-200">
                <i class="fas fa-tags" aria-hidden="true"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider group-hover:text-[#005B2A] dark:group-hover:text-[#52C97E] transition-colors">Quedan & Molasses Registry</h3>
                <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-1 leading-relaxed">Manage sugar quedan procurement prices, liens, and molasses market postings.</p>
            </div>
        </a>
    </div>

    <!-- Quick Shortcuts Toolbar -->
    <div class="kp-card p-5">
        <div class="flex items-center gap-2 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
            <i class="fas fa-bolt text-[#D9B54A]" aria-hidden="true"></i>
            <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Administrative Shortcuts</h3>
        </div>
        <div class="flex flex-wrap gap-2.5">
            <a href="{{ route('crop-weeks.index') }}" class="kp-btn kp-btn-secondary text-xs">
                <i class="fas fa-calendar-plus mr-1.5 text-[#005B2A] dark:text-[#52C97E]" aria-hidden="true"></i> Manage Crop Years & Weeks
            </a>
            <a href="{{ route('prices.index') }}" class="kp-btn kp-btn-secondary text-xs">
                <i class="fas fa-tag mr-1.5 text-blue-600 dark:text-blue-400" aria-hidden="true"></i> Update Market Prices
            </a>
            <a href="{{ route('loans.index') }}" class="kp-btn kp-btn-secondary text-xs">
                <i class="fas fa-hand-holding-dollar mr-1.5 text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i> Loan Portfolio
            </a>
            <a href="{{ route('summaryReport') }}" class="kp-btn kp-btn-secondary text-xs">
                <i class="fas fa-chart-simple mr-1.5 text-[#D9B54A]" aria-hidden="true"></i> Production Reports
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function adminDashboardData() {
        return {};
    }

    function clearSystemCache() {
        Swal.fire({
            title: 'Clear System Cache?',
            text: 'This will flush all application cache including compiled views, route tables, and configuration states.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, flush cache',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#005B2A'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ route("admin.cache.clear") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cache Flushed',
                        text: data.message || 'System cache cleared successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Operation Failed',
                        text: 'Failed to flush system cache.'
                    });
                });
            }
        });
    }

    function createSystemBackup() {
        Swal.fire({
            title: 'Generate System Snapshot?',
            text: 'This creates an immediate backup archive of the database and runtime assets.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Create Backup',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#005B2A'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Creating snapshot...',
                    text: 'Archiving production database, please wait.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('{{ route("admin.backup.create") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Backup Successful',
                        text: data.message || 'Backup archive created successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Backup Failed',
                        text: 'Could not create system backup.'
                    });
                });
            }
        });
    }
</script>
@endpush
@endsection