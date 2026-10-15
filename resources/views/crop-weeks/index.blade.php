<!-- resources/views/crop-weeks/index.blade.php -->
@extends('layouts.main')

@section('title', 'Crop Year & Week Management')

@section('content')
    <div x-data="cropWeekData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Season & Calendar</p>
                    <h1 class="kp-page-title mt-1">Crop Year & Week Management</h1>
                    <p class="kp-page-sub">Configure milling season periods, official crop years, and weekly delivery schedules.</p>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Crop Years Section -->
            <div class="kp-card overflow-hidden">
                <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--kp-border)">
                    <div class="flex items-center gap-2.5">
                        <i class="fas fa-calendar-alt text-base" style="color: var(--kp-forest)"></i>
                        <div>
                            <h2 class="font-display text-base font-semibold" style="color: var(--kp-text)">Crop Years</h2>
                            <p class="text-xs" style="color: var(--kp-muted)">Registered milling seasons</p>
                        </div>
                    </div>
                    <span class="text-xs font-mono font-medium px-2.5 py-1 rounded border"
                        style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-muted)"
                        x-text="cropYears.length + ' seasons'"></span>
                </div>
                <div class="kp-table-wrap" style="max-height: 420px; overflow-y: auto;">
                    <table class="kp-table">
                        <thead>
                            <tr>
                                <th class="cursor-pointer select-none" @click="sortCropYears('crop_year')">
                                    <div class="flex items-center gap-1.5">
                                        <span>Crop Year</span>
                                        <i class="fas text-[10px]" :class="cropYearSortField === 'crop_year' ? (cropYearSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center cursor-pointer select-none" @click="sortCropYears('weeks_count')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>Weeks</span>
                                        <i class="fas text-[10px]" :class="cropYearSortField === 'weeks_count' ? (cropYearSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="cropYear in sortedCropYears" :key="cropYear.id">
                                <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                    <td class="font-mono font-semibold" style="color: var(--kp-text)" x-text="cropYear.crop_year"></td>
                                    <td class="text-center">
                                        <span class="kp-badge kp-badge-emerald font-mono tabular" x-text="cropYear.weeks_count + ' weeks'"></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button @click="editCropYear(cropYear.id, cropYear.crop_year)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] transition"
                                                style="color: var(--kp-cane)" title="Edit Season">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button @click="deleteCropYear(cropYear.id, cropYear.crop_year)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] transition text-red-600"
                                                title="Delete Season">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="cropYears.length === 0">
                                <td colspan="3" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                    <i class="fas fa-calendar-alt text-3xl mb-2 block opacity-30"></i>
                                    No crop year entries recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Weeks Section -->
            <div class="kp-card overflow-hidden">
                <div class="p-5 border-b flex flex-wrap items-center justify-between gap-3" style="border-color: var(--kp-border)">
                    <div class="flex items-center gap-2.5">
                        <i class="fas fa-calendar-week text-base" style="color: var(--kp-forest)"></i>
                        <div>
                            <h2 class="font-display text-base font-semibold" style="color: var(--kp-text)">Week Numbers</h2>
                            <p class="text-xs" style="color: var(--kp-muted)">Active schedule breakdown</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <select x-model="weekCropYearFilter" @change="filterWeeks()" class="kp-select text-xs py-1 px-2.5">
                            <option value="">All Crop Years</option>
                            <template x-for="cy in cropYears" :key="cy.id">
                                <option :value="cy.crop_year" x-text="cy.crop_year"></option>
                            </template>
                        </select>
                        <span class="text-xs font-mono font-medium px-2 py-1 rounded border"
                            style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-muted)"
                            x-text="filteredWeeks.length + ' weeks'"></span>
                    </div>
                </div>
                <div class="kp-table-wrap" style="max-height: 420px; overflow-y: auto;">
                    <table class="kp-table">
                        <thead>
                            <tr>
                                <th class="cursor-pointer select-none" @click="sortWeeks('crop_year')">
                                    <div class="flex items-center gap-1.5">
                                        <span>Season</span>
                                        <i class="fas text-[10px]" :class="weekSortField === 'crop_year' ? (weekSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center cursor-pointer select-none" @click="sortWeeks('week_no')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>Week #</span>
                                        <i class="fas text-[10px]" :class="weekSortField === 'week_no' ? (weekSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="cursor-pointer select-none" @click="sortWeeks('week_start_date')">
                                    <div class="flex items-center gap-1.5">
                                        <span>Start</span>
                                        <i class="fas text-[10px]" :class="weekSortField === 'week_start_date' ? (weekSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="cursor-pointer select-none" @click="sortWeeks('week_end_date')">
                                    <div class="flex items-center gap-1.5">
                                        <span>End</span>
                                        <i class="fas text-[10px]" :class="weekSortField === 'week_end_date' ? (weekSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort opacity-30'"></i>
                                    </div>
                                </th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="week in sortedWeeks" :key="week.id">
                                <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                    <td>
                                        <span class="font-mono text-xs px-2 py-0.5 rounded border"
                                            style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-text)"
                                            x-text="week.crop_year"></span>
                                    </td>
                                    <td class="text-center font-mono font-semibold" style="color: var(--kp-text)"
                                        x-text="'Week ' + week.week_no"></td>
                                    <td>
                                        <div class="flex flex-col text-xs">
                                            <span style="color: var(--kp-text)" x-text="formatDate(week.week_start_date)"></span>
                                            <span class="font-mono text-[11px]" style="color: var(--kp-muted)" x-text="formatTime(week.week_start_date)"></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col text-xs">
                                            <span style="color: var(--kp-text)" x-text="formatDate(week.week_end_date)"></span>
                                            <span class="font-mono text-[11px]" style="color: var(--kp-muted)" x-text="formatTime(week.week_end_date)"></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button @click="editWeek(week)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] transition"
                                                style="color: var(--kp-cane)" title="Edit Week">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button @click="deleteWeek(week.id)"
                                                class="p-1.5 rounded-md hover:bg-[var(--kp-surface-2)] transition text-red-600"
                                                title="Delete Week">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredWeeks.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                    <i class="fas fa-calendar-week text-3xl mb-2 block opacity-30"></i>
                                    No weeks recorded for the selected filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit Crop Year Modal -->
        <div x-show="showCropYearModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showCropYearModal = false"></div>
            <div class="relative rounded-xl border max-w-md w-full p-6 shadow-2xl"
                style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                    <h3 class="font-display text-lg font-semibold" style="color: var(--kp-text)">Edit Crop Year</h3>
                    <button @click="showCropYearModal = false" class="p-1 rounded text-xs hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <form @submit.prevent="saveCropYear()" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--kp-muted)">Crop Year Code (8 digits)</label>
                        <input type="text" x-model="cropYearForm.crop_year" placeholder="e.g., 20252026" maxlength="8" required class="kp-input w-full">
                    </div>
                    <div class="flex gap-2 pt-2 border-t" style="border-color: var(--kp-border)">
                        <button type="button" @click="showCropYearModal = false" class="kp-btn kp-btn-secondary flex-1">Cancel</button>
                        <button type="submit" class="kp-btn kp-btn-primary flex-1">
                            <i class="fas fa-save mr-1.5"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Week Modal -->
        <div x-show="showWeekModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showWeekModal = false"></div>
            <div class="relative rounded-xl border max-w-md w-full p-6 shadow-2xl"
                style="background: var(--kp-surface); border-color: var(--kp-border); color: var(--kp-text)">
                <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--kp-border)">
                    <h3 class="font-display text-lg font-semibold" style="color: var(--kp-text)">Edit Week Definition</h3>
                    <button @click="showWeekModal = false" class="p-1 rounded text-xs hover:bg-[var(--kp-surface-2)]" style="color: var(--kp-muted)">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <form @submit.prevent="saveWeek()" class="space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Crop Year</label>
                            <input type="text" x-model="weekForm.crop_year" placeholder="20252026" maxlength="8" required class="kp-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--kp-muted)">Week Number</label>
                            <input type="number" x-model="weekForm.week_no" min="1" max="52" required class="kp-input w-full">
                        </div>
                    </div>

                    <!-- Start Date & Time -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--kp-faint)">Start Schedule</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" x-model="weekForm.week_start_date" required class="kp-input w-full">
                            <input type="time" x-model="weekForm.week_start_time" step="1" required class="kp-input w-full">
                        </div>
                    </div>

                    <!-- End Date & Time -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--kp-faint)">End Schedule</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" x-model="weekForm.week_end_date" required class="kp-input w-full">
                            <input type="time" x-model="weekForm.week_end_time" step="1" required class="kp-input w-full">
                        </div>
                    </div>

                    <div class="flex gap-2 pt-3 border-t" style="border-color: var(--kp-border)">
                        <button type="button" @click="showWeekModal = false" class="kp-btn kp-btn-secondary flex-1">Cancel</button>
                        <button type="submit" class="kp-btn kp-btn-primary flex-1">
                            <i class="fas fa-save mr-1.5"></i> Save Week
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function cropWeekData() {
            return {
                showCropYearModal: false,
                showWeekModal: false,
                editingCropYearId: null,
                editingWeekId: null,
                cropYearForm: {
                    crop_year: ''
                },
                weekForm: {
                    crop_year: '',
                    week_no: '',
                    week_start_date: '',
                    week_start_time: '00:00:00',
                    week_end_date: '',
                    week_end_time: '23:59:59'
                },
                cropYearSortField: 'crop_year',
                cropYearSortDir: 'asc',
                weekSortField: 'week_no',
                weekSortDir: 'asc',
                weekCropYearFilter: '',
                cropYears: {!! json_encode($cropYearsData) !!},
                weeks: {!! json_encode($weeksData) !!},

                get sortedCropYears() {
                    return [...this.cropYears].sort((a, b) => {
                        let valA = a[this.cropYearSortField] ?? '';
                        let valB = b[this.cropYearSortField] ?? '';
                        if (typeof valA === 'string') valA = valA.toLowerCase();
                        if (typeof valB === 'string') valB = valB.toLowerCase();
                        if (valA < valB) return this.cropYearSortDir === 'asc' ? -1 : 1;
                        if (valA > valB) return this.cropYearSortDir === 'asc' ? 1 : -1;
                        return 0;
                    });
                },

                get filteredWeeks() {
                    if (!this.weekCropYearFilter) return this.weeks;
                    return this.weeks.filter(w => w.crop_year === this.weekCropYearFilter);
                },

                get sortedWeeks() {
                    return [...this.filteredWeeks].sort((a, b) => {
                        let valA = a[this.weekSortField] ?? '';
                        let valB = b[this.weekSortField] ?? '';

                        if (this.weekSortField === 'week_no') {
                            valA = parseInt(valA) || 0;
                            valB = parseInt(valB) || 0;
                        } else if (typeof valA === 'string') {
                            valA = valA.toLowerCase();
                            valB = valB.toLowerCase();
                        }

                        if (valA < valB) return this.weekSortDir === 'asc' ? -1 : 1;
                        if (valA > valB) return this.weekSortDir === 'asc' ? 1 : -1;
                        return 0;
                    });
                },

                sortCropYears(field) {
                    if (this.cropYearSortField === field) {
                        this.cropYearSortDir = this.cropYearSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.cropYearSortField = field;
                        this.cropYearSortDir = 'asc';
                    }
                },

                sortWeeks(field) {
                    if (this.weekSortField === field) {
                        this.weekSortDir = this.weekSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.weekSortField = field;
                        this.weekSortDir = 'asc';
                    }
                },

                filterWeeks() {},

                cropYearBaseUrl: '{{ url('/crop-year') }}',
                weekBaseUrl: '{{ url('/week') }}',

                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                },

                async parseJsonResponse(response, url) {
                    const text = await response.text();
                    let data = {};
                    try {
                        data = text ? JSON.parse(text) : {};
                    } catch (e) {
                        const titleMatch = text.match(/<title[^>]*>([^<]*)<\/title>/i);
                        const title = titleMatch ? titleMatch[1].trim() : '';
                        const preview = (title ? title + ' — ' : '') + 'HTTP ' + response.status +
                            ' on ' + (url || 'request');
                        if (response.status === 419) throw new Error(
                            'Session expired (419). Please refresh the page and try again.');
                        if (response.status === 401) throw new Error(
                            'Unauthenticated (401). Please sign in again.');
                        if (response.status === 403) throw new Error(
                            'Forbidden (403). ' + (title || 'Blocked by server/WAF or missing permission.'));
                        if (response.status === 404) throw new Error(
                            'Not found (404) on ' + (url || 'request') + '. ' + (title ||
                                'Route missing on hosted server.'));
                        if (response.status === 405) throw new Error(
                            'Method not allowed (405). Your host blocks PUT/DELETE — redeploy this fix which uses POST spoofing.');
                        if (response.status === 500) throw new Error(
                            'Server error (500). ' + (title || 'Check storage/logs/laravel.log on hosting.'));
                        if (response.status === 503) throw new Error(
                            'Service unavailable (503). ' + (title || 'System locked / maintenance.'));
                        throw new Error('Server returned HTML instead of JSON. ' + preview +
                            '. Check DevTools Network → Response tab.');
                    }
                    if (!response.ok) throw new Error(data.message || ('Request failed (HTTP ' +
                        response.status + ')'));
                    return data;
                },

                formatDate(dateString) {
                    if (!dateString) return 'N/A';
                    if (/^\d{4}-\d{4}$/.test(dateString)) return dateString;
                    const match = dateString.match(/^(\d{4})-(\d{2})-(\d{2})/);
                    if (match) {
                        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        return months[parseInt(match[2]) - 1] + ' ' + parseInt(match[3]) + ', ' + match[1];
                    }
                    return dateString;
                },

                formatTime(dateString) {
                    if (!dateString) return '--:--:--';
                    let match = dateString.match(/(\d{2}):(\d{2}):(\d{2})/);
                    if (match) return match[1] + ':' + match[2] + ':' + match[3];
                    match = dateString.match(/(\d{2}):(\d{2})/);
                    if (match) return match[1] + ':' + match[2] + ':00';
                    return '--:--:--';
                },

                editCropYear(id, cropYear) {
                    this.editingCropYearId = id;
                    this.cropYearForm = {
                        crop_year: cropYear
                    };
                    this.showCropYearModal = true;
                },

                async saveCropYear() {
                    const url = this.cropYearBaseUrl + '/' + this.editingCropYearId;
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-HTTP-Method-Override': 'PUT',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({
                                _method: 'PUT',
                                crop_year: this.cropYearForm.crop_year,
                                user_id: '{{ auth()->id() }}'
                            })
                        });
                        const data = await this.parseJsonResponse(response, url);
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        this.showCropYearModal = false;
                        setTimeout(() => location.reload(), 1500);
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message
                        });
                    }
                },

                async deleteCropYear(id, cropYear) {
                    const result = await Swal.fire({
                        title: 'Delete Crop Year?',
                        text: 'Delete "' + cropYear + '"?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete',
                        confirmButtonColor: '#832727'
                    });
                    if (!result.isConfirmed) return;
                    try {
                        const delCropUrl = this.cropYearBaseUrl + '/' + id;
                        const response = await fetch(delCropUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-HTTP-Method-Override': 'DELETE',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({
                                _method: 'DELETE'
                            })
                        });
                        const data = await this.parseJsonResponse(response, delCropUrl);
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        setTimeout(() => location.reload(), 1500);
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message
                        });
                    }
                },

                editWeek(week) {
                    this.editingWeekId = week.id;
                    const extractDateTime = (dateString) => {
                        if (!dateString) return { date: '', time: '00:00:00' };
                        const fullMatch = dateString.match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}):?(\d{2})?$/);
                        if (fullMatch) return { date: fullMatch[1], time: fullMatch[2] + ':' + (fullMatch[3] || '00') };
                        const tMatch = dateString.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}):?(\d{2})?/);
                        if (tMatch) return { date: tMatch[1], time: tMatch[2] + ':' + (tMatch[3] || '00') };
                        return { date: '', time: '00:00:00' };
                    };
                    const start = extractDateTime(week.week_start_date);
                    const end = extractDateTime(week.week_end_date);
                    this.weekForm = {
                        crop_year: week.crop_year,
                        week_no: week.week_no,
                        week_start_date: start.date,
                        week_start_time: start.time,
                        week_end_date: end.date,
                        week_end_time: end.time
                    };
                    this.showWeekModal = true;
                },

                async saveWeek() {
                    const startTime = this.weekForm.week_start_time.length === 5 ? this.weekForm.week_start_time + ':00' : this.weekForm.week_start_time;
                    const endTime = this.weekForm.week_end_time.length === 5 ? this.weekForm.week_end_time + ':00' : this.weekForm.week_end_time;
                    const weekStart = this.weekForm.week_start_date + ' ' + startTime;
                    const weekEnd = this.weekForm.week_end_date + ' ' + endTime;
                    if (new Date(weekEnd) <= new Date(weekStart)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'End date/time must be after start date/time'
                        });
                        return;
                    }
                    try {
                        const saveWeekUrl = this.weekBaseUrl + '/' + this.editingWeekId;
                        const response = await fetch(saveWeekUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-HTTP-Method-Override': 'PUT',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({
                                _method: 'PUT',
                                crop_year: this.weekForm.crop_year,
                                week_no: this.weekForm.week_no,
                                week_start_date: weekStart,
                                week_end_date: weekEnd,
                                user_id: '{{ auth()->id() }}'
                            })
                        });
                        const data = await this.parseJsonResponse(response, saveWeekUrl);
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        this.showWeekModal = false;
                        setTimeout(() => location.reload(), 1500);
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message
                        });
                    }
                },

                async deleteWeek(id) {
                    const result = await Swal.fire({
                        title: 'Delete Week?',
                        text: 'This cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete',
                        confirmButtonColor: '#832727'
                    });
                    if (!result.isConfirmed) return;
                    try {
                        const delWeekUrl = this.weekBaseUrl + '/' + id;
                        const response = await fetch(delWeekUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-HTTP-Method-Override': 'DELETE',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({
                                _method: 'DELETE'
                            })
                        });
                        const data = await this.parseJsonResponse(response, delWeekUrl);
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        setTimeout(() => location.reload(), 1500);
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message
                        });
                    }
                }
            };
        }
    </script>
@endsection
