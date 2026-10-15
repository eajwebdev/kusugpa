@php
    $peso = fn ($amount) => '₱' . number_format($amount);
    $annualSubscription = (int) round($prices['subscription'] * 12 * (100 - $prices['discount']) / 100);
    $annualSaving = $prices['subscription'] * 12 - $annualSubscription;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1434">
    <meta name="description" content="KUSUG-PA system quotation with optional member portal and SMS services.">
    <title>KUSUG-PA System Quotation | EAJ Web Development Services</title>
    <link rel="icon" href="{{ asset('img/eaj-primary.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/quotation.css') }}">
</head>
<body>
    <a class="skip-link" href="#quote">Skip to quotation</a>
    <header class="site-header">
        <div class="wrap header-inner">
            <a class="brand" href="#top" aria-label="EAJ Web Development Services, back to top"><span>EAJ<span class="brand-dot">.</span></span><small>WEB DEVELOPMENT SERVICES</small></a>
            <nav aria-label="Page navigation"><a href="#quote">Build quote</a><a href="#scope">Scope</a><a href="#terms">Terms</a></nav>
            <a class="header-download" href="{{ route('quotation.download') }}" id="headerDownload">Download PDF <span aria-hidden="true">↓</span></a>
        </div>
    </header>

    <main id="top">
        <section class="quote-header">
            <div class="wrap quote-header-inner">
                <div>
                    <p class="eyebrow">CUSTOM SOFTWARE QUOTATION · 2026</p>
                    <h1>KUSUG-PA System <em>Quotation</em></h1>
                    <p>Sugarcane Crop Management &amp; Recording System with integrated check and voucher printing.</p>
                    <div class="header-facts"><span>11 core modules</span><span>Cloud hosted</span><span>Prepared for KUSUG-PA</span></div>
                </div>
                <img src="{{ asset('img/eaj-primary.png') }}" alt="EAJ Web Development Services logo" width="1536" height="1024">
            </div>
        </section>

        <section class="quote-section" id="quote" aria-labelledby="quote-title">
            <div class="wrap">
                <div class="section-heading"><div><p class="eyebrow">BUILD YOUR QUOTE</p><h2 id="quote-title">Select what you need.</h2></div><p>One-time development, your subscription choice, and any optional services are calculated together.</p></div>
                <div class="quote-layout">
                    <div class="choices">
                        <div class="choice-group">
                            <h3>Included</h3>
                            <div class="choice-row"><span class="included-icon" aria-hidden="true">✓</span><span class="choice-copy"><strong>Core management system</strong><small>11 modules, deployment and one staff onboarding session</small></span><span class="price-tag">{{ $peso($prices['base']) }}<small>one-time</small></span></div>
                            <div class="choice-row"><span class="included-icon" aria-hidden="true">✓</span><span class="choice-copy"><strong>Public landing page</strong><small>A branded introduction to KUSUG-PA</small></span><span class="free-tag">FREE</span></div>
                        </div>

                        <fieldset class="choice-group billing-group">
                            <legend>Software subscription</legend>
                            <div class="billing-options">
                                <label class="billing-option selected" for="billingMonthly"><input id="billingMonthly" name="billing" type="radio" value="monthly" checked><span><strong>Pay monthly</strong><small>{{ $peso($prices['subscription']) }}/month for Year 1</small></span></label>
                                <label class="billing-option" for="billingAnnual"><input id="billingAnnual" name="billing" type="radio" value="annual"><span><strong>12 months upfront <b class="discount-tag">SAVE {{ $prices['discount'] }}%</b></strong><small>{{ $peso($annualSubscription) }} total · save {{ $peso($annualSaving) }}</small></span></label>
                            </div>
                            <p class="help-text">The discount applies to the software subscription. Hosting and optional services retain their stated monthly rates.</p>
                        </fieldset>

                        <fieldset class="choice-group addons-group">
                            <legend>Optional add-ons</legend>
                            <label class="addon-row" for="memberPortal"><input id="memberPortal" type="checkbox"><span class="choice-copy"><strong>Member Portal Access</strong><small>Individual accounts for members to access their own records and updates</small></span><span class="price-tag">+{{ $peso($prices['member_once']) }}<small>+{{ $peso($prices['member_monthly']) }}/month</small></span></label>
                            <label class="addon-row" for="smsAddon"><input id="smsAddon" type="checkbox"><span class="choice-copy"><strong>SMS Notifications</strong><small>{{ number_format($prices['sms_credits']) }} SMS per month for member notices</small></span><span class="price-tag">+{{ $peso($prices['sms_once']) }}<small>+{{ $peso($prices['sms_monthly']) }}/month</small></span></label>
                        </fieldset>
                    </div>

                    <aside class="summary" aria-labelledby="summary-title">
                        <div class="summary-heading"><p class="eyebrow">YOUR SELECTION</p><h2 id="summary-title">Investment summary</h2></div>
                        <div class="summary-content">
                            <p class="summary-label">ONE-TIME DEVELOPMENT</p>
                            <div class="summary-row"><span>Core system</span><strong>{{ $peso($prices['base']) }}</strong></div>
                            <div class="summary-row"><span>Landing page</span><strong>Free</strong></div>
                            <div class="summary-row" id="memberOnceRow" hidden><span>Member Portal Access</span><strong>{{ $peso($prices['member_once']) }}</strong></div>
                            <div class="summary-row" id="smsOnceRow" hidden><span>SMS setup</span><strong>{{ $peso($prices['sms_once']) }}</strong></div>
                            <div class="summary-total"><span>One-time total</span><strong id="onceTotal" aria-live="polite">{{ $peso($prices['base']) }}</strong></div>
                            <div class="split-row"><span>50% on signing</span><strong id="depositTotal">{{ $peso($prices['base'] / 2) }}</strong></div>
                            <div class="split-row"><span>50% on acceptance</span><strong id="balanceTotal">{{ $peso($prices['base'] / 2) }}</strong></div>

                            <div class="summary-divider"></div>
                            <p class="summary-label" id="billingLabel">MONTHLY FROM DEPLOYMENT</p>
                            <div class="summary-row" id="subscriptionMonthlyRow"><span>Software subscription</span><strong>{{ $peso($prices['subscription']) }}/mo</strong></div>
                            <div class="summary-row" id="subscriptionAnnualRow" hidden><span>12-month subscription · 20% off</span><strong>{{ $peso($annualSubscription) }} upfront</strong></div>
                            <div class="summary-row"><span>Hosting, domain &amp; SSL</span><strong>{{ $peso($prices['hosting']) }}/mo</strong></div>
                            <div class="summary-row" id="memberMonthRow" hidden><span>Member Portal Access</span><strong>{{ $peso($prices['member_monthly']) }}/mo</strong></div>
                            <div class="summary-row" id="smsMonthRow" hidden><span>SMS · {{ number_format($prices['sms_credits']) }} messages</span><strong>{{ $peso($prices['sms_monthly']) }}/mo</strong></div>
                            <div class="summary-recurring"><span>Monthly charges <small id="monthlyNote">including subscription</small></span><strong id="monthlyTotal" aria-live="polite">{{ $peso($prices['subscription'] + $prices['hosting']) }}/mo</strong></div>
                            <div class="year-total"><span>Year 1 total <small>development + 12 months of applicable charges</small></span><strong id="yearTotal" aria-live="polite">{{ $peso($prices['base'] + 12 * ($prices['subscription'] + $prices['hosting'])) }}</strong></div>
                        </div>
                        <div class="summary-actions"><a class="download-button" id="downloadQuote" href="{{ route('quotation.download') }}">Download selected quote as PDF <span aria-hidden="true">↓</span></a><p>No login required. The downloaded PDF reflects these selections.</p></div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="details-section" id="scope"><div class="wrap">
            <div class="details-heading"><div><p class="eyebrow">PROPOSAL DETAILS</p><h2>Everything behind the quote.</h2></div><p>Reference {{ $details['reference'] }} · Issued {{ now()->timezone('Asia/Manila')->format('F j, Y') }} · Valid for 60 days</p></div>
            <div class="details-grid">
                <details class="info-panel info-panel-wide"><summary><span><small>FULL SYSTEM SCOPE</small>Overview and 11 included modules</span><span class="expand-icon" aria-hidden="true">+</span></summary>
                    <div class="panel-body"><p class="panel-lead">A cloud-hosted platform for the association’s administrative, financial, and crop operations. It is accessible online without an on-site server.</p><ul class="bullet-grid">@foreach($details['overview'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                        <div class="module-details">@foreach($details['modules'] as $module)<article class="module-detail"><div class="module-title"><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $module['title'] }}</h3></div><p>{{ $module['summary'] }}</p><ul>@foreach($module['features'] as $feature)<li>{{ $feature }}</li>@endforeach</ul></article>@endforeach</div>
                    </div>
                </details>
                <details class="info-panel"><summary><span><small>RECURRING SERVICES</small>Hosting, support and renewal</span><span class="expand-icon" aria-hidden="true">+</span></summary><div class="panel-body"><h3>Cloud hosting · {{ $peso($prices['hosting']) }}/month</h3><ul>@foreach($details['hosting'] as $item)<li>{{ $item }}</li>@endforeach</ul><h3>Year 1 software subscription · {{ $peso($prices['subscription']) }}/month</h3><ul>@foreach($details['subscription'] as $item)<li>{{ $item }}</li>@endforeach</ul><p class="panel-note">Choose monthly billing or prepay 12 months for {{ $prices['discount'] }}% off the software subscription. Hosting and add-ons are billed separately.</p><h3>After Year 1</h3><ul>@foreach($details['continuity'] as $item)<li>{{ $item }}</li>@endforeach</ul></div></details>
                <details class="info-panel" id="terms"><summary><span><small>COMMERCIAL TERMS</small>Payments, delivery and scope limits</span><span class="expand-icon" aria-hidden="true">+</span></summary><div class="panel-body"><h3>Payment milestones</h3><ul><li>50% of selected one-time fees upon signing the service agreement.</li><li>Remaining 50% upon system delivery and client acceptance.</li><li>Subscription and hosting begin at deployment. The annual subscription option is prepaid at deployment.</li></ul><h3>Included in delivery</h3><ul>@foreach($details['inclusions'] as $item)<li>{{ $item }}</li>@endforeach</ul><h3>Outside this quotation</h3><ul>@foreach($details['exclusions'] as $item)<li>{{ $item }}</li>@endforeach</ul><h3>Timeline and validity</h3><ul>@foreach($details['delivery'] as $item)<li>{{ $item }}</li>@endforeach<li>This revised quotation is valid for 60 days from the issue date; pricing may be reviewed afterward.</li></ul></div></details>
                <details class="info-panel"><summary><span><small>DATA &amp; LICENSING</small>Ownership and usage rights</span><span class="expand-icon" aria-hidden="true">+</span></summary><div class="panel-body"><ul>@foreach($details['ownership'] as $item)<li>{{ $item }}</li>@endforeach</ul></div></details>
                <details class="info-panel"><summary><span><small>PROPOSED CONTROLS</small>Security and client responsibilities</span><span class="expand-icon" aria-hidden="true">+</span></summary><div class="panel-body"><h3>Security measures in the proposal</h3><ul>@foreach($details['security'] as $item)<li>{{ $item }}</li>@endforeach</ul><h3>Client responsibilities</h3><ul>@foreach($details['client_responsibilities'] as $item)<li>{{ $item }}</li>@endforeach</ul></div></details>
            </div>
        </div></section>
    </main>
    <footer class="footer"><div class="wrap"><strong>EAJ WEB DEVELOPMENT SERVICES</strong><span>Prepared for KUSUG-PA · Kabankalan City, Negros Occidental</span><a href="{{ url('/landing') }}">KUSUG-PA landing page ↗</a></div></footer>
    <script>
        (() => {
            const prices = @json($prices);
            const annualSubscription = {{ $annualSubscription }};
            const member = document.getElementById('memberPortal');
            const sms = document.getElementById('smsAddon');
            const annual = document.getElementById('billingAnnual');
            const money = amount => '₱' + new Intl.NumberFormat('en-PH', {maximumFractionDigits: 0}).format(amount);
            const show = (id, visible) => { document.getElementById(id).hidden = !visible; };
            const update = () => {
                const once = prices.base + (member.checked ? prices.member_once : 0) + (sms.checked ? prices.sms_once : 0);
                const addonsMonthly = (member.checked ? prices.member_monthly : 0) + (sms.checked ? prices.sms_monthly : 0);
                const monthly = prices.hosting + addonsMonthly + (annual.checked ? 0 : prices.subscription);
                const year = once + (annual.checked ? annualSubscription : 0) + monthly * 12;
                show('memberOnceRow', member.checked); show('memberMonthRow', member.checked);
                show('smsOnceRow', sms.checked); show('smsMonthRow', sms.checked);
                show('subscriptionMonthlyRow', !annual.checked); show('subscriptionAnnualRow', annual.checked);
                document.querySelectorAll('.billing-option').forEach(option => option.classList.toggle('selected', option.querySelector('input').checked));
                document.querySelectorAll('.addon-row').forEach(option => option.classList.toggle('selected', option.querySelector('input').checked));
                document.getElementById('onceTotal').textContent = money(once);
                document.getElementById('depositTotal').textContent = money(once / 2);
                document.getElementById('balanceTotal').textContent = money(once / 2);
                document.getElementById('monthlyTotal').textContent = money(monthly) + '/mo';
                document.getElementById('billingLabel').textContent = annual.checked ? 'BILLING FROM DEPLOYMENT' : 'MONTHLY FROM DEPLOYMENT';
                document.getElementById('monthlyNote').textContent = annual.checked ? 'subscription prepaid separately' : 'including subscription';
                document.getElementById('yearTotal').textContent = money(year);
                const url = new URL(@json(route('quotation.download')));
                if (member.checked) url.searchParams.set('member', '1');
                if (sms.checked) url.searchParams.set('sms', '1');
                if (annual.checked) url.searchParams.set('billing', 'annual');
                document.getElementById('downloadQuote').href = url.href;
                document.getElementById('headerDownload').href = url.href;
            };
            [member, sms, ...document.querySelectorAll('input[name="billing"]')].forEach(input => input.addEventListener('change', update));
            update();
        })();
    </script>
</body>
</html>
