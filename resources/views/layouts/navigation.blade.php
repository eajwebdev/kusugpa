<!-- resources/views/layouts/navigation.blade.php -->
<header class="kp-topbar">
    <div class="kp-topbar-inner">
        <!-- Left: menu toggles + page identity -->
        <div class="flex min-w-0 items-center gap-2">
            <button @click="sidebarOpen = !sidebarOpen" type="button" class="kp-icon-btn lg:hidden"
                :aria-expanded="sidebarOpen.toString()" aria-controls="main-content">
                <span class="sr-only">Open sidebar</span>
                <i class="fas fa-bars text-lg" aria-hidden="true"></i>
            </button>
            <button @click="collapsed = !collapsed" type="button" class="kp-icon-btn hidden lg:inline-grid"
                :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <i class="fas fa-angles-left text-sm transition-transform duration-200"
                    :class="{ 'rotate-180': collapsed }" aria-hidden="true"></i>
            </button>

            <div class="ml-1 min-w-0 border-l pl-4" style="border-color: var(--kp-border)">
                <p class="kp-crumb-eyebrow hidden sm:block">Sugarcane Crop Management &amp; Recording System</p>
                <p class="kp-crumb-title truncate">@hasSection('title')@yield('title')@else Dashboard @endif</p>
            </div>
        </div>

        <!-- Right: Crop Year & Week filters -->
        <div class="flex items-center gap-3">
            <div class="hidden items-center gap-2 md:flex" x-data="filterData()">
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
                        <span x-text="selectedWeek ? 'Week ' + selectedWeek : 'Week No'"></span>
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
                        <div x-show="weeks.length === 0" class="px-4 py-2 text-sm" style="color: var(--kp-faint)">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    function filterData() {
        return {
            cropYears: [],
            weeks: [],
            selectedCropYear: '',
            selectedWeek: '',

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
                    const data = await response.json();

                    if (data.stats && !this.selectedCropYear) {
                        this.selectedCropYear = data.stats.currentCropYear || '';
                    }
                    if (data.stats && !this.selectedWeek) {
                        this.selectedWeek = data.stats.currentWeek || '';
                    }

                    if (data.availableYears) {
                        this.cropYears = data.availableYears;
                    } else if (data.yearlyData && data.yearlyData.labels) {
                        this.cropYears = data.yearlyData.labels;
                    }

                    await this.loadWeeksForYear(this.selectedCropYear);
                } catch (error) {
                    console.error('Error loading filters:', error);
                }
            },

            async loadWeeksForYear(cropYear) {
                if (!cropYear) return;

                try {
                    const response = await fetch('{{ route('dashboard.weekly') }}?year=' + cropYear, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    const data = await response.json();

                    if (data.labels) {
                        this.weeks = data.labels.map(label =>
                            parseInt(label.replace('Week ', ''))
                        ).filter(w => !isNaN(w));
                    }
                } catch (error) {
                    console.error('Error loading weeks:', error);
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

    function clockData() {
        return {
            timeFormatted: '',
            dateFormatted: '',
            dayName: '',

            startClock() {
                this.updateClock();
                setInterval(() => {
                    this.updateClock();
                }, 1000);
            },

            updateClock() {
                const now = new Date();
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');

                this.timeFormatted = `${hours}:${minutes}:${seconds}`;
                this.dateFormatted = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
                this.dayName = days[now.getDay()];
            }
        }
    }
</script>
