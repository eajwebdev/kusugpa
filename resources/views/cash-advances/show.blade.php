<!-- resources/views/cash-advances/show.blade.php -->
@extends('layouts.main')

@section('title', 'CA #' . $cashAdvance->ca_number)

@section('content')
    @php
        // Weekly cash advances are repaid by their weekly schedule
        $scheduledTotal = $weeklyRows->sum('amount');
        $collected = $weeklyRows->sum('paid');
        $nextWeek = $weeklyRows->firstWhere('remaining', '>', 0);
        $weekStatus = [
            'paid' => ['Paid', 'kp-badge--forest'],
            'partial' => ['Partial', 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'],
            'overdue' => ['Overdue', 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'],
            'upcoming' => ['Upcoming', 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300'],
        ];
    @endphp
    <div class="space-y-6 kp-fade-in" x-data="weeklyPayments()">
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
                    $canProcess = $isAdmin || $isManager || auth()->user()->hasPermission('create-cash-advances') || auth()->user()->hasPermission('process-cash-advance-payments');
                @endphp

                <div class="flex flex-wrap items-center gap-2">
                    @if ($userPermissions['awaitingManager'])
                        <span class="kp-badge kp-badge--amber text-xs py-1 px-2.5">
                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                            Waiting for Manager approval ({{ \App\Support\ApprovalPolicy::thresholdLabel() }} or more)
                        </span>
                    @endif
                    @if ($userPermissions['canApprove'])
                        <button type="button" onclick="approveCA()" class="kp-btn kp-btn-primary text-xs">
                            <i class="fas fa-check mr-1.5" aria-hidden="true"></i> Approve Advance
                        </button>
                    @endif
                    @if ($userPermissions['canReject'])
                        <button type="button" onclick="rejectCA()" class="kp-btn kp-btn-danger text-xs">
                            <i class="fas fa-times mr-1.5" aria-hidden="true"></i> Reject
                        </button>
                    @endif
                    @if ($userPermissions['canActivate'])
                        <form action="{{ route('cash-advances.activate', $cashAdvance) }}" method="POST">
                            @csrf
                            <button type="submit" class="kp-btn kp-btn-primary text-xs">
                                <i class="fas fa-play mr-1.5" aria-hidden="true"></i> Activate Advance
                            </button>
                        </form>
                    @endif
                    @if ($userPermissions['canDelete'])
                        <form action="{{ route('cash-advances.destroy', $cashAdvance) }}" method="POST" onsubmit="return confirm('Permanently delete this cash advance with its weekly schedule and recorded payments? This cannot be undone.')">
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
                <p class="kp-kpi-sub">
                    {{ $cashAdvance->interest_rate }}% interest
                    @if ((float) $cashAdvance->interest_amount > 0)
                        · ₱{{ number_format($cashAdvance->interest_amount, 2) }} · Total ₱{{ number_format($cashAdvance->repayable(), 2) }}
                    @endif
                </p>
            </div>
            @if ($isWeekly)
                <div class="kp-kpi">
                    <p class="kp-kpi-label"><i class="fas fa-calendar-week" aria-hidden="true"></i> Weekly Schedule</p>
                    <p class="kp-kpi-value kp-num" style="color: var(--kp-forest)">{{ $weeklyRows->count() }} week(s)</p>
                    <p class="kp-kpi-sub">
                        @if ($nextWeek)
                            Next: Week {{ $nextWeek['week_no'] }}{{ $nextWeek['due_date'] ? ' · ' . \Carbon\Carbon::parse($nextWeek['due_date'])->format('M d') : '' }} · ₱{{ number_format($nextWeek['remaining'], 2) }}
                        @elseif ($weeklyRows->isNotEmpty())
                            All weeks paid
                        @else
                            No weeks scheduled
                        @endif
                    </p>
                </div>
                <div class="kp-kpi">
                    <p class="kp-kpi-label"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Outstanding Balance</p>
                    <p class="kp-kpi-value kp-num" style="color: {{ $scheduledTotal - $collected > 0 ? '#b91c1c' : 'var(--kp-emerald)' }}">
                        ₱{{ number_format($scheduledTotal - $collected, 2) }}
                    </p>
                    <p class="kp-kpi-sub">Collected ₱{{ number_format($collected, 2) }} of ₱{{ number_format($scheduledTotal, 2) }}</p>
                </div>
            @else
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
            @endif
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
                            <span class="font-bold text-[#172B20] dark:text-[#EAF5ED] kp-num">₱{{ number_format($isWeekly ? $scheduledTotal : $cashAdvance->total_amount, 2) }}</span>
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

                @if ($isWeekly)
                <!-- Weekly Repayment Schedule -->
                <div class="kp-card overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#1A3324] flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-calendar-week text-[#005B2A] dark:text-[#52C97E]"></i>
                            <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Weekly Repayment Schedule</h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($userPermissions['canEditSchedule'])
                                <button type="button" @click="openSchedule()" class="kp-btn kp-btn-secondary text-xs py-1.5 px-3">
                                    <i class="fas fa-pen-to-square mr-1.5" aria-hidden="true"></i> Edit Schedule
                                </button>
                            @endif
                            @if ($userPermissions['canPayWeekly'] && $nextWeek)
                                <button type="button" @click="openPayment('{{ $nextWeek['week_no'] }}')" class="kp-btn kp-btn-primary text-xs py-1.5 px-3">
                                    <i class="fas fa-hand-holding-dollar mr-1.5" aria-hidden="true"></i> Record Payment
                                </button>
                            @endif
                        </div>
                    </div>

                    @if (in_array($cashAdvance->status, ['pending', 'approved']))
                        <div class="px-5 py-2.5 text-xs border-b border-[#E3EBE5] dark:border-[#203A2B] bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                            <i class="fas fa-circle-info mr-1" aria-hidden="true"></i>
                            {{ $cashAdvance->status === 'pending' ? 'Payments can be recorded once this advance is approved.' : 'Recording the first payment activates this advance.' }}
                        </div>
                    @endif

                    <div class="kp-table-wrap overflow-x-auto">
                        <table class="kp-table w-full">
                            <thead>
                                <tr>
                                    <th>Week</th>
                                    <th>Due Date</th>
                                    <th class="text-right">Amount Due</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Remaining</th>
                                    <th class="text-center">Status</th>
                                    @if ($userPermissions['canPayWeekly'])
                                        <th class="text-center">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($weeklyRows as $row)
                                    <tr class="hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition-colors">
                                        <td class="text-xs font-semibold">Week {{ $row['week_no'] }}</td>
                                        <td class="text-xs font-medium">{{ $row['due_date'] ? \Carbon\Carbon::parse($row['due_date'])->format('M d, Y') : '—' }}</td>
                                        <td class="text-xs text-right kp-num font-medium">₱{{ number_format($row['amount'], 2) }}</td>
                                        <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]">₱{{ number_format($row['paid'], 2) }}</td>
                                        <td class="text-xs text-right kp-num">₱{{ number_format($row['remaining'], 2) }}</td>
                                        <td class="text-center">
                                            <span class="kp-badge text-xs {{ $weekStatus[$row['status']][1] }}">{{ $weekStatus[$row['status']][0] }}</span>
                                        </td>
                                        @if ($userPermissions['canPayWeekly'])
                                            <td class="text-center whitespace-nowrap">
                                                @if ($row['remaining'] > 0)
                                                    <button type="button" @click="markPaid('{{ $row['week_no'] }}')" class="kp-btn kp-btn-primary text-xs py-1 px-2.5"
                                                        title="Pay the full ₱{{ number_format($row['remaining'], 2) }} dated today">
                                                        <i class="fas fa-check mr-1" aria-hidden="true"></i> Mark Paid
                                                    </button>
                                                    <button type="button" @click="openPayment('{{ $row['week_no'] }}')" class="kp-btn kp-btn-secondary text-xs py-1 px-2.5"
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
                                        <td colspan="7" class="py-12 text-center text-xs text-[#52705F]">No weekly schedule. Add deduction weeks from CA Receipt Details on the Cash Advances list.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($weeklyRows->isNotEmpty())
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
                                    <th>Week</th>
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
                                        <td class="text-xs">Week {{ $payment->week_no }}</td>
                                        <td class="text-xs text-right kp-num font-bold text-[#005B2A] dark:text-[#52C97E]">₱{{ number_format($payment->amount, 2) }}</td>
                                        <td class="text-xs font-mono">{{ $payment->reference ?: '—' }}</td>
                                        <td class="text-xs">{{ $payment->remarks ?: '—' }}</td>
                                        <td class="text-xs">{{ trim(($payment->recordedByUser->fname ?? '') . ' ' . ($payment->recordedByUser->lname ?? '')) ?: '—' }}</td>
                                        @if ($userPermissions['canUndoPayment'])
                                            <td class="text-center">
                                                <button type="button" @click="undoPayment({{ $payment->id }}, '{{ $payment->week_no }}', {{ (float) $payment->amount }})"
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
                @else
                @if ($canSwitchToWeekly)
                    <!-- Older monthly cash advance: offer the weekly schedule -->
                    <div class="kp-card p-5 flex flex-wrap items-center justify-between gap-3 border-l-4 border-l-[#005B2A]">
                        <div class="text-xs">
                            <p class="font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Switch to a weekly schedule</p>
                            <p class="text-[#52705F] dark:text-[#8AA895] mt-0.5">Pick the weeks this advance is repaid. Each week can then be marked paid in one click, and Hauling Liquidity deducts the same weeks. The unpaid monthly installments below are replaced.</p>
                        </div>
                        <button type="button" @click="openSchedule()" class="kp-btn kp-btn-primary text-xs">
                            <i class="fas fa-calendar-week mr-1.5" aria-hidden="true"></i> Set Up Weekly Schedule
                        </button>
                    </div>
                @endif
                <!-- Amortization Schedule Card (monthly cash advances) -->
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
                @endif
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

        @if ($isWeekly || $canSwitchToWeekly)
            <!-- Weekly Schedule Editor -->
            <div x-show="scheduleModal" @keydown.escape.window="scheduleModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;" x-cloak>
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="scheduleModal = false"></div>
                <div class="kp-modal relative max-w-2xl w-full p-4 sm:p-6 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="schedule-editor-title">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <div>
                            <h3 id="schedule-editor-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">{{ $isWeekly ? 'Edit Weekly Schedule' : 'Set Up Weekly Schedule' }}</h3>
                            <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">
                                Crop year {{ $cashAdvance->crop_year }} · Advance ₱{{ number_format($cashAdvance->amount, 2) }}
                                @if ((float) $cashAdvance->interest_amount > 0)
                                    + interest ₱{{ number_format($cashAdvance->interest_amount, 2) }} = ₱{{ number_format($cashAdvance->repayable(), 2) }} to repay
                                @endif
                            </p>
                        </div>
                        <button type="button" @click="scheduleModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895]">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>

                    <div class="flex items-center justify-end gap-2 mb-2">
                        <button type="button" @click="addScheduleRow()" :disabled="!cropWeeks.length" class="kp-btn kp-btn-secondary text-xs py-1 px-2.5 disabled:opacity-50">
                            <i class="fas fa-plus mr-1" aria-hidden="true"></i> Add Week
                        </button>
                        <button type="button" @click="splitEvenly()" :disabled="!schedule.length" class="kp-btn kp-btn-secondary text-xs py-1 px-2.5 disabled:opacity-50">
                            <i class="fas fa-arrow-right-arrow-left mr-1" aria-hidden="true"></i> Auto Split Evenly
                        </button>
                    </div>

                    <p x-show="!cropWeeks.length" class="text-xs text-rose-600 mb-2">No weeks are set up for this crop year in Crop &amp; Week Management.</p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider text-[#52705F] dark:text-[#8AA895]">
                                    <th class="text-left font-semibold px-2 py-2">Week</th>
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
                                            <select x-model="row.week_no" @change="row.due_date = weekEnd(row.week_no)" :disabled="row.paid > 0"
                                                class="kp-input py-1 text-xs min-w-[6.5rem] disabled:opacity-70"
                                                :class="scheduleDuplicates().includes(row.week_no) ? 'border-rose-500 text-rose-700' : ''">
                                                <template x-for="w in cropWeeks" :key="w.week_no">
                                                    <option :value="w.week_no" :selected="w.week_no === row.week_no" x-text="'Week ' + w.week_no"></option>
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
                                                class="p-1.5 rounded-md text-rose-600 hover:bg-[var(--kp-surface-2)] disabled:opacity-30" :title="row.paid > 0 ? 'This week has payments' : 'Remove week'">
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
                            x-text="'₱' + money(scheduleTotal()) + ' of ₱' + money(advanceAmount)"></span>
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
        @endif

        @if ($isWeekly)
            <!-- Record Weekly Payment -->
            <div x-show="payModal" @keydown.escape.window="payModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;" x-cloak>
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="payModal = false"></div>
                <div class="kp-modal relative max-w-md w-full p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="weekly-payment-title">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                        <h3 id="weekly-payment-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Record Payment</h3>
                        <button type="button" @click="payModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895]">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                    <form @submit.prevent="submitPayment()" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Week</label>
                            <select x-model="form.week_no" @change="form.amount = remainingFor(form.week_no)" class="kp-input w-full">
                                <template x-for="w in openWeeks()" :key="w.week_no">
                                    <option :value="w.week_no" :selected="w.week_no === form.week_no"
                                        x-text="'Week ' + w.week_no + (w.due_date ? ' · due ' + formatDate(w.due_date) : '') + ' · ₱' + money(w.remaining) + ' left'"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Amount Paid (₱)</label>
                            <div class="flex gap-2">
                                <input type="number" x-model="form.amount" step="any" min="0.01" required class="kp-input w-full text-right kp-num">
                                <button type="button" @click="form.amount = remainingFor(form.week_no)" class="kp-btn kp-btn-secondary text-xs whitespace-nowrap">Full</button>
                            </div>
                            <p class="text-[11px] mt-1" :class="overRemaining() ? 'text-rose-600' : 'text-[#52705F] dark:text-[#8AA895]'"
                                x-text="overRemaining() ? 'More than the ₱' + money(remainingFor(form.week_no)) + ' left for this week.' : 'Remaining for this week: ₱' + money(remainingFor(form.week_no))"></p>
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
                            <input type="text" x-model="form.remarks" maxlength="255" class="kp-input w-full" placeholder="Optional, e.g. deducted from Hauling Liquidity">
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
        @endif
    </div>

    <!-- Payment Modal (monthly cash advances) -->
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
                @if ($isWeekly)
                    // Weekly cash advances follow their schedule: no start date to pick
                    Swal.fire({
                        title: 'Approve Cash Advance?',
                        text: 'Repayment follows the weekly schedule ({{ $weeklyRows->count() }} week(s), ₱{{ number_format($scheduledTotal, 2) }}).',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Confirm Approval',
                        confirmButtonColor: '#005B2A',
                        cancelButtonText: 'Cancel'
                    }).then(result => {
                        if (!result.isConfirmed) return;
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route('cash-advances.approve', $cashAdvance) }}';
                        form.innerHTML = '@csrf';
                        document.body.appendChild(form);
                        form.submit();
                    });
                    return;
                @endif
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

            // Weekly repayments: record against a schedule week, undo a mistaken entry
            function weeklyPayments() {
                return {
                    weeks: @json($weeklyRows),
                    cropWeeks: @json($cropWeeks),
                    // What the weeks must add up to: the advance plus its interest
                    advanceAmount: {{ $cashAdvance->repayable() }},
                    payModal: false,
                    scheduleModal: false,
                    saving: false,
                    form: { week_no: '', amount: '', paid_date: '', reference: '', remarks: '' },
                    schedule: [],
                    rowUid: 0,

                    today() {
                        const now = new Date();
                        return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
                    },

                    // One click: pay the week's full remaining amount, dated today
                    async markPaid(weekNo) {
                        const remaining = this.remainingFor(weekNo);
                        const res = await Swal.fire({
                            icon: 'question',
                            title: 'Mark Week ' + weekNo + ' as paid?',
                            text: 'Records ₱' + this.money(remaining) + ' paid today.',
                            showCancelButton: true,
                            confirmButtonText: 'Mark Paid',
                            confirmButtonColor: '#005B2A',
                            cancelButtonText: 'Cancel'
                        });
                        if (!res.isConfirmed) return;
                        try {
                            const d = await this.request('{{ route('cash-advances.weekly-payments.store', $cashAdvance) }}', 'POST',
                                { week_no: weekNo, amount: remaining, paid_date: this.today(), reference: '', remarks: '' });
                            await Swal.fire({ icon: 'success', title: 'Marked paid', text: d.message, timer: 1800, showConfirmButton: false });
                            window.location.reload();
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    },

                    // ---------- schedule editor ----------
                    weekEnd(weekNo) {
                        return this.cropWeeks.find(w => w.week_no === weekNo)?.week_end || '';
                    },
                    openSchedule() {
                        this.schedule = this.weeks.map(w => ({ uid: ++this.rowUid, week_no: w.week_no, due_date: w.due_date || this.weekEnd(w.week_no), amount: Number(w.amount).toFixed(2), paid: w.paid }));
                        if (!this.schedule.length && this.cropWeeks.length) {
                            // Start from the current week of the crop year
                            const today = this.today();
                            const current = this.cropWeeks.find(w => w.week_end && w.week_end >= today) || this.cropWeeks[0];
                            this.schedule = [{ uid: ++this.rowUid, week_no: current.week_no, due_date: current.week_end || '', amount: this.advanceAmount.toFixed(2), paid: 0 }];
                        }
                        this.scheduleModal = true;
                    },
                    addScheduleRow() {
                        const used = this.schedule.map(r => r.week_no);
                        const last = this.schedule.length ? this.cropWeeks.findIndex(w => w.week_no === this.schedule[this.schedule.length - 1].week_no) : -1;
                        const next = this.cropWeeks.slice(last + 1).find(w => !used.includes(w.week_no)) || this.cropWeeks.find(w => !used.includes(w.week_no)) || this.cropWeeks[0];
                        this.schedule.push({ uid: ++this.rowUid, week_no: next.week_no, due_date: next.week_end || '', amount: '', paid: 0 });
                    },
                    // Split evenly in centavos; the last week takes the remainder
                    splitEvenly() {
                        const n = this.schedule.length;
                        const cents = Math.round(this.advanceAmount * 100);
                        const each = Math.floor(cents / n);
                        this.schedule.forEach((r, i) => r.amount = ((i === n - 1 ? cents - each * (n - 1) : each) / 100).toFixed(2));
                    },
                    scheduleTotal() {
                        return this.schedule.reduce((t, r) => t + Math.round((parseFloat(r.amount) || 0) * 100), 0) / 100;
                    },
                    scheduleBalanced() {
                        return Math.round(this.scheduleTotal() * 100) === Math.round(this.advanceAmount * 100);
                    },
                    scheduleDuplicates() {
                        const seen = {};
                        this.schedule.forEach(r => seen[r.week_no] = (seen[r.week_no] || 0) + 1);
                        return Object.keys(seen).filter(w => seen[w] > 1);
                    },
                    belowPaid(row) {
                        return row.paid > 0 && Math.round((parseFloat(row.amount) || 0) * 100) < Math.round(row.paid * 100);
                    },
                    scheduleProblem() {
                        if (!this.schedule.length) return 'Add at least one week.';
                        if (this.scheduleDuplicates().length) return 'Week ' + this.scheduleDuplicates().join(', ') + ' is listed more than once.';
                        if (this.schedule.some(r => !(parseFloat(r.amount) > 0))) return 'Each week needs an amount.';
                        const low = this.schedule.find(r => this.belowPaid(r));
                        if (low) return 'Week ' + low.week_no + ' already has ₱' + this.money(low.paid) + ' paid; its amount cannot be lower.';
                        if (!this.scheduleBalanced()) {
                            const diff = this.advanceAmount - this.scheduleTotal();
                            return 'Weeks are ' + (diff > 0 ? 'short' : 'over') + ' by ₱' + this.money(Math.abs(diff)) + '. They must equal the amount to repay.';
                        }
                        return '';
                    },
                    async saveSchedule() {
                        this.saving = true;
                        try {
                            const d = await this.request('{{ route('cash-advances.schedule.update', $cashAdvance) }}', 'POST', {
                                schedule: this.schedule.map(r => ({ week_no: r.week_no, due_date: r.due_date || null, amount: Math.round((parseFloat(r.amount) || 0) * 100) / 100 })),
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

                    openWeeks() {
                        return this.weeks.filter(w => w.remaining > 0);
                    },
                    remainingFor(weekNo) {
                        const w = this.weeks.find(x => x.week_no === weekNo);
                        return w ? Number(w.remaining).toFixed(2) : '0.00';
                    },
                    overRemaining() {
                        return Math.round((parseFloat(this.form.amount) || 0) * 100) > Math.round(parseFloat(this.remainingFor(this.form.week_no)) * 100);
                    },
                    openPayment(weekNo) {
                        this.form = {
                            week_no: weekNo,
                            amount: this.remainingFor(weekNo),
                            paid_date: this.today(),
                            reference: '',
                            remarks: '',
                        };
                        this.payModal = true;
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
                    async submitPayment() {
                        this.saving = true;
                        try {
                            const d = await this.request('{{ route('cash-advances.weekly-payments.store', $cashAdvance) }}', 'POST', this.form);
                            this.payModal = false;
                            await Swal.fire({ icon: 'success', title: 'Payment recorded', text: d.message, timer: 2000, showConfirmButton: false });
                            window.location.reload();
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        } finally {
                            this.saving = false;
                        }
                    },
                    async undoPayment(id, weekNo, amount) {
                        const res = await Swal.fire({
                            icon: 'warning',
                            title: 'Undo payment?',
                            text: 'The ₱' + this.money(amount) + ' payment for Week ' + weekNo + ' will be removed and added back to the balance.',
                            showCancelButton: true,
                            confirmButtonText: 'Undo Payment',
                            confirmButtonColor: '#dc2626',
                            cancelButtonText: 'Cancel'
                        });
                        if (!res.isConfirmed) return;
                        try {
                            const d = await this.request('{{ url('/cash-advances/' . $cashAdvance->id . '/weekly-payments') }}/' + id, 'DELETE');
                            await Swal.fire({ icon: 'success', title: 'Payment undone', text: d.message, timer: 2000, showConfirmButton: false });
                            window.location.reload();
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
                        }
                    },
                    money(n) {
                        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    formatDate(ymd) {
                        const [y, m, d] = ymd.split('-').map(Number);
                        return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                    },
                };
            }
        </script>
    @endpush
@endsection
