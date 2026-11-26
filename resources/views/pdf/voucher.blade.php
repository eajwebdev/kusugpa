{{-- Check Voucher on the pre-printed Metrobank check-voucher form (11 in x 23 cm, landscape, 1/2 in margin left and right,
     see config kusugpa.voucher_form). Only the data is printed,
     in the dot-matrix draft font, on the 15 cpi x 6 lpi grid of App\Support\CheckVoucherForm.
     Print at "Actual size" (not "Fit"); a printer that feeds the form off is corrected with VOUCHER_OFFSET_X_MM / _Y_MM. --}}
@php
    $draftFont = str_replace('\\', '/', resource_path('fonts/kp-draft/KPDraft-Regular.ttf'));
    $form = config('kusugpa.voucher_form', []);
    $paperWidth = (float) ($form['paper_width_cm'] ?? 27.94);
    $paperHeight = (float) ($form['paper_height_cm'] ?? 23);
    $margin = (float) ($form['margin_x_cm'] ?? 1.27);
    // dompdf places the absolutely positioned lines from the paper's edge (not inside the margin), so they keep
    // their spot on the form whatever the margin
    $left = \App\Support\CheckVoucherForm::ORIGIN_X_IN + ((float) ($form['offset_x_mm'] ?? 0)) / 25.4;
    // The font's cap top sits 0.2pt below the top of its 8pt line box
    $top = \App\Support\CheckVoucherForm::ORIGIN_Y_IN + ((float) ($form['offset_y_mm'] ?? 0)) / 25.4 - 0.2 / 72;
    $voucherDate = $voucherDate ?? now();
    $receivers = $receivers ?? collect();
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Check Voucher</title>
    <style>
        @font-face { font-family: 'KP Draft'; font-style: normal; font-weight: normal; src: url('{{ $draftFont }}') format('truetype'); }

        @page { size: {{ $paperWidth }}cm {{ $paperHeight }}cm; margin: 0 {{ $margin }}cm; }

        html, body { margin: 0; padding: 0; }

        .page {
            position: relative;
            width: {{ round($paperWidth - 2 * $margin, 3) }}cm;
            height: {{ round($paperHeight, 3) }}cm;
            overflow: hidden;
            page-break-after: always;
        }

        .page:last-child { page-break-after: auto; }

        .ln {
            position: absolute;
            left: {{ round($left, 4) }}in;
            margin: 0;
            padding: 0;
            font-family: 'KP Draft', 'DejaVu Sans Mono', monospace;
            font-size: 8pt;
            line-height: 8pt;
            white-space: pre;
            color: #000;
        }
    </style>
</head>

<body>
    @foreach ($summaryData as $record)
        @php
            // The person authorized to receive this voucher, when set, in place of the planter
            $payee = $receivers[(string) ($record['voucher_key'] ?? '')] ?? $receivers[(string) $record['planter_code']] ?? $record['planter_name'];
            $lines = \App\Support\CheckVoucherForm::lines(\App\Services\PlanterProceeds::compute($record), $record, (string) $payee, $voucherDate);
        @endphp
        <div class="page">
            @foreach ($lines as $line)
                <div class="ln" style="top: {{ round($top + $line['row'] / \App\Support\CheckVoucherForm::LPI, 4) }}in">{{ $line['text'] }}</div>
            @endforeach
        </div>
    @endforeach
</body>

</html>
