<!-- resources/views/loans/reports.blade.php -->
@extends('layouts.main')

@section('title', 'Loan Reports')

@section('content')
<div class="space-y-6 kp-fade-in">
    <!-- Page Header -->
    <header>
        <div class="kp-page-header">
            <div>
                <p class="kp-eyebrow">Financial Reporting & Statements</p>
                <h1 class="kp-page-title mt-1">Loan Portfolio Reports</h1>
                <p class="kp-page-sub">Generate and print authoritative amortization schedules, statements of account, and monthly collection registers.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('loans.index') }}" class="kp-btn kp-btn-secondary text-xs">
                    <i class="fas fa-arrow-left mr-1.5" aria-hidden="true"></i> Back to Loans
                </a>
            </div>
        </div>
        <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- 1. Amortization Schedule Card -->
        <div class="kp-card p-6 flex flex-col justify-between" 
            x-data="searchableSelect({ loans: {{ json_encode(\App\Models\Loan::whereIn('status', ['approved', 'active'])->orderBy('created_at', 'desc')->get()->toArray()) }}, reportType: 'amortization' })">
            <div>
                <div class="flex items-start gap-3 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="w-10 h-10 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Amortization Schedule</h3>
                        <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-0.5">Print installment breakdown for a specific loan.</p>
                    </div>
                </div>

                <div class="relative" @click.away="open = false">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[#52705F] text-xs"></i>
                        <input type="text" x-model="search" @click="open = true" @input="open = true"
                            placeholder="Search planter or loan #..."
                            class="kp-input w-full pl-8 py-2 text-xs mb-1">
                        <button x-show="search" @click="search = ''; selectedLoan = null; open = false" 
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#52705F] hover:text-[#172B20] dark:hover:text-[#EAF5ED]" x-cloak>
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>

                    <!-- Dropdown List -->
                    <div x-show="open" x-transition x-cloak
                        class="absolute z-20 w-full bg-white dark:bg-[#13231B] border border-[#CFDCD3] dark:border-[#203A2B] rounded-xl shadow-xl max-h-48 overflow-y-auto mt-1 divide-y divide-[#E3EBE5] dark:divide-[#203A2B]">
                        <template x-for="loan in filteredLoans" :key="loan.id">
                            <div @click="selectLoan(loan)" 
                                :class="{'bg-[#EAF5ED] dark:bg-[#1A3324]': selectedLoan?.id === loan.id}"
                                class="px-3.5 py-2 text-xs cursor-pointer hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-[#172B20] dark:text-[#EAF5ED]" x-text="loan.loan_number"></p>
                                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]" x-text="loan.planter_name + ' (' + loan.planter_code + ')'"></p>
                                </div>
                                <span class="kp-badge text-[10px]" 
                                    :class="loan.status === 'active' ? 'kp-badge--forest' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'"
                                    x-text="loan.status"></span>
                            </div>
                        </template>
                        <div x-show="filteredLoans.length === 0" class="px-4 py-3 text-xs text-[#52705F] text-center">No loans found</div>
                    </div>
                </div>

                <!-- Selected Loan Preview -->
                <div x-show="selectedLoan" class="mt-3 p-3 bg-[#EAF5ED] dark:bg-[#1A3324] rounded-xl border border-[#CFDCD3] dark:border-[#203A2B]" x-cloak>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-[#005B2A] dark:text-[#52C97E]" x-text="selectedLoan?.loan_number"></p>
                            <p class="text-[11px] text-[#172B20] dark:text-[#EAF5ED]" x-text="selectedLoan?.planter_name"></p>
                        </div>
                        <button @click="selectedLoan = null; search = ''" class="text-[#52705F] hover:text-rose-600 text-xs">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" @click="generateReport()" :disabled="!selectedLoan"
                class="w-full mt-4 kp-btn kp-btn-primary justify-center text-xs disabled:opacity-40">
                <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Generate Amortization PDF
            </button>
        </div>

        <!-- 2. Loan Details Card -->
        <div class="kp-card p-6 flex flex-col justify-between" 
            x-data="searchableSelect({ loans: {{ json_encode(\App\Models\Loan::orderBy('created_at', 'desc')->get()->toArray()) }}, reportType: 'details' })">
            <div>
                <div class="flex items-start gap-3 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="w-10 h-10 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fas fa-file-invoice" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Loan Full Disclosure</h3>
                        <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-0.5">Print comprehensive facility contract details.</p>
                    </div>
                </div>

                <div class="relative" @click.away="open = false">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[#52705F] text-xs"></i>
                        <input type="text" x-model="search" @click="open = true" @input="open = true"
                            placeholder="Search planter or loan #..."
                            class="kp-input w-full pl-8 py-2 text-xs mb-1">
                        <button x-show="search" @click="search = ''; selectedLoan = null; open = false" 
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#52705F] hover:text-[#172B20] dark:hover:text-[#EAF5ED]" x-cloak>
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>

                    <div x-show="open" x-transition x-cloak
                        class="absolute z-20 w-full bg-white dark:bg-[#13231B] border border-[#CFDCD3] dark:border-[#203A2B] rounded-xl shadow-xl max-h-48 overflow-y-auto mt-1 divide-y divide-[#E3EBE5] dark:divide-[#203A2B]">
                        <template x-for="loan in filteredLoans" :key="loan.id">
                            <div @click="selectLoan(loan)" :class="{'bg-[#EAF5ED] dark:bg-[#1A3324]': selectedLoan?.id === loan.id}"
                                class="px-3.5 py-2 text-xs cursor-pointer hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-[#172B20] dark:text-[#EAF5ED]" x-text="loan.loan_number"></p>
                                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]" x-text="loan.planter_name + ' (' + loan.planter_code + ')'"></p>
                                </div>
                                <span class="kp-badge text-[10px]" :class="getStatusClass(loan.status)" x-text="loan.status"></span>
                            </div>
                        </template>
                        <div x-show="filteredLoans.length === 0" class="px-4 py-3 text-xs text-[#52705F] text-center">No loans found</div>
                    </div>
                </div>

                <div x-show="selectedLoan" class="mt-3 p-3 bg-[#EAF5ED] dark:bg-[#1A3324] rounded-xl border border-[#CFDCD3] dark:border-[#203A2B]" x-cloak>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-[#005B2A] dark:text-[#52C97E]" x-text="selectedLoan?.loan_number"></p>
                            <p class="text-[11px] text-[#172B20] dark:text-[#EAF5ED]" x-text="selectedLoan?.planter_name"></p>
                        </div>
                        <button @click="selectedLoan = null; search = ''" class="text-[#52705F] hover:text-rose-600 text-xs">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" @click="generateReport()" :disabled="!selectedLoan"
                class="w-full mt-4 kp-btn kp-btn-primary justify-center text-xs disabled:opacity-40">
                <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Generate Disclosure PDF
            </button>
        </div>

        <!-- 3. Statement of Account Card -->
        <div class="kp-card p-6 flex flex-col justify-between" 
            x-data="searchableSelect({ loans: {{ json_encode(\App\Models\Loan::whereIn('status', ['active', 'completed'])->orderBy('created_at', 'desc')->get()->toArray()) }}, reportType: 'soa' })">
            <div>
                <div class="flex items-start gap-3 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="w-10 h-10 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fas fa-receipt" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Statement of Account (SOA)</h3>
                        <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-0.5">Print ledger payments and current balance.</p>
                    </div>
                </div>

                <div class="relative" @click.away="open = false">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[#52705F] text-xs"></i>
                        <input type="text" x-model="search" @click="open = true" @input="open = true"
                            placeholder="Search planter or loan #..."
                            class="kp-input w-full pl-8 py-2 text-xs mb-1">
                        <button x-show="search" @click="search = ''; selectedLoan = null; open = false" 
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#52705F] hover:text-[#172B20] dark:hover:text-[#EAF5ED]" x-cloak>
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>

                    <div x-show="open" x-transition x-cloak
                        class="absolute z-20 w-full bg-white dark:bg-[#13231B] border border-[#CFDCD3] dark:border-[#203A2B] rounded-xl shadow-xl max-h-48 overflow-y-auto mt-1 divide-y divide-[#E3EBE5] dark:divide-[#203A2B]">
                        <template x-for="loan in filteredLoans" :key="loan.id">
                            <div @click="selectLoan(loan)" :class="{'bg-[#EAF5ED] dark:bg-[#1A3324]': selectedLoan?.id === loan.id}"
                                class="px-3.5 py-2 text-xs cursor-pointer hover:bg-[#EAF5ED]/50 dark:hover:bg-[#1A3324]/50 transition flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-[#172B20] dark:text-[#EAF5ED]" x-text="loan.loan_number"></p>
                                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]" x-text="loan.planter_name + ' (' + loan.planter_code + ')'"></p>
                                </div>
                                <span class="kp-badge text-[10px]" :class="getStatusClass(loan.status)" x-text="loan.status"></span>
                            </div>
                        </template>
                        <div x-show="filteredLoans.length === 0" class="px-4 py-3 text-xs text-[#52705F] text-center">No loans found</div>
                    </div>
                </div>

                <div x-show="selectedLoan" class="mt-3 p-3 bg-[#EAF5ED] dark:bg-[#1A3324] rounded-xl border border-[#CFDCD3] dark:border-[#203A2B]" x-cloak>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-[#005B2A] dark:text-[#52C97E]" x-text="selectedLoan?.loan_number"></p>
                            <p class="text-[11px] text-[#172B20] dark:text-[#EAF5ED]" x-text="selectedLoan?.planter_name"></p>
                        </div>
                        <button @click="selectedLoan = null; search = ''" class="text-[#52705F] hover:text-rose-600 text-xs">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" @click="generateReport()" :disabled="!selectedLoan"
                class="w-full mt-4 kp-btn kp-btn-primary justify-center text-xs disabled:opacity-40">
                <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Generate Official SOA PDF
            </button>
        </div>

        <!-- 4. Monthly Collection Ledger Card -->
        <div class="kp-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-start gap-3 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="w-10 h-10 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Monthly Loan Collections</h3>
                        <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-0.5">Summary of monthly collections and releases.</p>
                    </div>
                </div>

                <form action="{{ route('loans.monthly-report-pdf') }}" method="GET" data-pdf-preview="Monthly Loan Collections" class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Target Month</label>
                        <input type="month" name="month" value="{{ date('Y-m') }}" class="kp-input w-full py-2 text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Crop Year Filter</label>
                        <select name="crop_year" class="kp-input w-full py-2 text-xs">
                            <option value="">All Crop Years</option>
                            @foreach($cropYears as $cy)
                                <option value="{{ $cy }}">{{ $cy }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full mt-2 kp-btn kp-btn-secondary justify-center text-xs">
                        <i class="fas fa-file-pdf mr-1.5 text-rose-600" aria-hidden="true"></i> Generate Monthly Statement
                    </button>
                </form>
            </div>
        </div>

        <!-- 5. Active Portfolio Summary Card -->
        <div class="kp-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-start gap-3 pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="w-10 h-10 rounded-xl bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fas fa-list-check" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Active Portfolio Summary</h3>
                        <p class="text-xs text-[#52705F] dark:text-[#8AA895] mt-0.5">Ledger summary of all active loans in the cooperative.</p>
                    </div>
                </div>

                <form action="{{ route('loans.active-loans-pdf') }}" method="GET" data-pdf-preview="Active Loan Portfolio" class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Crop Year Filter</label>
                        <select name="crop_year" class="kp-input w-full py-2 text-xs">
                            <option value="">All Crop Years</option>
                            @foreach($cropYears as $cy)
                                <option value="{{ $cy }}">{{ $cy }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full mt-2 kp-btn kp-btn-secondary justify-center text-xs">
                        <i class="fas fa-file-pdf mr-1.5 text-rose-600" aria-hidden="true"></i> Generate Active Summary
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function searchableSelect(config) {
        return {
            loans: config.loans || [],
            selectedLoan: config.selectedLoan || null,
            reportType: config.reportType || '',
            search: '',
            open: false,

            get filteredLoans() {
                if (!this.search) return this.loans;
                const s = this.search.toLowerCase();
                return this.loans.filter(l => 
                    (l.loan_number || '').toLowerCase().includes(s) ||
                    (l.planter_name || '').toLowerCase().includes(s) ||
                    (l.planter_code || '').toLowerCase().includes(s)
                );
            },

            selectLoan(loan) {
                this.selectedLoan = loan;
                this.search = '';
                this.open = false;
            },

            generateReport() {
                if (!this.selectedLoan) return;
                const id = this.selectedLoan.id;
                const urls = {
                    'amortization': '/loans/' + id + '/amortization-pdf',
                    'details': '/loans/' + id + '/details-pdf',
                    'soa': '/loans/' + id + '/soa-pdf',
                };
                const titles = { amortization: 'Amortization Schedule', details: 'Full Loan Disclosure', soa: 'Statement of Account' };
                window.KusugpaPdfPreview.open(urls[this.reportType], titles[this.reportType]);
            },

            getStatusClass(status) {
                const classes = {
                    active: 'kp-badge--forest',
                    pending: 'kp-badge--amber',
                    approved: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                    completed: 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300',
                    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                };
                return classes[status] || 'bg-stone-100 text-stone-700';
            }
        };
    }
</script>
@endpush
@endsection
