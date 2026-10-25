<!-- resources/views/reports/certification.blade.php -->
@extends('layouts.main')

@section('title', 'Production Certification')

@section('content')
    <div x-data="certificationData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Reports & Certificates</p>
                    <h1 class="kp-page-title mt-1">Production Certification</h1>
                    <p class="kp-page-sub">Certify a planter's net cane, sugar and molasses production per crop year, based on the uploaded mill records.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="resetFilters()" class="kp-btn kp-btn-secondary text-xs">
                        <i class="fas fa-undo mr-1.5" aria-hidden="true"></i> Reset Filters
                    </button>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Criteria Card -->
        <div class="kp-card p-6">
            <div class="flex items-center gap-2.5 pb-4 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                    <i class="fas fa-certificate" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Certification Criteria</h2>
                    <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Pick the crop year and week range, tick the planters below, then generate one certificate per planter.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Crop Year -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-calendar-alt text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Crop Year
                    </label>
                    <select x-model="filters.crop_year" @change="onCropYearChange()" class="kp-input w-full">
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
                    <select x-model="filters.week_from" @change="loadPlanters()" :disabled="cropWeeks.length === 0"
                        class="kp-input w-full disabled:opacity-50">
                        <option value="" x-show="cropWeeks.length === 0" x-text="filters.crop_year ? 'No weeks set up' : 'All Weeks'"></option>
                        <template x-for="w in cropWeeks" :key="'f' + w">
                            <option :value="w" x-text="'Week ' + w" :selected="w === filters.week_from"></option>
                        </template>
                    </select>
                </div>

                <!-- Week To -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-stop-circle text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Week To
                    </label>
                    <select x-model="filters.week_to" @change="loadPlanters()" :disabled="cropWeeks.length === 0"
                        class="kp-input w-full disabled:opacity-50">
                        <option value="" x-show="cropWeeks.length === 0" x-text="filters.crop_year ? 'No weeks set up' : 'All Weeks'"></option>
                        <template x-for="w in cropWeeks" :key="'t' + w">
                            <option :value="w" x-text="'Week ' + w" :selected="w === filters.week_to"></option>
                        </template>
                    </select>
                </div>

                <!-- Issued Date -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-calendar-day text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Date Issued
                    </label>
                    <input type="date" x-model="filters.issued_date" class="kp-input w-full">
                </div>

                <!-- Selection summary -->
                <div class="sm:col-span-2 lg:col-span-4 flex items-end justify-end gap-2.5">
                    <span class="text-xs text-[#52705F] dark:text-[#8AA895] mr-auto lg:mr-0 self-center"
                        x-text="selected.length + ' of ' + planters.length + ' planter(s) selected'"></span>
                    <button type="button" @click="generate(true)" :disabled="selected.length === 0"
                        class="kp-btn kp-btn-secondary disabled:opacity-50">
                        <i class="fas fa-download mr-1.5" aria-hidden="true"></i> Download
                    </button>
                    <button type="button" @click="generate(false)" :disabled="selected.length === 0"
                        class="kp-btn kp-btn-primary shadow-sm disabled:opacity-50">
                        <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Generate Certification
                    </button>
                </div>
            </div>

            <!-- Signatories -->
            <div class="mt-5 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                <button type="button" @click="showSignatories = !showSignatories"
                    class="text-xs font-semibold text-[#005B2A] dark:text-[#52C97E] flex items-center gap-1.5">
                    <i class="fas fa-signature" aria-hidden="true"></i>
                    <span>Mill &amp; Signatories</span>
                    <i class="fas fa-chevron-down text-[10px] transition-transform" :class="{ 'rotate-180': showSignatories }"></i>
                </button>
                <div x-show="showSignatories" x-transition x-cloak class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1">Mill</label>
                        <input type="text" x-model="signatories.mill_name" class="kp-input w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1">Prepared By</label>
                        <input type="text" x-model="signatories.prepared_name" class="kp-input w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1">Position</label>
                        <input type="text" x-model="signatories.prepared_title" class="kp-input w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1">Approved By</label>
                        <input type="text" x-model="signatories.approved_name" class="kp-input w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1">Position</label>
                        <input type="text" x-model="signatories.approved_title" class="kp-input w-full text-xs">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-5 flex items-center justify-end gap-2">
                        <span class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Changes apply to the next certificate. Save to keep them for everyone.</span>
                        <button type="button" @click="saveSignatories()" class="kp-btn kp-btn-secondary text-xs py-1.5">
                            <i class="fas fa-save mr-1.5" aria-hidden="true"></i> Save as Default
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planter Table Card -->
        <div class="kp-card overflow-hidden">
            <div class="px-5 py-3.5 border-b border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Planter Production</span>
                    <button type="button" @click="selectAllFiltered()" class="text-xs font-semibold text-[#005B2A] dark:text-[#52C97E] hover:underline"
                        x-text="search.trim() ? 'Select Matches' : 'Select All'"></button>
                    <button type="button" @click="selected = []" class="text-xs font-medium text-[#52705F] dark:text-[#8AA895] hover:text-rose-600">Clear</button>
                </div>
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#52705F] dark:text-[#8AA895]">
                        <i class="fas fa-search text-xs" aria-hidden="true"></i>
                    </div>
                    <input type="text" x-model="search" class="kp-input w-full pl-8 py-1.5 text-xs"
                        placeholder="Search planter name or code...">
                </div>
            </div>

            <div x-show="loading" class="flex items-center justify-center py-16" x-cloak>
                <div class="flex flex-col items-center gap-2">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-[#005B2A] dark:text-[#52C97E]"></i>
                    <span class="text-xs font-medium text-[#52705F] dark:text-[#8AA895]">Computing production totals...</span>
                </div>
            </div>

            <div x-show="!loading" class="kp-table-wrap overflow-x-auto" style="max-height: 560px; overflow-y: auto;">
                <table class="kp-table w-full">
                    <thead>
                        <tr>
                            <th class="w-10 text-center">
                                <input type="checkbox" @change="toggleAllFiltered($event)" :checked="isAllFilteredSelected"
                                    class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                            </th>
                            <th>Code</th>
                            <th>Planter Name</th>
                            <th class="text-center">Crop Year</th>
                            <th class="text-right">Net Tons</th>
                            <th class="text-right">Net Sugar (LKG)</th>
                            <th class="text-right">Net Mol (MT)</th>
                            <th>Data Check</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="planter in filteredPlanters" :key="planter.code_key">
                            <tr class="hover:bg-[#EAF5ED]/30 dark:hover:bg-[#1A3324]/30 transition-colors cursor-pointer"
                                :class="selected.includes(planter.code_key) ? 'bg-[#EAF5ED]/60 dark:bg-[#1A3324]/50' : ''"
                                @click="toggle(planter.code_key)">
                                <td class="text-center" @click.stop>
                                    <input type="checkbox" :value="planter.code_key" x-model="selected"
                                        class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                                </td>
                                <td class="font-mono text-xs text-[#52705F] dark:text-[#8AA895]" x-text="planter.planter_code"></td>
                                <td class="text-xs font-semibold text-[#172B20] dark:text-[#EAF5ED]" x-text="planter.planter_name"></td>
                                <td class="text-center text-xs font-mono" x-text="planter.years.join(', ')"></td>
                                <td class="text-xs text-right kp-num" x-text="formatNum(planter.net_tons, 2)"></td>
                                <td class="text-xs text-right kp-num font-semibold text-[#005B2A] dark:text-[#52C97E]" x-text="formatNum(planter.net_lkg, 2)"></td>
                                <td class="text-xs text-right kp-num" x-text="formatNum(planter.net_mol, 3)"></td>
                                <td class="text-xs">
                                    <template x-if="planter.missing.length === 0">
                                        <span class="kp-badge kp-badge--forest text-[10px]">Complete</span>
                                    </template>
                                    <template x-if="planter.missing.length > 0">
                                        <span class="kp-badge kp-badge--amber text-[10px]" x-text="'No ' + planter.missing.join(', ')"></span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && filteredPlanters.length === 0" x-cloak>
                            <td colspan="8" class="py-12 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                                <i class="fas fa-folder-open text-2xl mb-2 text-[#8AA895] block"></i>
                                <span x-text="planters.length === 0 ? 'No consolidated, quedan or molasses records for this crop year and week range.' : 'No planters match your search.'"></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] text-[11px] text-[#52705F] dark:text-[#8AA895] leading-relaxed">
                <i class="fas fa-info-circle mr-1 text-[#087A3D]" aria-hidden="true"></i>
                Net Tons = Consolidated Report TA Wt · Net Sugar = Quedan sugar LKG − labor LKG · Net Mol = Molasses net, for the selected weeks.
                "Data Check" flags planters missing one of those uploads, so the certificate would show 0 for it.
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function certificationData() {
                const weeks = @json($weeks);
                return {
                    filters: {
                        crop_year: @json($cropYears->first() ?? ''),
                        week_from: '',
                        week_to: '',
                        issued_date: new Date().toLocaleDateString('en-CA'),
                    },
                    signatories: @json($signatories),
                    savedSignatories: @json($signatories),
                    showSignatories: false,
                    rows: [],
                    search: '',
                    selected: [],
                    loading: false,

                    init() {
                        this.onCropYearChange();
                    },

                    // Weeks of the selected crop year (none with "All Crop Years")
                    get cropWeeks() {
                        return weeks.filter(w => w.crop_year === this.filters.crop_year).map(w => w.week_no);
                    },

                    // A new crop year starts with its whole season selected
                    onCropYearChange() {
                        const list = this.cropWeeks;
                        this.filters.week_from = list.length ? list[0] : '';
                        this.filters.week_to = list.length ? list[list.length - 1] : '';
                        this.loadPlanters();
                    },

                    periodParams() {
                        const params = { crop_year: this.filters.crop_year };
                        if (this.filters.crop_year && this.filters.week_from !== '' && this.filters.week_to !== '') {
                            params.week_from = this.filters.week_from;
                            params.week_to = this.filters.week_to;
                        }
                        return params;
                    },

                    // One entry per planter; with "All Crop Years" the crop years are summed
                    get planters() {
                        const byPlanter = {};
                        this.rows.forEach(row => {
                            const p = byPlanter[row.code_key] ??= {
                                code_key: row.code_key,
                                planter_code: row.planter_code,
                                planter_name: row.planter_name,
                                years: [],
                                net_tons: 0, net_lkg: 0, net_mol: 0,
                            };
                            p.years.push(row.crop_year_label);
                            p.net_tons += parseFloat(row.net_tons) || 0;
                            p.net_lkg += parseFloat(row.net_lkg) || 0;
                            p.net_mol += parseFloat(row.net_mol) || 0;
                        });
                        return Object.values(byPlanter).map(p => ({
                            ...p,
                            missing: [
                                p.net_tons === 0 ? 'consolidated' : null,
                                p.net_lkg === 0 ? 'quedan' : null,
                                p.net_mol === 0 ? 'molasses' : null,
                            ].filter(Boolean),
                        }));
                    },

                    get filteredPlanters() {
                        const term = this.search.trim().toLowerCase();
                        if (!term) return this.planters;
                        return this.planters.filter(p =>
                            p.planter_name.toLowerCase().includes(term) ||
                            String(p.planter_code).toLowerCase().includes(term)
                        );
                    },

                    get isAllFilteredSelected() {
                        return this.filteredPlanters.length > 0 &&
                            this.filteredPlanters.every(p => this.selected.includes(p.code_key));
                    },

                    async loadPlanters() {
                        this.loading = true;
                        this.selected = [];
                        try {
                            const params = new URLSearchParams(this.periodParams());
                            const response = await fetch(`{{ route('certification-report.planters') }}?${params}`, {
                                headers: { 'Accept': 'application/json' }
                            });
                            if (!response.ok) throw new Error('Failed to load planters');
                            const data = await response.json();
                            this.rows = data.data || [];
                        } catch (error) {
                            console.error('Error loading planters:', error);
                            this.rows = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    toggle(key) {
                        const idx = this.selected.indexOf(key);
                        idx > -1 ? this.selected.splice(idx, 1) : this.selected.push(key);
                    },

                    selectAllFiltered() {
                        this.filteredPlanters.forEach(p => {
                            if (!this.selected.includes(p.code_key)) this.selected.push(p.code_key);
                        });
                    },

                    toggleAllFiltered(e) {
                        if (e.target.checked) {
                            this.selectAllFiltered();
                        } else {
                            const keys = this.filteredPlanters.map(p => p.code_key);
                            this.selected = this.selected.filter(k => !keys.includes(k));
                        }
                    },

                    generate(download) {
                        if (this.selected.length === 0) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'No Planter Selected',
                                text: 'Tick at least one planter to certify.',
                                confirmButtonColor: '#005B2A'
                            });
                            return;
                        }

                        const params = new URLSearchParams({
                            ...this.periodParams(),
                            issued_date: this.filters.issued_date,
                            ...this.signatories,
                        });
                        if (this.selected.length === this.planters.length) {
                            params.set('all', '1');
                        } else {
                            params.set('planters', this.selected.join(','));
                        }

                        const url = `{{ route('certification-report.pdf') }}?${params}`;
                        // More than 100 certificates open in the preview, split into parts
                        if (download) {
                            window.KusugpaPdfPreview.download(url, 'Production Certification');
                        } else {
                            window.KusugpaPdfPreview.open(url, 'Production Certification');
                        }
                    },

                    async saveSignatories() {
                        try {
                            const response = await fetch('{{ route('certification-report.signatories') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(this.signatories)
                            });
                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message || 'Failed to save');
                            this.savedSignatories = { ...this.signatories };
                            Swal.fire({ icon: 'success', title: 'Saved', text: data.message, timer: 1800, showConfirmButton: false });
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
                        }
                    },

                    resetFilters() {
                        this.filters.crop_year = @json($cropYears->first() ?? '');
                        this.filters.issued_date = new Date().toLocaleDateString('en-CA');
                        this.signatories = { ...this.savedSignatories };
                        this.search = '';
                        this.onCropYearChange();
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
