<!-- resources/views/menu/printVoucher.blade.php -->
@extends('layouts.main')

@section('title', 'Print Vouchers')

@section('content')
    <div x-data="voucherFilter()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Disbursement & Settlement</p>
                    <h1 class="kp-page-title mt-1">Print Vouchers</h1>
                    <p class="kp-page-sub">Generate and print check vouchers and remittance statements for planters.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="resetFilters()" class="kp-btn kp-btn-secondary text-xs">
                        <i class="fas fa-undo mr-1.5" aria-hidden="true"></i> Reset Filters
                    </button>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Filter Card -->
        <div class="kp-card p-6 overflow-visible">
            <div class="flex items-center gap-2.5 pb-4 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                    <i class="fas fa-filter" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Voucher Generation Criteria</h2>
                    <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Select crop period and target planters to compile check disbursements.</p>
                </div>
            </div>

            <form @submit.prevent="loadVoucher" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Crop Year -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-calendar-alt text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Crop Year <span class="text-rose-600">*</span>
                        </label>
                        <select x-model="filters.crop_year" @change="onFilterChange()"
                            class="kp-input w-full">
                            <option value="">Select Crop Year</option>
                            @foreach ($cropYear as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Week From -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-play-circle text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Week From <span class="text-rose-600">*</span>
                        </label>
                        <select x-model="filters.week_from" @change="onFilterChange()"
                            class="kp-input w-full">
                            <option value="">Select Week</option>
                            @foreach ($weekNos as $week)
                                <option value="{{ $week }}">Week {{ $week }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Week To -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-stop-circle text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Week To <span class="text-rose-600">*</span>
                        </label>
                        <select x-model="filters.week_to" @change="onFilterChange()"
                            class="kp-input w-full">
                            <option value="">Select Week</option>
                            @foreach ($weekNos as $week)
                                <option value="{{ $week }}">Week {{ $week }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Planter Selection -->
                    <div class="relative">
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-user text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Planter Scope
                        </label>
                        <div class="relative">
                            <button
                                @click="togglePlanterDropdown()"
                                type="button"
                                :disabled="!canLoadPlanters"
                                class="kp-input w-full text-left flex items-center justify-between disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="truncate font-medium" x-text="planterSelectedText"></span>
                                <i class="fas fa-chevron-down text-[#52705F] text-xs transition-transform duration-200"
                                    :class="{ 'rotate-180': planterOpen }"></i>
                            </button>

                            <div x-show="planterOpen" @click.away="planterOpen = false" x-transition x-cloak
                                class="absolute z-50 mt-1.5 w-full bg-white dark:bg-[#13231B] border border-[#CFDCD3] dark:border-[#203A2B] rounded-xl shadow-xl max-h-64 overflow-y-auto left-0">
                                <!-- Loading State -->
                                <div x-show="planterLoading" class="p-4 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                                    <i class="fas fa-circle-notch fa-spin mr-2 text-[#005B2A] dark:text-[#52C97E]"></i> Loading planter roster...
                                </div>

                                <!-- No Results -->
                                <div x-show="!planterLoading && planters.length === 0"
                                    class="p-4 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                                    No planters left to generate for the selected period.
                                    <span class="block mt-1">Planters already in a generated group are not listed; delete their group to generate them again.</span>
                                </div>

                                <!-- Planter List -->
                                <div x-show="!planterLoading && planters.length > 0">
                                    <div class="sticky top-0 bg-[#F7FAF8] dark:bg-[#1A3324] px-3 py-2 border-b border-[#E3EBE5] dark:border-[#203A2B] z-10 space-y-2 text-xs">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-[#52705F] dark:text-[#8AA895] pointer-events-none" aria-hidden="true"></i>
                                            <input type="text" x-model="planterSearch" x-ref="planterSearch" @keydown.enter.prevent
                                                class="kp-input w-full pl-7 py-1 text-xs" placeholder="Search planter name or code...">
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <button @click="selectAllPlanters()" type="button"
                                                class="font-semibold text-[#005B2A] dark:text-[#52C97E] hover:underline"
                                                x-text="planterSearch.trim() ? 'Select Matches' : 'Select All'"></button>
                                            <button @click="clearAllPlanters()" type="button"
                                                class="font-medium text-[#52705F] dark:text-[#8AA895] hover:text-rose-600">Clear</button>
                                        </div>
                                    </div>
                                    <div x-show="filteredPlanters.length === 0" class="p-4 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                                        No planters match your search
                                    </div>
                                    <div class="divide-y divide-[#E3EBE5] dark:divide-[#203A2B]">
                                        <template x-for="planter in filteredPlanters" :key="planter.planter_code">
                                            <label class="flex items-center px-3.5 py-2 hover:bg-[#EAF5ED] dark:hover:bg-[#1A3324] cursor-pointer transition">
                                                <input type="checkbox" :value="planter.planter_code"
                                                    x-model="filters.planter_names"
                                                    class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A] mr-2.5">
                                                <span class="text-xs text-[#172B20] dark:text-[#EAF5ED] truncate font-medium"
                                                    x-text="planter.planter_name + ' (' + planter.planter_code + ')'"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="text-xs text-[#52705F] dark:text-[#8AA895] flex items-center gap-1.5">
                        <i class="fas fa-info-circle text-[#087A3D]" aria-hidden="true"></i>
                        <span>Generate saves a group below with the planters not yet generated for these weeks.</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <button type="button" @click="resetFilters()"
                            class="kp-btn kp-btn-secondary">
                            <i class="fas fa-undo mr-1.5" aria-hidden="true"></i> Reset
                        </button>
                        <button type="submit"
                            class="kp-btn kp-btn-primary shadow-sm"
                            :disabled="!filters.crop_year || !filters.week_from || !filters.week_to || generating">
                            <i class="fas mr-1.5" :class="generating ? 'fa-circle-notch fa-spin' : 'fa-file-pdf'" aria-hidden="true"></i> Generate Voucher
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Generated groups: one per Generate, stamped with its date and time -->
        <div class="kp-card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Generated Voucher Groups</h2>
                        <p class="text-xs text-[#52705F] dark:text-[#8AA895]">
                            A planter in a group is not generated again for those weeks. Delete the group to generate them again.
                        </p>
                    </div>
                </div>
                <span class="text-xs text-[#52705F] dark:text-[#8AA895]"
                    x-text="filters.crop_year ? 'Crop Year ' + filters.crop_year : 'All crop years'"></span>
            </div>

            <div x-show="batchesLoading" class="p-8 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                <i class="fas fa-circle-notch fa-spin mr-2 text-[#005B2A] dark:text-[#52C97E]"></i> Loading groups...
            </div>

            <div x-show="!batchesLoading && batches.length === 0" class="py-10 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center mx-auto mb-3 text-xl">
                    <i class="fas fa-print" aria-hidden="true"></i>
                </div>
                <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] mb-1">No Generated Groups</h3>
                <p class="text-xs text-[#52705F] dark:text-[#8AA895] max-w-md mx-auto leading-relaxed">
                    Select a crop year, week range, and optionally target specific planters, then click <strong>"Generate Voucher"</strong>.
                </p>
            </div>

            <div x-show="!batchesLoading && batches.length > 0" class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-left text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <th class="py-2 pr-3 font-semibold">Generated</th>
                            <th class="py-2 pr-3 font-semibold">Crop Year</th>
                            <th class="py-2 pr-3 font-semibold">Week</th>
                            <th class="py-2 pr-3 font-semibold text-right">Planters</th>
                            <th class="py-2 pr-3 font-semibold text-right">Net Paid</th>
                            <th class="py-2 pr-3 font-semibold">Generated By</th>
                            <th class="py-2 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E3EBE5] dark:divide-[#203A2B]">
                        <template x-for="b in batches" :key="b.id">
                            <tr class="align-middle" :class="b.id === highlightId ? 'bg-[#EAF5ED] dark:bg-[#1A3324]' : ''">
                                <td class="py-2 pr-3 font-semibold text-[#172B20] dark:text-[#EAF5ED] whitespace-nowrap" x-text="b.generated_at"></td>
                                <td class="py-2 pr-3 whitespace-nowrap" x-text="b.crop_year"></td>
                                <td class="py-2 pr-3 whitespace-nowrap" x-text="weekLabel(b)"></td>
                                <td class="py-2 pr-3 text-right whitespace-nowrap">
                                    <span x-text="b.planters"></span>
                                    <span x-show="b.receivers > 0" class="block text-[11px] text-[#087A3D] dark:text-[#52C97E]"
                                        x-text="b.receivers + ' authorized'"></span>
                                </td>
                                <td class="py-2 pr-3 text-right whitespace-nowrap font-semibold" x-text="money(b.net_paid)"></td>
                                <td class="py-2 pr-3 whitespace-nowrap text-[#52705F] dark:text-[#8AA895]" x-text="b.generated_by || '-'"></td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" @click="openBatch(b)" class="kp-btn kp-btn-secondary px-2.5 py-1 text-xs">
                                            <i class="fas fa-folder-open mr-1" aria-hidden="true"></i> Open
                                        </button>
                                        <button type="button" @click="printBatch(b)" class="kp-btn kp-btn-primary px-2.5 py-1 text-xs">
                                            <i class="fas fa-print mr-1" aria-hidden="true"></i> Print All
                                        </button>
                                        <button type="button" @click="deleteBatch(b)" title="Delete group"
                                            class="kp-btn kp-btn-secondary px-2.5 py-1 text-xs hover:text-rose-600">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Opened group: its planters, each with the person authorized to receive the voucher -->
        <div x-show="current" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="current = null"
            @keydown.escape.window="if (!Swal.isVisible() && !document.getElementById('kpGlobalPdfPreview')?.open) current = null">
            <div class="kp-card w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="flex flex-wrap items-start justify-between gap-3 p-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div>
                        <h2 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Voucher Group</h2>
                        <p class="text-xs text-[#52705F] dark:text-[#8AA895]" x-show="current"
                            x-text="current ? current.generated_at + ' · Crop Year ' + current.crop_year + ' · ' + weekLabel(current) + ' · ' + items.length + ' planter(s)' : ''"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-[#52705F] dark:text-[#8AA895] pointer-events-none" aria-hidden="true"></i>
                            <input type="text" x-model="itemSearch" class="kp-input pl-7 py-1.5 text-xs w-52" placeholder="Search planter or receiver...">
                        </div>
                        <button type="button" @click="printBatch(current)" class="kp-btn kp-btn-primary text-xs">
                            <i class="fas fa-print mr-1.5" aria-hidden="true"></i> Print All
                        </button>
                        <button type="button" @click="current = null" class="kp-btn kp-btn-secondary text-xs" title="Close">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-y-auto p-5">
                    <div x-show="itemsLoading" class="p-8 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                        <i class="fas fa-circle-notch fa-spin mr-2 text-[#005B2A] dark:text-[#52C97E]"></i> Loading planters...
                    </div>
                    <table x-show="!itemsLoading" class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider border-b border-[#E3EBE5] dark:border-[#203A2B]">
                                <th class="py-2 pr-3 font-semibold">Code</th>
                                <th class="py-2 pr-3 font-semibold">Planter</th>
                                <th class="py-2 pr-3 font-semibold text-right">Net Paid</th>
                                <th class="py-2 pr-3 font-semibold">Authorized to Receive</th>
                                <th class="py-2 font-semibold text-right">Voucher</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E3EBE5] dark:divide-[#203A2B]">
                            <template x-for="v in filteredItems" :key="v.id">
                                <tr class="align-middle">
                                    <td class="py-2 pr-3 text-[#52705F] dark:text-[#8AA895] whitespace-nowrap" x-text="v.planter_code"></td>
                                    <td class="py-2 pr-3 text-[#172B20] dark:text-[#EAF5ED] font-medium">
                                        <span x-text="v.planter_name"></span>
                                        <span x-show="v.receiver_name" class="block text-[11px] text-[#087A3D] dark:text-[#52C97E]">
                                            <i class="fas fa-user-check mr-1" aria-hidden="true"></i>Voucher prints <b x-text="v.receiver_name"></b>
                                        </span>
                                    </td>
                                    <td class="py-2 pr-3 text-right whitespace-nowrap font-semibold"
                                        :class="v.net_paid < 0 ? 'text-rose-600' : 'text-[#172B20] dark:text-[#EAF5ED]'"
                                        x-text="money(v.net_paid)"></td>
                                    <td class="py-2 pr-3 min-w-[16rem]">
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" x-model="v.draft" maxlength="255"
                                                @keydown.enter.prevent="saveReceiver(v)"
                                                class="kp-input w-full py-1 text-xs" placeholder="Planter receives (leave blank)">
                                            <button type="button" @click="saveReceiver(v)" title="Save authorized receiver"
                                                x-show="(v.draft || '').trim() !== (v.receiver_name || '')"
                                                :disabled="v.saving"
                                                class="kp-btn kp-btn-primary px-2.5 py-1 text-xs">
                                                <i class="fas" :class="v.saving ? 'fa-circle-notch fa-spin' : 'fa-check'" aria-hidden="true"></i>
                                            </button>
                                            <button type="button" @click="v.draft = ''; saveReceiver(v)" title="Remove authorized receiver"
                                                x-show="v.receiver_name && (v.draft || '').trim() === v.receiver_name"
                                                :disabled="v.saving"
                                                class="kp-btn kp-btn-secondary px-2.5 py-1 text-xs hover:text-rose-600">
                                                <i class="fas fa-times" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right whitespace-nowrap">
                                        <button type="button" @click="printOne(v)" class="kp-btn kp-btn-secondary px-2.5 py-1 text-xs">
                                            <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i> Print
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function voucherFilter() {
                const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
                const jsonHeaders = () => ({ 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() });
                const batchUrl = (id) => '{{ url('voucher/batches') }}/' + id;

                return {
                    // Main filters
                    filters: {
                        crop_year: '{{ $selectedCropYear ?? '' }}',
                        week_from: '{{ $weekFrom ?? '' }}',
                        week_to: '{{ $weekTo ?? '' }}',
                        planter_names: []
                    },
                    generating: false,

                    // Planter dropdown
                    planterOpen: false,
                    planters: [],
                    planterLoading: false,
                    planterLoaded: false,
                    planterSearch: '',

                    // Generated groups
                    batches: [],
                    batchesLoading: false,
                    highlightId: null,
                    current: null,
                    items: [],
                    itemsLoading: false,
                    itemSearch: '',

                    init() {
                        this.fetchBatches();
                    },

                    get filteredPlanters() {
                        const term = this.planterSearch.trim().toLowerCase();
                        if (!term) return this.planters;
                        return this.planters.filter(p =>
                            String(p.planter_name || '').toLowerCase().includes(term) ||
                            String(p.planter_code || '').toLowerCase().includes(term)
                        );
                    },

                    get filteredItems() {
                        const term = this.itemSearch.trim().toLowerCase();
                        if (!term) return this.items;
                        return this.items.filter(v =>
                            String(v.planter_name).toLowerCase().includes(term) ||
                            String(v.planter_code).toLowerCase().includes(term) ||
                            String(v.receiver_name).toLowerCase().includes(term)
                        );
                    },

                    get canLoadPlanters() {
                        return this.filters.crop_year && this.filters.week_from && this.filters.week_to;
                    },

                    get planterSelectedText() {
                        if (!this.filters.planter_names || this.filters.planter_names.length === 0) {
                            if (this.planterLoaded && this.planters.length === 0) return 'No planters left to generate';
                            return this.planterLoaded ? 'All remaining planters (' + this.planters.length + ')' : 'All Planters';
                        }
                        return this.filters.planter_names.length + ' planter(s) selected';
                    },

                    money(value) {
                        return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },

                    weekLabel(b) {
                        return b.week_from == b.week_to ? 'Week ' + b.week_from : 'Week ' + b.week_from + ' - ' + b.week_to;
                    },

                    onFilterChange() {
                        this.filters.planter_names = [];
                        this.planters = [];
                        this.planterLoaded = false;
                        this.planterSearch = '';
                        this.fetchBatches();
                    },

                    // After a group is generated or deleted, the planters left to generate change
                    refreshPlanters() {
                        this.filters.planter_names = [];
                        this.planters = [];
                        this.planterLoaded = false;
                        if (this.canLoadPlanters) this.fetchPlanters();
                    },

                    togglePlanterDropdown() {
                        this.planterOpen = !this.planterOpen;
                        if (this.planterOpen && !this.planterLoaded && this.canLoadPlanters) {
                            this.fetchPlanters();
                        }
                        if (this.planterOpen) this.$nextTick(() => this.$refs.planterSearch?.focus());
                    },

                    async fetchPlanters() {
                        if (!this.canLoadPlanters) {
                            return;
                        }

                        this.planterLoading = true;
                        try {
                            const params = new URLSearchParams({
                                crop_year: this.filters.crop_year,
                                week_from: this.filters.week_from,
                                week_to: this.filters.week_to,
                                // Only planters not yet in a generated group for these weeks
                                exclude_generated: 1,
                            });

                            const response = await fetch('{{ route('get.planter.names') }}?' + params.toString());
                            if (response.ok) {
                                const data = await response.json();
                                this.planters = Array.isArray(data) ? data : [];
                            } else {
                                this.planters = [];
                            }
                        } catch (error) {
                            console.error('Error fetching planters:', error);
                            this.planters = [];
                        } finally {
                            this.planterLoaded = true;
                            this.planterLoading = false;
                        }
                    },

                    selectAllPlanters() {
                        // With a search term, add only the matches to what is already selected
                        const codes = this.filteredPlanters.map(p => p.planter_code);
                        this.filters.planter_names = [...new Set([...this.filters.planter_names, ...codes])];
                    },

                    clearAllPlanters() {
                        this.filters.planter_names = [];
                    },

                    async fetchBatches() {
                        this.batchesLoading = true;
                        try {
                            const params = new URLSearchParams();
                            if (this.filters.crop_year) params.append('crop_year', this.filters.crop_year);
                            const response = await fetch('{{ route('voucher.batches.index') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                            const data = await response.json().catch(() => null);
                            this.batches = response.ok && Array.isArray(data) ? data : [];
                        } catch (error) {
                            console.error('Error loading voucher groups:', error);
                            this.batches = [];
                        } finally {
                            this.batchesLoading = false;
                        }
                    },

                    // Generate saves a group of the planters not yet generated for these weeks
                    async loadVoucher() {
                        if (!this.filters.crop_year || !this.filters.week_from || !this.filters.week_to) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Incomplete Filters',
                                text: 'Please select crop year and week range.',
                                confirmButtonColor: '#005B2A'
                            });
                            return;
                        }
                        if (this.generating) return;

                        this.generating = true;
                        try {
                            const response = await fetch('{{ route('voucher.batches.store') }}', {
                                method: 'POST',
                                headers: jsonHeaders(),
                                body: JSON.stringify({
                                    crop_year: this.filters.crop_year,
                                    week_from: this.filters.week_from,
                                    week_to: this.filters.week_to,
                                    planter_name: this.filters.planter_names,
                                }),
                            });
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                Swal.fire({ icon: 'info', title: 'Nothing Generated', text: data.message || 'Failed to generate vouchers.', confirmButtonColor: '#005B2A' });
                                return;
                            }
                            await this.fetchBatches();
                            this.refreshPlanters();
                            this.highlightId = data.batch.id;
                            Swal.fire({ icon: 'success', title: 'Vouchers Generated', text: data.message, confirmButtonColor: '#005B2A' });
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Generate Failed', text: error.message, confirmButtonColor: '#005B2A' });
                        } finally {
                            this.generating = false;
                        }
                    },

                    async openBatch(b) {
                        this.current = b;
                        this.items = [];
                        this.itemSearch = '';
                        this.itemsLoading = true;
                        try {
                            const response = await fetch(batchUrl(b.id), { headers: { 'Accept': 'application/json' } });
                            const data = await response.json().catch(() => null);
                            if (!response.ok || !data) throw new Error('Could not load the group.');
                            this.items = data.items.map(v => ({ ...v, draft: v.receiver_name || '', saving: false }));
                        } catch (error) {
                            this.current = null;
                            Swal.fire({ icon: 'error', title: 'Error', text: error.message, confirmButtonColor: '#005B2A' });
                        } finally {
                            this.itemsLoading = false;
                        }
                    },

                    printBatch(b) {
                        if (!b) return;
                        window.KusugpaPdfPreview.open('{{ route('voucher.pdf') }}?batch_id=' + b.id, 'Vouchers - ' + b.generated_at);
                    },

                    printOne(v) {
                        const params = new URLSearchParams({ batch_id: this.current.id });
                        params.append('planter_name[]', v.planter_code);
                        window.KusugpaPdfPreview.open('{{ route('voucher.pdf') }}?' + params.toString(), 'Voucher - ' + (v.receiver_name || v.planter_name));
                    },

                    async saveReceiver(v) {
                        const name = (v.draft || '').trim().replace(/\s+/g, ' ');
                        if (name === (v.receiver_name || '') || v.saving) return;
                        v.saving = true;
                        try {
                            const response = await fetch(batchUrl(this.current.id) + '/items/' + v.id, {
                                method: 'PATCH',
                                headers: jsonHeaders(),
                                body: JSON.stringify({ receiver_name: name }),
                            });
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) throw new Error(data.message || 'Failed to save the authorized receiver.');
                            const had = !!v.receiver_name;
                            v.receiver_name = data.receiver_name || '';
                            v.draft = v.receiver_name;
                            // Keep the group's "authorized" count current
                            const batch = this.batches.find(b => b.id === this.current.id);
                            if (batch && had !== !!v.receiver_name) batch.receivers += v.receiver_name ? 1 : -1;
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2000 });
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Not Saved', text: error.message, confirmButtonColor: '#005B2A' });
                        } finally {
                            v.saving = false;
                        }
                    },

                    async deleteBatch(b) {
                        const confirm = await Swal.fire({
                            icon: 'warning',
                            title: 'Delete this group?',
                            html: 'Group of <b>' + b.generated_at + '</b> (' + this.weekLabel(b) + ', ' + b.planters + ' planter(s)).<br>'
                                + 'Its planters can then be generated again, and their authorized receivers are removed.',
                            showCancelButton: true,
                            confirmButtonText: 'Delete',
                            confirmButtonColor: '#e11d48',
                        });
                        if (!confirm.isConfirmed) return;
                        try {
                            const response = await fetch(batchUrl(b.id), { method: 'DELETE', headers: jsonHeaders() });
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) throw new Error(data.message || 'Failed to delete the group.');
                            if (this.current && this.current.id === b.id) this.current = null;
                            await this.fetchBatches();
                            this.refreshPlanters();
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2500 });
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Not Deleted', text: error.message, confirmButtonColor: '#005B2A' });
                        }
                    },

                    resetFilters() {
                        this.filters = {
                            crop_year: '',
                            week_from: '',
                            week_to: '',
                            planter_names: []
                        };
                        this.planters = [];
                        this.planterLoaded = false;
                        this.planterSearch = '';
                        this.planterOpen = false;
                        this.highlightId = null;
                        this.fetchBatches();
                    },
                }
            }
        </script>
    @endpush
@endsection
