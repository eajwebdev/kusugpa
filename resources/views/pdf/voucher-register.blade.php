<!-- resources/views/pdf/voucher-register.blade.php -->
{{-- Legal landscape. One row per Check Voucher with every amount, page totals and the grand total. --}}
@php
    $m = fn ($v) => round((float) $v, 2) != 0 ? number_format((float) $v, 2) : '-';
    $q = fn ($v) => round((float) $v, 3) != 0 ? number_format((float) $v, 3) : '-';
    // [key, heading, formatter]
    $cols = [
        ['lkg_gross', "LKG\nGROSS", $q],
        ['lkg_net', "LKG\nNET", $q],
        ['sugar', "SUGAR\nAMOUNT", $m],
        ['liens', "QUEDAN\nLIENS", $m],
        ['svc_sugar', "SVC CHG\n8.00/LKG", $m],
        ['svc_addl', "SVC CHG\n7.00/TON", $m],
        ['ins_sugar', "INSUR.\n3.00/LKG", $m],
        ['tax_sugar', "W/TAX\nSUGAR", $m],
        ['mol_mt', "MOLASSES\nMT", $q],
        ['mol', "MOLASSES\nAMOUNT", $m],
        ['svc_mol', "SVC CHG\n20.00/MT", $m],
        ['ins_mol', "INS/HDL\nMOLASSES", $m],
        ['tax_mol', "W/TAX\nMOLASSES", $m],
        ['trucking', "TRUCKING\nALLOW.", $m],
        ['fuel_issued', "FUEL\nISSUED", $m],
        ['net', "NET\nPROCEEDS", $m],
        ['loan', "LOAN", $m],
        ['ca', "CASH\nADVANCE", $m],
        ['others', "OTHERS", $m],
        ['fuel_ac', "FUEL\nA/C", $m],
        ['net_paid', "NET PAID\n(CHECK)", $m],
    ];
    $pageCount = max(1, $pages->count());
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Voucher Register - {{ $cropYear }} {{ $periodLabel }}</title>
    <style>
        @page {
            margin: 0.35in 0.3in;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 6pt;
            color: #111;
            margin: 0;
        }

        .page + .page {
            page-break-before: always;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            font-weight: bold;
            font-size: 5.6pt;
            text-align: right;
            padding: 3px 2px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            white-space: pre-line;
            vertical-align: bottom;
        }

        th.left,
        td.left {
            text-align: left;
        }

        td {
            padding: 2.5px 2px;
            text-align: right;
            white-space: nowrap;
            border-bottom: 0.4px solid #ccc;
        }

        td.name {
            white-space: normal;
        }

        tr.sub td {
            font-weight: bold;
            border-top: 0.8px solid #000;
            border-bottom: none;
        }

        tr.grand td {
            font-weight: bold;
            font-size: 6.4pt;
            border-top: 1.2px solid #000;
            border-bottom: 2.5px double #000;
        }
    </style>
</head>

