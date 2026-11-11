<!-- resources/views/prices/buy-quedan.blade.php -->
@extends('layouts.main')

@section('title', 'Buy Quedan')

@section('content')
<div x-data="buyQuedanData()" class="space-y-6 kp-fade-in">
    <!-- Page Header -->
    <header>
        <div class="kp-page-header">
            <div>
                <p class="kp-eyebrow">Commodity Procurement</p>
                <h1 class="kp-page-title mt-1">Buy Sugar Quedans</h1>
                <p class="kp-page-sub">Select and flag planter sugar quedans as purchased for cooperative or mill settlement.</p>
            </div>
            <div class="flex items-center gap-2">
                @if (auth()->user()->hasPermission('view-quedan-molasses-registry'))
                <a href="{{ route('prices.registry') }}" class="kp-btn kp-btn-secondary text-xs">
                    <i class="fas fa-list-ul mr-1.5" aria-hidden="true"></i> Quedan Registry
                </a>
                @endif
            </div>
        </div>
        <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
    </header>

    <!-- Filters Bar -->
    <div class="kp-card p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1">Crop Year</label>
                <select x-model="filters.cropYear" @change="onCropYearChange()" class="kp-input py-1.5 text-xs">
                    <option value="">Select Crop Year</option>
                    @foreach($cropYears as $cy)
                        <option value="{{ $cy }}">{{ $cy }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1">Week No</label>
                <select x-model="filters.weekNo" @change="loadData()" :disabled="!filters.cropYear" class="kp-input py-1.5 text-xs disabled:opacity-40">
                    <option value="">All Weeks</option>
                    <template x-for="w in availableWeeks" :key="w">
                        <option :value="w" x-text="'Week ' + w"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1">Week Bought</label>
                <select x-model="filters.boughtWeek" @change="loadData()" :disabled="!filters.cropYear" class="kp-input py-1.5 text-xs disabled:opacity-40">
                    <option value="">All Week Bought</option>
                    <template x-for="w in availableBoughtWeeks" :key="w.week_no">
                        <option :value="w.week_no" x-text="w.label" :selected="w.week_no === filters.boughtWeek"></option>
                    </template>
                </select>
            </div>
            <button type="button" @click="loadData()" :disabled="!filters.cropYear || loading"
                class="kp-btn kp-btn-secondary text-xs py-2 disabled:opacity-40" title="Reload the list">
                <i class="fas mr-1.5" :class="loading ? 'fa-circle-notch fa-spin' : 'fa-rotate'" aria-hidden="true"></i> Refresh
            </button>
            <div class="relative w-full sm:w-64">
                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-[#52705F] dark:text-[#8AA895] pointer-events-none" aria-hidden="true"></i>
                <input type="text" x-model="search" :disabled="!filters.cropYear"
                    class="kp-input w-full pl-7 py-1.5 text-xs disabled:opacity-40" placeholder="Search planter, code or QDN #...">
            </div>
            <div class="flex-1"></div>
            <!-- Status counts; click one to show only those rows -->
            <div class="flex items-center gap-2" x-show="stats.total > 0" x-cloak>
                <button type="button" @click="statusFilter = ''" class="kp-badge text-xs py-1 px-2.5"
                    :class="statusFilter === '' ? 'ring-2 ring-offset-1 ring-[#8AA895]' : 'opacity-70 hover:opacity-100'">
                    All: <strong class="ml-1" x-text="stats.total"></strong>
                </button>
                <button type="button" @click="statusFilter = 'pending'" class="kp-badge kp-badge--amber text-xs py-1 px-2.5"
                    :class="statusFilter === 'pending' ? 'ring-2 ring-offset-1 ring-amber-400' : 'opacity-70 hover:opacity-100'">
                    <i class="fas fa-clock" aria-hidden="true"></i> Pending: <strong class="ml-1" x-text="stats.pending"></strong>
                </button>
                <button type="button" @click="statusFilter = 'bought'" class="kp-badge kp-badge--forest text-xs py-1 px-2.5"
                    :class="statusFilter === 'bought' ? 'ring-2 ring-offset-1 ring-emerald-500' : 'opacity-70 hover:opacity-100'">
                    <i class="fas fa-check-circle" aria-hidden="true"></i> Bought: <strong class="ml-1" x-text="stats.bought"></strong>
                </button>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Strip -->
    <div x-show="selectedIds.length > 0" x-transition x-cloak 
        class="p-3.5 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] border border-[#CFDCD3] dark:border-[#203A2B] flex flex-wrap items-center gap-3">
        <span class="text-xs font-bold text-[#005B2A] dark:text-[#52C97E]" x-text="selectedIds.length + ' record(s) selected'"></span>
        <button type="button" @click="openPricePicker()" class="kp-btn kp-btn-primary text-xs py-1.5">
            <i class="fas fa-check mr-1.5" aria-hidden="true"></i> Mark as Bought
        </button>
        <button type="button" @click="bulkUpdate('pending')" class="kp-btn kp-btn-secondary text-xs py-1.5">
            <i class="fas fa-undo mr-1.5" aria-hidden="true"></i> Reset to Pending
        </button>
        <button type="button" @click="selectedIds = []" class="text-xs text-[#52705F] dark:text-[#8AA895] hover:underline ml-auto">
            Clear Selection
        </button>
    </div>

    <!-- Table Card -->
    <!-- Table Card: every row in one scrollable list -->
    <div class="kp-card overflow-hidden">
        <div class="kp-table-wrap overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
            <table class="kp-table kp-table-sticky w-full">
                <thead>
                    <tr>
                        <th class="w-10 text-center">
                            <input type="checkbox" @change="toggleAll($event)" :checked="isAllSelected" title="Select every row shown"
                                class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                        </th>
                        <th class="text-center">Status</th>
                        <th>Crop Year</th>
                        <th class="text-center">Week</th>
                        <th>Planter Code</th>
                        <th>Planter Name</th>
                        <th>QDN No</th>
                        <th class="text-right">Total Liens</th>
                        <th class="text-center">Week Bought</th>
                        <th class="text-right">Price Used</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in filteredData" :key="row.id">
                        <tr @click="toggleSelect(row.id)"
                            :class="selectedIds.includes(row.id) ? 'bg-[#EAF5ED] dark:bg-[#1A3324]' : (row.status === 'bought' ? 'bg-[#EAF5ED]/40 dark:bg-[#1A3324]/40' : '')"
                            class="cursor-pointer hover:bg-[#EAF5ED]/60 dark:hover:bg-[#1A3324]/60 transition-colors">
                            <td class="text-center" @click.stop>
                                <input type="checkbox" :checked="selectedIds.includes(row.id)" @change="toggleSelect(row.id)" class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                            </td>
                            <td class="text-center">
                                <span class="kp-badge text-xs"
                                    :class="row.status === 'bought' ? 'kp-badge--forest' : (row.status === 'rejected' ? 'kp-badge--rose' : 'kp-badge--amber')">
                                    <i class="fas" :class="row.status === 'bought' ? 'fa-check-circle' : (row.status === 'rejected' ? 'fa-times-circle' : 'fa-clock')" aria-hidden="true"></i>
                                    <span x-text="row.status === 'bought' ? 'Bought' : (row.status === 'rejected' ? 'Rejected' : 'Pending')"></span>
                                </span>
                            </td>
                            <td><span class="kp-badge kp-badge--forest text-xs font-mono" x-text="row.crop_year"></span></td>
                            <td class="text-center font-mono text-xs" x-text="row.week_no"></td>
                            <td class="font-mono text-xs text-[#52705F] dark:text-[#8AA895]" x-text="row.planter_code"></td>
                            <td class="text-xs font-semibold text-[#172B20] dark:text-[#EAF5ED]" x-text="row.planter_name"></td>
                            <td class="font-mono text-xs text-[#52705F] dark:text-[#8AA895]" x-text="row.qdn_no || '—'"></td>
                            <td class="text-xs text-right kp-num font-semibold text-[#172B20] dark:text-[#EAF5ED]" x-text="formatNum(row.total_liens, 3)"></td>
                            <td class="text-center font-mono text-xs whitespace-nowrap">
                                <span x-show="row.bought_week" class="font-semibold text-[#005B2A] dark:text-[#52C97E]" x-text="'Week ' + row.bought_week"></span>
                                <span x-show="!row.bought_week" class="text-[#8AA895]">—</span>
                            </td>
                            <td class="text-xs text-right kp-num whitespace-nowrap">
                                <template x-if="row.sold_price !== null && row.sold_price !== undefined">
                                    <span class="font-semibold text-[#005B2A] dark:text-[#52C97E]">
                                        <span class="kp-badge text-[10px] py-0 px-1.5 mr-1" x-text="row.sold_price_type || 'B'"></span>₱<span x-text="formatNum(row.sold_price)"></span>
                                    </span>
                                </template>
                                <template x-if="(row.sold_price === null || row.sold_price === undefined) && row.status === 'bought'">
                                    <span class="text-[#52705F] dark:text-[#8AA895] italic" title="Bought before sold prices were recorded: the week's prices from Price Management are used">Week's price</span>
                                </template>
                                <template x-if="(row.sold_price === null || row.sold_price === undefined) && row.status !== 'bought'">
                                    <span class="text-[#8AA895]">—</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!filters.cropYear" x-cloak>
                        <td colspan="10" class="py-12 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                            <i class="fas fa-filter text-2xl mb-2 text-[#8AA895] block"></i>
                            Select a crop year to list its quedans.
                        </td>
                    </tr>
                    <template x-if="feed && filters.cropYear">
                        @include('layouts.partials.feed-footer', ['feed' => 'feed', 'colspan' => 10, 'empty' => 'No quedans match the crop year, week, search or status.'])
                    </template>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-2.5 border-t border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] text-xs text-[#52705F] dark:text-[#8AA895]"
            x-show="data.length > 0" x-cloak
            x-text="'Showing ' + data.length.toLocaleString() + ' of ' + (feed && feed.total !== null ? feed.total.toLocaleString() : '…') + ' quedan(s) · more load as you scroll · click a row to select it · the header box selects every row shown'"></div>
    </div>

    <!-- Sold Price picker: a quedan is marked bought only at a price picked here -->
    <div x-show="showPricePicker" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak @keydown.escape.window="showPricePicker = false">
        <div class="kp-viewport-modal-layout flex items-center justify-center">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showPricePicker = false"></div>
            <div class="kp-modal kp-viewport-modal-panel relative max-w-lg w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="sold-price-title">
                <div class="pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <h3 id="sold-price-title" class="text-base font-bold text-[#172B20] dark:text-[#EAF5ED]">Select Sold Price</h3>
                    <p class="text-xs mt-1 text-[#52705F] dark:text-[#8AA895]">
                        <span x-text="selectedIds.length"></span> quedan(s) of crop year <span class="font-mono" x-text="filters.cropYear"></span>
                        <template x-if="selectedWeeks().length"><span> · Week <span x-text="selectedWeeks().join(', ')"></span></span></template>.
                        The Check Voucher and proceeds reports use this price for these quedans.
                    </p>
                </div>

                <div class="space-y-4">
                    <!-- 1. Crop Year -->
                    <div>
                        <label for="spCropYear" class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">1. Crop Year <span class="text-rose-600">*</span></label>
                        <select id="spCropYear" x-model="picker.cropYear" @change="onPickerCropYear()" class="kp-input w-full">
                            <option value="">Select crop year</option>
                            @foreach($cropYears as $cy)
                                <option value="{{ $cy }}">{{ $cy }}</option>
                            @endforeach
                        </select>
                        <p x-show="picker.cropYear && picker.cropYear !== String(filters.cropYear)" x-cloak class="text-[11px] mt-1 text-amber-700 dark:text-amber-300">
                            <i class="fas fa-triangle-exclamation mr-0.5" aria-hidden="true"></i> The selected quedans are of crop year <span x-text="filters.cropYear"></span>; a price of another crop year is refused.
                        </p>
                    </div>

                    <!-- 2. Week -->
                    <div>
                        <label for="spWeek" class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">2. Week No <span class="text-rose-600">*</span></label>
                        <select id="spWeek" x-model="picker.weekNo" @change="onPickerWeek()" :disabled="!picker.cropYear || picker.loadingWeeks" class="kp-input w-full disabled:opacity-40">
                            <option value="" x-text="picker.loadingWeeks ? 'Loading weeks…' : 'Select week'"></option>
                            <template x-for="w in picker.weeks" :key="w.week_no">
                                <option :value="w.week_no" :selected="w.week_no === picker.weekNo"
                                    x-text="'Week ' + w.week_no + (w.prices ? ' · ' + w.prices + (w.prices === 1 ? ' price' : ' prices') : ' · no price')
                                        + (selectedWeeks().includes(w.week_no) ? '  (selected quedans)' : '')"></option>
                            </template>
                        </select>
                    </div>

                    <!-- 3. Price: searchable dropdown, loaded from the server for the week -->
                    <div class="relative" @click.outside="picker.open = false">
                        <label for="spPrice" class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">3. Sold Price <span class="text-rose-600">*</span></label>
                        <button type="button" id="spPrice" @click="togglePriceList()" :disabled="!picker.weekNo"
                            class="kp-input w-full flex items-center justify-between text-left disabled:opacity-40"
                            :aria-expanded="picker.open" aria-haspopup="listbox">
                            <span x-show="picker.selected" class="flex items-center gap-2">
                                <span class="kp-badge kp-badge--forest text-xs" x-text="picker.selected ? 'Type ' + picker.selected.type : ''"></span>
                                <span class="font-mono font-bold" x-text="picker.selected ? '₱' + formatNum(picker.selected.price) : ''"></span>
                            </span>
                            <span x-show="!picker.selected" class="text-[#8AA895]" x-text="picker.weekNo ? 'Select price' : 'Select the week first'"></span>
                            <i class="fas fa-chevron-down text-[10px] text-[#8AA895]" aria-hidden="true"></i>
                        </button>

                        <div x-show="picker.open" x-cloak x-transition.opacity
                            class="mt-1 w-full rounded-lg border border-[#CFDCD3] dark:border-[#203A2B] bg-white dark:bg-[#132A1D] shadow-lg">
                            <div class="p-2 border-b border-[#E3EBE5] dark:border-[#203A2B] relative">
                                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[10px] text-[#8AA895] pointer-events-none" aria-hidden="true"></i>
                                <input type="text" x-ref="priceSearch" x-model="picker.search" @input="searchPrices()"
                                    @keydown.escape.stop="picker.open = false"
                                    placeholder="Search type or amount (e.g. B, 2550)..." class="kp-input w-full pl-7 py-1.5 text-xs">
                            </div>
                            <ul class="max-h-56 overflow-y-auto py-1" role="listbox">
                                <li x-show="picker.loadingPrices" class="px-3 py-3 text-xs text-[#52705F] dark:text-[#8AA895]">
                                    <i class="fas fa-circle-notch fa-spin mr-1" aria-hidden="true"></i> Loading prices…
                                </li>
                                <li x-show="!picker.loadingPrices && picker.prices.length === 0" class="px-3 py-3 text-xs text-amber-700 dark:text-amber-300">
                                    <span x-text="picker.search ? 'No price matches the search.' : 'No quedan price is set for this week. Add one in Price Management.'"></span>
                                </li>
                                <template x-for="p in picker.prices" :key="p.id">
                                    <li role="option" :aria-selected="soldPriceId === p.id" @click="choosePrice(p)"
                                        class="flex items-center gap-3 px-3 py-2 cursor-pointer text-sm"
                                        :class="soldPriceId === p.id ? 'bg-[#EAF5ED] dark:bg-[#1A3324]' : 'hover:bg-[#F7FAF8] dark:hover:bg-[#1A3324]/60'">
                                        <span class="kp-badge kp-badge--forest text-xs" x-text="'Type ' + p.type"></span>
                                        <span class="text-xs text-[#52705F] dark:text-[#8AA895]" x-text="'Week ' + p.week_no"></span>
                                        <span class="ml-auto font-mono font-bold text-[#172B20] dark:text-[#EAF5ED]" x-text="'₱' + formatNum(p.price)"></span>
                                        <i x-show="soldPriceId === p.id" class="fas fa-check text-xs text-[#005B2A] dark:text-[#52C97E]" aria-hidden="true"></i>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 mt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <button type="button" @click="showPricePicker = false" class="kp-btn kp-btn-secondary text-xs">Cancel</button>
                    <button type="button" @click="confirmBought()" :disabled="!soldPriceId || saving"
                        class="kp-btn kp-btn-primary text-xs disabled:opacity-40">
                        <i class="fas mr-1.5" :class="saving ? 'fa-circle-notch fa-spin' : 'fa-check'" aria-hidden="true"></i>
                        <span x-text="saving ? 'Saving…' : 'Mark as Bought'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function buyQuedanData() {
        return {
            filters: { cropYear: '', weekNo: '', boughtWeek: '' },
            selectedIds: [],
            search: '',
            statusFilter: '',
            // Quedans come from the server in batches of 100 as the list scrolls (kpFeed);
            // search and the Pending / Bought filter run in the database
            feed: null,
            searchTimer: null,
            // Sold Price picker: crop year → week → price, loaded from the server as each is picked
            picker: {
                cropYear: '', weekNo: '', weeks: [], prices: [], search: '', selected: null,
                open: false, loadingWeeks: false, loadingPrices: false, weeksRequest: 0, pricesRequest: 0, searchTimer: null,
            },
            showPricePicker: false,
            soldPriceId: null,
            saving: false,

            init() {
                this.feed = kpFeed('{{ route('registry.data') }}', () => ({
                    type: 'quedan', crop_year: this.filters.cropYear, week_no: this.filters.weekNo,
                    bought_week: this.filters.boughtWeek, search: this.search.trim(), status: this.statusFilter,
                }));
                this.$watch('statusFilter', () => this.loadData());
                this.$watch('search', () => {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = setTimeout(() => this.loadData(), 400);
                });
            },

            get data() { return this.feed ? this.feed.rows : []; },
            get filteredData() { return this.data; },
            get loading() { return this.feed ? this.feed.loading : false; },
            // Counts of the crop year / week / search, whatever the status filter
            get stats() { return (this.feed && this.feed.extra.stats) || { total: 0, bought: 0, pending: 0 }; },
            get availableWeeks() { return (this.feed && this.feed.extra.weeks) || []; },
            // Crop year weeks with dates, for the "Week Bought" filter (the week of the sold price picked)
            get availableBoughtWeeks() { return (this.feed && this.feed.extra.bought_weeks) || []; },
            get isAllSelected() {
                return this.filteredData.length > 0 && this.filteredData.every(r => this.selectedIds.includes(r.id));
            },

            // Picking a crop year lists its weeks and loads its quedans right away
            onCropYearChange() {
                this.filters.weekNo = '';
                this.filters.boughtWeek = '';
                this.loadData();
            },

            loadData() {
                this.selectedIds = [];
                if (!this.filters.cropYear) {
                    this.feed.request++;
                    this.feed.rows = []; this.feed.extra = {}; this.feed.total = null; this.feed.hasMore = false;
                    return;
                }
                return this.feed.reset();
            },

            toggleSelect(id) {
                const idx = this.selectedIds.indexOf(id);
                idx > -1 ? this.selectedIds.splice(idx, 1) : this.selectedIds.push(id);
            },
            // Selects every row currently shown (after search and status filter)
            toggleAll(e) {
                const shownIds = this.filteredData.map(r => r.id);
                if (e.target.checked) {
                    this.selectedIds = [...new Set([...this.selectedIds, ...shownIds])];
                } else {
                    this.selectedIds = this.selectedIds.filter(id => !shownIds.includes(id));
                }
            },
            // Weeks of the selected quedans
            selectedWeeks() {
                const weeks = this.data.filter(r => this.selectedIds.includes(r.id)).map(r => String(r.week_no));
                return [...new Set(weeks)].sort((a, b) => Number(a) - Number(b));
            },
            async fetchPriceOptions(params) {
                const r = await fetch('{{ route('quedan-buy.prices') }}?' + new URLSearchParams(params), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!r.ok) throw new Error('Could not load prices (HTTP ' + r.status + ')');
                return r.json();
            },
            clearPickedPrice() {
                // A price list still loading for the old crop year / week is dropped
                this.picker.pricesRequest++;
                this.picker.loadingPrices = false;
                clearTimeout(this.picker.searchTimer);
                this.soldPriceId = null;
                this.picker.selected = null;
                this.picker.prices = [];
                this.picker.search = '';
                this.picker.open = false;
            },
            // Opens on the page's crop year and, when the selection is one week, that week
            async openPricePicker() {
                if (!this.selectedIds.length) return;
                const weeks = this.selectedWeeks();
                this.picker.cropYear = String(this.filters.cropYear || '');
                this.picker.weekNo = '';
                this.clearPickedPrice();
                this.showPricePicker = true;
                await this.loadPickerWeeks(weeks.length === 1 ? weeks[0] : '');
            },
            async onPickerCropYear() {
                this.picker.weekNo = '';
                this.clearPickedPrice();
                await this.loadPickerWeeks('');
            },
            async loadPickerWeeks(preselectWeek) {
                this.picker.weeks = [];
                if (!this.picker.cropYear) return;
                const req = ++this.picker.weeksRequest;
                this.picker.loadingWeeks = true;
                try {
                    const d = await this.fetchPriceOptions({ crop_year: this.picker.cropYear });
                    if (req !== this.picker.weeksRequest) return;
                    this.picker.weeks = d.weeks || [];
                    if (preselectWeek && this.picker.weeks.some(w => w.week_no === String(preselectWeek))) {
                        this.picker.weekNo = String(preselectWeek);
                        await this.loadPickerPrices(true);
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Prices', text: e.message });
                } finally {
                    if (req === this.picker.weeksRequest) this.picker.loadingWeeks = false;
                }
            },
            async onPickerWeek() {
                this.clearPickedPrice();
                if (this.picker.weekNo) await this.loadPickerPrices(true);
            },
            // autoPick: a week with a single price picks it right away
            async loadPickerPrices(autoPick = false) {
                const req = ++this.picker.pricesRequest;
                this.picker.loadingPrices = true;
                try {
                    const d = await this.fetchPriceOptions({
                        crop_year: this.picker.cropYear, week_no: this.picker.weekNo, search: this.picker.search.trim(),
                    });
                    if (req !== this.picker.pricesRequest) return;
                    this.picker.prices = d.prices || [];
                    if (autoPick && this.picker.prices.length === 1) this.choosePrice(this.picker.prices[0]);
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Prices', text: e.message });
                } finally {
                    if (req === this.picker.pricesRequest) this.picker.loadingPrices = false;
                }
            },
            searchPrices() {
                clearTimeout(this.picker.searchTimer);
                this.picker.searchTimer = setTimeout(() => this.loadPickerPrices(false), 300);
            },
            togglePriceList() {
                if (!this.picker.weekNo) return;
                this.picker.open = !this.picker.open;
                if (this.picker.open) this.$nextTick(() => this.$refs.priceSearch && this.$refs.priceSearch.focus());
            },
            choosePrice(p) {
                this.soldPriceId = p.id;
                this.picker.selected = p;
                this.picker.open = false;
            },
            async confirmBought() {
                if (!this.soldPriceId) return;
                this.saving = true;
                try {
                    if (await this.bulkUpdate('bought', { quedan_price_id: this.soldPriceId })) {
                        this.showPricePicker = false;
                    }
                } finally {
                    this.saving = false;
                }
            },
            async bulkUpdate(status, extra = {}) {
                if (!this.selectedIds.length) return false;
                const r = await fetch('{{ route('quedans.bulk-update') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ ids: this.selectedIds, status, ...extra })
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) {
                    const firstError = d.errors ? Object.values(d.errors)[0][0] : null;
                    Swal.fire({ icon: 'error', title: 'Not Updated', text: firstError || d.message || 'Request failed' });
                    return false;
                }
                Swal.fire({ icon: 'success', title: 'Status Updated', text: d.message, timer: 2500, showConfirmButton: false });
                this.selectedIds = [];
                await this.loadData();
                return true;
            },
            formatNum(num, decimals = 2) {
                return parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            }
        }
    }
</script>
@endpush
@endsection