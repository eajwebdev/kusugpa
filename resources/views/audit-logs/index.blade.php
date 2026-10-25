<!-- resources/views/audit-logs/index.blade.php -->
@extends('layouts.main')

@section('title', 'Audit Logs')

@section('content')
<div x-data="auditLogData()" class="space-y-6 kp-fade-in">
    <!-- Page Header -->
    <header>
        <div class="kp-page-header">
            <div>
                <p class="kp-eyebrow">Activity & Compliance</p>
                <h1 class="kp-page-title mt-1">Audit Logs</h1>
                <p class="kp-page-sub">{{ auth()->user()->role === 'Administrator' ? 'Activity across all accounts and system events.' : (in_array(auth()->user()->role, ['super_admin', 'manager'], true) ? 'Your activity and activity from roles below yours.' : 'Your account activity.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="exportPDF()" class="kp-btn kp-btn-secondary">
                    <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Preview PDF
                </button>
            </div>
        </div>
        <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
    </header>

    <!-- Stats Strip -->
    <section aria-label="Audit statistics" class="kp-kpis">
        <div class="kp-kpi">
            <p class="kp-kpi-label"><i class="fas fa-database" aria-hidden="true"></i> Total Logs</p>
            <p class="kp-kpi-value kp-num" x-text="stats.total"></p>
            <p class="kp-kpi-sub">Lifetime activity entries</p>
        </div>
        <div class="kp-kpi">
            <p class="kp-kpi-label"><i class="fas fa-calendar-day" aria-hidden="true"></i> Today</p>
            <p class="kp-kpi-value kp-num" style="color: var(--kp-emerald)" x-text="stats.today"></p>
            <p class="kp-kpi-sub">Events logged today</p>
        </div>
        <div class="kp-kpi">
            <p class="kp-kpi-label"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Logins</p>
            <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)" x-text="stats.logins"></p>
            <p class="kp-kpi-sub">Authentication sessions</p>
        </div>
        <div class="kp-kpi">
            <p class="kp-kpi-label"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> System Errors</p>
            <p class="kp-kpi-value kp-num" style="color: #A32F2F" x-text="stats.errors"></p>
            <p class="kp-kpi-sub">Failed operations / exceptions</p>
        </div>
    </section>

    <!-- Filters Card -->
    <div class="kp-card p-4">
        <div class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search Logs</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                    <input type="text" x-model="search" @input="applyFilters()" placeholder="Search description, user, or IP..."
                        class="kp-input w-full pl-9">
                </div>
            </div>
            <div class="w-40">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Module</label>
                <select x-model="moduleFilter" @change="applyFilters()" class="kp-select w-full">
                    <option value="all">All Modules</option>
                    <template x-for="mod in modules" :key="mod">
                        <option :value="mod" x-text="mod.charAt(0).toUpperCase() + mod.slice(1)"></option>
                    </template>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Action</label>
                <select x-model="actionFilter" @change="applyFilters()" class="kp-select w-full">
                    <option value="all">All Actions</option>
                    <template x-for="act in actions" :key="act">
                        <option :value="act" x-text="act.charAt(0).toUpperCase() + act.slice(1)"></option>
                    </template>
                </select>
            </div>
            {{-- Crop year + week fill From / To with the week's dates; the dates can still be set by hand --}}
            <div class="w-36">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Crop Year</label>
                <select x-model="cropYear" @change="weekNo = ''" class="kp-select w-full">
                    <option value="">Any</option>
                    <template x-for="cy in cropYears" :key="cy">
                        <option :value="cy" x-text="cy"></option>
                    </template>
                </select>
            </div>
            <div class="w-32">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Week</label>
                <select x-model="weekNo" @change="applyWeek()" :disabled="!cropYear" class="kp-select w-full disabled:opacity-50">
                    <option value="" x-text="cropYear ? 'Select' : 'Crop year first'"></option>
                    <template x-for="w in weeks.filter(w => w.crop_year === cropYear)" :key="w.week_no">
                        <option :value="w.week_no" x-text="'Week ' + w.week_no"></option>
                    </template>
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">From</label>
                <input type="date" x-model="dateFrom" @change="weekNo = ''; loadFilteredLogs()" class="kp-input w-full">
            </div>
            <div class="w-36">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">To</label>
                <input type="date" x-model="dateTo" @change="weekNo = ''; loadFilteredLogs()" class="kp-input w-full">
            </div>
            <button @click="clearFilters()" class="kp-btn kp-btn-secondary h-[38px]">
                <i class="fas fa-times mr-1"></i> Reset
            </button>
            <span class="text-xs font-mono ml-auto py-2" style="color: var(--kp-muted)"
                x-text="feed && feed.total !== null ? 'Showing ' + filteredLogs.length.toLocaleString() + ' of ' + totalRecords.toLocaleString() + ' logs' : 'Loading…'"></span>
        </div>
    </div>

    <!-- Logs Table Card -->
    <div class="kp-card overflow-hidden">
        <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
            <table class="kp-table">
                <thead>
                    <tr>
                        <th class="cursor-pointer select-none" @click="sortBy('created_at')">
                            <div class="flex items-center gap-1.5">
                                <span>Timestamp</span>
                                <i class="fas text-[10px]" :class="sortField === 'created_at' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                            </div>
                        </th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th class="font-mono">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="log in filteredLogs" :key="log.id">
                        <tr class="hover:bg-[var(--kp-hover)] transition-colors cursor-pointer" @click="showDetails(log)">
                            <td class="font-mono text-xs tabular" style="color: var(--kp-muted)" x-text="formatDateTime(log.created_at)"></td>
                            <td>
                                <p class="font-medium text-sm" style="color: var(--kp-text)" x-text="log.user_name || 'System'"></p>
                                <p class="text-xs font-mono" style="color: var(--kp-muted)" x-text="log.user_role || '—'"></p>
                            </td>
                            <td>
                                <span class="kp-badge" :class="getActionBadgeClass(log.action)" x-text="log.action.charAt(0).toUpperCase() + log.action.slice(1)"></span>
                            </td>
                            <td>
                                <span class="px-2 py-0.5 text-xs font-medium rounded border"
                                    style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-text)"
                                    x-text="log.module.charAt(0).toUpperCase() + log.module.slice(1)"></span>
                            </td>
                            <td style="color: var(--kp-text)" x-text="truncate(log.description, 80)"></td>
                            <td class="font-mono text-xs" style="color: var(--kp-muted)" x-text="log.ip_address || '—'"></td>
                        </tr>
                    </template>
                    <template x-if="feed">
                        @include('layouts.partials.feed-footer', ['feed' => 'feed', 'colspan' => 6, 'empty' => 'No audit log records match the current filter criteria.'])
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Log Detail Modal -->
    <div x-show="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showDetailModal = false"></div>
        <div class="relative rounded-xl border max-w-lg w-full p-6 shadow-2xl max-h-[85vh] overflow-y-auto"
            style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
            <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                <h3 class="font-display text-lg font-semibold" style="color: var(--kp-text)">Audit Log Details</h3>
                <button @click="showDetailModal = false" class="p-1 rounded text-xs hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <dl class="space-y-3 text-xs">
                <div class="flex justify-between py-1 border-b" style="border-color: var(--kp-border)">
                    <dt class="font-medium" style="color: var(--kp-faint)">User</dt>
                    <dd class="font-semibold" style="color: var(--kp-text)" x-text="logDetail.user_name || 'System'"></dd>
                </div>
                <div class="flex justify-between py-1 border-b" style="border-color: var(--kp-border)">
                    <dt class="font-medium" style="color: var(--kp-faint)">Role</dt>
                    <dd class="font-mono" style="color: var(--kp-text)" x-text="logDetail.user_role || '—'"></dd>
                </div>
                <div class="flex justify-between py-1 border-b" style="border-color: var(--kp-border)">
                    <dt class="font-medium" style="color: var(--kp-faint)">Action</dt>
                    <dd class="font-medium" style="color: var(--kp-forest)" x-text="logDetail.action"></dd>
                </div>
                <div class="flex justify-between py-1 border-b" style="border-color: var(--kp-border)">
                    <dt class="font-medium" style="color: var(--kp-faint)">Module</dt>
                    <dd class="font-medium" style="color: var(--kp-text)" x-text="logDetail.module"></dd>
                </div>
                <div class="flex justify-between py-1 border-b" style="border-color: var(--kp-border)">
                    <dt class="font-medium" style="color: var(--kp-faint)">Description</dt>
                    <dd class="font-medium text-right max-w-xs" style="color: var(--kp-text)" x-text="logDetail.description"></dd>
                </div>
                <div class="flex justify-between py-1 border-b" style="border-color: var(--kp-border)">
                    <dt class="font-medium" style="color: var(--kp-faint)">IP Address</dt>
                    <dd class="font-mono" style="color: var(--kp-text)" x-text="logDetail.ip_address || '—'"></dd>
                </div>
                <div class="flex justify-between py-1 border-b" style="border-color: var(--kp-border)">
                    <dt class="font-medium" style="color: var(--kp-faint)">Timestamp</dt>
                    <dd class="font-mono tabular" style="color: var(--kp-text)" x-text="formatDateTime(logDetail.created_at)"></dd>
                </div>
                <div x-show="logDetail.details" class="pt-2">
                    <dt class="font-medium mb-1" style="color: var(--kp-faint)">Raw Payload Details</dt>
                    <dd>
                        <pre class="p-3 rounded-lg text-xs font-mono overflow-x-auto border"
                            style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-text)"
                            x-text="JSON.stringify(logDetail.details, null, 2)"></pre>
                    </dd>
                </div>
            </dl>
            <div class="pt-4 mt-4 border-t flex justify-end" style="border-color: var(--kp-border)">
                <button type="button" @click="showDetailModal = false" class="kp-btn kp-btn-secondary">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function auditLogData() {
        return {
            // Logs come from the server in batches of 100 as the table scrolls (kpFeed); every filter runs in the database
            feed: null,
            searchTimer: null,
            modules: @json($modules),
            actions: @json($actions),
            stats: @json($stats),
            search: '',
            moduleFilter: 'all',
            actionFilter: 'all',
            dateFrom: '',
            dateTo: '',
            weeks: @json($weeks),
            cropYear: '',
            weekNo: '',
            get cropYears() {
                return [...new Set(this.weeks.map(w => w.crop_year))].sort().reverse();
            },
            // A crop year week sets From / To to its start and end dates
            applyWeek() {
                const w = this.weeks.find(w => w.crop_year === this.cropYear && w.week_no === this.weekNo);
                if (!w) return;
                this.dateFrom = w.start;
                this.dateTo = w.end;
                this.loadFilteredLogs();
            },
            sortField: 'created_at',
            sortDirection: 'desc',
            showDetailModal: false,
            logDetail: {},

            init() {
                this.feed = kpFeed('{{ route('audit-logs.load-more') }}', () => ({
                    search: this.search.trim(),
                    module: this.moduleFilter, action: this.actionFilter,
                    date_from: this.dateFrom, date_to: this.dateTo,
                    sort: this.sortField, dir: this.sortDirection,
                }));
                this.feed.reset();
            },

            get filteredLogs() { return this.feed ? this.feed.rows : []; },
            get loading() { return this.feed ? this.feed.loading : false; },
            get totalRecords() { return this.feed && this.feed.total !== null ? this.feed.total : 0; },

            // Search waits for typing to pause; the dropdowns reload at once
            applyFilters() {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.feed.reset(), 350);
            },

            loadFilteredLogs() {
                return this.feed.reset();
            },

            sortBy(field) {
                this.sortDirection = this.sortField === field && this.sortDirection === 'desc' ? 'asc' : 'desc';
                this.sortField = field;
                this.feed.reset();
            },

            clearFilters() {
                this.search = '';
                this.moduleFilter = 'all';
                this.actionFilter = 'all';
                this.dateFrom = '';
                this.dateTo = '';
                this.cropYear = '';
                this.weekNo = '';
                this.loadFilteredLogs();
            },

            showDetails(log) {
                this.logDetail = log;
                this.showDetailModal = true;
            },

            exportPDF() {
                const params = new URLSearchParams();
                if (this.dateFrom) params.append('date_from', this.dateFrom);
                if (this.dateTo) params.append('date_to', this.dateTo);
                if (this.moduleFilter !== 'all') params.append('module', this.moduleFilter);
                window.KusugpaPdfPreview.open('/audit-logs/export/pdf?' + params.toString(), 'Audit Logs');
            },

            getActionBadgeClass(action) {
                const classes = {
                    'login': 'kp-badge-info',
                    'logout': 'kp-badge-neutral',
                    'create': 'kp-badge-emerald',
                    'upload': 'kp-badge-emerald',
                    'update': 'kp-badge-amber',
                    'delete': 'kp-badge-rose',
                    'approve': 'kp-badge-emerald',
                    'reject': 'kp-badge-rose',
                    'payment': 'kp-badge-forest',
                    'error': 'kp-badge-rose',
                    'activate': 'kp-badge-emerald',
                    'deactivate': 'kp-badge-amber',
                    'view': 'kp-badge-info',
                    'print': 'kp-badge-info',
                    'download': 'kp-badge-info',
                    'preview': 'kp-badge-info',
                    'maintenance': 'kp-badge-amber',
                    'lock': 'kp-badge-rose',
                    'unlock': 'kp-badge-emerald',
                    'enable': 'kp-badge-emerald',
                    'disable': 'kp-badge-rose',
                    'failed_login': 'kp-badge-rose',
                };
                return classes[action] || 'kp-badge-neutral';
            },

            formatDateTime(date) {
                if (!date) return '';
                const d = new Date(date);
                return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) + ' ' +
                       d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
            },

            truncate(str, len) {
                if (!str) return '';
                return str.length > len ? str.substring(0, len) + '...' : str;
            }
        }
    }
</script>
@endpush
@endsection
