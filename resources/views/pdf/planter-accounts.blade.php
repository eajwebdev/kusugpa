<!-- resources/views/pdf/planter-accounts.blade.php -->
{{-- Same layout as the Proceeds Sheet (pdf/proceeds-sheet.blade.php), as a ledger without the signature column --}}
@php
    $money = fn ($v) => round((float) $v, 2) != 0 ? number_format((float) $v, 2) : '-';
    $pageCount = max(1, $pages->count());
    // Column widths (inches); the planter name takes the rest of the page width
    $w = ['no' => 0.75, 'amt' => 1.0, 'total' => 1.1];
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Planter Accounts Applied - Week {{ $weekLabel }}</title>
    <style>
        @page {
            margin: 0.45in 0.35in 0.4in 0.35in;
        }

        body {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8.5pt;
            color: #111;
            margin: 0;
        }

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            padding: 0 3px;
            vertical-align: bottom;
        }

        .center {
            text-align: center;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .head th {
            font-weight: normal;
            text-align: right;
            line-height: 1.35;
            padding-bottom: 3px;
            border-bottom: 1px dashed #111;
        }

        .head th.left {
            text-align: left;
        }

        .row td {
            height: 0.3in;
            border-bottom: 0.4px dotted #999;
        }

        .totals td {
            padding-top: 6px;
            font-weight: bold;
            border-top: 1px dashed #111;
        }

        .totals td.total {
            border-bottom: 3px double #111;
        }
    </style>
</head>

<body>
    @forelse ($pages as $pageIndex => $pageRows)
        <div class="page">
            <!-- Header -->
            <div class="center" style="line-height: 1.45">
                <div>KABANKALAN UNITED SUGAR PLANTERS ASSN., INC.</div>
                <div>( {{ implode(' ', mb_str_split(str_replace(' ', '', $org['short_name']))) }} )</div>
                <div>Kabankalan City, Neg. Occ.</div>
            </div>

            <div class="center" style="margin-top: 0.3in">PLANTER ACCOUNTS APPLIED</div>
            <table style="margin-top: 2px">
                <tr>
                    <td style="width: 0.6in"></td>
                    <td style="width: 1.0in">CROP YEAR:</td>
                    <td style="width: 1.1in">{{ $cropYear }}</td>
                    <td style="width: 0.8in">WEEK NO:</td>
                    <td style="width: 0.9in">{{ $weekLabel }}</td>
                    <td style="width: 1.0in">DATE ISSUED:</td>
                    <td style="width: 1.0in">{{ $dateIssued }}</td>
                    <td style="white-space: nowrap">PAGE NO. {{ str_pad($pageIndex + 1, 3, '0', STR_PAD_LEFT) }}</td>
                </tr>
            </table>

            <!-- Planters -->
            <table style="margin-top: 0.25in">
                <thead>
                    <tr class="head">
                        <th class="left" style="width: {{ $w['no'] }}in">P-NO.</th>
                        <th class="left">PLANTER NAME</th>
                        <th style="width: {{ $w['amt'] }}in">LOAN<br>PAYMENT</th>
                        <th style="width: {{ $w['amt'] }}in">CASH<br>ADVANCE</th>
                        <th style="width: {{ $w['amt'] }}in">OTHERS</th>
                        <th style="width: {{ $w['amt'] }}in">FUEL A/C</th>
                        <th style="width: {{ $w['total'] }}in">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pageRows as $r)
                        <tr class="row">
                            <td>{{ $r['code'] }}</td>
                            <td>{{ $r['name'] }}</td>
                            <td class="num">{{ $money($r['loan']) }}</td>
                            <td class="num">{{ $money($r['ca']) }}</td>
                            <td class="num">{{ $money($r['others']) }}</td>
                            <td class="num">{{ $money($r['fuel']) }}</td>
                            <td class="num"><b>{{ $money($r['total']) }}</b></td>
                        </tr>
                    @endforeach

                    <!-- Column totals on the last row of the form -->
                    @if ($loop->last)
                        <tr class="totals">
                            <td colspan="2">TOTAL &nbsp; ({{ $rows->count() }} planter{{ $rows->count() === 1 ? '' : 's' }})</td>
                            <td class="num">{{ $money($rows->sum('loan')) }}</td>
                            <td class="num">{{ $money($rows->sum('ca')) }}</td>
                            <td class="num">{{ $money($rows->sum('others')) }}</td>
                            <td class="num">{{ $money($rows->sum('fuel')) }}</td>
                            <td class="num total">{{ $money($rows->sum('total')) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @unless ($loop->last)
                <div class="center" style="margin-top: 8px; font-size: 7.5pt">continued on page {{ str_pad($pageIndex + 2, 3, '0', STR_PAD_LEFT) }} of {{ str_pad($pageCount, 3, '0', STR_PAD_LEFT) }}</div>
            @endunless
        </div>
    @empty
        <div class="center" style="margin-top: 2in">No loan, cash advance, others or fuel payments applied for crop year {{ $cropYear }} week {{ $weekLabel }}.</div>
    @endforelse
</body>

</html>
