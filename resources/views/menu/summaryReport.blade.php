<!-- resources/views/menu/summaryReport.blade.php -->
@extends('layouts.main')

@section('title', 'Summary Report')

@section('content')
    <div x-data="summaryReportData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Production Accounting</p>
                    <h1 class="kp-page-title mt-1">Summary Report</h1>
                    <p class="kp-page-sub">Consolidated crop production summaries, net tonnage metrics, and planter net settlements.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="kp-badge kp-badge--forest text-xs py-1.5 px-3">
                        <i class="fas fa-database mr-1.5" aria-hidden="true"></i> Records: <strong class="ml-1" x-text="totalRecords">0</strong>
                    </span>
                    <button type="button" @click="previewPDF()" class="kp-btn kp-btn-secondary text-xs">
                        <i class="fas fa-file-pdf mr-1.5 text-rose-600" aria-hidden="true"></i> PDF Report
                    </button>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Filter Card -->
        <div class="kp-card p-5">
            <div class="flex items-center gap-2.5 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="w-7 h-7 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-xs font-semibold">
                    <i class="fas fa-filter" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Report Parameters</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Crop Year -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-calendar-alt text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Crop Year
                    </label>
                    <select x-model="filters.cropYear" @change="onCropYearChange()"
                        class="kp-input w-full">
                        <option value="">All Crop Years</option>
                        @foreach ($cropYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Week From -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-play-circle text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Week From
                    </label>
                    <select x-model="filters.weekFrom" @change="loadData(1)"
                        class="kp-input w-full">
                        <option value="">All Weeks</option>
                        <template x-for="week in availableWeeks" :key="'from_' + week">
                            <option :value="week" x-text="'Week ' + week"></option>
                        </template>
                    </select>
                    <p x-show="availableWeeks.length === 0 && filters.cropYear" class="text-[11px] text-amber-600 dark:text-amber-400 mt-1" x-cloak>
                        <i class="fas fa-info-circle mr-1" aria-hidden="true"></i> No weeks recorded for year
                    </p>
                </div>

                <!-- Week To -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-stop-circle text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Week To
                    </label>
                    <select x-model="filters.weekTo" @change="loadData(1)"
                        class="kp-input w-full">
                        <option value="">All Weeks</option>
                        <template x-for="week in availableWeeks" :key="'to_' + week">
                            <option :value="week" x-text="'Week ' + week"></option>
                        </template>
                    </select>
                </div>

                <!-- Per Page -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-list text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Page Size
                    </label>
                    <select x-model="filters.perPage" @change="loadData(1)"
                        class="kp-input w-full">
                        <option value="50">50 entries</option>
                        <option value="100">100 entries</option>
                        <option value="250">250 entries</option>
                        <option value="500">500 entries</option>
                        <option value="1000">1000 entries</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-end gap-2">
                    <button type="button" @click="previewPDF()"
                        class="kp-btn kp-btn-primary flex-1 justify-center text-xs">
                        <i class="fas fa-eye mr-1" aria-hidden="true"></i> Preview
                    </button>
                    <button type="button" @click="downloadPDF()"
                        class="kp-btn kp-btn-secondary flex-1 justify-center text-xs">
                        <i class="fas fa-download mr-1" aria-hidden="true"></i> Export
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="kp-card overflow-hidden">
            <!-- Table Action Toolbar -->
            <div class="px-5 py-3.5 border-b border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Settlement Records</span>
                    <span class="text-xs text-[#52705F] dark:text-[#8AA895]">(Sorted by ledger index)</span>
                </div>
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#52705F] dark:text-[#8AA895]">
                        <i class="fas fa-search text-xs" aria-hidden="true"></i>
                    </div>
                    <input type="text" x-model="filters.search" @input.debounce.500ms="loadData(1)"
                        class="kp-input w-full pl-8 py-1.5 text-xs"
                        placeholder="Search planter name or code...">
                </div>
            </div>

            <!-- Loading Indicator -->
            <div x-show="loading" class="flex items-center justify-center py-16" x-cloak>
                <div class="flex flex-col items-center gap-2">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-[#005B2A] dark:text-[#52C97E]"></i>
                    <span class="text-xs font-medium text-[#52705F] dark:text-[#8AA895]">Loading summary records...</span>
                </div>
            </div>

            <!-- Table Body -->
            <div x-show="!loading" class="kp-table-wrap overflow-x-auto" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table w-full" id="summaryTable">
                    <thead>
                        <tr>
                            <th class="cursor-pointer select-none" @click="sortBy('id')">
                                <div class="flex items-center gap-1.5">
                                    <span>#</span>
                                    <i :class="sortField === 'id' ? (sortDirection === 'asc' ? 'fas fa-sort-up text-[#005B2A] dark:text-[#52C97E]' : 'fas fa-sort-down text-[#005B2A] dark:text-[#52C97E]') : 'fas fa-sort opacity-40'" class="text-xs"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none" @click="sortBy('crop_year')">
                                <div class="flex items-center gap-1.5">
                                    <span>Crop Year</span>
                                    <i :class="sortField === 'crop_year' ? (sortDirection === 'asc' ? 'fas fa-sort-up text-[#005B2A] dark:text-[#52C97E]' : 'fas fa-sort-down text-[#005B2A] dark:text-[#52C97E]') : 'fas fa-sort opacity-40'" class="text-xs"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none" @click="sortBy('week_no')">
                                <div class="flex items-center gap-1.5">
                                    <span>Week No</span>
                                    <i :class="sortField === 'week_no' ? (sortDirection === 'asc' ? 'fas fa-sort-up text-[#005B2A] dark:text-[#52C97E]' : 'fas fa-sort-down text-[#005B2A] dark:text-[#52C97E]') : 'fas fa-sort opacity-40'" class="text-xs"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_code')">
                                <div class="flex items-center gap-1.5">
                                    <span>Planter Code</span>
                                    <i :class="sortField === 'planter_code' ? (sortDirection === 'asc' ? 'fas fa-sort-up text-[#005B2A] dark:text-[#52C97E]' : 'fas fa-sort-down text-[#005B2A] dark:text-[#52C97E]') : 'fas fa-sort opacity-40'" class="text-xs"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_name')">
                                <div class="flex items-center gap-1.5">
                                    <span>Planter Name</span>
                                    <i :class="sortField === 'planter_name' ? (sortDirection === 'asc' ? 'fas fa-sort-up text-[#005B2A] dark:text-[#52C97E]' : 'fas fa-sort-down text-[#005B2A] dark:text-[#52C97E]') : 'fas fa-sort opacity-40'" class="text-xs"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none text-right" @click="sortBy('net_cane')">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Net Cane (TC)</span>
                                    <i :class="sortField === 'net_cane' ? (sortDirection === 'asc' ? 'fas fa-sort-up text-[#005B2A] dark:text-[#52C97E]' : 'fas fa-sort-down text-[#005B2A] dark:text-[#52C97E]') : 'fas fa-sort opacity-40'" class="text-xs"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none text-right" @click="sortBy('net_amount')">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Net Proceeds (₱)</span>
                                    <i :class="sortField === 'net_amount' ? (sortDirection === 'asc' ? 'fas fa-sort-up text-[#005B2A] dark:text-[#52C97E]' : 'fas fa-sort-down text-[#005B2A] dark:text-[#52C97E]') : 'fas fa-sort opacity-40'" class="text-xs"></i>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(summary, index) in summaries" :key="summary.id">
                            <tr class="hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition-colors">
                                <td class="text-xs text-[#52705F] dark:text-[#8AA895] font-mono"
                                    x-text="(currentPage - 1) * filters.perPage + index + 1"></td>
                                <td>
                                    <span class="kp-badge kp-badge--forest text-xs font-mono"
                                        x-text="summary.crop_year"></span>
                                </td>
                                <td class="text-xs font-medium text-[#172B20] dark:text-[#EAF5ED]" x-text="'Week ' + summary.week_no"></td>
                                <td class="text-xs font-mono text-[#52705F] dark:text-[#8AA895]" x-text="summary.planter_code"></td>
                                <td class="text-xs font-semibold text-[#172B20] dark:text-[#EAF5ED]" x-text="summary.planter_name"></td>
                                <td class="text-xs text-right kp-num font-medium text-[#172B20] dark:text-[#EAF5ED]"
                                    x-text="formatNumber(summary.net_cane, 3)"></td>
                                <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]"
                                    x-text="'₱' + formatNumber(summary.net_amount, 2)"></td>
                            </tr>
                        </template>
                        <tr x-show="summaries.length === 0" x-cloak>
                            <td colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-12 h-12 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-xl mb-3">
                                        <i class="fas fa-inbox" aria-hidden="true"></i>
                                    </div>
                                    <p class="text-xs font-medium text-[#52705F] dark:text-[#8AA895]">No crop summary records found for the specified criteria.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div x-show="!loading && totalPages > 1" x-cloak
                class="px-5 py-3 border-t border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-1.5">
                    <button type="button" @click="loadData(1)" :disabled="currentPage === 1"
                        class="kp-btn kp-btn-secondary text-xs px-2.5 py-1 disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-angle-double-left" aria-hidden="true"></i>
                    </button>
                    <button type="button" @click="loadData(currentPage - 1)" :disabled="currentPage === 1"
                        class="kp-btn kp-btn-secondary text-xs px-2.5 py-1 disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-angle-left" aria-hidden="true"></i>
                    </button>

                    <template x-for="page in visiblePages" :key="page">
                        <button type="button" @click="loadData(page)" x-text="page"
                            :class="page === currentPage ? 'kp-btn-primary' : 'kp-btn-secondary'"
                            class="kp-btn text-xs px-3 py-1"></button>
                    </template>

                    <button type="button" @click="loadData(currentPage + 1)" :disabled="currentPage === totalPages"
                        class="kp-btn kp-btn-secondary text-xs px-2.5 py-1 disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-angle-right" aria-hidden="true"></i>
                    </button>
                    <button type="button" @click="loadData(totalPages)" :disabled="currentPage === totalPages"
                        class="kp-btn kp-btn-secondary text-xs px-2.5 py-1 disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-angle-double-right" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="text-xs text-[#52705F] dark:text-[#8AA895]">
                    Page <span class="font-bold text-[#172B20] dark:text-[#EAF5ED]" x-text="currentPage"></span> of <span class="font-bold text-[#172B20] dark:text-[#EAF5ED]" x-text="totalPages"></span>
                    (<span class="kp-num" x-text="totalRecords"></span> records total)
                </p>
            </div>
        </div>

    </div>

    @push('scripts')
        <script>
            function summaryReportData() {
                return {
                    summaries: [],
                    totalRecords: 0,
                    currentPage: 1,
                    totalPages: 1,
                    loading: false,
                    sortField: 'id',
                    sortDirection: 'desc',
                    availableWeeks: [],
                    filters: {
                        cropYear: '',
                        weekFrom: '',
                        weekTo: '',
                        search: '',
                        perPage: 50,
                    },

                    init() {
                        this.loadData(1);
                        if (this.filters.cropYear) {
                            this.loadWeeks();
                        }
                    },

                    get visiblePages() {
                        const pages = [];
                        const maxVisible = 5;
                        let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
                        let end = Math.min(this.totalPages, start + maxVisible - 1);

                        if (end - start + 1 < maxVisible) {
                            start = Math.max(1, end - maxVisible + 1);
                        }

                        for (let i = start; i <= end; i++) {
                            pages.push(i);
                        }
                        return pages;
                    },

                    async onCropYearChange() {
                        this.filters.weekFrom = '';
                        this.filters.weekTo = '';
                        await this.loadWeeks();
                        this.loadData(1);
                    },

                    async loadWeeks() {
                        if (!this.filters.cropYear) {
                            this.availableWeeks = [];
                            return;
                        }

                        try {
                            const response = await fetch(
                                `{{ route('summaryReport.weeks') }}?crop_year=${this.filters.cropYear}`, {
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    }
                                });

                            if (!response.ok) throw new Error('Failed to load weeks');

                            const data = await response.json();
                            this.availableWeeks = data.weeks;
                        } catch (error) {
                            console.error('Error loading weeks:', error);
                            this.availableWeeks = [];
                        }
                    },

                    async loadData(page) {
                        this.loading = true;
                        this.currentPage = page;

                        try {
                            const params = new URLSearchParams({
                                page: page,
                                per_page: this.filters.perPage,
                                crop_year: this.filters.cropYear,
                                week_from: this.filters.weekFrom,
                                week_to: this.filters.weekTo,
                                search: this.filters.search,
                                sort_field: this.sortField,
                                sort_direction: this.sortDirection,
                            });

                            const response = await fetch(`{{ route('summaryReport.data') }}?${params.toString()}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });

                            if (!response.ok) throw new Error('Failed to load');

                            const data = await response.json();
                            this.summaries = data.data;
                            this.totalRecords = data.total;
                            this.totalPages = data.last_page;
                        } catch (error) {
                            console.error('Error:', error);
                        } finally {
                            this.loading = false;
                        }
                    },

                    sortBy(field) {
                        if (this.sortField === field) {
                            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.sortField = field;
                            this.sortDirection = 'asc';
                        }
                        this.loadData(1);
                    },

                    formatNumber(number, decimals = 2) {
                        if (number === null || number === undefined) return '0.00';
                        return parseFloat(number).toLocaleString('en-US', {
                            minimumFractionDigits: decimals,
                            maximumFractionDigits: decimals
                        });
                    },

                    previewPDF() {
                        if (!this.filters.cropYear) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Missing Parameter',
                                text: 'Please select a Crop Year first.',
                                confirmButtonColor: '#005B2A'
                            });
                            return;
                        }
                        const url =
                            `{{ url('/summary/pdf-preview') }}?crop_year=${this.filters.cropYear}&week_from=${this.filters.weekFrom}&week_to=${this.filters.weekTo}`;
                        window.KusugpaPdfPreview.open(url, 'Summary Report');
                    },

                    downloadPDF() {
                        if (!this.filters.cropYear) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Missing Parameter',
                                text: 'Please select a Crop Year first.',
                                confirmButtonColor: '#005B2A'
                            });
                            return;
                        }
                        // The preview route downloads with download=1 and can also show a part in the preview
                        const downloadUrl =
                            `{{ url('/summary/pdf-preview') }}?crop_year=${this.filters.cropYear}&week_from=${this.filters.weekFrom}&week_to=${this.filters.weekTo}`;
                        // A large crop year opens in the preview, split into parts
                        window.KusugpaPdfPreview.download(downloadUrl, 'Summary Report');
                    }
                }
            }
        </script>
    @endpush
@endsection
