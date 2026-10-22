<!-- resources/views/layouts/sidebar-items.blade.php -->
@php
    $navUser = auth()->user();
@endphp

<!-- Dashboard -->
<li>
    <a href="{{ route('dashboard') }}"
        class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('dashboard') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
        <i class="fas fa-tachometer-alt w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
        <span>Dashboard</span>
    </a>
</li>

<!-- Print Vouchers -->
@if ($navUser->hasPermission('print-vouchers'))
<li>
    <a href="{{ route('printVoucher') }}"
        class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('printVoucher') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
        <i class="fas fa-print w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
        <span>Print Vouchers</span>
    </a>
</li>
@endif

<!-- Quedan & Molasses Dropdown -->
@php
    $quedanMolassesActive = request()->routeIs(
        'prices.registry*',
        'quedan-molasses-registry*',
        'quedan-buy*',
        'molasses-buy*',
        'quedans.bulk*',
        'molasses.bulk*'
    );
@endphp
@if (\App\Support\NavigationPermissions::canSeeGroup($navUser, 'quedan-molasses'))
<li x-data="{ open: {{ $quedanMolassesActive ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
        class="group flex w-full items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ $quedanMolassesActive ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
        <i class="fas fa-qrcode w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
        <span class="flex-1 text-left">Quedan & Molasses</span>
        <i class="fas fa-chevron-down w-4 h-4 shrink-0 flex items-center justify-center transition-transform"
            :class="{ 'rotate-180': open }"></i>
    </button>
    <ul x-show="open" class="mt-1 space-y-1 ml-8">
        @if ($navUser->hasPermission('view-quedan-molasses-registry'))
        <li>
            <a href="{{ route('prices.registry') }}"
                class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('prices.registry*') || request()->routeIs('quedan-molasses-registry*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                <i class="fas fa-list-ul w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                <span>Registry List</span>
            </a>
        </li>
        @endif
        @if ($navUser->hasPermission('buy-quedan'))
        <li>
            <a href="{{ route('quedan-buy.index') }}"
                class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('quedan-buy*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                <i class="fas fa-cart-shopping w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                <span>Buy Quedan</span>
            </a>
        </li>
        @endif
        @if ($navUser->hasPermission('buy-molasses'))
        <li>
            <a href="{{ route('molasses-buy.index') }}"
                class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('molasses-buy*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                <i class="fas fa-cart-shopping w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                <span>Buy Molasses</span>
            </a>
        </li>
        @endif
    </ul>
</li>
@endif

<!-- Weekly Uploads -->
@php
    $canUpload = \App\Support\NavigationPermissions::canSeeGroup($navUser, 'uploads');
@endphp
@if ($canUpload)
    <li x-data="{ open: false }">
        <button @click="open = !open"
            class="group flex w-full items-center gap-x-3 rounded-md p-2 text-sm font-semibold text-gray-700 hover:text-primary-600 hover:bg-gray-50">
            <i class="fas fa-upload w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span class="flex-1 text-left">Weekly Uploads</span>
            <i class="fas fa-chevron-down w-4 h-4 shrink-0 flex items-center justify-center transition-transform"
                :class="{ 'rotate-180': open }"></i>
        </button>
        <ul x-show="open" class="mt-1 space-y-1 ml-8">

            @if ($navUser->hasPermission('upload-quedan'))
            <li>
                <button onclick="openUploadModal('quedan')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-qrcode w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Quedan</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-molasses'))
            <li>
                <button onclick="openUploadModal('molasses')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-flask w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Molasses Data</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-consolidated'))
            <li>
                <button onclick="openUploadModal('consolidated')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-file-import w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Consolidated Summary</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-summary'))
            <li>
                <button onclick="openUploadModal('summary')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-file-csv w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Summary</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-trucking'))
            <li>
                <button onclick="openUploadModal('trucking')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-truck w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Trucking Allowance</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-fci'))
            <li>
                <button onclick="openUploadModal('fci')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-leaf w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Fresh Cane Incentive</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-fuel'))
            <li>
                <button onclick="openUploadModal('fuel')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-gas-pump w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Fuel</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-rentals'))
            <li>
                <button onclick="openUploadModal('rentals')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-building w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Rentals</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-underload'))
            <li>
                <button onclick="openUploadModal('underload')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-weight-scale w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Underload</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-transloading'))
            <li>
                <button onclick="openUploadModal('transloading')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-exchange-alt w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Transloading</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-mudpress'))
            <li>
                <button onclick="openUploadModal('mudpress')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-industry w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Mudpress</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('upload-tls'))
            <li>
                <button onclick="openUploadModal('tls')"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-truck-moving w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>TLS Hauling</span>
                </button>
            </li>
            @endif
        </ul>
    </li>
@endif

<!-- Weekly Updates -->
@php
    $canManageSettings = \App\Support\NavigationPermissions::canSeeGroup($navUser, 'updates');
@endphp

@if ($canManageSettings)
    <li x-data="{ open: false }">
        <button @click="open = !open"
            class="group flex w-full items-center gap-x-3 rounded-md p-2 text-sm font-semibold text-gray-700 hover:text-primary-600 hover:bg-gray-50">
            <i class="fas fa-pen-to-square w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span class="flex-1 text-left">Weekly Updates</span>
            <i class="fas fa-chevron-down w-4 h-4 shrink-0 flex items-center justify-center transition-transform"
                :class="{ 'rotate-180': open }"></i>
        </button>
        <ul x-show="open" class="mt-1 space-y-1 ml-8">
            @if ($navUser->hasPermission('set-quedan-price'))
            <li>
                <button onclick="openQuedanPriceModal()"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-tag w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Quedan Price</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('set-molasses-price'))
            <li>
                <button onclick="openMolassesPriceModal()"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-flask w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Molasses Price</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('set-crop-year'))
            <li>
                <button onclick="openCropYearModal()"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-calendar-alt w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Crop Year</span>
                </button>
            </li>
            @endif
            @if ($navUser->hasPermission('set-week-number'))
            <li>
                <button onclick="openWeekNumberModal()"
                    class="group flex w-full items-center gap-x-2 rounded-md py-1.5 px-2 text-sm text-gray-600 hover:text-primary-600 hover:bg-gray-50">
                    <i class="fas fa-calendar-week w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Week Number</span>
                </button>
            </li>
            @endif
        </ul>
    </li>
@endif

<!-- Crop & Week Management -->
@if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']))
    <li>
        <a href="{{ route('crop-weeks.index') }}"
            class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('crop-weeks.*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-calendar-days w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span>Crop & Week Management</span>
        </a>
    </li>
@endif

<!-- Price Management -->
@if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
        auth()->user()->hasPermission('manage-prices'))
    <li>
        <a href="{{ route('prices.index') }}"
            class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('prices.*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-tags w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span>Price Management</span>
        </a>
    </li>
@endif

<!-- Reports -->
@php
    $canViewReports = \App\Support\NavigationPermissions::canSeeGroup($navUser, 'reports');
    $reportsActive = request()->routeIs('summaryReport', 'consolidated-report*', 'trucking-allowance-report*', 'certification-report*', 'tls-report*', 'proceeds-sheet-report*');
@endphp

@if ($canViewReports)
    <li x-data="{ open: {{ $reportsActive ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open" :aria-expanded="open.toString()"
            class="group flex w-full items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ $reportsActive ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-chart-pie w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span class="flex-1 text-left">Reports</span>
            <i class="fas fa-chevron-down w-4 h-4 shrink-0 flex items-center justify-center transition-transform"
                :class="{ 'rotate-180': open }"></i>
        </button>
        <ul x-show="open" class="mt-1 space-y-1 ml-8">

            @if ($navUser->hasPermission('view-consolidated-report'))
            <li>
                <a href="{{ route('consolidated-report') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('consolidated-report*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-file-invoice w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Consolidated Report</span>
                </a>
            </li>
            @endif
            @if ($navUser->hasPermission('view-summary-report'))
            <li>
                <a href="{{ route('summaryReport') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('summaryReport') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-chart-bar w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Summary Reports</span>
                </a>
            </li>
            @endif
            @if ($navUser->hasPermission('view-trucking-allowance-report'))
            <li>
                <a href="{{ route('trucking-allowance-report') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('trucking-allowance-report*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-truck w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Trucking Allowance</span>
                </a>
            </li>
            @endif
            @if ($navUser->hasPermission('view-certification-report'))
            <li>
                <a href="{{ route('certification-report') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('certification-report*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-certificate w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Production Certification</span>
                </a>
            </li>
            @endif
            @if ($navUser->hasPermission('view-tls-report'))
            <li>
                <a href="{{ route('tls-report') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('tls-report*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-truck w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>TLS Reports</span>
                </a>
            </li>
            @endif
            @if ($navUser->hasPermission('view-proceeds-sheet-report'))
            <li>
                <a href="{{ route('proceeds-sheet-report') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('proceeds-sheet-report*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-signature w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Proceeds Sheet</span>
                </a>
            </li>
            @endif
        </ul>
    </li>
@endif

<!-- Planter Profiles -->
@if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager', 'loan_officer']) ||
        auth()->user()->hasAnyPermission(['view-planter-profiles', 'manage-planter-profiles']))
    <li>
        <a href="{{ route('planter-profiles.index') }}"
            class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('planter-profiles.*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-address-book w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span>Planter Profiles</span>
        </a>
    </li>
@endif

<!-- Loans Dropdown -->
@php
    $canViewLoans = \App\Support\NavigationPermissions::canSeeGroup($navUser, 'loans');
@endphp

@if ($canViewLoans)
    <li x-data="{ open: {{ request()->routeIs('loans.*') ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open" :aria-expanded="open.toString()"
            class="group flex w-full items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('loans.*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-hand-holding-usd w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span class="flex-1 text-left">Loans</span>
            <i class="fas fa-chevron-down w-4 h-4 shrink-0 flex items-center justify-center transition-transform"
                :class="{ 'rotate-180': open }"></i>
        </button>
        <ul x-show="open" class="mt-1 space-y-1 ml-8">
            @if ($navUser->hasAnyPermission(['view-loans', 'create-loans', 'approve-loans', 'process-loan-payments']))
            <li>
                <a href="{{ route('loans.index') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('loans.index') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-list w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>All Loans</span>
                </a>
            </li>
            @endif
            @if ($navUser->hasPermission('view-loan-reports'))
            <li>
                <a href="{{ route('loans.reports') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('loans.reports') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-chart-bar w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>Loan Reports</span>
                </a>
            </li>
            @endif
            @if ($navUser->hasPermission('manage-loan-settings'))
                <li>
                    <a href="{{ route('loans.settings') }}"
                        class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('loans.settings') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                        <i class="fas fa-cog w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                        <span>Loan Settings</span>
                    </a>
                </li>
            @endif
        </ul>
    </li>
@endif


<!-- Cash Advances Dropdown -->
@php
    $canViewCA = \App\Support\NavigationPermissions::canSeeGroup($navUser, 'cash-advances');
@endphp

@if ($canViewCA)
    <li x-data="{ open: {{ request()->routeIs('cash-advances.*') ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open" :aria-expanded="open.toString()"
            class="group flex w-full items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('cash-advances.*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-money-bill-wave w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span class="flex-1 text-left">Cash Advances</span>
            <i class="fas fa-chevron-down w-4 h-4 shrink-0 flex items-center justify-center transition-transform"
                :class="{ 'rotate-180': open }"></i>
        </button>
        <ul x-show="open" class="mt-1 space-y-1 ml-8">
            @if ($navUser->hasAnyPermission(['view-cash-advances', 'create-cash-advances', 'approve-cash-advances', 'process-cash-advance-payments']))
            <li>
                <a href="{{ route('cash-advances.index') }}"
                    class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('cash-advances.index') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                    <i class="fas fa-list w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                    <span>All Cash Advances</span>
                </a>
            </li>
            @endif
            @if ($navUser->hasPermission('manage-cash-advance-settings'))
                <li>
                    <a href="{{ route('cash-advances.settings') }}"
                        class="group flex items-center gap-x-2 rounded-md py-1.5 px-2 text-sm {{ request()->routeIs('cash-advances.settings') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:text-primary-600 hover:bg-gray-50' }}">
                        <i class="fas fa-cog w-4 h-4 shrink-0 flex items-center justify-center text-gray-400"></i>
                        <span>CA Settings</span>
                    </a>
                </li>
            @endif
        </ul>
    </li>
@endif

<!-- Hauling Liquidity -->
@if (in_array(auth()->user()->role, ['Administrator', 'super_admin', 'manager']) ||
        auth()->user()->hasAnyPermission(['view-hauling-liquidity', 'manage-hauling-liquidity']))
    <li>
        <a href="{{ route('hauling-liquidity.index') }}"
            class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('hauling-liquidity.*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-truck-moving w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span>Hauler TLS LIQ</span>
        </a>
    </li>
@endif


<!-- User Management -->
@if (auth()->user()->role === 'Administrator' || auth()->user()->role === 'super_admin')
    <li>
        <a href="{{ route('user-management') }}"
            class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('user-management*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-users-cog w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span>User Management</span>
        </a>
    </li>
@endif

<!-- Audit Logs -->
@if (in_array(auth()->user()->role, ['Administrator', 'super_admin']) ||
        auth()->user()->hasPermission('view-audit-logs'))
    <li>
        <a href="{{ route('audit-logs.index') }}"
            class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold {{ request()->routeIs('audit-logs.*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
            <i class="fas fa-history w-5 h-5 shrink-0 flex items-center justify-center text-center"></i>
            <span>Audit Logs</span>
        </a>
    </li>
@endif
