<!-- resources/views/reports/hauler-signature-sheet.blade.php -->
@extends('layouts.main')

@section('title', 'Hauler TLS Signature')

@section('content')
    <div x-data="haulerSignatureSheet()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Reports</p>
                    <h1 class="kp-page-title mt-1">Hauler TLS Signature</h1>
                    <p class="kp-page-sub">The weekly sheet haulers sign when they receive their hauling check, one sheet per hauler location.</p>
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
        <div class="kp-card p-6 overflow-visible">
            <div class="flex items-center gap-2.5 pb-4 mb-5 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                    <i class="fas fa-signature" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Sheet Criteria</h2>
                    <p class="text-xs text-[#52705F] dark:text-[#8AA895]">Amounts are the same as the Hauler TLS LIQ entries for the selected weeks.</p>
                </div>
            </div>

            <form @submit.prevent="preview()" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Crop Year -->
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

                    <!-- Week From -->
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

                    <!-- Week To -->
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

                    <!-- Hauler Location Selection -->
                    <div class="relative">
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-map-marker-alt text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Hauler Location
                        </label>
                        <div class="relative">
                            <button @click="locationOpen = !locationOpen" type="button"
                                class="kp-input w-full text-left flex items-center justify-between">
                                <span class="truncate font-medium" x-text="locationSelectedText"></span>
                                <i class="fas fa-chevron-down text-[#52705F] text-xs transition-transform duration-200"
                                    :class="{ 'rotate-180': locationOpen }"></i>
                            </button>

                            <div x-show="locationOpen" @click.away="locationOpen = false" x-transition x-cloak
                                class="absolute z-50 mt-1.5 w-full bg-white dark:bg-[#13231B] border border-[#CFDCD3] dark:border-[#203A2B] rounded-xl shadow-xl max-h-64 overflow-y-auto left-0">
                                <div x-show="haulers.length === 0" class="p-4 text-center text-xs text-[#52705F] dark:text-[#8AA895]">
                                    No haulers set up yet
                                </div>
                                <div x-show="haulers.length > 0">
                                    <div class="sticky top-0 bg-[#F7FAF8] dark:bg-[#1A3324] px-3 py-2 border-b border-[#E3EBE5] dark:border-[#203A2B] z-10 flex justify-between items-center text-xs">
                                        <button @click="filters.hauler_ids = haulers.map(h => String(h.id))" type="button"
                                            class="font-semibold text-[#005B2A] dark:text-[#52C97E] hover:underline">Select All</button>
                                        <button @click="filters.hauler_ids = []" type="button"
                                            class="font-medium text-[#52705F] dark:text-[#8AA895] hover:text-rose-600">Clear</button>
                                    </div>
                                    <div class="divide-y divide-[#E3EBE5] dark:divide-[#203A2B]">
                                        <template x-for="hauler in haulers" :key="hauler.id">
                                            <label class="flex items-center px-3.5 py-2 hover:bg-[#EAF5ED] dark:hover:bg-[#1A3324] cursor-pointer transition">
                                                <input type="checkbox" :value="String(hauler.id)" x-model="filters.hauler_ids"
                                                    class="rounded border-[#CFDCD3] text-[#005B2A] focus:ring-[#005B2A] mr-2.5">
                                                <span class="text-xs text-[#172B20] dark:text-[#EAF5ED] truncate font-medium" x-text="hauler.name"></span>
                                                <span x-show="!hauler.is_active" class="ml-auto text-[10px] text-[#52705F] dark:text-[#8AA895]">Inactive</span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Date Issued -->
                    <div>
                        <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                            <i class="fas fa-calendar-day text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Date Issued
                        </label>
                        <input type="date" x-model="dateIssued" class="kp-input w-full">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <div class="text-xs text-[#52705F] dark:text-[#8AA895] flex items-center gap-1.5">
                        <i class="fas fa-info-circle text-[#087A3D]" aria-hidden="true"></i>
                        <span>Select crop year and week range, then preview to print or download.</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <button type="button" @click="resetFilters()" class="kp-btn kp-btn-secondary">
                            <i class="fas fa-undo mr-1.5" aria-hidden="true"></i> Reset
                        </button>
                        <button type="submit" class="kp-btn kp-btn-primary shadow-sm" :disabled="!canPreview">
                            <i class="fas fa-file-pdf mr-1.5" aria-hidden="true"></i> Preview Sheet
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- What the sheet holds -->
        <div class="kp-card p-6 text-xs text-[#52705F] dark:text-[#8AA895] leading-relaxed">
            <p class="font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider mb-2">On the sheet</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Each hauler location (e.g. Canlamay, Inapoy) starts on its own page, with its name under the title.</li>
                <li>One line per liquidation entry, sorted by hauler name: Hauler's Name, Plate No., Check No., Net Tons, Deducted Amount, Net Amount and a signature line.</li>
                <li>Check numbers come from the entries in Hauler TLS LIQ; a blank one can be written by hand.</li>
                <li>15 lines per page with page totals; a location with more pages also gets grand totals on its last page.</li>
            </ul>
        </div>
    </div>

    @push('scripts')
        <script>
            function haulerSignatureSheet() {
                const weeks = @json($weeks);
                const now = new Date();
                const today = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
                return {
                    haulers: @json($haulers),
                    filters: { crop_year: '', week_from: '', week_to: '', hauler_ids: [] },
                    dateIssued: today,
                    locationOpen: false,

                    // Weeks of the selected crop year only
                    get cropWeeks() {
                        return weeks.filter(w => w.crop_year === this.filters.crop_year).map(w => w.week_no);
                    },

                    get canPreview() {
                        return this.filters.crop_year && this.filters.week_from && this.filters.week_to;
                    },

                    get locationSelectedText() {
                        const ids = this.filters.hauler_ids;
                        if (ids.length === 0 || ids.length === this.haulers.length) return 'All Locations';
                        if (ids.length === 1) return (this.haulers.find(h => String(h.id) === ids[0]) || {}).name || '1 location selected';
                        return ids.length + ' locations selected';
                    },

                    preview() {
                        if (!this.canPreview) return;
                        const params = new URLSearchParams({
                            crop_year: this.filters.crop_year,
                            week_from: this.filters.week_from,
                            week_to: this.filters.week_to,
                        });
                        if (this.dateIssued) params.append('date_issued', this.dateIssued);
                        this.filters.hauler_ids.forEach(id => params.append('hauler_id[]', id));
                        window.KusugpaPdfPreview.open('{{ route('hauler-signature-report.pdf') }}?' + params.toString(), 'Hauler TLS Signature');
                    },

                    resetFilters() {
                        this.filters = { crop_year: '', week_from: '', week_to: '', hauler_ids: [] };
                        this.locationOpen = false;
                        this.dateIssued = today;
                    },
                };
            }
        </script>
    @endpush
@endsection
