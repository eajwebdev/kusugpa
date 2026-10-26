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
                @if ($canDelete)
                    <button type="button" @click="openDelete()" class="kp-btn kp-btn-danger">
                        <i class="fas fa-trash mr-1.5" aria-hidden="true"></i> Delete Weeks
                    </button>
                @endif
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

        @if ($canDelete)
        <!-- Group delete: crop year, then one or more weeks -->
        <div x-show="del.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="del.open = false">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="del.open = false"></div>
            <div class="relative w-full max-w-lg rounded-xl border p-6 shadow-2xl max-h-[85vh] overflow-y-auto"
                style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)"
                role="dialog" aria-modal="true" aria-labelledby="tls-del-h">
                <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                    <h3 id="tls-del-h" class="font-display text-lg font-semibold flex items-center gap-2" style="color: #A32F2F">
                        <i class="fas fa-trash text-base" aria-hidden="true"></i> Delete TLS Hauling Weeks
                    </h3>
                    <button type="button" @click="del.open = false" class="p-1 rounded text-xs hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)" aria-label="Close">
                        <i class="fas fa-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Crop Year</label>
                <select x-model="del.crop_year" @change="loadDeleteWeeks()" class="kp-input w-full mb-4">
                    @foreach ($cropYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>

                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium" style="color: var(--kp-muted)">Weeks with uploaded tickets</span>
                    <label class="flex items-center gap-2 text-xs" x-show="selectableWeeks.length > 1">
                        <input type="checkbox" class="rounded" :checked="allSelected" @change="toggleAll($event.target.checked)"> Select all
                    </label>
                </div>
                <div class="rounded-lg border divide-y" style="border-color: var(--kp-border)">
                    <div x-show="del.loading" class="py-6 text-center text-xs" style="color: var(--kp-muted)">
                        <i class="fas fa-circle-notch fa-spin mr-1" aria-hidden="true"></i> Loading weeks…
                    </div>
                    <template x-for="w in del.weeks" :key="w.week_no">
                        <label class="flex items-center gap-3 px-3 py-2.5 text-sm" :class="w.released ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer hover:bg-[var(--kp-surface-2)]'">
                            <input type="checkbox" class="rounded" :value="w.week_no" x-model.number="del.selected" :disabled="w.released > 0">
                            <span class="flex-1 min-w-0">
                                <span class="block font-medium" x-text="weekLabel(w.week_no)"></span>
                                <span class="block text-xs" style="color: var(--kp-muted)" x-text="weekSummary(w)"></span>
                            </span>
                            <span x-show="w.released" class="kp-badge kp-badge--amber whitespace-nowrap" title="Undo the release in Hauler TLS LIQ to delete this week">
                                <i class="fas fa-lock" aria-hidden="true"></i> <span x-text="w.released + ' released'"></span>
                            </span>
                        </label>
                    </template>
                    <p x-show="!del.loading && del.weeks.length === 0" class="py-6 text-center text-xs" style="color: var(--kp-muted)">No TLS tickets uploaded for this crop year.</p>
                </div>

                <div x-show="del.selected.length" class="mt-4 rounded-lg p-3 text-xs" style="background: rgba(163, 47, 47, 0.06); color: #8A2A2A">
                    <i class="fas fa-triangle-exclamation mr-1" aria-hidden="true"></i>
                    <span x-text="deleteSummary()"></span>
                </div>
                <p class="mt-3 text-xs" style="color: var(--kp-faint)">Weeks with a released liquidation (CV no., check no. or release date set) are locked. Manually added liquidation entries are kept.</p>

                <div class="mt-4 pt-4 border-t flex justify-end gap-2" style="border-color: var(--kp-border)">
                    <button type="button" @click="del.open = false" class="kp-btn kp-btn-secondary">Cancel</button>
                    <button type="button" @click="confirmDelete()" :disabled="!del.selected.length || del.busy" class="kp-btn kp-btn-danger disabled:opacity-50">
                        <i class="fas mr-1.5" :class="del.busy ? 'fa-circle-notch fa-spin' : 'fa-trash'" aria-hidden="true"></i>
                        <span x-text="del.selected.length ? 'Delete ' + del.selected.length + ' week(s)' : 'Delete'"></span>
                    </button>
                </div>
            </div>
        </div>
        @endif
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
                    del: { open: false, crop_year: '', weeks: [], selected: [], loading: false, busy: false },

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
                    // Weeks with a released liquidation cannot be picked
                    get selectableWeeks() {
                        return this.del.weeks.filter(w => !w.released);
                    },
                    get allSelected() {
                        return this.selectableWeeks.length > 0 && this.selectableWeeks.every(w => this.del.selected.includes(w.week_no));
                    },
                    get selectedTotals() {
                        return this.del.weeks.filter(w => this.del.selected.includes(w.week_no))
                            .reduce((t, w) => ({ tickets: t.tickets + w.tickets, tons: t.tons + w.tons, entries: t.entries + w.entries }), { tickets: 0, tons: 0, entries: 0 });
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

                    openDelete() {
                        this.del.open = true;
                        this.del.crop_year = this.filters.crop_year;
                        this.loadDeleteWeeks();
                    },

                    async loadDeleteWeeks() {
                        this.del.selected = [];
                        this.del.weeks = [];
                        if (!this.del.crop_year) return;
                        this.del.loading = true;
                        try {
                            const r = await fetch(`{{ route('tls-report.weeks') }}?crop_year=${encodeURIComponent(this.del.crop_year)}`, { headers: { 'Accept': 'application/json' } });
                            if (!r.ok) throw new Error('Could not load the weeks.');
                            this.del.weeks = (await r.json()).weeks || [];
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        } finally {
                            this.del.loading = false;
                        }
                    },

                    toggleAll(on) {
                        this.del.selected = on ? this.selectableWeeks.map(w => w.week_no) : [];
                    },

                    weekLabel(weekNo) {
                        const w = weeks.find(w => w.crop_year === this.del.crop_year && Number(w.week_no) === weekNo);
                        return w ? w.label : 'Week ' + weekNo;
                    },

                    entries(n) {
                        return n + ' liquidation entr' + (n === 1 ? 'y' : 'ies');
                    },

                    weekSummary(w) {
                        return w.tickets + ' ticket(s) · ' + this.fmt(w.tons, 3) + ' t · ' + this.entries(w.entries);
                    },

                    sortedSelection() {
                        return [...this.del.selected].sort((a, b) => a - b);
                    },

                    deleteSummary() {
                        const t = this.selectedTotals;
                        return 'Will permanently delete ' + t.tickets + ' ticket(s) (' + this.fmt(t.tons, 3) + ' t) and '
                            + this.entries(t.entries) + ' made from them, for week ' + this.sortedSelection().join(', ') + '.';
                    },

                    async confirmDelete() {
                        const t = this.selectedTotals;
                        const list = this.sortedSelection();
                        const result = await Swal.fire({
                            title: 'Delete ' + list.length + ' week(s)?',
                            text: 'Crop year ' + this.del.crop_year + ', week ' + list.join(', ') + ': ' + t.tickets + ' TLS ticket(s) and '
                                + this.entries(t.entries) + ' will be deleted. This cannot be undone.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete',
                            confirmButtonColor: '#832727',
                            cancelButtonText: 'Cancel',
                        });
                        if (!result.isConfirmed) return;

                        this.del.busy = true;
                        try {
                            const r = await fetch(`{{ route('tls-report.delete-weeks') }}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({ crop_year: this.del.crop_year, weeks: list }),
                            });
                            const d = await r.json().catch(() => ({}));
                            if (!r.ok) throw new Error(d.message || 'Could not delete the selected weeks.');

                            Swal.fire({ icon: 'success', title: 'Deleted', text: d.message, timer: 3500, showConfirmButton: false });
                            this.del.open = false;
                            this.load();
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Not deleted', text: e.message });
                            this.loadDeleteWeeks();
                        } finally {
                            this.del.busy = false;
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
