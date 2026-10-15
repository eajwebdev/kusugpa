<!-- resources/views/layouts/main.blade.php -->
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#003F20">
    <link rel="icon" type="image/png" href="{{ asset('img/logo-64.png') }}">
    <title>KUSUG-PA @hasSection('title')| @yield('title')@elseif(isset($title))| {{ $title }}@endif</title>

    <!-- Apply saved theme / sidebar state before first paint (prevents flash) -->
    <script>
        (function() {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
                if (localStorage.getItem('kp.sidebar') === '1') {
                    document.documentElement.classList.add('kp-collapsed');
                }
            } catch (e) {}
        })();
    </script>

    @include('layouts.partials.assets')

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @stack('styles')
</head>

<body class="h-full" x-data="{ sidebarOpen: window.innerWidth >= 1024, collapsed: document.documentElement.classList.contains('kp-collapsed') }"
    x-init="$watch('collapsed', v => { document.documentElement.classList.toggle('kp-collapsed', v); try { localStorage.setItem('kp.sidebar', v ? '1' : '0'); } catch (e) {} })"
    @resize.window="sidebarOpen = window.innerWidth >= 1024" @kp-expand-sidebar.window="collapsed = false">
    <a href="#main-content" class="kp-skip">Skip to main content</a>

    <div class="kp-shell min-h-full">
        <!-- Mobile Overlay -->
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="kp-overlay" aria-hidden="true">
        </div>

        <!-- Desktop Sidebar (lg+, collapsible) -->
        <aside class="kp-sidebar-desktop" aria-label="Primary">
            @include('layouts.sidebar-desktop')
        </aside>

        <!-- Mobile Sidebar (drawer, toggles with sidebarOpen) -->
        <aside x-show="sidebarOpen" x-cloak @keydown.escape.window="if (window.innerWidth < 1024) sidebarOpen = false"
            x-transition:enter="transition ease-out duration-200 transform" x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150 transform"
            x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
            class="kp-sidebar-mobile" aria-label="Primary">
            @include('layouts.sidebar-mobile')
        </aside>

        <div class="kp-content-wrap">
            @include('layouts.navigation')

            <main id="main-content" class="kp-content" tabindex="-1">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Give every sidebar control an accessible name / tooltip (needed when the rail is collapsed)
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.kp-nav > ul > li > ul > li > a, .kp-nav > ul > li > ul > li > button').forEach(el => {
                const label = (el.querySelector('span') || el).textContent.trim().replace(/\s+/g, ' ');
                if (label && !el.getAttribute('aria-label')) { el.setAttribute('aria-label', label); }
                if (label && !el.getAttribute('title')) { el.setAttribute('title', label); }
            });
            // In the collapsed rail, dropdown groups can't show their children: expand the rail first
            document.querySelectorAll('.kp-sidebar-desktop .kp-nav button').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (document.documentElement.classList.contains('kp-collapsed') && btn.querySelector('.fa-chevron-down')) {
                        window.dispatchEvent(new CustomEvent('kp-expand-sidebar'));
                    }
                });
            });
        });
    </script>

    <!-- Dark Mode & Profile Dropdown Script -->
    <script>
        // Check for saved theme preference
        if (localStorage.getItem('theme') === 'dark' ||
            (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }

        // Alpine component for profile dropdown with theme toggle
        document.addEventListener('alpine:init', () => {
            Alpine.data('profileDropdown', () => ({
                profileOpen: false,
                isDark: document.documentElement.classList.contains('dark'),

                init() {
                    // Update isDark when theme changes externally
                    this.isDark = document.documentElement.classList.contains('dark');
                },

                toggleTheme() {
                    const html = document.documentElement;

                    if (html.classList.contains('dark')) {
                        html.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                        this.isDark = false;
                    } else {
                        html.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                        this.isDark = true;
                    }
                }
            }));
        });

        // Global toggleDarkMode for backward compatibility (used in navigation etc.)
        function toggleDarkMode() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        }
    </script>

    @stack('scripts')


    <script>
        const swalCustomStyles = `
    <style>
    .swal2-popup {
        border-radius: 14px !important;
        padding: 1.75rem !important;
    }
    .swal2-title {
        margin-bottom: 1.25rem !important;
    }
    .swal2-input {
        border: 1px solid #BCCBC2 !important;
        border-radius: 8px !important;
        padding: 0.7rem 1rem !important;
        font-size: 0.95rem !important;
        transition: all 0.3s ease !important;
        margin: 0.5rem 0 !important;
        width: 100% !important;
    }
    .swal2-input:focus {
        border-color: #087A3D !important;
        box-shadow: 0 0 0 3px rgba(8, 122, 61, 0.28) !important;
        outline: none !important;
    }
    .swal2-select {
        border: 1px solid #BCCBC2 !important;
        border-radius: 8px !important;
        padding: 0.7rem 1rem !important;
        font-size: 0.95rem !important;
        transition: all 0.3s ease !important;
        margin: 0.5rem 0 !important;
        width: 100% !important;
        background-color: white !important;
    }
    .swal2-select:focus {
        border-color: #087A3D !important;
        box-shadow: 0 0 0 3px rgba(8, 122, 61, 0.28) !important;
        outline: none !important;
    }
    .swal2-confirm {
        padding: 0.65rem 1.6rem !important;
        font-size: 0.9rem !important;
    }
    .swal2-cancel {
        padding: 0.65rem 1.6rem !important;
        font-size: 0.9rem !important;
    }
    .form-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: #172B20;
        margin-bottom: 0.25rem;
    }
    .form-label i {
        width: 20px;
        color: #087A3D;
        font-size: 0.9rem;
    }
    .input-group {
        margin-bottom: 1rem;
        text-align: left;
    }
    .input-icon-wrapper {
        position: relative;
    }
    .input-icon-wrapper i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #66786C;
        z-index: 1;
    }
    .input-icon-wrapper input,
    .input-icon-wrapper select {
        padding-left: 2.75rem !important;
    }
    </style>
    `;

        function refreshDashboardData() {
            window.dispatchEvent(new CustomEvent('refresh-dashboard'));
        }

        // Base URLs rendered by Blade so fetch() works on hosted subdirectories /
        // HTTPS too. Hardcoded '/get-weeks' or '/upload/...' hits the domain root
        // on hosting and returns an HTML 404 page -> "Error loading weeks".
        const APP_BASE_URL = '{{ url('/') }}'.replace(/\/$/, '');
        const GET_WEEKS_URL = '{{ url('/get-weeks') }}';
        const UPLOAD_BASE_URL = '{{ url('/upload') }}';
        // Tracks which upload modal is open. Used by checkUploadReady() instead of
        // guessing the type from the modal title text (fragile).
        let currentUploadType = '';

        function openQuedanPriceModal() {
            Swal.fire({
                title: 'Add Quedan Price',
                html: swalCustomStyles + `
            <div style="padding: 0.5rem 0;">
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-tag"></i> Quedan Type</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-tag"></i>
                        <input type="text" id="quedan_type" class="swal2-input" placeholder="Enter quedan type (e.g., A, B, C)" style="padding-left: 2.75rem;">
                    </div>
                </div>
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-peso-sign"></i> Quedan Price</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-peso-sign"></i>
                        <input type="number" id="quedan_price" class="swal2-input" placeholder="Enter price (e.g., 950.00)" step="0.01">
                    </div>
                </div>
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Crop Year</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-calendar-alt"></i>
                        <select id="crop_year" class="swal2-select">
                            <option value="">Select crop year</option>
                            @foreach (App\Models\CropYear::pluck('crop_year') as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-calendar-week"></i> Week Number</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-calendar-week"></i>
                        <select id="week_no" class="swal2-select">
                            <option value="">Select week number</option>
                            @foreach (App\Models\WeekNo::pluck('week_no')->unique()->sort() as $week)
                                <option value="{{ $week }}">Week {{ $week }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save mr-2"></i> Save Price',
                cancelButtonText: '<i class="fas fa-times mr-2"></i> Cancel',
                preConfirm: () => {
                    const quedanType = document.getElementById('quedan_type').value.trim();
                    const quedanPrice = document.getElementById('quedan_price').value;
                    const cropYear = document.getElementById('crop_year').value;
                    const weekNo = document.getElementById('week_no').value;
                    if (!quedanType || !quedanPrice || !cropYear || !weekNo) {
                        Swal.showValidationMessage('Please fill all fields');
                        return false;
                    }
                    return fetch('{{ url('/updates/add-quedan-price') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                quedan_type: quedanType,
                                quedan_price: quedanPrice,
                                crop_year: cropYear,
                                week_no: weekNo,
                                user_id: '{{ auth()->id() }}'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.message) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                refreshDashboardData();
                            }
                        })
                        .catch(error => {
                            Swal.showValidationMessage('Failed to save. Please try again.');
                        });
                }
            });
        }

        function openMolassesPriceModal() {
            Swal.fire({
                title: 'Add Molasses Price',
                html: swalCustomStyles + `
            <div style="padding: 0.5rem 0;">
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-peso-sign"></i> Molasses Price</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-peso-sign"></i>
                        <input type="number" id="mol_price" class="swal2-input" placeholder="Enter price (e.g., 850.00)" step="0.01">
                    </div>
                </div>
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Crop Year</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-calendar-alt"></i>
                        <select id="crop_year" class="swal2-select">
                            <option value="">Select crop year</option>
                            @foreach (App\Models\CropYear::pluck('crop_year') as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-calendar-week"></i> Week Number</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-calendar-week"></i>
                        <select id="week_no" class="swal2-select">
                            <option value="">Select week number</option>
                            @foreach (App\Models\WeekNo::pluck('week_no')->unique()->sort() as $week)
                                <option value="{{ $week }}">Week {{ $week }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save mr-2"></i> Save Price',
                cancelButtonText: '<i class="fas fa-times mr-2"></i> Cancel',
                preConfirm: () => {
                    const molPrice = document.getElementById('mol_price').value;
                    const cropYear = document.getElementById('crop_year').value;
                    const weekNo = document.getElementById('week_no').value;
                    if (!molPrice || !cropYear || !weekNo) {
                        Swal.showValidationMessage('Please fill all fields');
                        return false;
                    }
                    return fetch('{{ url('/updates/add-molasses-price') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                mol_price: molPrice,
                                crop_year: cropYear,
                                week_no: weekNo,
                                user_id: '{{ auth()->id() }}'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.message) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                refreshDashboardData();
                            }
                        })
                        .catch(error => {
                            Swal.showValidationMessage('Failed to save. Please try again.');
                        });
                }
            });
        }

        function openCropYearModal() {
            Swal.fire({
                title: 'Add Crop Year',
                html: swalCustomStyles + `
            <div style="padding: 0.5rem 0;">
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Crop Year</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="text" id="new_crop_year" class="swal2-input" placeholder="Enter crop year (e.g., 20232024)" maxlength="8">
                    </div>
                    <p style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;"><i class="fas fa-info-circle mr-1"></i> Format: YYYYMMDD (e.g., 20232024)</p>
                </div>
            </div>
        `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-plus-circle mr-2"></i> Add Crop Year',
                cancelButtonText: '<i class="fas fa-times mr-2"></i> Cancel',
                preConfirm: () => {
                    const cropYear = document.getElementById('new_crop_year').value.trim();
                    if (!cropYear) {
                        Swal.showValidationMessage('Please enter crop year');
                        return false;
                    }
                    if (!cropYear.match(/^\d{8}$/)) {
                        Swal.showValidationMessage('Please use format: YYYYMMDD (e.g., 20232024)');
                        return false;
                    }
                    return fetch('{{ url('/updates/add-crop-year') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                crop_year: cropYear,
                                user_id: '{{ auth()->id() }}'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.message) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                refreshDashboardData();
                            }
                        })
                        .catch(error => {
                            Swal.showValidationMessage('Failed to save. Please try again.');
                        });
                }
            });
        }

        function openWeekNumberModal() {
            Swal.fire({
                title: 'Add Week Number',
                html: swalCustomStyles + `
            <div style="padding: 0.5rem 0;">
                <div class="input-group"><label class="form-label"><i class="fas fa-calendar-alt"></i> Crop Year</label><div class="input-icon-wrapper"><i class="fas fa-calendar-alt"></i><select id="crop_year" class="swal2-select"><option value="">Select crop year</option>@foreach (App\Models\CropYear::pluck('crop_year') as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select></div></div>
                <div class="input-group"><label class="form-label"><i class="fas fa-hashtag"></i> Week Number</label><div class="input-icon-wrapper"><i class="fas fa-hashtag"></i><input type="number" id="week_no" class="swal2-input" placeholder="Enter week number (e.g., 1)" min="1" max="52"></div></div>
                <div class="input-group"><label class="form-label"><i class="fas fa-play-circle"></i> Week Start</label><div class="grid grid-cols-2 gap-2"><div class="input-icon-wrapper"><i class="fas fa-calendar"></i><input type="date" id="week_start_date" class="swal2-input" style="padding-left: 2.75rem;"></div><div class="input-icon-wrapper"><i class="fas fa-clock"></i><input type="time" id="week_start_time" class="swal2-input" value="00:00:00" step="1" style="padding-left: 2.75rem;"></div></div></div>
                <div class="input-group"><label class="form-label"><i class="fas fa-stop-circle"></i> Week End</label><div class="grid grid-cols-2 gap-2"><div class="input-icon-wrapper"><i class="fas fa-calendar"></i><input type="date" id="week_end_date" class="swal2-input" style="padding-left: 2.75rem;"></div><div class="input-icon-wrapper"><i class="fas fa-clock"></i><input type="time" id="week_end_time" class="swal2-input" value="23:59:59" step="1" style="padding-left: 2.75rem;"></div></div></div>
            </div>`,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save mr-2"></i> Save Week',
                cancelButtonText: '<i class="fas fa-times mr-2"></i> Cancel',
                preConfirm: () => {
                    const cropYear = document.getElementById('crop_year').value;
                    const weekNo = document.getElementById('week_no').value;
                    const startDate = document.getElementById('week_start_date').value;
                    const startTime = document.getElementById('week_start_time').value || '00:00:00';
                    const endDate = document.getElementById('week_end_date').value;
                    const endTime = document.getElementById('week_end_time').value || '23:59:59';
                    if (!cropYear || !weekNo || !startDate || !endDate) {
                        Swal.showValidationMessage('Please fill all fields');
                        return false;
                    }
                    const weekStart = startDate + ' ' + startTime;
                    const weekEnd = endDate + ' ' + endTime;
                    if (new Date(weekEnd) <= new Date(weekStart)) {
                        Swal.showValidationMessage('End date/time must be after start date/time');
                        return false;
                    }
                    return fetch('{{ url('/updates/add-week-number') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                crop_year: cropYear,
                                week_no: weekNo,
                                week_start_date: weekStart,
                                week_end_date: weekEnd,
                                user_id: '{{ auth()->id() }}'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.message) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                refreshDashboardData();
                            }
                        })
                        .catch(error => {
                            Swal.showValidationMessage('Failed to save. Please try again.');
                        });
                }
            });
        }

        // Handle file selection
        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                document.getElementById('selectedFileName').textContent = file.name;
                document.getElementById('fileInfo2').style.display = 'block';
            }
        }

        function openUploadModal(type) {
            currentUploadType = type;
            const titles = {
                'summary': '📊 Upload Summary CSV',
                'trucking': '🚛 Upload Trucking Allowance CSV',
                'fci': '🌿 Upload Fresh Cane Incentive CSV',
                'fuel': '⛽ Upload Fuel CSV',
                'rentals': '🏗️ Upload Rentals CSV',
                'underload': '📉 Upload Underload CSV',
                'transloading': '🔄 Upload Transloading CSV',
                'mudpress': '🏭 Upload Mudpress CSV',
                'consolidated': '📦 Upload Consolidated Summary CSV',
                'quedan': '📋 Upload Quedan CSV',
                'molasses': '🧪 Upload Molasses CSV',
            };
            const descriptions = {
                'summary': 'Columns: crop_year, week_no, planter_code, planter_name, net_cane, net_amount',
                'trucking': 'Columns: crop_year, week_no, planter_code, planter_name, net_cane, ta_amount, trans_code',
                'fci': 'Upload fresh cane incentive data',
                'fuel': 'Upload fuel consumption records',
                'rentals': 'Upload rental equipment records',
                'underload': 'Upload underload delivery data',
                'transloading': 'Upload transloading records',
                'mudpress': 'Columns: crop_year, week_no, planter_code, planter_name, trans_code, charge_code, mpress',
                'consolidated': 'Columns: planter_code, assn_code, planter_name, assn_name, ta_wt, ta_amount, emi_wt, emi_amount, pat_wt, pat_amount, cci_fa_wt, cci_fa_amt, cci_fb_wt, cci_fb_amt, cci_fc_wt, cci_fc_amt, fuel_issuance_amt, rental_amt, underload_amt, mudpress_amt, adj_amt',
                'quedan': 'Columns: crop_year, week_no, planter_code, planter_name, qdn_no, tin_no, total_liens, sugar_lkg, labor_lkg',
                'molasses': 'Columns: crop_year, week_no, planter_code, planter_name, tin_no, mc_no, mol_net',
            };

            // Types that need crop_year and week_no selection
            const needsCropWeek = ['summary', 'trucking', 'fci', 'fuel', 'rentals', 'underload', 'transloading', 'mudpress',
                'quedan', 'molasses', 'consolidated'
            ];

            Swal.fire({
                title: titles[type] || 'Upload CSV',
                html: swalCustomStyles + `
            <div style="padding: 0.5rem 0;">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 1.5rem; color: white;"></i>
                    </div>
                    <p style="color: #6b7280; font-size: 0.9rem; margin-top: 0.5rem;">${descriptions[type] || 'Upload CSV data file'}</p>
                </div>
                
                ${needsCropWeek.includes(type) ? `
                                    <!-- Crop Year & Week Selection -->
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                        <div>
                                            <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; text-align: left;">
                                                <i class="fas fa-calendar-alt" style="color: #16a34a; margin-right: 4px;"></i> Crop Year *
                                            </label>
                                            <select id="uploadCropYear" onchange="onUploadCropYearChange(this)" 
                                                style="width: 100%; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; background: white;">
                                                <option value="">Select Crop Year</option>
                                                @foreach (App\Models\CropYear::orderBy('crop_year', 'desc')->pluck('crop_year') as $cy)
                                                    <option value="{{ $cy }}">{{ $cy }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; text-align: left;">
                                                <i class="fas fa-calendar-week" style="color: #16a34a; margin-right: 4px;"></i> Week No *
                                            </label>
                                            <select id="uploadWeekNo" onchange="checkUploadReady()" disabled
                                                style="width: 100%; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; background: white; opacity: 0.6;">
                                                <option value="">Select Week</option>
                                            </select>
                                        </div>
                                    </div>
                                    ` : ''}

                <div style="border: 2px dashed #d1d5db; border-radius: 12px; padding: 2rem; text-align: center; background: #f9fafb; transition: all 0.3s ease;" 
                    id="dropZone" onmouseover="this.style.borderColor='#16a34a'; this.style.background='#f0fdf4';" 
                    onmouseout="this.style.borderColor='#d1d5db'; this.style.background='#f9fafb';">
                    <i class="fas fa-file-csv" style="font-size: 2rem; color: #16a34a; margin-bottom: 0.75rem;"></i>
                    <p style="font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Choose CSV File</p>
                    <p style="font-size: 0.8rem; color: #9ca3af; margin-bottom: 1rem;">Drag and drop or click to browse</p>
                    <label id="browseLabel" style="cursor: pointer; display: inline-block; padding: 0.5rem 1.5rem; background: white; border: 2px solid #16a34a; border-radius: 8px; color: #16a34a; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease;"
                        onmouseover="this.style.background='#16a34a'; this.style.color='white';" 
                        onmouseout="this.style.background='white'; this.style.color='#16a34a';">
                        <i class="fas fa-folder-open mr-2"></i> Browse Files
                        <input type="file" id="csvFileInput" accept=".csv,.txt" style="display: none;" onchange="handleFileSelect(this); checkUploadReady();">
                    </label>
                    <div id="fileInfo2" style="display: none; margin-top: 1rem; padding: 0.75rem; background: white; border-radius: 8px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-file-csv" style="color: #16a34a; font-size: 1.25rem;"></i>
                            <div style="text-align: left; flex: 1;">
                                <p id="selectedFileName" style="font-weight: 600; color: #374151; font-size: 0.9rem;"></p>
                                <p style="color: #9ca3af; font-size: 0.8rem;">Ready to upload</p>
                            </div>
                            <i class="fas fa-check-circle" style="color: #16a34a;"></i>
                        </div>
                    </div>
                </div>
                <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.75rem; text-align: center;">
                    <i class="fas fa-info-circle mr-1"></i>Accepted: .csv, .txt | Max size: 5MB
                </p>
                <p id="uploadHint" style="font-size: 0.75rem; color: #f59e0b; margin-top: 0.5rem; text-align: center; display: ${needsCropWeek.includes(type) ? 'block' : 'none'};">
                    <i class="fas fa-exclamation-circle mr-1"></i>Please select Crop Year and Week No before uploading
                </p>
            </div>`,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-cloud-upload-alt mr-2"></i> Upload',
                cancelButtonText: '<i class="fas fa-times mr-2"></i> Cancel',
                confirmButtonColor: '#16a34a',
                customClass: {
                    popup: 'swal2-popup-modern'
                },
                didOpen: () => {
                    // Initially disable upload button for types that need crop/week
                    if (needsCropWeek.includes(type)) {
                        const confirmBtn = Swal.getConfirmButton();
                        if (confirmBtn) {
                            confirmBtn.disabled = true;
                            confirmBtn.style.opacity = '0.5';
                            confirmBtn.style.cursor = 'not-allowed';
                        }
                    }
                },
                preConfirm: () => {
                    const fileInput = document.getElementById('csvFileInput');
                    if (!fileInput || !fileInput.files.length) {
                        Swal.showValidationMessage('Please select a file to upload');
                        return false;
                    }
                    const file = fileInput.files[0];
                    if (file.size > 5242880) {
                        Swal.showValidationMessage('File size must be less than 5MB');
                        return false;
                    }

                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                    // Append crop year and week if available
                    if (needsCropWeek.includes(type)) {
                        const cropYear = document.getElementById('uploadCropYear')?.value;
                        const weekNo = document.getElementById('uploadWeekNo')?.value;
                        if (!cropYear && !weekNo) {
                            Swal.showValidationMessage('Please select Crop Year and Week No');
                            return false;
                        }
                        if (!cropYear) {
                            Swal.showValidationMessage('Please select a Crop Year');
                            return false;
                        }
                        if (!weekNo) {
                            const weekSelect = document.getElementById('uploadWeekNo');
                            const optionCount = weekSelect ? weekSelect.options.length : 0;
                            if (optionCount <= 1) {
                                Swal.showValidationMessage(
                                    'No weeks loaded for this Crop Year. Reselect the Crop Year or add weeks first.'
                                );
                            } else {
                                Swal.showValidationMessage('Please select a Week No');
                            }
                            return false;
                        }
                        formData.append('crop_year', cropYear);
                        formData.append('week_no', weekNo);
                    }

                    return fetch(UPLOAD_BASE_URL + '/' + type, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: formData
                        })
                        .then(async response => {
                            if (response.redirected) {
                                window.location.reload();
                                return;
                            }
                            const text = await response.text();
                            let data;
                            try {
                                data = text ? JSON.parse(text) : {};
                            } catch (e) {
                                throw new Error('Upload failed: server returned HTML (HTTP ' +
                                    response.status + '). URL: ' + UPLOAD_BASE_URL + '/' +
                                    type);
                            }
                            if (!response.ok) {
                                throw new Error(data.message || data.error ||
                                    ('Upload failed (HTTP ' + response.status + ')'));
                            }
                            return data;
                        })
                        .then(data => {
                            if (data && data.error) {
                                Swal.showValidationMessage(data.error);
                                return false;
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Upload Complete!',
                                text: data?.message || 'File uploaded successfully.',
                                timer: 2500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        })
                        .catch(error => {
                            Swal.showValidationMessage('Upload failed. Please try again.');
                        });
                }
            });
        }

        // Handle crop year change - load weeks
        function onUploadCropYearChange(select) {
            const cropYear = select.value;
            const weekSelect = document.getElementById('uploadWeekNo');
            const uploadHint = document.getElementById('uploadHint');

            if (!cropYear) {
                weekSelect.innerHTML = '<option value="">Select Week</option>';
                weekSelect.disabled = true;
                weekSelect.style.opacity = '0.6';
                if (uploadHint) delete uploadHint.dataset.locked;
                checkUploadReady();
                return;
            }

            weekSelect.disabled = false;
            weekSelect.style.opacity = '1';
            weekSelect.innerHTML = '<option value="">Loading weeks...</option>';
            if (uploadHint) delete uploadHint.dataset.locked;

            // Fetch weeks from WeekNo model - already sorted
            fetch(GET_WEEKS_URL + '?crop_year=' + encodeURIComponent(cropYear), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(async response => {
                    const text = await response.text();
                    let data;
                    try {
                        data = text ? JSON.parse(text) : {};
                    } catch (e) {
                        const titleMatch = text.match(/<title[^>]*>([^<]*)<\/title>/i);
                        const title = titleMatch ? titleMatch[1].trim() : '';
                        throw new Error('Server returned HTML (HTTP ' + response.status + ')' +
                            (title ? ': ' + title : '') + ' for ' + GET_WEEKS_URL);
                    }
                    if (!response.ok) {
                        throw new Error(data.message || ('Request failed (HTTP ' + response
                            .status + ') for ' + GET_WEEKS_URL));
                    }
                    return data;
                })
                .then(data => {
                    let weeks = data.weeks || [];

                    // Sort numerically
                    weeks.sort((a, b) => parseInt(a) - parseInt(b));

                    if (!weeks.length) {
                        weekSelect.innerHTML =
                            '<option value="">No weeks found for this crop year</option>';
                        if (uploadHint) {
                            uploadHint.dataset.locked = '1';
                            uploadHint.style.color = '#dc2626';
                            uploadHint.innerHTML =
                                '<i class="fas fa-exclamation-circle mr-1"></i>No weeks found for this Crop Year — add weeks first';
                        }
                    } else {
                        weekSelect.innerHTML = '<option value="">Select Week</option>';
                        weeks.forEach(week => {
                            weekSelect.innerHTML +=
                                `<option value="${week}">Week ${week}</option>`;
                        });
                    }
                    checkUploadReady();
                })
                .catch(error => {
                    console.error('Error fetching weeks:', error);
                    const msg = error && error.message ? error.message : 'network error';
                    weekSelect.innerHTML =
                        '<option value="">Error loading weeks — reselect Crop Year to retry</option>';
                    weekSelect.title = msg;
                    if (uploadHint) {
                        uploadHint.dataset.locked = '1';
                        uploadHint.style.color = '#dc2626';
                        uploadHint.innerHTML =
                            '<i class="fas fa-exclamation-circle mr-1"></i>Could not load weeks (' +
                            msg + ')';
                    }
                    checkUploadReady();
                });
        }

        // Check if all required fields are filled
        function checkUploadReady() {
            const confirmBtn = Swal.getConfirmButton();
            if (!confirmBtn) return;

            const cropYear = document.getElementById('uploadCropYear')?.value;
            const weekNo = document.getElementById('uploadWeekNo')?.value;
            const fileInput = document.getElementById('csvFileInput');
            const hasFile = fileInput && fileInput.files.length > 0;

            // Check if current modal type needs crop/week (explicit variable set by
            // openUploadModal — do NOT sniff the title text, it breaks for some types)
            const needsCropWeek = ['summary', 'trucking', 'fci', 'fuel', 'rentals', 'underload', 'transloading', 'mudpress',
                'quedan', 'molasses', 'consolidated'
            ];
            let typeNeedsCropWeek;
            if (currentUploadType) {
                typeNeedsCropWeek = needsCropWeek.includes(currentUploadType);
            } else {
                const currentType = document.querySelector('.swal2-title')?.textContent
                    ?.toLowerCase() || '';
                typeNeedsCropWeek = false;
                for (const t of needsCropWeek) {
                    if (currentType.includes(t)) {
                        typeNeedsCropWeek = true;
                        break;
                    }
                }
            }

            // Keep the hint in sync so it never looks like a stuck error:
            // amber = still missing something, green = ready.
            const hint = document.getElementById('uploadHint');
            if (hint && typeNeedsCropWeek && !hint.dataset.locked) {
                if (cropYear && weekNo && hasFile) {
                    hint.style.color = '#16a34a';
                    hint.innerHTML =
                        '<i class="fas fa-check-circle mr-1"></i>Ready to upload';
                } else {
                    const missing = [];
                    if (!cropYear) missing.push('Crop Year');
                    if (!weekNo) missing.push('Week No');
                    if (!hasFile) missing.push('CSV file');
                    hint.style.color = '#f59e0b';
                    hint.innerHTML =
                        '<i class="fas fa-exclamation-circle mr-1"></i>Please select: ' +
                        missing.join(', ');
                }
            }

            if (typeNeedsCropWeek) {
                if (cropYear && weekNo && hasFile) {
                    confirmBtn.disabled = false;
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.cursor = 'pointer';
                } else {
                    confirmBtn.disabled = true;
                    confirmBtn.style.opacity = '0.5';
                    confirmBtn.style.cursor = 'not-allowed';
                }
            } else {
                if (hasFile) {
                    confirmBtn.disabled = false;
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.cursor = 'pointer';
                } else {
                    confirmBtn.disabled = true;
                    confirmBtn.style.opacity = '0.5';
                    confirmBtn.style.cursor = 'not-allowed';
                }
            }
        }

        function openSummaryUpload() {
            openUploadModal('summary');
        }
    </script>
</body>

</html>
