<!-- resources/views/landing.blade.php -->
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kabankalan United Sugar Planters Association Inc. (KUSUG-PA) — crop management, milling coordination, and market advocacy for sugar planters.">
    <meta name="theme-color" content="#003F20">
    <title>KUSUG-PA | Empowering Sugar Planters</title>

    <meta property="og:type" content="website">
    <meta property="og:title" content="KUSUG-PA | Empowering Sugar Planters">
    <meta property="og:description" content="Crop management, milling coordination, and market advocacy for sugar planters in Kabankalan and beyond.">
    <meta property="og:image" content="{{ asset('img/logo-512.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url('/') }}">

    @include('layouts.partials.assets')

    <style>
        :root {
            --kp-parchment: #F7F3E9;
            --kp-hero-gold: #E8B84B;
            --kp-on-dark: #CFE5D6;
            --kp-on-dark-2: #A8C7B4;
        }

        html { scroll-behavior: smooth; }
        html { scroll-padding-top: 86px; }
        body {
            font-family: var(--kp-font-sans);
            background-color: var(--kp-bg);
            color: var(--kp-text);
            letter-spacing: -0.005em;
        }

        .kp-skip { position:absolute; left:-9999px; top:0; z-index:100; padding:10px 16px; background:var(--kp-deep); color:#fff; border-radius:0 0 10px 10px; font-weight:600; }
        .kp-skip:focus { left:0; }

        .kp-shell { position: relative; overflow: clip; }
        .kp-grain::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: .035;
            background-image: radial-gradient(rgba(255,255,255,.9) .5px, transparent .5px);
            background-size: 4px 4px;
        }

        .kp-premium-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            height: 46px;
            padding: 0 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 650;
            letter-spacing: .01em;
            transition: transform .25s var(--kp-ease), box-shadow .25s var(--kp-ease), background-color .25s var(--kp-ease), border-color .25s var(--kp-ease);
        }
        .kp-premium-btn-primary {
            background: linear-gradient(135deg, var(--kp-emerald), var(--kp-forest));
            color: #fff;
            box-shadow: 0 1px 2px rgba(0,63,32,.3), 0 12px 28px -14px rgba(0,63,32,.55);
        }
        .kp-premium-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 1px 2px rgba(0,63,32,.3), 0 18px 34px -14px rgba(0,63,32,.65); }
        .kp-premium-btn-ghost {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.24);
            color: #fff;
            backdrop-filter: blur(8px);
        }
        .kp-premium-btn-ghost:hover { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.34); transform: translateY(-1px); }
        .kp-premium-btn-outline {
            background: #fff;
            border: 1px solid var(--kp-border-strong);
            color: var(--kp-text);
        }
        .kp-premium-btn-outline:hover { border-color: var(--kp-emerald); transform: translateY(-1px); box-shadow: var(--kp-shadow); }

        .kp-premium-card {
            background: linear-gradient(180deg, #fff, var(--kp-surface-2));
            border: 1px solid var(--kp-border);
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(23,43,32,.05), 0 24px 50px -32px rgba(0,63,32,.45);
        }

        .kp-frame {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: var(--kp-surface);
            border: 1px solid var(--kp-border);
            box-shadow: 0 1px 2px rgba(23,43,32,.05), 0 28px 60px -34px rgba(0,63,32,.55);
        }
        .kp-frame::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0), rgba(0,63,32,.06));
            pointer-events: none;
        }

        .kp-zoom { overflow: hidden; }
        .kp-zoom img { transition: transform 1s var(--kp-ease); }
        .kp-zoom:hover img { transform: scale(1.06); }

        .kp-nav-link { position:relative; padding:4px 2px; transition:color .2s var(--kp-ease); }
        .kp-nav-link::after { content:''; position:absolute; left:0; bottom:-2px; height:2px; width:0; background:var(--kp-cane); transition:width .25s var(--kp-ease); }
        .kp-nav-link:hover::after, .kp-nav-link.is-active::after { width:100%; }

        .kp-tick { width:3px; height:16px; border-radius:9999px; background:var(--kp-cane); display:inline-block; }
        .kp-text-on-dark { color: var(--kp-on-dark); }
        .kp-text-on-dark-2 { color: var(--kp-on-dark-2); }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }

        .kp-drawer { max-height:0; overflow:hidden; transition:max-height .3s var(--kp-ease); }
        .kp-drawer.open { max-height:420px; }

        .kp-service-card { position:relative; border-radius: 14px; overflow:hidden; transition: transform .35s var(--kp-ease), border-color .35s var(--kp-ease), box-shadow .35s var(--kp-ease); }
        .kp-service-card:hover { transform: translateY(-8px); border-color: rgba(217,181,74,.55); box-shadow: 0 30px 60px -32px rgba(0,0,0,.68); }
        .kp-service-card:focus-visible { outline: 3px solid var(--kp-cane); outline-offset: 4px; }
        .kp-service-icon { transition: background-color .25s var(--kp-ease), color .25s var(--kp-ease); }
        .kp-service-card:hover .kp-service-icon { background: var(--kp-cane); color: var(--kp-deep); }
        .kp-hero-photo img { object-position: center; }
        .kp-hero-heading { text-wrap: balance; }
        .kp-portal-image { overflow: visible; }
        .kp-portal-image img { width: min(60vw, 750px); max-width: none; height: clamp(340px, 30vw, 440px); object-fit: cover; object-position: center; margin-left: -12%; }
        @media (min-width: 1024px) {
            .kp-membership-visual { margin-left: calc((min(100vw, 80rem) - 100vw) / 2 - 40px); }
            .kp-membership-visual .kp-frame { border: 0; border-radius: 0; box-shadow: none; }
        }

        @media (max-width: 1023px) {
            #kpHeader { background: rgba(0,63,32,.92); backdrop-filter: blur(10px); }
            .kp-portal-image img { width: 100%; height: auto; margin-left: 0; object-fit: contain; }
        }
        @media (max-width: 640px) {
            .kp-premium-btn { min-height: 48px; height: auto; padding: 12px 18px; }
        }
        @media (max-width: 420px) {
            .kp-header-signin { width: 44px; padding: 0; justify-content: center; }
            .kp-header-signin span { display: none; }
        }
    </style>
