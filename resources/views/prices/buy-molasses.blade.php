<!-- resources/views/prices/buy-molasses.blade.php -->
@extends('layouts.main')

@section('title', 'Buy Molasses')

@section('content')
<div x-data="buyMolassesData()" class="space-y-6 kp-fade-in">
    <!-- Page Header -->
    <header>
        <div class="kp-page-header">
            <div>
                <p class="kp-eyebrow">Commodity Procurement</p>
                <h1 class="kp-page-title mt-1">Buy Molasses Allocations</h1>
                <p class="kp-page-sub">Select and flag planter molasses certificates as purchased for settlement.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('prices.registry') }}" class="kp-btn kp-btn-secondary text-xs">
                    <i class="fas fa-list-ul mr-1.5" aria-hidden="true"></i> Molasses Registry
                </a>
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
                <select x-model="filters.weekNo" @change="loadData()" class="kp-input py-1.5 text-xs">
                    <option value="">All Weeks</option>
                    <template x-for="w in availableWeeks" :key="w">
                        <option :value="w" x-text="'Week ' + w"></option>
                    </template>
                </select>
            </div>
            <button type="button" @click="loadData()" :disabled="!filters.cropYear" 
                class="kp-btn kp-btn-primary text-xs py-2 disabled:opacity-40">
                <i class="fas fa-search mr-1.5" aria-hidden="true"></i> Load Molasses
            </button>
            <div class="flex-1"></div>
            <div class="flex items-center gap-2" x-show="stats.total > 0" x-cloak>
                <span class="kp-badge text-xs py-1 px-2.5">Total: <strong class="ml-1" x-text="stats.total"></strong></span>
                <span class="kp-badge kp-badge--forest text-xs py-1 px-2.5">Bought: <strong class="ml-1" x-text="stats.bought"></strong></span>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Strip -->
    <div x-show="selectedIds.length > 0" x-transition x-cloak 
        class="p-3.5 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] border border-[#CFDCD3] dark:border-[#203A2B] flex flex-wrap items-center gap-3">
        <span class="text-xs font-bold text-[#005B2A] dark:text-[#52C97E]" x-text="selectedIds.length + ' record(s) selected'"></span>
        <button type="button" @click="bulkUpdate('bought')" class="kp-btn kp-btn-primary text-xs py-1.5">
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
    <div class="kp-card overflow-hidden">
        <div class="kp-table-wrap overflow-x-auto" style="max-height: 560px; overflow-y: auto;">
            <table class="kp-table w-full">
                <thead>
                    <tr>
                        <th class="w-10 text-center">
                            <input type="checkbox" @change="toggleAll($event)" :checked="isAllSelected" class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                        </th>
                        <th>Crop Year</th>
                        <th class="text-center">Week</th>
                        <th>Planter Code</th>
                        <th>Planter Name</th>
                        <th class="text-right">Molasses Net (MT)</th>
                        <th class="text-center">Procurement Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in paginatedData" :key="row.id">
                        <tr :class="row.status === 'bought' ? 'bg-[#EAF5ED]/50 dark:bg-[#1A3324]/40' : ''" class="hover:bg-[#EAF5ED]/30 dark:hover:bg-[#1A3324]/30 transition-colors">
                            <td class="text-center">
                                <input type="checkbox" :checked="selectedIds.includes(row.id)" @change="toggleSelect(row.id)" class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                            </td>
                            <td><span class="kp-badge kp-badge--forest text-xs font-mono" x-text="row.crop_year"></span></td>
                            <td class="text-center font-mono text-xs" x-text="row.week_no"></td>
                            <td class="font-mono text-xs text-[#52705F] dark:text-[#8AA895]" x-text="row.planter_code"></td>
                            <td class="text-xs font-semibold text-[#172B20] dark:text-[#EAF5ED]" x-text="row.planter_name"></td>
                            <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]" x-text="formatNum(row.mol_net, 3)"></td>
                            <td class="text-center">
                                <span class="kp-badge text-xs"
                                    :class="row.status === 'bought' ? 'kp-badge--forest' : 'kp-badge--amber'"
                                    x-text="row.status === 'bought' ? 'Bought' : 'Pending'"></span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="data.length === 0" x-cloak>
                        <td colspan="7" class="py-12 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                            <i class="fas fa-filter text-2xl mb-2 text-[#8AA895] block"></i>
                            Select a crop year and click <strong>"Load Molasses"</strong> to view records.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 border-t border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex items-center justify-between" x-show="totalPages > 1" x-cloak>
            <button type="button" @click="currentPage--" :disabled="currentPage === 1" class="kp-btn kp-btn-secondary text-xs px-3 py-1 disabled:opacity-40">Previous</button>
            <span class="text-xs text-[#52705F] dark:text-[#8AA895]">
                Page <strong class="text-[#172B20] dark:text-[#EAF5ED]" x-text="currentPage"></strong> of <strong class="text-[#172B20] dark:text-[#EAF5ED]" x-text="totalPages"></strong>
            </span>
            <button type="button" @click="currentPage++" :disabled="currentPage >= totalPages" class="kp-btn kp-btn-secondary text-xs px-3 py-1 disabled:opacity-40">Next</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function buyMolassesData() {
        return {
            filters: { cropYear: '', weekNo: '' },
            availableWeeks: [],
            data: [],
            stats: { total: 0, bought: 0, pending: 0 },
            selectedIds: [],
            currentPage: 1,
            perPage: 25,

            get paginatedData() {
                const start = (this.currentPage - 1) * this.perPage;
                return this.data.slice(start, start + this.perPage);
            },
            get totalPages() { return Math.max(1, Math.ceil(this.data.length / this.perPage)); },
            get isAllSelected() {
                return this.paginatedData.length > 0 && this.paginatedData.every(r => this.selectedIds.includes(r.id));
            },

            async onCropYearChange() {
                this.filters.weekNo = '';
                if (this.filters.cropYear) {
                    const r = await fetch(`{{ route('registry.data') }}?type=molasses&crop_year=${this.filters.cropYear}`, { headers: { 'Accept': 'application/json' } });
                    const d = await r.json();
                    this.availableWeeks = d.weeks || [];
                } else { this.availableWeeks = []; }
            },

            async loadData() {
                if (!this.filters.cropYear) return;
                const params = new URLSearchParams({ type: 'molasses', crop_year: this.filters.cropYear, week_no: this.filters.weekNo });
                const r = await fetch(`{{ route('registry.data') }}?${params}`, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.data = d.data || [];
                this.stats = d.stats || { total: 0, bought: 0, pending: 0 };
                this.selectedIds = [];
                this.currentPage = 1;
            },

            toggleSelect(id) {
                const idx = this.selectedIds.indexOf(id);
                idx > -1 ? this.selectedIds.splice(idx, 1) : this.selectedIds.push(id);
            },
            toggleAll(e) {
                if (e.target.checked) {
                    this.paginatedData.forEach(r => { if (!this.selectedIds.includes(r.id)) this.selectedIds.push(r.id); });
                } else {
                    this.selectedIds = this.selectedIds.filter(id => !this.paginatedData.map(r => r.id).includes(id));
                }
            },
            async bulkUpdate(status) {
                if (!this.selectedIds.length) return;
                const r = await fetch('{{ route('molasses.bulk-update') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ ids: this.selectedIds, status })
                });
                const d = await r.json();
                Swal.fire({ icon: 'success', title: 'Status Updated', text: d.message, timer: 2000, showConfirmButton: false });
                this.selectedIds = [];
                await this.loadData();
            },
            formatNum(num, decimals = 2) {
                return parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            }
        }
    }
</script>
@endpush
@endsection