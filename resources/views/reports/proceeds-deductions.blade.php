<!-- resources/views/reports/proceeds-deductions.blade.php -->
@extends('layouts.main')

@section('title', 'Proceeds & Deductions')

@section('content')
    <div x-data="proceedsDeductions()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Reports</p>
                    <h1 class="kp-page-title mt-1">Proceeds &amp; Deductions</h1>
                    <p class="kp-page-sub">Every planter's proceeds, mill deductions, accounts applied and net paid, for the week and to date.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="resetFilters()" class="kp-btn kp-btn-secondary text-xs">
                        <i class="fas fa-undo mr-1.5" aria-hidden="true"></i> Reset Filters
                    </button>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <div class="kp-card p-6">
            <div class="flex items-center gap-2.5 pb-4 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                    <i class="fas fa-scale-balanced" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Report Criteria</h2>
                    <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Pick a crop year and week. Each planter shows that week and the total to date (week 1 through that week).</p>
                </div>
            </div>

            <form @submit.prevent="preview()" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-calendar-alt text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Crop Year <span class="text-rose-600">*</span>
                        </label>
                        <select x-model="filters.crop_year" @change="filters.week_no = ''" class="kp-input w-full">
                            <option value="">Select Crop Year</option>
                            @foreach ($cropYear as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-calendar-week text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Week No. <span class="text-rose-600">*</span>
                        </label>
                        <select x-model="filters.week_no" :disabled="!filters.crop_year" class="kp-input w-full disabled:opacity-50">
                            <option value="" x-text="filters.crop_year ? 'Select Week' : 'Select crop year first'"></option>
                            <template x-for="w in cropWeeks" :key="w">
                                <option :value="w" x-text="'Week ' + w"></option>
                            </template>
                        </select>
                        <p class="text-[11px] mt-1 text-[#52705F] dark:text-[#8AA895]" x-show="filters.week_no"
                            x-text="'To date: week ' + (cropWeeks[0] || 1) + ' to week ' + filters.week_no"></p>
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
                        <span>Printed on legal paper, landscape.</span>
                    </div>
                    <button type="submit" class="kp-btn kp-btn-primary shadow-sm" :disabled="!canPreview">
                        <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Preview Report
                    </button>
                </div>
            </form>
        </div>

        <div class="kp-card p-6 text-xs text-[#52705F] dark:text-[#8AA895] leading-relaxed">
            <p class="font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider mb-2">On the report</p>
            <ul class="list-disc pl-5 space-y-1">
                <li><b>Proceeds:</b> Quedan, Molasses, Trucking Allowance and Gross Proceeds.</li>
                <li><b>Mill deductions:</b> liens, service charge, insurance and tax; fuel issuance; Net Proceeds.</li>
                <li><b>Accounts applied:</b> Loan Payment, Cash Advance, Others and Fuel A/C; then Net Paid, the check amount.</li>
                <li>Each planter has a <b>This Week</b> line and a <b>To Date</b> line. To date adds up each week's Check Voucher, with totals at the end.</li>
            </ul>
        </div>
    </div>

    @push('scripts')
        <script>
            function proceedsDeductions() {
                const weeks = @json($weeks);
                const now = new Date();
                const today = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
                return {
                    filters: { crop_year: '', week_no: '' },
                    dateIssued: today,
                    get cropWeeks() {
                        return weeks.filter(w => w.crop_year === this.filters.crop_year).map(w => w.week_no);
                    },
                    get canPreview() {
                        return this.filters.crop_year && this.filters.week_no;
                    },
                    preview() {
                        if (!this.canPreview) return;
                        const params = new URLSearchParams(this.filters);
                        if (this.dateIssued) params.append('date_issued', this.dateIssued);
                        window.KusugpaPdfPreview.open('{{ route('proceeds-deductions-report.pdf') }}?' + params.toString(), 'Proceeds & Deductions');
                    },
                    resetFilters() {
                        this.filters = { crop_year: '', week_no: '' };
                        this.dateIssued = today;
                    },
                };
            }
        </script>
    @endpush
@endsection
