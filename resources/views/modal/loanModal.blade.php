<!-- resources/views/modal/loanModal.blade.php -->
<div x-show="showNewLoanModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-data="newLoanForm()" x-cloak>
    <div class="kp-viewport-modal-layout flex items-center justify-center">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showNewLoanModal = false"></div>
        <div class="kp-modal kp-viewport-modal-panel relative max-w-2xl w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="new-loan-modal-title" @keydown.escape.window="showNewLoanModal = false">
            <div class="flex items-center justify-between pb-3 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                        <i class="fas fa-hand-holding-dollar" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 id="new-loan-modal-title" class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">New Loan Application</h3>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Initiate credit application with document attachments.</p>
                    </div>
                </div>
                <button type="button" @click="showNewLoanModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895] p-1.5 rounded-lg">
                    <i class="fas fa-times text-base" aria-hidden="true"></i>
                </button>
            </div>

            <form id="newLoanForm" @submit.prevent="submitLoanForm()" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Planter Selection -->
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

                    <!-- Loan Type -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Loan Classification <span class="text-rose-600">*</span></label>
                        <select name="loan_type_id" x-model="selectedLoanType" @change="updateLoanDetails()" required
                            class="kp-input w-full">
                            <option value="">Select Type</option>
                            @foreach ($loanTypes as $type)
                                <option value="{{ $type->id }}" data-rate="{{ $type->default_interest_rate }}"
                                    data-term="{{ $type->default_term_months }}" data-max="{{ $type->max_amount }}">
                                    {{ $type->name }} ({{ $type->default_interest_rate }}% - {{ $type->default_term_months }} mos)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Principal Amount -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Principal Amount (₱) <span class="text-rose-600">*</span></label>
                        <input type="number" name="principal_amount" x-model="principalAmount" step="0.01" required
                            class="kp-input w-full"
                            placeholder="0.00">
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mt-1" x-show="maxAmount > 0">
                            Policy Ceiling: ₱<span x-text="formatNumber(maxAmount)"></span>
                        </p>
                    </div>

                    <!-- Interest Rate -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Interest Rate (%) <span class="text-rose-600">*</span></label>
                        <input type="number" name="interest_rate" x-model="interestRate" step="0.01" required
                            class="kp-input w-full">
                    </div>

                    <!-- Term -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Term (Months) <span class="text-rose-600">*</span></label>
                        <input type="number" name="term_months" x-model="termMonths" required
                            class="kp-input w-full">
                    </div>

                    <!-- Crop Year -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Crop Year <span class="text-rose-600">*</span></label>
                        <select name="crop_year" required
                            class="kp-input w-full">
                            @foreach ($cropYears as $cy)
                                <option value="{{ $cy }}">{{ $cy }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Planter Name (Auto-filled) -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Selected Planter Name</label>
                    <input type="text" name="planter_name" x-model="planterName" readonly
                        class="kp-input w-full bg-[#F7FAF8] dark:bg-[#13231B] text-[#52705F] cursor-not-allowed">
                </div>

                <!-- Purpose with Default Options -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Purpose Preset & Notes</label>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <button type="button"
                            @click="purpose = 'Crop production expenses (fertilizers, pesticides, labor)'"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-[#EAF5ED] text-[#005B2A] dark:bg-[#1A3324] dark:text-[#52C97E] hover:opacity-80 transition">
                            Crop Production
                        </button>
                        <button type="button" @click="purpose = 'Emergency financial assistance'"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 hover:opacity-80 transition">
                            Emergency
                        </button>
                        <button type="button" @click="purpose = 'Purchase of farming equipment and machinery'"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 hover:opacity-80 transition">
                            Equipment
                        </button>
                        <button type="button" @click="purpose = 'Farm land improvement and development'"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 hover:opacity-80 transition">
                            Farm Improvement
                        </button>
                        <button type="button" @click="purpose = ''"
                            class="px-2.5 py-1 text-[11px] rounded-lg font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 hover:opacity-80 transition">
                            Clear
                        </button>
                    </div>
                    <textarea name="purpose" x-model="purpose" rows="2"
                        class="kp-input w-full"
                        placeholder="Enter statement of loan purpose..."></textarea>
                </div>

                <!-- Estimated Summary -->
                <div class="rounded-xl p-4 bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B]"
                    x-show="principalAmount > 0 && interestRate > 0 && termMonths > 0">
                    <h4 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider mb-2">Estimated Repayment Summary</h4>
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

                <!-- === DOCUMENT ATTACHMENT SECTION === -->
                <div class="border-t border-[#E3EBE5] dark:border-[#203A2B] pt-4">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-paperclip text-[#087A3D] dark:text-[#52C97E]"></i>
                        <h4 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Proof of Identity & Signed Agreement</h4>
                    </div>

                    <div class="bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 rounded-xl p-3 mb-3 text-xs text-amber-800 dark:text-amber-300">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span>Upload signed credit note, promissory agreement, or valid government ID (PDF, JPG, PNG, DOC - Max 10MB).</span>
                    </div>

                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Document Type *</label>
                                <select id="docType" class="kp-input w-full py-1.5 text-xs">
                                    <option value="proof">Proof of Identity</option>
                                    <option value="agreement">Signed Agreement</option>
                                    <option value="id">Valid Government ID</option>
                                    <option value="other">Other Supporting Document</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Description</label>
                                <input type="text" id="docDesc" placeholder="e.g. Barangay Clearance, SSS ID"
                                    class="kp-input w-full py-1.5 text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Select File *</label>
                            <input type="file" id="loanDocument" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                required @change="previewFile($event)"
                                class="kp-input w-full py-1.5 text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#EAF5ED] file:text-[#005B2A] dark:file:bg-[#1A3324] dark:file:text-[#52C97E]">
                        </div>

                        <!-- File Preview -->
                        <div id="filePreview" class="hidden p-3 bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B] rounded-xl">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm">
                                        <i class="fas fa-file"></i>
                                    </div>
                                    <div>
                                        <p id="previewFileName" class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED]"></p>
                                        <p id="previewFileSize" class="text-[11px] text-[#52705F] dark:text-[#8AA895]"></p>
                                    </div>
                                </div>
                                <button type="button" @click="removeFile()" class="text-rose-500 hover:text-rose-700 p-1">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <button type="button" @click="showNewLoanModal = false; resetForm()"
                        class="kp-btn kp-btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting"
                        class="kp-btn kp-btn-primary text-xs disabled:opacity-50">
                        <span x-show="!submitting"><i class="fas fa-check mr-1.5" aria-hidden="true"></i> Submit Application</span>
                        <span x-show="submitting"><i class="fas fa-circle-notch fa-spin mr-1.5"></i> Submitting...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
