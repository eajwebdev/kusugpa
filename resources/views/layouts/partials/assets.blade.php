{{-- KUSUG-PA shared frontend assets: fonts, Tailwind theme (design tokens), design-system stylesheet.
     Included by every full-page layout so the whole system shares one visual language. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="64x64" href="{{ asset('img/logo-64.png') }}">
<link rel="apple-touch-icon" sizes="128x128" href="{{ asset('img/logo-128.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=IBM+Plex+Mono:wght@500&family=IBM+Plex+Sans:wght@400;500;600&display=swap"
    rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
    /*
     * KUSUG-PA design tokens for Tailwind.
     * The original palette names (primary / green / gray / blue / purple ...) are remapped onto the brand
     * system so every existing page inherits the new identity without touching its markup:
     *   primary, green, emerald, teal, lime -> brand greens   (#EAF5ED ... #003F20)
     *   gray, slate, zinc, neutral, stone    -> green-tinted neutrals (text = #172B20)
     *   blue, sky, cyan                       -> muted steel "info"
     *   purple, indigo, violet, fuchsia, pink -> muted plum "alt"
     *   amber, yellow                         -> cane gold (warning)
     *   orange                                -> clay, red -> muted danger
     */
    (function() {
        const brand = {
            50: '#EAF5ED', 100: '#D5EBDB', 200: '#B0D8BC', 300: '#82BF96', 400: '#3E9E65',
            500: '#1B8B4E', 600: '#087A3D', 700: '#005B2A', 800: '#004C24', 900: '#003F20', 950: '#002A15'
        };
        const neutral = {
            50: '#F7FAF8', 100: '#EEF3F0', 200: '#DCE5DF', 300: '#BCCBC2', 400: '#66786C',
            500: '#56675C', 600: '#44544A', 700: '#33423A', 800: '#22302A', 900: '#172B20', 950: '#0E1A13'
        };
        const info = {
            50: '#EDF4F7', 100: '#D9E8EF', 200: '#B6D2DF', 300: '#88B3C8', 400: '#5A93AF',
            500: '#3E7792', 600: '#2E6179', 700: '#254E62', 800: '#1E3F4F', 900: '#17313E'
        };
        const plum = {
            50: '#F4F2F8', 100: '#E7E3F0', 200: '#CFC7E0', 300: '#B0A4CB', 400: '#8D7FB0',
            500: '#70619A', 600: '#5A4C80', 700: '#483D66', 800: '#392F50', 900: '#2B243D'
        };
        const cane = {
            50: '#FBF5E6', 100: '#F5E8C3', 200: '#EBD28F', 300: '#DDB75A', 400: '#CC9C33',
            500: '#B7841F', 600: '#976A14', 700: '#775211', 800: '#5C3F12', 900: '#473112'
        };
        const clay = {
            50: '#FCF1EA', 100: '#F8DFCF', 200: '#F0BE9F', 300: '#E59A6B', 400: '#D67A44',
            500: '#C0602B', 600: '#A04C20', 700: '#7F3B1B', 800: '#653018', 900: '#512815'
        };
        const danger = {
            50: '#FCEEEE', 100: '#F8DADA', 200: '#F0B4B4', 300: '#E48A8A', 400: '#D45F5F',
            500: '#BE3F3F', 600: '#A32F2F', 700: '#832727', 800: '#6A2323', 900: '#561F1F'
        };

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"IBM Plex Sans"', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'sans-serif'],
                        display: ['Fraunces', 'Georgia', 'serif'],
                        mono: ['"IBM Plex Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
                    },
                    colors: {
                        primary: brand, green: brand, emerald: brand, teal: brand, lime: brand,
                        gray: neutral, slate: neutral, zinc: neutral, neutral: neutral, stone: neutral,
                        blue: info, sky: info, cyan: info,
                        purple: plum, indigo: plum, violet: plum, fuchsia: plum, pink: plum,
                        amber: cane, yellow: cane,
                        orange: clay,
                        red: danger, rose: danger,
                        forest: '#005B2A', deep: '#003F20', soft: '#EAF5ED', canvas: '#F7FAF8', ink: '#172B20',
                    },
                    borderRadius: {
                        xl: '0.625rem',
                        '2xl': '0.75rem',
                        '3xl': '1rem',
                    },
                    boxShadow: {
                        sm: '0 1px 2px rgba(23, 43, 32, 0.06)',
                        DEFAULT: '0 1px 3px rgba(23, 43, 32, 0.08), 0 1px 2px rgba(23, 43, 32, 0.05)',
                        md: '0 4px 10px -2px rgba(23, 43, 32, 0.08), 0 2px 4px -2px rgba(23, 43, 32, 0.05)',
                        lg: '0 10px 24px -6px rgba(23, 43, 32, 0.14), 0 4px 8px -4px rgba(23, 43, 32, 0.06)',
                        xl: '0 18px 40px -10px rgba(0, 63, 32, 0.22), 0 6px 12px -6px rgba(23, 43, 32, 0.08)',
                    },
                }
            }
        };
    })();
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('css/kusugpa.css') }}?v={{ @filemtime(public_path('css/kusugpa.css')) }}">
