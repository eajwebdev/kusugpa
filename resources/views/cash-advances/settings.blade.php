<!-- resources/views/cash-advances/settings.blade.php -->
@extends('layouts.main')

@section('title', 'Cash Advance Settings')

@section('content')
<div class="space-y-6 kp-fade-in">
    <!-- Page Header -->
    <header>
        <div class="kp-page-header">
            <div>
                <p class="kp-eyebrow">Financial Services Administration</p>
                <h1 class="kp-page-title mt-1">Cash Advance Settings & Policy</h1>
                <p class="kp-page-sub">Configure default interest rates, short-term limits, and automatic voucher deduction parameters.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('cash-advances.index') }}" class="kp-btn kp-btn-secondary text-xs">
                    <i class="fas fa-arrow-left mr-1.5" aria-hidden="true"></i> Back to Cash Advances
                </a>
            </div>
        </div>
        <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Default Settings Card -->
        <div class="kp-card p-6">
            <div class="flex items-center gap-2 pb-3 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                    <i class="fas fa-sliders" aria-hidden="true"></i>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Default Advance Parameters</h3>
                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Emergency and seasonal advance baseline limits.</p>
                </div>
            </div>

            <form action="{{ route('cash-advances.settings.update') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        Default Interest Rate (%)
                    </label>
                    <input type="number" name="ca_default_interest_rate" step="0.01" value="{{ $settings['ca_default_interest_rate'] }}" class="kp-input w-full">
                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mt-1">Standard interest applied to cash advance amortizations.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        Maximum Term (Months)
                    </label>
                    <input type="number" name="ca_max_term_months" value="{{ $settings['ca_max_term_months'] }}" class="kp-input w-full">
                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mt-1">Maximum repayment window permitted.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            Minimum Amount (₱)
                        </label>
                        <input type="number" name="ca_min_amount" step="0.01" value="{{ $settings['ca_min_amount'] }}" class="kp-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            Maximum Amount (₱)
                        </label>
                        <input type="number" name="ca_max_amount" step="0.01" value="{{ $settings['ca_max_amount'] }}" class="kp-input w-full">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        CA Receipt Interest (% per week)
                    </label>
                    <input type="number" name="ca_receipt_weekly_interest" step="0.01" min="0" max="100" value="{{ $settings['ca_receipt_weekly_interest'] }}" class="kp-input w-full">
                    <p class="text-[11px] mt-1 text-[#52705F] dark:text-[#8AA895]">Printed on the Cash Advance Receipt and Sugar Assignment (printed from All Cash Advances).</p>
                </div>

                <div class="flex items-center justify-between p-3.5 bg-[#F7FAF8] dark:bg-[#13231B] rounded-xl border border-[#E3EBE5] dark:border-[#203A2B]">
                    <div>
                        <span class="text-xs font-semibold text-[#172B20] dark:text-[#EAF5ED]">Auto-deduct Amortizations</span>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Automatically deduct dues from weekly crop remittance vouchers</p>
                    </div>
                    <input type="hidden" name="ca_auto_deduct" value="0">
                    <input type="checkbox" name="ca_auto_deduct" value="1" {{ ($settings['ca_auto_deduct'] ?? false) ? 'checked' : '' }} 
                        class="w-5 h-5 text-[#005B2A] rounded border-[#CFDCD3] focus:ring-[#005B2A]">
                </div>

                <div class="pt-2">
                    <button type="submit" class="kp-btn kp-btn-primary w-full justify-center">
                        <i class="fas fa-save mr-1.5" aria-hidden="true"></i> Save Cash Advance Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Info Card -->
        <div class="kp-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 pb-3 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Policy Context</h3>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Guidelines for planters emergency financial assistance.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="p-4 rounded-xl bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B]">
                        <h4 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider mb-1">Scope & Purpose</h4>
                        <p class="text-xs text-[#52705F] dark:text-[#8AA895] leading-relaxed">
                            Cash advances are short-term emergency liquidity provisions designed to cover urgent field labor, fertilizer top-ups, tractor fuel, or emergency personal expenses, repaid through weekly cane delivery vouchers.
                        </p>
                    </div>

                    <div class="p-4 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] border border-[#CFDCD3] dark:border-[#203A2B]">
                        <h4 class="text-xs font-bold text-[#005B2A] dark:text-[#52C97E] uppercase tracking-wider mb-2">Active Configuration Summary</h4>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Interest Charge</span>
                                <span class="font-bold text-[#172B20] dark:text-[#EAF5ED]">{{ $settings['ca_default_interest_rate'] }}%</span>
                            </div>
                            <div>
                                <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Maximum Term</span>
                                <span class="font-bold text-[#172B20] dark:text-[#EAF5ED]">{{ $settings['ca_max_term_months'] }} months</span>
                            </div>
                            <div>
                                <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Minimum Amount</span>
                                <span class="font-bold text-[#172B20] dark:text-[#EAF5ED] kp-num">₱{{ number_format($settings['ca_min_amount'], 2) }}</span>
                            </div>
                            <div>
                                <span class="text-[#52705F] dark:text-[#8AA895] block mb-0.5">Maximum Amount</span>
                                <span class="font-bold text-[#172B20] dark:text-[#EAF5ED] kp-num">₱{{ number_format($settings['ca_max_amount'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B] text-[11px] text-[#52705F] dark:text-[#8AA895] flex items-center gap-1.5">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Changes are applied immediately to all newly created cash advance records.</span>
            </div>
        </div>
    </div>
</div>
@endsection