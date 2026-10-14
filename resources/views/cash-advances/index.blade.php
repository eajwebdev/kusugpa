<!-- resources/views/cash-advances/index.blade.php -->
@extends('layouts.main')

@section('title', 'Cash Advances Management')

@section('content')
    <div x-data="cashAdvancesData()" x-init="init()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Financial Services</p>
                    <h1 class="kp-page-title mt-1">Cash Advances</h1>
                    <p class="kp-page-sub">Manage planter cash advance requests, active balances, and weekly deductions.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if (auth()->user()->role === 'Administrator' ||
                            auth()->user()->role === 'super_admin' ||
                            auth()->user()->role === 'manager' ||
                            auth()->user()->hasPermission('create-cash-advances'))
                        <button @click="$dispatch('open-ca-modal')" class="kp-btn kp-btn-primary">
                            <i class="fas fa-plus-circle mr-1.5" aria-hidden="true"></i> New Cash Advance
                        </button>
                    @endif
                    @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
                            auth()->user()->hasPermission('manage-cash-advance-settings'))
                        <a href="{{ route('cash-advances.settings') }}" class="kp-btn kp-btn-secondary">
                            <i class="fas fa-cog mr-1.5" aria-hidden="true"></i> Settings
                        </a>
                    @endif
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Stats Strip -->
        <section aria-label="Cash advance portfolio metrics" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-check-circle" aria-hidden="true"></i> Active Advances</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-emerald)">{{ $stats['total_active'] }}</p>
                <p class="kp-kpi-sub">Currently servicing</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-clock" aria-hidden="true"></i> Pending Approval</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-cane)">{{ $stats['total_pending'] }}</p>
                <p class="kp-kpi-sub">Awaiting management</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Total Disbursed</p>
                <p class="kp-kpi-value kp-num">₱{{ number_format($stats['total_amount'], 0) }}</p>
                <p class="kp-kpi-sub">Cumulative principal</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-coins" aria-hidden="true"></i> Total Collected</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">₱{{ number_format($stats['total_collected'], 0) }}</p>
                <p class="kp-kpi-sub">Recovered amount</p>
            </div>
        </section>

        <!-- Search & Filter Bar -->
        <div class="kp-card p-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search Advances</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                        <input type="text" x-model="search" @input="filterCA()"
                            placeholder="Search by planter name, code, or CA number..."
                            class="kp-input w-full pl-9">
                    </div>
                </div>
                <div class="w-44">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Status</label>
                    <select x-model="statusFilter" @change="filterCA()" class="kp-select w-full">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="w-44">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Crop Year</label>
                    <select x-model="cropYearFilter" @change="filterCA()" class="kp-select w-full">
                        <option value="">All Crop Years</option>
                        @foreach ($cropYears as $cy)
                            <option value="{{ $cy }}">{{ $cy }}</option>
                        @endforeach
                    </select>
                </div>
                <button @click="clearFilters()" class="kp-btn kp-btn-secondary h-[38px]">
                    <i class="fas fa-times mr-1"></i> Reset
                </button>
                <span class="text-xs font-mono ml-auto py-2" style="color: var(--kp-muted)"
                    x-text="'Showing ' + filteredCA.length + ' records'"></span>
            </div>
        </div>

        <!-- Table Container -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table">
                    <thead>
                        <tr>
                            <th class="cursor-pointer select-none" @click="sortBy('ca_number')">
                                <div class="flex items-center gap-1.5">
                                    <span>CA #</span>
                                    <i class="fas text-[10px]" :class="sortField === 'ca_number' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_name')">
                                <div class="flex items-center gap-1.5">
                                    <span>Planter</span>
                                    <i class="fas text-[10px]" :class="sortField === 'planter_name' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="text-right cursor-pointer select-none" @click="sortBy('amount')">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Amount</span>
                                    <i class="fas text-[10px]" :class="sortField === 'amount' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="text-right">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="cursor-pointer select-none" @click="sortBy('application_date')">
                                <div class="flex items-center gap-1.5">
                                    <span>Date</span>
                                    <i class="fas text-[10px]" :class="sortField === 'application_date' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="ca in paginatedCA" :key="ca.id">
                            <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                <td class="font-mono font-semibold" style="color: var(--kp-text)">
                                    <a :href="'/cash-advances/' + ca.id" class="hover:underline" style="color: var(--kp-emerald)" x-text="ca.ca_number"></a>
                                </td>
                                <td>
                                    <p class="font-medium" style="color: var(--kp-text)" x-text="ca.planter_name"></p>
                                    <p class="text-xs font-mono" style="color: var(--kp-muted)" x-text="ca.planter_code"></p>
                                </td>
                                <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-text)"
                                    x-text="'₱' + formatNumber(ca.amount)"></td>
                                <td class="text-right font-mono font-semibold tabular"
                                    :style="ca.balance > 0 ? 'color: #A32F2F' : 'color: var(--kp-emerald)'"
                                    x-text="'₱' + formatNumber(ca.balance)"></td>
                                <td class="text-center">
                                    <span :class="statusClass(ca.status)" x-text="ca.status.charAt(0).toUpperCase() + ca.status.slice(1)"></span>
                                </td>
                                <td class="text-xs font-mono" style="color: var(--kp-muted)" x-text="formatDate(ca.application_date)"></td>
                                <td class="text-center">
                                    <a :href="'/cash-advances/' + ca.id"
                                        class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs inline-flex items-center gap-1">
                                        <i class="fas fa-eye text-[11px]"></i> View
                                    </a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredCA.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                <i class="fas fa-money-bill-wave text-3xl mb-2 block opacity-30"></i>
                                No cash advance records match the selected filters.
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

        @include('modal.cashAdvanceModal')
    </div>

    @push('scripts')
        <script>
            function cashAdvancesData() {
                return {
                    search: '',
                    statusFilter: 'all',
                    cropYearFilter: '',
                    sortField: 'application_date',
                    sortDirection: 'desc',
                    currentPage: 1,
                    perPage: 15,
                    allCA: @json($cashAdvances->items()),
                    showModal: false,

                    init() {
                        this.filterCA();
                    },

                    get filteredCA() {
                        let data = [...this.allCA];
                        if (this.search) {
                            const s = this.search.toLowerCase();
                            data = data.filter(d => d.ca_number?.toLowerCase().includes(s) || d.planter_name?.toLowerCase()
                                .includes(s) || d.planter_code?.toLowerCase().includes(s));
                        }
                        if (this.statusFilter !== 'all') data = data.filter(d => d.status === this.statusFilter);
                        if (this.cropYearFilter) data = data.filter(d => d.crop_year === this.cropYearFilter);
                        data.sort((a, b) => {
                            let valA = a[this.sortField] ?? '',
                                valB = b[this.sortField] ?? '';
                            if (typeof valA === 'string') valA = valA.toLowerCase();
                            if (typeof valB === 'string') valB = valB.toLowerCase();
                            if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                            if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
                            return 0;
                        });
                        return data;
                    },
                    get paginatedCA() {
                        const start = (this.currentPage - 1) * this.perPage;
                        return this.filteredCA.slice(start, start + this.perPage);
                    },
                    get totalPages() {
                        return Math.ceil(this.filteredCA.length / this.perPage);
                    },
                    filterCA() {
                        this.currentPage = 1;
                    },
                    sortBy(field) {
                        if (this.sortField === field) {
                            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.sortField = field;
                            this.sortDirection = 'asc';
                        }
                        this.filterCA();
                    },
                    clearFilters() {
                        this.search = '';
                        this.statusFilter = 'all';
                        this.cropYearFilter = '';
                        this.filterCA();
                    },
                    statusClass(status) {
                        const classes = {
                            active: 'kp-badge kp-badge-emerald',
                            pending: 'kp-badge kp-badge-amber',
                            approved: 'kp-badge kp-badge-info',
                            completed: 'kp-badge kp-badge-neutral',
                            rejected: 'kp-badge kp-badge-rose',
                            cancelled: 'kp-badge kp-badge-rose'
                        };
                        return classes[status] || 'kp-badge kp-badge-neutral';
                    },
                    formatNumber(num) {
                        return parseFloat(num || 0).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    },
                    formatDate(date) {
                        if (!date) return '';
                        return new Date(date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: '2-digit',
                            year: 'numeric'
                        });
                    }
                };
            }

            function newCAForm() {
                return {
                    showModal: false,
                    selectedPlanter: '',
                    planterName: '',
                    amount: '',
                    interestRate: {{ $settings['default_interest'] ?? 3 }},
                    termMonths: 3,
                    purpose: '',
                    submitting: false,

                    updatePlanterName() {
                        const select = document.querySelector('select[name="planter_code"]');
                        const option = select?.options[select.selectedIndex];
                        this.planterName = option?.getAttribute('data-name') || '';
                    },
                    calculateMonthly() {
                        const p = parseFloat(this.amount) || 0;
                        const r = (parseFloat(this.interestRate) || 0) / 100 / 12;
                        const n = parseInt(this.termMonths) || 1;
                        if (p === 0) return 0;
                        if (r === 0) return p / n;
                        return p * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
                    },
                    calculateTotal() {
                        return this.calculateMonthly() * (parseInt(this.termMonths) || 1);
                    },
                    calculateTotalInterest() {
                        return this.calculateTotal() - (parseFloat(this.amount) || 0);
                    },
                    formatNumber(num) {
                        return parseFloat(num || 0).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    },

                    async submitCAForm() {
                        this.submitting = true;
                        try {
                            const formData = new FormData();
                            formData.append('planter_code', document.querySelector('select[name="planter_code"]').value);
                            formData.append('planter_name', this.planterName);
                            formData.append('amount', this.amount);
                            formData.append('interest_rate', this.interestRate);
                            formData.append('term_months', this.termMonths);
                            formData.append('crop_year', document.querySelector('select[name="crop_year"]').value);
                            formData.append('purpose', this.purpose);
                            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                            const response = await fetch('{{ route('cash-advances.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: formData
                            });
                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message || 'Failed to create cash advance');

                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Cash advance application submitted successfully.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            this.$parent.showModal = false;
                            this.resetForm();
                            location.reload();
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message || 'Something went wrong'
                            });
                        } finally {
                            this.submitting = false;
                        }
                    },
                    resetForm() {
                        this.selectedPlanter = '';
                        this.planterName = '';
                        this.amount = '';
                        this.interestRate = {{ $settings['default_interest'] ?? 3 }};
                        this.termMonths = 3;
                        this.purpose = '';
                    }
                };
            }
        </script>
    @endpush
@endsection
