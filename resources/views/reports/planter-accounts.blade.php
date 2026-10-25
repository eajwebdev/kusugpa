<!-- resources/views/reports/planter-accounts.blade.php -->
@extends('layouts.main')

@section('title', 'Planter Accnt. Applied')

@section('content')
    <div x-data="planterAccounts()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Reports</p>
                    <h1 class="kp-page-title mt-1">Planter Accnt. Applied</h1>
                    <p class="kp-page-sub">Loan, cash advance, others and fuel payments taken off each planter's Check Voucher: a ledger for check and balance.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="resetFilters()" class="kp-btn kp-btn-secondary text-xs">
                        <i class="fas fa-undo mr-1.5" aria-hidden="true"></i> Reset Filters
                    </button>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Filter Card -->
        <div class="kp-card p-6">
            <div class="flex items-center gap-2.5 pb-4 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                    <i class="fas fa-book" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Ledger Criteria</h2>
                    <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Amounts are the same as the planters' vouchers for the selected weeks.</p>
                </div>
            </div>

            <form @submit.prevent="preview()" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-calendar-alt text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Crop Year <span class="text-rose-600">*</span>
                        </label>
                        <select x-model="filters.crop_year" @change="filters.week_from = ''; filters.week_to = ''" class="kp-input w-full">
                            <option value="">Select Crop Year</option>
                            @foreach ($cropYear as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-play-circle text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Week From <span class="text-rose-600">*</span>
                        </label>
                        <select x-model="filters.week_from" :disabled="!filters.crop_year" class="kp-input w-full disabled:opacity-50">
                            <option value="" x-text="filters.crop_year ? 'Select Week' : 'Select crop year first'"></option>
                            <template x-for="w in cropWeeks" :key="'f' + w">
                                <option :value="w" x-text="'Week ' + w"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-stop-circle text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Week To <span class="text-rose-600">*</span>
                        </label>
                        <select x-model="filters.week_to" :disabled="!filters.crop_year" class="kp-input w-full disabled:opacity-50">
                            <option value="" x-text="filters.crop_year ? 'Select Week' : 'Select crop year first'"></option>
                            <template x-for="w in cropWeeks" :key="'t' + w">
                                <option :value="w" x-text="'Week ' + w"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-calendar-day text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Date Issued
                        </label>
                        <input type="date" x-model="dateIssued" class="kp-input w-full">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="text-xs text-[#52705F] dark:text-[#8AA895] flex items-center gap-1.5">
                        <i class="fas fa-info-circle text-[#087A3D]" aria-hidden="true"></i>
                        <span>Select crop year and week range, then preview to print or download.</span>
                    </div>
                    <button type="submit" class="kp-btn kp-btn-primary shadow-sm" :disabled="!canPreview">
                        <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Preview Ledger
                    </button>
                </div>
            </form>
        </div>

        <div class="kp-card p-6 text-xs text-[#52705F] dark:text-[#8AA895] leading-relaxed">
            <p class="font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider mb-2">On the ledger</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>One line per planter with an account applied, sorted by name: P-No., Planter Name, Loan Payment, Cash Advance, Others, Fuel A/C and Total.</li>
                <li>Others and Fuel A/C come from Fuel &amp; Others Deduction; Loan and Cash Advance from their schedules, exactly as on the Check Voucher.</li>
                <li>The last row of the form totals every column.</li>
            </ul>
        </div>
    </div>

    @push('scripts')
        <script>
            function planterAccounts() {
                const weeks = @json($weeks);
                const now = new Date();
                const today = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
                return {
                    filters: { crop_year: '', week_from: '', week_to: '' },
                    dateIssued: today,
                    // Weeks of the selected crop year only
                    get cropWeeks() {
                        return weeks.filter(w => w.crop_year === this.filters.crop_year).map(w => w.week_no);
                    },
                    get canPreview() {
                        return this.filters.crop_year && this.filters.week_from && this.filters.week_to;
                    },
                    preview() {
                        if (!this.canPreview) return;
                        const params = new URLSearchParams(this.filters);
                        if (this.dateIssued) params.append('date_issued', this.dateIssued);
                        window.KusugpaPdfPreview.open('{{ route('planter-accounts-report.pdf') }}?' + params.toString(), 'Planter Accnt. Applied');
                    },
                    resetFilters() {
                        this.filters = { crop_year: '', week_from: '', week_to: '' };
                        this.dateIssued = today;
                    },
                };
            }
        </script>
    @endpush
@endsection
