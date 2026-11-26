{{-- TLS Station Performance: stations ranked by Net Cane Wt for one week / month / crop year, and net cane per period --}}
@php
    $t = fn ($v) => $v === null ? '' : number_format((float) $v, 3);
    $pct = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%';
    $matrixSums = array_map(fn ($i) => array_sum(array_map(fn ($r) => $r['values'][$i] ?? 0, $matrix['rows'])), array_keys($matrix['keys']));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TLS Station Performance</title>
    <style>
        @page { margin: 0.45in 0.4in; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #111; margin: 0; }
        .org { text-align: center; margin-bottom: 8px; }
        .org h1 { font-size: 11pt; margin: 0; }
        .org p { margin: 1px 0; }
        h2 { font-size: 9pt; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 3px 4px; font-size: 7.2pt; text-align: left; }
        td { padding: 3px 4px; border-bottom: 1px solid #ddd; }
        .num { text-align: right; white-space: nowrap; }
        .top td { background: #e6f2ea; font-weight: bold; }
        .total td { font-weight: bold; border-top: 1.5px solid #111; border-bottom: 3px double #111; }
        .down { color: #b42318; }
        .up { color: #087a3d; }
        .muted { color: #555; }
        ul { margin: 4px 0 0 14px; padding: 0; }
        li { margin: 2px 0; }
        .best { font-weight: bold; color: #087a3d; }
        .foot { margin-top: 10px; font-size: 6.8pt; color: #444; }
    </style>
</head>
<body>
    <div class="org">
        <h1>{{ $org['name'] ?? 'KABANKALAN UNITED SUGAR-PLANTERS ASSOCIATION, INC.' }}</h1>
        <p><strong>TLS STATION PERFORMANCE REPORT</strong></p>
        <p class="muted">Crop Year {{ $cropYear }} · {{ $periodWord }}: {{ $pickLabel ?? '—' }}{{ $prevLabel ? ' (compared with ' . $prevLabel . ')' : '' }}</p>
        <p class="muted">Printed {{ now()->format('m.d.Y h:i A') }}{{ $printedBy ? ' by ' . $printedBy : '' }}</p>
    </div>

    <h2>RANKING BY NET CANE WT · {{ strtoupper($pickLabel ?? '') }}</h2>
    <table>
        <thead><tr>
            <th>RANK</th><th>TLS STATION</th><th>CODE</th>
            <th class="num">NET CANE WT (t)</th><th class="num">SHARE</th><th class="num">GROSS CANE WT (t)</th>
            <th class="num">LOSS (t)</th><th class="num">LOSS %</th><th class="num">TICKETS</th><th class="num">AVG NET / TICKET</th>
            <th class="num">{{ $prevLabel ? strtoupper($prevLabel) . ' NET' : 'PREVIOUS NET' }}</th><th class="num">CHANGE</th>
        </tr></thead>
        <tbody>
            @foreach ($rows as $r)
                <tr class="{{ $r['rank'] === 1 && $r['net'] > 0 ? 'top' : '' }}">
                    <td>{{ $r['rank'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['code'] }}</td>
                    <td class="num">{{ $t($r['net']) }}</td>
                    <td class="num">{{ $pct($r['share']) }}</td>
                    <td class="num">{{ $t($r['gross']) }}</td>
                    <td class="num">{{ $t($r['loss']) }}</td>
                    <td class="num">{{ $pct($r['lossPct']) }}</td>
                    <td class="num">{{ number_format($r['tickets']) }}</td>
                    <td class="num">{{ $t($r['avgNet']) }}</td>
                    <td class="num">{{ $t($r['prevNet']) }}</td>
                    <td class="num {{ $r['change'] === null ? '' : ($r['change'] < 0 ? 'down' : 'up') }}">{{ $r['change'] === null ? '' : ($r['change'] > 0 ? '+' : '') . $r['change'] . '%' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td></td><td>TOTAL</td><td></td>
                <td class="num">{{ $t($totals['net']) }}</td>
                <td class="num">100%</td>
                <td class="num">{{ $t($totals['gross']) }}</td>
                <td class="num">{{ $t($totals['gross'] - $totals['net']) }}</td>
                <td class="num">{{ $totals['gross'] > 0 ? $pct(($totals['gross'] - $totals['net']) / $totals['gross'] * 100) : '' }}</td>
                <td class="num">{{ number_format($totals['tickets']) }}</td>
                <td></td>
                <td class="num">{{ $prevLabel ? $t($totals['prevNet']) : '' }}</td>
                <td class="num">{{ $prevLabel && $totals['prevNet'] > 0 ? (($totals['net'] - $totals['prevNet']) / $totals['prevNet'] * 100 > 0 ? '+' : '') . round(($totals['net'] - $totals['prevNet']) / $totals['prevNet'] * 100, 1) . '%' : '' }}</td>
            </tr>
        </tbody>
    </table>

    @if ($findings)
        <h2>FINDINGS</h2>
        <ul>
            @foreach ($findings as $f)
                <li>{{ $f }}</li>
            @endforeach
        </ul>
    @endif

    <h2>NET CANE WT (t) PER {{ strtoupper($periodWord) }}</h2>
    <table>
        <thead><tr>
            <th>TLS STATION</th>
            @foreach ($matrix['labels'] as $i => $l)
                <th class="num" style="{{ $matrix['keys'][$i] === $pick ? 'background: #e6f2ea' : '' }}">{{ $l }}</th>
            @endforeach
            <th class="num">TOTAL</th>
        </tr></thead>
        <tbody>
            @foreach ($matrix['rows'] as $m)
                <tr>
                    <td>{{ $m['name'] }} <span class="muted">{{ $m['code'] }}</span></td>
                    @foreach ($m['values'] as $i => $v)
                        @php $isBest = $v > 0 && $v >= max(array_map(fn ($r) => $r['values'][$i] ?? 0, $matrix['rows'])); @endphp
                        <td class="num {{ $isBest ? 'best' : '' }}">{{ $v > 0 ? $t($v) : '-' }}</td>
                    @endforeach
                    <td class="num"><strong>{{ $t(array_sum($m['values'])) }}</strong></td>
                </tr>
            @endforeach
            <tr class="total">
                <td>TOTAL</td>
                @foreach ($matrixSums as $s)
                    <td class="num">{{ $t($s) }}</td>
                @endforeach
                <td class="num">{{ $t(array_sum($matrixSums)) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="foot">From the TLS hauling tickets (TLS trans codes only). Ranked by Net Cane Wt; loss = Gross Cane Wt − Net Cane Wt. Bold green = the station with the most net cane in that period.
        Months are by date milled.</p>
</body>
</html>
