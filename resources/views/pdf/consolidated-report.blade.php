<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>KUSUG-PA Consolidated Report</title>
    <style>
        @include('pdf.partials.formal-font')

        @page { margin: 25px 22px 30px; }
        body { font-family: 'Tinos', 'Times New Roman', serif; color: #193126; font-size: 7px; }
        .header { border-bottom: 2px solid #006c38; padding-bottom: 10px; margin-bottom: 12px; }
        .eyebrow { color: #007a42; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { margin: 3px 0 4px; font-size: 18px; line-height: 1.2; }
        .meta { color: #52675a; font-size: 7px; }
        .count { float: right; color: #006c38; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #e7f2e9; color: #173c28; border: 1px solid #b9d1bf; padding: 5px 3px; font-size: 6px; text-align: right; vertical-align: bottom; }
        td { border: 1px solid #d7e2da; padding: 4px 3px; text-align: right; vertical-align: top; overflow-wrap: break-word; }
        tbody tr:nth-child(even) { background: #f6faf7; }
        .left { text-align: left; }
        .center { text-align: center; }
        .name { overflow-wrap: anywhere; }
        .total { font-weight: bold; color: #005b2a; }
        .empty { padding: 24px; text-align: center; color: #60766a; }
        .footer { margin-top: 10px; color: #60766a; font-size: 6px; }
    </style>
</head>
<body>
    @php
        $amountColumns = [
            'ta_amount' => 'TA Amt', 'emi_amount' => 'EMI Amt', 'pat_amount' => 'PAT Amt',
            'cci_fa_amt' => 'CCI FA', 'cci_fb_amt' => 'CCI FB', 'cci_fc_amt' => 'CCI FC',
            'fuel_issuance_amt' => 'Fuel', 'rental_amt' => 'Rental',
            'underload_amt' => 'Underload', 'mudpress_amt' => 'Mudpress',
            'adj_amt' => 'Adj', 'total_summary' => 'Total',
        ];
    @endphp
    <div class="header">
        <div class="eyebrow">KUSUG-PA / Production Accounting</div>
        <h1>Consolidated Report</h1>
        <div class="meta">
            Generated {{ $generatedDate }} &nbsp; | &nbsp; Amounts in PHP; weights in metric tons
            <span class="count">{{ number_format($uploads->count()) }} {{ $uploads->count() === 1 ? 'record' : 'records' }}</span>
        </div>
    </div>

    <table>
        <colgroup>
            <col style="width: 5%"><col style="width: 3%"><col style="width: 5%"><col style="width: 10%"><col style="width: 5%">
            <col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%">
            <col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%">
            <col style="width: 4.8%"><col style="width: 4.8%"><col style="width: 4.8%">
        </colgroup>
        <thead>
            <tr>
                <th class="center">Season</th><th class="center">Wk</th><th class="left">Code</th><th class="left">Planter Name</th><th class="left">Assn</th>
                <th>TA Wt</th><th>TA Amt</th><th>EMI Wt</th><th>EMI Amt</th><th>PAT Wt</th><th>PAT Amt</th>
                <th>CCI FA</th><th>CCI FB</th><th>CCI FC</th><th>Fuel</th><th>Rental</th><th>Underload</th><th>Mudpress</th><th>Adj</th><th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($uploads as $upload)
                <tr>
                    <td class="center">{{ $upload->crop_year ?: '-' }}</td>
                    <td class="center">{{ $upload->week_no ?: '-' }}</td>
                    <td class="left">{{ $upload->planter_code ?: '-' }}</td>
                    <td class="left name">{{ $upload->planter_name ?: '-' }}</td>
                    <td class="left">{{ $upload->assn_name ?: '-' }}</td>
                    <td>{{ number_format((float) $upload->ta_wt, 3) }}</td>
                    <td>{{ number_format((float) $upload->ta_amount, 2) }}</td>
                    <td>{{ number_format((float) $upload->emi_wt, 3) }}</td>
                    <td>{{ number_format((float) $upload->emi_amount, 2) }}</td>
                    <td>{{ number_format((float) $upload->pat_wt, 3) }}</td>
                    <td>{{ number_format((float) $upload->pat_amount, 2) }}</td>
                    @foreach(array_slice($amountColumns, 3, null, true) as $field => $label)
                        <td class="{{ $field === 'total_summary' ? 'total' : '' }}">{{ number_format((float) $upload->{$field}, 2) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="20" class="empty">No consolidated records are available for this report.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">KUSUG-PA Check and Voucher System</div>
</body>
</html>
