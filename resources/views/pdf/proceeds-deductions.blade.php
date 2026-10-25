<!-- resources/views/pdf/proceeds-deductions.blade.php -->
{{-- Legal landscape. Each planter has a THIS WEEK line and a TO DATE line (week 1 through the selected week). --}}
@php
    $money = fn ($v) => round((float) $v, 2) != 0 ? number_format((float) $v, 2) : '-';
    // Printed in parts: page numbers continue, totals (over every planter) only on the last part
    $pageOffset = $pageOffset ?? 0;
    $pageCount = $totalPages ?? max(1, $pages->count());
    $isLastPart = $isLastPart ?? true;
    // Columns after the accounts applied group are what the planter finally receives
    $groups = [
        'PROCEEDS' => ['quedan', 'molasses', 'trucking', 'gross'],
        'MILL DEDUCTIONS' => ['mill', 'fuel_issued', 'net'],
        'ACCOUNTS APPLIED' => ['loan', 'ca', 'others', 'fuel'],
        '' => ['net_paid'],
    ];
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Proceeds &amp; Deductions - {{ $cropYear }} Week {{ $week }}</title>
    <style>
        @page {
            margin: 0.4in 0.3in 0.35in 0.3in;
        }

        body {
            font-family: 'DejaVu Sans Condensed', 'DejaVu Sans', sans-serif;
            font-size: 7pt;
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
            padding: 1.5px 3px;
            vertical-align: middle;
        }

        .center {
            text-align: center;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .ledger th {
            font-weight: bold;
            font-size: 6.3pt;
            text-align: center;
            line-height: 1.2;
            border: 0.6px solid #555;
            background: #eeeeee;
        }

        .ledger td {
            border-left: 0.6px solid #bbb;
            border-right: 0.6px solid #bbb;
        }

        .ledger tr.week td {
            border-top: 0.6px solid #555;
            padding-top: 3px;
        }

        .ledger tr.todate td {
            color: #333;
            background: #f6f6f6;
            border-bottom: 0.6px solid #555;
        }

        .ledger td.period {
            font-size: 6pt;
            color: #555;
            white-space: nowrap;
        }

        .ledger td.strong {
            font-weight: bold;
        }

        .ledger tr.total td {
            font-weight: bold;
            border-top: 1px solid #111;
            background: #e4e4e4;
        }

        .ledger tr.total.todate td {
            border-bottom: 2.5px double #111;
        }
    </style>
</head>

<body>
    @forelse ($pages as $pageIndex => $pagePlanters)
        <div class="page">
            <!-- Header -->
            <table>
                <tr>
                    <td style="width: 30%; vertical-align: top">
                        <div style="font-weight: bold">KABANKALAN UNITED SUGAR PLANTERS ASSN., INC.</div>
                        <div>({{ $org['short_name'] }}) · Kabankalan City, Neg. Occ.</div>
                    </td>
                    <td class="center" style="width: 40%; vertical-align: top">
                        <div style="font-size: 11pt; font-weight: bold; letter-spacing: 1px">PROCEEDS &amp; DEDUCTIONS</div>
                        <div>CROP YEAR {{ $cropYear }} · WEEK {{ $week }}{{ $weekEnding ? ' (ending ' . $weekEnding . ')' : '' }} · TO DATE {{ $toDateLabel }}</div>
                    </td>
                    <td class="num" style="width: 30%; vertical-align: top">
                        <div>DATE ISSUED: {{ $dateIssued }}</div>
                        <div>PAGE {{ $pageOffset + $pageIndex + 1 }} OF {{ $pageCount }}</div>
                    </td>
                </tr>
            </table>

            <table class="ledger" style="margin-top: 8px">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 0.55in">P-NO.</th>
                        <th rowspan="2" style="width: 1.7in">PLANTER NAME</th>
                        <th rowspan="2" style="width: 0.5in">PERIOD</th>
                        @foreach ($groups as $label => $cols)
                            @if ($label !== '')
                                <th colspan="{{ count($cols) }}">{{ $label }}</th>
                            @else
                                <th rowspan="2" style="width: 0.85in">{{ $columns[$cols[0]] }}</th>
                            @endif
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($groups as $label => $cols)
                            @if ($label !== '')
                                @foreach ($cols as $col)
                                    <th style="width: 0.82in">{{ $columns[$col] }}</th>
                                @endforeach
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pagePlanters as $p)
                        @foreach (['week' => 'THIS WK', 'to_date' => 'TO DATE'] as $period => $periodLabel)
                            <tr class="{{ $period === 'week' ? 'week' : 'todate' }}">
                                @if ($period === 'week')
                                    <td rowspan="2" style="vertical-align: top; border-bottom: 0.6px solid #555">{{ $p['code'] }}</td>
                                    <td rowspan="2" style="vertical-align: top; border-bottom: 0.6px solid #555">{{ $p['name'] }}</td>
                                @endif
                                <td class="period">{{ $periodLabel }}</td>
                                @foreach ($columns as $col => $label)
                                    <td class="num {{ in_array($col, ['gross', 'net', 'net_paid']) ? 'strong' : '' }}">{{ $money($p[$period][$col]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach

                    @if ($loop->last && $isLastPart)
                        @foreach (['week' => 'THIS WK', 'to_date' => 'TO DATE'] as $period => $periodLabel)
                            <tr class="total {{ $period === 'week' ? 'week' : 'todate' }}">
                                @if ($period === 'week')
                                    <td colspan="2" rowspan="2">TOTAL ({{ $planters->count() }} planter{{ $planters->count() === 1 ? '' : 's' }})</td>
                                @endif
                                <td class="period">{{ $periodLabel }}</td>
                                @foreach ($columns as $col => $label)
                                    <td class="num">{{ $money($totals[$period][$col]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>

            @if ($loop->last && $isLastPart)
                <p style="margin-top: 8px; font-size: 6.5pt; color: #444">
                    Gross Proceeds − Liens, Charges &amp; Tax − Fuel Issuance = Net Proceeds. Net Proceeds − Accounts Applied (Loan, Cash Advance, Others, Fuel A/C) = Net Paid, as on the Check Voucher.
                    To Date adds up each week's voucher from {{ $toDateLabel }}.
                </p>
            @endif
        </div>
    @empty
        <div class="center" style="margin-top: 2in">No proceeds or deductions for crop year {{ $cropYear }} through week {{ $week }}.</div>
    @endforelse
</body>

</html>
