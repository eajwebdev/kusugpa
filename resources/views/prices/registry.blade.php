<!-- resources/views/prices/registry.blade.php -->
@extends('layouts.main')

@section('title', 'Quedan & Molasses Registry')

@section('content')
    <div x-data="registryData()" x-init="initRegistry()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Commodity Exchange & Pricing</p>
                    <h1 class="kp-page-title mt-1">Quedan & Molasses Registry</h1>
                    <p class="kp-page-sub">Complete transaction ledger of warehouse quedans, sugar liens, and molasses allocations.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="showDeleteModal = true; deleteType = activeTab"
                        class="kp-btn kp-btn-danger text-xs">
                        <i class="fas fa-trash mr-1.5" aria-hidden="true"></i> Batch Delete
                    </button>
                    <a :href="activeTab === 'quedan' ? '{{ route('quedan-registry.export') }}' : '{{ route('molasses-registry.export') }}'"
                        :data-pdf-preview="activeTab === 'quedan' ? 'Quedan Registry' : 'Molasses Registry'"
                        class="kp-btn kp-btn-secondary text-xs">
                        <i class="fas fa-file-pdf mr-1.5 text-rose-600 dark:text-rose-400" aria-hidden="true"></i> Preview <span x-text="activeTab === 'quedan' ? 'Quedan PDF' : 'Molasses PDF'"></span>
                    </a>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Tab Navigation Bar -->
        <div class="flex p-1.5 rounded-2xl bg-[#EAF5ED]/60 dark:bg-[#13231B] border border-[#CFDCD3] dark:border-[#203A2B] max-w-md">
            <button type="button" @click="activeTab = 'quedan'; resetSelections()"
                :class="activeTab === 'quedan' ? 'bg-white dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] shadow-sm font-bold' : 'text-[#52705F] dark:text-[#8AA895] hover:text-[#172B20] font-medium'"
                class="flex-1 py-2 px-4 rounded-xl text-xs transition flex items-center justify-center gap-2">
                <i class="fas fa-qrcode" aria-hidden="true"></i>
                <span>Quedan Registry</span>
                <span class="kp-badge kp-badge--forest text-[10px] py-0.5 px-2" x-text="totalQuedanCount"></span>
            </button>
            <button type="button" @click="activeTab = 'molasses'; resetSelections()"
                :class="activeTab === 'molasses' ? 'bg-white dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] shadow-sm font-bold' : 'text-[#52705F] dark:text-[#8AA895] hover:text-[#172B20] font-medium'"
                class="flex-1 py-2 px-4 rounded-xl text-xs transition flex items-center justify-center gap-2">
                <i class="fas fa-flask" aria-hidden="true"></i>
                <span>Molasses Registry</span>
                <span class="kp-badge kp-badge--forest text-[10px] py-0.5 px-2" x-text="totalMolassesCount"></span>
            </button>
        </div>

        <!-- Quedan Tab -->
        <div x-show="activeTab === 'quedan'" class="space-y-4" x-transition>
            <!-- Filter & Bulk Actions Bar -->
            <div class="kp-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="relative w-full sm:w-72">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#52705F]">
                            <i class="fas fa-search text-xs" aria-hidden="true"></i>
                        </div>
                        <input type="text" x-model="quedanSearch" @input="quedanCurrentPage = 1"
                            placeholder="Search planter, code, or QDN #..." 
                            class="kp-input w-full pl-8 py-1.5 text-xs">
                    </div>
                    <span class="text-xs text-[#52705F] dark:text-[#8AA895]" x-text="'Showing ' + filteredQuedans.length + ' of ' + totalQuedanCount + ' records'"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button x-show="quedanSelectedIds.length > 0" @click="confirmDeleteSelected('quedan')" x-cloak
                        class="kp-btn kp-btn-danger text-xs py-1.5 px-3">
                        <i class="fas fa-trash mr-1.5" aria-hidden="true"></i> Delete Selected (<span x-text="quedanSelectedIds.length"></span>)
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="kp-card overflow-hidden">
                <div class="kp-table-wrap overflow-x-auto" style="max-height: 580px; overflow-y: auto;">
                    <table class="kp-table w-full">
                        <thead>
                            <tr>
                                <th class="w-10 text-center">
                                    <input type="checkbox" @click="toggleSelectAllQuedan" :checked="isAllQuedanSelected"
                                        class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                                </th>
                                <th class="cursor-pointer select-none" @click="sortQuedan('crop_year')">Crop Year</th>
                                <th class="cursor-pointer select-none text-center" @click="sortQuedan('week_no')">Week</th>
                                <th class="cursor-pointer select-none" @click="sortQuedan('planter_code')">Planter Code</th>
                                <th class="cursor-pointer select-none" @click="sortQuedan('planter_name')">Planter Name</th>
                                <th>QDN No</th>
                                <th>TIN No</th>
                                <th class="cursor-pointer select-none text-right" @click="sortQuedan('total_liens')">Total Liens</th>
                                <th class="cursor-pointer select-none text-right" @click="sortQuedan('sugar_lkg')">Sugar (Lkg)</th>
                                <th class="cursor-pointer select-none text-right" @click="sortQuedan('labor_lkg')">Labor (Lkg)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in paginatedQuedans" :key="row.id">
                                <tr class="hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition-colors"
                                    :class="{ 'bg-[#EAF5ED]/80 dark:bg-[#1A3324]/80': quedanSelectedIds.includes(row.id) }">
                                    <td class="text-center">
                                        <input type="checkbox" :value="row.id" x-model="quedanSelectedIds"
                                            class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                                    </td>
                                    <td>
                                        <span class="kp-badge kp-badge--forest text-xs font-mono" x-text="row.crop_year"></span>
                                    </td>
                                    <td class="text-center font-mono text-xs" x-text="row.week_no"></td>
                                    <td class="font-mono text-xs text-[#52705F] dark:text-[#8AA895]" x-text="row.planter_code"></td>
                                    <td class="text-xs font-semibold text-[#172B20] dark:text-[#EAF5ED]" x-text="row.planter_name"></td>
                                    <td class="text-xs font-mono text-[#52705F] dark:text-[#8AA895]" x-text="row.qdn_no || '—'"></td>
                                    <td class="text-xs font-mono text-[#52705F] dark:text-[#8AA895]" x-text="row.tin_no || '—'"></td>
                                    <td class="text-xs text-right kp-num font-medium text-[#172B20] dark:text-[#EAF5ED]" x-text="formatNum(row.total_liens, 3)"></td>
                                    <td class="text-xs text-right kp-num font-semibold text-[#005B2A] dark:text-[#52C97E]" x-text="formatNum(row.sugar_lkg, 3)"></td>
                                    <td class="text-xs text-right kp-num font-medium text-[#172B20] dark:text-[#EAF5ED]" x-text="formatNum(row.labor_lkg, 3)"></td>
                                </tr>
                            </template>
                            <tr x-show="filteredQuedans.length === 0" x-cloak>
                                <td colspan="10" class="py-12 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                                    <i class="fas fa-inbox text-3xl mb-2 text-[#8AA895] block"></i>
                                    No quedan registry records found matching the filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="px-5 py-3 border-t border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex items-center justify-between"
                    x-show="quedanTotalPages > 1" x-cloak>
                    <button type="button" @click="quedanCurrentPage--" :disabled="quedanCurrentPage === 1"
                        class="kp-btn kp-btn-secondary text-xs px-3 py-1 disabled:opacity-40">Previous</button>
                    <span class="text-xs text-[#52705F] dark:text-[#8AA895]">
                        Page <strong class="text-[#172B20] dark:text-[#EAF5ED]" x-text="quedanCurrentPage"></strong> of <strong class="text-[#172B20] dark:text-[#EAF5ED]" x-text="quedanTotalPages"></strong>
                    </span>
                    <button type="button" @click="quedanCurrentPage++" :disabled="quedanCurrentPage >= quedanTotalPages"
                        class="kp-btn kp-btn-secondary text-xs px-3 py-1 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>

        <!-- Molasses Tab -->
        <div x-show="activeTab === 'molasses'" class="space-y-4" x-transition x-cloak>
            <!-- Filter & Bulk Actions Bar -->
            <div class="kp-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="relative w-full sm:w-72">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#52705F]">
                            <i class="fas fa-search text-xs" aria-hidden="true"></i>
                        </div>
                        <input type="text" x-model="molassesSearch" @input="molassesCurrentPage = 1"
                            placeholder="Search planter, code, or MC #..." 
                            class="kp-input w-full pl-8 py-1.5 text-xs">
                    </div>
                    <span class="text-xs text-[#52705F] dark:text-[#8AA895]" x-text="'Showing ' + filteredMolasses.length + ' of ' + totalMolassesCount + ' records'"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button x-show="molassesSelectedIds.length > 0" @click="confirmDeleteSelected('molasses')" x-cloak
                        class="kp-btn kp-btn-danger text-xs py-1.5 px-3">
                        <i class="fas fa-trash mr-1.5" aria-hidden="true"></i> Delete Selected (<span x-text="molassesSelectedIds.length"></span>)
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="kp-card overflow-hidden">
                <div class="kp-table-wrap overflow-x-auto" style="max-height: 580px; overflow-y: auto;">
                    <table class="kp-table w-full">
                        <thead>
                            <tr>
                                <th class="w-10 text-center">
                                    <input type="checkbox" @click="toggleSelectAllMolasses"
                                        :checked="isAllMolassesSelected"
                                        class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                                </th>
                                <th class="cursor-pointer select-none" @click="sortMolasses('crop_year')">Crop Year</th>
                                <th class="cursor-pointer select-none text-center" @click="sortMolasses('week_no')">Week</th>
                                <th class="cursor-pointer select-none" @click="sortMolasses('planter_code')">Planter Code</th>
                                <th class="cursor-pointer select-none" @click="sortMolasses('planter_name')">Planter Name</th>
                                <th>TIN No</th>
                                <th>MC No</th>
                                <th class="cursor-pointer select-none text-right" @click="sortMolasses('mol_net')">Molasses Net (MT)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in paginatedMolasses" :key="row.id">
                                <tr class="hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition-colors"
                                    :class="{ 'bg-[#EAF5ED]/80 dark:bg-[#1A3324]/80': molassesSelectedIds.includes(row.id) }">
                                    <td class="text-center">
                                        <input type="checkbox" :value="row.id" x-model="molassesSelectedIds"
                                            class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A]">
                                    </td>
                                    <td>
                                        <span class="kp-badge kp-badge--forest text-xs font-mono" x-text="row.crop_year"></span>
                                    </td>
                                    <td class="text-center font-mono text-xs" x-text="row.week_no"></td>
                                    <td class="font-mono text-xs text-[#52705F] dark:text-[#8AA895]" x-text="row.planter_code"></td>
                                    <td class="text-xs font-semibold text-[#172B20] dark:text-[#EAF5ED]" x-text="row.planter_name"></td>
                                    <td class="text-xs font-mono text-[#52705F] dark:text-[#8AA895]" x-text="row.tin_no || '—'"></td>
                                    <td class="text-xs font-mono text-[#52705F] dark:text-[#8AA895]" x-text="row.mc_no || '—'"></td>
                                    <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]" x-text="formatNum(row.mol_net, 3)"></td>
                                </tr>
                            </template>
                            <tr x-show="filteredMolasses.length === 0" x-cloak>
                                <td colspan="8" class="py-12 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                                    <i class="fas fa-inbox text-3xl mb-2 text-[#8AA895] block"></i>
                                    No molasses registry records found matching the filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="px-5 py-3 border-t border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex items-center justify-between"
                    x-show="molassesTotalPages > 1" x-cloak>
                    <button type="button" @click="molassesCurrentPage--" :disabled="molassesCurrentPage === 1"
                        class="kp-btn kp-btn-secondary text-xs px-3 py-1 disabled:opacity-40">Previous</button>
                    <span class="text-xs text-[#52705F] dark:text-[#8AA895]">
                        Page <strong class="text-[#172B20] dark:text-[#EAF5ED]" x-text="molassesCurrentPage"></strong> of <strong class="text-[#172B20] dark:text-[#EAF5ED]" x-text="molassesTotalPages"></strong>
                    </span>
                    <button type="button" @click="molassesCurrentPage++" :disabled="molassesCurrentPage >= molassesTotalPages"
                        class="kp-btn kp-btn-secondary text-xs px-3 py-1 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div x-show="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display: none;" x-cloak>
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showDeleteModal = false"></div>
            <div class="kp-modal relative max-w-lg w-full p-6 mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-trash text-rose-600 dark:text-rose-400"></i>
                        <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Delete Registry Records</h3>
                    </div>
                    <button type="button" @click="showDeleteModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895]">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Delete ALL (Both Quedan and Molasses) -->
                    <div class="border border-rose-300 dark:border-rose-900/50 rounded-xl p-4 bg-rose-50/70 dark:bg-rose-950/20">
                        <div class="flex items-center gap-2 mb-1.5">
                            <i class="fas fa-triangle-exclamation text-rose-600 dark:text-rose-400 text-sm"></i>
                            <h4 class="text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider">Delete ALL Records (Both Types)</h4>
                        </div>
                        <p class="text-xs text-rose-700 dark:text-rose-400 mb-3">Permanent purge of <strong>all</strong> quedan and molasses ledger entries.</p>
                        <div class="flex gap-2 mb-3">
                            <span class="bg-rose-200/80 dark:bg-rose-900/40 text-rose-900 dark:text-rose-200 text-xs px-2.5 py-0.5 rounded-full font-medium">
                                <strong x-text="totalQuedanCount"></strong> Quedans
                            </span>
                            <span class="bg-rose-200/80 dark:bg-rose-900/40 text-rose-900 dark:text-rose-200 text-xs px-2.5 py-0.5 rounded-full font-medium">
                                <strong x-text="totalMolassesCount"></strong> Molasses
                            </span>
                        </div>
                        <button type="button" @click="confirmDeleteAll()"
                            class="kp-btn kp-btn-danger w-full justify-center text-xs">
                            <i class="fas fa-exclamation-triangle mr-1.5"></i> Purge Entire Quedan & Molasses Registry
                        </button>
                    </div>

                    <!-- Delete Quedan Only -->
                    <div class="border border-amber-300 dark:border-amber-900/50 rounded-xl p-4 bg-amber-50/70 dark:bg-amber-950/20">
                        <div class="flex items-center gap-2 mb-1.5">
                            <i class="fas fa-qrcode text-amber-600 dark:text-amber-400 text-sm"></i>
                            <h4 class="text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">Delete Quedan Records Only</h4>
                        </div>
                        <div class="space-y-3">
                            <button type="button" @click="confirmDeleteByType('quedan', 'all')"
                                class="kp-btn w-full justify-center text-xs bg-amber-600 text-white hover:bg-amber-700">
                                <i class="fas fa-trash mr-1.5"></i> Delete ALL Quedan Records (<span x-text="totalQuedanCount"></span>)
                            </button>
                            <div class="pt-2 border-t border-amber-200 dark:border-amber-900/30">
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Crop Year</label>
                                        <select x-model="quedanDeleteForm.crop_year" @change="updateQuedanWeekOptions()"
                                            class="kp-input w-full py-1 text-xs">
                                            <option value="">Select Year</option>
                                            <template x-for="cy in quedanCropYears" :key="cy">
                                                <option :value="cy" x-text="cy"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Week No</label>
                                        <select x-model="quedanDeleteForm.week_no" :disabled="!quedanDeleteForm.crop_year"
                                            class="kp-input w-full py-1 text-xs disabled:opacity-50">
                                            <option value="">Select Week</option>
                                            <template x-for="wk in quedanWeeks" :key="wk.week_no">
                                                <option :value="wk.week_no" x-text="'Week ' + wk.week_no + ' (' + wk.count + ')'"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                                <button type="button" @click="confirmDeleteByType('quedan', 'week')"
                                    :disabled="!quedanDeleteForm.crop_year || !quedanDeleteForm.week_no"
                                    class="kp-btn kp-btn-secondary w-full justify-center text-xs disabled:opacity-50">
                                    Delete Quedan by Selected Week
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Molasses Only -->
                    <div class="border border-blue-300 dark:border-blue-900/50 rounded-xl p-4 bg-blue-50/70 dark:bg-blue-950/20">
                        <div class="flex items-center gap-2 mb-1.5">
                            <i class="fas fa-flask text-blue-600 dark:text-blue-400 text-sm"></i>
                            <h4 class="text-xs font-bold text-blue-800 dark:text-blue-300 uppercase tracking-wider">Delete Molasses Records Only</h4>
                        </div>
                        <div class="space-y-3">
                            <button type="button" @click="confirmDeleteByType('molasses', 'all')"
                                class="kp-btn w-full justify-center text-xs bg-blue-600 text-white hover:bg-blue-700">
                                <i class="fas fa-trash mr-1.5"></i> Delete ALL Molasses Records (<span x-text="totalMolassesCount"></span>)
                            </button>
                            <div class="pt-2 border-t border-blue-200 dark:border-blue-900/30">
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Crop Year</label>
                                        <select x-model="molassesDeleteForm.crop_year" @change="updateMolassesWeekOptions()"
                                            class="kp-input w-full py-1 text-xs">
                                            <option value="">Select Year</option>
                                            <template x-for="cy in molassesCropYears" :key="cy">
                                                <option :value="cy" x-text="cy"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Week No</label>
                                        <select x-model="molassesDeleteForm.week_no" :disabled="!molassesDeleteForm.crop_year"
                                            class="kp-input w-full py-1 text-xs disabled:opacity-50">
                                            <option value="">Select Week</option>
                                            <template x-for="wk in molassesWeeks" :key="wk.week_no">
                                                <option :value="wk.week_no" x-text="'Week ' + wk.week_no + ' (' + wk.count + ')'"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                                <button type="button" @click="confirmDeleteByType('molasses', 'week')"
                                    :disabled="!molassesDeleteForm.crop_year || !molassesDeleteForm.week_no"
                                    class="kp-btn kp-btn-secondary w-full justify-center text-xs disabled:opacity-50">
                                    Delete Molasses by Selected Week
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <button type="button" @click="showDeleteModal = false; resetDeleteForms()"
                        class="kp-btn kp-btn-secondary w-full justify-center text-xs">
                        Cancel & Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function registryData() {
                return {
                    activeTab: 'quedan',
                    deleteType: 'quedan',
                    showDeleteModal: false,

                    quedanDeleteForm: { crop_year: '', week_no: '' },
                    molassesDeleteForm: { crop_year: '', week_no: '' },

                    quedanCropYears: [],
                    quedanWeeks: [],
                    quedanAllWeeksData: [],
                    molassesCropYears: [],
                    molassesWeeks: [],
                    molassesAllWeeksData: [],

                    totalQuedanCount: 0,
                    totalMolassesCount: 0,

                    quedanSearch: '',
                    quedanSortField: 'null',
                    quedanSortDir: 'asc',
                    quedanCurrentPage: 1,
                    quedanSelectedIds: [],
                    perPage: 25,
                    allQuedans: @json($quedans),

                    molassesSearch: '',
                    molassesSortField: 'null',
                    molassesSortDir: 'asc',
                    molassesCurrentPage: 1,
                    molassesSelectedIds: [],
                    allMolasses: @json($molassesList),

                    initRegistry() {
                        this.totalQuedanCount = this.allQuedans.length;
                        this.totalMolassesCount = this.allMolasses.length;
                        this.fetchAllFilters();
                    },

                    resetSelections() {
                        this.quedanSelectedIds = [];
                        this.molassesSelectedIds = [];
                    },

                    resetDeleteForms() {
                        this.quedanDeleteForm = { crop_year: '', week_no: '' };
                        this.molassesDeleteForm = { crop_year: '', week_no: '' };
                        this.quedanWeeks = [];
                        this.molassesWeeks = [];
                    },

                    async fetchAllFilters() {
                        try {
                            const quedanRes = await fetch('{{ route('registry.filters') }}?type=quedan', {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const quedanData = await quedanRes.json();
                            this.quedanCropYears = quedanData.crop_years || [];
                            this.quedanAllWeeksData = quedanData.weeks_data || [];

                            const molassesRes = await fetch('{{ route('registry.filters') }}?type=molasses', {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const molassesData = await molassesRes.json();
                            this.molassesCropYears = molassesData.crop_years || [];
                            this.molassesAllWeeksData = molassesData.weeks_data || [];
                        } catch (error) {
                            console.error('Error fetching filters:', error);
                        }
                    },

                    updateQuedanWeekOptions() {
                        this.quedanDeleteForm.week_no = '';
                        if (this.quedanDeleteForm.crop_year && this.quedanAllWeeksData.length > 0) {
                            this.quedanWeeks = this.quedanAllWeeksData
                                .filter(w => w.crop_year === this.quedanDeleteForm.crop_year)
                                .map(w => ({ week_no: w.week_no, count: w.count }))
                                .sort((a, b) => parseInt(a.week_no) - parseInt(b.week_no));
                        } else {
                            this.quedanWeeks = [];
                        }
                    },

                    updateMolassesWeekOptions() {
                        this.molassesDeleteForm.week_no = '';
                        if (this.molassesDeleteForm.crop_year && this.molassesAllWeeksData.length > 0) {
                            this.molassesWeeks = this.molassesAllWeeksData
                                .filter(w => w.crop_year === this.molassesDeleteForm.crop_year)
                                .map(w => ({ week_no: w.week_no, count: w.count }))
                                .sort((a, b) => parseInt(a.week_no) - parseInt(b.week_no));
                        } else {
                            this.molassesWeeks = [];
                        }
                    },

                    get filteredQuedans() {
                        let data = [...this.allQuedans];
                        if (this.quedanSearch) {
                            const s = this.quedanSearch.toLowerCase();
                            data = data.filter(d => (d.planter_name || '').toLowerCase().includes(s) || (d.planter_code || '').toLowerCase().includes(s) || (d.qdn_no || '').toLowerCase().includes(s));
                        }
                        if (this.quedanSortField && this.quedanSortField !== 'null') {
                            data.sort((a, b) => {
                                let va = a[this.quedanSortField] ?? '';
                                let vb = b[this.quedanSortField] ?? '';
                                if (!isNaN(va) && !isNaN(vb)) {
                                    return this.quedanSortDir === 'asc' ? va - vb : vb - va;
                                }
                                return this.quedanSortDir === 'asc' ? String(va).localeCompare(String(vb)) : String(vb).localeCompare(String(va));
                            });
                        }
                        return data;
                    },

                    get paginatedQuedans() {
                        const start = (this.quedanCurrentPage - 1) * this.perPage;
                        return this.filteredQuedans.slice(start, start + this.perPage);
                    },

                    get quedanTotalPages() {
                        return Math.ceil(this.filteredQuedans.length / this.perPage) || 1;
                    },

                    get isAllQuedanSelected() {
                        return this.paginatedQuedans.length > 0 && this.paginatedQuedans.every(r => this.quedanSelectedIds.includes(r.id));
                    },

                    toggleSelectAllQuedan() {
                        if (this.isAllQuedanSelected) {
                            const currentIds = this.paginatedQuedans.map(r => r.id);
                            this.quedanSelectedIds = this.quedanSelectedIds.filter(id => !currentIds.includes(id));
                        } else {
                            const currentIds = this.paginatedQuedans.map(r => r.id);
                            this.quedanSelectedIds = [...new Set([...this.quedanSelectedIds, ...currentIds])];
                        }
                    },

                    sortQuedan(field) {
                        if (this.quedanSortField === field) {
                            this.quedanSortDir = this.quedanSortDir === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.quedanSortField = field;
                            this.quedanSortDir = 'asc';
                        }
                    },

                    get filteredMolasses() {
                        let data = [...this.allMolasses];
                        if (this.molassesSearch) {
                            const s = this.molassesSearch.toLowerCase();
                            data = data.filter(d => (d.planter_name || '').toLowerCase().includes(s) || (d.planter_code || '').toLowerCase().includes(s) || (d.mc_no || '').toLowerCase().includes(s));
                        }
                        if (this.molassesSortField && this.molassesSortField !== 'null') {
                            data.sort((a, b) => {
                                let va = a[this.molassesSortField] ?? '';
                                let vb = b[this.molassesSortField] ?? '';
                                if (!isNaN(va) && !isNaN(vb)) {
                                    return this.molassesSortDir === 'asc' ? va - vb : vb - va;
                                }
                                return this.molassesSortDir === 'asc' ? String(va).localeCompare(String(vb)) : String(vb).localeCompare(String(va));
                            });
                        }
                        return data;
                    },

                    get paginatedMolasses() {
                        const start = (this.molassesCurrentPage - 1) * this.perPage;
                        return this.filteredMolasses.slice(start, start + this.perPage);
                    },

                    get molassesTotalPages() {
                        return Math.ceil(this.filteredMolasses.length / this.perPage) || 1;
                    },

                    get isAllMolassesSelected() {
                        return this.paginatedMolasses.length > 0 && this.paginatedMolasses.every(r => this.molassesSelectedIds.includes(r.id));
                    },

                    toggleSelectAllMolasses() {
                        if (this.isAllMolassesSelected) {
                            const currentIds = this.paginatedMolasses.map(r => r.id);
                            this.molassesSelectedIds = this.molassesSelectedIds.filter(id => !currentIds.includes(id));
                        } else {
                            const currentIds = this.paginatedMolasses.map(r => r.id);
                            this.molassesSelectedIds = [...new Set([...this.molassesSelectedIds, ...currentIds])];
                        }
                    },

                    sortMolasses(field) {
                        if (this.molassesSortField === field) {
                            this.molassesSortDir = this.molassesSortDir === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.molassesSortField = field;
                            this.molassesSortDir = 'asc';
                        }
                    },

                    async confirmDeleteAll() {
                        const result = await Swal.fire({
                            title: 'Delete ALL Quedan & Molasses Records?',
                            text: 'This will permanently delete all records across both registries. This cannot be undone!',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete everything',
                            confirmButtonColor: '#dc2626',
                            cancelButtonText: 'Cancel'
                        });

                        if (!result.isConfirmed) return;

                        let successCount = 0;
                        try {
                            const res1 = await fetch('{{ route('registry.quedan.delete-all') }}', {
                                method: 'DELETE',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                            });
                            if (res1.ok) successCount++;
                        } catch (e) { console.error(e); }

                        try {
                            const res2 = await fetch('{{ route('registry.molasses.delete-all') }}', {
                                method: 'DELETE',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                            });
                            if (res2.ok) successCount++;
                        } catch (e) { console.error(e); }

                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'All quedan and molasses records deleted.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        this.showDeleteModal = false;
                        setTimeout(() => location.reload(), 1200);
                    },

                    async confirmDeleteByType(type, scope) {
                        let title, text, route, body = {};
                        const label = type === 'quedan' ? 'Quedan' : 'Molasses';

                        if (scope === 'all') {
                            title = `Delete ALL ${label} Records?`;
                            text = `This will delete all ${label} records. This cannot be undone!`;
                            route = type === 'quedan' ? '{{ route('registry.quedan.delete-all') }}' : '{{ route('registry.molasses.delete-all') }}';
                        } else {
                            const form = type === 'quedan' ? this.quedanDeleteForm : this.molassesDeleteForm;
                            if (!form.crop_year || !form.week_no) {
                                Swal.fire({ icon: 'warning', title: 'Required', text: 'Please select both crop year and week number.' });
                                return;
                            }
                            title = `Delete ${label} Records?`;
                            text = `Delete ${label} records for ${form.crop_year} Week ${form.week_no}?`;
                            route = type === 'quedan' ? '{{ route('registry.quedan.delete-by-week') }}' : '{{ route('registry.molasses.delete-by-week') }}';
                            body = { crop_year: form.crop_year, week_no: form.week_no };
                        }

                        const result = await Swal.fire({
                            title: title,
                            text: text,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete',
                            confirmButtonColor: '#dc2626',
                            cancelButtonText: 'Cancel'
                        });

                        if (!result.isConfirmed) return;

                        try {
                            const response = await fetch(route, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: scope === 'week' ? JSON.stringify(body) : undefined
                            });
                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message);

                            Swal.fire({ icon: 'success', title: 'Deleted!', text: data.message, timer: 2000, showConfirmButton: false });
                            this.resetDeleteForms();
                            this.showDeleteModal = false;
                            setTimeout(() => location.reload(), 1200);
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
                        }
                    },

                    async confirmDeleteSelected(type) {
                        const ids = type === 'quedan' ? this.quedanSelectedIds : this.molassesSelectedIds;
                        if (ids.length === 0) {
                            Swal.fire({ icon: 'warning', title: 'No Selection', text: 'Please select records to delete.' });
                            return;
                        }

                        const label = type === 'quedan' ? 'Quedan' : 'Molasses';
                        const route = type === 'quedan' ? '{{ route('registry.quedan.delete-selected') }}' : '{{ route('registry.molasses.delete-selected') }}';

                        const result = await Swal.fire({
                            title: 'Delete Selected Records?',
                            text: `Delete ${ids.length} ${label} record(s)?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete',
                            confirmButtonColor: '#dc2626'
                        });

                        if (!result.isConfirmed) return;

                        try {
                            const response = await fetch(route, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({ ids: ids })
                            });
                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message);

                            Swal.fire({ icon: 'success', title: 'Deleted!', text: data.message, timer: 2000, showConfirmButton: false });
                            if (type === 'quedan') this.quedanSelectedIds = [];
                            else this.molassesSelectedIds = [];
                            setTimeout(() => location.reload(), 1200);
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
                        }
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
