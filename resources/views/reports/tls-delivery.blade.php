<!-- resources/views/reports/tls-delivery.blade.php -->
@extends('layouts.main')

@section('title', 'TLS Delivery Report')

@section('content')
    <div x-data="tlsReport()" class="space-y-6 kp-fade-in">
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Reports</p>
                    <h1 class="kp-page-title mt-1">Summary of Transloading Delivery</h1>
                    <p class="kp-page-sub">Weekly TLS hauling tickets grouped by transloading station and association, from the TLS Hauling upload.</p>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Filters -->
        <div class="kp-card p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Crop Year</label>
                    <select x-model="filters.crop_year" @change="onCropYearChange()" class="kp-input w-full">
                        @foreach ($cropYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Week From</label>
                    <select x-model="filters.week_from" @change="load()" class="kp-input w-full">
                        <template x-for="w in cropWeeks" :key="'f' + w.week_no">
                            <option :value="w.week_no" x-text="w.label"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Week To</label>
                    <select x-model="filters.week_to" @change="load()" class="kp-input w-full">
                        <template x-for="w in cropWeeks" :key="'t' + w.week_no">
                            <option :value="w.week_no" x-text="w.label"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Station</label>
                    <select x-model="filters.station" @change="load()" class="kp-input w-full">
                        <option value="">All Stations</option>
                        <template x-for="s in options.stations" :key="s">
                            <option :value="s" x-text="s"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Association</label>
                    <select x-model="filters.association" @change="load()" class="kp-input w-full">
                        <option value="">All Associations</option>
                        <template x-for="a in options.associations" :key="a">
                            <option :value="a" x-text="a"></option>
                        </template>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2.5 mt-5 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                <span class="text-xs text-[#52705F] dark:text-[#8AA895] mr-auto" x-text="totalRecords + ' ticket(s)'"></span>
                <button type="button" @click="openPdf(true)" :disabled="totalRecords === 0" class="kp-btn kp-btn-secondary disabled:opacity-50">
                    <i class="fas fa-download mr-1.5" aria-hidden="true"></i> Download
                </button>
                <button type="button" @click="openPdf(false)" :disabled="totalRecords === 0" class="kp-btn kp-btn-primary disabled:opacity-50">
                    <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Preview Report
                </button>
            </div>
        </div>

        <!-- Summary -->
        <div class="kp-card overflow-hidden">
            <div x-show="loading" class="flex items-center justify-center py-14" x-cloak>
                <i class="fas fa-circle-notch fa-spin text-2xl text-[#005B2A] dark:text-[#52C97E]"></i>
            </div>
            <div x-show="!loading" class="kp-table-wrap overflow-x-auto">
                <table class="kp-table w-full">
                    <thead>
                        <tr>
                            <th>Transloading / Association</th>
                            <th class="text-right">Records</th>
                            <th class="text-right">Gross Cane Wt</th>
                            <th class="text-right">Net Cane Wt</th>
                            <th class="text-right">TDIF Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in summaryRows" :key="row.key">
                            <tr :class="row.station ? 'bg-[#F7FAF8] dark:bg-[#1A3324] font-bold' : ''">
                                <td class="text-xs" :class="row.station ? 'text-[#172B20] dark:text-[#EAF5ED]' : 'pl-8 text-[#52705F] dark:text-[#8AA895]'"
                                    x-text="(row.code ? row.code + ' · ' : '') + row.name"></td>
                                <td class="text-xs text-right kp-num" x-text="row.records"></td>
                                <td class="text-xs text-right kp-num" x-text="fmt(row.gross, 3)"></td>
                                <td class="text-xs text-right kp-num" x-text="fmt(row.net, 3)"></td>
                                <td class="text-xs text-right kp-num" x-text="fmt(row.amount, 2)"></td>
                            </tr>
                        </template>
                        <tr x-show="stations.length === 0">
                            <td colspan="5" class="py-12 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                                <i class="fas fa-folder-open text-2xl mb-2 block opacity-40"></i>
                                No TLS hauling records for this period. Upload them under Weekly Uploads → TLS Hauling.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function tlsReport() {
                const weeks = @json($weeks);
                return {
                    filters: { crop_year: @json($cropYears->first() ?? ''), week_from: '', week_to: '', station: '', association: '' },
                    options: { stations: [], associations: [] },
                    stations: [],
                    loading: false,

                    get cropWeeks() {
                        return weeks.filter(w => w.crop_year === this.filters.crop_year);
                    },
                    // Station rows followed by their association rows, as one flat list for the table
                    get summaryRows() {
                        return this.stations.flatMap(s => [
                            { ...s, key: 's|' + s.name, station: true },
                            ...s.associations.map(a => ({ ...a, key: 'a|' + s.name + '|' + a.code + '|' + a.name, station: false })),
                        ]);
                    },
                    get totalRecords() {
                        return this.stations.reduce((sum, s) => sum + s.records, 0);
                    },

                    init() {
                        this.onCropYearChange();
                    },

                    onCropYearChange() {
                        const list = this.cropWeeks;
                        this.filters.week_from = list.length ? list[0].week_no : '';
                        this.filters.week_to = list.length ? list[list.length - 1].week_no : '';
                        this.load();
                    },

                    params() {
                        return new URLSearchParams(Object.fromEntries(Object.entries(this.filters).filter(([, v]) => v !== '')));
                    },

                    async load() {
                        this.loading = true;
                        try {
                            const r = await fetch(`{{ route('tls-report.data') }}?${this.params()}`, { headers: { 'Accept': 'application/json' } });
                            const d = await r.json();
                            this.stations = d.stations || [];
                            this.options = d.filters || this.options;
                        } catch (e) {
                            console.error('Error loading TLS report:', e);
                            this.stations = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    openPdf(download) {
                        const url = `{{ route('tls-report.pdf') }}?${this.params()}`;
                        download ? window.location.assign(url + '&download=1') : window.KusugpaPdfPreview.open(url, 'Summary of Transloading Delivery');
                    },

                    fmt(n, d) {
                        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
                    }
                }
            }
        </script>
    @endpush
@endsection