<body>
    @foreach ($pages as $pageIndex => $pageRows)
        <div class="page">
            <table style="margin-bottom: 6px">
                <tr>
                    <td class="left" style="border: none; font-size: 8pt">
                        <b>{{ $org['name'] }}</b><br>
                        <b style="font-size: 9pt">CHECK VOUCHER REGISTER</b> · Crop Year {{ $cropYear }} · {{ $periodLabel }}
                    </td>
                    <td style="border: none; font-size: 7pt">
                        {{ $rows->count() }} voucher(s) · Generated {{ $generatedAt }}<br>
                        Page {{ $pageIndex + 1 }} of {{ $pageCount }}
                    </td>
                </tr>
            </table>

            <table>
                <thead>
                    <tr>
                        <th class="left" style="width: 0.42in">CODE</th>
                        <th class="left" style="width: 1.45in">PLANTER</th>
                        @foreach ($cols as [$key, $heading])
                            <th>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pageRows as $r)
                        <tr>
                            <td class="left">{{ $r['code'] }}</td>
                            <td class="left name">
                                {{ $r['name'] }}
                                @if ($r['qdn'])
                                    <span style="color: #555; font-size: 5.2pt">· QDN {{ $r['qdn'] }}</span>
                                @endif
                            </td>
                            @foreach ($cols as [$key, , $fmt])
                                <td>{{ $fmt($r[$key]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <!-- This page's total -->
                    <tr class="sub">
                        <td class="left" colspan="2">Page total ({{ $pageRows->count() }})</td>
                        @foreach ($cols as [$key, , $fmt])
                            <td>{{ $fmt($pageRows->sum($key)) }}</td>
                        @endforeach
                    </tr>

                    @if ($loop->last)
                        <!-- Grand total of every voucher -->
                        <tr class="grand">
                            <td class="left" colspan="2">GRAND TOTAL ({{ $rows->count() }} vouchers)</td>
                            @foreach ($cols as [$key, , $fmt])
                                <td>{{ $fmt($totals[$key]) }}</td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>

            @if ($loop->last)
                @php
                    // Balance check: the grand totals added back up the way each voucher computes them
                    $t = $totals;
                    $gross = round($t['sugar'] + $t['mol'] + $t['trucking'], 2);
                    $deductions = round($t['liens'] + $t['svc_sugar'] + $t['svc_addl'] + $t['ins_sugar'] + $t['tax_sugar']
                        + $t['svc_mol'] + $t['ins_mol'] + $t['tax_mol'] + $t['fuel_issued'], 2);
                    $netCheck = round($gross - $deductions, 2);
                    $accounts = round($t['loan'] + $t['ca'] + $t['others'] + $t['fuel_ac'], 2);
                    $paidCheck = round($t['net'] - $accounts, 2);
                    // Each voucher's net is rounded once; its parts are rounded one by one, so a centavo per voucher can differ
                    $tolerance = round($rows->count() * 0.01, 2);
                    $netOk = abs($netCheck - $t['net']) <= $tolerance;
                    $paidOk = abs($paidCheck - $t['net_paid']) <= $tolerance;
                    $off = $rows->filter(fn ($r) => abs(round($r['sugar'] + $r['mol'] + $r['trucking'] - $r['liens'] - $r['svc_sugar'] - $r['svc_addl']
                        - $r['ins_sugar'] - $r['tax_sugar'] - $r['svc_mol'] - $r['ins_mol'] - $r['tax_mol'] - $r['fuel_issued'] - $r['net'], 2)) > 0.02
                        || abs(round($r['net'] - $r['loan'] - $r['ca'] - $r['others'] - $r['fuel_ac'] - $r['net_paid'], 2)) > 0.02);
                    $money2 = fn ($v) => number_format((float) $v, 2);
                @endphp
                <table style="width: 62%; margin-top: 10px; font-size: 7pt">
                    <tr>
                        <td class="left" colspan="3" style="font-weight: bold; font-size: 8pt; border-bottom: 1px solid #000">BALANCE CHECK (GRAND TOTALS)</td>
                    </tr>
                    <tr>
                        <td class="left">Sugar + molasses + trucking allowance</td>
                        <td></td>
                        <td>{{ $money2($gross) }}</td>
                    </tr>
                    <tr>
                        <td class="left">Less: liens + service charges (8.00/LKG, 7.00/ton, 20.00/MT) + insurance/handling + w/tax + fuel issued</td>
                        <td></td>
                        <td>({{ $money2($deductions) }})</td>
                    </tr>
                    <tr>
                        <td class="left"><b>Net proceeds, added up</b> · vs NET PROCEEDS column {{ $money2($t['net']) }}</td>
                        <td>{{ $netOk ? 'BALANCED' : 'NOT BALANCED' }} (diff {{ $money2($netCheck - $t['net']) }})</td>
                        <td><b>{{ $money2($netCheck) }}</b></td>
                    </tr>
                    <tr>
                        <td class="left">Less: loan + cash advance + others + fuel A/C</td>
                        <td></td>
                        <td>({{ $money2($accounts) }})</td>
                    </tr>
                    <tr>
                        <td class="left"><b>Net paid, added up</b> · vs NET PAID column {{ $money2($t['net_paid']) }}</td>
                        <td>{{ $paidOk ? 'BALANCED' : 'NOT BALANCED' }} (diff {{ $money2($paidCheck - $t['net_paid']) }})</td>
                        <td style="border-bottom: 2.5px double #000"><b>{{ $money2($paidCheck) }}</b></td>
                    </tr>
                    <tr>
                        <td class="left" colspan="3" style="border: none; color: #333">
                            Every voucher checked the same way: {{ $off->isEmpty() ? 'all ' . $rows->count() . ' balance' : $off->count() . ' do not balance: ' . $off->pluck('code')->implode(', ') }}.
                            A difference of up to one centavo per voucher (here {{ $money2($tolerance) }}) comes from rounding each amount on the voucher.
                        </td>
                    </tr>
                </table>
                <div style="margin-top: 8px; font-size: 6pt; color: #333">
                    Service charge on the voucher = SVC CHG 8.00/LKG (gross LKG, before the 2% labor) + SVC CHG 7.00/TON
                    (net cane of trucking with trans code 06/08/25/36/38) + SVC CHG 20.00/MT (molasses).
                    Net proceeds = sugar + molasses + trucking − liens − service − insurance − tax − fuel issued.
                    Net paid = net proceeds − loan − cash advance − others − fuel A/C.
                </div>
            @endif
        </div>
    @endforeach
</body>

</html>
