<!-- resources/views/planter-deductions/index.blade.php -->
@extends('layouts.main')

@section('title', 'Fuel & Others Deduction')

@section('content')
    <div x-data="planterDeductions()" @keydown.escape.window="showModal = false" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Financial Services</p>
                    <h1 class="kp-page-title mt-1">Fuel &amp; Others Deduction</h1>
                    <p class="kp-page-sub">Planter fuel and other-account amounts, deducted on the Check Voucher in the scheduled weeks. No approval needed.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($canManage)
                        <button type="button" @click="openCreate()" class="kp-btn kp-btn-primary">
                            <i class="fas fa-plus-circle mr-1.5" aria-hidden="true"></i>
                            <span x-text="'New ' + typeLabel(tab) + ' Deduction'"></span>
                        </button>
                    @endif
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Fuel / Others tabs -->
        <div class="flex p-1.5 rounded-2xl bg-[#EAF5ED]/60 dark:bg-[#13231B] border border-[#CFDCD3] dark:border-[#203A2B] max-w-md">
            <template x-for="t in ['fuel', 'others']" :key="t">
                <button type="button" @click="tab = t"
                    :class="tab === t ? 'bg-white dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] shadow-sm font-bold' : 'text-[#52705F] dark:text-[#8AA895] hover:text-[#172B20] font-medium'"
                    class="flex-1 py-2 px-4 rounded-xl text-xs transition flex items-center justify-center gap-2">
                    <i class="fas" :class="t === 'fuel' ? 'fa-gas-pump' : 'fa-file-invoice-dollar'" aria-hidden="true"></i>
                    <span x-text="typeLabel(t)"></span>
                    <span class="kp-badge kp-badge--forest text-[10px] py-0.5 px-2" x-text="typeCount(t)"></span>
                </button>
            </template>
        </div>

        <!-- Stats (follow the tab and filters) -->
        <section aria-label="Deduction summary" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-list-ol" aria-hidden="true"></i> Deductions</p>
                <p class="kp-kpi-value kp-num" x-text="feed && feed.total !== null ? feed.total.toLocaleString() : '…'"></p>
                <p class="kp-kpi-sub" x-text="typeLabel(tab) + ' records matching the filters'"></p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-coins" aria-hidden="true"></i> Total Amount</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)" x-text="'₱' + formatNumber(feed ? feed.extra.amount : 0)"></p>
                <p class="kp-kpi-sub">Scheduled for deduction</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-users" aria-hidden="true"></i> Planters</p>
                <p class="kp-kpi-value kp-num" x-text="feed ? num(feed.extra.planters) : 0"></p>
                <p class="kp-kpi-sub">With a deduction</p>
            </div>
        </section>

        <!-- Filters -->
        <div class="kp-card p-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                        <input type="text" x-model="search" placeholder="Planter, code, reference or description..." class="kp-input w-full pl-9">
                    </div>
                </div>
                <div class="w-44">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Crop Year</label>
                    <select x-model="cropYearFilter" @change="weekFilter = ''" class="kp-select w-full">
                        <option value="">All Crop Years</option>
                        @foreach ($cropYears as $cy)
                            <option value="{{ $cy }}">{{ $cy }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-40">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Deduction Week</label>
                    <select x-model="weekFilter" :disabled="!cropYearFilter" class="kp-select w-full disabled:opacity-50">
                        <option value="" x-text="cropYearFilter ? 'All Weeks' : 'Crop year first'"></option>
                        <template x-for="w in allWeeks.filter(w => w.crop_year === cropYearFilter)" :key="w.week_no">
                            <option :value="w.week_no" x-text="'Week ' + w.week_no"></option>
                        </template>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table kp-table-sticky">
                    <thead>
                        <tr>
                            @if ($canManage)
                                <th class="text-center">Actions</th>
                            @endif
                            <th>Reference</th>
                            <th>Planter</th>
                            <th>Crop Year</th>
                            <th class="text-right">Amount</th>
                            <th>Deduction Weeks</th>
                            <th>Description</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="d in filtered" :key="d.id">
                            <tr>
                                @if ($canManage)
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" @click="openEdit(d)" title="Edit"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs" style="color: var(--kp-cane)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" @click="remove(d)" title="Delete"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs text-red-500">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                @endif
                                <td class="font-mono text-xs font-semibold" style="color: var(--kp-text)" x-text="d.reference_no"></td>
                                <td>
                                    <p class="text-sm font-medium" style="color: var(--kp-text)" x-text="d.planter_name"></p>
                                    <p class="text-xs font-mono" style="color: var(--kp-muted)" x-text="d.planter_code"></p>
                                </td>
                                <td class="font-mono text-xs" style="color: var(--kp-muted)" x-text="d.crop_year"></td>
                                <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-emerald)" x-text="formatNumber(d.amount)"></td>
                                <td class="text-xs" style="color: var(--kp-muted)">
                                    <template x-for="w in d.weeks" :key="w.id">
                                        <div class="whitespace-nowrap"><span class="font-semibold" x-text="'Wk ' + w.week_no"></span> = <span class="font-mono" x-text="formatNumber(w.amount)"></span></div>
                                    </template>
                                </td>
                                <td class="text-xs max-w-[16rem]" style="color: var(--kp-muted)" x-text="d.description || '—'"></td>
                                <td class="text-xs whitespace-nowrap" style="color: var(--kp-muted)">
                                    <span x-text="formatDate(d.created_at)"></span>
                                    <span x-show="d.created_by_user" x-text="d.created_by_user ? ' · ' + d.created_by_user.fname : ''"></span>
                                </td>
                            </tr>
                        </template>
                        <template x-if="feed">
                            @include('layouts.partials.feed-footer', ['feed' => 'feed', 'colspan' => 8, 'empty' => 'No deductions match the filters.'])
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        @if ($canManage)
            <!-- Create / Edit Modal -->
            <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
                <div class="kp-viewport-modal-layout flex items-center justify-center">
                    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>
                    <div class="kp-modal kp-viewport-modal-panel relative max-w-3xl w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="deduction-modal-title">
                        <div class="flex items-center justify-between pb-3 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <div>
                                <h3 id="deduction-modal-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider"
                                    x-text="(editing ? 'Edit ' : 'New ') + typeLabel(form.type) + ' Deduction' + (editing ? ' · ' + editing.reference_no : '')"></h3>
                                <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]"
                                    x-text="'Deducted on the planter\'s Check Voucher as ' + (form.type === 'fuel' ? 'FUEL PAYMENT' : 'OTHERS ACCOUNT') + ' in the weeks below.'"></p>
                            </div>
                            <button type="button" @click="showModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895] p-1.5 rounded-lg">
                                <i class="fas fa-times text-base"></i>
                            </button>
                        </div>

                        <form @submit.prevent="save()" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Crop Year <span class="text-rose-600">*</span></label>
                                    <select x-model="form.crop_year" @change="buildSchedule()" required class="kp-input w-full">
                                        <template x-for="cy in cropYears" :key="cy">
                                            <option :value="cy" x-text="cy" :selected="cy === form.crop_year"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Planter <span class="text-rose-600">*</span></label>
                                    <select x-model="form.planter_code" @change="form.planter_name = planterName(form.planter_code)" required class="kp-input w-full">
                                        <option value="">Select Planter</option>
                                        <template x-for="p in planters" :key="p.planter_code">
                                            <option :value="p.planter_code" x-text="p.planter_name + ' (' + p.planter_code + ')'" :selected="p.planter_code === form.planter_code"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Amount (₱) <span class="text-rose-600">*</span></label>
                                    <input type="number" x-model="form.amount" @input="if (!manualSplit) autoSplit()" step="any" min="0" required class="kp-input w-full" placeholder="0.00">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Term (Weeks) <span class="text-rose-600">*</span></label>
                                    <select x-model.number="termWeeks" @change="buildSchedule()" class="kp-input w-full">
                                        <template x-for="n in termOptions()" :key="n">
                                            <option :value="n" :selected="n === termWeeks" x-text="n + (n === 1 ? ' Week' : ' Weeks')"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Description</label>
                                    <input type="text" x-model="form.description" maxlength="500" class="kp-input w-full"
                                        :placeholder="form.type === 'fuel' ? 'e.g. Diesel issued 10/20' : 'e.g. Fertilizer account'">
                                </div>
                            </div>

                            <!-- Weekly Deduction Schedule -->
                            <div class="rounded-xl p-4 bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B]">
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <h4 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Weekly Deduction Schedule</h4>
                                    <button type="button" @click="manualSplit = false; autoSplit()" :disabled="!schedule.length"
                                        class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs disabled:opacity-50">
                                        <i class="fas fa-arrow-right-arrow-left mr-1.5" aria-hidden="true"></i> Auto Split Evenly
                                    </button>
                                </div>
                                <p x-show="!schedule.length" class="text-xs py-2 text-amber-700 dark:text-amber-400">
                                    <i class="fas fa-triangle-exclamation mr-1" aria-hidden="true"></i>
                                    No weeks are set up for this crop year in Crop &amp; Week Management.
                                </p>
                                <div x-show="schedule.length" class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-[11px] uppercase tracking-wider text-[#52705F] dark:text-[#8AA895] bg-[#EAF5ED]/60 dark:bg-[#1A3324]/60">
                                                <th class="text-left font-semibold px-2 py-2">Week</th>
                                                <th class="text-left font-semibold px-2 py-2">Week Start</th>
                                                <th class="text-left font-semibold px-2 py-2">Week End</th>
                                                <th class="text-left font-semibold px-2 py-2">Amount (₱)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="row in schedule" :key="row.uid">
                                                <tr class="border-b border-[#E3EBE5] dark:border-[#203A2B]">
                                                    <td class="px-2 py-1.5">
                                                        <select x-model="row.week_no" @change="changeWeek(row)"
                                                            class="kp-input py-1 text-xs font-semibold min-w-[6.5rem]"
                                                            :class="duplicateWeeks().includes(row.week_no) ? 'border-rose-500 text-rose-700 dark:text-rose-400' : 'text-[#005B2A] dark:text-[#52C97E]'">
                                                            <template x-for="w in cropYearWeeks()" :key="w.week_no">
                                                                <option :value="w.week_no" :selected="w.week_no === row.week_no" x-text="'Week ' + w.week_no"></option>
                                                            </template>
                                                        </select>
                                                    </td>
                                                    <td class="px-2 py-1.5 whitespace-nowrap" x-text="formatDate(row.week_start)"></td>
                                                    <td class="px-2 py-1.5 whitespace-nowrap" x-text="formatDate(row.week_end)"></td>
                                                    <td class="px-2 py-1.5">
                                                        <input type="number" x-model="row.amount" @input="manualSplit = true" step="any" min="0"
                                                            class="kp-input w-full min-w-[7rem] py-1 text-xs text-right kp-num" placeholder="0.00">
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <div class="flex items-center justify-between mt-2 px-3 py-2 rounded-lg"
                                        :class="isBalanced() ? 'bg-[#EAF5ED] dark:bg-[#1A3324]' : 'bg-rose-50 dark:bg-rose-950/30'">
                                        <span class="text-xs font-bold uppercase tracking-wider text-[#172B20] dark:text-[#EAF5ED]">Total Scheduled</span>
                                        <span class="text-sm font-bold kp-num text-[#172B20] dark:text-[#EAF5ED]">₱ <span x-text="formatNumber(totalScheduled())"></span></span>
                                    </div>
                                    <p x-show="duplicateWeeks().length" class="text-xs mt-2 text-rose-700 dark:text-rose-400">
                                        <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i>
                                        Week <span x-text="duplicateWeeks().join(', ')"></span> is listed more than once.
                                    </p>
                                    <p x-show="!isBalanced()" class="text-xs mt-2 text-rose-700 dark:text-rose-400">
                                        <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i>
                                        Weekly deductions are <span x-text="totalScheduled() < num(form.amount) ? 'short' : 'over'"></span>
                                        by ₱<span x-text="formatNumber(Math.abs(num(form.amount) - totalScheduled()))"></span>. They must equal the amount.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                                <button type="button" @click="showModal = false" class="kp-btn kp-btn-secondary text-xs">Cancel</button>
                                <button type="submit" :disabled="saving || !canSave()" :title="canSave() ? '' : blockReason()"
                                    class="kp-btn kp-btn-primary text-xs disabled:opacity-50">
                                    <i class="fas mr-1.5" :class="saving ? 'fa-circle-notch fa-spin' : 'fa-check'" aria-hidden="true"></i>
                                    <span x-text="saving ? 'Saving…' : (editing ? 'Save Changes' : 'Save Deduction')"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            function planterDeductions() {
                return {
                    // Deductions of the open tab come from the server in batches of 100 as the table scrolls
                    // (kpFeed); filters and search run in the database
                    feed: null,
                    searchTimer: null,
                    planters: @json($planters),
                    allWeeks: @json($weeks),
                    cropYears: @json($cropYears->map(fn ($cy) => (string) $cy)->values()),
                    tab: 'fuel',
                    search: '',
                    cropYearFilter: '',
                    weekFilter: '', // deductions scheduled in this week of the crop year
                    showModal: false,
                    editing: null,
                    saving: false,
                    form: {},
                    termWeeks: 1,
                    schedule: [],
                    rowUid: 0,
                    manualSplit: false,

                    init() {
                        this.form = this.blankForm();
                        this.feed = kpFeed('{{ route('planter-deductions.index') }}', () => ({
                            type: this.tab, crop_year: this.cropYearFilter, week_no: this.weekFilter, search: this.search.trim(),
                        }));
                        this.feed.reset();
                        ['tab', 'cropYearFilter', 'weekFilter'].forEach(key => this.$watch(key, () => this.feed.reset()));
                        this.$watch('search', () => {
                            clearTimeout(this.searchTimer);
                            this.searchTimer = setTimeout(() => this.feed.reset(), 350);
                        });
                    },
                    // Counts for the tab badges (same crop year / week / search)
                    typeCount(t) {
                        return this.num(((this.feed && this.feed.extra.counts) || {})[t]);
                    },
                    typeLabel(t) {
                        return t === 'fuel' ? 'Fuel' : 'Others';
                    },
                    num(v) {
                        const n = parseFloat(v);
                        return isNaN(n) ? 0 : n;
                    },
                    formatNumber(n) {
                        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    formatDate(d) {
                        if (!d) return '—';
                        const [y, m, day] = String(d).slice(0, 10).split('-').map(Number);
                        return new Date(y, m - 1, day).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                    },
                    planterName(code) {
                        const p = this.planters.find(p => p.planter_code === code);
                        return p ? p.planter_name : '';
                    },

                    // Rows of the open tab loaded so far (already filtered by the server)
                    get filtered() {
                        return this.feed ? this.feed.rows : [];
                    },

                    // ---------- Form ----------
                    today() {
                        const now = new Date();
                        return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
                    },
                    // Crop year whose weeks cover today, else the latest crop year that has weeks
                    defaultCropYear() {
                        const today = this.today();
                        const current = this.allWeeks.find(w => w.week_start <= today && w.week_end >= today);
                        if (current) return current.crop_year;
                        const withWeeks = this.cropYears.filter(cy => this.allWeeks.some(w => w.crop_year === cy));
                        return withWeeks[withWeeks.length - 1] || this.cropYears[0] || '';
                    },
                    blankForm() {
                        return { type: this.tab, planter_code: '', planter_name: '', crop_year: this.defaultCropYear(), amount: '', description: '' };
                    },
                    cropYearWeeks() {
                        return this.allWeeks.filter(w => w.crop_year === this.form.crop_year);
                    },
                    termOptions() {
                        const max = this.cropYearWeeks().length || 12;
                        return Array.from({ length: max }, (_, i) => i + 1);
                    },
                    // Suggested weeks start at the current week; each row's week can be changed
                    buildSchedule() {
                        const weeks = this.cropYearWeeks();
                        if (weeks.length && this.termWeeks > weeks.length) this.termWeeks = weeks.length;
                        if (!this.termWeeks) this.termWeeks = 1;
                        const today = this.today();
                        const current = Math.max(0, weeks.findIndex(w => w.week_end && w.week_end >= today));
                        const start = Math.max(0, Math.min(current, weeks.length - this.termWeeks));
                        this.schedule = weeks.slice(start, start + this.termWeeks).map(w => ({
                            uid: ++this.rowUid, week_no: w.week_no, week_start: w.week_start, week_end: w.week_end, amount: '',
                        }));
                        this.manualSplit = false;
                        this.autoSplit();
                    },
                    changeWeek(row) {
                        const w = this.cropYearWeeks().find(x => x.week_no === row.week_no);
                        row.week_start = w?.week_start || '';
                        row.week_end = w?.week_end || '';
                    },
                    duplicateWeeks() {
                        const seen = {};
                        this.schedule.forEach(r => seen[r.week_no] = (seen[r.week_no] || 0) + 1);
                        return Object.keys(seen).filter(w => seen[w] > 1);
                    },
                    // Even split in centavos; the last week takes the remainder
                    autoSplit() {
                        const n = this.schedule.length;
                        if (!n) return;
                        const cents = Math.round(this.num(this.form.amount) * 100);
                        const each = Math.floor(cents / n);
                        this.schedule.forEach((row, i) => {
                            const c = i === n - 1 ? cents - each * (n - 1) : each;
                            row.amount = cents ? (c / 100).toFixed(2) : '';
                        });
                    },
                    totalScheduled() {
                        return this.schedule.reduce((t, r) => t + Math.round(this.num(r.amount) * 100), 0) / 100;
                    },
                    isBalanced() {
                        return Math.round(this.totalScheduled() * 100) === Math.round(this.num(this.form.amount) * 100);
                    },
                    canSave() {
                        return this.form.planter_code && this.num(this.form.amount) > 0 && this.schedule.length > 0 && !this.duplicateWeeks().length && this.isBalanced();
                    },
                    blockReason() {
                        if (!this.form.planter_code) return 'Select the planter.';
                        if (!(this.num(this.form.amount) > 0)) return 'Enter the amount.';
                        if (!this.schedule.length) return 'No weeks are set up for this crop year.';
                        if (this.duplicateWeeks().length) return 'Week ' + this.duplicateWeeks().join(', ') + ' is listed more than once.';
                        return 'Weekly deductions must equal the amount.';
                    },
                    openCreate() {
                        this.editing = null;
                        this.form = this.blankForm();
                        this.termWeeks = 1;
                        this.buildSchedule();
                        this.showModal = true;
                    },
                    openEdit(d) {
                        this.editing = d;
                        this.form = {
                            type: d.type, planter_code: d.planter_code, planter_name: d.planter_name,
                            crop_year: String(d.crop_year), amount: d.amount, description: d.description || '',
                        };
                        // Keep a planter that is no longer active selectable
                        if (!this.planters.some(p => p.planter_code === d.planter_code)) {
                            this.planters.push({ planter_code: d.planter_code, planter_name: d.planter_name });
                        }
                        const weeks = this.cropYearWeeks();
                        this.schedule = d.weeks.map(w => {
                            const info = weeks.find(x => x.week_no === String(w.week_no)) || {};
                            return { uid: ++this.rowUid, week_no: String(w.week_no), week_start: info.week_start, week_end: info.week_end, amount: w.amount };
                        });
                        this.termWeeks = this.schedule.length || 1;
                        this.manualSplit = true;
                        this.showModal = true;
                    },
                    async post(url, body = {}) {
                        const r = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(body)
                        });
                        const d = await r.json();
                        if (!r.ok) throw new Error(d.message || 'Request failed');
                        return d;
                    },
                    async save() {
                        if (!this.canSave()) {
                            Swal.fire({ icon: 'warning', title: 'Cannot save yet', text: this.blockReason() });
                            return;
                        }
                        this.saving = true;
                        try {
                            const payload = {
                                ...this.form,
                                amount: Math.round(this.num(this.form.amount) * 100) / 100,
                                schedule: this.schedule.map(r => ({ week_no: r.week_no, due_date: r.week_end || null, amount: Math.round(this.num(r.amount) * 100) / 100 })),
                            };
                            const url = this.editing
                                ? '/planter-deductions/' + this.editing.id + '/update'
                                : '{{ route('planter-deductions.store') }}';
                            const d = await this.post(url, payload);
                            // Reload the tab of the saved deduction (counts and totals included)
                            if (this.tab !== d.deduction.type) {
                                this.tab = d.deduction.type;
                            } else {
                                this.feed.reset();
                            }
                            this.showModal = false;
                            Swal.fire({ icon: 'success', title: 'Saved', text: d.message, timer: 2400, showConfirmButton: false });
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        } finally {
                            this.saving = false;
                        }
                    },
                    async remove(d) {
                        const res = await Swal.fire({
                            icon: 'warning',
                            title: 'Delete this deduction?',
                            text: d.reference_no + ' · ' + d.planter_name + ' · ₱' + this.formatNumber(d.amount) + '. It will no longer come off the planter\'s vouchers.',
                            showCancelButton: true,
                            confirmButtonText: 'Delete',
                            confirmButtonColor: '#dc2626'
                        });
                        if (!res.isConfirmed) return;
                        try {
                            const r = await this.post('/planter-deductions/' + d.id + '/delete');
                            this.feed.reset();
                            Swal.fire({ icon: 'success', title: 'Deleted', text: r.message, timer: 1600, showConfirmButton: false });
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    },
                }
            }
        </script>
    @endpush
@endsection
