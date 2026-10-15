<!-- resources/views/modal/cashAdvanceModal.blade.php -->
<div x-data="newCAForm()" x-show="showModal" x-on:open-ca-modal.window="showModal = true"
    x-on:close-ca-modal.window="showModal = false" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="kp-viewport-modal-layout flex items-center justify-center">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>
        <div class="kp-modal kp-viewport-modal-panel relative max-w-2xl w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="new-cash-advance-modal-title" @keydown.escape.window="showModal = false">
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
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Advance Amount (₱) <span class="text-rose-600">*</span></label>
                        <input type="number" name="amount" x-model="amount" step="0.01" required
                            class="kp-input w-full"
                            placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Interest Rate (%) <span class="text-rose-600">*</span></label>
                        <input type="number" name="interest_rate" x-model="interestRate" step="0.01" required
                            class="kp-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Term (Months) <span class="text-rose-600">*</span></label>
                        <input type="number" name="term_months" x-model="termMonths" required
                            class="kp-input w-full">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Crop Year <span class="text-rose-600">*</span></label>
                        <select name="crop_year" required
                            class="kp-input w-full">
                            @foreach ($cropYears as $cy)
                                <option value="{{ $cy }}">{{ $cy }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Selected Planter Name</label>
                    <input type="text" name="planter_name" x-model="planterName" readonly
                        class="kp-input w-full bg-[#F7FAF8] dark:bg-[#13231B] text-[#52705F] cursor-not-allowed">
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
                    <textarea name="purpose" x-model="purpose" rows="2"
                        class="kp-input w-full"
                        placeholder="Enter statement of purpose or select from presets above..."></textarea>
                </div>

                <div class="rounded-xl p-4 bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B]" x-show="amount > 0 && interestRate > 0 && termMonths > 0">
                    <h4 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider mb-2">Estimated Calculation Summary</h4>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div>
                            <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Monthly Due</p>
                            <p class="font-bold text-[#172B20] dark:text-[#EAF5ED] kp-num mt-0.5">₱<span x-text="formatNumber(calculateMonthly())"></span></p>
                        </div>
                        <div>
                            <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Total Interest</p>
                            <p class="font-bold text-amber-600 dark:text-amber-400 kp-num mt-0.5">₱<span x-text="formatNumber(calculateTotalInterest())"></span></p>
                        </div>
                        <div>
                            <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Repayable Sum</p>
                            <p class="font-bold text-[#005B2A] dark:text-[#52C97E] kp-num mt-0.5">₱<span x-text="formatNumber(calculateTotal())"></span></p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <button type="button" @click="showModal = false; resetForm()"
                        class="kp-btn kp-btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting"
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
            selectedPlanter: '',
            planterName: '',
            amount: '',
            interestRate: {{ $settings['default_interest'] ?? 3 }},
            termMonths: 3,
            purpose: '',
            submitting: false,

            updatePlanterName() {
                const select = document.querySelector('select[name="planter_code"]');
                const option = select?.options[select.selectedIndex];
                this.planterName = option?.getAttribute('data-name') || '';
            },
            calculateMonthly() {
                const p = parseFloat(this.amount) || 0;
                const r = (parseFloat(this.interestRate) || 0) / 100 / 12;
                const n = parseInt(this.termMonths) || 1;
                if (p === 0) return 0;
                if (r === 0) return p / n;
                return p * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
            },
            calculateTotal() {
                return this.calculateMonthly() * (parseInt(this.termMonths) || 1);
            },
            calculateTotalInterest() {
                return this.calculateTotal() - (parseFloat(this.amount) || 0);
            },
            formatNumber(num) {
                return parseFloat(num || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            },

            async submitCAForm() {
                this.submitting = true;
                try {
                    const formData = new FormData();
                    formData.append('planter_code', document.querySelector('select[name="planter_code"]').value);
                    formData.append('planter_name', this.planterName);
                    formData.append('amount', this.amount);
                    formData.append('interest_rate', this.interestRate);
                    formData.append('term_months', this.termMonths);
                    formData.append('crop_year', document.querySelector('select[name="crop_year"]').value);
                    formData.append('purpose', this.purpose);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                    const response = await fetch('{{ route('cash-advances.store') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: formData
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Failed to create cash advance');

                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Cash advance created successfully.',
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
                this.selectedPlanter = '';
                this.planterName = '';
                this.amount = '';
                this.interestRate = {{ $settings['default_interest'] ?? 3 }};
                this.termMonths = 3;
                this.purpose = '';
            }
        };
    }
</script>
