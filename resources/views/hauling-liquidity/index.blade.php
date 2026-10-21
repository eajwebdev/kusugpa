<!-- resources/views/hauling-liquidity/index.blade.php -->
@extends('layouts.main')

@section('title', 'Hauler TLS LIQ')

@section('content')
    <div x-data="haulerData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Hauling</p>
                    <h1 class="kp-page-title mt-1">Hauler TLS LIQ</h1>
                    <p class="kp-page-sub">Manage haulers and their weekly hauling liquidation entries.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($canManage)
                        <button @click="openAddModal()" class="kp-btn kp-btn-primary">
                            <i class="fas fa-plus-circle mr-1.5" aria-hidden="true"></i> Add Hauler
                        </button>
                    @endif
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Stats Strip -->
        <section aria-label="Hauling summary metrics" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-truck-moving" aria-hidden="true"></i> Haulers</p>
                <p class="kp-kpi-value kp-num">{{ $stats['total'] }}</p>
                <p class="kp-kpi-sub">Registered hauler groups</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-check-circle" aria-hidden="true"></i> Active</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-emerald)">{{ $stats['active'] }}</p>
                <p class="kp-kpi-sub">Available for new entries</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-file-invoice" aria-hidden="true"></i> Entries</p>
                <p class="kp-kpi-value kp-num">{{ number_format($stats['entries']) }}</p>
                <p class="kp-kpi-sub">Liquidation entries recorded</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-coins" aria-hidden="true"></i> Net Liquidation</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">₱{{ number_format($stats['total_net'], 2) }}</p>
                <p class="kp-kpi-sub">All haulers, all weeks</p>
            </div>
        </section>

        <!-- Filter Bar -->
        <div class="kp-card p-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search Haulers</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                        <input type="text" x-model="search" placeholder="Search by hauler name..." class="kp-input w-full pl-9">
                    </div>
                </div>
                <div class="w-48">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Status</label>
                    <select x-model="statusFilter" class="kp-select w-full">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <span class="text-xs font-mono ml-auto py-2" style="color: var(--kp-muted)"
                    x-text="'Showing ' + filteredHaulers.length + ' haulers'"></span>
            </div>
        </div>

        <!-- Haulers Table -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap">
                <table class="kp-table">
                    <thead>
                        <tr>
                            <th>Hauler</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Entries</th>
                            <th class="text-right">Total Tonnage</th>
                            <th class="text-right">Net Liquidation</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="hauler in filteredHaulers" :key="hauler.id">
                            <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                <td>
                                    <a :href="'/hauling-liquidity/' + hauler.id" class="font-medium hover:underline"
                                        style="color: var(--kp-emerald)" x-text="hauler.name"></a>
                                    <p class="text-xs" style="color: var(--kp-muted)" x-show="hauler.description" x-text="hauler.description"></p>
                                </td>
                                <td class="text-center">
                                    <span class="kp-badge" :class="hauler.is_active ? 'kp-badge-emerald' : 'kp-badge-neutral'"
                                        x-text="hauler.is_active ? 'Active' : 'Inactive'"></span>
                                </td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)" x-text="hauler.liquidations_count"></td>
                                <td class="text-right font-mono tabular" style="color: var(--kp-text)"
                                    x-text="formatNumber(hauler.total_tonnage, 3) + ' MT'"></td>
                                <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-emerald)"
                                    x-text="'₱' + formatNumber(hauler.total_net, 2)"></td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <a :href="'/hauling-liquidity/' + hauler.id"
                                            class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold"
                                            style="color: var(--kp-forest)" title="Open Liquidations">
                                            <i class="fas fa-folder-open"></i>
                                        </a>
                                        @if ($canManage)
                                            <button @click="editHauler(hauler)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold"
                                                style="color: var(--kp-cane)" title="Edit Hauler">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="toggleHauler(hauler)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold"
                                                :style="hauler.is_active ? 'color: var(--kp-emerald)' : 'color: var(--kp-muted)'"
                                                :title="hauler.is_active ? 'Click to deactivate' : 'Click to activate'">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                            <button @click="deleteHauler(hauler)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs font-semibold text-red-500"
                                                title="Delete Hauler">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredHaulers.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                <i class="fas fa-truck-moving text-3xl mb-2 block opacity-30"></i>
                                No haulers match the current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add/Edit Hauler Modal -->
        <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>
            <div class="relative rounded-xl border max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto"
                style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                    <h3 class="font-display text-lg font-semibold" style="color: var(--kp-text)"
                        x-text="editingHauler ? 'Edit Hauler' : 'Add Hauler'"></h3>
                    <button @click="showModal = false" class="text-xs p-1 rounded hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <form @submit.prevent="saveHauler()" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Hauler Name *</label>
                        <input type="text" x-model="form.name" placeholder="e.g. ISIO-CAUAYAN HAULER TLS LIQ." required class="kp-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Description</label>
                        <input type="text" x-model="form.description" placeholder="Optional notes about this hauler group" class="kp-input w-full">
                    </div>
                    <label class="flex items-center gap-2 text-sm" style="color: var(--kp-text)">
                        <input type="checkbox" x-model="form.is_active" class="rounded">
                        Active
                    </label>
                    <div class="flex gap-2 pt-3 border-t" style="border-color: var(--kp-border)">
                        <button type="button" @click="showModal = false" class="kp-btn kp-btn-secondary flex-1">Cancel</button>
                        <button type="submit" class="kp-btn kp-btn-primary flex-1">
                            <i class="fas fa-save mr-1.5"></i> Save Hauler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function haulerData() {
                return {
                    haulers: @json($haulers),
                    search: '',
                    statusFilter: 'all',
                    showModal: false,
                    editingHauler: null,
                    form: { name: '', description: '', is_active: true },

                    get filteredHaulers() {
                        const s = this.search.toLowerCase();
                        return this.haulers.filter(h =>
                            (!s || (h.name || '').toLowerCase().includes(s)) &&
                            (this.statusFilter === 'all' || (this.statusFilter === 'active') === !!h.is_active)
                        );
                    },
                    formatNumber(num, decimals = 2) {
                        return parseFloat(num || 0).toLocaleString('en-US', {
                            minimumFractionDigits: decimals,
                            maximumFractionDigits: decimals
                        });
                    },
                    async post(url, body = {}) {
                        const r = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(body)
                        });
                        const d = await r.json();
                        if (!r.ok) throw new Error(d.message || 'Request failed');
                        return d;
                    },
                    openAddModal() {
                        this.editingHauler = null;
                        this.form = { name: '', description: '', is_active: true };
                        this.showModal = true;
                    },
                    editHauler(hauler) {
                        this.editingHauler = hauler;
                        this.form = { name: hauler.name, description: hauler.description || '', is_active: !!hauler.is_active };
                        this.showModal = true;
                    },
                    async saveHauler() {
                        const url = this.editingHauler
                            ? '/hauling-liquidity/haulers/' + this.editingHauler.id + '/update'
                            : '/hauling-liquidity/haulers';
                        try {
                            const d = await this.post(url, this.form);
                            this.showModal = false;
                            Swal.fire({ icon: 'success', title: 'Success!', text: d.message, timer: 1500, showConfirmButton: false });
                            setTimeout(() => location.reload(), 1200);
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    },
                    async toggleHauler(hauler) {
                        try {
                            const d = await this.post('/hauling-liquidity/haulers/' + hauler.id + '/toggle');
                            hauler.is_active = d.is_active;
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    },
                    async deleteHauler(hauler) {
                        const res = await Swal.fire({
                            icon: 'warning',
                            title: 'Delete hauler?',
                            text: hauler.name,
                            showCancelButton: true,
                            confirmButtonText: 'Delete',
                            confirmButtonColor: '#dc2626'
                        });
                        if (!res.isConfirmed) return;
                        try {
                            const d = await this.post('/hauling-liquidity/haulers/' + hauler.id + '/delete');
                            this.haulers = this.haulers.filter(h => h.id !== hauler.id);
                            Swal.fire({ icon: 'success', title: 'Deleted', text: d.message, timer: 1500, showConfirmButton: false });
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Cannot delete', text: e.message });
                        }
                    }
                }
            }
        </script>
    @endpush
@endsection
