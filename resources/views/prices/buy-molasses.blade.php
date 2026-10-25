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
                <select x-model="filters.weekNo" @change="loadData()" :disabled="!filters.cropYear" class="kp-input py-1.5 text-xs disabled:opacity-40">
                    <option value="">All Weeks</option>
                    <template x-for="w in availableWeeks" :key="w">
                        <option :value="w" x-text="'Week ' + w"></option>
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
                    class="kp-input w-full pl-7 py-1.5 text-xs disabled:opacity-40" placeholder="Search planter, code or MC #...">
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

    <!-- Table Card: one scrolling list; more rows load as it scrolls -->
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
                        <th>MC No</th>
                        <th class="text-right">Molasses Net (MT)</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in data" :key="row.id">
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
                            <td class="font-mono text-xs text-[#52705F] dark:text-[#8AA895]" x-text="row.mc_no || '—'"></td>
                            <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]" x-text="formatNum(row.mol_net, 3)"></td>
                        </tr>
                    </template>
                    <tr x-show="!filters.cropYear" x-cloak>
                        <td colspan="8" class="py-12 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                            <i class="fas fa-filter text-2xl mb-2 text-[#8AA895] block"></i>
                            Select a crop year to list its molasses.
                        </td>
                    </tr>
                    <template x-if="feed && filters.cropYear">
                        @include('layouts.partials.feed-footer', ['feed' => 'feed', 'colspan' => 8, 'empty' => 'No molasses match the crop year, week, search or status.'])
                    </template>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-2.5 border-t border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] text-xs text-[#52705F] dark:text-[#8AA895]"
            x-show="data.length > 0" x-cloak
            x-text="'Showing ' + data.length.toLocaleString() + ' of ' + (feed && feed.total !== null ? feed.total.toLocaleString() : '…') + ' molasses record(s) · more load as you scroll · click a row to select it · the header box selects every row shown'"></div>
    </div>
</div>

@push('scripts')
<script>
    function buyMolassesData() {
        return {
            filters: { cropYear: '', weekNo: '' },
            selectedIds: [],
            search: '',
            statusFilter: '',
            // Molasses come from the server in batches of 100 as the list scrolls (kpFeed);
            // search and the Pending / Bought filter run in the database
            feed: null,
            searchTimer: null,

            init() {
                this.feed = kpFeed('{{ route('registry.data') }}', () => ({
                    type: 'molasses', crop_year: this.filters.cropYear, week_no: this.filters.weekNo,
                    search: this.search.trim(), status: this.statusFilter,
                }));
                this.$watch('statusFilter', () => this.loadData());
                this.$watch('search', () => {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = setTimeout(() => this.loadData(), 400);
                });
            },

            get data() { return this.feed ? this.feed.rows : []; },
            get loading() { return this.feed ? this.feed.loading : false; },
            // Counts of the crop year / week / search, whatever the status filter
            get stats() { return (this.feed && this.feed.extra.stats) || { total: 0, bought: 0, pending: 0 }; },
            get availableWeeks() { return (this.feed && this.feed.extra.weeks) || []; },
            get isAllSelected() {
                return this.data.length > 0 && this.data.every(r => this.selectedIds.includes(r.id));
            },

            onCropYearChange() {
                this.filters.weekNo = '';
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
            // Selects every row loaded so far
            toggleAll(e) {
                const shownIds = this.data.map(r => r.id);
                this.selectedIds = e.target.checked
                    ? [...new Set([...this.selectedIds, ...shownIds])]
                    : this.selectedIds.filter(id => !shownIds.includes(id));
            },
            async bulkUpdate(status) {
                if (!this.selectedIds.length) return;
                const r = await fetch('{{ route('molasses.bulk-update') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ ids: this.selectedIds, status })
                });
                const d = await r.json();
                Swal.fire({ icon: r.ok ? 'success' : 'error', title: r.ok ? 'Status Updated' : 'Error', text: d.message, timer: 2000, showConfirmButton: false });
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
