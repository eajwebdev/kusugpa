<!-- resources/views/planter-profiles/index.blade.php -->
@extends('layouts.main')

@section('title', 'Planter Profiles')

@section('content')
    <div x-data="planterData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Directory & Membership</p>
                    <h1 class="kp-page-title mt-1">Planter Profiles</h1>
                    <p class="kp-page-sub">Manage planter member records, operational status, and production history.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
                            auth()->user()->hasPermission('manage-planter-profiles'))
                        <button @click="openAddModal()" class="kp-btn kp-btn-primary">
                            <i class="fas fa-plus-circle mr-1.5" aria-hidden="true"></i> Add Planter
                        </button>
                        <button @click="syncPlanters()" class="kp-btn kp-btn-secondary">
                            <i class="fas fa-sync mr-1.5" aria-hidden="true"></i> Sync
                        </button>
                    @endif
                    <a href="{{ route('planter-profiles.export') }}" data-pdf-preview="Planter Profiles" class="kp-btn kp-btn-secondary">
                        <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Preview PDF
                    </a>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Stats Strip -->
        <section aria-label="Planter summary metrics" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-address-book" aria-hidden="true"></i> Total Planters</p>
                <p class="kp-kpi-value kp-num">{{ $stats['total'] }}</p>
                <p class="kp-kpi-sub">Total registered members</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-check-circle" aria-hidden="true"></i> Active</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-emerald)">{{ $stats['active'] }}</p>
                <p class="kp-kpi-sub">Good standing profiles</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-pause-circle" aria-hidden="true"></i> Inactive</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-muted)">{{ $stats['inactive'] }}</p>
                <p class="kp-kpi-sub">Dormant member profiles</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-calendar-plus" aria-hidden="true"></i> New This Month</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">{{ $stats['new_this_month'] }}</p>
                <p class="kp-kpi-sub">Recently registered</p>
            </div>
        </section>

        <!-- Filter Bar -->
        <div class="kp-card p-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search Planters</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                        <input type="text" x-model="search" @input="applyFilters()" placeholder="Search by planter name or code..."
                            class="kp-input w-full pl-9">
                    </div>
                </div>
                <div class="w-48">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Status</label>
                    <select x-model="statusFilter" @change="applyFilters()" class="kp-select w-full">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <button @click="clearFilters()" class="kp-btn kp-btn-secondary h-[38px]">
                    <i class="fas fa-times mr-1"></i> Reset
                </button>
                <span class="text-xs font-mono ml-auto py-2" style="color: var(--kp-muted)"
                    x-text="'Showing ' + filteredPlanters.length + ' planters'"></span>
            </div>
        </div>

        <!-- Table Container -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table">
                    <thead>
                        <tr>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_code')">
                                <div class="flex items-center gap-1.5">
                                    <span>Code</span>
                                    <i class="fas text-[10px]" :class="sortField === 'planter_code' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_name')">
                                <div class="flex items-center gap-1.5">
                                    <span>Planter Name</span>
                                    <i class="fas text-[10px]" :class="sortField === 'planter_name' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th>Contact</th>
                            <th>Area Location</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Total Cane</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="planter in paginatedPlanters" :key="planter.id">
                            <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                <td class="font-mono font-semibold" style="color: var(--kp-text)" x-text="planter.planter_code"></td>
                                <td>
                                    <a :href="'/planter-profiles/' + planter.id"
                                        class="font-medium hover:underline"
                                        style="color: var(--kp-emerald)"
                                        x-text="planter.planter_name"></a>
                                </td>
                                <td style="color: var(--kp-muted)" x-text="planter.contact_number || '—'"></td>
                                <td style="color: var(--kp-muted)" x-text="planter.area_location || '—'"></td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="kp-badge"
                                            :class="{
                                                'kp-badge-emerald': planter.status === 'active',
                                                'kp-badge-rose': planter.status === 'suspended',
                                                'kp-badge-neutral': planter.status === 'inactive'
                                            }">
                                            <span class="w-1.5 h-1.5 rounded-full"
                                                :class="{
                                                    'bg-emerald-500 animate-pulse': planter.status === 'active',
                                                    'bg-red-500': planter.status === 'suspended',
                                                    'bg-gray-400': planter.status === 'inactive'
                                                }"></span>
                                            <span x-text="planter.status.charAt(0).toUpperCase() + planter.status.slice(1)"></span>
                                        </span>

                                        @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
                                                auth()->user()->hasPermission('manage-planter-profiles'))
                                            <button @click.stop="toggleStatus(planter)"
                                                class="p-1 rounded transition hover:bg-[var(--kp-surface-2)]"
                                                :style="planter.status === 'active' ? 'color: var(--kp-emerald)' : 'color: var(--kp-muted)'"
                                                :title="planter.status === 'active' ? 'Click to deactivate' : 'Click to activate'">
                                                <i class="fas fa-power-off text-xs"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right font-mono font-medium tabular" style="color: var(--kp-text)"
                                    x-text="formatNumber(getPlanterStat(planter.planter_code, 'total_cane'), 2) + ' tons'">
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <a :href="'/planter-profiles/' + planter.id"
                                            class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold"
                                            style="color: var(--kp-forest)" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
                                                auth()->user()->hasPermission('manage-planter-profiles'))
                                            <button @click.stop="editPlanter(planter)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold"
                                                style="color: var(--kp-cane)" title="Edit Planter">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredPlanters.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                <i class="fas fa-address-book text-3xl mb-2 block opacity-30"></i>
                                No planters match the current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t flex items-center justify-between"
                style="border-color: var(--kp-border); background: var(--kp-surface-2)"
                x-show="totalPages > 1">
                <button @click="currentPage--" :disabled="currentPage === 1"
                    class="kp-btn kp-btn-secondary py-1 px-3 text-xs disabled:opacity-50">
                    <i class="fas fa-chevron-left mr-1"></i> Previous
                </button>
                <span class="text-xs font-medium tabular" style="color: var(--kp-muted)" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
                <button @click="currentPage++" :disabled="currentPage >= totalPages"
                    class="kp-btn kp-btn-secondary py-1 px-3 text-xs disabled:opacity-50">
                    Next <i class="fas fa-chevron-right ml-1"></i>
                </button>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>
            <div class="relative rounded-xl border max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto"
                style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                    <h3 class="font-display text-lg font-semibold" style="color: var(--kp-text)"
                        x-text="editingPlanter ? 'Edit Planter Profile' : 'Add New Planter'">
                    </h3>
                    <button @click="showModal = false" class="text-xs p-1 rounded hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <form @submit.prevent="savePlanter()" class="space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Planter Code *</label>
                            <input type="text" x-model="form.planter_code" placeholder="e.g. 1001" required class="kp-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Planter Name *</label>
                            <input type="text" x-model="form.planter_name" placeholder="Full name" required class="kp-input w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Contact Number</label>
                        <input type="text" x-model="form.contact_number" placeholder="e.g. 0917-123-4567" class="kp-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Address</label>
                        <input type="text" x-model="form.address" placeholder="Barangay, City / Municipality" class="kp-input w-full">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Area Location</label>
                            <input type="text" x-model="form.area_location" placeholder="e.g. Hacienda San Jose" class="kp-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Total Area (ha)</label>
                            <input type="number" x-model="form.total_area" placeholder="0.00" step="0.01" class="kp-input w-full">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Status</label>
                            <select x-model="form.status" class="kp-select w-full">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Membership Date</label>
                            <input type="date" x-model="form.membership_date" class="kp-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Crop Year</label>
                            <input type="text" x-model="form.crop_year" placeholder="2023-2024" class="kp-input w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Notes</label>
                        <textarea x-model="form.notes" placeholder="Additional remarks or notes..." rows="2" class="kp-input w-full"></textarea>
                    </div>
                    <div class="flex gap-2 pt-3 border-t" style="border-color: var(--kp-border)">
                        <button type="button" @click="showModal = false" class="kp-btn kp-btn-secondary flex-1">Cancel</button>
                        <button type="submit" class="kp-btn kp-btn-primary flex-1">
                            <i class="fas fa-save mr-1.5"></i> Save Planter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function planterData() {
                return {
                    allPlanters: @json($allPlanters),
                    planterStats: @json($planterStats),
                    search: '',
                    statusFilter: 'all',
                    sortField: 'planter_name',
                    sortDirection: 'asc',
                    currentPage: 1,
                    perPage: 25,
                    showModal: false,
                    editingPlanter: null,
                    form: {
                        planter_code: '',
                        planter_name: '',
                        contact_number: '',
                        address: '',
                        area_location: '',
                        total_area: '',
                        status: 'active',
                        membership_date: '',
                        crop_year: '',
                        notes: ''
                    },

                    get filteredPlanters() {
                        let planters = [...this.allPlanters];
                        if (this.search) {
                            const s = this.search.toLowerCase();
                            planters = planters.filter(p => (p.planter_name || '').toLowerCase().includes(s) || (p
                                .planter_code || '').toLowerCase().includes(s));
                        }
                        if (this.statusFilter !== 'all') {
                            planters = planters.filter(p => p.status === this.statusFilter);
                        }
                        planters.sort((a, b) => {
                            let valA = a[this.sortField] ?? '',
                                valB = b[this.sortField] ?? '';
                            if (typeof valA === 'string') valA = valA.toLowerCase();
                            if (typeof valB === 'string') valB = valB.toLowerCase();
                            if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                            if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
                            return 0;
                        });
                        return planters;
                    },
                    get paginatedPlanters() {
                        const start = (this.currentPage - 1) * this.perPage;
                        return this.filteredPlanters.slice(start, start + this.perPage);
                    },
                    get totalPages() {
                        return Math.ceil(this.filteredPlanters.length / this.perPage);
                    },
                    applyFilters() {
                        this.currentPage = 1;
                    },
                    sortBy(field) {
                        if (this.sortField === field) {
                            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.sortField = field;
                            this.sortDirection = 'asc';
                        }
                    },
                    clearFilters() {
                        this.search = '';
                        this.statusFilter = 'all';
                        this.applyFilters();
                    },
                    getPlanterStat(code, field) {
                        const stat = this.planterStats[code];
                        return stat ? parseFloat(stat[field] || 0) : 0;
                    },
                    formatNumber(num, decimals = 2) {
                        return parseFloat(num || 0).toLocaleString('en-US', {
                            minimumFractionDigits: decimals,
                            maximumFractionDigits: decimals
                        });
                    },
                    openAddModal() {
                        this.editingPlanter = null;
                        this.form = {
                            planter_code: '',
                            planter_name: '',
                            contact_number: '',
                            address: '',
                            area_location: '',
                            total_area: '',
                            status: 'active',
                            membership_date: '',
                            crop_year: '',
                            notes: ''
                        };
                        this.showModal = true;
                    },
                    editPlanter(planter) {
                        this.editingPlanter = planter;
                        this.form = {
                            ...planter
                        };
                        this.showModal = true;
                    },
                    async savePlanter() {
                        const url = this.editingPlanter ? '/planter-profiles/' + this.editingPlanter.id :
                            '/planter-profiles';
                        const method = this.editingPlanter ? 'PUT' : 'POST';
                        try {
                            const r = await fetch(url, {
                                method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(this.form)
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
                            this.showModal = false;
                            setTimeout(() => location.reload(), 1500);
                        } catch (e) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: e.message
                            });
                        }
                    },
                    async toggleStatus(planter) {
                        try {
                            const r = await fetch('/planter-profiles/' + planter.id + '/toggle-status', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const d = await r.json();

                            // Update the planter in the allPlanters array
                            const index = this.allPlanters.findIndex(p => p.id === planter.id);
                            if (index !== -1) {
                                this.allPlanters[index].status = d.status;
                            }

                            Swal.fire({
                                icon: 'success',
                                title: 'Updated!',
                                text: d.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } catch (e) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: e.message
                            });
                        }
                    },
                    async syncPlanters() {
                        Swal.fire({
                            title: 'Syncing...',
                            text: 'Please wait',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });
                        try {
                            const r = await fetch('/planter-profiles/sync', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const d = await r.json();
                            Swal.fire({
                                icon: 'success',
                                title: 'Synced!',
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
                    }
                }
            }
        </script>
    @endpush
@endsection
