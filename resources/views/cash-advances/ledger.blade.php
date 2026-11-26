<!-- resources/views/cash-advances/ledger.blade.php -->
@extends('layouts.main')

@section('title', 'Cash Advance Ledger')

@section('content')
    <div x-data="caLedger()" x-init="init()" class="space-y-6 kp-fade-in">
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Financial Services</p>
                    <h1 class="kp-page-title mt-1">Cash Advance Ledger</h1>
                    <p class="kp-page-sub">One line per planter with their cash advances, payments and balance. Click a planter to see their advances and who made them.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('cash-advances.index') }}" class="kp-btn kp-btn-secondary"><i class="fas fa-list mr-1.5" aria-hidden="true"></i> All Cash Advances</a>
                    <button type="button" @click="print()" class="kp-btn kp-btn-primary" :disabled="!data || !data.planters.length">
                        <i class="fas fa-print mr-1.5" aria-hidden="true"></i>
                        <span x-text="picked.length ? 'Print Selected (' + picked.length + ')' : 'Print Ledger'"></span>
                    </button>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Filters -->
        <section class="kp-card p-4" aria-label="Ledger filters">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--kp-muted)" for="f-cy">Crop Year</label>
                    <select id="f-cy" x-model="f.crop_year" @change="load()" class="kp-input w-full">
                        <option value="">All crop years</option>
                        @foreach ($cropYears as $cy)
                            <option value="{{ $cy }}">{{ $cy }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--kp-muted)" for="f-from">Date From</label>
                    <input id="f-from" type="date" x-model="f.date_from" @change="load()" class="kp-input w-full">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--kp-muted)" for="f-to">Date To</label>
                    <input id="f-to" type="date" x-model="f.date_to" @change="load()" class="kp-input w-full">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--kp-muted)" for="f-status">Status</label>
                    <select id="f-status" x-model="f.status" @change="load()" class="kp-input w-full">
                        <option value="released">Released</option>
                        <option value="active">Active</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                        <option value="all">All statuses</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--kp-muted)" for="f-by">Created By</label>
                    <select id="f-by" x-model="f.created_by" @change="load()" class="kp-input w-full">
                        <option value="">Anyone</option>
                        <template x-for="(name, id) in creators" :key="id">
                            <option :value="id" x-text="name" :selected="String(id) === String(f.created_by)"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--kp-muted)" for="f-search">Search</label>
                    <input id="f-search" type="search" x-model="f.search" @input.debounce.400ms="load()" class="kp-input w-full" placeholder="Planter, code or CA No.">
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                <span style="color: var(--kp-muted)">Quick range:</span>
                <template x-for="r in [{ k: 'today', l: 'Today' }, { k: 'week', l: 'This week' }, { k: 'month', l: 'This month' }, { k: 'year', l: 'This year' }, { k: 'clear', l: 'Clear dates' }]" :key="r.k">
                    <button type="button" class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs" @click="range(r.k)" x-text="r.l"></button>
                </template>
                <span class="ml-auto" x-show="loading" role="status"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Loading…</span>
            </div>
        </section>

        <div x-show="error" x-cloak class="kp-dash-error" role="alert"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> <span x-text="error"></span></div>

        <template x-if="data">
            <div class="space-y-6">
                <!-- Totals -->
                <section aria-label="Ledger totals" class="kp-kpis">
                    <div class="kp-kpi">
                        <p class="kp-kpi-label"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Cash Advances</p>
                        <p class="kp-kpi-value kp-num" x-text="fmt(data.totals.count, 0)"></p>
                        <p class="kp-kpi-sub" x-text="data.totals.planters + ' planter(s) · ' + data.totals.settled + ' fully paid'"></p>
                    </div>
                    <div class="kp-kpi">
                        <p class="kp-kpi-label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Principal</p>
                        <p class="kp-kpi-value kp-num" x-text="'₱' + fmt(data.totals.principal)"></p>
                        <p class="kp-kpi-sub" x-text="'Total with interest ₱' + fmt(data.totals.total)"></p>
                    </div>
                    <div class="kp-kpi">
                        <p class="kp-kpi-label"><i class="fas fa-coins" aria-hidden="true"></i> Payments</p>
                        <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)" x-text="'₱' + fmt(data.totals.paid)"></p>
                        <p class="kp-kpi-sub" x-text="'₱' + fmt(data.totals.principal_paid) + ' principal · ₱' + fmt(data.totals.interest_paid) + ' interest'"></p>
                    </div>
                    <div class="kp-kpi">
                        <p class="kp-kpi-label"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Total Balance</p>
                        <p class="kp-kpi-value kp-num" style="color: var(--kp-cane)" x-text="'₱' + fmt(data.totals.balance)"></p>
                        <p class="kp-kpi-sub" x-text="pct(data.totals.paid, data.totals.total) + '% of the total paid'"></p>
                    </div>
                </section>

                <!-- The ledger: one line per planter -->
                <section class="kp-card overflow-hidden" aria-label="Cash advance ledger">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-xs" style="color: var(--kp-muted)">
                        <span x-text="data.planters.length + ' planter(s)' + (picked.length ? ' · ' + picked.length + ' selected to print' : ' · tick planters to print only those')"></span>
                        <button type="button" x-show="picked.length" @click="picked = []" class="font-semibold hover:underline" style="color: var(--kp-forest)">Clear selection</button>
                    </div>
                    {{-- Scrolls inside its own box, so the sideways scrollbar is always in view --}}
                    <div class="overflow-auto border-t" style="max-height: 70vh; border-color: var(--kp-border)">
                        <table class="text-sm whitespace-nowrap">
                            <thead class="sticky top-0 z-10" style="background: var(--kp-surface-2)"><tr>
                                <th scope="col" class="w-8">
                                    <input type="checkbox" :checked="allPicked()" @change="pickAll($event.target.checked)" aria-label="Select every planter">
                                </th>
                                <th scope="col">Pltr Code</th>
                                <th scope="col">Planter Name</th>
                                <th scope="col" class="text-right">CAs</th>
                                <th scope="col" class="text-right">Principal</th>
                                <th scope="col" class="text-right">Interest</th>
                                <th scope="col" class="text-right" title="Late charges (+) or balance reductions (−)">Adj.</th>
                                <th scope="col" class="text-right">Total</th>
                                <th scope="col" class="text-right" style="color: #b42318">Principal Pmt</th>
                                <th scope="col" class="text-right" style="color: #b42318">Interest Pmt</th>
                                <th scope="col" class="text-right">Balance</th>
                                <th scope="col"><span class="sr-only">Open</span></th>
                            </tr></thead>
                            <template x-for="g in data.planters" :key="g.planter_key">
                                <tbody>
                                    <tr class="cursor-pointer" @click="toggle(g.planter_key)"
                                        :style="open === g.planter_key ? 'background: rgba(8,122,61,0.07)' : ''">
                                        <td @click.stop>
                                            <input type="checkbox" :value="g.planter_code" x-model="picked" :aria-label="'Select ' + g.planter_name">
                                        </td>
                                        <td class="font-mono" style="color: #b42318" x-text="g.planter_code"></td>
                                        <td class="font-medium" style="color: var(--kp-forest)" x-text="g.planter_name"></td>
                                        <td class="text-right tabular" x-text="g.subtotal.count"></td>
                                        <td class="text-right tabular" x-text="fmt(g.subtotal.principal)"></td>
                                        <td class="text-right tabular" x-text="fmt(g.subtotal.interest)"></td>
                                        <td class="text-right tabular" :style="g.subtotal.adjustment < 0 ? 'color:#b42318' : ''" x-text="money(g.subtotal.adjustment)"></td>
                                        <td class="text-right tabular" x-text="fmt(g.subtotal.total)"></td>
                                        <td class="text-right tabular" x-text="money(g.subtotal.principal_paid)"></td>
                                        <td class="text-right tabular" x-text="money(g.subtotal.interest_paid)"></td>
                                        <td class="text-right tabular font-semibold" :style="g.subtotal.balance > 0.004 ? '' : 'color: var(--kp-forest)'" x-text="g.subtotal.balance > 0.004 ? fmt(g.subtotal.balance) : 'Paid'"></td>
                                        <td class="text-center"><i class="fas fa-chevron-down text-xs transition-transform" :class="open === g.planter_key ? 'rotate-180' : ''" style="color: var(--kp-muted)" aria-hidden="true"></i></td>
                                    </tr>
                                    <!-- This planter's advances -->
                                    <tr x-show="open === g.planter_key" x-cloak>
                                        <td colspan="12" class="p-0" style="background: var(--kp-surface-2)">
                                            <div class="px-4 py-3">
                                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                                    <p class="text-sm font-semibold" x-text="g.planter_name + ' · ' + g.planter_code"></p>
                                                    <div class="flex gap-2">
                                                        <button type="button" class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs" @click="openPlanter(g)"><i class="fas fa-clock-rotate-left mr-1" aria-hidden="true"></i> All crop years</button>
                                                        <button type="button" class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs" @click="print([g.planter_code])"><i class="fas fa-print mr-1" aria-hidden="true"></i> Print this planter</button>
                                                    </div>
                                                </div>
                                                <table class="text-xs whitespace-nowrap">
                                                    <thead><tr>
                                                        <th scope="col">Date</th><th scope="col">CA No.</th><th scope="col" class="text-center">Week #</th>
                                                        <th scope="col" class="text-right">Principal</th><th scope="col" class="text-right">Int. %</th><th scope="col" class="text-right">Interest</th>
                                                        <th scope="col" class="text-right">Adj.</th><th scope="col" class="text-right">Total</th>
                                                        <th scope="col" class="text-right">Paid</th><th scope="col" class="text-right">Balance</th>
                                                        <th scope="col">Status</th><th scope="col">Check No.</th><th scope="col">Created By</th>
                                                    </tr></thead>
                                                    <tbody>
                                                        <template x-for="r in g.rows" :key="r.id">
                                                            <tr>
                                                                <td class="tabular" x-text="shortDate(r.date)"></td>
                                                                <td><a :href="'{{ url('cash-advances') }}/' + r.id" class="hover:underline" style="color: var(--kp-forest)" x-text="r.ca_number"></a></td>
                                                                <td class="text-center tabular" x-text="r.weeks.length > 1 ? r.weeks[0] + '–' + r.weeks[r.weeks.length - 1] : (r.week_no ?? '—')"></td>
                                                                <td class="text-right tabular" x-text="fmt(r.principal)"></td>
                                                                <td class="text-right tabular" x-text="fmt(r.rate, 0) + '%'"></td>
                                                                <td class="text-right tabular" x-text="fmt(r.interest)"></td>
                                                                <td class="text-right tabular" x-text="money(r.adjustment)"></td>
                                                                <td class="text-right tabular" x-text="fmt(r.total)"></td>
                                                                <td class="text-right tabular" x-text="money(r.paid)"></td>
                                                                <td class="text-right tabular font-semibold" x-text="fmt(r.balance)"></td>
                                                                <td><span class="kp-badge" x-text="r.status"></span></td>
                                                                <td x-text="r.check_no || '—'"></td>
                                                                <td style="color: var(--kp-muted)" :title="r.created_at ? 'Created ' + r.created_at : ''" x-text="r.created_by_name || '—'"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </template>
                            <tbody>
                                <tr x-show="!data.planters.length"><td colspan="12" class="text-center text-sm" style="color: var(--kp-muted)">No cash advances for these filters</td></tr>
                            </tbody>
                            <tfoot class="sticky bottom-0" style="background: var(--kp-surface-2)">
                                <tr class="font-bold" x-show="data.planters.length">
                                    <td></td>
                                    <td colspan="2">GRAND TOTAL</td>
                                    <td class="text-right tabular" x-text="data.totals.count"></td>
                                    <td class="text-right tabular" x-text="fmt(data.totals.principal)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.totals.interest)"></td>
                                    <td class="text-right tabular" x-text="money(data.totals.adjustment)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.totals.total)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.totals.principal_paid)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.totals.interest_paid)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.totals.balance)"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="px-4 py-2 text-xs border-t" style="color: var(--kp-faint); border-color: var(--kp-border)">
                        Payments (voucher or cash over the counter) go to the interest and late charges first, then to the principal. Adj. = late charges (+) or balance reductions (−).
                    </p>
                </section>
            </div>
        </template>

        <!-- Planter history, all crop years -->
        <div x-show="history.open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="history.open = false">
            <div class="kp-viewport-modal-layout flex items-center justify-center">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="history.open = false"></div>
                <div class="kp-modal kp-viewport-modal-panel relative max-w-5xl w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="hist-title">
                    <div class="flex items-start justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                        <div>
                            <h3 id="hist-title" class="text-sm font-bold uppercase tracking-wider" x-text="history.name"></h3>
                            <p class="text-xs mt-1" style="color: var(--kp-muted)" x-text="'Planter code ' + history.code + ' · every cash advance, all crop years'"></p>
                        </div>
                        <button type="button" @click="history.open = false" class="hover:text-rose-600" style="color: var(--kp-muted)" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>

                    <p x-show="history.loading" class="text-sm" role="status"><i class="fas fa-circle-notch fa-spin mr-1" aria-hidden="true"></i> Loading…</p>
                    <template x-if="history.data">
                        <div class="space-y-4">
                            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4 text-sm">
                                <div class="rounded-lg p-3" style="background: var(--kp-surface-2)"><dt class="text-xs" style="color: var(--kp-muted)">Cash advances</dt>
                                    <dd class="font-bold tabular" x-text="history.data.totals.count + (history.data.totals.count !== history.data.totals.released ? ' (' + history.data.totals.released + ' released)' : '')"></dd></div>
                                <div class="rounded-lg p-3" style="background: var(--kp-surface-2)"><dt class="text-xs" style="color: var(--kp-muted)">Total (principal + interest)</dt>
                                    <dd class="font-bold tabular" x-text="'₱' + fmt(history.data.totals.total)"></dd></div>
                                <div class="rounded-lg p-3" style="background: var(--kp-surface-2)"><dt class="text-xs" style="color: var(--kp-muted)">Paid</dt>
                                    <dd class="font-bold tabular" style="color: var(--kp-forest)" x-text="'₱' + fmt(history.data.totals.paid)"></dd></div>
                                <div class="rounded-lg p-3" style="background: #fff3a3; color: #172b20"><dt class="text-xs">Balance</dt>
                                    <dd class="font-bold tabular" x-text="'₱' + fmt(history.data.totals.balance)"></dd></div>
                            </dl>

                            <template x-for="a in history.data.advances" :key="a.id">
                                <article class="rounded-xl border p-3" style="border-color: var(--kp-border)">
                                    <header class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <a :href="a.url" class="font-semibold hover:underline" style="color: var(--kp-forest)" x-text="a.ca_number"></a>
                                            <span class="kp-badge" x-text="a.status"></span>
                                            <span class="text-xs" style="color: var(--kp-muted)" x-text="shortDate(a.date) + ' · CY ' + a.crop_year + (a.check_no ? ' · Check No. ' + a.check_no : '')"></span>
                                        </div>
                                        <span class="text-xs" style="color: var(--kp-muted)" x-text="'Created by ' + (a.created_by_name || '—') + (a.approved_by_name ? ' · approved by ' + a.approved_by_name : '')"></span>
                                    </header>
                                    <dl class="mt-2 grid grid-cols-3 gap-2 text-xs tabular sm:grid-cols-6">
                                        <div><dt style="color: var(--kp-muted)">Principal</dt><dd x-text="fmt(a.principal)"></dd></div>
                                        <div><dt style="color: var(--kp-muted)">Interest</dt><dd x-text="fmt(a.interest) + ' (' + fmt(a.rate, 0) + '%)'"></dd></div>
                                        <div><dt style="color: var(--kp-muted)">Total</dt><dd x-text="fmt(a.total) + (a.adjustment ? ' (adj. ' + fmt(a.adjustment) + ')' : '')"></dd></div>
                                        <div><dt style="color: var(--kp-muted)">Principal paid</dt><dd x-text="fmt(a.principal_paid)"></dd></div>
                                        <div><dt style="color: var(--kp-muted)">Interest paid</dt><dd x-text="fmt(a.interest_paid)"></dd></div>
                                        <div><dt style="color: var(--kp-muted)">Balance</dt><dd class="font-bold" x-text="fmt(a.balance)"></dd></div>
                                    </dl>
                                    <p class="mt-1 text-xs" style="color: var(--kp-muted)" x-show="a.schedule.length"
                                        x-text="'Schedule: ' + a.schedule.map(s => 'Wk ' + s.week_no + ' ₱' + fmt(s.amount)).join(' · ')"></p>
                                    <p class="mt-1 text-xs" style="color: var(--kp-muted)" x-show="a.purpose" x-text="'Purpose: ' + a.purpose"></p>
                                    <table class="mt-2 text-xs" x-show="a.payments.length">
                                        <thead><tr><th scope="col">Date paid</th><th scope="col">Week</th><th scope="col">Method</th><th scope="col">Receipt No.</th><th scope="col" class="text-right">Amount</th></tr></thead>
                                        <tbody>
                                            <template x-for="(p, j) in a.payments" :key="j">
                                                <tr><td x-text="shortDate(p.paid_date)"></td><td x-text="p.week_no || '—'"></td><td x-text="p.method"></td><td x-text="p.receipt_no || '—'"></td><td class="text-right tabular" x-text="fmt(p.amount)"></td></tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <p class="mt-2 text-xs" style="color: var(--kp-faint)" x-show="!a.payments.length">No payments recorded yet.</p>
                                </article>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function caLedger() {
                const pad = (n) => String(n).padStart(2, '0');
                const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
                return {
                    f: { crop_year: @json($cropYears->first() ?? ''), date_from: '', date_to: '', status: 'released', created_by: '', search: '' },
                    data: null,
                    creators: {},
                    loading: false,
                    error: '',
                    requestId: 0,
                    // The planter whose advances are shown (one at a time) and the planters ticked to print
                    open: null,
                    picked: [],
                    history: { open: false, loading: false, code: '', name: '', data: null },

                    init() { this.load(); },

                    query(planters = null) {
                        const p = new URLSearchParams();
                        Object.entries(this.f).forEach(([k, v]) => { if (v !== '' && v !== null) p.append(k, v); });
                        (planters || []).forEach(c => p.append('planters[]', c));
                        return p.toString();
                    },
                    async load() {
                        const id = ++this.requestId;
                        this.loading = true;
                        this.error = '';
                        try {
                            const r = await fetch('{{ route('cash-advances.ledger.data') }}?' + this.query(), { headers: { Accept: 'application/json' } });
                            const d = await r.json();
                            if (id !== this.requestId) return;
                            if (!r.ok) throw new Error(d.message || 'Could not load the ledger.');
                            this.data = d;
                            this.creators = { ...this.creators, ...d.creators };
                            // Keep only the ticked planters still in the list
                            const codes = d.planters.map(g => g.planter_code);
                            this.picked = this.picked.filter(c => codes.includes(c));
                            if (!d.planters.some(g => g.planter_key === this.open)) this.open = null;
                        } catch (e) {
                            if (id === this.requestId) this.error = e.message;
                        } finally {
                            if (id === this.requestId) this.loading = false;
                        }
                    },
                    toggle(key) { this.open = this.open === key ? null : key; },
                    allPicked() { return this.data && this.data.planters.length > 0 && this.picked.length === this.data.planters.length; },
                    pickAll(on) { this.picked = on ? this.data.planters.map(g => g.planter_code) : []; },
                    range(kind) {
                        const now = new Date();
                        if (kind === 'clear') { this.f.date_from = ''; this.f.date_to = ''; }
                        if (kind === 'today') { this.f.date_from = this.f.date_to = ymd(now); }
                        if (kind === 'week') {
                            const mon = new Date(now); mon.setDate(now.getDate() - ((now.getDay() + 6) % 7));
                            const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
                            this.f.date_from = ymd(mon); this.f.date_to = ymd(sun);
                        }
                        if (kind === 'month') { this.f.date_from = ymd(new Date(now.getFullYear(), now.getMonth(), 1)); this.f.date_to = ymd(new Date(now.getFullYear(), now.getMonth() + 1, 0)); }
                        if (kind === 'year') { this.f.date_from = now.getFullYear() + '-01-01'; this.f.date_to = now.getFullYear() + '-12-31'; }
                        if (kind !== 'clear') this.f.crop_year = '';
                        this.load();
                    },
                    // The ticked planters (or the one given); every planter in the list when none is ticked
                    print(planters = null) {
                        const list = planters || this.picked;
                        const title = list.length === 1 ? 'CA Ledger · ' + (this.data.planters.find(g => g.planter_code === list[0])?.planter_name || list[0]) : 'Cash Advance Ledger';
                        window.KusugpaPdfPreview.open('{{ route('cash-advances.ledger.pdf') }}?' + this.query(list), title);
                    },
                    async openPlanter(g) {
                        this.history = { open: true, loading: true, code: g.planter_code, name: g.planter_name, data: null };
                        try {
                            const res = await fetch('{{ route('cash-advances.ledger.planter') }}?planter_code=' + encodeURIComponent(g.planter_code), { headers: { Accept: 'application/json' } });
                            const d = await res.json();
                            if (!res.ok) throw new Error(d.message || 'Could not load this planter.');
                            this.history.data = d;
                        } catch (e) {
                            this.history.open = false;
                            Swal.fire({ icon: 'error', title: 'Not loaded', text: e.message, confirmButtonColor: '#005B2A' });
                        } finally {
                            this.history.loading = false;
                        }
                    },

                    fmt(v, d = 2) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); },
                    money(v) { return Math.abs(Number(v || 0)) > 0.004 ? this.fmt(v) : '—'; },
                    pct(a, b) { return b > 0 ? Math.round(a / b * 1000) / 10 : 0; },
                    // MM.DD.YYYY, like the ledger book
                    shortDate(s) { if (!s) return '—'; const [y, m, d] = s.slice(0, 10).split('-'); return m + '.' + d + '.' + y; },
                };
            }
        </script>
    @endpush
@endsection
