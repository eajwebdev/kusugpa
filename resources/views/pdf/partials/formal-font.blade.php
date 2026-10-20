{{-- Formal report typeface for every PDF: Tinos (metric-compatible with Times New Roman, Apache 2.0, includes ₱).
     Include inside each PDF template's <style> block. Font files: resources/fonts/tinos (dompdf caches metrics in storage/fonts). --}}
@php
    // Plain filesystem path (dompdf rejects file:// URLs for fonts); forward slashes work on Windows and Linux
    $tinos = fn ($file) => str_replace('\\', '/', resource_path('fonts/tinos/' . $file));
@endphp
@font-face { font-family: 'Tinos'; font-style: normal; font-weight: normal; src: url('{{ $tinos('Tinos-Regular.ttf') }}') format('truetype'); }
@font-face { font-family: 'Tinos'; font-style: normal; font-weight: bold; src: url('{{ $tinos('Tinos-Bold.ttf') }}') format('truetype'); }
@font-face { font-family: 'Tinos'; font-style: italic; font-weight: normal; src: url('{{ $tinos('Tinos-Italic.ttf') }}') format('truetype'); }
@font-face { font-family: 'Tinos'; font-style: italic; font-weight: bold; src: url('{{ $tinos('Tinos-BoldItalic.ttf') }}') format('truetype'); }
