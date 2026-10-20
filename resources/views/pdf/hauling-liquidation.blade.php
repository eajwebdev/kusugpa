<!-- resources/views/pdf/hauling-liquidation.blade.php -->
@php
    // Accounting style: zero shows as a dash, negatives in parentheses
    $fmt = function ($value, $decimals = 2) {
        $value = round((float) $value, $decimals);
        if (abs($value) < 0.005) {
            return '-';
        }
        return $value < 0 ? '(' . number_format(abs($value), $decimals) . ')' : number_format($value, $decimals);
    };
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('m/d/Y') : '';
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Hauling Liquidation</title>
    <style>
        @include('pdf.partials.formal-font')

        @page {
            margin: 0.55in 0.7in 0.6in 0.7in;
        }

        body {
            font-family: 'Tinos', 'Times New Roman', serif;
            font-size: 10.5pt;
            color: #111;
            margin: 0;
        }

        .page-break {
            page-break-after: always;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            padding: 3px 4px;
            vertical-align: top;
        }

        /* Header */
        .org {
            text-align: center;
            font-size: 11.5pt;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        .hauler {
            text-align: center;
            font-size: 10pt;
            margin-top: 2px;
        }

        .period {
            text-align: center;
            font-size: 9pt;
            margin-top: 2px;
            color: #333;
        }

        .title {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 3px;
            margin: 10px 0 0 0;
            padding: 5px 0;
            border-top: 1.5px solid #111;
            border-bottom: 1.5px solid #111;
        }

        /* Info block */
        .info {
            margin-top: 10px;
        }

        .info td {
            padding: 2px 4px;
        }

        .info .lbl {
            width: 17%;
            font-size: 8.5pt;
            color: #444;
            text-transform: uppercase;
        }

        .info .val {
            width: 33%;
            font-weight: bold;
            border-bottom: 0.6px solid #999;
        }

        /* Ledger */
        .ledger {
            margin-top: 14px;
        }

        .ledger th {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #333;
            background: #eeeeee;
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 5px 4px;
        }

        .ledger td {
            padding: 3.5px 4px;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .section {
            font-weight: bold;
            font-size: 8.5pt;
            text-transform: uppercase;
        }

        .muted {
            color: #777;
        }

        .gap td {
            padding: 2px;
        }

        .underline {
            border-bottom: 0.8px solid #111;
        }

        .net td {
            font-weight: bold;
            font-size: 10.5pt;
            padding-top: 7px;
        }

        .net .total {
            border-top: 1px solid #111;
            border-bottom: 3px double #111;
        }
    </style>
</head>

<body>
    @foreach ($entries as $entry)
        @php
            $addItems = $entry->items->where('type', 'add')->values();
            $lessItems = $entry->items->where('type', 'less')->values();
        @endphp
        <div class="{{ $loop->last ? '' : 'page-break' }}">
            <!-- Header -->
            <div class="org">KABANKALAN UNITED SUGAR PLANTERS ASSOCIATION, INC. (KUSUG-PA)</div>
            <div class="hauler">{{ $entry->hauler->name }}</div>
            <div class="period">CROP YEAR {{ $entry->crop_year_label }} &nbsp;&middot;&nbsp; WEEK {{ $entry->week_no }}</div>
            <div class="title">HAULING LIQUIDATION</div>

            <!-- Info -->
            <table class="info">
                <tr>
                    <td class="lbl">Week Ending</td>
                    <td class="val">{{ $date($entry->week_ending) }}</td>
                    <td style="width: 4%"></td>
                    <td class="lbl">CV No.</td>
                    <td class="val">{{ $entry->cv_no }}</td>
                </tr>
                <tr>
                    <td class="lbl">Date Released</td>
                    <td class="val">{{ $date($entry->date_released) }}</td>
                    <td></td>
                    <td class="lbl">Check No.</td>
                    <td class="val">{{ $entry->check_no }}</td>
                </tr>
                <tr>
                    <td class="lbl">Payee</td>
                    <td class="val" colspan="4" style="font-size: 10.5pt">{{ $entry->payee_name }}</td>
                </tr>
            </table>

            <!-- Ledger -->
            <table class="ledger">
                <thead>
                    <tr>
                        <th style="width: 12%; text-align: left"></th>
                        <th style="width: 34%; text-align: left">Particulars</th>
                        <th style="width: 13%" class="num">Tonnage (MT)</th>
                        <th style="width: 11%" class="num">Rate / Ton</th>
                        <th style="width: 15%" class="num">Amount</th>
                        <th style="width: 15%" class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Gross hauling -->
                    <tr>
                        <td class="section">Hauling</td>
                        <td>
                            Gross Hauling Amount<br>
                            <span class="muted" style="font-size: 8pt">Truck / Hauler Code: {{ $entry->truck_code ?: '—' }}</span>
                        </td>
                        <td class="num">{{ number_format($entry->tonnage, 3) }}</td>
                        <td class="num">{{ number_format($entry->rate_per_ton, 2) }}</td>
                        <td></td>
                        <td class="num"><b>{{ $fmt($entry->gross_amount) }}</b></td>
                    </tr>
                    <tr class="gap"><td colspan="6"></td></tr>

                    <!-- Additions -->
                    @forelse ($addItems as $i => $item)
                        <tr>
                            <td class="section">{{ $i === 0 ? 'Add:' : '' }}</td>
                            <td>{{ $item->description }}</td>
                            <td></td>
                            <td></td>
                            <td class="num">{{ $fmt($item->amount) }}</td>
                            <td></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="section">Add:</td>
                            <td class="muted">None</td>
                            <td colspan="4"></td>
                        </tr>
                    @endforelse
                    <tr class="gap"><td colspan="6"></td></tr>

                    <!-- Deductions -->
                    @forelse ($lessItems as $i => $item)
                        <tr>
                            <td class="section">{{ $i === 0 ? 'Less:' : '' }}</td>
                            <td>{{ $item->description }}</td>
                            <td></td>
                            <td></td>
                            <td class="num {{ $loop->last ? 'underline' : '' }}">{{ $fmt($item->amount) }}</td>
                            <td></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="section">Less:</td>
                            <td class="muted">None</td>
                            <td colspan="4"></td>
                        </tr>
                    @endforelse

                    <!-- Net -->
                    <tr class="net">
                        <td colspan="4" style="text-align: right">NET HAULING LIQUIDATION</td>
                        <td></td>
                        <td class="num total">&#8369; {{ number_format($entry->net_amount, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>

</html>
