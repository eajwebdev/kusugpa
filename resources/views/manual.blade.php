@php
    $modules = [
        ['id' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'fa-chart-line', 'route' => 'dashboard', 'purpose' => 'Review the selected period, then check Balance Health before using its figures.', 'steps' => ['Sign in and open Dashboard from the left menu.', 'Choose the crop year and optional week at the top of the page.', 'Check the Balance Check tab first for red or amber findings, then open the other tabs to understand the figures.']],
        ['id' => 'weekly-setup', 'name' => 'Crop years, weeks & prices', 'icon' => 'fa-calendar-days', 'route' => 'crop-weeks.index', 'purpose' => 'Set up the period and prices used by weekly work.', 'steps' => ['Open Weekly Updates to add a Crop Year, Week Number, Quedan Price, or Molasses Price.', 'Choose the correct crop year before adding a week or price.', 'Use Crop & Week Management or Price Management to review existing entries.']],
        ['id' => 'crop-management', 'name' => 'Crop & Week Management', 'icon' => 'fa-calendar-week', 'route' => 'crop-weeks.index', 'purpose' => 'Review and maintain the crop year calendar.', 'steps' => ['Open Crop & Week Management.', 'Choose the crop year to review its weeks.', 'Add or correct a period only after checking existing entries.']],
        ['id' => 'price-management', 'name' => 'Price Management', 'icon' => 'fa-tags', 'route' => 'prices.index', 'purpose' => 'Review the prices used for quedan and molasses.', 'steps' => ['Open Price Management.', 'Find the crop year or period you need.', 'Review the price before changing it; saved prices affect later work.']],
        ['id' => 'registry', 'name' => 'Quedan & Molasses', 'icon' => 'fa-list-ul', 'route' => 'prices.registry', 'purpose' => 'Review the registry and record purchases.', 'steps' => ['Open Quedan & Molasses, then Registry List to find a record.', 'Use the search and filters to find the right planter or period.', 'Open Buy Quedan or Buy Molasses when recording purchases; check the details before saving.']],
        ['id' => 'buy-quedan', 'name' => 'Buy Quedan', 'icon' => 'fa-cart-shopping', 'route' => 'quedan-buy.index', 'purpose' => 'Mark selected sugar quedans as purchased after settlement.', 'steps' => []],
        ['id' => 'buy-molasses', 'name' => 'Buy Molasses', 'icon' => 'fa-flask', 'route' => 'molasses-buy.index', 'purpose' => 'Mark selected molasses allocations as purchased after settlement.', 'steps' => []],
        ['id' => 'profiles', 'name' => 'Planter Profiles', 'icon' => 'fa-address-book', 'route' => 'planter-profiles.index', 'purpose' => 'Find and maintain planter information.', 'steps' => ['Open Planter Profiles and search for a name or planter code.', 'Open a profile to inspect its details and history.', 'If you have permission, add or update a profile, or use Sync Planters to refresh the roster.']],
        ['id' => 'loans', 'name' => 'Loans', 'icon' => 'fa-hand-holding-dollar-sign', 'route' => 'loans.index', 'purpose' => 'Manage loan requests, payments, attachments, and statements.', 'steps' => ['Open Loans → All Loans and use Create Loan for a new request.', 'Review the loan details and attachments. Authorized staff can approve or reject it, then activate an approved loan.', 'Record payments on the loan page. Open Loan Reports for statements, schedules, and summaries.']],
        ['id' => 'cash-advances', 'name' => 'Cash Advances', 'icon' => 'fa-money-bill-wave', 'route' => 'cash-advances.index', 'purpose' => 'Track advances and their repayments.', 'steps' => ['Open Cash Advances → All Cash Advances and select Create for a new advance.', 'Check the details. Authorized staff can approve, reject, or activate the advance.', 'Record payments on its detail page. Open the receipt PDF from the selected advance when needed.']],
        ['id' => 'deductions', 'name' => 'Fuel & Others Deduction', 'icon' => 'fa-gas-pump', 'route' => 'planter-deductions.index', 'purpose' => 'Record planter deductions for the correct week.', 'steps' => ['Open Fuel & Others Deduction.', 'Select the planter and weekly period, then enter the deduction details.', 'Review the saved row. These entries can appear in account and voucher reports.']],
        ['id' => 'hauling', 'name' => 'Hauler TLS LIQ', 'icon' => 'fa-truck-moving', 'route' => 'hauling-liquidity.index', 'purpose' => 'Manage haulers and hauling liquidation entries.', 'steps' => ['Open Hauler TLS LIQ and choose a hauler.', 'Review the weekly entries imported from TLS hauling or add an entry as permitted.', 'Check the payee and amounts, then open a slip PDF or a weekly set of slips.']],
        ['id' => 'vouchers', 'name' => 'Print Vouchers', 'icon' => 'fa-print', 'route' => 'printVoucher', 'purpose' => 'Prepare printable voucher ledgers.', 'steps' => ['Open Print Vouchers.', 'Select Crop Year, Week From, and Week To. Optionally choose specific planters; leaving the list empty uses all planters.', 'Press Generate Voucher to open the PDF preview, then download or print from the viewer.']],
        ['id' => 'account', 'name' => 'My account', 'icon' => 'fa-user-gear', 'route' => 'account.password.edit', 'purpose' => 'Keep your own sign-in details current.', 'steps' => ['Open the account menu at the bottom of the left sidebar.', 'Choose Change password or Google sign-in.', 'Follow the on-screen instructions and sign in again if prompted.']],
        ['id' => 'users', 'name' => 'User Management', 'icon' => 'fa-users-gear', 'route' => 'user-management', 'purpose' => 'Manage staff accounts and access.', 'steps' => ['Administrators open User Management.', 'Find or add a staff member, then review their role and permissions.', 'Save changes and ask the user to sign in again if their access has changed.']],
        ['id' => 'audit', 'name' => 'Audit Logs', 'icon' => 'fa-clock-rotate-left', 'route' => 'audit-logs.index', 'purpose' => 'Review actions recorded by the system.', 'steps' => ['Open Audit Logs or My Audit Logs from the menu.', 'Use the available filters to find the activity you need.', 'Open a log entry for details; authorized staff can export a PDF.']],
        ['id' => 'system-panel', 'name' => 'System Panel', 'icon' => 'fa-shield-halved', 'route' => 'admin.dashboard', 'purpose' => 'Administrator tools for system settings and maintenance.', 'steps' => ['Administrators open the account menu and choose System Panel.', 'Review the setting or maintenance task before changing anything.', 'Use the available backup and system controls only when you are responsible for them.']],
    ];

    $moduleWalkthroughs = [
        'dashboard' => ['Sign in and choose Dashboard in the left menu.', 'Choose the crop year and, if needed, the week in the bar above the tabs.', 'Open Balance Check before relying on production or payment figures. Review red findings first, then amber findings.', 'Use Overview, Production, Loans & Advances, Proceeds, and Hauling to examine the area behind a figure.'],
        'weekly-setup' => ['Open Weekly Updates in the left menu and choose Crop Year, Week Number, Quedan Price, or Molasses Price.', 'In the dialog, enter the season or week details shown. The pictured Add Crop Year dialog expects two four-digit years together.', 'Press the dialog’s Add or Save button only after checking the selected period.', 'Open Crop & Week Management or Price Management to confirm the new setup is listed.'],
        'crop-management' => ['Open Crop & Week Management to see crop years on the left and week numbers on the right.', 'Use the All Crop Years selector above Week Numbers to focus on one season.', 'Check each week’s start and end dates before uploading data for that week.', 'If a season or week is missing, open Weekly Updates and add it, then return to verify it appears here.'],
        'price-management' => ['Open Price Management and choose the crop year in the Quedan Prices or Molasses Prices panel.', 'Review the week, class or type, and rate shown in each list.', 'Use Price Registry to inspect the related transaction records.', 'If a weekly rate is missing, use Weekly Updates to add the correct Quedan Price or Molasses Price before preparing vouchers.'],
        'registry' => ['Open Quedan & Molasses → Registry List.', 'Choose Quedan Registry or Molasses Registry, then select Crop Year and Week.', 'Use the search field to locate the planter, code, QDN, or molasses record.', 'Click Preview Quedan PDF when you need a printable registry. Confirm the period and rows in the PDF viewer.'],
        'buy-quedan' => ['Open Quedan & Molasses → Buy Quedan.', 'Select Crop Year first, then optionally Week No. Use Search to narrow the pending records.', 'Tick the checkbox beside each verified QDN, or the header checkbox for every row currently shown.', 'Click Mark as Bought in the selection bar, then check the Bought count and registry status.'],
        'buy-molasses' => ['Open Quedan & Molasses → Buy Molasses.', 'Select Crop Year and, if needed, Week No. Search by planter, code, or MC number.', 'Tick only the molasses allocations confirmed as purchased.', 'Click Mark as Bought in the selection bar and confirm their status in the registry.'],
        'profiles' => ['Open Planter Profiles and search by planter name or code.', 'Choose a status filter if you need only active or inactive members.', 'Open a planter row to review its profile and production history.', 'For a new member, click Add Planter and complete the form. Use Sync when the roster should be refreshed from source records.'],
        'loans' => ['Open Loans → All Loans and click New Loan.', 'Choose the planter and loan type, then enter principal, interest, and schedule details requested by the form.', 'Submit the request. An authorized reviewer approves it before activation.', 'Open the loan to check its amortization and record received payments. Use Reports for the schedule, statement, and portfolio PDFs.'],
        'cash-advances' => ['Open Cash Advances → All Cash Advances and click New Cash Advance.', 'Select the planter and enter the amount, crop year, repayment weeks, and other required terms.', 'Submit the request and have an authorized reviewer approve and activate it.', 'Open the advance to review its weekly deductions, record received payments, and generate its receipt when needed.'],
        'deductions' => ['Open Fuel & Others Deduction and choose Fuel or Others at the top.', 'Click New Fuel Deduction or the matching new deduction button for the selected tab.', 'Select the planter, crop year, deduction week, amount, and reference or description requested by the form.', 'Save and confirm the deduction appears under the right tab and week. It can flow into vouchers and account reports.'],
        'hauling' => ['Open Hauler TLS LIQ and find the hauler by name or status.', 'If a hauler is not listed, click Add Hauler and enter its details.', 'Open the hauler to inspect TLS-linked weekly entries, then add a liquidation entry when permitted.', 'Check the payee, tonnage, rate, deductions, and calculated net amount before generating a slip or signature sheet.'],
        'vouchers' => ['Open Print Vouchers.', 'Select Crop Year, Week From, and Week To. Choose specific planters under Planter Scope, or leave it at All Planters.', 'Review Balance Check when a weekly amount or deduction looks wrong.', 'Click Generate Voucher, then check the planter, period, gross proceeds, deductions, and net amount in the PDF preview before printing.'],
        'account' => ['Open the account menu at the bottom of the left sidebar and choose Change password.', 'Enter the current password and a new password that meets every listed requirement.', 'Repeat the new password in Confirm new password, then submit the form.', 'Use the account menu’s Google sign-in option if your administrator has enabled it.'],
        'users' => ['Administrators open User Management and use Search to find an existing operator.', 'Click Add User to create an account.', 'Enter the staff details, choose the role, and select the permissions needed for their job.', 'Save the user and confirm the account appears in the list. Ask them to sign in with their assigned credentials.'],
        'audit' => ['Open Audit Logs or My Audit Logs from the menu.', 'Use Search, Module, Action, Crop Year, Week, and date filters to narrow the activity.', 'Review the timestamp, user, action, and description in the results.', 'Click Preview PDF when you need a printable copy of the filtered log.'],
        'system-panel' => ['Administrators open the account menu and choose System Panel.', 'Review the status cards and choose the relevant administrative area, such as Settings, User Management, or Activity Compliance Logs.', 'Use the Settings button for system parameters and maintenance controls.', 'For a backup, use Create Snapshot Backup only as part of the administrator’s normal backup procedure.'],
    ];

    $moduleTargets = [
        'dashboard' => [['x' => 1013, 'y' => 361, 'label' => 'Balance Check']],
        'weekly-setup' => [['x' => 780, 'y' => 494, 'label' => 'Add crop year']],
        'crop-management' => [['x' => 1156, 'y' => 261, 'label' => 'Choose crop year']],
        'price-management' => [['x' => 1254, 'y' => 151, 'label' => 'Price Registry']],
        'registry' => [['x' => 1227, 'y' => 151, 'label' => 'Preview PDF'], ['x' => 610, 'y' => 245, 'label' => 'Molasses tab']],
        'buy-quedan' => [['x' => 380, 'y' => 270, 'label' => 'Crop Year'], ['x' => 320, 'y' => 346, 'label' => 'Select rows']],
        'buy-molasses' => [['x' => 380, 'y' => 270, 'label' => 'Crop Year'], ['x' => 320, 'y' => 346, 'label' => 'Select rows']],
        'profiles' => [['x' => 995, 'y' => 151, 'label' => 'Add Planter']],
        'loans' => [['x' => 1022, 'y' => 151, 'label' => 'New Loan']],
        'cash-advances' => [['x' => 1103, 'y' => 151, 'label' => 'New Cash Advance']],
        'deductions' => [['x' => 1236, 'y' => 151, 'label' => 'New deduction']],
        'hauling' => [['x' => 1254, 'y' => 151, 'label' => 'Add Hauler']],
        'vouchers' => [['x' => 1206, 'y' => 417, 'label' => 'Generate Voucher']],
        'account' => [['x' => 800, 'y' => 461, 'label' => 'New password']],
        'users' => [['x' => 1263, 'y' => 151, 'label' => 'Add User']],
        'audit' => [['x' => 1251, 'y' => 151, 'label' => 'Preview PDF']],
        'system-panel' => [['x' => 1271, 'y' => 151, 'label' => 'Settings']],
    ];

    $reportTargets = [
        [['x' => 1178, 'y' => 322, 'label' => 'Preview']],
        [['x' => 608, 'y' => 202, 'label' => 'Preview PDF']],
        [['x' => 1250, 'y' => 151, 'label' => 'Preview PDF']],
        [['x' => 1217, 'y' => 344, 'label' => 'Preview Report']],
        [['x' => 1192, 'y' => 397, 'label' => 'Generate Certification']],
        [['x' => 1216, 'y' => 517, 'label' => 'Preview Sheet']],
        [['x' => 1218, 'y' => 482, 'label' => 'Preview Sheet']],
        [['x' => 1225, 'y' => 418, 'label' => 'Preview Ledger']],
        [['x' => 1225, 'y' => 418, 'label' => 'Preview Report']],
        [['x' => 458, 'y' => 419, 'label' => 'Generate PDF']],
    ];

    $reports = [
        ['name' => 'Summary Reports', 'route' => 'summaryReport', 'select' => 'Crop Year, then optional Week From and Week To', 'action' => 'Preview or Export', 'check' => 'the crop year, week range, and summary totals', 'note' => 'A crop year is required for the PDF. Preview opens it; Export downloads it.'],
        ['name' => 'Consolidated Report', 'route' => 'consolidated-report', 'select' => 'Crop Year, Week From, Week To, and optional Search Planter', 'action' => 'Preview PDF', 'check' => 'the planter rows and selected period', 'note' => 'The same filters and planter search are sent to the PDF.'],
        ['name' => 'Trucking Allowance', 'route' => 'trucking-allowance-report', 'select' => 'Crop Year, Week From, Week To, and optional Search Planter', 'action' => 'Preview PDF', 'check' => 'the planter and allowance amounts', 'note' => 'Use the table to review the records before opening the PDF.'],
        ['name' => 'TLS Reports', 'route' => 'tls-report', 'select' => 'Crop Year, Week From, Week To, and optional Station or Association', 'action' => 'Preview Report or Download', 'check' => 'the selected station, association, and ticket totals', 'note' => 'The PDF buttons are enabled only when matching tickets exist.'],
        ['name' => 'Production Certification', 'route' => 'certification-report', 'select' => 'Crop Year, week range, Date Issued, and one or more planters', 'action' => 'Generate Certification or Download', 'check' => 'the selected planters, production figures, date, and signatories', 'note' => 'The button stays disabled until at least one planter is selected. Save signatories first if they need updating.'],
        ['name' => 'Proceeds Sheet', 'route' => 'proceeds-sheet-report', 'select' => 'Crop Year, Week From, Week To, optional planters, Starting Check No., and Date Issued', 'action' => 'Preview Sheet', 'check' => 'the planter list, check numbers, and issue date', 'note' => 'Leave the planter selection empty for all planters. Leave Starting Check No. blank if numbers will be written by hand.'],
        ['name' => 'Hauler TLS Signature', 'route' => 'hauler-signature-report', 'select' => 'Crop Year, Week From, Week To, Hauler Location, and Date Issued', 'action' => 'Preview Sheet', 'check' => 'hauler names, plate numbers, deductions, net amounts, and signature lines', 'note' => 'Amounts come from Hauler TLS LIQ entries; each selected location starts on its own page.'],
        ['name' => 'Planter Accnt. Applied', 'route' => 'planter-accounts-report', 'select' => 'Crop Year, Week From, Week To, and Date Issued', 'action' => 'Preview Ledger', 'check' => 'loan, cash advance, fuel, and other amounts applied to each planter', 'note' => 'The ledger uses the same scheduled deductions as the Check Voucher.'],
        ['name' => 'Proceeds & Deductions', 'route' => 'proceeds-deductions-report', 'select' => 'Crop Year, Week No., and optional Date Issued', 'action' => 'Preview Report', 'check' => 'gross proceeds, deductions, accounts applied, and net paid', 'note' => 'This report is prepared for landscape legal paper.'],
        ['name' => 'Loan Reports', 'route' => 'loans.reports', 'select' => 'a loan for an individual PDF, or a crop year with week range or month for Monthly Loan Collections', 'action' => 'the matching Generate PDF button', 'check' => 'the loan name, schedule or period, and balance', 'note' => 'The page includes amortization, disclosure, SOA, monthly collections, and active portfolio reports. A week range takes priority over the month.'],
    ];

    $dashboardTabs = [
        ['key' => 'overview', 'name' => 'Overview', 'icon' => 'fa-chart-pie', 'shows' => 'Main production and value figures, price cards, charts, Modules at a Glance, and the Collections Tracker when you have finance access.', 'use' => 'Start here to see the overall picture, then open the tab behind any number that needs checking.'],
        ['key' => 'production', 'name' => 'Production', 'icon' => 'fa-seedling', 'shows' => 'Net sugar, molasses, cane weight, sugar yield, weekly charts, allowances, registry status, and top planters.', 'use' => 'Use this to confirm that weekly uploads and registry records match what was milled.'],
        ['key' => 'finance', 'name' => 'Loans & Advances', 'icon' => 'fa-hand-holding-dollar-sign', 'shows' => 'The Collections Tracker, outstanding loan and cash advance balances, scheduled versus collected amounts, account statuses, deductions, and upcoming dues.', 'use' => 'Use this to see what is still owed. The Collections Tracker covers open accounts from every crop year.'],
        ['key' => 'proceeds', 'name' => 'Proceeds', 'icon' => 'fa-money-check', 'shows' => 'Gross proceeds, deductions, accounts applied, net paid to planters, breakdowns, and any negative net paid.', 'use' => 'Check this before vouchers. A negative net paid amount needs attention in Balance Check.'],
        ['key' => 'hauling', 'name' => 'Hauling', 'icon' => 'fa-truck', 'shows' => 'TLS tickets, liquidations, net amount to haulers, items needing attention, tonnage comparison, trucks, and deductions.', 'use' => 'Use this to find tickets that have not become liquidations or entries missing payee or rate.'],
        ['key' => 'balance', 'name' => 'Balance Check', 'icon' => 'fa-scale-balanced', 'shows' => 'Balance Health, the module flow, and detailed checks between uploads, prices, registries, vouchers, receivables, and hauling.', 'use' => 'Review this first whenever a report or payout looks wrong. Open each red or amber check to see the affected records and its link to the source module.'],
    ];

    $balanceChecks = [
        ['name' => 'Uploaded weeks are set up', 'meaning' => 'An uploaded week is missing from Crop & Week Management or has no week end date.', 'action' => 'Add or correct the crop year, week, and end date.', 'route' => 'crop-weeks.index'],
        ['name' => 'Every delivered week has a price', 'meaning' => 'Quedan or molasses was delivered in a week with no positive price.', 'action' => 'Set the correct week price; a missing price can make voucher gross proceeds ₱0.00.', 'route' => 'prices.index'],
        ['name' => 'Quedan registry ↔ Consolidated summary', 'meaning' => 'A planter and week appear in one source but not the other.', 'action' => 'Compare both uploads for the listed planter and week; correct the missing or wrong source record.', 'route' => 'consolidated-report'],
        ['name' => 'Quedan ↔ Molasses uploads', 'meaning' => 'The planter and week lists in the two milling files differ.', 'action' => 'Check the registry and the original upload files for the listed planter and week.', 'route' => 'prices.registry'],
        ['name' => 'Trucking allowance ↔ Consolidated cane weight', 'meaning' => 'Trucking net cane and consolidated TA weight differ for the same planter and week beyond the allowed rounding.', 'action' => 'Compare the two source figures and correct the wrong upload.', 'route' => 'trucking-allowance-report'],
        ['name' => 'Registry records bought', 'meaning' => 'Quedan or molasses records are still pending purchase.', 'action' => 'Open Buy Quedan or Buy Molasses and process only the records ready for purchase. This is a note, not automatically an error.', 'route' => 'quedan-buy.index'],
        ['name' => 'Planters have a profile', 'meaning' => 'A planter code in uploads or accounts has no matching Planter Profile.', 'action' => 'Use Sync Planters or correct the code in the source record.', 'route' => 'planter-profiles.index'],
        ['name' => 'Inactive planters with deliveries', 'meaning' => 'An inactive or suspended profile has deliveries in the selected period.', 'action' => 'Review the profile status and the delivery upload before changing either.', 'route' => 'planter-profiles.index'],
        ['name' => 'Accounts applied fit within net proceeds', 'meaning' => 'Loan, advance, fuel, and other amounts applied exceed a planter’s net proceeds, producing negative net paid.', 'action' => 'Review Proceeds & Deductions and the account schedules; have authorized staff move an amount to a later week before paying.', 'route' => 'proceeds-deductions-report'],
        ['name' => 'Scheduled deductions have proceeds to come from', 'meaning' => 'A cash advance, fuel, or other deduction is scheduled in a delivered week with no matching planter delivery.', 'action' => 'Check the planter code, delivery upload, and deduction schedule. Correct the source or reschedule the deduction.', 'route' => 'cash-advances.index'],
        ['name' => 'Cash advance schedule ↔ payments', 'meaning' => 'A cash advance week is past due and its payment is not recorded in full.', 'action' => 'Check whether payment was received but not entered, or follow up on the unpaid amount.', 'route' => 'cash-advances.index'],
        ['name' => 'Cash advance balance ↔ schedule', 'meaning' => 'The stored balance does not match the weekly schedule less payments, or the schedule does not add up to amount plus interest.', 'action' => 'Compare the advance, schedule, and payments. Correct the source entries; re-saving a correct schedule recalculates it.', 'route' => 'cash-advances.index'],
        ['name' => 'Loan balance ↔ amortization & payments', 'meaning' => 'A loan has no schedule, a balance difference, or a payment recorded for a month outside its schedule.', 'action' => 'Open the loan and compare its amortization months and payments before correcting the entry.', 'route' => 'loans.index'],
        ['name' => 'TLS tickets ↔ Hauling liquidations', 'meaning' => 'TLS tickets have no linked liquidation, or liquidated tonnage differs from linked tickets.', 'action' => 'Check the uploaded tickets and hauling entries for the listed week.', 'route' => 'hauling-liquidity.index'],
        ['name' => 'Liquidation entries are complete', 'meaning' => 'A hauling entry has no payee or a rate per ton of zero or less.', 'action' => 'Select the correct payee and rate in Hauler TLS LIQ, then review the recalculated amount.', 'route' => 'hauling-liquidity.index'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A simple step-by-step guide to the KUSUG-PA planter portal, its modules, and reports.">
    <meta name="theme-color" content="#073d27">
    <title>User Guide | KUSUG-PA</title>
    <link rel="icon" href="{{ asset('img/logo-64.png') }}">
    <link rel="stylesheet" href="{{ asset('template/plugins/fontawesome-free-v6/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/manual.css') }}?v={{ @filemtime(public_path('css/manual.css')) }}">
    <script defer src="{{ asset('js/manual.js') }}?v={{ @filemtime(public_path('js/manual.js')) }}"></script>
</head>
<body>
    <a class="skip-link" href="#main">Skip to guide</a>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="{{ url('/') }}" aria-label="KUSUG-PA home"><img src="{{ asset('img/logo-64.webp') }}" alt="" width="42" height="42"><span><strong>KUSUG-PA</strong><small>PORTAL USER GUIDE</small></span></a>
            <nav class="header-nav" aria-label="Guide navigation"><a href="#dashboard-guide">Dashboard</a><a href="#balance-guide">Balance Health</a><a href="#modules">Modules</a><a href="#reports">Reports</a><a href="#help">Help</a></nav>
            <a class="header-cta" href="{{ auth()->check() ? route('dashboard') : route('getLogin') }}">{{ auth()->check() ? 'Open dashboard' : 'Sign in' }} <span aria-hidden="true">↗</span></a>
        </div>
    </header>

    <main id="main">
        <section class="hero">
            <div class="container hero-grid">
                <div>
                    <p class="eyebrow"><span class="eyebrow-line"></span> The KUSUG-PA portal, made simple</p>
                    <h1>A clearer guide to your daily work.</h1>
                    <p class="hero-copy">Learn what each dashboard tab means, check Balance Health before a payout, then follow the steps for each module and report.</p>
                    <div class="hero-actions"><a class="button button-gold" href="#balance-guide">Understand Balance Health <span aria-hidden="true">→</span></a><a class="button button-outline" href="#modules">Browse the modules</a></div>
                    <p class="hero-note"><span aria-hidden="true">●</span> What you see depends on your account permissions.</p>
                </div>
                <aside class="hero-priority" aria-labelledby="priority-title">
                    <span class="priority-label"><i class="fas fa-scale-balanced" aria-hidden="true"></i> CHECK FIRST</span>
                    <h2 id="priority-title">Is the period in balance?</h2>
                    <p>The Balance Check tab compares the records used by vouchers, receivables, production, and hauling.</p>
                    <ul><li><strong>Red:</strong> an amount or required setup is off balance.</li><li><strong>Amber:</strong> records need review.</li><li><strong>Blue note:</strong> information or a check that could not run.</li></ul>
                    <a href="{{ route('dashboard') }}#balance">Open Balance Check <span aria-hidden="true">↗</span></a>
                </aside>
            </div>
        </section>

        <section class="intro-strip"><div class="container strip-grid"><div><strong>01</strong><span>Set the crop year and week</span></div><div><strong>02</strong><span>Upload or enter records</span></div><div><strong>03</strong><span>Review the results</span></div><div><strong>04</strong><span>Preview and print reports</span></div></div></section>

        <section class="section dashboard-guide" id="dashboard-guide"><div class="container">
            <div class="section-heading"><p class="kicker">DASHBOARD GUIDE</p><h2>What each dashboard tab tells you</h2><p>Open Dashboard from the left menu. Choose the crop year and, if needed, a week at the top. The figures update for that selection; some open account totals cover every crop year and say so on the screen.</p></div>
            <div class="dashboard-tabs-guide">
                @foreach($dashboardTabs as $tab)
                <article class="dashboard-tab-card{{ $tab['key'] === 'balance' ? ' dashboard-tab-card--priority' : '' }}"><div class="dashboard-tab-head"><span class="module-icon"><i class="fas {{ $tab['icon'] }}" aria-hidden="true"></i></span><span class="module-index">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></div><h3>{{ $tab['name'] }}</h3><p><strong>What you see:</strong> {{ $tab['shows'] }}</p><p><strong>When to use it:</strong> {{ $tab['use'] }}</p><a class="text-link" href="{{ route('dashboard') }}#{{ $tab['key'] }}">Open {{ $tab['name'] }} <span aria-hidden="true">↗</span></a></article>
                @endforeach
            </div>
            <p class="dashboard-scope-note"><i class="fas fa-circle-info" aria-hidden="true"></i> Overview and Loans &amp; Advances can show a Collections Tracker. It summarizes released, collected, overdue, due soon, and still owed amounts. It is a panel inside those tabs, not a separate tab.</p>
        </div></section>

        <section class="section balance-guide" id="balance-guide"><div class="container">
            <div class="balance-heading"><div><p class="kicker">MOST CRITICAL · BALANCE CHECK</p><h2>When Balance Health shows a problem</h2><p>Use the red and amber findings to identify the affected week, planter, account, or truck before preparing the related payment or report.</p></div><a class="button button-gold" href="{{ route('dashboard') }}#balance">Open Balance Check <span aria-hidden="true">↗</span></a></div>
            @include('manual.annotated-shot', ['shot' => 'balance', 'alt' => 'Real Dashboard Balance Check tab with the Balance Health score and module flow', 'targets' => [['x' => 1013, 'y' => 362, 'label' => 'Balance Check tab'], ['x' => 1180, 'y' => 526, 'label' => 'Module Flow finding']]])
            <div class="balance-legend"><div><span class="health-tag health-tag--danger">Off balance</span><p>A required setup, amount, or account balance failed its check. Investigate this first.</p></div><div><span class="health-tag health-tag--warning">Review</span><p>Two sources differ or an entry is incomplete. Compare the original records.</p></div><div><span class="health-tag health-tag--info">Note</span><p>Information for follow-up. If it says “Could not run this check,” ask an administrator to investigate.</p></div><div><span class="health-tag health-tag--ok">Balanced</span><p>That check passed for the records it examined.</p></div></div>
            <div class="health-explain"><h3>What the Health percentage means</h3><p>The score counts checks, not pesos. It gives each <strong>Balanced</strong> or <strong>Note</strong> check full credit, each <strong>Review</strong> check half credit, and each <strong>Off balance</strong> check no credit. It is rounded to a percentage. The red and amber counts beside it are the issues shown on the Balance Check tab. A high score does not clear a red finding, and a “Could not run” note does not prove that the data balances.</p><p>The Module Flow turns a module amber or red when a check touching it has an issue. Click that module to filter the checks. The checks available can differ by account permissions. Loan balance and cash advance schedule checks also inspect eligible accounts from all crop years, even when a week is selected.</p></div>
            <div class="balance-process"><h3>How to resolve a finding</h3><ol><li>Open <strong>Dashboard → Balance Check</strong> and confirm the crop year and week shown under Balance Health.</li><li>Look at <strong>Off balance</strong> first, then <strong>Review</strong>. Use <strong>Only show issues</strong> to hide passing checks.</li><li>Click a finding to read its explanation and affected rows. Write down the listed planter, week, account, or truck.</li><li>Use the button in that finding to open the related module. Compare its entries with the original upload, schedule, or payment record.</li><li>Have an authorized user correct the source record. Return to Balance Check and confirm the finding has cleared before finalizing the affected voucher or payment.</li></ol></div>
            <div class="balance-example-heading"><h3>Follow a finding to its source</h3><p>This real example has the issue filter on and one finding expanded. The affected record rows are hidden in the public guide.</p></div>
            @include('manual.annotated-shot', ['shot' => 'balance-detail', 'alt' => 'Real Balance Check finding expanded with issue filter and related report button', 'targets' => [['x' => 1194, 'y' => 410, 'label' => 'Only show issues'], ['x' => 746, 'y' => 483, 'label' => 'Open finding'], ['x' => 528, 'y' => 719, 'label' => 'Related module']]])
            <div class="check-heading"><h3>Every Balance Check explained</h3><p>These are the checks built into the current dashboard. The status you see depends on your records.</p></div>
            <div class="balance-check-grid">
                @foreach($balanceChecks as $check)
                <article class="balance-check-card"><div class="balance-check-title"><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h4>{{ $check['name'] }}</h4></div><p><strong>What it means</strong><br>{{ $check['meaning'] }}</p><p><strong>What to do</strong><br>{{ $check['action'] }}</p><a class="text-link" href="{{ route($check['route']) }}">Open related module <span aria-hidden="true">↗</span></a></article>
                @endforeach
            </div>
        </div></section>

        <section class="section section-soft" id="modules"><div class="container"><div class="section-heading"><p class="kicker">MODULE WALKTHROUGHS</p><h2>Choose the job you need to do</h2><p>Every image is captured from the actual portal. Numbered arrows point to the controls used in the steps. Record rows and private amounts are hidden in these public examples.</p></div><div class="module-search"><label for="moduleSearch">Find a module</label><div><i class="fas fa-magnifying-glass" aria-hidden="true"></i><input id="moduleSearch" type="search" placeholder="Try “loans”, “upload”, or “prices”" autocomplete="off"></div><p id="moduleCount" aria-live="polite">Showing all {{ count($modules) }} modules</p></div><p class="empty-modules" id="emptyModules" hidden>No modules match that search. Try a shorter word.</p><div class="module-grid" id="moduleGrid">
            @foreach($modules as $module)
            <article class="module-card" id="{{ $module['id'] }}">
                <div class="module-head"><span class="module-icon"><i class="fas {{ $module['icon'] }}" aria-hidden="true"></i></span><span class="module-index">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></div>
                <h3>{{ $module['name'] }}</h3><p><strong>Purpose:</strong> {{ $module['purpose'] }}</p>
                <div class="manual-walkthrough-grid">
                    @include('manual.annotated-shot', ['shot' => $module['id'], 'alt' => 'Actual ' . $module['name'] . ' screen with numbered arrows to relevant controls', 'targets' => $moduleTargets[$module['id']]])
                    <div class="manual-steps"><h4>Follow these steps</h4><ol>@foreach($moduleWalkthroughs[$module['id']] as $step)<li>{{ $step }}</li>@endforeach</ol><a class="text-link" href="{{ route($module['route']) }}" target="_blank" rel="noopener noreferrer">Open module <span aria-hidden="true">↗</span></a></div>
                </div>
            </article>
            @endforeach
        </div></div></section>

        <section class="section report-section" id="reports"><div class="container"><div class="section-heading"><p class="kicker">REPORTS & PRINTING</p><h2>Make a report with confidence</h2><p>Choose the report, set its period, review the preview, then save or print the PDF.</p></div>
            <div class="report-flow"><div class="report-steps"><div><span>1</span><div><h3>Open the report</h3><p>From the left menu, open <strong>Reports</strong> and choose the report name. Loan PDFs are in <strong>Loans → Loan Reports</strong>; vouchers have their own menu item.</p></div></div><div><span>2</span><div><h3>Set the filters</h3><p>Choose a <strong>Crop Year</strong> first, then <strong>Week From</strong> and <strong>Week To</strong> when shown. Some reports also ask for a planter, hauler, station, or association.</p></div></div><div><span>3</span><div><h3>Check what will be included</h3><p>Look at the on-screen table or selected names. If nothing appears, confirm the period and that the matching records were uploaded or entered. Check <a href="#balance-guide">Balance Health</a> if figures do not agree.</p></div></div><div><span>4</span><div><h3>Preview, then download or print</h3><p>Press the report’s <strong>Preview PDF</strong>, <strong>Preview</strong>, or <strong>Generate</strong> button. Check the PDF title, period, and pages before downloading or printing.</p></div></div></div></div>
            <div class="report-list-heading"><h3>Report-by-report instructions</h3><p>All links open the real portal page. Sign-in and the right permission are required.</p></div><div class="report-grid">
            @foreach($reports as $report)
                <article class="report-card"><div class="report-card-top"><span class="report-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h4>{{ $report['name'] }}</h4></div>
                    <div class="manual-walkthrough-grid">
                        @include('manual.annotated-shot', ['shot' => 'report-' . str_pad($loop->iteration, 2, '0', STR_PAD_LEFT), 'alt' => 'Actual ' . $report['name'] . ' page with an arrow to the report control', 'targets' => $reportTargets[$loop->index]])
                        <div class="manual-steps"><h5>Make this report</h5><ol><li>Open <strong>{{ $report['name'] }}</strong> from Reports or its module menu.</li><li>Select {{ $report['select'] }}.</li><li>Review the matching rows or selected names. If none appear, check the period and source records.</li><li>Click <strong>{{ $report['action'] }}</strong>. The arrow in the image points to the report control.</li><li>In the PDF, verify {{ $report['check'] }} before printing or saving.</li></ol><p class="report-tip"><strong>Tip:</strong> {{ $report['note'] }}</p><a class="text-link" href="{{ route($report['route']) }}" target="_blank" rel="noopener noreferrer">Open {{ $report['name'] }} <span aria-hidden="true">↗</span></a></div>
                    </div>
                </article>
            @endforeach
            </div>
        </div></section>

        <section class="section section-soft" id="help"><div class="container help-grid"><div><p class="kicker">QUICK HELP</p><h2>When something does not look right</h2><p>Open Balance Check to identify the exact period and records involved, then follow the link inside the finding.</p><a class="button button-dark" href="{{ auth()->check() ? route('dashboard') . '#balance' : route('getLogin') }}">{{ auth()->check() ? 'Open Balance Check' : 'Go to sign in' }} <span aria-hidden="true">→</span></a></div><div class="faq-list"><details><summary>The numbers are not balancing. What should I do first?</summary><p>Open Dashboard → Balance Check. Review red findings, then amber findings. Expand each one to see its affected rows and use its related-module link. Have an authorized user correct the source before finalizing the affected payment or report.</p></details><details><summary>I cannot see a module. What should I do?</summary><p>Menus follow your assigned permissions. Ask your administrator which access you need for that task.</p></details><details><summary>Why is the report empty?</summary><p>Check the crop year and week range, then confirm that records for that period were uploaded or entered. Clear optional planter or station filters and try again.</p></details><details><summary>The PDF button is disabled. Why?</summary><p>Complete all required fields first. Some reports also require a selected planter or matching records.</p></details><details><summary>How do I correct a wrong upload?</summary><p>Check the source file and selected period, then ask an authorized staff member to review the affected records before another upload.</p></details></div></div></section>
    </main>
    <footer class="site-footer"><div class="container footer-inner"><span><img src="{{ asset('img/logo-64.webp') }}" alt="" width="32" height="32"> KUSUG-PA User Guide</span><div><a href="{{ url('/') }}">Home</a><a href="#main">Back to top ↑</a></div><small>Guide to the current portal interface · {{ date('Y') }}</small></div></footer>
</body>
</html>