</head>
<body class="min-h-screen">
    @php
        $loginUrl = Route::has('getLogin') ? route('getLogin') : url('/login');
    @endphp

    <a href="#main" class="kp-skip">Skip to content</a>

    <!-- Header -->
    <header id="kpHeader" class="absolute inset-x-0 top-0 z-30 transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6 lg:px-10 h-20 flex items-center justify-between">
            <a href="#top" class="flex items-center gap-3">
                <img src="{{ asset('img/logo-64.png') }}" alt="KUSUG-PA logo" width="46" height="46" decoding="async" class="h-11 w-11 rounded-full object-contain">
                <div class="leading-none text-white">
                    <span class="font-display text-xl font-bold block">KUSUG-PA</span>
                    <span class="hidden sm:block mt-1 text-[9px] uppercase tracking-[0.16em] text-[#A8C7B4] leading-[1.45]">Kabankalan United Sugar<br>Planters Association Inc.</span>
                </div>
            </a>

            <nav class="hidden lg:flex items-center gap-8 text-[13px] font-medium text-white/90">
                <a href="#about" class="kp-nav-link">About</a>
                <a href="#services" class="kp-nav-link">Services</a>
                <a href="#portal" class="kp-nav-link">Planter Portal</a>
                <a href="{{ route('manual') }}" class="kp-nav-link">User Guide</a>
                <a href="#membership" class="kp-nav-link">Membership</a>
                <a href="#contact" class="kp-nav-link">Contact</a>
            </nav>

            <div class="flex items-center gap-2 sm:gap-4">
                <span class="hidden lg:block w-px h-6 bg-white/20"></span>
                <button id="kpMenuBtn" class="lg:hidden inline-grid place-items-center h-11 w-11 rounded-lg border border-white/20 text-white" aria-label="Open menu" aria-controls="kpDrawer" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="{{ $loginUrl }}" class="kp-header-signin inline-flex items-center gap-2 h-11 px-4 rounded-lg bg-primary-600 text-white text-sm font-semibold shadow-[0_1px_2px_rgba(0,63,32,.35)] hover:bg-primary-700 transition-colors" aria-label="Sign in to the planter portal">
                    <i class="fas fa-user text-xs" aria-hidden="true"></i> <span>Sign in</span>
                </a>
                @include('layouts.partials.manual-help-button', ['manualHelpClass' => 'kp-manual-help--on-dark'])
            </div>
        </div>

        <div id="kpDrawer" class="kp-drawer lg:hidden" style="background: rgba(0,63,32,.96);" inert>
            <div class="px-6 py-3 flex flex-col gap-1 text-sm font-medium text-white/85">
                <a href="#about" class="py-2.5 border-b border-white/10">About</a>
                <a href="#services" class="py-2.5 border-b border-white/10">Services</a>
                <a href="#portal" class="py-2.5 border-b border-white/10">Planter Portal</a>
                <a href="{{ route('manual') }}" class="py-2.5 border-b border-white/10">User Guide</a>
                <a href="#membership" class="py-2.5 border-b border-white/10">Membership</a>
                <a href="#contact" class="py-2.5">Contact</a>
            </div>
        </div>
    </header>

    <main id="main">
        <!-- Hero -->
        <section id="top" class="kp-grain relative isolate overflow-hidden min-h-[620px] lg:min-h-[610px] flex items-center text-white">
            <picture class="kp-hero-photo absolute inset-0">
                <source srcset="{{ asset('img/landing-premium/hero-cane-field.webp') }}" type="image/webp">
                <img src="{{ asset('img/landing-premium/hero-cane-field.png') }}" alt="" loading="eager" fetchpriority="high" width="2056" height="765" class="w-full h-full object-cover object-center">
            </picture>
            <div class="absolute inset-0" style="background:
                radial-gradient(1000px 520px at 82% 12%, rgba(217,181,74,.12), transparent 62%),
                linear-gradient(90deg, rgba(0,43,25,.96) 0%, rgba(0,51,28,.9) 31%, rgba(0,50,28,.48) 60%, rgba(0,43,25,.04) 100%);"></div>
            <div class="absolute inset-x-0 bottom-0 h-[30%]" style="background:linear-gradient(0deg, rgba(0,63,32,.72), transparent);"></div>
            <div class="absolute inset-x-0 top-0 h-32" style="background:linear-gradient(180deg, rgba(0,63,32,.55), transparent);"></div>

            <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10 w-full pt-32 pb-16 lg:pt-28 lg:pb-4">
                <div class="max-w-[750px]">
                    <span class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-[rgba(217,181,74,.14)] border border-[rgba(217,181,74,.38)] backdrop-blur-md shadow-[0_12px_30px_-18px_rgba(0,0,0,.8)]">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#F5E8C3]">Kabankalan United Sugar Planters Association Inc.</span>
                    </span>

                    <h1 class="kp-hero-heading font-display mt-7 text-[42px] sm:text-[52px] lg:text-[54px] font-semibold leading-[1.05] tracking-[-0.028em] text-white">
                        Empowering Sugar Planters.
                        <span class="block bg-gradient-to-r from-[#E8B84B] via-[#F0CE71] to-[#B78E2B] bg-clip-text text-transparent">Strengthening Our<br class="hidden sm:block"> Community.</span>
                    </h1>

                    <p class="mt-6 text-[16px] lg:text-[17px] leading-[1.68] kp-text-on-dark max-w-[58ch]">
                        Driven by unity and progress, KUSUG-PA provides the tools, advocacy, and resources you need for a prosperous harvest — from planting and milling to market price and policy.
                    </p>

                    <div class="mt-7 flex flex-wrap gap-3.5">
                        <a href="#membership" class="kp-premium-btn kp-premium-btn-primary">
                            <i class="fas fa-users" aria-hidden="true"></i> Join KUSUG-PA Today
                        </a>
                        <a href="#portal" class="kp-premium-btn kp-premium-btn-ghost">
                            <i class="fas fa-chart-line"></i> Explore Planter Portal
                        </a>
                    </div>

                    <div class="mt-7 flex flex-wrap items-center gap-x-9 gap-y-3 text-[13px] text-[#D5E8D9]">
                        <span class="inline-flex items-center gap-2"><i class="fas fa-seedling text-amber-300" aria-hidden="true"></i> Rooted in agriculture</span>
                        <span class="inline-flex items-center gap-2"><i class="fas fa-industry text-amber-300" aria-hidden="true"></i> Milling coordination</span>
                        <span class="inline-flex items-center gap-2"><i class="fas fa-users text-amber-300" aria-hidden="true"></i> Planter community</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- About -->
        <section id="about" class="kp-reveal relative overflow-hidden py-12 lg:py-8" style="background:var(--kp-parchment);">
            <div class="absolute -top-24 -left-24 w-[420px] h-[420px] rounded-full opacity-60 pointer-events-none" style="background:radial-gradient(closest-side, rgba(8,122,61,.14), transparent);"></div>
            <div class="absolute -bottom-24 -right-20 opacity-[0.05] pointer-events-none" aria-hidden="true">
                <i class="fas fa-leaf text-[340px]" style="color:var(--kp-emerald);"></i>
            </div>

            <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
                <div class="grid lg:grid-cols-12 gap-10 lg:gap-14 items-center">
                    <div class="lg:col-span-5">
                        <p class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.2em] text-primary-600">
                            <span class="kp-tick"></span> About KUSUG-PA
                        </p>
                        <h2 class="font-display mt-4 text-[36px] lg:text-[44px] leading-[1.1] tracking-[-0.022em] text-ink max-w-[15ch]">
                            A United Voice for Sugar Planters in Kabankalan
                        </h2>
                        <p class="mt-5 text-[15px] leading-[1.75] text-gray-500 max-w-[46ch]">
                            KUSUG-PA brings sugarcane planters in Kabankalan together through shared resources, crop records, milling coordination, and a stronger collective voice. Our community grows stronger when we work together.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="#membership" class="kp-premium-btn kp-premium-btn-primary">
                                Learn More About Us <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                            <a href="#contact" class="kp-premium-btn kp-premium-btn-outline">
                                Contact Association
                            </a>
                        </div>
                    </div>

                    <div class="lg:col-span-7 relative">
                        <div class="kp-frame kp-zoom h-[300px] lg:h-[300px]">
                            <picture class="block h-full">
                                <source srcset="{{ asset('img/landing-premium/about-cane-closeup.webp') }}" type="image/webp">
                                <img src="{{ asset('img/landing-premium/about-cane-closeup.png') }}" alt="Sunlit sugarcane field" loading="lazy" class="w-full h-full object-cover">
                            </picture>
                        </div>

                        <div class="relative lg:ml-auto lg:-mr-4 lg:mt-[-74px] lg:max-w-[90%] rounded-[18px] shadow-[0_28px_60px_-28px_rgba(0,63,32,.55)] px-8 py-7 grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-0 sm:divide-x sm:divide-white/10" style="background: linear-gradient(135deg, #004524, var(--kp-deep));">
                            @php
                                $stats = [
                                    ['icon'=>'fa-users','title'=>'Stronger Communities','body'=>'United for a brighter future'],
                                    ['icon'=>'fa-leaf','title'=>'Sustainable Farming','body'=>'Better practices for generations'],
                                    ['icon'=>'fa-chart-simple','title'=>'Greater Opportunities','body'=>'From field to market'],
                                ];
                            @endphp
                            @foreach($stats as $st)
                            <div class="sm:px-6 first:sm:pl-0 last:sm:pr-0 text-center sm:text-left">
                                <div class="h-10 w-10 rounded-full grid place-items-center bg-[rgba(217,181,74,.16)] border border-[rgba(217,181,74,.35)] text-amber-300">
                                    <i class="fas {{ $st['icon'] }}"></i>
                                </div>
                                <div class="mt-4 text-[13px] font-bold text-white leading-tight">{{ $st['title'] }}</div>
                                <div class="mt-1.5 text-[11px] leading-snug kp-text-on-dark-2">{{ $st['body'] }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services -->
        <section id="services" class="kp-reveal relative overflow-hidden py-10 lg:py-6" style="background:var(--kp-deep);">
            <picture class="absolute inset-0" aria-hidden="true">
                <source srcset="{{ asset('img/landing-premium/about-cane-closeup.webp') }}" type="image/webp">
                <img src="{{ asset('img/landing-premium/about-cane-closeup.png') }}" alt="" loading="lazy" class="w-full h-full object-cover opacity-25 mix-blend-luminosity">
            </picture>
            <div class="absolute inset-0" style="background:
                radial-gradient(900px 420px at 15% 8%, rgba(8,122,61,.28), transparent 62%),
                linear-gradient(180deg, rgba(0,63,32,.88), rgba(0,42,21,.96));"></div>
            <div class="absolute inset-x-0 bottom-0 h-24" style="background:linear-gradient(0deg, var(--kp-deep), transparent);"></div>

            <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
                <div class="max-w-6xl mx-auto text-center">
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-300">Our Services</p>
                    <h2 class="font-display mt-3 text-[32px] lg:text-[42px] font-semibold leading-[1.08] tracking-[-0.02em] text-white">Tools and Support for a Prosperous Harvest</h2>
                    <p class="mt-2 text-[15px] kp-text-on-dark max-w-2xl mx-auto">KUSUG-PA provides essential services to help our members grow, plan, and succeed.</p>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @php
                        $services = [
                            ['img'=>'service-tractor','icon'=>'fa-seedling','title'=>'Crop Management & Records','body'=>'Guidance and record keeping tools for better farm planning.','href'=>'#portal'],
                            ['img'=>'service-milling','icon'=>'fa-industry','title'=>'Milling Coordination','body'=>'Milling schedules, delivery records, and logistics in one place.','href'=>'#portal'],
                            ['img'=>'service-market','icon'=>'fa-chart-line','title'=>'Market Watch & Prices','body'=>'Price information and industry updates for informed decisions.','href'=>'#portal'],
                            ['img'=>'service-community','icon'=>'fa-users','title'=>'Planter Support & Advocacy','body'=>'Shared resources and a stronger voice for planters.','href'=>'#membership'],
                        ];
                    @endphp
                    @foreach($services as $s)
                    <a href="{{ $s['href'] }}" class="kp-service-card group block border border-white/20 bg-[#00351E]/85 backdrop-blur-sm" aria-label="Learn more about {{ $s['title'] }}">
                        <div class="kp-zoom h-[110px]">
                            <picture class="block h-full">
                                <source srcset="{{ asset('img/landing-premium/'.$s['img'].'.webp') }}" type="image/webp">
                                <img src="{{ asset('img/landing-premium/'.$s['img'].'.png') }}" alt="" loading="lazy" class="w-full h-full object-cover">
                            </picture>
                        </div>
                        <div class="relative p-4 min-h-[160px]">
                            <div class="kp-service-icon absolute left-4 top-4 h-10 w-10 rounded-full grid place-items-center border border-[rgba(217,181,74,.4)] bg-[rgba(217,181,74,.16)] text-amber-300">
                                <i class="fas {{ $s['icon'] }}"></i>
                            </div>
                            <h3 class="ml-14 text-[15px] font-bold text-white leading-snug">{{ $s['title'] }}</h3>
                            <p class="ml-14 mt-2 text-[12px] leading-relaxed kp-text-on-dark-2">{{ $s['body'] }}</p>
                            <div class="absolute left-4 bottom-4 h-8 w-8 rounded-full grid place-items-center bg-amber-300 text-[#003F20] transition-colors" aria-hidden="true">
                                <i class="fas fa-arrow-right text-xs"></i>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Planter Portal -->
        <section id="portal" class="kp-reveal relative overflow-hidden py-12 lg:py-4" style="background:var(--kp-parchment);">
            <div class="absolute top-0 right-0 w-[520px] h-[520px] rounded-full opacity-70 pointer-events-none" style="background:radial-gradient(closest-side, rgba(217,181,74,.16), transparent);"></div>
            <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
                <div class="grid lg:grid-cols-[42%_58%] gap-10 lg:gap-0 items-center">
                    <div>
                        <p class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.2em] text-primary-600">
                            <span class="kp-tick"></span> Planter Portal
                        </p>
                        <h2 class="font-display mt-4 text-[36px] lg:text-[44px] leading-[1.1] tracking-[-0.022em] text-ink max-w-[15ch]">
                            Your Farm Information in One Place
                        </h2>
                        <p class="mt-5 text-[15px] leading-[1.75] text-gray-500">
                            Access crop records, milling information, prices, and important updates through the KUSUG-PA Planter Portal.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ $loginUrl }}" class="kp-premium-btn kp-premium-btn-primary">
                                Explore Planter Portal <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                            <a href="#membership" class="kp-premium-btn kp-premium-btn-outline">
                                Membership Benefits
                            </a>
                        </div>
                    </div>

                    <div class="relative kp-portal-image">
                        <picture>
                            <source srcset="{{ asset('img/landing-premium/portal-dashboard-cutout.webp') }}" type="image/webp">
                            <img src="{{ asset('img/landing-premium/portal-dashboard-cutout.png') }}" alt="Illustrative laptop and phone view of the planter portal" loading="lazy" width="1536" height="1024" class="block">
                        </picture>
                    </div>
                </div>
            </div>
        </section>

        <!-- Membership -->
        <section id="membership" class="kp-reveal kp-grain relative overflow-hidden py-12 lg:py-0" style="background:var(--kp-deep); color:#fff;">
            <div class="absolute inset-0" style="background:
                radial-gradient(700px 380px at 12% 15%, rgba(8,122,61,.32), transparent 62%),
                radial-gradient(600px 320px at 85% 85%, rgba(217,181,74,.14), transparent 62%);"></div>
            <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
                <div class="grid lg:grid-cols-12 gap-10 lg:gap-12 items-center">
                    <div class="kp-membership-visual lg:col-span-4">
                        <div class="kp-frame kp-zoom h-[290px] lg:h-[290px]">
                            <picture class="block h-full">
                                <source srcset="{{ asset('img/landing-premium/membership-workshop.webp') }}" type="image/webp">
                                <img src="{{ asset('img/landing-premium/membership-workshop.png') }}" alt="Sugarcane planters talking in a field" loading="lazy" class="w-full h-full object-cover">
                            </picture>
                        </div>
                    </div>

                    <div class="lg:col-span-4">
                        <p class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.2em] text-amber-300">
                            <span class="kp-tick"></span> Membership
                        </p>
                        <h2 class="font-display mt-4 text-[30px] lg:text-[38px] leading-[1.12] text-white">Be Part of a Stronger Planter Community</h2>
                        <p class="mt-4 text-[15px] leading-[1.75] kp-text-on-dark">
                            Join KUSUG-PA and gain access to valuable resources, representation, and programs designed for your success as a sugarcane planter.
                        </p>
                        <a href="#contact" class="kp-premium-btn kp-premium-btn-primary mt-7">
                            Ask About Membership <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                        </a>
                    </div>

                    <div class="lg:col-span-4">
                        @php
                            $benefits = [
                                ['icon'=>'fa-leaf','title'=>'Access to services and programs','body'=>'Get the support you need for a better harvest.'],
                                ['icon'=>'fa-users','title'=>'A stronger collective voice','body'=>'Representation in key industry discussions.'],
                                ['icon'=>'fa-chart-simple','title'=>'Opportunities for growth','body'=>'Be part of a progressive and united community.'],
                            ];
                        @endphp
                        <div class="space-y-7">
                            @foreach($benefits as $b)
                            <div class="flex items-start gap-4 pb-7 last:pb-0 border-b border-white/10 last:border-0">
                                <div class="h-11 w-11 shrink-0 rounded-full grid place-items-center border border-white/15 bg-white/10 text-amber-300">
                                    <i class="fas {{ $b['icon'] }}"></i>
                                </div>
                                <div>
                                    <div class="text-[14px] font-bold text-white">{{ $b['title'] }}</div>
                                    <div class="mt-1 text-[12.5px] kp-text-on-dark-2">{{ $b['body'] }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section id="contact" class="kp-reveal relative overflow-hidden py-10 lg:py-6" style="background:var(--kp-parchment);">
            <div class="absolute -bottom-32 -left-20 opacity-[0.05] pointer-events-none" aria-hidden="true">
                <i class="fas fa-seedling text-[300px]" style="color:var(--kp-emerald);"></i>
            </div>
            <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
                <div class="grid md:grid-cols-2 lg:grid-cols-12 gap-5 items-stretch">
                    <div class="lg:col-span-3">
                        <p class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.2em] text-primary-600">
                            <span class="kp-tick"></span> Contact Us
                        </p>
                        <h2 class="font-display mt-4 text-[34px] lg:text-[40px] leading-[1.1] tracking-[-0.02em] text-ink">Get in Touch</h2>
                        <p class="mt-4 text-[14px] leading-relaxed text-gray-500">Have a question about membership or planter services? Visit our team in Kabankalan City.</p>
                    </div>

                    <div class="lg:col-span-3">
                        <div class="kp-premium-card p-5 h-full flex flex-col justify-center">
                            <div class="h-11 w-11 rounded-full grid place-items-center bg-primary-50 text-primary-600">
                                <i class="fas fa-location-dot" aria-hidden="true"></i>
                            </div>
                            <h3 class="font-display mt-5 text-[23px] text-ink">Visit KUSUG-PA</h3>
                            <p class="mt-2 text-[14px] leading-relaxed text-gray-500">Kabankalan City, Negros Occidental</p>
                            <a href="https://www.google.com/maps/search/?api=1&amp;query=Kabankalan+City%2C+Negros+Occidental" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex items-center gap-2 text-[13px] font-semibold text-primary-700 hover:text-primary-600">
                                View Kabankalan on map <i class="fas fa-arrow-up-right-from-square text-[11px]" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <div class="kp-frame kp-zoom h-full min-h-[190px]">
                            <picture class="block h-full">
                                <source srcset="{{ asset('img/landing-premium/contact-office.webp') }}" type="image/webp">
                                <img src="{{ asset('img/landing-premium/contact-office.png') }}" alt="Illustrative agricultural association office building" loading="lazy" class="w-full h-full object-cover">
                            </picture>
                        </div>
                    </div>
                    <div class="lg:col-span-3">
                        <a href="https://www.google.com/maps/search/?api=1&amp;query=Kabankalan+City%2C+Negros+Occidental" target="_blank" rel="noopener noreferrer" class="kp-frame block h-full min-h-[190px]" aria-label="View Kabankalan City on Google Maps">
                            <picture class="block h-full">
                                <source srcset="{{ asset('img/landing-premium/contact-map.webp') }}" type="image/webp">
                                <img src="{{ asset('img/landing-premium/contact-map.png') }}" alt="Illustrative map with location marker" loading="lazy" class="w-full h-full object-cover">
                            </picture>
                            <span class="absolute bottom-3 left-3 rounded-md bg-white/95 px-3 py-1.5 text-[11px] font-semibold text-primary-800 shadow-sm">Kabankalan City map</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer style="background:var(--kp-deep); color:#fff;">
        <div class="mx-auto max-w-7xl px-6 lg:px-10 py-6 grid lg:grid-cols-[auto_1px_1.4fr_1px_auto] gap-8 items-center">
            <div class="flex items-center gap-3">
                <img src="{{ asset('img/logo-64.png') }}" alt="" width="56" height="56" decoding="async" class="h-14 w-14 rounded-full object-contain opacity-90">
                <div class="leading-none">
                    <span class="font-display text-xl font-bold block">KUSUG-PA</span>
                    <span class="block mt-1 text-[9px] uppercase tracking-[0.14em] text-[#8FBBA0] leading-[1.45]">Kabankalan United Sugar<br>Planters Association Inc.</span>
                </div>
            </div>
            <div class="hidden lg:block w-px self-stretch bg-white/12"></div>
            <p class="text-[12.5px] leading-relaxed kp-text-on-dark-2 max-w-[46ch]">
                KUSUG-PA connects sugarcane planters in Kabankalan through shared information, coordination, and a commitment to a stronger agricultural community.
            </p>
            <div class="hidden lg:block w-px self-stretch bg-white/12"></div>
            <div>
                <div class="text-[13px] font-bold mb-2.5">Quick Links</div>
                <nav class="flex flex-wrap gap-x-6 gap-y-1 text-[12px] kp-text-on-dark-2">
                    <a href="#about" class="hover:text-white transition-colors">About</a>
                    <a href="#services" class="hover:text-white transition-colors">Services</a>
                    <a href="#portal" class="hover:text-white transition-colors">Planter Portal</a>
                    <a href="#membership" class="hover:text-white transition-colors">Membership</a>
                    <a href="#contact" class="hover:text-white transition-colors">Contact</a>
                </nav>
            </div>
        </div>
        <div class="border-t border-white/10 px-6 lg:px-10 py-3 flex flex-col md:flex-row items-center justify-between gap-2 text-[11.5px] kp-text-on-dark-2">
            <span>&copy; {{ date('Y') }} Kabankalan United Sugar Planters Association, Inc. All rights reserved.</span>
            <span class="inline-flex items-center gap-1.5">A stronger sugar industry. A brighter Kabankalan. <i class="fas fa-leaf text-amber-300"></i></span>
        </div>
    </footer>

    <script>
        (function () {
            var btn = document.getElementById('kpMenuBtn');
            var drawer = document.getElementById('kpDrawer');
            function setMenu(open) {
                drawer.classList.toggle('open', open);
                drawer.inert = !open;
                btn.setAttribute('aria-expanded', String(open));
                btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
                btn.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
            }
            btn && btn.addEventListener('click', function () {
                setMenu(!drawer.classList.contains('open'));
            });
            drawer && drawer.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function () { setMenu(false); });
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && drawer.classList.contains('open')) {
                    setMenu(false);
                    btn.focus();
                }
            });

            var sections = document.querySelectorAll('main section[id]');
            var links = document.querySelectorAll('.kp-nav-link');
            if ('IntersectionObserver' in window) {
                var spy = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        links.forEach(function (l) { l.classList.toggle('is-active', l.getAttribute('href') === '#' + e.target.id); });
                    }
                });
                }, { rootMargin: '-40% 0px -55% 0px' });
                sections.forEach(function (s) { spy.observe(s); });
            }
        })();
    </script>
</body>
</html>
