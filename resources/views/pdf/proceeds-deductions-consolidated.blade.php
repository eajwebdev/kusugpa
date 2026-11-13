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
    <title>Consolidated Proceeds &amp; Deductions - {{ $cropYear }} Week {{ $week }}</title>
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

        .grand td {
            border: 0.6px solid #555;
            text-align: center;
            font-size: 7.5pt;
        }

        .grand th {
            border: 0.6px solid #555;
            background: #eeeeee;
            font-size: 6.8pt;
            padding: 2px 3px;
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
                <div>CROP YEAR {{ $cropYear }} &middot; WEEK {{ $week }}{{ $weekEnding ? ' (ending ' . $weekEnding . ')' : '' }}</div>
            </td>
            <td class="num" style="width: 30%">
                <div>DATE ISSUED: {{ $dateIssued }}</div>
                <div>{{ $planterCount }} PLANTER{{ $planterCount === 1 ? '' : 'S' }}</div>
                <div>{{ number_format($volumes['quedans']) }} QUEDAN{{ $volumes['quedans'] === 1 ? '' : 'S' }}</div>
            </td>
        </tr>
    </table>

    <!-- Grand totals in one line -->
    <p class="head" style="margin: 14px 0 4px">GRAND TOTALS</p>
    <table class="grand">
        <tr>
            <th>NET SUGAR (LKG)</th>
            <th>MOLASSES (MT)</th>
            <th>GROSS PROCEEDS</th>
            <th>QUEDAN LIENS</th>
            <th>TOTAL DEDUCTIONS</th>
            <th>NET PROCEEDS B4 TRKNG ALLW.</th>
        </tr>
        <tr>
            <td>{{ $vol($volumes['sugar_net']) }}</td>
            <td>{{ $vol($volumes['molasses']) }}</td>
            <td>{{ $money($t['gross']) }}</td>
            <td>{{ $money($t['quedan_liens']) }}</td>
            <td>{{ $money($t['deductions']) }}</td>
            <td>{{ $money($t['net_before']) }}</td>
        </tr>
    </table>

    <table style="margin-top: 14px">
        <colgroup>
            <col style="width: 46%">
            <col style="width: 16%">
            <col style="width: 19%">
            <col style="width: 19%">
        </colgroup>

        <!-- Breakdown -->
        <tr>
            <td class="head">BREAKDOWN:</td>
            <td class="num head">VOLUME</td>
            <td></td>
            <td class="num head">AMOUNT</td>
        </tr>
        <tr>
            <td class="in1">TOTAL SUGAR GROSS (LKG)</td>
            <td class="num">{{ $vol($volumes['sugar_gross']) }}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="in1">TOTAL 2% LABOR (LKG)</td>
            <td class="num">{{ $vol($volumes['labor']) }}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="in1">TOTAL NET SUGAR (LKG)</td>
            <td class="num">{{ $vol($volumes['sugar_net']) }}</td>
            <td></td>
            <td class="num">{{ $money($t['sugar']) }}</td>
        </tr>
        <tr>
            <td class="in1">TOTAL MOLASSES (MT)</td>
            <td class="num">{{ $vol($volumes['molasses']) }}</td>
            <td></td>
            <td class="num">{{ $money($t['molasses']) }}</td>
        </tr>
        <tr>
            <td class="in1" style="padding-top: 6px">TOTAL GROSS PROCEEDS</td>
            <td></td>
            <td></td>
            <td class="num rule" style="padding-top: 6px">{{ $money($t['gross']) }}</td>
        </tr>

        <!-- Deductions -->
        <tr>
            <td class="head" colspan="4">DEDUCTIONS/LIENS:</td>
        </tr>
        <tr>
            <td class="in1 head" colspan="4" style="padding-top: 3px">QUEDAN LIENS:</td>
        </tr>
        <tr>
            <td class="in2">01 STATUTORY QUEDAN LIENS</td>
            <td></td>
            <td class="num">{{ $money($t['liens']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="in2">02 INSURANCE</td>
            <td></td>
            <td class="num">{{ $money($t['insurance']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="in1 head" colspan="4" style="padding-top: 3px">OTHER CHARGES:</td>
        </tr>
        <tr>
            <td class="in2">03 WITHHOLDING TAX</td>
            <td></td>
            <td class="num">{{ $money($t['tax']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="in2">04 SERVICE CHARGE</td>
            <td></td>
            <td class="num">{{ $money($t['service']) }}</td>
            <td class="num">{{ $money($t['deductions']) }}</td>
        </tr>

        <!-- Net proceeds -->
        <tr>
            <td style="padding-top: 8px">NET PROCEEDS B4 TRKNG ALLW.</td>
            <td></td>
            <td></td>
            <td class="num rule" style="padding-top: 8px">{{ $money($t['net_before']) }}</td>
        </tr>
        <tr>
            <td>ADD: TOTAL NET TRUCKING ALLOWANCE</td>
            <td></td>
            <td></td>
            <td class="num">{{ $money($t['trucking']) }}</td>
        </tr>
        <tr>
            <td>LESS: TOTAL FUEL ISSUANCE (DIESEL)</td>
            <td></td>
            <td></td>
            <td class="num">({{ $money($t['fuel']) }})</td>
        </tr>
        <tr>
            <td style="padding-top: 8px; font-weight: bold">GRAND TOTAL NET PROCEEDS &gt;&gt;&gt;&gt;</td>
            <td></td>
            <td></td>
            <td class="num rule" style="padding-top: 8px"><span class="double">{{ $money($t['net']) }}</span></td>
        </tr>

        <!-- Accounts applied to reach the checks paid -->
        <tr>
            <td class="head" colspan="4" style="padding-top: 14px">ACCOUNTS APPLIED:</td>
        </tr>
        <tr>
            <td class="in2">LOAN PAYMENT</td>
            <td></td>
            <td class="num">{{ $money($t['loan']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="in2">CASH ADVANCE</td>
            <td></td>
            <td class="num">{{ $money($t['ca']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="in2">OTHERS</td>
            <td></td>
            <td class="num">{{ $money($t['others']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="in2">FUEL A/C</td>
            <td></td>
            <td class="num">{{ $money($t['fuel_ac']) }}</td>
            <td class="num">({{ $money($t['accounts']) }})</td>
        </tr>
        <tr>
            <td style="padding-top: 8px; font-weight: bold">TOTAL NET PAID (CHECKS) &gt;&gt;&gt;&gt;</td>
            <td></td>
            <td></td>
            <td class="num rule" style="padding-top: 8px"><span class="double">{{ $money($t['net_paid']) }}</span></td>
        </tr>
    </table>

    <!-- Rates used -->
    <table style="margin-top: 18px; width: 70%">
        <tr>
            <td colspan="3" style="font-weight: bold">COMPUTATION OF INSURANCE &amp; SERVICE CHARGE</td>
        </tr>
        <tr>
            <td class="in1" style="width: 34%">INSURANCE</td>
            <td style="width: 20%">: SUGAR</td>
            <td>3.00/LKG</td>
        </tr>
        <tr>
            <td></td>
            <td>&nbsp; MOLASSES</td>
            <td>120.00/MT + 30.00</td>
        </tr>
        <tr>
            <td class="in1">SERVICE CHARGE</td>
            <td>: SUGAR</td>
            <td>8.00/LKG</td>
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
    <table class="sign" style="margin-top: 24px">
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
