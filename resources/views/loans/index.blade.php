<!-- resources/views/loans/index.blade.php -->
@extends('layouts.main')

@section('title', 'Loans Management')

@section('content')
    <div x-data="loansData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Financial Services</p>
                    <h1 class="kp-page-title mt-1">Loans Management</h1>
                    <p class="kp-page-sub">Manage planter loan applications, active accounts, and amortizations.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if (auth()->user()->role === 'Administrator' ||
                            auth()->user()->role === 'super_admin' ||
                            auth()->user()->role === 'manager' ||
                            auth()->user()->hasPermission('create-loans'))
                        <button @click="openNewLoanModal()" class="kp-btn kp-btn-primary">
                            <i class="fas fa-plus-circle mr-1.5" aria-hidden="true"></i> New Loan
                        </button>
                    @endif
                    @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
                            auth()->user()->hasPermission('manage-loan-settings'))
                        <a href="{{ route('loans.settings') }}" class="kp-btn kp-btn-secondary">
                            <i class="fas fa-cog mr-1.5" aria-hidden="true"></i> Settings
                        </a>
                    @endif
                    @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
                            auth()->user()->hasPermission('view-loans'))
                        <a href="{{ route('loans.reports') }}" class="kp-btn kp-btn-secondary">
                            <i class="fas fa-chart-bar mr-1.5" aria-hidden="true"></i> Reports
                        </a>
                    @endif
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Stats Cards -->
        <section aria-label="Loan portfolio summary" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-check-circle" aria-hidden="true"></i> Active Loans</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-emerald)">{{ $stats['total_active'] }}</p>
                <p class="kp-kpi-sub">Currently servicing</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-clock" aria-hidden="true"></i> Pending Approval</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-cane)">{{ $stats['total_pending'] }}</p>
                <p class="kp-kpi-sub">Awaiting review</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Total Portfolio</p>
                <p class="kp-kpi-value kp-num">₱{{ number_format($stats['total_amount'], 0) }}</p>
                <p class="kp-kpi-sub">Principal granted</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-coins" aria-hidden="true"></i> Collected</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">₱{{ number_format($stats['total_collected'], 0) }}</p>
                <p class="kp-kpi-sub">Total payments received</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Overdue</p>
                <p class="kp-kpi-value kp-num" style="color: #A32F2F">{{ $stats['overdue_payments'] }}</p>
                <p class="kp-kpi-sub">Missed schedule dates</p>
            </div>
        </section>

        <!-- Search & Filter Bar -->
        <div class="kp-card p-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Search Loans</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                        <input type="text" x-model="search" @input="filterLoans()"
                            placeholder="Search by planter name, code, or loan number..."
                            class="kp-input w-full pl-9">
                    </div>
                </div>
                <div class="w-44">
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--kp-faint)">Status</label>
                    <select x-model="statusFilter" @change="filterLoans()" class="kp-select w-full">
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
                    <select x-model="cropYearFilter" @change="filterLoans()" class="kp-select w-full">
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
                    x-text="feed && feed.total !== null ? 'Showing ' + filteredLoans.length.toLocaleString() + ' of ' + feed.total.toLocaleString() + ' loans' : 'Loading…'"></span>
            </div>
        </div>

        <!-- Loans Table Container -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table" id="loansTable">
                    <thead>
                        <tr>
                            <th class="cursor-pointer select-none" @click="sortBy('loan_number')">
                                <div class="flex items-center gap-1.5">
                                    <span>Loan #</span>
                                    <i class="fas text-[10px]" :class="sortField === 'loan_number' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th class="cursor-pointer select-none" @click="sortBy('planter_name')">
                                <div class="flex items-center gap-1.5">
                                    <span>Planter</span>
                                    <i class="fas text-[10px]" :class="sortField === 'planter_name' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                </div>
                            </th>
                            <th>Type</th>
                            <th class="text-right cursor-pointer select-none" @click="sortBy('principal_amount')">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Principal</span>
                                    <i class="fas text-[10px]" :class="sortField === 'principal_amount' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
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
                            <th class="text-center">Files</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="loan in filteredLoans" :key="loan.id">
                            <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                <td class="font-mono font-semibold" style="color: var(--kp-text)">
                                    <a :href="'/loans/' + loan.id" class="hover:underline" style="color: var(--kp-emerald)" x-text="loan.loan_number"></a>
                                </td>
                                <td>
                                    <p class="font-medium" style="color: var(--kp-text)" x-text="loan.planter_name"></p>
                                    <p class="text-xs font-mono" style="color: var(--kp-muted)" x-text="loan.planter_code"></p>
                                </td>
                                <td style="color: var(--kp-muted)" x-text="loan.loan_type_name"></td>
                                <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-text)"
                                    x-text="'₱' + formatNumber(loan.principal_amount)"></td>
                                <td class="text-right font-mono font-semibold tabular"
                                    :style="loan.balance > 0 ? 'color: #A32F2F' : 'color: var(--kp-emerald)'"
                                    x-text="'₱' + formatNumber(loan.balance)"></td>
                                <td class="text-center">
                                    <span :class="statusClass(loan.status)" x-text="loan.status.charAt(0).toUpperCase() + loan.status.slice(1)"></span>
                                </td>
                                <td class="text-xs font-mono" style="color: var(--kp-muted)" x-text="formatDate(loan.application_date)"></td>
                                <td class="text-center">
                                    <button @click="viewAttachments(loan)"
                                        class="relative inline-flex items-center justify-center p-2 rounded-lg transition-colors hover:bg-[var(--kp-surface-2)]"
                                        style="color: var(--kp-forest)"
                                        :title="'View Attachments'">
                                        <i class="fas fa-paperclip text-sm"></i>
                                        <span x-show="loan.attachments_count > 0"
                                            class="absolute -top-1 -right-1 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center"
                                            style="background: var(--kp-emerald)"
                                            x-text="loan.attachments_count"></span>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <a :href="'/loans/' + loan.id"
                                        class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs inline-flex items-center gap-1">
                                        <i class="fas fa-eye text-[11px]"></i> View
                                    </a>
                                </td>
                            </tr>
                        </template>
                        <template x-if="feed">
                            @include('layouts.partials.feed-footer', ['feed' => 'feed', 'colspan' => 9, 'empty' => 'No loans match the selected filters.'])
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        @include('modal/view-loan-attachment')
        @include('modal/loanModal')
    </div>

    @push('scripts')
        <script>
            // Main loans data component
            function loansData() {
                return {
                    search: '',
                    statusFilter: 'all',
                    cropYearFilter: '',
                    sortField: 'application_date',
                    sortDirection: 'desc',
                    showNewLoanModal: false,
                    // Loans come from the server in batches of 100 as the table scrolls (kpFeed);
                    // search, filters and sorting run in the database
                    feed: null,
                    searchTimer: null,
                    showAttachmentsModal: false,
                    viewingLoan: null,
                    attachments: [],
                    loadingAttachments: false,
                    uploadingAttachment: false,

                    init() {
                        this.feed = kpFeed('{{ route('loans.index') }}', () => ({
                            search: this.search.trim(),
                            status: this.statusFilter,
                            crop_year: this.cropYearFilter,
                            sort: this.sortField, dir: this.sortDirection,
                        }));
                        this.feed.reset();
                    },

                    get filteredLoans() {
                        return this.feed ? this.feed.rows : [];
                    },

                    async viewAttachments(loan) {
                        this.viewingLoan = loan;
                        this.showAttachmentsModal = true;
                        this.loadingAttachments = true;
                        this.attachments = [];

                        try {
                            const response = await fetch('/loans/' + loan.id + '/attachments', {
                                headers: {
                                    'Accept': 'application/json'
                                }
                            });
                            const data = await response.json();
                            this.attachments = data.attachments || [];
                        } catch (error) {
                            console.error('Error loading attachments:', error);
                        } finally {
                            this.loadingAttachments = false;
                        }
                    },

                    async uploadNewAttachment() {
                        if (!this.viewingLoan) return;

                        const fileInput = document.getElementById('newAttachmentFile');
                        if (!fileInput.files.length) {
                            alert('Please select a file');
                            return;
                        }

                        this.uploadingAttachment = true;

                        try {
                            const formData = new FormData();
                            formData.append('document', fileInput.files[0]);
                            formData.append('document_type', document.getElementById('newDocType').value);
                            formData.append('description', document.getElementById('newDocDesc').value);
                            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                            const response = await fetch('/loans/' + this.viewingLoan.id + '/attachments', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: formData
                            });

                            if (!response.ok) throw new Error('Upload failed');

                            await this.viewAttachments(this.viewingLoan);

                            fileInput.value = '';
                            document.getElementById('newDocDesc').value = '';

                        } catch (error) {
                            alert('Failed to upload: ' + error.message);
                        } finally {
                            this.uploadingAttachment = false;
                        }
                    },

                    getFileIcon(mimeType) {
                        if (mimeType?.includes('pdf')) return 'fas fa-file-pdf text-red-500';
                        if (mimeType?.includes('image')) return 'fas fa-file-image text-blue-500';
                        if (mimeType?.includes('word') || mimeType?.includes('document'))
                            return 'fas fa-file-word text-blue-600';
                        return 'fas fa-file text-gray-500';
                    },

                    getFileBgColor(mimeType) {
                        if (mimeType?.includes('pdf')) return 'bg-red-100';
                        if (mimeType?.includes('image')) return 'bg-blue-100';
                        if (mimeType?.includes('word') || mimeType?.includes('document')) return 'bg-blue-50';
                        return 'bg-gray-100';
                    },

                    // Reload from the first batch; typing in the search waits for a pause
                    filterLoans() {
                        clearTimeout(this.searchTimer);
                        this.searchTimer = setTimeout(() => this.feed.reset(), 300);
                    },

                    sortBy(field) {
                        this.sortDirection = this.sortField === field && this.sortDirection === 'asc' ? 'desc' : 'asc';
                        this.sortField = field;
                        this.feed.reset();
                    },

                    clearFilters() {
                        this.search = '';
                        this.statusFilter = 'all';
                        this.cropYearFilter = '';
                        this.filterLoans();
                    },

                    openNewLoanModal() {
                        this.showNewLoanModal = true;
                    },

                    statusClass(status) {
                        const classes = {
                            active: 'kp-badge kp-badge-emerald',
                            pending: 'kp-badge kp-badge-amber',
                            approved: 'kp-badge kp-badge-info',
                            completed: 'kp-badge kp-badge-neutral',
                            rejected: 'kp-badge kp-badge-rose',
                            cancelled: 'kp-badge kp-badge-rose',
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

            // Separate new loan form component for the modal
            function newLoanForm() {
                return {
                    selectedPlanter: '',
                    planterName: '',
                    selectedLoanType: '',
                    principalAmount: '',
                    interestRate: 5,
                    termMonths: 12,
                    maxTerm: {{ max(1, (int) $maxTerm) }},
                    maxAmount: 0,
                    purpose: '',
                    submitting: false,
                    schedule: [],
                    rowUid: 0,
                    manualSplit: false,
                    monthOptions: [],
                    // ApprovalPolicy: from this principal up, a Manager or Administrator approves
                    threshold: {{ \App\Support\ApprovalPolicy::MANAGER_THRESHOLD }},
                    thresholdLabel: @json(\App\Support\ApprovalPolicy::thresholdLabel()),

                    hasPrincipal() {
                        return (parseFloat(this.principalAmount) || 0) > 0;
                    },
                    needsManager() {
                        return Math.round((parseFloat(this.principalAmount) || 0) * 100) >= this.threshold * 100;
                    },

                    init() {
                        // Months to choose from: a year back (earlier manual loans) to five years ahead
                        const now = new Date();
                        for (let i = -12; i <= 60; i++) {
                            const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
                            const value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                            this.monthOptions.push({ value, label: this.monthLabel(value) });
                        }
                        this.buildSchedule();
                    },
                    monthLabel(value) {
                        const [y, m] = value.split('-').map(Number);
                        return new Date(y, m - 1, 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    },
                    monthEnd(value) {
                        const [y, m] = value.split('-').map(Number);
                        const d = new Date(y, m, 0);
                        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                    },
                    // Months start the month after today
                    buildSchedule() {
                        if (this.termMonths > this.maxTerm) this.termMonths = this.maxTerm;
                        const start = 13; // monthOptions[12] is the current month
                        this.schedule = this.monthOptions.slice(start, start + this.termMonths).map(m => ({
                            uid: ++this.rowUid, month: m.value, due_date: this.monthEnd(m.value), amount: '',
                        }));
                        this.manualSplit = false;
                        this.autoSplit();
                    },
                    totalRepayable() {
                        return Math.round(this.calculateTotal() * 100) / 100;
                    },
                    // Split the total repayable evenly in centavos; the last month takes the remainder
                    autoSplit() {
                        const n = this.schedule.length;
                        if (!n) return;
                        const cents = Math.round(this.totalRepayable() * 100);
                        const each = Math.floor(cents / n);
                        this.schedule.forEach((row, i) => {
                            const c = i === n - 1 ? cents - each * (n - 1) : each;
                            row.amount = cents ? (c / 100).toFixed(2) : '';
                        });
                    },
                    totalAllocated() {
                        return this.schedule.reduce((t, r) => t + Math.round((parseFloat(r.amount) || 0) * 100), 0) / 100;
                    },
                    isBalanced() {
                        return Math.round(this.totalAllocated() * 100) === Math.round(this.totalRepayable() * 100);
                    },
                    allocatedPercent() {
                        const total = this.totalRepayable();
                        if (!total) return '0%';
                        return parseFloat((this.totalAllocated() / total * 100).toFixed(2)) + '%';
                    },
                    duplicateMonths() {
                        const seen = {};
                        this.schedule.forEach(r => seen[r.month] = (seen[r.month] || 0) + 1);
                        return Object.keys(seen).filter(m => seen[m] > 1);
                    },
                    // Submitting needs distinct months whose payments add up to the total repayable
                    canSubmit() {
                        return this.totalRepayable() > 0 && this.schedule.length > 0 && !this.duplicateMonths().length && this.isBalanced();
                    },
                    submitBlockReason() {
                        if (!(this.totalRepayable() > 0)) return 'Enter the principal amount.';
                        if (this.duplicateMonths().length) return this.duplicateMonths().map(m => this.monthLabel(m)).join(', ') + ' is listed more than once.';
                        return 'Monthly payments total must equal the total repayable.';
                    },

                    updatePlanterName() {
                        const select = document.querySelector('select[name="planter_code"]');
                        const option = select?.options[select.selectedIndex];
                        this.planterName = option?.getAttribute('data-name') || '';
                    },

                    updateLoanDetails() {
                        const select = document.querySelector('select[name="loan_type_id"]');
                        const option = select?.options[select.selectedIndex];
                        if (option && option.value) {
                            this.interestRate = parseFloat(option.getAttribute('data-rate')) || 5;
                            this.termMonths = Math.min(this.maxTerm, parseInt(option.getAttribute('data-term')) || 12);
                            this.maxAmount = parseFloat(option.getAttribute('data-max')) || 0;
                            this.buildSchedule();
                        }
                    },

                    previewFile(event) {
                        const file = event.target.files[0];
                        if (!file) return;

                        document.getElementById('previewFileName').textContent = file.name;
                        document.getElementById('previewFileSize').textContent = this.formatFileSize(file.size);
                        document.getElementById('filePreview').classList.remove('hidden');
                    },

                    removeFile() {
                        document.getElementById('loanDocument').value = '';
                        document.getElementById('filePreview').classList.add('hidden');
                    },

                    formatFileSize(bytes) {
                        if (bytes === 0) return '0 B';
                        const units = ['B', 'KB', 'MB', 'GB'];
                        const i = Math.floor(Math.log(bytes) / Math.log(1024));
                        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
                    },

                    // Same amortization formula as the server (Loan::calculateAmortization)
                    calculateMonthly() {
                        const p = parseFloat(this.principalAmount) || 0;
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
                        return this.calculateTotal() - (parseFloat(this.principalAmount) || 0);
                    },

                    formatNumber(num) {
                        return parseFloat(num || 0).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    },

                    async submitLoanForm() {
                        if (!this.canSubmit()) {
                            Swal.fire({ icon: 'warning', title: 'Cannot submit yet', text: this.submitBlockReason() });
                            return;
                        }
                        this.submitting = true;

                        try {
                            const formData = new FormData();
                            formData.append('planter_code', document.querySelector('select[name="planter_code"]').value);
                            formData.append('planter_name', this.planterName);
                            formData.append('loan_type_id', document.querySelector('select[name="loan_type_id"]').value);
                            formData.append('principal_amount', this.principalAmount);
                            formData.append('interest_rate', this.interestRate);
                            formData.append('term_months', this.termMonths);
                            formData.append('crop_year', document.querySelector('select[name="crop_year"]').value);
                            formData.append('purpose', this.purpose);
                            this.schedule.forEach((r, i) => {
                                formData.append('schedule[' + i + '][month]', r.month);
                                formData.append('schedule[' + i + '][due_date]', r.due_date || '');
                                formData.append('schedule[' + i + '][amount]', Math.round((parseFloat(r.amount) || 0) * 100) / 100);
                            });
                            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                            const loanResponse = await fetch('{{ route('loans.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: formData
                            });

                            const loanData = await loanResponse.json();

                            if (!loanResponse.ok) {
                                throw new Error(loanData.message || 'Failed to create loan');
                            }

                            const fileInput = document.getElementById('loanDocument');
                            if (fileInput && fileInput.files.length > 0 && loanData.loan) {
                                const attachForm = new FormData();
                                attachForm.append('document', fileInput.files[0]);
                                attachForm.append('document_type', document.getElementById('docType').value);
                                attachForm.append('description', document.getElementById('docDesc').value);
                                attachForm.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                                await fetch('/loans/' + loanData.loan.id + '/attachments', {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    },
                                    body: attachForm
                                });
                            }

                            this.showNewLoanModal = false;
                            this.resetForm();

                            await Swal.fire({
                                icon: 'success',
                                title: loanData.auto_approved ? 'Approved!' : 'Submitted for Approval',
                                text: loanData.message,
                                timer: 2600,
                                showConfirmButton: false
                            });

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
                        this.selectedLoanType = '';
                        this.principalAmount = '';
                        this.interestRate = 5;
                        this.termMonths = Math.min(12, this.maxTerm);
                        this.maxAmount = 0;
                        this.purpose = '';
                        this.buildSchedule();
                        document.getElementById('loanDocument').value = '';
                        document.getElementById('filePreview').classList.add('hidden');
                    }
                };
            }
        </script>
    @endpush
@endsection
