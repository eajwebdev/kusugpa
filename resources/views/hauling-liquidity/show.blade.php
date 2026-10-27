<!-- resources/views/hauling-liquidity/show.blade.php -->
@extends('layouts.main')

@section('title', 'Hauler TLS LIQ - ' . $hauler->name)

@section('content')
    <div x-data="liquidationData()" @keydown.escape.window="showModal = false" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <a href="{{ route('hauling-liquidity.index') }}"
                            class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs inline-flex items-center gap-1.5">
                            <i class="fas fa-arrow-left text-[11px]"></i>
                            <span>Haulers</span>
                        </a>
                        @unless ($hauler->is_active)
                            <span class="kp-badge kp-badge-neutral">Inactive</span>
                        @endunless
                    </div>
                    <h1 class="kp-page-title">{{ $hauler->name }}</h1>
                    <p class="kp-page-sub mt-1">KUSUG-PA / Hauling liquidation entries by crop year and week.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($canManage)
                        <button @click="openAddModal()" class="kp-btn kp-btn-primary">
                            <i class="fas fa-plus-circle mr-1.5" aria-hidden="true"></i> New Entry
                        </button>
                    @endif
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Stats Strip (follows the filters) -->
        <section aria-label="Liquidation summary metrics" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-file-invoice" aria-hidden="true"></i> Entries</p>
                <p class="kp-kpi-value kp-num" x-text="matchingCount.toLocaleString()"></p>
                <p class="kp-kpi-sub">Matching the filters</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-weight-hanging" aria-hidden="true"></i> Tonnage</p>
                <p class="kp-kpi-value kp-num" x-text="formatNumber(sumOf('tonnage'), 3)"></p>
                <p class="kp-kpi-sub">Metric tons hauled</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-truck" aria-hidden="true"></i> Gross Hauling</p>
                <p class="kp-kpi-value kp-num" x-text="'₱' + formatNumber(sumOf('gross_amount'), 2)"></p>
                <p class="kp-kpi-sub" x-text="'Less ₱' + formatNumber(sumOf('total_less'), 2) + ' deductions'"></p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-coins" aria-hidden="true"></i> Net Liquidation</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)" x-text="'₱' + formatNumber(sumOf('net_amount'), 2)"></p>
                <p class="kp-kpi-sub">Amount released to payees</p>
            </div>
        </section>

        <!-- Filter Bar -->
        <div class="kp-card p-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                        <input type="text" x-model="search" placeholder="Payee, planter code, plate # or CV no..." class="kp-input w-full pl-9">
                    </div>
                </div>
                <div class="w-44">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Crop Year</label>
                    <select x-model="cropYearFilter" @change="weekFilter = 'all'" class="kp-select w-full">
                        <option value="all">All Crop Years</option>
                        <template x-for="cy in cropYears" :key="cy">
                            <option :value="cy" x-text="cropYearLabel(cy)"></option>
                        </template>
                    </select>
                </div>
                <div class="w-36">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Week</label>
                    <select x-model="weekFilter" :disabled="cropYearFilter === 'all'" class="kp-select w-full disabled:opacity-50">
                        <option value="all">All Weeks</option>
                        <template x-for="w in weeksFor(cropYearFilter)" :key="w.week_no">
                            <option :value="w.week_no" x-text="'Week ' + w.week_no"></option>
                        </template>
                    </select>
                </div>
                <button @click="search = ''; cropYearFilter = 'all'; weekFilter = 'all'" class="kp-btn kp-btn-secondary h-[38px]">
                    <i class="fas fa-times mr-1"></i> Reset
                </button>
                <button @click="previewWeek()" :disabled="cropYearFilter === 'all' || weekFilter === 'all' || matchingCount === 0"
                    class="kp-btn kp-btn-secondary h-[38px] disabled:opacity-50"
                    :title="weekFilter === 'all' ? 'Pick a crop year and week to print all its slips' : 'Print all slips for this week'">
                    <i class="fas fa-print mr-1"></i> Print Week
                </button>
            </div>
        </div>

        <!-- Entries Table -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table">
                    <thead>
                        <tr>
                            <th class="w-px whitespace-nowrap">Actions</th>
                            <template x-for="col in columns" :key="col.key">
                                <th class="cursor-pointer select-none whitespace-nowrap" :class="col.numeric ? 'text-right' : ''" @click="sortBy(col.key)">
                                    <div class="flex items-center gap-1.5" :class="col.numeric ? 'justify-end' : ''">
                                        <span x-text="col.label"></span>
                                        <i class="fas text-[10px]" :class="sortField === col.key ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="entry in sortedEntries" :key="entry.id">
                            <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                <td class="w-px whitespace-nowrap">
                                    <div class="kp-row-actions">
                                        <button type="button" @click="previewSlip(entry)" class="is-view" title="Preview liquidation slip" aria-label="Preview liquidation slip">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </button>
                                        @if ($canManage)
                                            <button type="button" @click="editEntry(entry)" class="is-edit" title="Edit entry" aria-label="Edit entry">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                            </button>
                                            <button type="button" @click="deleteEntry(entry)" class="is-delete" title="Delete entry" aria-label="Delete entry">
                                                <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap" style="color: var(--kp-text)">
                                    <span class="font-medium" x-text="cropYearLabel(entry.crop_year)"></span>
                                    <span class="text-xs" style="color: var(--kp-muted)" x-text="' · Wk ' + entry.week_no"></span>
                                </td>
                                <td class="whitespace-nowrap" style="color: var(--kp-muted)" x-text="formatDate(entry.week_ending)"></td>
                                <td class="font-medium">
                                    <span x-show="entry.payee_name" style="color: var(--kp-text)" x-text="entry.payee_name"></span>
                                    @if ($canManage)
                                        {{-- The TLS upload has no payee: type it here; the plate keeps it in later weeks --}}
                                        <template x-if="!entry.payee_name && payeeEdit.id !== entry.id">
                                            <button type="button" @click="startPayee(entry)" class="kp-badge kp-badge--amber text-[11px] hover:opacity-80"
                                                :title="'Set the hauler / driver of plate ' + (entry.truck_code || '')">
                                                <i class="fas fa-pen text-[9px]" aria-hidden="true"></i> Payee not set
                                            </button>
                                        </template>
                                        <template x-if="payeeEdit.id === entry.id">
                                            <form @submit.prevent="savePayee(entry)" class="flex items-center gap-1">
                                                <input type="text" x-model="payeeEdit.value" list="payee-names" maxlength="255"
                                                    x-init="$nextTick(() => $el.focus())" @keydown.escape.prevent="payeeEdit = { id: null, value: '', saving: false }"
                                                    placeholder="Hauler / driver name" class="kp-input py-1 px-2 text-xs w-48">
                                                <button type="submit" :disabled="payeeEdit.saving || !payeeEdit.value.trim()" title="Save (Enter)"
                                                    class="p-1 rounded text-[#005B2A] dark:text-[#52C97E] disabled:opacity-40">
                                                    <i class="fas" :class="payeeEdit.saving ? 'fa-circle-notch fa-spin' : 'fa-check'"></i>
                                                </button>
                                            </form>
                                        </template>
                                    @else
                                        <span x-show="!entry.payee_name" style="color: #B45309">Payee not set</span>
                                    @endif
                                </td>
                                <td class="font-mono" style="color: var(--kp-muted)" x-text="entry.cv_no || '—'"></td>
                                <td class="font-mono" style="color: var(--kp-muted)" x-text="entry.truck_code || '—'"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="formatNumber(entry.tonnage, 3)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-muted)" x-text="formatNumber(entry.rate_per_ton, 2)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="formatNumber(entry.gross_amount, 2)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-muted)" x-text="formatNumber(entry.total_add, 2)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-muted)" x-text="formatNumber(entry.total_less, 2)"></td>
                                <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-emerald)" x-text="formatNumber(entry.net_amount, 2)"></td>
                            </tr>
                        </template>
                        <template x-if="feed">
                            @include('layouts.partials.feed-footer', ['feed' => 'feed', 'colspan' => 12, 'empty' => 'No liquidation entries match the current filters.'])
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        @if ($canManage)
            <datalist id="truck-codes">
                @foreach ($truckCodes as $code)
                    <option value="{{ $code }}"></option>
                @endforeach
            </datalist>
            <datalist id="payee-names">
                @foreach ($payeeNames as $name)
                    <option value="{{ $name }}"></option>
                @endforeach
            </datalist>

            <!-- Hauling Liquidation Entry Modal -->
            <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>
                <div class="relative rounded-xl border w-full max-w-7xl shadow-2xl max-h-[94vh] flex flex-col overflow-y-auto md:overflow-hidden"
                    style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                    <div class="flex items-center justify-between gap-4 px-6 pt-4 pb-3 border-b shrink-0" style="border-color: var(--kp-border)">
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <h3 class="font-display text-lg font-semibold" style="color: var(--kp-text)"
                                x-text="editingEntry ? 'Edit Hauling Liquidation' : 'Hauling Liquidation Entry'"></h3>
                            <p class="text-xs font-semibold uppercase tracking-wide" style="color: var(--kp-muted)">KUSUG-PA / {{ $hauler->name }}</p>
                        </div>
                        <button @click="showModal = false" class="text-xs p-1 rounded hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>

                    <form @submit.prevent="saveEntry()" class="flex flex-col flex-1 min-h-0">
                        <div class="px-6 pt-4 space-y-3 shrink-0">
                            <div x-show="cropYears.length === 0" class="kp-alert kp-alert-warn text-sm">
                                No crop years are set up yet. Add the crop year and its weeks in
                                <a href="{{ route('crop-weeks.index') }}" class="font-semibold underline">Crop &amp; Week Management</a> first.
                            </div>

                            <!-- Period & references -->
                            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Week Ending</label>
                                    <input type="date" :value="formWeek ? formWeek.week_end_date : ''" readonly tabindex="-1"
                                        class="kp-input w-full" style="background: var(--kp-surface-2)" title="Set automatically from the selected week">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Date Released</label>
                                    <input type="date" x-model="form.date_released" class="kp-input w-full">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Crop Year *</label>
                                    <select x-model="form.crop_year" @change="form.week_no = ''; autoInfo = null" required class="kp-select w-full">
                                        <option value="">Select…</option>
                                        <template x-for="cy in cropYears" :key="cy">
                                            <option :value="cy" x-text="cropYearLabel(cy)" :selected="cy === form.crop_year"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Week No. *</label>
                                    <select x-model="form.week_no" @change="loadAutoDeductions()" required :disabled="!form.crop_year" class="kp-select w-full disabled:opacity-50">
                                        <option value="">Select…</option>
                                        <template x-for="w in weeksFor(form.crop_year)" :key="w.week_no">
                                            <option :value="w.week_no" x-text="weekLabel(w)" :selected="w.week_no === form.week_no"></option>
                                        </template>
                                    </select>
                                    <p x-show="form.crop_year && weeksFor(form.crop_year).length === 0" class="text-[11px] mt-1 text-red-500">
                                        No weeks set up for this crop year.
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">CV No.</label>
                                    <input type="text" x-model="form.cv_no" class="kp-input w-full">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Check No.</label>
                                    <input type="text" x-model="form.check_no" placeholder="e.g. 213497" class="kp-input w-full">
                                </div>
                            </div>

                            <!-- Payee & hauling -->
                            <div class="grid grid-cols-2 md:grid-cols-12 gap-3">
                                <div class="col-span-2 md:col-span-4">
                                    <label for="hl-payee" class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Payee Name *</label>
                                    <input type="text" id="hl-payee" x-model="form.payee_name" @change="loadAutoDeductions()" list="payee-names"
                                        required maxlength="255" placeholder="Hauler / driver name" class="kp-input w-full">
                                    <p x-show="editingEntry && !form.payee_name.trim()" class="text-[11px] mt-1 text-red-500"
                                        x-text="'Enter the payee (hauler / driver) for plate ' + (form.truck_code || '') + '. Next week’s upload remembers it.'"></p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Plate #</label>
                                    <input type="text" x-model="form.truck_code" list="truck-codes" placeholder="e.g. WHU612" class="kp-input w-full">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Tonnage (MT) *</label>
                                    <input type="number" x-model="form.tonnage" @input="$nextTick(() => applyFixedDeductions())" step="0.001" min="0" required class="kp-input w-full text-right">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Rate per Ton *</label>
                                    <input type="number" x-model="form.rate_per_ton" @input="$nextTick(() => applyFixedDeductions())" step="0.01" min="0" required class="kp-input w-full text-right">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Gross Hauling Amount</label>
                                    <input type="text" :value="formatNumber(grossAmount, 2)" readonly tabindex="-1"
                                        class="kp-input w-full text-right font-mono" style="background: var(--kp-surface-2)">
                                </div>
                            </div>
                        </div>

                        <!-- ADD / LESS side by side; only this area scrolls when rows grow -->
                        <div class="px-6 py-4 flex-1 min-h-0 md:overflow-y-auto">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                <template x-for="section in [
                                    { key: 'add_items', title: 'ADD', sub: 'Additional Charges' },
                                    { key: 'less_items', title: 'LESS', sub: 'Deductions' }
                                ]" :key="section.key">
                                    <div class="rounded-lg border overflow-hidden" style="border-color: var(--kp-border)">
                                        <div class="flex items-center justify-between px-4 py-2" style="background: var(--kp-surface-2)">
                                            <p class="text-sm font-bold" style="color: var(--kp-text)">
                                                <span x-text="section.title"></span>
                                                <span class="text-xs font-normal" style="color: var(--kp-muted)" x-text="'(' + section.sub + ')'"></span>
                                            </p>
                                            <div class="flex items-center gap-3">
                                                <span class="text-xs font-mono font-semibold tabular" style="color: var(--kp-muted)"
                                                    x-text="formatNumber(totalOf(section.key), 2)"></span>
                                                <button type="button" @click="form[section.key].push({ description: '', amount: '' })"
                                                    class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs">
                                                    <i class="fas fa-plus mr-1"></i> Add Row
                                                </button>
                                            </div>
                                        </div>
                                        <div class="px-4 py-2.5 space-y-1.5">
                                            <div class="grid grid-cols-[1fr_9rem_2rem] gap-2 text-xs font-semibold" style="color: var(--kp-muted)">
                                                <span>Description</span><span class="text-right pr-1">Amount</span><span></span>
                                            </div>
                                            <template x-for="(row, i) in form[section.key]" :key="i">
                                                <div class="grid grid-cols-[1fr_9rem_2rem] gap-2 items-center">
                                                    <input type="text" x-model="row.description" class="kp-input w-full !py-1.5" placeholder="Description">
                                                    <input type="number" x-model="row.amount" step="0.01" class="kp-input w-full text-right !py-1.5" placeholder="0.00">
                                                    <button type="button" @click="form[section.key].splice(i, 1)"
                                                        class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-red-500" title="Remove row">
                                                        <i class="fas fa-trash-alt text-xs"></i>
                                                    </button>
                                                </div>
                                            </template>
                                            <p x-show="form[section.key].length === 0" class="text-xs py-1" style="color: var(--kp-muted)">No rows.</p>
                                            <p x-show="section.key === 'less_items'" class="text-[11px] pt-1" style="color: var(--kp-muted)">
                                                <i class="fas fa-calculator mr-1"></i>EWT = Gross Hauling × 2% · MIS. = Tonnage × ₱1.50 — computed from the hauling figures, you can still edit them.
                                            </p>
                                            <p x-show="section.key === 'less_items' && autoInfo" class="text-[11px] pt-1" style="color: var(--kp-forest)">
                                                <i class="fas fa-link mr-1"></i><span x-text="autoInfoText"></span>
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Totals + actions (always visible) -->
                        <div class="px-6 py-3 border-t shrink-0 flex flex-col md:flex-row md:items-center gap-3"
                            style="border-color: var(--kp-border); background: var(--kp-surface)">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 flex-1">
                                <div>
                                    <p class="text-xs" style="color: var(--kp-muted)">Gross Hauling</p>
                                    <p class="text-lg font-bold font-mono tabular" x-text="formatNumber(grossAmount, 2)"></p>
                                </div>
                                <div>
                                    <p class="text-xs" style="color: var(--kp-muted)">Total Add</p>
                                    <p class="text-lg font-bold font-mono tabular" x-text="formatNumber(totalOf('add_items'), 2)"></p>
                                </div>
                                <div>
                                    <p class="text-xs" style="color: var(--kp-muted)">Total Less</p>
                                    <p class="text-lg font-bold font-mono tabular" x-text="formatNumber(totalOf('less_items'), 2)"></p>
                                </div>
                                <div class="rounded-md px-3 py-1.5" style="background: var(--kp-surface-2)">
                                    <p class="text-xs font-semibold" style="color: var(--kp-forest)">Net Hauling Liquidation</p>
                                    <p class="text-xl font-bold font-mono tabular" style="color: var(--kp-forest)" x-text="formatNumber(netAmount, 2)"></p>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 shrink-0">
                                <button type="button" @click="showModal = false" class="kp-btn kp-btn-secondary">Cancel</button>
                                <button type="submit" :disabled="saving" class="kp-btn kp-btn-primary disabled:opacity-50">
                                    <i class="fas fa-save mr-1.5"></i>
                                    <span x-text="saving ? 'Saving…' : 'Save Liquidation'"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            function liquidationData() {
                const DEFAULT_ADD_ROWS = ['Adjustment'];
                const DEFAULT_LESS_ROWS = ['EWT', 'MIS.', 'Fuel Account', 'Cash Adv.', 'Cable Sling/Tire/Battery'];
                const EWT_RATE = 0.02;  // EWT   = Gross Hauling x 2%
                const MIS_RATE = 1.5;   // MIS.  = Tonnage x ₱1.50 per ton

                return {
                    hauler: @json($hauler),
                    // Defaults of a new entry: the hauler's latest crop year, week and rate
                    latest: @json($latest),
                    // Entries come from the server in batches of 100 as the table scrolls (kpFeed);
                    // filters, search and sorting run in the database
                    feed: null,
                    searchTimer: null,
                    cropYears: @json($cropYears),
                    weeks: @json($weeks),
                    planters: @json($planters),
                    search: '',
                    cropYearFilter: 'all',
                    weekFilter: 'all',
                    // No column picked = server order (latest crop year / week first)
                    sortField: null,
                    sortDirection: 'asc',
                    columns: [
                        { key: 'period', label: 'Crop Year / Week' },
                        { key: 'week_ending', label: 'Week Ending' },
                        { key: 'payee_name', label: 'Payee' },
                        { key: 'cv_no', label: 'CV No.' },
                        { key: 'truck_code', label: 'Plate #' },
                        { key: 'tonnage', label: 'Tonnage', numeric: true },
                        { key: 'rate_per_ton', label: 'Rate', numeric: true },
                        { key: 'gross_amount', label: 'Gross', numeric: true },
                        { key: 'total_add', label: 'Add', numeric: true },
                        { key: 'total_less', label: 'Less', numeric: true },
                        { key: 'net_amount', label: 'Net', numeric: true },
                    ],
                    showModal: false,
                    saving: false,
                    editingEntry: null,
                    form: {},
                    autoInfo: null,
                    autoRequest: 0,
                    payeeEdit: { id: null, value: '', saving: false },

                    init() {
                        this.form = this.blankForm();
                        this.feed = kpFeed('{{ route('hauling-liquidity.show', $hauler) }}', () => ({
                            crop_year: this.cropYearFilter === 'all' ? '' : this.cropYearFilter,
                            week_no: this.weekFilter === 'all' ? '' : this.weekFilter,
                            search: this.search.trim(),
                            sort: this.sortField || '', dir: this.sortDirection,
                        }));
                        this.feed.reset();
                        this.$watch('cropYearFilter', () => this.feed.reset());
                        this.$watch('weekFilter', () => this.feed.reset());
                        this.$watch('search', () => {
                            clearTimeout(this.searchTimer);
                            this.searchTimer = setTimeout(() => this.feed.reset(), 350);
                        });
                    },
                    blankForm() {
                        const latest = this.latest;
                        return {
                            crop_year: latest ? latest.crop_year : (this.cropYears[0] || ''),
                            week_no: latest ? String(latest.week_no) : '',
                            date_released: '',
                            payee_name: '',
                            cv_no: '',
                            check_no: '',
                            truck_code: '',
                            tonnage: '',
                            // Carry over the hauler's last rate since it rarely changes week to week
                            rate_per_ton: latest ? latest.rate_per_ton : '',
                            add_items: DEFAULT_ADD_ROWS.map(d => ({ description: d, amount: '0.00' })),
                            less_items: DEFAULT_LESS_ROWS.map(d => ({ description: d, amount: '' })),
                        };
                    },

                    // ---------- Helpers ----------
                    num(v) {
                        const n = parseFloat(v);
                        return isNaN(n) ? 0 : n;
                    },
                    round2(n) {
                        return Math.round((n + Number.EPSILON) * 100) / 100;
                    },
                    formatNumber(num, decimals = 2) {
                        return parseFloat(num || 0).toLocaleString('en-US', {
                            minimumFractionDigits: decimals,
                            maximumFractionDigits: decimals
                        });
                    },
                    formatDate(d) {
                        if (!d) return '—';
                        const [y, m, day] = String(d).slice(0, 10).split('-');
                        return m + '/' + day + '/' + y;
                    },
                    cropYearLabel(cy) {
                        cy = String(cy || '');
                        return /^\d{8}$/.test(cy) ? cy.slice(0, 4) + ' – ' + cy.slice(4) : cy;
                    },
                    // A payee whose name matches a planter gets that planter's Cash Adv. / Fuel deductions
                    planterCodeByName(name) {
                        const n = String(name || '').trim().replace(/\s+/g, ' ').toLowerCase();
                        const match = n && this.planters.find(p => p.planter_name.trim().toLowerCase() === n);
                        return match ? match.planter_code : '';
                    },
                    weeksFor(cropYear) {
                        return this.weeks.filter(w => w.crop_year === cropYear);
                    },
                    weekLabel(w) {
                        const short = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '?';
                        return 'Week ' + w.week_no + ' (' + short(w.week_start_date) + ' – ' + short(w.week_end_date) + ')';
                    },

                    // ---------- List ----------
                    // Rows loaded so far, already filtered and sorted by the server
                    get filteredEntries() {
                        return this.feed ? this.feed.rows : [];
                    },
                    get sortedEntries() {
                        return this.filteredEntries;
                    },
                    // How many entries match the filters (all of them, not only the loaded ones)
                    get matchingCount() {
                        return this.feed && this.feed.total !== null ? this.feed.total : 0;
                    },
                    sortBy(field) {
                        this.sortDirection = this.sortField === field && this.sortDirection === 'asc' ? 'desc' : 'asc';
                        this.sortField = field;
                        this.feed.reset();
                    },
                    // Totals of every matching entry, worked out by the server
                    sumOf(field) {
                        return this.num(((this.feed && this.feed.extra.sums) || {})[field]);
                    },
                    // Keep the stat cards in step with a saved / deleted entry without reloading the list
                    adjustSums(entry, sign) {
                        const sums = this.feed.extra.sums;
                        if (!sums || !entry) return;
                        ['tonnage', 'gross_amount', 'total_less', 'net_amount']
                            .forEach(k => sums[k] = this.num(sums[k]) + sign * this.num(entry[k]));
                    },

                    // ---------- Form ----------
                    get formWeek() {
                        return this.weeks.find(w => w.crop_year === this.form.crop_year && w.week_no === this.form.week_no) || null;
                    },
                    get grossAmount() {
                        return this.round2(this.num(this.form.tonnage) * this.num(this.form.rate_per_ton));
                    },
                    totalOf(key) {
                        return this.round2((this.form[key] || []).reduce((t, r) => t + this.num(r.amount), 0));
                    },
                    get netAmount() {
                        return this.round2(this.grossAmount + this.totalOf('add_items') - this.totalOf('less_items'));
                    },
                    // Fill "Cash Adv." (deduction weeks set on All Cash Advances) and "Fuel Account" (Fuel Allowance upload)
                    // when the payee is a planter. Runs only when the user changes payee or week, so saved amounts are never overwritten on open.
                    async loadAutoDeductions() {
                        const { crop_year, week_no } = this.form;
                        const planter_code = this.planterCodeByName(this.form.payee_name);
                        const token = ++this.autoRequest;
                        this.autoInfo = null;
                        if (!planter_code || !crop_year || !week_no) return;
                        try {
                            const r = await fetch('/hauling-liquidity/auto-deductions?' + new URLSearchParams({ planter_code, crop_year, week_no }), {
                                headers: { 'Accept': 'application/json' }
                            });
                            if (!r.ok || token !== this.autoRequest) return;
                            const d = await r.json();
                            this.setLessRow('Cash Adv.', d.cash_advance.amount);
                            this.setLessRow('Fuel Account', d.fuel.amount);
                            this.autoInfo = d;
                        } catch (e) {
                            // Leave the rows as they are; the user can still type the amounts
                        }
                    },
                    // Zero leaves the amount blank so the 0.00 shows only as a placeholder.
                    // onlyBlank keeps an amount already on the row (e.g. one saved by hand).
                    setLessRow(label, amount, onlyBlank = false) {
                        const key = s => String(s || '').toLowerCase().replace(/[^a-z]/g, '');
                        let row = this.form.less_items.find(r => key(r.description) === key(label));
                        if (!row) {
                            row = { description: label, amount: '' };
                            this.form.less_items.push(row);
                        }
                        if (onlyBlank && this.num(row.amount) !== 0) return;
                        const value = this.round2(Number(amount) || 0);
                        row.amount = value !== 0 ? value.toFixed(2) : '';
                    },
                    // EWT and MIS. follow the tonnage and gross hauling amount; the amounts stay editable
                    applyFixedDeductions(onlyBlank = false) {
                        this.setLessRow('EWT', this.grossAmount * EWT_RATE, onlyBlank);
                        this.setLessRow('MIS.', this.num(this.form.tonnage) * MIS_RATE, onlyBlank);
                    },
                    get autoInfoText() {
                        const d = this.autoInfo;
                        if (!d) return '';
                        const ca = d.cash_advance.amount > 0
                            ? 'Cash Adv. ₱' + this.formatNumber(d.cash_advance.amount, 2) + ' from ' + d.cash_advance.sources.join(', ')
                            : 'No cash advance due this week';
                        const fuel = d.fuel.rows > 0
                            ? 'Fuel ₱' + this.formatNumber(d.fuel.amount, 2) + ' from Fuel Allowance upload'
                            : 'no fuel allowance uploaded for this week';
                        return ca + ' · ' + fuel + ' — you can still edit the amounts.';
                    },
                    openAddModal() {
                        this.editingEntry = null;
                        this.autoInfo = null;
                        this.form = this.blankForm();
                        this.showModal = true;
                    },
                    editEntry(entry) {
                        const rows = type => entry.items
                            .filter(i => i.type === type)
                            // LESS rows at zero open blank, with 0.00 only as the placeholder
                            .map(i => ({ description: i.description, amount: type === 'less' && this.num(i.amount) === 0 ? '' : i.amount }));
                        this.editingEntry = entry;
                        this.autoInfo = null;
                        this.form = {
                            crop_year: entry.crop_year,
                            week_no: String(entry.week_no),
                            date_released: entry.date_released ? String(entry.date_released).slice(0, 10) : '',
                            payee_name: entry.payee_name || '',
                            cv_no: entry.cv_no || '',
                            check_no: entry.check_no || '',
                            truck_code: entry.truck_code || '',
                            tonnage: entry.tonnage,
                            rate_per_ton: entry.rate_per_ton,
                            add_items: rows('add'),
                            less_items: rows('less'),
                        };
                        // Entries from the TLS upload have no EWT / MIS. yet; fill them without touching saved amounts
                        this.applyFixedDeductions(true);
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
                    async saveEntry() {
                        // Drop rows left completely blank; keep labelled rows even at 0.00
                        const clean = rows => rows
                            .filter(r => (r.description || '').trim() !== '' || this.num(r.amount) !== 0)
                            .map(r => ({ description: (r.description || '').trim(), amount: this.num(r.amount) }));

                        const payload = {
                            ...this.form,
                            add_items: clean(this.form.add_items),
                            less_items: clean(this.form.less_items),
                        };
                        const url = this.editingEntry
                            ? '/hauling-liquidity/entries/' + this.editingEntry.id + '/update'
                            : '/hauling-liquidity/' + this.hauler.id + '/entries';

                        this.saving = true;
                        try {
                            const d = await this.post(url, payload);
                            const old = this.feed.rows.find(e => e.id === d.entry.id);
                            if (old) {
                                this.adjustSums(old, -1);
                                this.feed.replace(d.entry);
                            } else {
                                this.feed.rows.unshift(d.entry);
                                if (this.feed.total !== null) this.feed.total++;
                                this.latest = { crop_year: d.entry.crop_year, week_no: d.entry.week_no, rate_per_ton: d.entry.rate_per_ton };
                            }
                            this.adjustSums(d.entry, 1);
                            this.showModal = false;

                            const res = await Swal.fire({
                                icon: 'success',
                                title: 'Saved!',
                                text: d.message + ' · Net ₱' + this.formatNumber(d.entry.net_amount, 2),
                                showCancelButton: true,
                                confirmButtonText: '<i class="fas fa-file-pdf"></i> Preview Slip',
                                cancelButtonText: 'Close'
                            });
                            if (res.isConfirmed) this.previewSlip(d.entry);
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        } finally {
                            this.saving = false;
                        }
                    },
                    // ---------- Payee set straight from the table ----------
                    startPayee(entry) {
                        this.payeeEdit = { id: entry.id, value: '', saving: false };
                    },
                    async savePayee(entry) {
                        const name = this.payeeEdit.value.trim();
                        if (!name) return;
                        this.payeeEdit.saving = true;
                        try {
                            const d = await this.post('/hauling-liquidity/entries/' + entry.id + '/payee', { payee_name: name });
                            this.feed.replace(d.entry);
                            this.payeeEdit = { id: null, value: '', saving: false };
                            // Jump to the next entry still without a payee, so a week can be filled in one go
                            const next = this.sortedEntries.find(e => !e.payee_name);
                            if (next) this.startPayee(next);
                        } catch (e) {
                            this.payeeEdit.saving = false;
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    },
                    previewSlip(entry) {
                        window.KusugpaPdfPreview.open(
                            '/hauling-liquidity/entries/' + entry.id + '/pdf',
                            'Hauling Liquidation · ' + entry.payee_name
                        );
                    },
                    previewWeek() {
                        const params = new URLSearchParams({ crop_year: this.cropYearFilter, week_no: this.weekFilter });
                        window.KusugpaPdfPreview.open(
                            '/hauling-liquidity/' + this.hauler.id + '/pdf?' + params.toString(),
                            'Hauling Liquidation · ' + this.cropYearLabel(this.cropYearFilter) + ' Week ' + this.weekFilter
                        );
                    },
                    async deleteEntry(entry) {
                        const res = await Swal.fire({
                            icon: 'warning',
                            title: 'Delete this liquidation?',
                            text: entry.payee_name + ' · Week ' + entry.week_no + ' · ₱' + this.formatNumber(entry.net_amount, 2),
                            showCancelButton: true,
                            confirmButtonText: 'Delete',
                            confirmButtonColor: '#dc2626'
                        });
                        if (!res.isConfirmed) return;
                        try {
                            const d = await this.post('/hauling-liquidity/entries/' + entry.id + '/delete');
                            this.adjustSums(entry, -1);
                            this.feed.remove(entry.id);
                            Swal.fire({ icon: 'success', title: 'Deleted', text: d.message, timer: 1500, showConfirmButton: false });
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    }
                }
            }
        </script>
    @endpush
@endsection
