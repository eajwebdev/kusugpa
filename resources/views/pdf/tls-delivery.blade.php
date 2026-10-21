<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Summary of Transloading Delivery</title>
    <style>
        @page { margin: 28px 30px 34px; }
        body { font-family: Helvetica, Arial, sans-serif; color: #111; font-size: 8.5px; }
        .head { text-align: center; line-height: 1.35; }
        .company { font-size: 11px; font-weight: bold; }
        .addr { font-size: 10px; }
        h1 { font-size: 14px; margin: 3px 0 6px; }
        .period { font-size: 9px; font-weight: bold; margin-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th { font-weight: bold; font-size: 8.5px; padding: 3px 4px; border-bottom: 1px solid #111; vertical-align: bottom; }
        td { padding: 1.5px 4px; vertical-align: top; }
        .r { text-align: right; }
        .c { text-align: center; }
        .l { text-align: left; }

        tr.station td { font-weight: bold; font-size: 9.5px; padding-top: 9px; padding-bottom: 3px; }
        tr.assn td { font-weight: bold; font-size: 9px; padding-top: 6px; padding-bottom: 4px; }
        tr.subtotal td { font-weight: bold; padding-top: 3px; padding-bottom: 6px; }
        tr.subtotal td.num { border-top: 1px solid #111; }
        tr.station-total td { font-weight: bold; padding-top: 3px; padding-bottom: 4px; border-top: 1px dashed #555; }
        tr.grand td { font-weight: bold; font-size: 9.5px; padding-top: 5px; border-top: 1.5px solid #111; border-bottom: 3px double #111; }
        tr { page-break-inside: avoid; }
        .empty { text-align: center; padding: 30px; color: #555; }
    </style>
</head>
<body>
    <div class="head">
        <div class="company">URC SUGAR AND RENEWABLES - SONEDCO</div>
        <div class="addr">Sitio San Juan, Barangay Camugao, Kabankalan City 6111, Negros Occidental</div>
        <div class="addr">T.I.N 000-400-016 - VAT</div>
        <h1>Summary of Transloading Delivery</h1>
        <div class="period">
            Date From : {{ $dateFrom ?? '—' }}&nbsp;&nbsp;Date To: {{ $dateTo ?? '—' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="l" style="width: 13%">Hda<br>Ticket</th>
                <th class="c" style="width: 8%">Planter<br>Code</th>
                <th class="l" style="width: 27%">Planter Name</th>
                <th class="l" style="width: 10%">Plate<br>No</th>
                <th class="r" style="width: 11%">Gross<br>Cane Wt</th>
                <th class="r" style="width: 11%">Net<br>Cane Wt</th>
                <th class="r" style="width: 20%">Date Milled</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stations as $station)
                <tr class="station">
                    <td>Transloading :</td>
                    <td class="c">{{ $station['code'] }}</td>
                    <td colspan="2">{{ $station['name'] }}</td>
                    <td colspan="3">{{ $station['hauler'] }}</td>
                </tr>

                @foreach ($station['associations'] as $assn)
                    <tr class="assn">
                        <td>Association:</td>
                        <td class="c">{{ $assn['code'] }}</td>
                        <td colspan="5">{{ $assn['name'] }}</td>
                    </tr>
                    @foreach ($assn['rows'] as $row)
                        <tr>
                            <td>{{ $row['ticket'] }}</td>
                            <td class="c">{{ $row['planter_code'] }}</td>
                            <td>{{ $row['planter_name'] }}</td>
                            <td>{{ $row['plate_no'] }}</td>
                            <td class="r">{{ number_format($row['gross'], 3) }}</td>
                            <td class="r">{{ number_format($row['net'], 3) }}</td>
                            <td class="r">{{ $row['date_milled'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal">
                        <td>{{ $assn['name'] }}</td>
                        <td colspan="3" class="r">Total No. of Records: &nbsp; {{ number_format($assn['records']) }}</td>
                        <td class="r num">{{ number_format($assn['gross'], 3) }}</td>
                        <td class="r num">{{ number_format($assn['net'], 3) }}</td>
                        <td class="r num">
                            {{ $assn['rate'] !== null ? number_format($assn['rate'], 2) : '' }}&nbsp;&nbsp;&nbsp;{{ number_format($assn['amount'], 2) }}
                        </td>
                    </tr>
                @endforeach

                <tr class="station-total">
                    <td colspan="2">{{ $station['name'] }}</td>
                    <td colspan="2" class="r">Total No. of Records: &nbsp; {{ number_format($station['records']) }}</td>
                    <td class="r">{{ number_format($station['gross'], 3) }}</td>
                    <td class="r">{{ number_format($station['net'], 3) }}</td>
                    <td class="r">{{ number_format($station['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No TLS hauling records for the selected period.</td></tr>
            @endforelse

            @if ($totals['records'] > 0)
                <tr class="grand">
                    <td colspan="2">GRAND TOTAL</td>
                    <td colspan="2" class="r">Total No. of Records: &nbsp; {{ number_format($totals['records']) }}</td>
                    <td class="r">{{ number_format($totals['gross'], 3) }}</td>
                    <td class="r">{{ number_format($totals['net'], 3) }}</td>
                    <td class="r">{{ number_format($totals['amount'], 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
