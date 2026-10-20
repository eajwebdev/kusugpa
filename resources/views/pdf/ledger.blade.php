<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @include('pdf.partials.formal-font')

        @page { margin: 28px 30px 35px; }
        body { font-family: 'Tinos', 'Times New Roman', serif; color: #183126; font-size: 9px; }
        .header { border-bottom: 2px solid #006b38; padding-bottom: 10px; margin-bottom: 14px; }
        .eyebrow { color: #007a42; font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 19px; margin: 4px 0 6px; }
        .meta { color: #63776a; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { padding: 7px 6px; border: 1px solid #b9d3c2; background: #e7f2e9; color: #183b28; text-align: left; font-size: 8px; }
        td { padding: 6px; border: 1px solid #d8e4db; vertical-align: top; overflow-wrap: break-word; }
        tbody tr:nth-child(even) { background: #f6faf7; }
        .number { text-align: right; }
        .empty { text-align: center; padding: 26px; color: #64776b; }
        .footer { margin-top: 12px; color: #64776b; font-size: 7px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="eyebrow">KUSUG-PA / Official Report</div>
        <h1>{{ $title }}</h1>
        <div class="meta">Generated {{ $generatedDate }} &nbsp; | &nbsp; {{ number_format($rows->count()) }} {{ $rows->count() === 1 ? 'record' : 'records' }}</div>
    </div>
    <table>
        <thead><tr>
            @foreach($columns as $column)
                <th class="{{ isset($column['decimals']) ? 'number' : '' }}">{{ $column['label'] }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $column)
                        @php $value = $row->{$column['key']}; @endphp
                        <td class="{{ isset($column['decimals']) ? 'number' : '' }}">
                            {{ isset($column['decimals']) ? number_format((float) $value, $column['decimals']) : ($value ?? '-') }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="empty">No records are available for this report.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">KUSUG-PA Check and Voucher System</div>
</body>
</html>
