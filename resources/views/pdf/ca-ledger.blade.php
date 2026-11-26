{{-- Cash Advance Ledger: per planter (subtotal when more than one advance), grand total, and totals per period --}}
@php
    $n = fn ($v) => number_format((float) $v, 2);
    $m = fn ($v) => abs((float) $v) > 0.004 ? number_format((float) $v, 2) : '';
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('m.d.Y') : '';
    $f = $filters;
    $scope = collect([
        $f['crop_year'] ? 'Crop Year ' . $f['crop_year'] : 'All crop years',
        $f['date_from'] || $f['date_to'] ? ($f['date_from'] ? $date($f['date_from']) : '…') . ' to ' . ($f['date_to'] ? $date($f['date_to']) : '…') : null,
        $f['status'] === 'released' ? 'Released advances' : ucfirst($f['status']),
        $f['picked'] ? $f['picked'] . ' planter(s) selected' : null,
    ])->filter()->implode(' · ');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cash Advance Ledger</title>
    <style>
        @page { margin: 0.4in 0.35in; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.6pt; color: #111; margin: 0; }
        h1 { font-size: 11pt; margin: 0; }
        .org { text-align: center; margin-bottom: 6px; }
        .org p { margin: 0; }
        .meta { width: 100%; margin: 4px 0 6px; font-size: 7.5pt; }
        table.ledger, table.per { width: 100%; border-collapse: collapse; }
        .ledger th, .per th { border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 3px 3px; font-size: 7pt; text-align: left; vertical-align: bottom; }
        .ledger td, .per td { padding: 2px 3px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .code { color: #b42318; font-weight: bold; }
        .red { color: #b42318; }
        .sub td { font-weight: bold; border-top: 1px dotted #777; }
        .hl { background: #fff200; }
        .grand td { font-weight: bold; border-top: 1.5px solid #111; border-bottom: 3px double #111; padding-top: 3px; }
        .muted { color: #555; }
        h2 { font-size: 9pt; margin: 14px 0 4px; }
        .foot { margin-top: 8px; font-size: 6.8pt; color: #444; }
    </style>
</head>
<body>
    <div class="org">
        <h1>{{ $org['name'] ?? 'KABANKALAN UNITED SUGAR-PLANTERS ASSOCIATION, INC.' }}</h1>
        <p><strong>CASH ADVANCE LEDGER</strong></p>
        <p class="muted">{{ $scope }}</p>
    </div>
    <table class="meta"><tr>
        <td>Advances: {{ $totals['count'] }} · Planters: {{ $totals['planters'] }}</td>
        <td style="text-align: right">Printed {{ now()->format('m.d.Y h:i A') }}{{ $printedBy ? ' by ' . $printedBy : '' }}</td>
    </tr></table>

    <table class="ledger">
        <thead><tr>
            <th>PLTR CODE</th><th>DATE</th><th>PLANTER NAME</th><th>CA NO.</th><th class="center">WEEK #</th>
            <th class="num">PRINCIPAL</th><th class="num">INT. %</th><th class="num">INT. AMOUNT</th><th class="num">ADJ.</th><th class="num">TOTAL</th>
            <th class="num red">PRINCIPAL PAYMENT</th><th class="num red">INTEREST PAYMENT</th><th class="num">TOTAL BALANCE</th><th>CREATED BY</th>
        </tr></thead>
        <tbody>
            @forelse ($planters as $g)
                @foreach ($g['rows'] as $i => $r)
                    <tr>
                        <td class="code">{{ $i === 0 ? $r['planter_code'] : '' }}</td>
                        <td>{{ $date($r['date']) }}</td>
                        <td>{{ $r['planter_name'] }}</td>
                        <td>{{ $r['ca_number'] }}{{ in_array($r['status'], ['active', 'approved']) ? '' : ' (' . $r['status'] . ')' }}</td>
                        <td class="center">{{ count($r['weeks']) > 1 ? $r['weeks'][0] . '-' . end($r['weeks']) : ($r['week_no'] ?? '') }}</td>
                        <td class="num">{{ $n($r['principal']) }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($r['rate'], 2), '0'), '.') }}%</td>
                        <td class="num">{{ $n($r['interest']) }}</td>
                        <td class="num">{{ $m($r['adjustment']) }}</td>
                        <td class="num">{{ $n($r['total']) }}</td>
                        <td class="num">{{ $m($r['principal_paid']) }}</td>
                        <td class="num">{{ $m($r['interest_paid']) }}</td>
                        <td class="num">{{ $n($r['balance']) }}</td>
                        <td class="muted">{{ $r['created_by_name'] }}</td>
                    </tr>
                @endforeach
                @if (count($g['rows']) > 1)
                    <tr class="sub">
                        <td colspan="5" class="num muted">{{ count($g['rows']) }} advances</td>
                        <td class="num">{{ $n($g['subtotal']['principal']) }}</td><td></td>
                        <td class="num">{{ $n($g['subtotal']['interest']) }}</td>
                        <td class="num">{{ $m($g['subtotal']['adjustment']) }}</td>
                        <td class="num">{{ $n($g['subtotal']['total']) }}</td>
                        <td class="num">{{ $m($g['subtotal']['principal_paid']) }}</td>
                        <td class="num">{{ $m($g['subtotal']['interest_paid']) }}</td>
                        <td class="num"><span class="hl">&nbsp;{{ $n($g['subtotal']['balance']) }}&nbsp;</span></td><td></td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="14" class="center muted">No cash advances for these filters</td></tr>
            @endforelse
            <tr class="grand">
                <td colspan="5">GRAND TOTAL</td>
                <td class="num">{{ $n($totals['principal']) }}</td><td></td>
                <td class="num">{{ $n($totals['interest']) }}</td>
                <td class="num">{{ $m($totals['adjustment']) }}</td>
                <td class="num">{{ $n($totals['total']) }}</td>
                <td class="num">{{ $n($totals['principal_paid']) }}</td>
                <td class="num">{{ $n($totals['interest_paid']) }}</td>
                <td class="num">{{ $n($totals['balance']) }}</td><td></td>
            </tr>
        </tbody>
    </table>

    <p class="foot">Payments (voucher or cash over the counter) are applied to the interest and late charges first, then to the principal. Adj. = late charges (+) or balance reductions (−).</p>
</body>
</html>
