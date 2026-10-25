<!-- resources/views/loans/show.blade.php -->
@extends('layouts.main')

@section('title', 'Loan #' . $loan->loan_number)

@section('content')
    @php
        // Loans are repaid by their monthly schedule
        $scheduledTotal = $monthlyRows->sum('amount');
        $collected = $monthlyRows->sum('paid');
        $nextMonth = $monthlyRows->firstWhere('remaining', '>', 0);
        $monthLabel = fn ($m) => \Carbon\Carbon::createFromFormat('Y-m-d', $m . '-01')->format('M Y');
        $monthStatus = [
            'paid' => ['Paid', 'kp-badge--forest'],
            'partial' => ['Partial', 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'],
            'overdue' => ['Overdue', 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'],
            'upcoming' => ['Upcoming', 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300'],
        ];
    @endphp
    <div class="space-y-6 kp-fade-in" x-data="loanPayments()">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('loans.index') }}" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895] dark:hover:text-[#EAF5ED]">
                            <i class="fas fa-arrow-left text-sm" aria-hidden="true"></i>
                        </a>
                        <p class="kp-eyebrow">Credit & Portfolio Facility</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-1">
                        <h1 class="kp-page-title">Loan #{{ $loan->loan_number }}</h1>
                        <span class="kp-badge text-xs
                            @if ($loan->status == 'active') kp-badge--forest
                            @elseif($loan->status == 'pending') kp-badge--amber
                            @elseif($loan->status == 'approved') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                            @elseif($loan->status == 'completed') bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300
                            @else bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 @endif">
                            {{ ucfirst($loan->status) }}
                        </span>
                    </div>
                    <p class="kp-page-sub mt-1">{{ $loan->planter_name }} • Code: <span class="font-mono">{{ $loan->planter_code }}</span></p>
                </div>

                @php
                    $isAdmin = in_array(auth()->user()->role, ['Administrator', 'super_admin']);
                    $isManager = auth()->user()->role === 'manager';
                    $canProcess =
                        $isAdmin ||
                        $isManager ||
                        auth()->user()->hasPermission('create-loans') ||
                        auth()->user()->hasPermission('process-loan-payments');
                @endphp

                <div class="flex flex-wrap items-center gap-2">
                    @if ($userPermissions['awaitingManager'])
                        <span class="kp-badge kp-badge--amber text-xs py-1 px-2.5">
                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                            Waiting for Manager approval ({{ \App\Support\ApprovalPolicy::thresholdLabel() }} or more)
                        </span>
                    @endif
                    @if ($userPermissions['canApprove'])
                        <button type="button" onclick="approveLoan()" class="kp-btn kp-btn-primary text-xs">
                            <i class="fas fa-check mr-1.5" aria-hidden="true"></i> Approve Loan
                        </button>
                    @endif
                    @if ($userPermissions['canReject'])
                        <button type="button" onclick="rejectLoan()" class="kp-btn kp-btn-danger text-xs">
                            <i class="fas fa-times mr-1.5" aria-hidden="true"></i> Reject
                        </button>
                    @endif
                    @if ($userPermissions['canActivate'])
                        <form action="{{ route('loans.activate', $loan) }}" method="POST">
                            @csrf
                            <button type="submit" class="kp-btn kp-btn-primary text-xs">
                                <i class="fas fa-play mr-1.5" aria-hidden="true"></i> Activate Facility
                            </button>
                        </form>
                    @endif
                    @if ($canProcess && in_array($loan->status, ['pending', 'rejected']))
                        <form action="{{ route('loans.destroy', $loan) }}" method="POST"
                            onsubmit="return confirm('Permanently delete this loan application?')">
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
        <section aria-label="Loan metrics" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-hand-holding-dollar" aria-hidden="true"></i> Principal</p>
                <p class="kp-kpi-value kp-num">₱{{ number_format($loan->principal_amount, 2) }}</p>
                <p class="kp-kpi-sub">{{ $loan->interest_rate }}% annual rate</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-calendar-check" aria-hidden="true"></i> Monthly Schedule</p>
                <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">{{ $monthlyRows->count() }} month(s)</p>
                <p class="kp-kpi-sub">
                    @if ($nextMonth)
                        Next: {{ $monthLabel($nextMonth['month']) }} · due {{ \Carbon\Carbon::parse($nextMonth['due_date'])->format('M d') }} · ₱{{ number_format($nextMonth['remaining'], 2) }}
                    @elseif ($monthlyRows->isNotEmpty())
                        All months paid
                    @else
                        No months scheduled
                    @endif
                </p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Outstanding Balance</p>
                <p class="kp-kpi-value kp-num" style="color: {{ $scheduledTotal - $collected > 0 ? '#b91c1c' : 'var(--kp-emerald)' }}">
                    ₱{{ number_format($scheduledTotal - $collected, 2) }}
                </p>
                <p class="kp-kpi-sub">Collected ₱{{ number_format($collected, 2) }} of ₱{{ number_format($scheduledTotal, 2) }} (with interest)</p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-calendar" aria-hidden="true"></i> Crop Period</p>
                <p class="kp-kpi-value font-mono text-base font-semibold mt-1 text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->crop_year }}</p>
                <p class="kp-kpi-sub">Active crop season</p>
            </div>
        </section>

        <!-- Loan Details & Schedule Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Loan Details Card -->
                <div class="kp-card p-6">
                    <div class="flex items-center gap-2 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <i class="fas fa-file-invoice-dollar text-[#005B2A] dark:text-[#52C97E]"></i>
                        <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Loan Facility Specifications</h3>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Loan Classification</span>
                            <span class="font-bold text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->loanType->name ?? 'Standard Credit' }}</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Start Date</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->start_date ? $loan->start_date->format('M d, Y') : 'Pending Activation' }}</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Interest Charge</span>
                            <span class="font-bold text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->interest_rate }}% Flat Annual</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Total Repayable</span>
                            <span class="font-bold text-[#172B20] dark:text-[#EAF5ED] kp-num">₱{{ number_format($loan->total_amount, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Application Date</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->application_date ? $loan->application_date->format('M d, Y') : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Approval Date</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->approved_date ? $loan->approved_date->format('M d, Y') : 'Pending' }}</span>
                        </div>
                    </div>

                    @if ($loan->purpose)
                        <div class="mt-4 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B] text-xs">
                            <span class="text-[#52705F] dark:text-[#8AA895] block mb-1 uppercase font-semibold">Purpose & Utilization</span>
                            <p class="text-[#172B20] dark:text-[#EAF5ED] leading-relaxed">{{ $loan->purpose }}</p>
                        </div>
                    @endif
                </div>

                <!-- Monthly Repayment Schedule -->
                <div class="kp-card overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-calendar-days text-[#005B2A] dark:text-[#52C97E]"></i>
                            <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Monthly Repayment Schedule</h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($userPermissions['canEditSchedule'])
                                <button type="button" @click="openSchedule()" class="kp-btn kp-btn-secondary text-xs py-1.5 px-3">
                                    <i class="fas fa-pen-to-square mr-1.5" aria-hidden="true"></i> Edit Schedule
                                </button>
                            @endif
                            @if ($userPermissions['canPay'] && $nextMonth)
                                <button type="button" @click="openPayment('{{ $nextMonth['month'] }}')" class="kp-btn kp-btn-primary text-xs py-1.5 px-3">
                                    <i class="fas fa-hand-holding-dollar mr-1.5" aria-hidden="true"></i> Record Payment
                                </button>
                            @endif
                        </div>
                    </div>

                    @if (in_array($loan->status, ['pending', 'approved']))
                        <div class="px-5 py-2.5 text-xs border-b border-[#E3EBE5] dark:border-[#203A2B] bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                            <i class="fas fa-circle-info mr-1" aria-hidden="true"></i>
                            {{ $loan->status === 'pending' ? 'Payments can be recorded once this loan is approved.' : 'Recording the first payment activates this loan.' }}
                        </div>
                    @endif

                    <div class="kp-table-wrap overflow-x-auto">
                        <table class="kp-table w-full">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Due Date</th>
                                    <th class="text-right">Amount Due</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Remaining</th>
                                    <th class="text-center">Status</th>
                                    @if ($userPermissions['canPay'])
                                        <th class="text-center">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($monthlyRows as $row)
                                    <tr class="hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition-colors">
                                        <td class="text-xs font-semibold">{{ $monthLabel($row['month']) }}</td>
                                        <td class="text-xs font-medium">{{ \Carbon\Carbon::parse($row['due_date'])->format('M d, Y') }}</td>
                                        <td class="text-xs text-right kp-num font-medium">₱{{ number_format($row['amount'], 2) }}</td>
                                        <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]">₱{{ number_format($row['paid'], 2) }}</td>
                                        <td class="text-xs text-right kp-num">₱{{ number_format($row['remaining'], 2) }}</td>
                                        <td class="text-center">
                                            <span class="kp-badge text-xs {{ $monthStatus[$row['status']][1] }}">{{ $monthStatus[$row['status']][0] }}</span>
                                        </td>
                                        @if ($userPermissions['canPay'])
                                            <td class="text-center whitespace-nowrap">
                                                @if ($row['remaining'] > 0)
                                                    <button type="button" @click="markPaid('{{ $row['month'] }}')" class="kp-btn kp-btn-primary text-xs py-1 px-2.5"
                                                        title="Pay the full ₱{{ number_format($row['remaining'], 2) }} dated today">
                                                        <i class="fas fa-check mr-1" aria-hidden="true"></i> Mark Paid
                                                    </button>
                                                    <button type="button" @click="openPayment('{{ $row['month'] }}')" class="kp-btn kp-btn-secondary text-xs py-1 px-2.5"
                                                        title="Partial payment, another date or a reference no.">
                                                        Pay…
                                                    </button>
                                                @else
                                                    <span class="text-xs text-[#52705F]"><i class="fas fa-check text-emerald-600"></i> Settled</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-12 text-center text-xs text-[#52705F]">No monthly schedule yet. Use Edit Schedule to set the months.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($monthlyRows->isNotEmpty())
                                <tfoot>
                                    <tr class="font-bold">
                                        <td class="text-xs" colspan="2">Total</td>
                                        <td class="text-xs text-right kp-num">₱{{ number_format($scheduledTotal, 2) }}</td>
                                        <td class="text-xs text-right kp-num text-[#005B2A] dark:text-[#52C97E]">₱{{ number_format($collected, 2) }}</td>
                                        <td class="text-xs text-right kp-num">₱{{ number_format($scheduledTotal - $collected, 2) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="kp-card overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-receipt text-[#005B2A] dark:text-[#52C97E]"></i>
                            <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Payment History</h3>
                        </div>
                        <span class="text-xs text-[#52705F] dark:text-[#8AA895]">{{ $payments->count() }} payment(s)</span>
                    </div>
                    <div class="kp-table-wrap overflow-x-auto">
                        <table class="kp-table w-full">
                            <thead>
                                <tr>
                                    <th>Date Paid</th>
                                    <th>Month</th>
                                    <th class="text-right">Amount</th>
                                    <th>Reference</th>
                                    <th>Remarks</th>
                                    <th>Recorded By</th>
                                    @if ($userPermissions['canUndoPayment'])
                                        <th class="text-center">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td class="text-xs font-medium">{{ $payment->paid_date->format('M d, Y') }}</td>
                                        <td class="text-xs">{{ $monthLabel($payment->month) }}</td>
                                        <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]">₱{{ number_format($payment->amount, 2) }}</td>
                                        <td class="text-xs font-mono">{{ $payment->reference ?: '—' }}</td>
                                        <td class="text-xs">{{ $payment->remarks ?: '—' }}</td>
                                        <td class="text-xs">{{ trim(($payment->recordedByUser->fname ?? '') . ' ' . ($payment->recordedByUser->lname ?? '')) ?: '—' }}</td>
                                        @if ($userPermissions['canUndoPayment'])
                                            <td class="text-center">
                                                <button type="button" @click="undoPayment({{ $payment->id }}, '{{ $monthLabel($payment->month) }}', {{ (float) $payment->amount }})"
                                                    class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] text-xs text-rose-600" title="Undo this payment">
                                                    <i class="fas fa-rotate-left mr-1" aria-hidden="true"></i> Undo
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-xs text-[#52705F]">No payments recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Documents & PDF Reports Card -->
                <div class="kp-card p-6">
                    <div class="flex items-center gap-2 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <i class="fas fa-file-pdf text-rose-600 dark:text-rose-400"></i>
                        <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Reports & Printable PDFs</h3>
                    </div>

                    <div class="space-y-2.5">
                        <a href="{{ route('loans.amortization-pdf', $loan) }}" data-pdf-preview="Amortization Schedule"
                            class="flex items-center justify-between p-3 rounded-xl bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B] hover:border-[#005B2A] dark:hover:border-[#52C97E] transition group">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-calendar-alt text-[#005B2A] dark:text-[#52C97E] text-base"></i>
                                <div>
                                    <p class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED]">Amortization Schedule</p>
                                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Installment ledger slip</p>
                                </div>
                            </div>
                            <i class="fas fa-download text-xs text-[#52705F] group-hover:text-[#005B2A] dark:group-hover:text-[#52C97E] transition"></i>
                        </a>

                        <a href="{{ route('loans.details-pdf', $loan) }}" data-pdf-preview="Full Loan Disclosure"
                            class="flex items-center justify-between p-3 rounded-xl bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B] hover:border-[#005B2A] dark:hover:border-[#52C97E] transition group">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-file-invoice text-[#005B2A] dark:text-[#52C97E] text-base"></i>
                                <div>
                                    <p class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED]">Full Loan Disclosure</p>
                                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Comprehensive facility agreement</p>
                                </div>
                            </div>
                            <i class="fas fa-download text-xs text-[#52705F] group-hover:text-[#005B2A] dark:group-hover:text-[#52C97E] transition"></i>
                        </a>

                        <a href="{{ route('loans.soa-pdf', $loan) }}" data-pdf-preview="Statement of Account"
                            class="flex items-center justify-between p-3 rounded-xl bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B] hover:border-[#005B2A] dark:hover:border-[#52C97E] transition group">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-receipt text-[#005B2A] dark:text-[#52C97E] text-base"></i>
                                <div>
                                    <p class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED]">Statement of Account</p>
                                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Settlement ledger history</p>
                                </div>
                            </div>
                            <i class="fas fa-download text-xs text-[#52705F] group-hover:text-[#005B2A] dark:group-hover:text-[#52C97E] transition"></i>
                        </a>
                    </div>
                </div>

                <!-- Audit Information Card -->
                <div class="kp-card p-6">
                    <div class="flex items-center gap-2 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <i class="fas fa-shield-halved text-[#52705F]"></i>
                        <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Compliance & Audit Trail</h3>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between py-1.5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <span class="text-[#52705F] dark:text-[#8AA895]">Date Applied</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->application_date->format('M d, Y') }}</span>
                        </div>
                        @if ($loan->approved_date)
                            <div class="flex items-center justify-between py-1.5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                                <span class="text-[#52705F] dark:text-[#8AA895]">Date Approved</span>
                                <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->approved_date->format('M d, Y') }}</span>
                            </div>
                        @endif
                        @if ($loan->approvedByUser)
                            <div class="flex items-center justify-between py-1.5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                                <span class="text-[#52705F] dark:text-[#8AA895]">Approved By</span>
                                <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->approvedByUser->fname }} {{ $loan->approvedByUser->lname }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between py-1.5">
                            <span class="text-[#52705F] dark:text-[#8AA895]">Officer Assigned</span>
                            <span class="font-medium text-[#172B20] dark:text-[#EAF5ED]">{{ $loan->createdByUser->fname ?? 'System' }} {{ $loan->createdByUser->lname ?? '' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Schedule Editor -->
        <div x-show="scheduleModal" @keydown.escape.window="scheduleModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;" x-cloak>
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="scheduleModal = false"></div>
            <div class="kp-modal relative max-w-2xl w-full p-4 sm:p-6 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="loan-schedule-title">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div>
                        <h3 id="loan-schedule-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Edit Monthly Schedule</h3>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Total repayable ₱{{ number_format($loan->total_amount, 2) }} (principal + interest)</p>
                    </div>
                    <button type="button" @click="scheduleModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895]">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="flex items-center justify-end gap-2 mb-2">
                    <button type="button" @click="addScheduleRow()" class="kp-btn kp-btn-secondary text-xs py-1 px-2.5">
                        <i class="fas fa-plus mr-1" aria-hidden="true"></i> Add Month
                    </button>
                    <button type="button" @click="splitEvenly()" :disabled="!schedule.length" class="kp-btn kp-btn-secondary text-xs py-1 px-2.5 disabled:opacity-50">
                        <i class="fas fa-arrow-right-arrow-left mr-1" aria-hidden="true"></i> Auto Split Evenly
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider text-[#52705F] dark:text-[#8AA895]">
                                <th class="text-left font-semibold px-2 py-2">Month</th>
                                <th class="text-left font-semibold px-2 py-2">Due Date</th>
                                <th class="text-right font-semibold px-2 py-2">Amount (₱)</th>
                                <th class="text-right font-semibold px-2 py-2">Paid</th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in schedule" :key="row.uid">
                                <tr class="border-t border-[#E3EBE5] dark:border-[#203A2B]">
                                    <td class="px-2 py-1.5">
                                        <select x-model="row.month" @change="row.due_date = monthEnd(row.month)" :disabled="row.paid > 0"
                                            class="kp-input py-1 text-xs min-w-[8rem] disabled:opacity-70"
                                            :class="scheduleDuplicates().includes(row.month) ? 'border-rose-500 text-rose-700' : ''">
                                            <template x-for="m in monthOptions" :key="m.value">
                                                <option :value="m.value" :selected="m.value === row.month" x-text="m.label"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1.5"><input type="date" x-model="row.due_date" class="kp-input py-1 text-xs"></td>
                                    <td class="px-2 py-1.5">
                                        <input type="number" x-model="row.amount" step="any" min="0.01" class="kp-input py-1 text-xs text-right kp-num w-full min-w-[7rem]"
                                            :class="belowPaid(row) ? 'border-rose-500' : ''">
                                    </td>
                                    <td class="px-2 py-1.5 text-right kp-num whitespace-nowrap" x-text="row.paid > 0 ? '₱' + money(row.paid) : '—'"></td>
                                    <td class="px-2 py-1.5 text-right">
                                        <button type="button" @click="schedule = schedule.filter(r => r.uid !== row.uid)" :disabled="row.paid > 0"
                                            class="p-1.5 rounded-md text-rose-600 hover:bg-[var(--kp-surface-2)] disabled:opacity-30" :title="row.paid > 0 ? 'This month has payments' : 'Remove month'">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between mt-3 px-3 py-2 rounded-lg text-xs"
                    :class="scheduleBalanced() ? 'bg-[#EAF5ED] dark:bg-[#1A3324]' : 'bg-rose-50 dark:bg-rose-950/30'">
                    <span class="font-bold uppercase tracking-wider">Total</span>
                    <span class="font-bold kp-num" :class="scheduleBalanced() ? 'text-[#005B2A] dark:text-[#52C97E]' : 'text-rose-700 dark:text-rose-400'"
                        x-text="'₱' + money(scheduleTotal()) + ' of ₱' + money(totalRepayable)"></span>
                </div>
                <p x-show="scheduleProblem()" class="text-xs mt-2 text-rose-700 dark:text-rose-400">
                    <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i> <span x-text="scheduleProblem()"></span>
                </p>

                <div class="flex items-center justify-end gap-3 pt-3 mt-3 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <button type="button" @click="scheduleModal = false" class="kp-btn kp-btn-secondary text-xs">Cancel</button>
                    <button type="button" @click="saveSchedule()" :disabled="saving || !!scheduleProblem()" class="kp-btn kp-btn-primary text-xs disabled:opacity-50">
                        <i class="fas fa-save mr-1.5" aria-hidden="true"></i> <span x-text="saving ? 'Saving…' : 'Save Schedule'"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Record Payment -->
        <div x-show="payModal" @keydown.escape.window="payModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;" x-cloak>
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="payModal = false"></div>
            <div class="kp-modal relative max-w-md w-full p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="loan-payment-modal-title">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <h3 id="loan-payment-modal-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Record Loan Payment</h3>
                    <button type="button" @click="payModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895]">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <form @submit.prevent="submitPayment()" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Month</label>
                        <select x-model="form.month" @change="form.amount = remainingFor(form.month)" class="kp-input w-full">
                            <template x-for="m in openMonths()" :key="m.month">
                                <option :value="m.month" :selected="m.month === form.month"
                                    x-text="monthLabel(m.month) + ' · due ' + formatDate(m.due_date) + ' · ₱' + money(m.remaining) + ' left'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Amount Paid (₱)</label>
                        <div class="flex gap-2">
                            <input type="number" x-model="form.amount" step="any" min="0.01" required class="kp-input w-full text-right kp-num">
                            <button type="button" @click="form.amount = remainingFor(form.month)" class="kp-btn kp-btn-secondary text-xs whitespace-nowrap">Full</button>
                        </div>
                        <p class="text-[11px] mt-1" :class="overRemaining() ? 'text-rose-600' : 'text-[#52705F] dark:text-[#8AA895]'"
                            x-text="overRemaining() ? 'More than the ₱' + money(remainingFor(form.month)) + ' left for this month.' : 'Remaining for this month: ₱' + money(remainingFor(form.month))"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Date Paid</label>
                            <input type="date" x-model="form.paid_date" required class="kp-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Reference / OR No.</label>
                            <input type="text" x-model="form.reference" maxlength="50" class="kp-input w-full" placeholder="Optional">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Remarks</label>
                        <input type="text" x-model="form.remarks" maxlength="255" class="kp-input w-full" placeholder="Optional, e.g. deducted from voucher">
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                        <button type="button" @click="payModal = false" class="kp-btn kp-btn-secondary text-xs">Cancel</button>
                        <button type="submit" :disabled="saving || overRemaining() || !(parseFloat(form.amount) > 0)" class="kp-btn kp-btn-primary text-xs disabled:opacity-50">
                            <i class="fas fa-check mr-1.5" aria-hidden="true"></i> <span x-text="saving ? 'Saving…' : 'Confirm Payment'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // The monthly schedule was set when the loan was created: no start date to pick
            function approveLoan() {
                Swal.fire({
                    title: 'Approve Loan Application?',
                    text: 'Repayment follows the monthly schedule ({{ $monthlyRows->count() }} month(s), ₱{{ number_format($scheduledTotal, 2) }}).',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Confirm Approval',
                    confirmButtonColor: '#005B2A',
                    cancelButtonText: 'Cancel'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route('loans.approve', $loan) }}';
                    form.innerHTML = '@csrf';
                    document.body.appendChild(form);
                    form.submit();
                });
            }

            function rejectLoan() {
                Swal.fire({
                    title: 'Reject Loan Application?',
                    input: 'text',
                    inputLabel: 'Reason for Rejection',
                    inputPlaceholder: 'Enter review remarks...',
                    showCancelButton: true,
                    confirmButtonText: 'Reject Application',
                    confirmButtonColor: '#dc2626',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route('loans.reject', $loan) }}';
                        form.innerHTML = '@csrf<input type="hidden" name="remarks" value="' + (result.value || '') + '">';
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            // Monthly repayments: mark paid, record partial payments, undo, edit the schedule
            function loanPayments() {
                return {
                    months: @json($monthlyRows),
                    totalRepayable: {{ (float) $loan->total_amount }},
                    payModal: false,
                    scheduleModal: false,
                    saving: false,
                    form: { month: '', amount: '', paid_date: '', reference: '', remarks: '' },
                    schedule: [],
                    rowUid: 0,
                    monthOptions: [],

                    init() {
                        // Months to choose from: a year before the first scheduled month to five years ahead
                        const first = this.months.length ? this.months[0].month : this.today().slice(0, 7);
                        const [y, m] = first.split('-').map(Number);
                        const now = new Date();
                        const from = new Date(Math.min(new Date(y, m - 1, 1), new Date(now.getFullYear(), now.getMonth(), 1)));
                        for (let i = -12; i <= 72; i++) {
                            const d = new Date(from.getFullYear(), from.getMonth() + i, 1);
                            const value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                            this.monthOptions.push({ value, label: this.monthLabel(value) });
                        }
                    },
                    today() {
                        const now = new Date();
                        return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
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
                    money(n) {
                        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    formatDate(ymd) {
                        const [y, m, d] = ymd.split('-').map(Number);
                        return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                    },
                    async request(url, method, body) {
                        const r = await fetch(url, {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: body ? JSON.stringify(body) : undefined
                        });
                        const d = await r.json();
                        if (!r.ok) throw new Error(d.message || 'Request failed');
                        return d;
                    },

                    // ---------- payments ----------
                    openMonths() {
                        return this.months.filter(m => m.remaining > 0);
                    },
                    remainingFor(month) {
                        const m = this.months.find(x => x.month === month);
                        return m ? Number(m.remaining).toFixed(2) : '0.00';
                    },
                    overRemaining() {
                        return Math.round((parseFloat(this.form.amount) || 0) * 100) > Math.round(parseFloat(this.remainingFor(this.form.month)) * 100);
                    },
                    openPayment(month) {
                        this.form = { month, amount: this.remainingFor(month), paid_date: this.today(), reference: '', remarks: '' };
                        this.payModal = true;
                    },
                    // One click: pay the month's full remaining amount, dated today
                    async markPaid(month) {
                        const remaining = this.remainingFor(month);
                        const res = await Swal.fire({
                            icon: 'question',
                            title: 'Mark ' + this.monthLabel(month) + ' as paid?',
                            text: 'Records ₱' + this.money(remaining) + ' paid today.',
                            showCancelButton: true,
                            confirmButtonText: 'Mark Paid',
                            confirmButtonColor: '#005B2A',
                            cancelButtonText: 'Cancel'
                        });
                        if (!res.isConfirmed) return;
                        await this.savePayment({ month, amount: remaining, paid_date: this.today(), reference: '', remarks: '' });
                    },
                    async submitPayment() {
                        await this.savePayment(this.form);
                    },
                    async savePayment(payload) {
                        this.saving = true;
                        try {
                            const d = await this.request('{{ route('loans.payments.store', $loan) }}', 'POST', payload);
                            this.payModal = false;
                            await Swal.fire({ icon: 'success', title: 'Payment recorded', text: d.message, timer: 1800, showConfirmButton: false });
                            window.location.reload();
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        } finally {
                            this.saving = false;
                        }
                    },
                    async undoPayment(id, monthText, amount) {
                        const res = await Swal.fire({
                            icon: 'warning',
                            title: 'Undo payment?',
                            text: 'The ₱' + this.money(amount) + ' payment for ' + monthText + ' will be removed and added back to the balance.',
                            showCancelButton: true,
                            confirmButtonText: 'Undo Payment',
                            confirmButtonColor: '#dc2626',
                            cancelButtonText: 'Cancel'
                        });
                        if (!res.isConfirmed) return;
                        try {
                            const d = await this.request('{{ url('/loans/' . $loan->id . '/payments') }}/' + id, 'DELETE');
                            await Swal.fire({ icon: 'success', title: 'Payment undone', text: d.message, timer: 1800, showConfirmButton: false });
                            window.location.reload();
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    },

                    // ---------- schedule editor ----------
                    openSchedule() {
                        this.schedule = this.months.map(m => ({ uid: ++this.rowUid, month: m.month, due_date: m.due_date, amount: Number(m.amount).toFixed(2), paid: m.paid }));
                        this.scheduleModal = true;
                    },
                    addScheduleRow() {
                        const used = this.schedule.map(r => r.month);
                        const lastMonth = this.schedule.length ? this.schedule[this.schedule.length - 1].month : this.today().slice(0, 7);
                        const last = this.monthOptions.findIndex(m => m.value === lastMonth);
                        const next = this.monthOptions.slice(last + 1).find(m => !used.includes(m.value)) || this.monthOptions.find(m => !used.includes(m.value));
                        this.schedule.push({ uid: ++this.rowUid, month: next.value, due_date: this.monthEnd(next.value), amount: '', paid: 0 });
                    },
                    // Split evenly in centavos; the last month takes the remainder
                    splitEvenly() {
                        const n = this.schedule.length;
                        const cents = Math.round(this.totalRepayable * 100);
                        const each = Math.floor(cents / n);
                        this.schedule.forEach((r, i) => r.amount = ((i === n - 1 ? cents - each * (n - 1) : each) / 100).toFixed(2));
                    },
                    scheduleTotal() {
                        return this.schedule.reduce((t, r) => t + Math.round((parseFloat(r.amount) || 0) * 100), 0) / 100;
                    },
                    scheduleBalanced() {
                        return Math.round(this.scheduleTotal() * 100) === Math.round(this.totalRepayable * 100);
                    },
                    scheduleDuplicates() {
                        const seen = {};
                        this.schedule.forEach(r => seen[r.month] = (seen[r.month] || 0) + 1);
                        return Object.keys(seen).filter(m => seen[m] > 1);
                    },
                    belowPaid(row) {
                        return row.paid > 0 && Math.round((parseFloat(row.amount) || 0) * 100) < Math.round(row.paid * 100);
                    },
                    scheduleProblem() {
                        if (!this.schedule.length) return 'Add at least one month.';
                        if (this.scheduleDuplicates().length) return this.scheduleDuplicates().map(m => this.monthLabel(m)).join(', ') + ' is listed more than once.';
                        if (this.schedule.some(r => !(parseFloat(r.amount) > 0))) return 'Each month needs an amount.';
                        if (this.schedule.some(r => r.due_date && r.due_date.slice(0, 7) !== r.month)) return 'Each due date must be within its month.';
                        const low = this.schedule.find(r => this.belowPaid(r));
                        if (low) return this.monthLabel(low.month) + ' already has ₱' + this.money(low.paid) + ' paid; its amount cannot be lower.';
                        if (!this.scheduleBalanced()) {
                            const diff = this.totalRepayable - this.scheduleTotal();
                            return 'Months are ' + (diff > 0 ? 'short' : 'over') + ' by ₱' + this.money(Math.abs(diff)) + '. They must equal the total repayable.';
                        }
                        return '';
                    },
                    async saveSchedule() {
                        this.saving = true;
                        try {
                            const d = await this.request('{{ route('loans.schedule.update', $loan) }}', 'POST', {
                                schedule: this.schedule.map(r => ({ month: r.month, due_date: r.due_date || null, amount: Math.round((parseFloat(r.amount) || 0) * 100) / 100 })),
                            });
                            this.scheduleModal = false;
                            await Swal.fire({ icon: 'success', title: 'Saved', text: d.message, timer: 1800, showConfirmButton: false });
                            window.location.reload();
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        } finally {
                            this.saving = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endsection
