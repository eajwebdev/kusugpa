<!-- resources/views/landing.blade.php -->
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kabankalan United Sugar Planters Association Inc. (KUSUG-PA) — crop management, milling coordination, and market advocacy for sugar planters.">
    <title>KUSUG-PA | Empowering Sugar Planters</title>
    <script>
        (function() {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @include('layouts.partials.assets')
</head>
<body class="min-h-screen" style="background:var(--kp-bg); color:var(--kp-text);">
    @php
        $loginUrl = Route::has('getLogin') ? route('getLogin') : url('/login');
    @endphp

    <!-- Top bar -->
    <header class="sticky top-0 z-30 border-b" style="background:rgba(247,250,248,0.85); border-color:var(--kp-border); backdrop-filter:blur(10px);">
        <div class="mx-auto max-w-6xl px-5 sm:px-8 h-16 flex items-center justify-between">
            <a href="#top" class="flex items-center gap-3">
                <picture>
                    <source srcset="{{ asset('img/logo-64.webp') }}" type="image/webp">
                    <img src="{{ asset('img/logo-64.png') }}" alt="KUSUG-PA logo" width="36" height="36" class="h-9 w-9 object-contain">
                </picture>
                <span class="font-display text-lg font-semibold" style="color:var(--kp-deep);">KUSUG-PA</span>
            </a>
            <nav class="hidden md:flex items-center gap-7 text-sm font-medium" style="color:var(--kp-muted);">
                <a href="#about" class="hover:text-[var(--kp-forest)]">About</a>
                <a href="#services" class="hover:text-[var(--kp-forest)]">Services</a>
                <a href="#portal" class="hover:text-[var(--kp-forest)]">Planter Portal</a>
                <a href="#membership" class="hover:text-[var(--kp-forest)]">Membership</a>
                <a href="#contact" class="hover:text-[var(--kp-forest)]">Contact</a>
            </nav>
            <a href="{{ $loginUrl }}" class="kp-submit !h-10 !px-5 !text-sm !w-auto">
                Sign in
            </a>
        </div>
    </header>

    <main id="top">
        <!-- Hero -->
        <section class="relative overflow-hidden" style="background:var(--kp-deep); color:#fff;">
            <div class="absolute inset-0" style="background:
                repeating-linear-gradient(100deg, transparent 0 46px, rgba(255,255,255,0.05) 46px 47px),
                linear-gradient(180deg, rgba(0,91,42,0.55) 0%, rgba(0,63,32,0) 55%, rgba(0,20,10,0.55) 100%);"></div>
            <div class="relative mx-auto max-w-6xl px-5 sm:px-8 py-20 sm:py-28">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide px-3 py-1 rounded-full" style="background:rgba(217,181,74,0.18); color:#F5E8C3; border:1px solid rgba(217,181,74,0.35);">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#D9B54A]"></span>
                        Kabankalan United Sugar Planters Association Inc.
                    </span>
                    <h1 class="font-display mt-6 text-4xl sm:text-5xl lg:text-6xl font-semibold leading-tight tracking-tight">
                        Empowering Sugar Planters. Strengthening Our Community.
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed" style="color:#CFE5D6; max-width:60ch;">
                        Driven by unity and progress, KUSUG-PA provides the tools, advocacy, and resources you need for a prosperous harvest.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ $loginUrl }}" class="kp-submit !w-auto !px-6">
                            <i class="fas fa-user-plus"></i> Join KUSUG-PA Today
                        </a>
                        <a href="{{ $loginUrl }}" class="kp-submit !w-auto !px-6" style="background:rgba(255,255,255,0.12); box-shadow:none; border:1px solid rgba(255,255,255,0.25);">
                            <i class="fas fa-chart-line"></i> Check Latest Sugar Prices
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- About -->
        <section id="about" class="mx-auto max-w-6xl px-5 sm:px-8 py-16 sm:py-20">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide" style="color:var(--kp-emerald);">About Us</p>
                <h2 class="font-display mt-2 text-3xl sm:text-4xl font-semibold" style="color:var(--kp-text);">The Strength Behind Your Success</h2>
                <p class="mt-5 text-base leading-relaxed" style="color:var(--kp-muted);">
                    For years, KUSUG-PA has stood as a pillar of support for the sugarcane planters of Kabankalan City and neighboring districts. We believe that a community is at its strongest—<em>kusug</em>—when it works together. As a registered non-profit organization, our sole mission is to uplift local farmers, protect their livelihoods, and maximize their yields through collaborative agriculture.
                </p>
            </div>
        </section>

        <!-- Services -->
        <section id="services" class="border-y" style="border-color:var(--kp-border); background:var(--kp-surface-2);">
            <div class="mx-auto max-w-6xl px-5 sm:px-8 py-16 sm:py-20">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color:var(--kp-emerald);">Services &amp; Support</p>
                    <h2 class="font-display mt-2 text-3xl sm:text-4xl font-semibold">Comprehensive Support from Planting to Milling</h2>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $services = [
                            ['icon'=>'fa-tractor', 'title'=>'Tractor & Equipment Rentals', 'body'=>'Access reliable, modern farm machinery at heavily subsidized rates to prepare your land quickly and efficiently.'],
                            ['icon'=>'fa-industry', 'title'=>'Milling Coordination', 'body'=>'Stay ahead with seamless scheduling, cane acceptance updates, and smooth hauling coordination with partner mills like URC-SONEDCO.'],
                            ['icon'=>'fa-handshake', 'title'=>'Market Advocacy & Bidding', 'body'=>'Benefit from collective bargaining. We publish and manage competitive bidding prices to ensure you always get the fair market value for your crop.'],
                        ];
                    @endphp
                    @foreach($services as $s)
                    <article class="kp-card p-6">
                        <div class="h-10 w-10 rounded-lg grid place-items-center" style="background:var(--kp-soft); color:var(--kp-forest);">
                            <i class="fas {{ $s['icon'] }}"></i>
                        </div>
                        <h3 class="mt-4 text-base font-semibold" style="color:var(--kp-text);">{{ $s['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed" style="color:var(--kp-muted);">{{ $s['body'] }}</p>
                    </article>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Planter Portal -->
        <section id="portal" class="mx-auto max-w-6xl px-5 sm:px-8 py-16 sm:py-20">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide" style="color:var(--kp-emerald);">Live Planter Dashboard</p>
                <h2 class="font-display mt-2 text-3xl sm:text-4xl font-semibold">Planter's Portal &amp; Market Watch</h2>
            </div>
            <div class="mt-10 grid gap-5 sm:grid-cols-3">
                @php
                    $features = [
                        ['icon'=>'fa-peso-sign', 'title'=>'Current Bidding Prices', 'body'=>'Stay informed with up-to-the-minute crop pricing and sugar quedans.'],
                        ['icon'=>'fa-calendar-week', 'title'=>'Milling Schedule', 'body'=>'Real-time announcements on URC-SONEDCO cane acceptance dates and weekly delivery allocations.'],
                        ['icon'=>'fa-bell', 'title'=>'Advisory Alerts', 'body'=>'Instant notices on weather disruptions, fertilizer distributions, and industry policies.'],
                    ];
                @endphp
                @foreach($features as $f)
                <div class="kp-card p-6">
                    <div class="h-10 w-10 rounded-full grid place-items-center" style="background:var(--kp-deep); color:#F5E8C3;">
                        <i class="fas {{ $f['icon'] }}"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold">{{ $f['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed" style="color:var(--kp-muted);">{{ $f['body'] }}</p>
                    <p class="mt-4 text-xs font-semibold" style="color:var(--kp-emerald);">Available after sign-in &rarr;</p>
                </div>
                @endforeach
            </div>
        </section>

        <!-- Membership -->
        <section id="membership" class="border-y" style="border-color:var(--kp-border); background:var(--kp-surface-2);">
            <div class="mx-auto max-w-6xl px-5 sm:px-8 py-16 sm:py-20">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color:var(--kp-emerald);">Membership &amp; Benefits</p>
                    <h2 class="font-display mt-2 text-3xl sm:text-4xl font-semibold">Grow With Us</h2>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-3">
                    @php
                        $benefits = [
                            ['icon'=>'fa-seedling', 'title'=>'Financial & Supply Assistance', 'body'=>'Gain priority access to subsidized fertilizers, quality cane seed pieces (patdan), and agricultural credit.'],
                            ['icon'=>'fa-bullhorn', 'title'=>'Voice in the Industry', 'body'=>'Be represented in major policy dialogues with the Sugar Regulatory Administration (SRA) and milling corporations.'],
                            ['icon'=>'fa-chalkboard-teacher', 'title'=>'Expert Workshops', 'body'=>'Free access to modern agricultural seminars, sustainable farming practices, and soil management techniques.'],
                        ];
                    @endphp
                    @foreach($benefits as $b)
                    <div class="kp-card p-6">
                        <div class="h-10 w-10 rounded-lg grid place-items-center" style="background:var(--kp-soft); color:var(--kp-forest);">
                            <i class="fas {{ $b['icon'] }}"></i>
                        </div>
                        <h3 class="mt-4 text-base font-semibold">{{ $b['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed" style="color:var(--kp-muted);">{{ $b['body'] }}</p>
                    </div>
                    @endforeach
                </div>
                <div class="mt-10">
                    <a href="{{ $loginUrl }}" class="kp-submit !w-auto !px-6">
                        <i class="fas fa-user-plus"></i> Join KUSUG-PA Today
                    </a>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section id="contact" class="mx-auto max-w-6xl px-5 sm:px-8 py-16 sm:py-20">
            <div class="grid gap-10 lg:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color:var(--kp-emerald);">Contact &amp; Location</p>
                    <h2 class="font-display mt-2 text-3xl sm:text-4xl font-semibold">Visit Our Office or Get in Touch</h2>
                    <p class="mt-5 text-base leading-relaxed" style="color:var(--kp-muted);">
                        We are here to support planters across Kabankalan City and neighboring districts.
                    </p>
                    <ul class="mt-8 space-y-4 text-sm">
                        <li class="flex items-start gap-3">
                            <i class="fas fa-location-dot mt-1" style="color:var(--kp-emerald);"></i>
                            <span>NZ Arcade Bldg., Repullo Street, Brgy. 2, Kabankalan City, Negros Occidental, Philippines</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <i class="fas fa-phone" style="color:var(--kp-emerald);"></i>
                            <a href="tel:+639777165339" class="hover:underline">+63 977 716 5339</a>
                        </li>
                        <li class="flex items-center gap-3">
                            <i class="fas fa-envelope" style="color:var(--kp-emerald);"></i>
                            <a href="mailto:kusugpa@yahoo.com" class="hover:underline">kusugpa@yahoo.com</a>
                        </li>
                    </ul>
                </div>
                <div class="kp-card p-6 sm:p-8">
                    <h3 class="text-lg font-semibold">Send a Message / Inquiry</h3>
                    <p class="mt-1 text-sm" style="color:var(--kp-muted);">For membership, services, or general inquiries, email or call us directly.</p>
                    <div class="mt-6 space-y-3">
                        <a href="mailto:kusugpa@yahoo.com?subject=KUSUG-PA%20Inquiry" class="kp-submit">
                            <i class="fas fa-envelope"></i> Email KUSUG-PA
                        </a>
                        <a href="tel:+639777165339" class="kp-submit" style="background:var(--kp-forest);">
                            <i class="fas fa-phone"></i> Call Office
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t" style="border-color:var(--kp-border); background:var(--kp-surface-2);">
        <div class="mx-auto max-w-6xl px-5 sm:px-8 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs" style="color:var(--kp-muted);">
            <span>&copy; {{ date('Y') }} KUSUG-PA Planters Association. All rights reserved.</span>
            <span>Check and Voucher System &middot; v1.0</span>
        </div>
    </footer>
</body>
</html>
