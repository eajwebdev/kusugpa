<!-- resources/views/reports/consolidated.blade.php -->
@extends('layouts.main')

@section('title', 'Consolidated Report')

@section('content')
    <div x-data="consolidatedData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Production Accounting</p>
                    <h1 class="kp-page-title mt-1">Consolidated Report</h1>
                    <p class="kp-page-sub">Comprehensive ledger statement of weekly cane production, hauling allowances, and deduction charges.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="showDeleteModal = true" class="kp-btn kp-btn-danger">
                        <i class="fas fa-trash mr-1.5" aria-hidden="true"></i> Manage / Delete Records
                    </button>
                    <button type="button" @click="openPdfPreview()" class="kp-btn kp-btn-secondary">
                        <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Preview PDF
                    </button>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Totals Cards -->
        <section aria-label="Consolidated aggregate metrics" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-list-ol" aria-hidden="true"></i> Total Records</p>
                <p class="kp-kpi-value kp-num" x-text="formatNum(totalRecords, 0)">{{ number_format($totals['total_records']) }}</p>
                <p class="kp-kpi-sub">Total upload entries</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-truck-moving" aria-hidden="true"></i> Trucking Allowance</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-emerald)">₱{{ number_format($totals['total_ta_amount'], 0) }}</p>
                <p class="kp-kpi-sub">Gross TA subsidy</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-weight-hanging" aria-hidden="true"></i> EMI Amount</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">₱{{ number_format($totals['total_emi_amount'], 0) }}</p>
                <p class="kp-kpi-sub">EMI gross proceeds</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-gas-pump" aria-hidden="true"></i> Fuel Deductions</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-cane)">₱{{ number_format($totals['total_fuel'], 0) }}</p>
                <p class="kp-kpi-sub">Issuance charges</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-coins" aria-hidden="true"></i> Total Summary</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">₱{{ number_format($totals['total_summary'] ?? 0, 0) }}</p>
                <p class="kp-kpi-sub">Net consolidated sum</p>
            </div>
        </section>

        <!-- Action / Filter Bar -->
        <div class="kp-card p-4">
            <div class="flex flex-wrap gap-4 items-end justify-between">
                <div class="flex flex-wrap gap-4 items-end flex-1">
                    <div class="w-40">
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Crop Year</label>
                        <select x-model="filters.cropYear" @change="onCropYearChange()" class="kp-select w-full">
                            <option value="">All Crop Years</option>
                            <template x-for="cy in availableCropYears" :key="'filter_' + cy">
                                <option :value="cy" x-text="cy"></option>
                            </template>
                        </select>
                    </div>
                    <div class="w-36">
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Week From</label>
                        <select x-model="filters.weekFrom" @change="applyFilters()" class="kp-select w-full">
                            <option value="">All Weeks</option>
                            <template x-for="wk in filterWeeks" :key="'from_' + wk">
                                <option :value="wk" x-text="'Week ' + wk"></option>
                            </template>
                        </select>
                    </div>
                    <div class="w-36">
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Week To</label>
                        <select x-model="filters.weekTo" @change="applyFilters()" class="kp-select w-full">
                            <option value="">All Weeks</option>
                            <template x-for="wk in filterWeeks" :key="'to_' + wk">
                                <option :value="wk" x-text="'Week ' + wk"></option>
                            </template>
                        </select>
                    </div>
                    <div class="min-w-[220px] flex-1">
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search Planter</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                            <input type="text" x-model="search" @input.debounce.500ms="applyFilters()"
                                placeholder="Search by planter name or code..."
                                class="kp-input w-full pl-9">
                        </div>
                    </div>
                    <div class="w-36">
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Per Page</label>
                        <select x-model="perPage" @change="loadPage(1)" class="kp-select w-full">
                            <option value="15">15 rows</option>
                            <option value="25">25 rows</option>
                            <option value="50">50 rows</option>
                            <option value="100">100 rows</option>
                        </select>
                    </div>
                    <button @click="clearFilters()" class="kp-btn kp-btn-secondary h-[38px]">
                        <i class="fas fa-times mr-1"></i> Reset
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-mono" style="color: var(--kp-muted)"
                        x-text="'Showing ' + allData.length + ' of ' + totalRecords + ' records'"></span>
                    <button x-show="selectedIds.length > 0" @click="deleteSelected()"
                        class="kp-btn kp-btn-danger">
                        <i class="fas fa-trash mr-1.5"></i> Delete Selected (<span x-text="selectedIds.length"></span>)
                    </button>
                </div>
            </div>
        </div>

        <!-- Data Table Card -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table text-xs">
                    <thead>
                        <tr>
                            <th class="w-10 text-center">
                                <input type="checkbox" @click="toggleSelectAll" :checked="isAllSelected"
                                    class="rounded accent-emerald-700">
                            </th>
                            <th class="cursor-pointer select-none text-center" @click="sortBy('crop_year')">Season</th>
                            <th class="cursor-pointer select-none text-center" @click="sortBy('week_no')">Wk</th>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_code')">Code</th>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_name')">Planter Name</th>
                            <th>Assn</th>
                            <th class="text-right cursor-pointer select-none" @click="sortBy('ta_wt')">TA Wt</th>
                            <th class="text-right cursor-pointer select-none" @click="sortBy('ta_amount')">TA Amt</th>
                            <th class="text-right">EMI Wt</th>
                            <th class="text-right">EMI Amt</th>
                            <th class="text-right">PAT Wt</th>
                            <th class="text-right">PAT Amt</th>
                            <th class="text-right">CCI FA</th>
                            <th class="text-right">CCI FB</th>
                            <th class="text-right">CCI FC</th>
                            <th class="text-right">Fuel</th>
                            <th class="text-right">Rental</th>
                            <th class="text-right">Underload</th>
                            <th class="text-right">Mudpress</th>
                            <th class="text-right">Adj</th>
                            <th class="text-right cursor-pointer select-none" @click="sortBy('total_summary')">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in allData" :key="row.id">
                            <tr class="hover:bg-[var(--kp-hover)] transition-colors"
                                :style="selectedIds.includes(row.id) ? 'background-color: var(--kp-soft)' : ''">
                                <td class="text-center">
                                    <input type="checkbox" :value="row.id" x-model="selectedIds"
                                        class="rounded accent-emerald-700">
                                </td>
                                <td class="text-center font-mono" style="color: var(--kp-muted)" x-text="row.crop_year || '—'"></td>
                                <td class="text-center font-mono font-semibold" style="color: var(--kp-text)" x-text="row.week_no || '—'"></td>
                                <td class="font-mono font-semibold" style="color: var(--kp-text)" x-text="row.planter_code"></td>
                                <td class="font-medium whitespace-nowrap" style="color: var(--kp-text)" x-text="row.planter_name"></td>
                                <td style="color: var(--kp-muted)" x-text="row.assn_name || '—'"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-muted)" x-text="formatNum(row.ta_wt, 3)"></td>
                                <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.ta_amount)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-muted)" x-text="formatNum(row.emi_wt, 3)"></td>
                                <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.emi_amount)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-muted)" x-text="formatNum(row.pat_wt, 3)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.pat_amount)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.cci_fa_amt)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.cci_fb_amt)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.cci_fc_amt)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-cane)" x-text="'₱' + formatNum(row.fuel_issuance_amt)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.rental_amt)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.underload_amt)"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="'₱' + formatNum(row.mudpress_amt)"></td>
                                <td class="text-right font-mono tabular font-semibold"
                                    :style="parseFloat(row.adj_amt) < 0 ? 'color: #A32F2F' : 'color: var(--kp-text)'"
                                    x-text="'₱' + formatNum(row.adj_amt)"></td>
                                <td class="text-right font-mono font-bold tabular whitespace-nowrap"
                                    :style="parseFloat(row.total_summary) < 0 ? 'color: #A32F2F' : 'color: var(--kp-forest)'"
                                    x-text="'₱' + formatNum(row.total_summary)"></td>
                            </tr>
                        </template>
                        <tr x-show="loading">
                            <td colspan="21" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                <i class="fas fa-circle-notch fa-spin text-2xl mb-2 block" style="color: var(--kp-forest)"></i>
                                Fetching consolidated records...
                            </td>
                        </tr>
                        <tr x-show="!loading && allData.length === 0">
                            <td colspan="21" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                <i class="fas fa-folder-open text-3xl mb-2 block opacity-30"></i>
                                No consolidated ledger records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Container -->
            <div class="px-4 py-3 border-t flex flex-wrap items-center justify-between gap-3"
                style="border-color: var(--kp-border); background: var(--kp-surface-2)">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-mono" style="color: var(--kp-muted)"
                        x-text="'Page ' + currentPage + ' of ' + totalPages + ' (' + totalRecords + ' records)'"></span>
                </div>
                <div class="flex items-center gap-1.5" x-show="totalPages > 1">
                    <button @click="loadPage(1)" :disabled="currentPage === 1"
                        class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs disabled:opacity-50">First</button>
                    <button @click="loadPage(currentPage - 1)" :disabled="currentPage === 1"
                        class="kp-btn kp-btn-secondary py-1 px-3 text-xs disabled:opacity-50">
                        <i class="fas fa-chevron-left mr-1"></i> Prev
                    </button>

                    <template x-for="page in visiblePages" :key="page">
                        <button @click="loadPage(page)"
                            :class="page === currentPage ? 'kp-btn kp-btn-primary' : 'kp-btn kp-btn-secondary'"
                            class="py-1 px-3 text-xs font-mono tabular" x-text="page"></button>
                    </template>

                    <button @click="loadPage(currentPage + 1)" :disabled="currentPage >= totalPages"
                        class="kp-btn kp-btn-secondary py-1 px-3 text-xs disabled:opacity-50">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                    <button @click="loadPage(totalPages)" :disabled="currentPage >= totalPages"
                        class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs disabled:opacity-50">Last</button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div x-show="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showDeleteModal = false"></div>
            <div class="relative rounded-xl border max-w-lg w-full p-6 shadow-2xl max-h-[85vh] overflow-y-auto"
                style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                    <h3 class="font-display text-lg font-semibold flex items-center gap-2" style="color: #A32F2F">
                        <i class="fas fa-trash text-base"></i>
                        <span>Manage / Delete Consolidated Records</span>
                    </h3>
                    <button @click="showDeleteModal = false" class="p-1 rounded text-xs hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- Delete Options -->
                <div class="space-y-4">
                    <!-- Delete by Crop Year & Week -->
                    <div class="rounded-xl p-4 border" style="background: var(--kp-surface-2); border-color: var(--kp-border)">
                        <h4 class="font-semibold text-sm mb-1" style="color: var(--kp-text)">Delete by Crop Year & Week</h4>
                        <p class="text-xs mb-3" style="color: var(--kp-muted)">Targeted removal of uploads for a specific milling cycle.</p>
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Crop Year</label>
                                <select x-model="deleteForm.crop_year" @change="updateWeekOptions()" class="kp-select w-full">
                                    <option value="">Select Crop Year</option>
                                    <template x-for="cy in availableCropYears" :key="cy">
                                        <option :value="cy" x-text="cy"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Week No</label>
                                <select x-model="deleteForm.week_no" :disabled="!deleteForm.crop_year" class="kp-select w-full disabled:opacity-50">
                                    <option value="">Select Week</option>
                                    <template x-for="wk in availableWeeks" :key="wk">
                                        <option :value="wk" x-text="'Week ' + wk"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div x-show="deleteForm.crop_year && deleteForm.week_no" class="mb-3">
                            <p class="text-xs font-mono" style="color: var(--kp-forest)">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span x-text="'Will delete ' + getWeekRecordCount() + ' record(s) for ' + deleteForm.crop_year + ' Week ' + deleteForm.week_no"></span>
                            </p>
                        </div>
                        <button @click="confirmDeleteByWeek()" :disabled="!deleteForm.crop_year || !deleteForm.week_no"
                            class="kp-btn kp-btn-danger text-xs py-1.5 px-3 disabled:opacity-50">
                            <i class="fas fa-trash mr-1.5"></i> Delete by Week
                        </button>
                    </div>

                    <!-- Delete Selected -->
                    <div class="rounded-xl p-4 border" style="background: var(--kp-surface-2); border-color: var(--kp-border)">
                        <h4 class="font-semibold text-sm mb-1" style="color: var(--kp-text)">Delete Checked Records</h4>
                        <p class="text-xs mb-3" style="color: var(--kp-muted)">Delete specific records currently selected in the table.</p>
                        <button @click="confirmDeleteSelected()" :disabled="selectedIds.length === 0"
                            class="kp-btn kp-btn-secondary text-xs py-1.5 px-3 disabled:opacity-50">
                            <i class="fas fa-trash mr-1.5"></i> Delete Selected (<span x-text="selectedIds.length"></span>)
                        </button>
                    </div>

                    <!-- Delete All -->
                    <div class="rounded-xl p-4 border border-red-200 dark:border-red-900" style="background: rgba(163, 47, 47, 0.05)">
                        <h4 class="font-semibold text-sm text-red-700 dark:text-red-400 mb-1">Delete ALL Consolidated Data</h4>
                        <p class="text-xs text-red-600 dark:text-red-300 mb-3">Irreversible wipe of all consolidated upload batches.</p>
                        <button @click="confirmDeleteAll()" class="kp-btn kp-btn-danger text-xs py-1.5 px-3">
                            <i class="fas fa-exclamation-triangle mr-1.5"></i> Delete All Records
                        </button>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t flex justify-end" style="border-color: var(--kp-border)">
                    <button @click="showDeleteModal = false" class="kp-btn kp-btn-secondary">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function consolidatedData() {
                return {
                    allData: @json($uploads->items()),
                    totalRecords: {{ $uploads->total() }},
                    currentPage: {{ $uploads->currentPage() }},
                    totalPages: {{ $uploads->lastPage() }},
                    search: '',
                    filters: {
                        cropYear: '',
                        weekFrom: '',
                        weekTo: '',
                    },
                    filterWeeks: [],
                    sortField: 'planter_name',
                    sortDirection: 'asc',
                    perPage: {{ $uploads->perPage() }},
                    loading: false,
                    selectedIds: [],
                    showDeleteModal: false,
                    deleteForm: {
                        crop_year: '',
                        week_no: ''
                    },
                    availableCropYears: [],
                    availableWeeks: [],
                    allWeeksData: [],

                    init() {
                        this.fetchAvailableFilters();
                    },

                    openPdfPreview() {
                        const url = new URL(@json(route('consolidated-report.export')), window.location.origin);
                        if (this.search.trim()) url.searchParams.set('search', this.search.trim());
                        if (this.filters.cropYear) url.searchParams.set('crop_year', this.filters.cropYear);
                        if (this.filters.weekFrom) url.searchParams.set('week_from', this.filters.weekFrom);
                        if (this.filters.weekTo) url.searchParams.set('week_to', this.filters.weekTo);
                        window.KusugpaPdfPreview.open(url.toString(), 'Consolidated Report');
                    },

                    onCropYearChange() {
                        this.filters.weekFrom = '';
                        this.filters.weekTo = '';
                        this.loadFilterWeeks();
                        this.applyFilters();
                    },

                    loadFilterWeeks() {
                        if (!this.filters.cropYear) {
                            this.filterWeeks = [];
                            return;
                        }
                        this.filterWeeks = [...new Set(this.allWeeksData
                                .filter(w => w.crop_year === this.filters.cropYear)
                                .map(w => w.week_no))]
                            .sort((a, b) => parseInt(a) - parseInt(b));
                    },

                    async fetchAvailableFilters() {
                        try {
                            const response = await fetch('{{ route('consolidated-report.filters') }}', {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const data = await response.json();
                            this.availableCropYears = data.crop_years || [];
                            this.allWeeksData = data.weeks_data || [];
                        } catch (error) {
                            console.error('Error fetching filters:', error);
                            this.availableCropYears = [...new Set(this.allData.map(row => row.crop_year))].filter(Boolean).sort();
                        }
                    },

                    updateWeekOptions() {
                        this.deleteForm.week_no = '';
                        if (this.deleteForm.crop_year && this.allWeeksData.length > 0) {
                            this.availableWeeks = this.allWeeksData
                                .filter(w => w.crop_year === this.deleteForm.crop_year)
                                .map(w => w.week_no)
                                .sort((a, b) => parseInt(a) - parseInt(b));
                        } else {
                            this.availableWeeks = [];
                        }
                    },

                    getWeekRecordCount() {
                        if (!this.deleteForm.crop_year || !this.deleteForm.week_no) return 0;
                        const weekData = this.allWeeksData.find(
                            w => w.crop_year === this.deleteForm.crop_year && w.week_no == this.deleteForm.week_no
                        );
                        return weekData ? weekData.count : 0;
                    },

                    get isAllSelected() {
                        return this.allData.length > 0 && this.selectedIds.length === this.allData.length;
                    },

                    toggleSelectAll() {
                        if (this.isAllSelected) {
                            this.selectedIds = [];
                        } else {
                            this.selectedIds = this.allData.map(row => row.id);
                        }
                    },

                    get visiblePages() {
                        const pages = [];
                        const maxVisible = 5;
                        let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
                        let end = Math.min(this.totalPages, start + maxVisible - 1);
                        if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
                        for (let i = start; i <= end; i++) pages.push(i);
                        return pages;
                    },

                    async loadPage(page) {
                        this.loading = true;
                        this.selectedIds = [];
                        try {
                            const params = new URLSearchParams({
                                page: page,
                                per_page: this.perPage,
                                crop_year: this.filters.cropYear,
                                week_from: this.filters.weekFrom,
                                week_to: this.filters.weekTo,
                                search: this.search,
                                sort_field: this.sortField,
                                sort_direction: this.sortDirection,
                            });
                            const response = await fetch(`{{ route('consolidated-report') }}?${params.toString()}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const data = await response.json();
                            this.allData = data.data;
                            this.currentPage = data.current_page;
                            this.totalPages = data.last_page;
                            this.totalRecords = data.total;
                        } catch (error) {
                            console.error('Error:', error);
                        } finally {
                            this.loading = false;
                        }
                    },

                    applyFilters() {
                        this.loadPage(1);
                    },

                    sortBy(field) {
                        if (this.sortField === field) {
                            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.sortField = field;
                            this.sortDirection = 'asc';
                        }
                        this.loadPage(1);
                    },

                    clearFilters() {
                        this.search = '';
                        this.filters = { cropYear: '', weekFrom: '', weekTo: '' };
                        this.filterWeeks = [];
                        this.loadPage(1);
                    },

                    async confirmDeleteAll() {
                        const result = await Swal.fire({
                            title: 'Delete ALL Records?',
                            text: 'This will permanently delete ALL consolidated upload records. This cannot be undone!',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete all',
                            confirmButtonColor: '#832727',
                            cancelButtonText: 'Cancel'
                        });

                        if (!result.isConfirmed) return;

                        try {
                            const response = await fetch('{{ route('consolidated-report.delete-all') }}', {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const data = await response.json();

                            if (!response.ok) throw new Error(data.message);

                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 3000,
                                showConfirmButton: false
                            });

                            this.showDeleteModal = false;
                            setTimeout(() => location.reload(), 1500);
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });
                        }
                    },

                    async confirmDeleteByWeek() {
                        if (!this.deleteForm.crop_year || !this.deleteForm.week_no) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Required',
                                text: 'Please select both crop year and week number.'
                            });
                            return;
                        }

                        const recordCount = this.getWeekRecordCount();
                        const result = await Swal.fire({
                            title: 'Delete Records?',
                            text: `Delete ${recordCount} record(s) for ${this.deleteForm.crop_year} Week ${this.deleteForm.week_no}?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete',
                            confirmButtonColor: '#832727',
                            cancelButtonText: 'Cancel'
                        });

                        if (!result.isConfirmed) return;

                        try {
                            const response = await fetch('{{ route('consolidated-report.delete-by-week') }}', {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(this.deleteForm)
                            });
                            const data = await response.json();

                            if (!response.ok) throw new Error(data.message);

                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 3000,
                                showConfirmButton: false
                            });

                            this.deleteForm = { crop_year: '', week_no: '' };
                            this.availableWeeks = [];
                            this.showDeleteModal = false;
                            setTimeout(() => location.reload(), 1500);
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });
                        }
                    },

                    async confirmDeleteSelected() {
                        if (this.selectedIds.length === 0) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'No Selection',
                                text: 'Please select records to delete using the checkboxes.'
                            });
                            return;
                        }

                        const result = await Swal.fire({
                            title: 'Delete Selected Records?',
                            text: `Delete ${this.selectedIds.length} selected record(s)? This cannot be undone!`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete',
                            confirmButtonColor: '#832727',
                            cancelButtonText: 'Cancel'
                        });

                        if (!result.isConfirmed) return;

                        try {
                            const response = await fetch('{{ route('consolidated-report.delete-selected') }}', {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({ ids: this.selectedIds })
                            });
                            const data = await response.json();

                            if (!response.ok) throw new Error(data.message);

                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 3000,
                                showConfirmButton: false
                            });

                            this.selectedIds = [];
                            this.showDeleteModal = false;
                            setTimeout(() => location.reload(), 1500);
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });
                        }
                    },

                    async deleteSelected() {
                        await this.confirmDeleteSelected();
                    },

                    formatNum(num, decimals = 2) {
                        return parseFloat(num || 0).toLocaleString('en-US', {
                            minimumFractionDigits: decimals,
                            maximumFractionDigits: decimals
                        });
                    }
                }
            }
        </script>
    @endpush
@endsection
