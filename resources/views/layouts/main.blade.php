<!-- resources/views/layouts/main.blade.php -->
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#003F20">
    <title>KUSUG-PA @hasSection('title')| @yield('title')@elseif(isset($title))| {{ $title }}@endif</title>

    <!-- Apply saved theme / sidebar state before first paint (prevents flash) -->
    <script>
        (function() {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark') { // light by default; dark only when chosen via the toggle
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
        if (localStorage.getItem('theme') === 'dark') { // light by default
            document.documentElement.classList.add('dark');
        }

        /**
         * Rows of a large table, fetched from the server in batches (App\Support\TableFeed).
         * Usage: feed: kpFeed(url, () => ({ search: this.search, ... }))  then  feed.reset() / feed.more().
         * The last table row carries x-load-more="feed.more()" so scrolling to it loads the next batch.
         */
        window.kpFeed = function (url, params) {
            return {
                rows: [], page: 0, hasMore: true, loading: false, total: null, extra: {}, error: '', request: 0,
                reset() {
                    this.request++;
                    this.rows = []; this.page = 0; this.hasMore = true; this.total = null; this.extra = {}; this.error = ''; this.loading = false;
                    return this.more();
                },
                async more() {
                    if (this.loading || !this.hasMore) return;
                    const id = this.request;
                    this.loading = true;
                    try {
                        const query = new URLSearchParams();
                        Object.entries(params() || {}).forEach(([key, value]) => {
                            if (value !== '' && value !== null && value !== undefined) query.set(key, value);
                        });
                        query.set('page', this.page + 1);
                        const response = await fetch(url + (url.includes('?') ? '&' : '?') + query, { headers: { 'Accept': 'application/json' } });
                        const data = await response.json();
                        if (id !== this.request) return;
                        if (!response.ok) throw new Error(data.message || 'Could not load the records');
                        this.rows.push(...data.data);
                        this.page = data.page;
                        this.hasMore = data.has_more;
                        if (data.total !== null && data.total !== undefined) this.total = data.total;
                        if (data.extra && !Array.isArray(data.extra)) this.extra = { ...this.extra, ...data.extra };
                    } catch (e) {
                        if (id === this.request) { this.error = e.message; this.hasMore = false; }
                    } finally {
                        if (id === this.request) this.loading = false;
                    }
                },
                // Keep a loaded row in step after it was edited on the page
                replace(row, key = 'id') {
                    const i = this.rows.findIndex(r => r[key] === row[key]);
                    if (i !== -1) this.rows.splice(i, 1, row);
                },
                remove(id, key = 'id') {
                    const before = this.rows.length;
                    this.rows = this.rows.filter(r => r[key] !== id);
                    if (this.total !== null && this.rows.length < before) this.total--;
                },
            };
        };

        document.addEventListener('alpine:init', () => {
            // x-load-more="feed.more()": runs when the element scrolls into view, and again while it stays
            // in view (a short first batch), until the feed has no more rows
            Alpine.directive('load-more', (el, { expression }, { evaluateLater, cleanup }) => {
                const run = evaluateLater(expression);
                let timer = null;
                const observer = new IntersectionObserver(entries => {
                    if (!entries.some(e => e.isIntersecting)) return;
                    run();
                    clearTimeout(timer);
                    timer = setTimeout(() => { observer.unobserve(el); observer.observe(el); }, 500);
                }, { rootMargin: '0px 0px 300px 0px' });
                observer.observe(el);
                cleanup(() => { clearTimeout(timer); observer.disconnect(); });
            });
        });

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

    <dialog id="kpGlobalPdfPreview" class="kp-pdf-preview" aria-labelledby="kpGlobalPdfTitle">
        <div class="kp-pdf-preview-header">
            <div class="min-w-0">
                <h2 id="kpGlobalPdfTitle">PDF Preview</h2>
                <p id="kpGlobalPdfSub">Document preview</p>
            </div>
            <div class="kp-pdf-preview-actions">
                {{-- Large reports come in parts so each one finishes within the server's time limit --}}
                <label id="kpGlobalPdfPartWrap" class="flex items-center gap-2 text-xs" style="display: none; color: var(--kp-muted)">
                    <span class="hidden sm:inline">Large report:</span>
                    <select id="kpGlobalPdfPart" class="kp-input py-1.5 text-xs max-w-[17rem]" aria-label="Part of the report to show"></select>
                </label>
                <a id="kpGlobalPdfOpen" href="#" target="_blank" rel="noopener" class="kp-btn kp-btn-secondary" aria-label="Open PDF in a new tab">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i><span>Open in new tab</span>
                </a>
                <button id="kpGlobalPdfClose" type="button" class="kp-pdf-preview-close" aria-label="Close PDF preview">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <iframe id="kpGlobalPdfFrame" title="PDF document preview" class="kp-pdf-preview-frame"></iframe>
    </dialog>
    <script>
        (() => {
            const dialog = document.getElementById('kpGlobalPdfPreview');
            const frame = document.getElementById('kpGlobalPdfFrame');
            const title = document.getElementById('kpGlobalPdfTitle');
            const openLink = document.getElementById('kpGlobalPdfOpen');
            const sub = document.getElementById('kpGlobalPdfSub');
            const partWrap = document.getElementById('kpGlobalPdfPartWrap');
            const partSelect = document.getElementById('kpGlobalPdfPart');
            // URL paths of the reports that can be split into parts (App\Support\PdfParts)
            const partPaths = @json(\App\Support\PdfParts::pathPatterns()).map(p => new RegExp(p));
            let returnFocus = null;
            let baseUrl = null;
            let openId = 0;

            const show = url => {
                openLink.href = url.toString();
                frame.src = url.toString();
            };
            const showPart = part => {
                const url = new URL(baseUrl);
                url.searchParams.set('part', part);
                show(url);
            };
            partSelect.addEventListener('change', () => showPart(partSelect.value));

            window.KusugpaPdfPreview = {
                async open(source, label = 'PDF Preview', trigger = document.activeElement) {
                    if (!source) return;
                    const id = ++openId;
                    const url = new URL(source, window.location.href);
                    url.searchParams.set('preview', '1');
                    url.searchParams.delete('part');
                    baseUrl = url;
                    title.textContent = label;
                    frame.title = label + ' PDF preview';
                    sub.textContent = 'Document preview';
                    partWrap.style.display = 'none';
                    returnFocus = trigger;
                    if (!dialog.open) dialog.showModal();
                    document.getElementById('kpGlobalPdfClose').focus();

                    if (!partPaths.some(re => re.test(url.pathname))) {
                        show(url);
                        return;
                    }
                    // Ask how many parts first; a report small enough loads whole
                    sub.textContent = 'Preparing document…';
                    frame.removeAttribute('src');
                    try {
                        const infoUrl = new URL(url);
                        infoUrl.searchParams.set('pdf_parts', '1');
                        const response = await fetch(infoUrl, { headers: { 'Accept': 'application/json' } });
                        const info = response.ok ? await response.json() : null;
                        if (id !== openId) return;
                        if (info && info.parts > 1) {
                            partSelect.innerHTML = '';
                            info.labels.forEach((text, i) => partSelect.add(new Option(text, i + 1)));
                            partWrap.style.display = '';
                            sub.textContent = 'Split into ' + info.parts + ' parts of up to ' + info.size + ' ' + info.unit
                                + ' so each loads quickly. Print each part.';
                            showPart(1);
                            return;
                        }
                    } catch (e) {
                        // Fall back to the whole document
                    }
                    if (id !== openId) return;
                    sub.textContent = 'Document preview';
                    show(url);
                },
                // Download a report; one too large for one request opens in the preview, split into parts
                async download(source, label = 'PDF Preview') {
                    if (!source) return;
                    const url = new URL(source, window.location.href);
                    url.searchParams.delete('part');
                    if (partPaths.some(re => re.test(url.pathname))) {
                        try {
                            const infoUrl = new URL(url);
                            infoUrl.searchParams.set('pdf_parts', '1');
                            const response = await fetch(infoUrl, { headers: { 'Accept': 'application/json' } });
                            const info = response.ok ? await response.json() : null;
                            if (info && info.parts > 1) {
                                await this.open(url.toString(), label);
                                if (window.Swal) {
                                    Swal.fire({
                                        toast: true, position: 'top-end', icon: 'info', timer: 6000, showConfirmButton: false,
                                        title: 'Large report: ' + info.parts + ' parts',
                                        text: 'Choose each part at the top of the preview and save it with the viewer\'s download button.',
                                    });
                                }
                                return;
                            }
                        } catch (e) {
                            // Fall through to a direct download
                        }
                    }
                    url.searchParams.set('download', '1');
                    window.location.assign(url.toString());
                },
                close() { if (dialog.open) dialog.close(); }
            };

            document.getElementById('kpGlobalPdfClose').addEventListener('click', () => dialog.close());
            dialog.addEventListener('close', () => {
                openId++; // a parts request still on its way is dropped
                partWrap.style.display = 'none';
                frame.removeAttribute('src');
                if (returnFocus && returnFocus.isConnected) returnFocus.focus();
                returnFocus = null;
            });
            document.addEventListener('click', event => {
                const link = event.target.closest('a[data-pdf-preview]');
                if (!link) return;
                event.preventDefault();
                window.KusugpaPdfPreview.open(link.href, link.dataset.pdfPreview || 'PDF Preview', link);
            });
            document.addEventListener('submit', event => {
                const form = event.target;
                if (!form.matches('form[data-pdf-preview]')) return;
                event.preventDefault();
                const url = new URL(form.action, window.location.href);
                new FormData(form).forEach((value, key) => url.searchParams.append(key, value));
                window.KusugpaPdfPreview.open(url.toString(), form.dataset.pdfPreview || 'PDF Preview', event.submitter || form);
            });
        })();
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
        const UPLOAD_TEMPLATE_BASE_URL = '{{ url('/upload') }}';
        // Tracks which upload modal is open. Used by checkUploadReady() instead of
        // guessing the type from the modal title text (fragile).
        let currentUploadType = '';

        @include('layouts.partials.weekly-updates-script')

        function openUploadModal(type) {
            currentUploadType = type;
            const titles = {
                'summary': 'Upload Summary',
                'trucking': 'Upload Trucking Allowance',
                'fci': 'Upload Fresh Cane Incentive',
                'fuel': 'Upload Fuel',
                'rentals': 'Upload Rentals',
                'underload': 'Upload Underload',
                'transloading': 'Upload Transloading',
                'mudpress': 'Upload Mudpress',
                'consolidated': 'Upload Consolidated Summary',
                'quedan': 'Upload Quedan',
                'molasses': 'Upload Molasses',
                'tls': 'Upload TLS Hauling',
            };
            // Types that need crop_year and week_no selection
            const needsCropWeek = ['summary', 'trucking', 'fci', 'fuel', 'rentals', 'underload', 'transloading', 'mudpress',
                'quedan', 'molasses', 'consolidated', 'tls'
            ];
            const templateUrl = UPLOAD_TEMPLATE_BASE_URL + '/' + encodeURIComponent(type) + '/template';

            Swal.fire({
                title: titles[type] || 'Upload CSV',
                html: `
                    <div class="kp-upload-toolbar">
                        <p>${type === 'tls' ? 'Each plate no. per planter becomes a Hauler TLS LIQ entry. ' : ''}Use the CSV template columns; the header row is optional.</p>
                        <a href="${templateUrl}" download><i class="fas fa-download" aria-hidden="true"></i> Download template</a>
                    </div>
                    ${needsCropWeek.includes(type) ? `
                        <div class="kp-upload-fields">
                            <div>
                                <label for="uploadCropYear">Crop Year <span aria-hidden="true">*</span></label>
                                <select id="uploadCropYear" onchange="onUploadCropYearChange(this)">
                                    <option value="">Select crop year</option>
                                    @foreach (App\Models\CropYear::orderBy('crop_year', 'desc')->pluck('crop_year') as $cy)
                                        <option value="{{ $cy }}">{{ $cy }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="uploadWeekNo">Week No <span aria-hidden="true">*</span></label>
                                <select id="uploadWeekNo" onchange="checkUploadReady()" disabled>
                                    <option value="">Select week</option>
                                </select>
                            </div>
                        </div>` : ''}
                    <div class="kp-upload-file">
                        <label for="csvFileInput">CSV file <span>.csv or .txt · up to 5 MB</span></label>
                        <input type="file" id="csvFileInput" accept=".csv,.txt" onchange="checkUploadReady();">
                    </div>
                    <p id="uploadHint" class="kp-upload-hint" style="display: ${needsCropWeek.includes(type) ? 'block' : 'none'};">
                        <i class="fas fa-circle-info" aria-hidden="true"></i> Select Crop Year, Week No, and a CSV file.
                    </p>`,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-cloud-upload-alt mr-2"></i> Upload',
                cancelButtonText: '<i class="fas fa-times mr-2"></i> Cancel',
                confirmButtonColor: '#16a34a',
                customClass: {
                    popup: 'kp-upload-popup',
                    title: 'kp-upload-title',
                    htmlContainer: 'kp-upload-content',
                    actions: 'kp-upload-actions',
                    confirmButton: 'kp-upload-confirm',
                    cancelButton: 'kp-upload-cancel'
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
                            // TLS results list the liquidation entries created, so keep them open until OK
                            const keepOpen = data && data.liquidations != null;
                            Swal.fire({
                                icon: 'success',
                                title: 'Upload Complete!',
                                text: data?.message || 'File uploaded successfully.',
                                timer: keepOpen ? undefined : 2500,
                                showConfirmButton: keepOpen,
                                confirmButtonColor: '#16a34a'
                            }).then(() => {
                                window.location.reload();
                            });
                        })
                        .catch(error => {
                            Swal.showValidationMessage(error && error.message ? error.message : 'Upload failed. Please try again.');
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
                'quedan', 'molasses', 'consolidated', 'tls'
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
