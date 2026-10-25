<!-- resources/views/cash-advances/index.blade.php -->
@extends('layouts.main')

@section('title', 'Cash Advances Management')

@section('content')
    <div x-data="cashAdvancesData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Financial Services</p>
                    <h1 class="kp-page-title mt-1">Cash Advances</h1>
                    <p class="kp-page-sub">Manage planter cash advance requests, active balances, and weekly deductions.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if (auth()->user()->role === 'Administrator' ||
                            auth()->user()->role === 'super_admin' ||
                            auth()->user()->role === 'manager' ||
                            auth()->user()->hasPermission('create-cash-advances'))
                        <button @click="$dispatch('open-ca-modal')" class="kp-btn kp-btn-primary">
                            <i class="fas fa-plus-circle mr-1.5" aria-hidden="true"></i> New Cash Advance
                        </button>
                    @endif
                    @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
                            auth()->user()->hasPermission('manage-cash-advance-settings'))
                        <a href="{{ route('cash-advances.settings') }}" class="kp-btn kp-btn-secondary">
                            <i class="fas fa-cog mr-1.5" aria-hidden="true"></i> Settings
                        </a>
                    @endif
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Stats Strip -->
        <section aria-label="Cash advance portfolio metrics" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-check-circle" aria-hidden="true"></i> Active Advances</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-emerald)">{{ $stats['total_active'] }}</p>
                <p class="kp-kpi-sub">Currently servicing</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-clock" aria-hidden="true"></i> Pending Approval</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-cane)">{{ $stats['total_pending'] }}</p>
                <p class="kp-kpi-sub">Awaiting management</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Total Disbursed</p>
                <p class="kp-kpi-value kp-num">₱{{ number_format($stats['total_amount'], 0) }}</p>
                <p class="kp-kpi-sub">Cumulative principal</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-coins" aria-hidden="true"></i> Total Collected</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">₱{{ number_format($stats['total_collected'], 0) }}</p>
                <p class="kp-kpi-sub">Recovered amount</p>
            </div>
        </section>

        <!-- Search & Filter Bar -->
        <div class="kp-card p-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search Advances</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                        <input type="text" x-model="search" @input="filterCA()"
                            placeholder="Search by planter name, code, or CA number..."
                            class="kp-input w-full pl-9">
                    </div>
                </div>
                <div class="w-44">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Status</label>
                    <select x-model="statusFilter" @change="filterCA()" class="kp-select w-full">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="w-44">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Crop Year</label>
                    <select x-model="cropYearFilter" @change="weekFilter = ''; filterCA()" class="kp-select w-full">
                        <option value="">All Crop Years</option>
                        @foreach ($cropYears as $cy)
                            <option value="{{ $cy }}">{{ $cy }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-36">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Deduction Week</label>
                    <select x-model="weekFilter" @change="filterCA()" :disabled="!cropYearFilter" class="kp-select w-full disabled:opacity-50">
                        <option value="" x-text="cropYearFilter ? 'All Weeks' : 'Crop year first'"></option>
                        <template x-for="w in weeks.filter(w => String(w.crop_year) === String(cropYearFilter))" :key="w.week_no">
                            <option :value="w.week_no" x-text="'Week ' + w.week_no"></option>
                        </template>
                    </select>
                </div>
                <button @click="clearFilters()" class="kp-btn kp-btn-secondary h-[38px]">
                    <i class="fas fa-times mr-1"></i> Reset
                </button>
                <span class="text-xs font-mono ml-auto py-2" style="color: var(--kp-muted)"
                    x-text="feed && feed.total !== null ? 'Showing ' + filteredCA.length.toLocaleString() + ' of ' + feed.total.toLocaleString() + ' records' : 'Loading…'"></span>
            </div>
        </div>

        <!-- Table Container -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table">
                    <thead>
                        <tr>
                            <th class="cursor-pointer select-none" @click="sortBy('ca_number')">
                                <div class="flex items-center gap-1.5">
                                    <span>CA #</span>
                                    <i class="fas text-[10px]" :class="sortField === 'ca_number' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_name')">
                                <div class="flex items-center gap-1.5">
                                    <span>Planter</span>
                                    <i class="fas text-[10px]" :class="sortField === 'planter_name' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="text-right cursor-pointer select-none" @click="sortBy('amount')">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Amount</span>
                                    <i class="fas text-[10px]" :class="sortField === 'amount' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="text-right">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="cursor-pointer select-none" @click="sortBy('application_date')">
                                <div class="flex items-center gap-1.5">
                                    <span>Date</span>
                                    <i class="fas text-[10px]" :class="sortField === 'application_date' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th>Deduction Weeks</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="ca in filteredCA" :key="ca.id">
                            <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                <td class="font-mono font-semibold" style="color: var(--kp-text)">
                                    <a :href="'/cash-advances/' + ca.id" class="hover:underline" style="color: var(--kp-emerald)" x-text="ca.ca_number"></a>
                                </td>
                                <td>
                                    <p class="font-medium" style="color: var(--kp-text)" x-text="ca.planter_name"></p>
                                    <p class="text-xs font-mono" style="color: var(--kp-muted)" x-text="ca.planter_code"></p>
                                </td>
                                <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-text)"
                                    x-text="'₱' + formatNumber(ca.amount)"></td>
                                <td class="text-right font-mono font-semibold tabular"
                                    :style="ca.balance > 0 ? 'color: #A32F2F' : 'color: var(--kp-emerald)'"
                                    x-text="'₱' + formatNumber(ca.balance)"></td>
                                <td class="text-center">
                                    <span :class="statusClass(ca.status)" x-text="ca.status.charAt(0).toUpperCase() + ca.status.slice(1)"></span>
                                </td>
                                <td class="text-xs font-mono" style="color: var(--kp-muted)" x-text="formatDate(ca.application_date)"></td>
                                <td class="text-xs" style="color: var(--kp-text)">
                                    <template x-for="d in (ca.deductions || [])" :key="d.id">
                                        <div class="whitespace-nowrap"><span class="font-semibold" x-text="'Wk ' + d.week_no"></span> = <span class="font-mono" x-text="formatNumber(d.amount)"></span></div>
                                    </template>
                                    <span x-show="!(ca.deductions || []).length" style="color: var(--kp-faint)">—</span>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <a :href="'/cash-advances/' + ca.id"
                                            class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs inline-flex items-center gap-1">
                                            <i class="fas fa-eye text-[11px]"></i> View
                                        </a>
                                        <button x-show="ca.status !== 'rejected'" @click="previewReceipt(ca)"
                                            class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold"
                                            style="color: var(--kp-forest)" title="Print CA Receipt">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                        @if ($userPermissions['canEditReceipt'])
                                            <button x-show="ca.status !== 'rejected'" @click="openReceiptDetails(ca)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold"
                                                style="color: var(--kp-cane)" title="CV / Check No. and deduction weeks">
                                                <i class="fas fa-file-signature"></i>
                                            </button>
                                        @endif
                                        @if ($userPermissions['canCreate'])
                                            <button x-show="['pending', 'rejected'].includes(ca.status)" @click="deleteCA(ca)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold text-red-500"
                                                title="Delete cash advance">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="feed">
                            @include('layouts.partials.feed-footer', ['feed' => 'feed', 'colspan' => 8, 'empty' => 'No cash advance records match the selected filters.'])
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        @include('modal.cashAdvanceModal')

        @if ($userPermissions['canEditReceipt'])
            <!-- CA Receipt details: CV / Check No. and deduction weeks -->
            <div x-show="receiptModal" @keydown.escape.window="receiptModal = false"
                class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="receiptModal = false"></div>
                <div class="relative rounded-xl border max-w-xl w-full p-6 shadow-2xl max-h-[92vh] overflow-y-auto"
                    style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                    <div class="flex items-start justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                        <div>
                            <h3 class="font-display text-lg font-semibold">CA Receipt Details</h3>
                            <p class="text-xs" style="color: var(--kp-muted)"
                                x-text="receiptCA ? receiptCA.ca_number + ' · ' + receiptCA.planter_name + ' · ₱' + formatNumber(receiptCA.amount) : ''"></p>
                        </div>
                        <button type="button" @click="receiptModal = false" aria-label="Close receipt details" class="text-xs p-2 rounded hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                            <i class="fas fa-times text-sm" aria-hidden="true"></i>
                        </button>
                    </div>

                    <form @submit.prevent="saveReceiptDetails()" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">CV No.</label>
                                <input type="text" x-model="receiptForm.cv_no" placeholder="e.g. 00016625" class="kp-input w-full">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Check No.</label>
                                <input type="text" x-model="receiptForm.check_no" class="kp-input w-full">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Cash Advance Interest (%)</label>
                                <input type="number" x-model="receiptForm.interest_rate" step="0.01" min="0" max="100" required
                                    class="kp-input w-full text-right" aria-describedby="receipt-interest-summary">
                            </div>
                        </div>
                        <p id="receipt-interest-summary" class="text-xs -mt-2" style="color: var(--kp-muted)">
                            Interest <span class="font-mono font-semibold" x-text="'₱' + formatNumber(receiptInterest)"></span>
                            · Total to repay <span class="font-mono font-semibold" style="color: var(--kp-text)" x-text="'₱' + formatNumber(receiptRepayable)"></span>
                        </p>

                        <div class="rounded-lg border overflow-hidden" style="border-color: var(--kp-border)">
                            <div class="flex items-center justify-between px-4 py-2" style="background: var(--kp-surface-2)">
                                <div>
                                    <p class="text-sm font-bold">Deduction Weeks</p>
                                    <p class="text-[11px]" style="color: var(--kp-muted)">Filled into Hauler TLS LIQ's "Cash Adv." for this planter and week.</p>
                                </div>
                                <button type="button" @click="receiptForm.deductions.push({ week_no: '', amount: '' })" class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs">
                                    <i class="fas fa-plus mr-1"></i> Add Week
                                </button>
                            </div>
                            <div class="px-4 py-3 space-y-2">
                                <div class="grid grid-cols-[1fr_9rem_2rem] gap-2 text-xs font-semibold" style="color: var(--kp-muted)">
                                    <span x-text="'Week (crop year ' + (receiptCA ? receiptCA.crop_year : '') + ')'"></span><span class="text-right pr-1">Amount</span><span></span>
                                </div>
                                <template x-for="(row, i) in receiptForm.deductions" :key="i">
                                    <div class="grid grid-cols-[1fr_9rem_2rem] gap-2 items-center">
                                        <select x-model="row.week_no" required class="kp-select w-full"
                                            :class="receiptDuplicateWeeks.includes(row.week_no) ? 'border-red-500 text-red-500' : ''">
                                            <option value="">Select week…</option>
                                            <template x-for="w in receiptWeeks" :key="w.week_no">
                                                <option :value="w.week_no" x-text="'Week ' + w.week_no" :selected="w.week_no === row.week_no"></option>
                                            </template>
                                        </select>
                                        <input type="number" x-model="row.amount" step="any" min="0.01" required class="kp-input w-full text-right" placeholder="0.00">
                                        <button type="button" @click="receiptForm.deductions.splice(i, 1)" class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-red-500" title="Remove week">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </template>
                                <p x-show="receiptForm.deductions.length === 0" class="text-xs py-1" style="color: var(--kp-muted)">No deduction weeks yet.</p>
                                <p x-show="receiptCA && receiptWeeks.length === 0" class="text-xs text-red-500">
                                    No weeks are set up for this crop year in Crop &amp; Week Management.
                                </p>
                            </div>
                            <div class="flex justify-between px-4 py-2 border-t text-xs" style="border-color: var(--kp-border)">
                                <span style="color: var(--kp-muted)">Scheduled</span>
                                <span class="font-mono font-semibold" :class="receiptBalanced ? 'text-[#005B2A] dark:text-[#52C97E]' : 'text-red-500'"
                                    x-text="formatNumber(scheduledTotal) + ' of ' + formatNumber(receiptRepayable)"></span>
                            </div>
                            <p x-show="receiptDuplicateWeeks.length" class="px-4 pb-2 text-xs text-red-500">
                                <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i>
                                Week <span x-text="receiptDuplicateWeeks.join(', ')"></span> is listed more than once. Each week can only be used once.
                            </p>
                            <p x-show="!receiptBalanced" class="px-4 pb-2 text-xs text-red-500">
                                <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i>
                                Deduction weeks are <span x-text="scheduledTotal < receiptRepayable ? 'short' : 'over'"></span>
                                by ₱<span x-text="formatNumber(Math.abs(receiptRepayable - scheduledTotal))"></span>.
                                They must equal the amount to repay (advance + interest) before you can save.
                            </p>
                        </div>

                        <div class="rounded-lg border overflow-hidden" style="border-color: var(--kp-border)">
                            <div class="px-4 py-2" style="background: var(--kp-surface-2)">
                                <p class="text-sm font-bold">Signatories</p>
                                <p class="text-[11px]" style="color: var(--kp-muted)">Names printed on this receipt. Blank uses the default from Cash Advance Settings, or prints an empty line.</p>
                            </div>
                            <div class="px-4 py-3 grid grid-cols-2 gap-3">
                                <template x-for="slot in signatorySlots" :key="slot.key">
                                    <div>
                                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)" x-text="slot.label"></label>
                                        <input type="text" x-model="receiptForm.signatories[slot.key]" maxlength="100" class="kp-input w-full"
                                            :placeholder="signatoryDefaults[slot.key] || 'Blank line'">
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex gap-2 pt-3 border-t" style="border-color: var(--kp-border)">
                            <button type="button" @click="receiptModal = false" class="kp-btn kp-btn-secondary flex-1">Cancel</button>
                            <button type="submit" :disabled="receiptSaving || !receiptCanSave" :title="receiptCanSave ? '' : receiptBlockReason" class="kp-btn kp-btn-primary flex-1 disabled:opacity-50">
                                <i class="fas fa-save mr-1.5"></i> <span x-text="receiptSaving ? 'Saving…' : 'Save Details'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            function cashAdvancesData() {
                return {
                    search: '',
                    statusFilter: 'all',
                    cropYearFilter: '',
                    weekFilter: '', // advances with a deduction in this week of the crop year
                    sortField: 'application_date',
                    sortDirection: 'desc',
                    // Cash advances come from the server in batches of 100 as the table scrolls (kpFeed);
                    // search, filters and sorting run in the database
                    feed: null,
                    searchTimer: null,
                    showModal: false,
                    weeks: @json($weeks),
                    receiptModal: false,
                    receiptSaving: false,
                    receiptCA: null,
                    receiptForm: { interest_rate: '', cv_no: '', check_no: '', deductions: [], signatories: {} },
                    signatoryDefaults: @json($signatoryDefaults),
                    signatorySlots: [
                        { key: 'committee', label: 'Approved Loan Committee (By)' },
                        { key: 'co_maker', label: 'Co Maker' },
                        { key: 'prepared', label: 'Prepared By' },
                        { key: 'approved', label: 'Approved By' },
                        { key: 'received', label: 'Received By' },
                    ],

                    init() {
                        this.feed = kpFeed('{{ route('cash-advances.index') }}', () => ({
                            search: this.search.trim(),
                            status: this.statusFilter,
                            crop_year: this.cropYearFilter,
                            week_no: this.weekFilter,
                            sort: this.sortField, dir: this.sortDirection,
                        }));
                        this.feed.reset();
                    },

                    get filteredCA() {
                        return this.feed ? this.feed.rows : [];
                    },
                    // Reload from the first batch; typing in the search waits for a pause
                    filterCA() {
                        clearTimeout(this.searchTimer);
                        this.searchTimer = setTimeout(() => this.feed.reset(), 300);
                    },
                    sortBy(field) {
                        this.sortDirection = this.sortField === field && this.sortDirection === 'asc' ? 'desc' : 'asc';
                        this.sortField = field;
                        this.feed.reset();
                    },
                    clearFilters() {
                        this.search = '';
                        this.statusFilter = 'all';
                        this.cropYearFilter = '';
                        this.weekFilter = '';
                        this.filterCA();
                    },
                    statusClass(status) {
                        const classes = {
                            active: 'kp-badge kp-badge-emerald',
                            pending: 'kp-badge kp-badge-amber',
                            approved: 'kp-badge kp-badge-info',
                            completed: 'kp-badge kp-badge-neutral',
                            rejected: 'kp-badge kp-badge-rose',
                            cancelled: 'kp-badge kp-badge-rose'
                        };
                        return classes[status] || 'kp-badge kp-badge-neutral';
                    },
                    formatNumber(num) {
                        return parseFloat(num || 0).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    },
                    formatDate(date) {
                        if (!date) return '';
                        return new Date(date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: '2-digit',
                            year: 'numeric'
                        });
                    },

                    // ---------- CA Receipt (Cash Advance Receipt and Sugar Assignment) ----------
                    get receiptWeeks() {
                        return this.receiptCA ? this.weeks.filter(w => w.crop_year === this.receiptCA.crop_year) : [];
                    },
                    // Totals in centavos so 6,666.66 + 6,666.66 + 6,666.68 is exactly 20,000.00
                    get scheduledTotal() {
                        return this.receiptForm.deductions.reduce((t, d) => t + Math.round((parseFloat(d.amount) || 0) * 100), 0) / 100;
                    },
                    get receiptDuplicateWeeks() {
                        const seen = {};
                        this.receiptForm.deductions.forEach(d => { if (d.week_no) seen[d.week_no] = (seen[d.week_no] || 0) + 1; });
                        return Object.keys(seen).filter(w => seen[w] > 1);
                    },
                    get receiptInterest() {
                        if (!this.receiptCA) return 0;
                        return Math.round((parseFloat(this.receiptCA.amount) || 0) * (parseFloat(this.receiptForm.interest_rate) || 0)) / 100;
                    },
                    // What the deduction weeks must add up to: the advance plus the edited interest
                    get receiptRepayable() {
                        if (!this.receiptCA) return 0;
                        return Math.round(((parseFloat(this.receiptCA.amount) || 0) + this.receiptInterest) * 100) / 100;
                    },
                    // Saving needs distinct weeks that add up to the amount to repay
                    get receiptCanSave() {
                        return !this.receiptDuplicateWeeks.length && this.receiptBalanced;
                    },
                    get receiptBlockReason() {
                        if (this.receiptDuplicateWeeks.length) return 'Week ' + this.receiptDuplicateWeeks.join(', ') + ' is listed more than once.';
                        return 'Deduction weeks must equal the amount to repay (₱' + this.formatNumber(this.receiptRepayable) + ').';
                    },
                    get receiptBalanced() {
                        if (!this.receiptCA) return false;
                        return Math.round(this.scheduledTotal * 100) === Math.round(this.receiptRepayable * 100);
                    },
                    previewReceipt(ca) {
                        window.KusugpaPdfPreview.open('/cash-advances/' + ca.id + '/receipt-pdf', 'CA Receipt · ' + ca.ca_number + ' · ' + ca.planter_name);
                    },
                    openReceiptDetails(ca) {
                        this.receiptCA = ca;
                        this.receiptForm = {
                            interest_rate: ca.interest_rate ?? 0,
                            cv_no: ca.cv_no || '',
                            check_no: ca.check_no || '',
                            deductions: (ca.deductions || []).map(d => ({ week_no: String(d.week_no), amount: d.amount })),
                            signatories: { ...(ca.receipt_signatories || {}) },
                        };
                        this.receiptModal = true;
                    },
                    async saveReceiptDetails() {
                        if (!this.receiptCanSave) {
                            Swal.fire({ icon: 'warning', title: 'Cannot save yet', text: this.receiptBlockReason });
                            return;
                        }
                        this.receiptSaving = true;
                        try {
                            const r = await fetch('/cash-advances/' + this.receiptCA.id + '/receipt-details', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(this.receiptForm)
                            });
                            const d = await r.json();
                            if (!r.ok) throw new Error(d.message || 'Request failed');
                            Object.assign(this.receiptCA, d.cash_advance);
                            this.receiptModal = false;
                            const res = await Swal.fire({
                                icon: 'success', title: 'Saved!', text: d.message,
                                showCancelButton: true,
                                confirmButtonText: '<i class="fas fa-file-pdf"></i> Print Receipt',
                                cancelButtonText: 'Close'
                            });
                            if (res.isConfirmed) this.previewReceipt(this.receiptCA);
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        } finally {
                            this.receiptSaving = false;
                        }
                    },

                    // Only pending or rejected cash advances can be deleted
                    async deleteCA(ca) {
                        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
                        const res = await Swal.fire({
                            icon: 'warning',
                            title: 'Delete cash advance?',
                            html: 'Cash advance <b>' + esc(ca.ca_number) + '</b> of ' + esc(ca.planter_name) + ' (₱' + this.formatNumber(ca.amount) + ') and its weekly schedule will be permanently deleted.',
                            showCancelButton: true,
                            confirmButtonText: 'Delete',
                            confirmButtonColor: '#dc2626',
                            cancelButtonText: 'Cancel'
                        });
                        if (!res.isConfirmed) return;
                        try {
                            const r = await fetch('/cash-advances/' + ca.id, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const d = await r.json();
                            if (!r.ok) throw new Error(d.message || 'Request failed');
                            this.feed.remove(ca.id);
                            Swal.fire({ icon: 'success', title: 'Deleted', text: d.message, timer: 2000, showConfirmButton: false });
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    }
                };
            }
            // The "New Cash Advance" form's newCAForm() lives in modal/cashAdvanceModal.blade.php
        </script>
    @endpush
@endsection
