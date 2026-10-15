<!-- resources/views/cash-advances/show.blade.php -->
@extends('layouts.main')

@section('title', 'CA #' . $cashAdvance->ca_number)

@section('content')
    <div class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('cash-advances.index') }}" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895] dark:hover:text-[#EAF5ED]">
                            <i class="fas fa-arrow-left text-sm" aria-hidden="true"></i>
                        </a>
                        <p class="kp-eyebrow">Financial Services</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-1">
                        <h1 class="kp-page-title">Cash Advance #{{ $cashAdvance->ca_number }}</h1>
                        <span class="kp-badge text-xs
                            @if ($cashAdvance->status == 'active') kp-badge--forest
                            @elseif($cashAdvance->status == 'pending') kp-badge--amber
                            @elseif($cashAdvance->status == 'approved') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                            @elseif($cashAdvance->status == 'completed') bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300
                            @else bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 @endif">
                            {{ ucfirst($cashAdvance->status) }}
                        </span>
                    </div>
                    <p class="kp-page-sub mt-1">{{ $cashAdvance->planter_name }} • Code: <span class="font-mono">{{ $cashAdvance->planter_code }}</span></p>
                </div>

                @php
                    $isAdmin = in_array(auth()->user()->role, ['Administrator', 'super_admin']);
                    $isManager = auth()->user()->role === 'manager';
                    $canApprove = $isAdmin || $isManager;
                    $canProcess = $isAdmin || $isManager || auth()->user()->hasPermission('create-cash-advances') || auth()->user()->hasPermission('process-cash-advance-payments');
                @endphp

                <div class="flex flex-wrap items-center gap-2">
                    @if ($canApprove && $cashAdvance->status === 'pending')
                        <button type="button" onclick="approveCA()" class="kp-btn kp-btn-primary text-xs">
                            <i class="fas fa-check mr-1.5" aria-hidden="true"></i> Approve Advance
                        </button>
                        <button type="button" onclick="rejectCA()" class="kp-btn kp-btn-danger text-xs">
                            <i class="fas fa-times mr-1.5" aria-hidden="true"></i> Reject
                        </button>
                    @endif
                    @if ($canApprove && $cashAdvance->status === 'approved')
                        <form action="{{ route('cash-advances.activate', $cashAdvance) }}" method="POST">
                            @csrf
                            <button type="submit" class="kp-btn kp-btn-primary text-xs">
                                <i class="fas fa-play mr-1.5" aria-hidden="true"></i> Activate Advance
                            </button>
                        </form>
                    @endif
                    @if ($canProcess && in_array($cashAdvance->status, ['pending', 'rejected']))
                        <form action="{{ route('cash-advances.destroy', $cashAdvance) }}" method="POST" onsubmit="return confirm('Permanently delete this cash advance application?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="kp-btn kp-btn-secondary text-xs text-rose-600 hover:text-rose-700">
                                <i class="fas fa-trash mr-1.5" aria-hidden="true"></i> Delete
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- KPI Metrics Ribbon -->
        <section aria-label="Cash advance metrics" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-hand-holding-dollar" aria-hidden="true"></i> Advance Amount</p>
                <p class="kp-kpi-value kp-num">₱{{ number_format($cashAdvance->amount, 2) }}</p>
                <p class="kp-kpi-sub">{{ $cashAdvance->interest_rate }}% interest rate</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-calendar-check" aria-hidden="true"></i> Monthly Amortization</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">₱{{ number_format($cashAdvance->monthly_amortization, 2) }}</p>
                <p class="kp-kpi-sub">{{ $cashAdvance->term_months }} month(s) term</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Outstanding Balance</p>
                <p class="kp-kpi-value kp-num" style="color: {{ $cashAdvance->balance > 0 ? '#b91c1c' : 'var(--kp-emerald)' }}">
                    ₱{{ number_format($cashAdvance->balance, 2) }}
                </p>
                <p class="kp-kpi-sub">Total with interest: ₱{{ number_format($cashAdvance->total_amount, 2) }}</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-calendar" aria-hidden="true"></i> Crop Period</p>
                <p class="kp-kpi-value font-mono text-base font-semibold mt-1 text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->crop_year }}</p>
                <p class="kp-kpi-sub">Active crop season</p>
            </div>
        </section>

        <!-- Details & Schedule Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Details Card -->
                <div class="kp-card p-6">
                    <div class="flex items-center gap-2 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <i class="fas fa-file-invoice text-[#005B2A] dark:text-[#52C97E]"></i>
                        <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Cash Advance Terms</h3>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Start Date</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->start_date ? $cashAdvance->start_date->format('M d, Y') : 'Pending Activation' }}</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Interest Charge</span>
                            <span class="font-bold text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->interest_rate }}%</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Total Repayable</span>
                            <span class="font-bold text-[#172B20] dark:text-[#EAF5ED] kp-num">₱{{ number_format($cashAdvance->total_amount, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Application Date</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->application_date ? $cashAdvance->application_date->format('M d, Y') : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Approval Date</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->approved_date ? $cashAdvance->approved_date->format('M d, Y') : 'Pending' }}</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Crop Period</span>
                            <span class="font-mono text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->crop_year }}</span>
                        </div>
                    </div>

                    @if ($cashAdvance->purpose)
                        <div class="mt-4 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B] text-xs">
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-1 uppercase font-semibold">Purpose / Justification</span>
                            <p class="text-[#172B20] dark:text-[#EAF5ED] leading-relaxed">{{ $cashAdvance->purpose }}</p>
                        </div>
                    @endif
                </div>

                <!-- Amortization Schedule Card -->
                <div class="kp-card overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-calendar-days text-[#005B2A] dark:text-[#52C97E]"></i>
                            <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Repayment Schedule</h3>
                        </div>
                        <span class="text-xs text-[#52705F] dark:text-[#8AA895]">{{ count($cashAdvance->amortizations) }} Installments</span>
                    </div>

                    <div class="kp-table-wrap overflow-x-auto">
                        <table class="kp-table w-full">
                            <thead>
                                <tr>
                                    <th class="w-12 text-center">#</th>
                                    <th>Due Date</th>
                                    <th class="text-right">Amount Due</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Balance</th>
                                    <th class="text-center">Status</th>
                                    @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager', 'loan_officer']))
                                        <th class="text-center">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cashAdvance->amortizations as $amort)
                                    <tr class="hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition-colors">
                                        <td class="text-center font-mono text-xs">{{ $amort->payment_number }}</td>
                                        <td class="text-xs font-medium">{{ $amort->due_date->format('M d, Y') }}</td>
                                        <td class="text-xs text-right kp-num font-medium">₱{{ number_format($amort->amount_due, 2) }}</td>
                                        <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]">₱{{ number_format($amort->amount_paid, 2) }}</td>
                                        <td class="text-xs text-right kp-num">₱{{ number_format($amort->balance_after, 2) }}</td>
                                        <td class="text-center">
                                            <span class="kp-badge text-xs
                                                @if ($amort->status == 'paid') kp-badge--forest
                                                @elseif($amort->status == 'partial') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                                                @elseif($amort->status == 'overdue') bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                                                @else bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300 @endif">
                                                {{ ucfirst($amort->status) }}
                                            </span>
                                        </td>
                                        @if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager', 'loan_officer']))
                                            <td class="text-center">
                                                @if ($amort->status != 'paid')
                                                    <button type="button" onclick="recordPayment({{ $amort->id }}, {{ $amort->amount_due }})"
                                                        class="kp-btn kp-btn-primary text-xs py-1 px-2.5">
                                                        <i class="fas fa-hand-holding-dollar mr-1" aria-hidden="true"></i> Pay
                                                    </button>
                                                @else
                                                    <span class="text-xs text-[#52705F]"><i class="fas fa-check text-emerald-600"></i> Settled</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-12 text-center text-xs text-[#52705F]">No amortization schedule generated yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Compliance & Officer Info -->
                <div class="kp-card p-6">
                    <div class="flex items-center gap-2 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <i class="fas fa-shield-halved text-[#52705F]"></i>
                        <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Compliance & Audit Trail</h3>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between py-1.5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <span class="text-[#52705F] dark:text-[#8AA895]">Date Applied</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->application_date->format('M d, Y') }}</span>
                        </div>
                        @if ($cashAdvance->approved_date)
                            <div class="flex items-center justify-between py-1.5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                                <span class="text-[#52705F] dark:text-[#8AA895]">Date Approved</span>
                                <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->approved_date->format('M d, Y') }}</span>
                            </div>
                        @endif
                        @if ($cashAdvance->approvedByUser)
                            <div class="flex items-center justify-between py-1.5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                                <span class="text-[#52705F] dark:text-[#8AA895]">Approved By</span>
                                <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->approvedByUser->fname }} {{ $cashAdvance->approvedByUser->lname }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between py-1.5">
                            <span class="text-[#52705F] dark:text-[#8AA895]">Officer Assigned</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $cashAdvance->createdByUser->fname ?? 'System' }} {{ $cashAdvance->createdByUser->lname ?? '' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" style="display:none;" class="fixed inset-0 bg-black/60 backdrop-blur-sm items-center justify-center z-50 overflow-y-auto p-4">
        <div class="kp-modal kp-viewport-modal-panel max-w-md w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="advance-payment-modal-title">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <h3 id="advance-payment-modal-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Record Advance Payment</h3>
                <button type="button" onclick="closePaymentModal()" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895]">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <form id="paymentForm" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="amortization_id" id="amortizationId">
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Payment Amount (₱)</label>
                    <input type="number" name="amount_paid" id="paymentAmount" step="0.01" required
                        class="kp-input w-full">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Settlement Week Number</label>
                    <input type="text" name="week_no" placeholder="e.g. 24"
                        class="kp-input w-full">
                </div>
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <button type="button" onclick="closePaymentModal()" class="kp-btn kp-btn-secondary text-xs">Cancel</button>
                    <button type="submit" class="kp-btn kp-btn-primary text-xs">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function approveCA() {
                Swal.fire({
                    title: 'Approve Cash Advance?',
                    html: '<label class="block text-xs font-semibold mb-2">Effective Disbursement Date:</label><input type="date" id="startDate" class="swal2-input">',
                    showCancelButton: true, 
                    confirmButtonText: 'Confirm Approval',
                    confirmButtonColor: '#005B2A',
                    cancelButtonText: 'Cancel',
                    preConfirm: () => {
                        const startDate = document.getElementById('startDate').value;
                        if (!startDate) { 
                            Swal.showValidationMessage('Please select start date'); 
                            return false; 
                        }
                        const form = document.createElement('form');
                        form.method = 'POST'; 
                        form.action = '{{ route('cash-advances.approve', $cashAdvance) }}';
                        form.innerHTML = '@csrf<input type="hidden" name="start_date" value="' + startDate + '">';
                        document.body.appendChild(form); 
                        form.submit();
                    }
                });
            }

            function rejectCA() {
                Swal.fire({
                    title: 'Reject Cash Advance?', 
                    input: 'text', 
                    inputLabel: 'Reason for Rejection', 
                    inputPlaceholder: 'Enter review remarks...',
                    showCancelButton: true, 
                    confirmButtonText: 'Reject Advance', 
                    confirmButtonColor: '#dc2626',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST'; 
                        form.action = '{{ route('cash-advances.reject', $cashAdvance) }}';
                        form.innerHTML = '@csrf<input type="hidden" name="remarks" value="' + (result.value || '') + '">';
                        document.body.appendChild(form); 
                        form.submit();
                    }
                });
            }

            function recordPayment(amortizationId, amountDue) {
                document.getElementById('amortizationId').value = amortizationId;
                document.getElementById('paymentAmount').value = amountDue;
                document.getElementById('paymentForm').action = '{{ route('cash-advances.payment', $cashAdvance) }}';
                document.getElementById('paymentModal').style.display = 'flex';
            }

            function closePaymentModal() { 
                document.getElementById('paymentModal').style.display = 'none'; 
            }
        </script>
    @endpush
@endsection
