<!-- resources/views/modal/loanModal.blade.php -->
<div x-show="showNewLoanModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-data="newLoanForm()" x-cloak>
    <div class="kp-viewport-modal-layout flex items-center justify-center">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showNewLoanModal = false"></div>
        <div class="kp-modal kp-viewport-modal-panel relative max-w-3xl w-full p-4 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="new-loan-modal-title" @keydown.escape.window="showNewLoanModal = false">
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
                        <input type="number" name="principal_amount" x-model="principalAmount" @input="if (!manualSplit) autoSplit()" step="any" min="0" required
                            class="kp-input w-full"
                            placeholder="0.00">
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mt-1" x-show="maxAmount > 0">
                            Policy Ceiling: ₱<span x-text="formatNumber(maxAmount)"></span>
                        </p>
                    </div>

                    <!-- Interest Rate -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Interest Rate (%) <span class="text-rose-600">*</span></label>
                        {{-- Opens once a principal is entered; the interest applies right away --}}
                        <input type="number" name="interest_rate" x-model="interestRate" @input="if (!manualSplit) autoSplit()" step="any" min="0" required
                            :disabled="!hasPrincipal()" :title="hasPrincipal() ? '' : 'Enter the principal amount first'"
                            class="kp-input w-full disabled:opacity-50 disabled:cursor-not-allowed">
                        <p class="text-[11px] mt-1" :class="hasPrincipal() ? 'text-[#005B2A] dark:text-[#52C97E]' : 'text-[#52705F] dark:text-[#8AA895]'"
                            x-text="hasPrincipal() ? 'Interest ₱' + formatNumber(calculateTotalInterest()) : 'Enter the principal first'"></p>
                    </div>

                    <!-- Term -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">Term (Months) <span class="text-rose-600">*</span></label>
                        <select name="term_months" x-model.number="termMonths" @change="buildSchedule()" required class="kp-input w-full">
                            <template x-for="n in maxTerm" :key="n">
                                <option :value="n" :selected="n === termMonths" x-text="n + (n === 1 ? ' Month' : ' Months')"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mt-1">Months start the month after today; each can be changed below.</p>
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

                <!-- Approval route, by principal -->
                <div x-show="hasPrincipal()" x-cloak class="flex items-start gap-2 px-3 py-2 rounded-lg text-xs"
                    :class="needsManager() ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300' : 'bg-[#EAF5ED] text-[#005B2A] dark:bg-[#1A3324] dark:text-[#52C97E]'">
                    <i class="fas mt-0.5" :class="needsManager() ? 'fa-user-tie' : 'fa-circle-check'" aria-hidden="true"></i>
                    <span x-text="needsManager()
                        ? 'This loan is ' + thresholdLabel + ' or more. It will wait as Pending until a Manager or Administrator approves it.'
                        : 'Below ' + thresholdLabel + ': no approval needed. The loan is approved as soon as you submit it.'"></span>
                </div>

                <!-- Monthly Payment Schedule -->
                <div class="rounded-xl p-4 bg-[#F7FAF8] dark:bg-[#13231B] border border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                        <h4 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Monthly Payment Schedule</h4>
                        <button type="button" @click="manualSplit = false; autoSplit()" :disabled="!schedule.length"
                            class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs disabled:opacity-50">
                            <i class="fas fa-arrow-right-arrow-left mr-1.5" aria-hidden="true"></i> Auto Split Evenly
                        </button>
                    </div>
                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mb-3">
                        Principal ₱<span x-text="formatNumber(principalAmount)"></span> + Interest ₱<span x-text="formatNumber(calculateTotalInterest())"></span>
                        = <span class="font-bold text-[#172B20] dark:text-[#EAF5ED]">Total Repayable ₱<span x-text="formatNumber(totalRepayable())"></span></span>
                    </p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider text-[#52705F] dark:text-[#8AA895] bg-[#EAF5ED]/60 dark:bg-[#1A3324]/60">
                                    <th class="text-left font-semibold px-2 py-2">Month</th>
                                    <th class="text-left font-semibold px-2 py-2">Due Date</th>
                                    <th class="text-left font-semibold px-2 py-2">Amount (₱)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in schedule" :key="row.uid">
                                    <tr class="border-b border-[#E3EBE5] dark:border-[#203A2B]">
                                        <td class="px-2 py-1.5">
                                            {{-- Any month, so earlier manual schedules can be entered --}}
                                            <select x-model="row.month" @change="row.due_date = monthEnd(row.month)"
                                                class="kp-input py-1 text-xs font-semibold min-w-[8rem]"
                                                :class="duplicateMonths().includes(row.month) ? 'border-rose-500 text-rose-700 dark:text-rose-400' : 'text-[#005B2A] dark:text-[#52C97E]'">
                                                <template x-for="m in monthOptions" :key="m.value">
                                                    <option :value="m.value" :selected="m.value === row.month" x-text="m.label"></option>
                                                </template>
                                            </select>
                                        </td>
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
                    </div>

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
                    <p x-show="duplicateMonths().length" class="text-xs mt-2 text-rose-700 dark:text-rose-400">
                        <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i>
                        <span x-text="duplicateMonths().map(monthLabel).join(', ')"></span> is listed more than once. Each month can only be used once.
                    </p>
                    <p x-show="isBalanced() && totalRepayable() > 0" class="text-xs mt-2 text-[#005B2A] dark:text-[#52C97E]">
                        <i class="fas fa-circle-check mr-1" aria-hidden="true"></i> Monthly payments total is equal to the total repayable.
                    </p>
                    <p x-show="!isBalanced()" class="text-xs mt-2 text-rose-700 dark:text-rose-400">
                        <i class="fas fa-circle-xmark mr-1" aria-hidden="true"></i>
                        Monthly payments total is <span x-text="totalAllocated() < totalRepayable() ? 'short' : 'over'"></span>
                        by ₱<span x-text="formatNumber(Math.abs(totalRepayable() - totalAllocated()))"></span>. It must equal the total repayable before you can submit.
                    </p>
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
                    <button type="submit" :disabled="submitting || !canSubmit()" :title="canSubmit() ? '' : submitBlockReason()"
                        class="kp-btn kp-btn-primary text-xs disabled:opacity-50">
                        <span x-show="!submitting"><i class="fas fa-check mr-1.5" aria-hidden="true"></i> Submit Application</span>
                        <span x-show="submitting"><i class="fas fa-circle-notch fa-spin mr-1.5"></i> Submitting...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
