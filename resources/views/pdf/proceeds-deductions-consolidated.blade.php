<!-- resources/views/pdf/proceeds-deductions-consolidated.blade.php -->
{{-- Letter portrait. Grand totals of one week, laid out like the mill's consolidated statement. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $vol = fn ($v) => number_format((float) $v, 3);
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Consolidated Proceeds &amp; Deductions - {{ $cropYear }} {{ $week !== null ? 'Week ' . $week : 'All Weeks' }}</title>
    <style>
        @page {
            margin: 0.5in 0.6in;
        }

        body {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8.5pt;
            color: #111;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 1.5px 3px;
            vertical-align: top;
        }

        .center {
            text-align: center;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .head {
            font-weight: bold;
            letter-spacing: 3px;
            padding-top: 8px;
        }

        .in1 {
            padding-left: 16px;
        }

        .in2 {
            padding-left: 32px;
        }

        .rule {
            border-top: 0.8px dashed #111;
        }

        .double {
            border-bottom: 2.5px double #111;
            font-weight: bold;
        }

        /* Sections: plain black and white, prints cleanly on any printer */
        .sheet {
            margin-top: 8px;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
        }

        .sheet th {
            font-weight: bold;
            text-align: left;
            padding: 3px 6px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .sheet th.num {
            text-align: right;
        }

        .sheet td {
            padding: 2px 6px;
        }

        .sheet tr.title td {
            font-weight: bold;
            padding-top: 4px;
        }

        .sheet tr.gap td {
            height: 4px;
        }

        .sheet tr.total td {
            font-weight: bold;
            border-top: 0.8px solid #000;
        }

        .sheet.grand-total td {
            font-size: 9.5pt;
            font-weight: bold;
            padding-top: 6px;
            border-top: 1px solid #000;
        }

        .sign td {
            padding-top: 26px;
        }

        .line {
            border-top: 0.8px solid #111;
            margin: 26px 20px 0;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <table>
        <tr>
            <td style="width: 30%"></td>
            <td class="center" style="width: 40%">
                <div style="font-weight: bold">KABANKALAN UNITED SUGAR PLANTERS ASSN., INC.</div>
                <div>({{ $org['short_name'] }}) Kabankalan City, Neg. Occ.</div>
                <div style="font-size: 11pt; font-weight: bold; letter-spacing: 1px; margin-top: 6px">CONSOLIDATED PROCEEDS &amp; DEDUCTIONS</div>
                <div>CROP YEAR {{ $cropYear }}</div>
                <div>QUEDANS &amp; MOLASSES: {{ !empty($boughtWeek) ? 'BOUGHT WEEK ' . $boughtWeek : ($week !== null ? 'WEEK ' . $week : 'ALL WEEKS') }}</div>
                <div>TRUCKING: {{ $week !== null ? 'WEEK ' . $week : 'ALL WEEKS' }}{{ $weekEnding ? ' (ending ' . $weekEnding . ')' : '' }}</div>
            </td>
            <td class="num" style="width: 30%">
                <div>DATE ISSUED: {{ $dateIssued }}</div>
                <div>{{ $planterCount }} PLANTER{{ $planterCount === 1 ? '' : 'S' }}</div>
                <div>{{ number_format($volumes['quedans']) }} QUEDAN{{ $volumes['quedans'] === 1 ? '' : 'S' }}</div>
            </td>
        </tr>
    </table>

    @php
        // Each section: [label, volume, amount, row kind]; kind: '' line, 'title' bold sub-heading, 'gap' blank, 'total'
        $sections = [
            ['QUEDAN AND MOLASSES', 'VOLUME', 'AMOUNT', [
                ['TOTAL SUGAR GROSS (LKG)', $vol($volumes['sugar_gross']), '', ''],
                ['TOTAL 2% LABOR (LKG)', $vol($volumes['labor']), '', ''],
                ['TOTAL NET SUGAR (LKG)', $vol($volumes['sugar_net']), $money($t['sugar']), ''],
                ['TOTAL MOLASSES (MT)' . (!empty($molWeek) ? ' - WEEK ' . $molWeek : ''), $vol($volumes['molasses']), $money($t['molasses']), ''],
                ['TOTAL', '', $money($t['gross']), 'total'],
            ]],
            ['DEDUCTIONS / LIENS', '', 'AMOUNT', [
                ['DEDUCTIONS/LIENS:', '', '', 'title'],
                ['STATUTORY QUEDAN LIENS', '', $money($t['liens']), ''],
                ['INSURANCE', '', $money($t['insurance']), ''],
                ['HANDLING FEE ' . number_format($handlingRate, 2) . '/MT + ' . number_format($docStamp, 2) . ' DOC STAMP', '', $money($t['handling']), ''],
                ['', '', '', 'gap'],
                ['OTHER CHARGES:', '', '', 'title'],
                ['WITHHOLDING TAX', '', $money($t['tax']), ''],
                ['SERVICE CHARGE', '', $money($t['service']), ''],
                ['STORAGE FEE', '', $money($t['storage']), ''],
                ['TOTAL', '', $money($t['deductions']), 'total'],
            ]],
            ['TRUCKING ALLOWANCE', '', 'AMOUNT', [
                ['TOTAL NET TRUCKING ALLOWANCE', '', $money($t['trucking']), ''],
                ['FUEL A/C (PLANTER ACCNT. APPLIED)', '', '(' . $money($t['fuel_ac']) . ')', ''],
                ['TOTAL TRANSLOADING S.C', '', '(' . $money($t['additional']) . ')', ''],
            ]],
        ];
    @endphp

    @foreach ($sections as [$heading, $volHead, $amtHead, $lines])
        <table class="sheet">
            <colgroup>
                <col style="width: 50%">
                <col style="width: 22%">
                <col style="width: 28%">
            </colgroup>
            <tr>
                <th>{{ $heading }}</th>
                <th class="num">{{ $volHead }}</th>
                <th class="num">{{ $amtHead }}</th>
            </tr>
            @foreach ($lines as $i => [$label, $volume, $amount, $kind])
                <tr class="{{ $i % 2 === 0 ? 'band' : '' }} {{ $kind }}">
                    <td>{{ $label }}</td>
                    <td class="num">{{ $volume }}</td>
                    <td class="num">{{ $amount }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    <!-- Quedan & molasses total - deductions/liens & other charges + net trucking allowance - fuel A/C -->
    <table class="sheet grand-total">
        <colgroup>
            <col style="width: 72%">
            <col style="width: 28%">
        </colgroup>
        <tr>
            <td>GRAND TOTAL NET PROCEEDS</td>
            <td class="num"><span class="double">{{ $money($t['net']) }}</span></td>
        </tr>
    </table>

    <!-- Rates used -->
    <table style="margin-top: 12px; width: 70%">
        <tr>
            <td colspan="3" style="font-weight: bold">COMPUTATION OF INSURANCE &amp; SERVICE CHARGE</td>
        </tr>
        <tr>
            <td class="in1" style="width: 34%">INSURANCE</td>
            <td style="width: 20%">: SUGAR</td>
            <td>3.00/LKG</td>
        </tr>
        <tr>
            <td class="in1">HANDLING FEE</td>
            <td>: MOLASSES</td>
            <td>{{ number_format($handlingRate, 2) }}/MT + {{ number_format($docStamp, 2) }} DOC STAMP</td>
        </tr>
        <tr>
            <td class="in1">SERVICE CHARGE</td>
            <td>: SUGAR</td>
            <td>8.00/LKG (+7.00/TON, TRANS CODE 06/08/25/36/38)</td>
        </tr>
        <tr>
            <td></td>
            <td>&nbsp; MOLASSES</td>
            <td>20.00/MT</td>
        </tr>
        <tr>
            <td class="in1">WITHHOLDING TAX</td>
            <td colspan="2">: 1% OF GROSS</td>
        </tr>
    </table>

    <!-- Signatories -->
    <table class="sign" style="margin-top: 10px">
        <tr>
            <td style="width: 33%">Prepared by:<div class="line"></div></td>
            <td style="width: 33%">Certified Correct:<div class="line"></div></td>
            <td style="width: 34%">Approved by:<div class="line"></div>
                <div class="center">Manager</div>
            </td>
        </tr>
    </table>
</body>

</html>
