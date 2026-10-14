<!-- resources/views/loans/settings.blade.php -->
@extends('layouts.main')

@section('title', 'Loan Settings')

@section('content')
<div class="space-y-6 kp-fade-in">
    <!-- Page Header -->
    <header>
        <div class="kp-page-header">
            <div>
                <p class="kp-eyebrow">Financial Services Administration</p>
                <h1 class="kp-page-title mt-1">Loan Parameters & Policy Settings</h1>
                <p class="kp-page-sub">Configure default interest rates, term limits, automatic deduction rules, and loan classification tiers.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('loans.index') }}" class="kp-btn kp-btn-secondary text-xs">
                    <i class="fas fa-arrow-left mr-1.5" aria-hidden="true"></i> Back to Loans
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
                    <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Global Loan Defaults</h3>
                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Standard rates and principal ceilings for new facilities.</p>
                </div>
            </div>

            <form action="{{ route('loans.settings.update') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        Default Interest Rate (%)
                    </label>
                    <input type="number" name="default_interest_rate" step="0.01" value="{{ $settings['default_interest_rate'] }}" class="kp-input w-full">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        Maximum Loan Term (Months)
                    </label>
                    <input type="number" name="max_loan_term_months" value="{{ $settings['max_loan_term_months'] }}" class="kp-input w-full">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            Minimum Principal (₱)
                        </label>
                        <input type="number" name="min_loan_amount" step="0.01" value="{{ $settings['min_loan_amount'] }}" class="kp-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            Maximum Principal (₱)
                        </label>
                        <input type="number" name="max_loan_amount" step="0.01" value="{{ $settings['max_loan_amount'] }}" class="kp-input w-full">
                    </div>
                </div>

                <div class="flex items-center justify-between p-3.5 bg-[#F7FAF8] dark:bg-[#13231B] rounded-xl border border-[#E3EBE5] dark:border-[#203A2B]">
                    <div>
                        <span class="text-xs font-semibold text-[#172B20] dark:text-[#EAF5ED]">Auto-deduct Amortizations</span>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Automatically deduct dues from weekly crop remittance vouchers</p>
                    </div>
                    <input type="hidden" name="auto_deduct" value="0">
                    <input type="checkbox" name="auto_deduct" value="1" {{ $settings['auto_deduct'] ? 'checked' : '' }} 
                        class="w-5 h-5 text-[#005B2A] rounded border-[#CFDCD3] focus:ring-[#005B2A]">
                </div>

                <div class="pt-2">
                    <button type="submit" class="kp-btn kp-btn-primary w-full justify-center">
                        <i class="fas fa-save mr-1.5" aria-hidden="true"></i> Save Loan Parameters
                    </button>
                </div>
            </form>
        </div>

        <!-- Loan Types Card -->
        <div class="kp-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 pb-3 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                        <i class="fas fa-tags" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Loan Classifications</h3>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Configured loan products with custom rate and term defaults.</p>
                    </div>
                </div>

                <div class="space-y-2.5 mb-5 max-h-56 overflow-y-auto pr-1">
                    @foreach($loanTypes as $type)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B]">
                        <div>
                            <p class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED]">{{ $type->name }}</p>
                            <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">{{ $type->default_interest_rate }}% • {{ $type->default_term_months }} mos • Max ₱{{ number_format($type->max_amount, 2) }}</p>
                        </div>
                        <form action="{{ route('loans.types.toggle', $type) }}" method="POST">
                            @csrf
                            <button type="submit" class="kp-badge text-xs {{ $type->is_active ? 'kp-badge--forest' : 'kp-badge--amber' }}">
                                {{ $type->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Add Loan Type Form -->
            <form action="{{ route('loans.types.store') }}" method="POST" class="space-y-3 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                @csrf
                <p class="text-[11px] font-bold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider">Add New Product Classification</p>
                <input type="text" name="name" placeholder="Classification Name (e.g., Cane Replanting)" required class="kp-input w-full py-1.5 text-xs">
                <div class="grid grid-cols-3 gap-2">
                    <input type="number" name="default_interest_rate" step="0.01" placeholder="Rate %" required class="kp-input w-full py-1.5 text-xs">
                    <input type="number" name="default_term_months" placeholder="Term (Mos)" required class="kp-input w-full py-1.5 text-xs">
                    <input type="number" name="max_amount" step="0.01" placeholder="Max ₱" required class="kp-input w-full py-1.5 text-xs">
                </div>
                <textarea name="description" placeholder="Description & eligibility terms" rows="2" class="kp-input w-full py-1.5 text-xs"></textarea>
                <button type="submit" class="kp-btn kp-btn-secondary w-full justify-center text-xs">
                    <i class="fas fa-plus mr-1.5" aria-hidden="true"></i> Add Loan Classification
                </button>
            </form>
        </div>
    </div>
</div>
@endsection