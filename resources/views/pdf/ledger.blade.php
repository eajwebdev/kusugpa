<!DOCTYPE html>
<html lang="en">
@php
    // One table per page: dompdf slows down sharply on a single table running over many pages,
    // and an nth-child zebra rule. Row shading is a class instead.
    $rowsPerPage = 30;
    $pages = $rows->values()->chunk($rowsPerPage);
    $partLabel = $partLabel ?? '';
@endphp
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
        th { padding: 7px 6px; border: 1px solid #b9d3c2; background: #e7f2e9; color: #183b28; text-align: left; font-size: 8px; }
        td { padding: 6px; border: 1px solid #d8e4db; vertical-align: top; overflow-wrap: break-word; }
        tr.even { background: #f6faf7; }
        .number { text-align: right; }
        .empty { text-align: center; padding: 26px; color: #64776b; }
        .page-break { page-break-after: always; }
        .footer { margin-top: 12px; color: #64776b; font-size: 7px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="eyebrow">KUSUG-PA / Official Report</div>
        <h1>{{ $title }}</h1>
        <div class="meta">
            Generated {{ $generatedDate }} &nbsp; | &nbsp; {{ number_format($totalRows ?? $rows->count()) }} {{ ($totalRows ?? $rows->count()) === 1 ? 'record' : 'records' }}
            @if ($partLabel !== '')
                &nbsp; | &nbsp; <b>{{ $partLabel }}</b>
            @endif
        </div>
    </div>
    @forelse ($pages as $pageRows)
        <div class="{{ $loop->last ? '' : 'page-break' }}">
            <table>
                <thead><tr>
                    @foreach($columns as $column)
                        <th class="{{ isset($column['decimals']) ? 'number' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @foreach($pageRows as $i => $row)
                        <tr class="{{ $i % 2 ? 'even' : '' }}">
                            @foreach($columns as $column)
                                @php $value = $row->{$column['key']}; @endphp
                                <td class="{{ isset($column['decimals']) ? 'number' : '' }}">
                                    {{ isset($column['decimals']) ? number_format((float) $value, $column['decimals']) : ($value ?? '-') }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <table>
            <tbody><tr><td class="empty">No records are available for this report.</td></tr></tbody>
        </table>
    @endforelse
    <div class="footer">KUSUG-PA Check and Voucher System</div>
</body>
</html>
