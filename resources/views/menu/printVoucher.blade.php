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
                    <span class="kp-badge kp-badge--forest text-xs py-1.5 px-3" x-show="showPreview" x-cloak>
                        <i class="fas fa-file-invoice mr-1.5" aria-hidden="true"></i> Voucher Statement Ready
                    </span>
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
                                    No planters found for the selected period
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
                        <span>Select crop year and week range to activate preview generation.</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <button type="button" @click="resetFilters()"
                            class="kp-btn kp-btn-secondary">
                            <i class="fas fa-undo mr-1.5" aria-hidden="true"></i> Reset
                        </button>
                        <button type="submit"
                            class="kp-btn kp-btn-primary shadow-sm"
                            :disabled="!filters.crop_year || !filters.week_from || !filters.week_to">
                            <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Generate Voucher
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Empty State -->
        <div x-show="!showPreview" class="kp-card p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fas fa-print" aria-hidden="true"></i>
            </div>
            <h3 class="text-base font-bold text-[#172B20] dark:text-[#EAF5ED] mb-1">No Voucher Generated</h3>
            <p class="text-xs text-[#52705F] dark:text-[#8AA895] max-w-md mx-auto leading-relaxed">
                Select a crop year, week range, and optionally target specific planters, then click <strong>"Generate Voucher"</strong> to preview the compiled printable statement.
            </p>
        </div>
    </div>

    @push('scripts')
        <script>
            function voucherFilter() {
                return {
                    // Main filters
                    filters: {
                        crop_year: '{{ $selectedCropYear ?? '' }}',
                        week_from: '{{ $weekFrom ?? '' }}',
                        week_to: '{{ $weekTo ?? '' }}',
                        planter_names: []
                    },
                    showPreview: false,
                    pdfUrl: '',

                    // Planter dropdown
                    planterOpen: false,
                    planters: [],
                    planterLoading: false,
                    planterSearch: '',

                    get filteredPlanters() {
                        const term = this.planterSearch.trim().toLowerCase();
                        if (!term) return this.planters;
                        return this.planters.filter(p =>
                            String(p.planter_name || '').toLowerCase().includes(term) ||
                            String(p.planter_code || '').toLowerCase().includes(term)
                        );
                    },

                    get canLoadPlanters() {
                        return this.filters.crop_year && this.filters.week_from && this.filters.week_to;
                    },

                    get planterSelectedText() {
                        if (!this.filters.planter_names || this.filters.planter_names.length === 0) {
                            return 'All Planters';
                        }
                        return this.filters.planter_names.length + ' planter(s) selected';
                    },

                    onFilterChange() {
                        this.filters.planter_names = [];
                        this.planters = [];
                        this.planterSearch = '';
                    },

                    togglePlanterDropdown() {
                        this.planterOpen = !this.planterOpen;
                        if (this.planterOpen && this.planters.length === 0 && this.canLoadPlanters) {
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
                                week_to: this.filters.week_to
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

                    loadVoucher() {
                        if (!this.filters.crop_year || !this.filters.week_from || !this.filters.week_to) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Incomplete Filters',
                                text: 'Please select crop year and week range.',
                                confirmButtonColor: '#005B2A'
                            });
                            return;
                        }

                        const params = new URLSearchParams({
                            crop_year: this.filters.crop_year,
                            week_from: this.filters.week_from,
                            week_to: this.filters.week_to,
                        });

                        if (this.filters.planter_names.length > 0) {
                            this.filters.planter_names.forEach(name => {
                                params.append('planter_name[]', name);
                            });
                        }

                        this.pdfUrl = '{{ route('voucher.pdf') }}?' + params.toString();
                        this.showPreview = true;
                        window.KusugpaPdfPreview.open(this.pdfUrl, 'Voucher Ledger');
                    },

                    resetFilters() {
                        this.filters = {
                            crop_year: '',
                            week_from: '',
                            week_to: '',
                            planter_names: []
                        };
                        this.planters = [];
                        this.planterSearch = '';
                        this.planterOpen = false;
                        this.showPreview = false;
                        this.pdfUrl = '';
                    },

                    downloadPDF() {
                        // A whole week of vouchers is too large for one request: the preview splits it into parts
                        if (this.pdfUrl) window.KusugpaPdfPreview.download(this.pdfUrl, 'Voucher Ledger');
                    },

                    printVoucher() {
                        if (this.pdfUrl) window.KusugpaPdfPreview.open(this.pdfUrl, 'Voucher Ledger');
                    }
                }
            }
        </script>
    @endpush
@endsection
