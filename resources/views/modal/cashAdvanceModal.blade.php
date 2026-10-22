<!-- resources/views/modal/cashAdvanceModal.blade.php -->
<div x-data="newCAForm()" x-show="showModal" x-on:open-ca-modal.window="showModal = true"
    x-on:close-ca-modal.window="showModal = false" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="kp-viewport-modal-layout flex items-center justify-center">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>
        <div class="kp-modal kp-viewport-modal-panel relative max-w-3xl w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="new-cash-advance-modal-title" @keydown.escape.window="showModal = false">
            <div class="flex items-center justify-between pb-3 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                        <i class="fas fa-hand-holding-dollar" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 id="new-cash-advance-modal-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">New Cash Advance Application</h3>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Disburse short-term cash assistance to registered planter.</p>
                    </div>
                </div>
                <button type="button" @click="showModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895] p-1.5 rounded-lg">
                    <i class="fas fa-times text-base" aria-hidden="true"></i>
                </button>
            </div>

            <form @submit.prevent="submitCAForm()" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Crop Year <span class="text-rose-600">*</span></label>
                        <select name="crop_year" x-model="cropYear" @change="buildSchedule()" required
                            class="kp-input w-full">
                            @foreach ($cropYears as $cy)
                                <option value="{{ $cy }}">{{ $cy }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Planter <span class="text-rose-600">*</span></label>
                        <select name="planter_code" x-model="selectedPlanter" @change="updatePlanterName()" required
                            class="kp-input w-full">
                            <option value="">Select Planter</option>
                            @foreach ($planters as $p)
                                <option value="{{ $p->planter_code }}" data-name="{{ $p->planter_name }}">
                                    {{ $p->planter_name }} ({{ $p->planter_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Advance Amount (₱) <span class="text-rose-600">*</span></label>
                        <input type="number" name="amount" x-model="amount" @input="if (!manualSplit) autoSplit()" step="any" min="0" required
                            class="kp-input w-full"
                            placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Interest Rate (%) <span class="text-rose-600">*</span></label>
                        <input type="number" name="interest_rate" x-model="interestRate" step="any" min="0" required
                            class="kp-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Term (Weeks) <span class="text-rose-600">*</span></label>
                        <select name="term_weeks" x-model.number="termWeeks" @change="buildSchedule()" required
                            class="kp-input w-full">
                            <template x-for="n in termOptions()" :key="n">
                                <option :value="n" :selected="n === termWeeks" x-text="n + (n === 1 ? ' Week' : ' Weeks')"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mt-1">Weeks are generated based on the selected crop year.</p>
                    </div>
                </div>

                <!-- Weekly Payment Schedule -->
                <div class="rounded-xl p-4 bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h4 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Weekly Payment Schedule</h4>
                        <button type="button" @click="manualSplit = false; autoSplit()" :disabled="!schedule.length"
                            class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs disabled:opacity-50">
                            <i class="fas fa-arrow-right-arrow-left mr-1.5" aria-hidden="true"></i> Auto Split Evenly
                        </button>
                    </div>

                    <p x-show="!schedule.length" class="text-xs py-2 text-amber-700 dark:text-amber-400">
                        <i class="fas fa-triangle-exclamation mr-1" aria-hidden="true"></i>
                        No weeks are set up for this crop year in Crop &amp; Week Management.
                    </p>

                    <div x-show="schedule.length" class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider text-[#52705F] dark:text-[#8AA895] bg-[#EAF5ED]/60 dark:bg-[#1A3324]/60">
                                    <th class="text-left font-semibold px-2 py-2">Week</th>
                                    <th class="text-left font-semibold px-2 py-2">Week Start</th>
                                    <th class="text-left font-semibold px-2 py-2">Week End</th>
                                    <th class="text-left font-semibold px-2 py-2">
                                        Due Date <i class="fas fa-circle-info ml-0.5" title="Defaults to the week's end date; you can change it." aria-hidden="true"></i>
                                    </th>
                                    <th class="text-left font-semibold px-2 py-2">Amount (₱)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, i) in schedule" :key="row.uid">
                                    <tr class="border-b border-[#E3EBE5] dark:border-[#203A2B]">
                                        <td class="px-2 py-1.5 whitespace-nowrap">
                                            {{-- Any week of the crop year, so earlier manual schedules can be entered --}}
                                            <select x-model="row.week_no" @change="changeWeek(row)"
                                                class="kp-input py-1 text-xs font-semibold min-w-[6.5rem]"
                                                :class="duplicateWeeks().includes(row.week_no) ? 'border-rose-500 text-rose-700 dark:text-rose-400' : 'text-[#005B2A] dark:text-[#52C97E]'">
                                                <template x-for="w in cropYearWeeks()" :key="w.week_no">
                                                    <option :value="w.week_no" :selected="w.week_no === row.week_no" x-text="'Week ' + w.week_no"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5 whitespace-nowrap text-[#172B20] dark:text-[#EAF5ED]" x-text="formatDate(row.week_start)"></td>
                                        <td class="px-2 py-1.5 whitespace-nowrap text-[#172B20] dark:text-[#EAF5ED]" x-text="formatDate(row.week_end)"></td>
                                        <td class="px-2 py-1.5">
                                            <input type="date" x-model="row.due_date" class="kp-input w-full min-w-[9rem] py-1 text-xs">
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <input type="number" x-model="row.amount" @input="manualSplit = true" step="any" min="0"
                                                class="kp-input w-full min-w-[7rem] py-1 text-xs text-right kp-num" placeholder="0.00">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <div class="flex items-center justify-between mt-2 px-3 py-2 rounded-lg"
                            :class="isBalanced() ? 'bg-[#EAF5ED] dark:bg-[#1A3324]' : 'bg-rose-50 dark:bg-rose-950/30'">
                            <span class="text-xs font-bold uppercase tracking-wider text-[#172B20] dark:text-[#EAF5ED]">Total Allocated</span>
                            <div class="flex items-center gap-6">
                                <span class="text-sm font-bold kp-num text-[#172B20] dark:text-[#EAF5ED]">₱ <span x-text="formatNumber(totalAllocated())"></span></span>
                                <span class="text-xs font-bold kp-num w-14 text-right"
                                    :class="isBalanced() ? 'text-[#005B2A] dark:text-[#52C97E]' : 'text-rose-700 dark:text-rose-400'"
                                    x-text="allocatedPercent()"></span>
                            </div>
                        </div>

                        <p x-show="duplicateWeeks().length" class="text-xs mt-2 text-rose-700 dark:text-rose-400">
                            <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i>
                            Week <span x-text="duplicateWeeks().join(', ')"></span> is listed more than once. Each week can only be used once.
                        </p>
                        <p x-show="isBalanced() && (parseFloat(amount) || 0) > 0" class="text-xs mt-2 text-[#005B2A] dark:text-[#52C97E]">
                            <i class="fas fa-circle-check mr-1" aria-hidden="true"></i> Weekly payments total is equal to the advance amount.
                        </p>
                        <p x-show="!isBalanced()" class="text-xs mt-2 text-rose-700 dark:text-rose-400">
                            <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i>
                            Weekly payments total is <span x-text="totalAllocated() < (parseFloat(amount) || 0) ? 'short' : 'over'"></span>
                            by ₱<span x-text="formatNumber(Math.abs((parseFloat(amount) || 0) - totalAllocated()))"></span>. It must equal the advance amount before you can submit.
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Purpose Preset & Notes</label>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <button type="button" @click="purpose = 'Emergency cash assistance'"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 hover:opacity-80 transition">Emergency</button>
                        <button type="button" @click="purpose = 'Personal financial needs'"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 hover:opacity-80 transition">Personal</button>
                        <button type="button" @click="purpose = 'Medical expenses'"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-[#EAF5ED] text-[#005B2A] dark:bg-[#1A3324] dark:text-[#52C97E] hover:opacity-80 transition">Medical</button>
                        <button type="button" @click="purpose = 'Education expenses'"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 hover:opacity-80 transition">Education</button>
                        <button type="button" @click="purpose = ''"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 hover:opacity-80 transition">Clear</button>
                    </div>
                    <textarea name="purpose" x-model="purpose" rows="2" maxlength="500"
                        class="kp-input w-full"
                        placeholder="Enter statement of purpose or select from presets above..."></textarea>
                    <p class="text-right text-[11px] text-[#52705F] dark:text-[#8AA895] mt-0.5"><span x-text="purpose.length"></span>/500</p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <button type="button" @click="showModal = false; resetForm()"
                        class="kp-btn kp-btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting || !canSubmit()" :title="canSubmit() ? '' : submitBlockReason()"
                        class="kp-btn kp-btn-primary text-xs disabled:opacity-50">
                        <span x-show="!submitting"><i class="fas fa-check mr-1.5" aria-hidden="true"></i> Submit Advance</span>
                        <span x-show="submitting"><i class="fas fa-circle-notch fa-spin mr-1.5"></i> Submitting...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function newCAForm() {
        return {
            showModal: false,
            // Weeks per crop year from Crop & Week Management
            allWeeks: @json($weeks),
            cropYears: @json($cropYears->map(fn ($cy) => (string) $cy)->values()),
            cropYear: '',
            selectedPlanter: '',
            planterName: '',
            amount: '',
            interestRate: {{ $settings['default_interest'] ?? 3 }},
            termWeeks: 4,
            schedule: [],
            rowUid: 0,
            manualSplit: false,
            purpose: '',
            submitting: false,

            init() {
                this.cropYear = this.defaultCropYear();
                this.buildSchedule();
            },

            today() {
                const now = new Date();
                return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
            },
            // Crop year whose weeks cover today, else the latest crop year that has weeks
            defaultCropYear() {
                const today = this.today();
                const current = this.allWeeks.find(w => w.week_start <= today && w.week_end >= today);
                if (current) return current.crop_year;
                const withWeeks = this.cropYears.filter(cy => this.allWeeks.some(w => w.crop_year === cy));
                return withWeeks[withWeeks.length - 1] || this.cropYears[0] || '';
            },
            toCents(value) {
                return Math.round((parseFloat(value) || 0) * 100) / 100;
            },

            updatePlanterName() {
                const select = document.querySelector('select[name="planter_code"]');
                const option = select?.options[select.selectedIndex];
                this.planterName = option?.getAttribute('data-name') || '';
            },

            cropYearWeeks() {
                return this.allWeeks.filter(w => w.crop_year === this.cropYear);
            },
            termOptions() {
                const max = this.cropYearWeeks().length || 12;
                return Array.from({ length: max }, (_, i) => i + 1);
            },
            // Suggested weeks start at the current week (or week 1 once the crop year is over);
            // each row's week can then be changed to any week of the crop year
            buildSchedule() {
                const weeks = this.cropYearWeeks();
                if (weeks.length && this.termWeeks > weeks.length) this.termWeeks = weeks.length;
                if (!this.termWeeks) this.termWeeks = Math.min(4, weeks.length) || 1;

                const today = this.today();
                const current = Math.max(0, weeks.findIndex(w => w.week_end && w.week_end >= today));
                const start = Math.max(0, Math.min(current, weeks.length - this.termWeeks));

                this.schedule = weeks.slice(start, start + this.termWeeks).map(w => ({
                    uid: ++this.rowUid,
                    week_no: w.week_no,
                    week_start: w.week_start,
                    week_end: w.week_end,
                    due_date: w.week_end || '',
                    amount: '',
                }));
                this.manualSplit = false;
                this.autoSplit();
            },
            // A different week brings its own dates; the due date resets to that week's end
            changeWeek(row) {
                const w = this.cropYearWeeks().find(x => x.week_no === row.week_no);
                row.week_start = w?.week_start || '';
                row.week_end = w?.week_end || '';
                row.due_date = w?.week_end || '';
            },
            duplicateWeeks() {
                const seen = {};
                this.schedule.forEach(r => seen[r.week_no] = (seen[r.week_no] || 0) + 1);
                return Object.keys(seen).filter(w => seen[w] > 1);
            },
            // Split the advance evenly in centavos; the last week takes the remainder
            autoSplit() {
                const n = this.schedule.length;
                if (!n) return;
                const cents = Math.round((parseFloat(this.amount) || 0) * 100);
                const each = Math.floor(cents / n);
                this.schedule.forEach((row, i) => {
                    const c = i === n - 1 ? cents - each * (n - 1) : each;
                    row.amount = cents ? (c / 100).toFixed(2) : '';
                });
            },
            totalAllocated() {
                return this.schedule.reduce((t, r) => t + this.toCents(r.amount), 0);
            },
            isBalanced() {
                return Math.abs(this.totalAllocated() - (parseFloat(this.amount) || 0)) < 0.005;
            },
            allocatedPercent() {
                const amount = parseFloat(this.amount) || 0;
                if (!amount) return '0%';
                return parseFloat((this.totalAllocated() / amount * 100).toFixed(2)) + '%';
            },

            formatNumber(num) {
                return parseFloat(num || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            },
            formatDate(ymd) {
                if (!ymd) return '—';
                const [y, m, d] = ymd.split('-').map(Number);
                return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            },

            // Submitting needs distinct weeks whose payments add up to the advance amount
            canSubmit() {
                return this.schedule.length > 0 && (parseFloat(this.amount) || 0) > 0 && !this.duplicateWeeks().length && this.isBalanced();
            },
            submitBlockReason() {
                if (!this.schedule.length) return 'No weeks are set up for this crop year.';
                if (!((parseFloat(this.amount) || 0) > 0)) return 'Enter the advance amount.';
                if (this.duplicateWeeks().length) return 'Week ' + this.duplicateWeeks().join(', ') + ' is listed more than once.';
                return 'Weekly payments total must equal the advance amount.';
            },

            async submitCAForm() {
                if (!this.canSubmit()) {
                    Swal.fire({ icon: 'warning', title: 'Cannot submit yet', text: this.submitBlockReason() });
                    return;
                }
                this.submitting = true;
                try {
                    const response = await fetch('{{ route('cash-advances.store') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            planter_code: this.selectedPlanter,
                            planter_name: this.planterName,
                            amount: this.toCents(this.amount),
                            interest_rate: this.interestRate,
                            term_weeks: this.termWeeks,
                            crop_year: this.cropYear,
                            purpose: this.purpose,
                            schedule: this.schedule.map(r => ({
                                week_no: r.week_no,
                                due_date: r.due_date || null,
                                amount: this.toCents(r.amount),
                            })),
                        })
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Failed to create cash advance');

                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Cash advance application submitted successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    this.showModal = false;
                    this.resetForm();
                    window.location.reload();
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
                this.cropYear = this.defaultCropYear();
                this.selectedPlanter = '';
                this.planterName = '';
                this.amount = '';
                this.interestRate = {{ $settings['default_interest'] ?? 3 }};
                this.termWeeks = 4;
                this.purpose = '';
                this.buildSchedule();
            }
        };
    }
</script>
