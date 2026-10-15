@php
    $peso = fn ($amount) => '₱' . number_format($amount);
    $subscriptionYear = $prices['subscription'] * 12;
    $saving = $subscriptionYear - $annualSubscription;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>KUSUG-PA System Quotation</title>
    <style>
        @page { margin: 29px 35px 32px; }
        body { margin:0; color:#14203b; font-family:DejaVu Sans,sans-serif; font-size:10px; line-height:1.55; }
        h1,h2,h3,p { margin-top:0; }
        .top { background:#0b1434; color:#fff; padding:17px 20px; border-radius:7px; }
        .top table { width:100%; border-collapse:collapse; }
        .top td { vertical-align:middle; }
        .top img { width:104px; height:69px; object-fit:cover; }
        .eyebrow { color:#ff83ad; font-size:8px; font-weight:bold; letter-spacing:1.1px; }
        h1 { font-size:22px; letter-spacing:-.7px; margin:5px 0; }
        .top p { margin:0; color:#cdd6e8; font-size:8px; }
        .meta { margin:12px 0 15px; padding-bottom:10px; border-bottom:1px solid #dce2ec; color:#59657b; }
        .meta table { width:100%; }
        .meta td:last-child { text-align:right; }
        .grid { width:100%; border-collapse:separate; border-spacing:9px 0; margin-left:-9px; }
        .grid>tbody>tr>td { width:50%; vertical-align:top; }
        .box { padding:12px 14px; border:1px solid #dce2ec; border-radius:6px; }
        .box h2 { margin:0 0 9px; font-size:11px; }
        .line { width:100%; border-collapse:collapse; }
        .line td { padding:5px 0; border-bottom:1px solid #e9edf3; }
        .line td:last-child { text-align:right; font-weight:bold; white-space:nowrap; }
        .line .total td { padding-top:10px; border-top:1px solid #aeb8c9; border-bottom:0; color:#0b1434; font-size:12px; font-weight:bold; }
        .label { color:#b31b54; font-size:8px; font-weight:bold; letter-spacing:.6px; margin:12px 0 4px; }
        .year { margin-top:9px; padding:9px 11px; background:#0b1434; color:#fff; border-radius:5px; font-size:12px; font-weight:bold; }
        .year span { float:right; }
        .muted { color:#647086; font-size:8px; }
        .selected { margin-top:7px; padding:8px 10px; background:#fce9f0; color:#9f174c; border-radius:4px; font-size:8px; }
        .scope { margin-top:14px; padding:12px 14px; border:1px solid #dce2ec; border-radius:6px; }
        .scope h2 { font-size:11px; margin:0 0 7px; }
        .scope table { width:100%; border-collapse:collapse; }
        .scope td { width:50%; padding:3px 5px 3px 0; color:#3c4a62; }
        .scope b { color:#d7115a; font-size:7px; margin-right:5px; }
        .terms { margin-top:13px; padding:0 2px; color:#4e5b71; font-size:8px; }
        .terms h2 { font-size:10px; color:#14203b; margin:0 0 5px; }
        .terms p { margin:0 0 5px; }
        .footer { margin-top:13px; padding-top:7px; border-top:1px solid #dce2ec; color:#657188; font-size:7px; }
        .overview { margin-top:14px; padding:11px 14px; border:1px solid #dce2ec; border-radius:6px; }
        .overview h2 { margin:0 0 6px; font-size:11px; }
        .overview ul { margin:0; padding-left:16px; color:#46536c; }
        .overview li { margin:2px 0; }
        .page-break { page-break-before:always; }
        .page-heading { border-bottom:2px solid #d7115a; padding-bottom:8px; margin-bottom:11px; }
        .page-heading h2 { font-size:17px; margin:0 0 3px; }
        .page-heading p { margin:0; color:#657188; font-size:8px; }
        .module-detail { page-break-inside:avoid; padding:11px 0 12px; border-bottom:1px solid #dce2ec; }
        .module-detail h3 { font-size:11px; margin:0 0 2px; }
        .module-detail h3 span { color:#d7115a; margin-right:8px; }
        .module-detail p { margin:0 0 3px; color:#5c6880; font-size:9px; }
        .module-detail ul { margin:0; padding-left:17px; }
        .module-detail li { margin:1px 0; font-size:9px; }
        .detail-box { page-break-inside:avoid; margin:0 0 12px; padding:12px 14px; border:1px solid #dce2ec; border-radius:5px; }
        .detail-box h3 { font-size:11px; margin:0 0 5px; }
        .detail-box ul { margin:0; padding-left:16px; }
        .detail-box li { margin:2px 0; font-size:9px; }
        .detail-box p { margin:0 0 5px; font-size:9px; }
        .two-col { width:100%; border-collapse:separate; border-spacing:8px 0; margin-left:-8px; }
        .two-col>tbody>tr>td { width:50%; vertical-align:top; }
        .small-note { color:#657188; font-size:7px; }
        .signatures { width:100%; margin-top:23px; border-collapse:separate; border-spacing:20px 0; margin-left:-20px; }
        .signatures td { width:50%; padding-top:6px; border-top:1px solid #9ba8bb; vertical-align:top; }
        .signatures strong { display:block; font-size:8px; }
        .signatures span { color:#657188; font-size:7px; }
    </style>
</head>
<body>
    <div class="top"><table><tr><td><div class="eyebrow">EAJ WEB DEVELOPMENT SERVICES · 2026</div><h1>KUSUG-PA System Quotation</h1><p>Sugarcane Crop Management &amp; Recording System with integrated check and voucher printing</p></td><td style="width:110px;text-align:right"><img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('img/eaj-primary.png'))) }}" alt="EAJ logo"></td></tr></table></div>
    <div class="meta"><table><tr><td>Prepared for: <strong>{{ $details['client'] }}</strong><br>Reference: {{ $details['reference'] }} · Revised quotation</td><td>Issued: {{ now()->timezone('Asia/Manila')->format('F j, Y') }}<br>Valid for 60 days from issue</td></tr></table></div>

    <table class="grid"><tr><td>
        <div class="box"><h2>One-time development</h2><table class="line">
            <tr><td>Core management system · 11 modules</td><td>{{ $peso($prices['base']) }}</td></tr>
            <tr><td>Public landing page</td><td>Free</td></tr>
            @if($member)<tr><td>Member Portal Access</td><td>{{ $peso($prices['member_once']) }}</td></tr>@endif
            @if($sms)<tr><td>SMS setup</td><td>{{ $peso($prices['sms_once']) }}</td></tr>@endif
            <tr class="total"><td>One-time total</td><td>{{ $peso($oneTime) }}</td></tr>
        </table><p class="muted" style="margin:8px 0 0">50% on signing: {{ $peso($oneTime / 2) }}<br>50% on delivery and acceptance: {{ $peso($oneTime / 2) }}</p></div>
    </td><td>
        <div class="box"><h2>{{ $annual ? '12-month subscription plan' : 'Monthly subscription plan' }}</h2><table class="line">
            @if($annual)<tr><td>Year 1 software · {{ $prices['discount'] }}% off</td><td>{{ $peso($annualSubscription) }} upfront</td></tr>
            @else<tr><td>Year 1 software subscription</td><td>{{ $peso($prices['subscription']) }}/mo</td></tr>@endif
            <tr><td>Hosting, domain &amp; SSL</td><td>{{ $peso($prices['hosting']) }}/mo</td></tr>
            @if($member)<tr><td>Member Portal Access</td><td>{{ $peso($prices['member_monthly']) }}/mo</td></tr>@endif
            @if($sms)<tr><td>SMS · {{ number_format($prices['sms_credits']) }} messages</td><td>{{ $peso($prices['sms_monthly']) }}/mo</td></tr>@endif
            <tr class="total"><td>Monthly charges</td><td>{{ $peso($monthly) }}/mo</td></tr>
        </table>@if($annual)<p class="muted" style="margin:8px 0 0">Software subscription saving: {{ $peso($saving) }}. Hosting and add-ons remain monthly.</p>@endif</div>
    </td></tr></table>
    <div class="year">TOTAL YEAR 1 INVESTMENT <span>{{ $peso($yearOne) }}</span></div>
    <div class="selected">Selected: core system + free landing page{{ $member ? ' + Member Portal Access' : '' }}{{ $sms ? ' + SMS Notifications' : '' }} · {{ $annual ? '12-month prepaid software subscription' : 'monthly software subscription' }}</div>

    <div class="overview"><h2>Project overview</h2><ul>@foreach($details['overview'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
    <div class="footer">EAJ WEB DEVELOPMENT SERVICES · Prepared for KUSUG-PA, Kabankalan City · Quotation summary</div>

    <div class="page-break page-heading"><h2>Included system modules · 1–6</h2><p>Detailed scope of the one-time development fee</p></div>
    @foreach(array_slice($details['modules'], 0, 6) as $module)
        <div class="module-detail"><h3><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $module['title'] }}</h3><p>{{ $module['summary'] }}</p><ul>@foreach($module['features'] as $feature)<li>{{ $feature }}</li>@endforeach</ul></div>
    @endforeach

    <div class="page-break page-heading"><h2>Included system modules · 7–11</h2><p>Reporting, planter records, loans, access control, and auditability</p></div>
    @foreach(array_slice($details['modules'], 6, 5) as $module)
        <div class="module-detail"><h3><span>{{ str_pad($loop->iteration + 6, 2, '0', STR_PAD_LEFT) }}</span>{{ $module['title'] }}</h3><p>{{ $module['summary'] }}</p><ul>@foreach($module['features'] as $feature)<li>{{ $feature }}</li>@endforeach</ul></div>
    @endforeach
    <table class="two-col" style="margin-top:11px"><tr><td><div class="detail-box"><h3>Hosting · {{ $peso($prices['hosting']) }}/month</h3><ul>@foreach($details['hosting'] as $item)<li>{{ $item }}</li>@endforeach</ul></div></td><td><div class="detail-box"><h3>Year 1 software subscription</h3><ul>@foreach($details['subscription'] as $item)<li>{{ $item }}</li>@endforeach</ul></div></td></tr></table>

    <div class="page-break page-heading"><h2>Commercial terms &amp; safeguards</h2><p>Delivery, support, ownership, and proposed security measures</p></div>
    <table class="two-col"><tr><td>
        <div class="detail-box"><h3>Payment milestones</h3><ul><li>50% of selected one-time fees on signing: {{ $peso($oneTime / 2) }}.</li><li>Remaining 50% on delivery and acceptance: {{ $peso($oneTime / 2) }}.</li><li>Software subscription and hosting begin at deployment. The selected annual plan is prepaid at deployment.</li></ul></div>
        <div class="detail-box"><h3>Included in delivery</h3><ul>@foreach($details['inclusions'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
        <div class="detail-box"><h3>Exclusions</h3><ul>@foreach($details['exclusions'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
        <div class="detail-box"><h3>Delivery timeline</h3><ul>@foreach($details['delivery'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
    </td><td>
        <div class="detail-box"><h3>Year 2 renewal &amp; hosting continuity</h3><ul>@foreach($details['continuity'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
        <div class="detail-box"><h3>Data privacy, license &amp; ownership</h3><ul>@foreach($details['ownership'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
        <div class="detail-box"><h3>Proposed security measures</h3><ul>@foreach($details['security'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
        <div class="detail-box"><h3>Client responsibilities</h3><ul>@foreach($details['client_responsibilities'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
    </td></tr></table>
    <p class="small-note">This revised quotation is valid for 60 days from issue; pricing may be reviewed afterward. Based on KUSUG-PA System Proposal 2026, with the agreed ₱90,000 base development price and selected optional services.</p>
    <table class="signatures"><tr><td><strong>MICHAEL A. BALIVIA</strong><span>System Development Team Manager · EAJ Web Development Services</span></td><td><strong>Authorized Representative, KUSUG-PA</strong><span>Accepted and agreed · Date signed: __________________</span></td></tr></table>
</body>
</html>
