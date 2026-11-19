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
                    @if ($canQuickCA)
                        <button type="button" @click="openQuick()" class="kp-btn kp-btn-primary text-xs">
                            <i class="fas fa-hand-holding-dollar mr-1.5" aria-hidden="true"></i> Quick Cash Advance
                        </button>
                    @endif
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
                @if ($canQuickCA)
                    <li><b>Quick Cash Advance</b>: records a cash advance directly (no application or approval step) with 1% interest, deducted in full on the week you pick. It also appears on the Cash Advances page.</li>
                @endif
            </ul>
        </div>

        @if ($canQuickCA)
            <!-- Quick Cash Advance: planter, amount, deduction week, 1% interest, date created -->
            <div x-show="quick.open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="quick.open = false">
                <div class="kp-viewport-modal-layout flex items-center justify-center">
                    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="quick.open = false"></div>
                    <div class="kp-modal kp-viewport-modal-panel relative max-w-xl w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="quick-ca-title">
                        <div class="flex items-start justify-between pb-3 mb-4 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                            <div>
                                <h3 id="quick-ca-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Quick Cash Advance</h3>
                                <p class="text-xs mt-1 text-[#52705F] dark:text-[#8AA895]">Recorded as approved, with 1% interest, and deducted in full on the selected week's Check Voucher.</p>
                            </div>
                            <button type="button" @click="quick.open = false" class="text-[#52705F] hover:text-rose-600" aria-label="Close"><i class="fas fa-times"></i></button>
                        </div>

                        <form @submit.prevent="saveQuick()" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Crop Year <span class="text-rose-600">*</span></label>
                                    <select x-model="quick.crop_year" @change="quick.week_no = ''" class="kp-input w-full" required>
                                        <option value="">Select Crop Year</option>
                                        @foreach ($cropYear as $year)
                                            <option value="{{ $year }}">{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Date Created <span class="text-rose-600">*</span></label>
                                    <input type="date" x-model="quick.date_created" class="kp-input w-full" required>
                                </div>
                            </div>

                            <!-- Planter: searchable by name or code -->
                            <div class="relative" @click.outside="quick.listOpen = false">
                                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Planter <span class="text-rose-600">*</span></label>
                                <input type="text" x-model="quick.search" @focus="quick.listOpen = true" @input="quick.listOpen = true; quick.planter = null"
                                    class="kp-input w-full" placeholder="Search planter name or code..." autocomplete="off">
                                <ul x-show="quick.listOpen && quickMatches.length" x-cloak
                                    class="absolute z-10 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-[#CFDCD3] dark:border-[#203A2B] bg-white dark:bg-[#132A1D] shadow-lg py-1 text-sm">
                                    <template x-for="p in quickMatches" :key="p.planter_code">
                                        <li @click="pickPlanter(p)" class="px-3 py-1.5 cursor-pointer hover:bg-[#EAF5ED] dark:hover:bg-[#1A3324]">
                                            <span class="font-mono text-xs text-[#52705F] dark:text-[#8AA895] mr-2" x-text="p.planter_code"></span>
                                            <span x-text="p.planter_name"></span>
                                        </li>
                                    </template>
                                </ul>
                                <p x-show="quick.search && !quick.planter && !quickMatches.length" x-cloak class="text-[11px] mt-1 text-amber-700 dark:text-amber-300">No active planter matches.</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Advance Amount (₱) <span class="text-rose-600">*</span></label>
                                    <input type="number" step="0.01" min="0.01" x-model="quick.amount" class="kp-input w-full text-right" placeholder="0.00" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Deduction Week <span class="text-rose-600">*</span></label>
                                    <select x-model="quick.week_no" :disabled="!quick.crop_year" class="kp-input w-full disabled:opacity-50" required>
                                        <option value="" x-text="quick.crop_year ? 'Select Week' : 'Select crop year first'"></option>
                                        <template x-for="w in quickWeeks" :key="'q' + w">
                                            <option :value="w" x-text="'Week ' + w"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <!-- 1% interest, worked out automatically -->
                            <div class="rounded-lg bg-[#F7FAF8] dark:bg-[#1A3324] border border-[#E3EBE5] dark:border-[#203A2B] p-3 text-xs space-y-1">
                                <div class="flex justify-between"><span>Advance</span><span class="font-mono" x-text="'₱' + money(quickAmount)"></span></div>
                                <div class="flex justify-between"><span>Interest (1%)</span><span class="font-mono" x-text="'₱' + money(quickInterest)"></span></div>
                                <div class="flex justify-between font-bold text-[#172B20] dark:text-[#EAF5ED] pt-1 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                                    <span x-text="'Deducted on Week ' + (quick.week_no || '—')"></span>
                                    <span class="font-mono" x-text="'₱' + money(quickAmount + quickInterest)"></span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Purpose / Notes</label>
                                <input type="text" x-model="quick.purpose" maxlength="500" class="kp-input w-full" placeholder="Optional">
                            </div>

                            <div class="flex justify-end gap-2 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                                <button type="button" @click="quick.open = false" class="kp-btn kp-btn-secondary text-xs">Cancel</button>
                                <button type="submit" :disabled="!canSaveQuick || quick.saving" class="kp-btn kp-btn-primary text-xs disabled:opacity-40">
                                    <i class="fas mr-1.5" :class="quick.saving ? 'fa-circle-notch fa-spin' : 'fa-check'" aria-hidden="true"></i>
                                    <span x-text="quick.saving ? 'Saving…' : 'Save Cash Advance'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
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

                    // Quick Cash Advance
                    planters: @json($planters ?? []),
                    quick: { open: false, crop_year: '', planter: null, search: '', listOpen: false, amount: '', week_no: '', date_created: today, purpose: '', saving: false },
                    get quickWeeks() {
                        return weeks.filter(w => w.crop_year === this.quick.crop_year).map(w => w.week_no);
                    },
                    get quickMatches() {
                        const term = this.quick.search.trim().toLowerCase();
                        if (!term) return this.planters.slice(0, 50);
                        return this.planters.filter(p => String(p.planter_name).toLowerCase().includes(term) || String(p.planter_code).toLowerCase().includes(term)).slice(0, 50);
                    },
                    get quickAmount() { return Math.round((parseFloat(this.quick.amount) || 0) * 100) / 100; },
                    // 1% flat interest, rounded to centavos like the Cash Advance page
                    get quickInterest() { return Math.round(this.quickAmount * 1) / 100; },
                    get canSaveQuick() {
                        return this.quick.crop_year && this.quick.planter && this.quickAmount > 0 && this.quick.week_no && this.quick.date_created;
                    },
                    money(v) {
                        return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    openQuick() {
                        this.quick = { ...this.quick, open: true, crop_year: this.filters.crop_year || this.quick.crop_year, planter: null, search: '', amount: '', week_no: '', date_created: today, purpose: '', saving: false };
                    },
                    pickPlanter(p) {
                        this.quick.planter = p;
                        this.quick.search = p.planter_name + ' (' + p.planter_code + ')';
                        this.quick.listOpen = false;
                    },
                    async saveQuick() {
                        if (!this.canSaveQuick || this.quick.saving) return;
                        this.quick.saving = true;
                        try {
                            const r = await fetch('{{ route('cash-advances.quick') }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                                body: JSON.stringify({
                                    crop_year: this.quick.crop_year,
                                    planter_code: this.quick.planter.planter_code,
                                    planter_name: this.quick.planter.planter_name,
                                    amount: this.quickAmount,
                                    week_no: this.quick.week_no,
                                    date_created: this.quick.date_created,
                                    purpose: this.quick.purpose,
                                }),
                            });
                            const d = await r.json().catch(() => ({}));
                            if (!r.ok) {
                                const first = d.errors ? Object.values(d.errors)[0][0] : null;
                                throw new Error(first || d.message || 'The cash advance was not saved.');
                            }
                            this.quick.open = false;
                            Swal.fire({ icon: 'success', title: 'Cash Advance Saved', text: d.message, confirmButtonColor: '#005B2A' });
                        } catch (e) {
                            Swal.fire({ icon: 'error', title: 'Not Saved', text: e.message, confirmButtonColor: '#005B2A' });
                        } finally {
                            this.quick.saving = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endsection
