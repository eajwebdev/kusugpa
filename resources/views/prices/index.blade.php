<!-- resources/views/prices/index.blade.php -->
@extends('layouts.main')

@section('title', 'Price Management')

@section('content')
    <div x-data="priceData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Market Rates & Pricing</p>
                    <h1 class="kp-page-title mt-1">Price Management</h1>
                    <p class="kp-page-sub">Configure weekly market prices for sugar quedan classes and molasses production.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('prices.registry') }}" class="kp-btn kp-btn-secondary">
                        <i class="fas fa-history mr-1.5" aria-hidden="true"></i> Price Registry
                    </a>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Quedan Prices Section -->
            <div class="kp-card overflow-hidden">
                <div class="p-5 border-b flex flex-wrap items-center justify-between gap-3" style="border-color: var(--kp-border)">
                    <div class="flex items-center gap-2.5">
                        <i class="fas fa-tag text-base" style="color: var(--kp-forest)"></i>
                        <div>
                            <h2 class="font-display text-base font-semibold" style="color: var(--kp-text)">Quedan Prices</h2>
                            <p class="text-xs" style="color: var(--kp-muted)">Weekly sugar quedans per Lkg</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <select x-model="quedanCropYearFilter" @change="filterQuedan()" class="kp-select text-xs py-1 px-2.5">
                            <option value="">All Crop Years</option>
                            <template x-for="cy in cropYears" :key="cy.id">
                                <option :value="cy.crop_year" x-text="cy.crop_year"></option>
                            </template>
                        </select>
                        <span class="text-xs font-mono font-medium px-2 py-1 rounded border"
                            style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-muted)"
                            x-text="filteredQuedan.length + ' entries'"></span>
                    </div>
                </div>
                <div class="kp-table-wrap" style="max-height: 480px; overflow-y: auto;">
                    <table class="kp-table">
                        <thead>
                            <tr>
                                <th class="cursor-pointer select-none" @click="sortQuedan('crop_year')">
                                    <div class="flex items-center gap-1.5">
                                        <span>Season</span>
                                        <i class="fas text-[10px]" :class="quedanSortField === 'crop_year' ? (quedanSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center cursor-pointer select-none" @click="sortQuedan('week_no')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>Week</span>
                                        <i class="fas text-[10px]" :class="quedanSortField === 'week_no' ? (quedanSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center">Class / Type</th>
                                <th class="text-right cursor-pointer select-none" @click="sortQuedan('quedan_price')">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span>Price / Lkg</span>
                                        <i class="fas text-[10px]" :class="quedanSortField === 'quedan_price' ? (quedanSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="price in sortedQuedan" :key="price.id">
                                <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                    <td>
                                        <span class="font-mono text-xs px-2 py-0.5 rounded border"
                                            style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-text)"
                                            x-text="price.crop_year"></span>
                                    </td>
                                    <td class="text-center font-mono font-semibold" style="color: var(--kp-text)"
                                        x-text="'Week ' + price.week_no"></td>
                                    <td class="text-center">
                                        <span class="kp-badge kp-badge-emerald font-semibold uppercase tracking-wider"
                                            x-text="price.quedan_type || 'STANDARD'"></span>
                                    </td>
                                    <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-text)"
                                        x-text="'₱' + formatNumber(price.quedan_price)"></td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button @click="editQuedan(price)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] transition"
                                                style="color: var(--kp-cane)" title="Edit Price">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button @click="deleteQuedan(price.id)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] transition text-red-600"
                                                title="Delete Price">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredQuedan.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                    <i class="fas fa-tag text-3xl mb-2 block opacity-30"></i>
                                    No quedan prices recorded for this selection.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Molasses Prices Section -->
            <div class="kp-card overflow-hidden">
                <div class="p-5 border-b flex flex-wrap items-center justify-between gap-3" style="border-color: var(--kp-border)">
                    <div class="flex items-center gap-2.5">
                        <i class="fas fa-flask text-base" style="color: var(--kp-forest)"></i>
                        <div>
                            <h2 class="font-display text-base font-semibold" style="color: var(--kp-text)">Molasses Prices</h2>
                            <p class="text-xs" style="color: var(--kp-muted)">Weekly molasses rates per metric ton</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <select x-model="molassesCropYearFilter" @change="filterMolasses()" class="kp-select text-xs py-1 px-2.5">
                            <option value="">All Crop Years</option>
                            <template x-for="cy in cropYears" :key="cy.id">
                                <option :value="cy.crop_year" x-text="cy.crop_year"></option>
                            </template>
                        </select>
                        <span class="text-xs font-mono font-medium px-2 py-1 rounded border"
                            style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-muted)"
                            x-text="filteredMolasses.length + ' entries'"></span>
                    </div>
                </div>
                <div class="kp-table-wrap" style="max-height: 480px; overflow-y: auto;">
                    <table class="kp-table">
                        <thead>
                            <tr>
                                <th class="cursor-pointer select-none" @click="sortMolasses('crop_year')">
                                    <div class="flex items-center gap-1.5">
                                        <span>Season</span>
                                        <i class="fas text-[10px]" :class="molassesSortField === 'crop_year' ? (molassesSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center cursor-pointer select-none" @click="sortMolasses('week_no')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>Week</span>
                                        <i class="fas text-[10px]" :class="molassesSortField === 'week_no' ? (molassesSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-right cursor-pointer select-none" @click="sortMolasses('mol_price')">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span>Price / MT</span>
                                        <i class="fas text-[10px]" :class="molassesSortField === 'mol_price' ? (molassesSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="price in sortedMolasses" :key="price.id">
                                <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                    <td>
                                        <span class="font-mono text-xs px-2 py-0.5 rounded border"
                                            style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-text)"
                                            x-text="price.crop_year"></span>
                                    </td>
                                    <td class="text-center font-mono font-semibold" style="color: var(--kp-text)"
                                        x-text="'Week ' + price.week_no"></td>
                                    <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-text)"
                                        x-text="'₱' + formatNumber(price.mol_price)"></td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button @click="editMolasses(price)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] transition"
                                                style="color: var(--kp-cane)" title="Edit Price">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button @click="deleteMolasses(price.id)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] transition text-red-600"
                                                title="Delete Price">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredMolasses.length === 0">
                                <td colspan="4" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                    <i class="fas fa-flask text-3xl mb-2 block opacity-30"></i>
                                    No molasses prices recorded for this selection.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit Quedan Modal -->
        <div x-show="showQuedanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showQuedanModal = false"></div>
            <div class="relative rounded-xl border max-w-md w-full p-6 shadow-2xl"
                style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                    <h3 class="font-display text-lg font-semibold" style="color: var(--kp-text)">Edit Quedan Price</h3>
                    <button @click="showQuedanModal = false" class="p-1 rounded text-xs hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <form @submit.prevent="saveQuedan()" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Quedan Type / Class *</label>
                        <input type="text" x-model="quedanForm.quedan_type" placeholder="e.g., A, B, C, D" required class="kp-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Price per Lkg (₱) *</label>
                        <input type="number" x-model="quedanForm.quedan_price" step="0.01" placeholder="0.00" required class="kp-input w-full">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Crop Year *</label>
                            <input type="text" x-model="quedanForm.crop_year" placeholder="20252026" required class="kp-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Week Number *</label>
                            <input type="text" x-model="quedanForm.week_no" placeholder="1-52" required class="kp-input w-full">
                        </div>
                    </div>
                    <div class="flex gap-2 pt-3 border-t" style="border-color: var(--kp-border)">
                        <button type="button" @click="showQuedanModal = false" class="kp-btn kp-btn-secondary flex-1">Cancel</button>
                        <button type="submit" class="kp-btn kp-btn-primary flex-1">
                            <i class="fas fa-save mr-1.5"></i> Save Quedan Price
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Molasses Modal -->
        <div x-show="showMolassesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showMolassesModal = false"></div>
            <div class="relative rounded-xl border max-w-md w-full p-6 shadow-2xl"
                style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                    <h3 class="font-display text-lg font-semibold" style="color: var(--kp-text)">Edit Molasses Price</h3>
                    <button @click="showMolassesModal = false" class="p-1 rounded text-xs hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <form @submit.prevent="saveMolasses()" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Price per Metric Ton (₱) *</label>
                        <input type="number" x-model="molassesForm.mol_price" step="0.01" placeholder="0.00" required class="kp-input w-full">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Crop Year *</label>
                            <input type="text" x-model="molassesForm.crop_year" placeholder="20252026" required class="kp-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Week Number *</label>
                            <input type="text" x-model="molassesForm.week_no" placeholder="1-52" required class="kp-input w-full">
                        </div>
                    </div>
                    <div class="flex gap-2 pt-3 border-t" style="border-color: var(--kp-border)">
                        <button type="button" @click="showMolassesModal = false" class="kp-btn kp-btn-secondary flex-1">Cancel</button>
                        <button type="submit" class="kp-btn kp-btn-primary flex-1">
                            <i class="fas fa-save mr-1.5"></i> Save Molasses Price
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function priceData() {
            return {
                showQuedanModal: false,
                showMolassesModal: false,
                editingQuedanId: null,
                editingMolassesId: null,
                quedanForm: {
                    quedan_type: '',
                    quedan_price: '',
                    crop_year: '',
                    week_no: ''
                },
                molassesForm: {
                    mol_price: '',
                    crop_year: '',
                    week_no: ''
                },
                quedanSortField: 'crop_year',
                quedanSortDir: 'asc',
                molassesSortField: 'crop_year',
                molassesSortDir: 'asc',
                quedanCropYearFilter: '',
                molassesCropYearFilter: '',
                cropYears: {!! json_encode($cropYearsData) !!},
                quedanPrices: {!! json_encode($quedanPricesData) !!},
                molassesPrices: {!! json_encode($molassesPricesData) !!},

                get filteredQuedan() {
                    if (!this.quedanCropYearFilter) return this.quedanPrices;
                    return this.quedanPrices.filter(p => p.crop_year === this.quedanCropYearFilter);
                },
                get sortedQuedan() {
                    return [...this.filteredQuedan].sort((a, b) => {
                        let valA = a[this.quedanSortField] ?? '';
                        let valB = b[this.quedanSortField] ?? '';

                        if (this.quedanSortField === 'week_no' || this.quedanSortField === 'quedan_price') {
                            valA = parseFloat(valA) || 0;
                            valB = parseFloat(valB) || 0;
                        } else if (typeof valA === 'string') {
                            valA = valA.toLowerCase();
                            valB = valB.toLowerCase();
                        }

                        if (valA < valB) return this.quedanSortDir === 'asc' ? -1 : 1;
                        if (valA > valB) return this.quedanSortDir === 'asc' ? 1 : -1;
                        return 0;
                    });
                },
                get filteredMolasses() {
                    if (!this.molassesCropYearFilter) return this.molassesPrices;
                    return this.molassesPrices.filter(p => p.crop_year === this.molassesCropYearFilter);
                },
                get sortedMolasses() {
                    return [...this.filteredMolasses].sort((a, b) => {
                        let valA = a[this.molassesSortField] ?? '';
                        let valB = b[this.molassesSortField] ?? '';

                        if (this.molassesSortField === 'week_no' || this.molassesSortField === 'mol_price') {
                            valA = parseFloat(valA) || 0;
                            valB = parseFloat(valB) || 0;
                        } else if (typeof valA === 'string') {
                            valA = valA.toLowerCase();
                            valB = valB.toLowerCase();
                        }

                        if (valA < valB) return this.molassesSortDir === 'asc' ? -1 : 1;
                        if (valA > valB) return this.molassesSortDir === 'asc' ? 1 : -1;
                        return 0;
                    });
                },

                sortQuedan(field) {
                    if (this.quedanSortField === field) {
                        this.quedanSortDir = this.quedanSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.quedanSortField = field;
                        this.quedanSortDir = 'asc';
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
                filterQuedan() {},
                filterMolasses() {},

                editQuedan(price) {
                    this.editingQuedanId = price.id;
                    this.quedanForm = {
                        quedan_type: price.quedan_type,
                        quedan_price: price.quedan_price,
                        crop_year: price.crop_year,
                        week_no: price.week_no
                    };
                    this.showQuedanModal = true;
                },
                async saveQuedan() {
                    try {
                        const r = await fetch('/prices/quedan/' + this.editingQuedanId, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.quedanForm)
                        });
                        const d = await r.json();
                        if (!r.ok) throw new Error(d.message);
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: d.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        this.showQuedanModal = false;
                        setTimeout(() => location.reload(), 1500);
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: e.message
                        });
                    }
                },
                async deleteQuedan(id) {
                    const result = await Swal.fire({
                        title: 'Delete Price?',
                        text: 'This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete',
                        confirmButtonColor: '#832727'
                    });
                    if (!result.isConfirmed) return;
                    try {
                        const r = await fetch('/prices/quedan/' + id, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const d = await r.json();
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: d.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        setTimeout(() => location.reload(), 1500);
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: e.message
                        });
                    }
                },

                editMolasses(price) {
                    this.editingMolassesId = price.id;
                    this.molassesForm = {
                        mol_price: price.mol_price,
                        crop_year: price.crop_year,
                        week_no: price.week_no
                    };
                    this.showMolassesModal = true;
                },
                async saveMolasses() {
                    try {
                        const r = await fetch('/prices/molasses/' + this.editingMolassesId, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.molassesForm)
                        });
                        const d = await r.json();
                        if (!r.ok) throw new Error(d.message);
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: d.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        this.showMolassesModal = false;
                        setTimeout(() => location.reload(), 1500);
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: e.message
                        });
                    }
                },
                async deleteMolasses(id) {
                    const result = await Swal.fire({
                        title: 'Delete Price?',
                        text: 'This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete',
                        confirmButtonColor: '#832727'
                    });
                    if (!result.isConfirmed) return;
                    try {
                        const r = await fetch('/prices/molasses/' + id, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const d = await r.json();
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: d.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        setTimeout(() => location.reload(), 1500);
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: e.message
                        });
                    }
                },

                formatNumber(num) {
                    return parseFloat(num || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            };
        }
    </script>
@endsection
