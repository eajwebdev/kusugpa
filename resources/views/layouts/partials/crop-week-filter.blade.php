{{-- Crop Year & Week picker of the dashboard. Sends "filter-changed" (cropYear, week), which public/js/dashboard.js reloads on. --}}
<div class="kp-filter-bar flex items-center gap-2" x-data="filterData()">
    <!-- Crop Year Dropdown -->
    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
        <button @click="open = !open" type="button" class="kp-filter-btn" aria-haspopup="listbox"
            :aria-expanded="open.toString()" aria-label="Select crop year">
            <i class="fas fa-calendar-alt kp-lead" aria-hidden="true"></i>
            <span x-text="selectedCropYear || 'Crop Year'"></span>
            <i class="fas fa-chevron-down text-[10px]" style="color: var(--kp-faint)" aria-hidden="true"></i>
        </button>
        <div x-show="open" x-cloak @click.away="open = false" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            class="kp-menu absolute right-0 z-50 mt-2 max-h-60 w-44 overflow-y-auto py-1" role="listbox">
            <div class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider"
                style="color: var(--kp-faint)">Crop Year</div>
            <template x-for="year in cropYears" :key="year">
                <button @click="selectCropYear(year); open = false" type="button" role="option"
                    :aria-selected="(selectedCropYear === year).toString()"
                    :class="selectedCropYear === year ? 'bg-primary-50 text-primary-700 font-semibold' : ''"
                    class="kp-menu-item flex w-full items-center justify-between px-4 py-2 text-left text-sm">
                    <span x-text="year"></span>
                    <i x-show="selectedCropYear === year" class="fas fa-check text-xs" style="color: var(--kp-emerald)"
                        aria-hidden="true"></i>
                </button>
            </template>
            <div x-show="cropYears.length === 0" class="px-4 py-2 text-sm" style="color: var(--kp-faint)">Loading...</div>
        </div>
    </div>

    <!-- Week Number Dropdown -->
    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
        <button @click="open = !open" type="button" class="kp-filter-btn" aria-haspopup="listbox"
            :aria-expanded="open.toString()" aria-label="Select week number">
            <i class="fas fa-calendar-week kp-lead" aria-hidden="true"></i>
            <span x-text="selectedWeek ? 'Week ' + selectedWeek : 'All Weeks'"></span>
            <i class="fas fa-chevron-down text-[10px]" style="color: var(--kp-faint)" aria-hidden="true"></i>
        </button>
        <div x-show="open" x-cloak @click.away="open = false" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            class="kp-menu absolute right-0 z-50 mt-2 max-h-60 w-44 overflow-y-auto py-1" role="listbox">
            <div class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider"
                style="color: var(--kp-faint)">Week Number</div>
            <button @click="selectWeek(''); open = false" type="button" role="option"
                :aria-selected="(!selectedWeek).toString()"
                :class="!selectedWeek ? 'bg-primary-50 text-primary-700 font-semibold' : ''"
                class="kp-menu-item flex w-full items-center justify-between px-4 py-2 text-left text-sm">
                <span>All Weeks</span>
                <i x-show="!selectedWeek" class="fas fa-check text-xs" style="color: var(--kp-emerald)" aria-hidden="true"></i>
            </button>
            <template x-for="week in weeks" :key="week">
                <button @click="selectWeek(week); open = false" type="button" role="option"
                    :aria-selected="(selectedWeek == week).toString()"
                    :class="selectedWeek == week ? 'bg-primary-50 text-primary-700 font-semibold' : ''"
                    class="kp-menu-item flex w-full items-center justify-between px-4 py-2 text-left text-sm">
                    <span x-text="'Week ' + week"></span>
                    <i x-show="selectedWeek == week" class="fas fa-check text-xs" style="color: var(--kp-emerald)"
                        aria-hidden="true"></i>
                </button>
            </template>
            <div x-show="weeks.length === 0" class="px-4 py-2 text-sm" style="color: var(--kp-faint)"
                x-text="weeksLoading ? 'Loading weeks...' : 'No weeks configured for this crop year'"></div>
        </div>
    </div>
</div>

<script>
    function filterData() {
        return {
            cropYears: [],
            weeks: [],
            selectedCropYear: '',
            selectedWeek: '',
            weeksLoading: false,
            weekRequestId: 0,

            init() {
                this.loadFilterOptions();
                window.addEventListener('refresh-filters', () => {
                    this.loadFilterOptions();
                });
            },

            async loadFilterOptions() {
                try {
                    const response = await fetch('{{ route('dashboard.data') }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    if (!response.ok) throw new Error('Could not load crop years');
                    const data = await response.json();

                    if (data.stats && !this.selectedCropYear) {
                        this.selectedCropYear = data.stats.currentCropYear || '';
                    }
                    if (data.availableYears) {
                        this.cropYears = data.availableYears;
                    } else if (data.yearlyData && data.yearlyData.labels) {
                        this.cropYears = data.yearlyData.labels;
                    }
                    if (!this.cropYears.includes(this.selectedCropYear)) {
                        this.selectedCropYear = data.stats?.currentCropYear || this.cropYears[0] || '';
                        this.selectedWeek = '';
                    }

                    await this.loadWeeksForYear(this.selectedCropYear);
                } catch (error) {
                    console.error('Error loading filters:', error);
                }
            },

            async loadWeeksForYear(cropYear) {
                const requestId = ++this.weekRequestId;
                this.weeks = [];
                if (!cropYear) {
                    this.weeksLoading = false;
                    return;
                }
                this.weeksLoading = true;

                try {
                    const url = new URL(@json(route('get.weeks')), window.location.origin);
                    url.searchParams.set('crop_year', cropYear);
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    if (!response.ok) throw new Error('Could not load weeks');
                    const data = await response.json();
                    if (requestId !== this.weekRequestId) return;
                    this.weeks = (data.weeks || []).map(Number).filter(Number.isInteger);
                } catch (error) {
                    if (requestId === this.weekRequestId) console.error('Error loading weeks:', error);
                } finally {
                    if (requestId === this.weekRequestId) this.weeksLoading = false;
                }
            },

            selectCropYear(year) {
                this.selectedCropYear = year;
                this.selectedWeek = '';
                this.loadWeeksForYear(year);
                this.dispatchFilterChange();
            },

            selectWeek(week) {
                this.selectedWeek = week || '';
                this.dispatchFilterChange();
            },

            dispatchFilterChange() {
                window.dispatchEvent(new CustomEvent('filter-changed', {
                    detail: {
                        cropYear: this.selectedCropYear,
                        week: this.selectedWeek
                    }
                }));
            }
        }
    }
</script>
