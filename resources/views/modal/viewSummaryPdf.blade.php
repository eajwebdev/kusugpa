<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Summary Report</title>
    <style>
        @include('pdf.partials.formal-font')

        body {
            font-family: 'Tinos', 'Times New Roman', serif;
            font-size: 11px;
            margin: 20px;
        }

        h4.title {
            text-align: center;
            margin-bottom: 20px;
        }

        .info {
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            page-break-inside: auto;
        }

        thead {
            display: table-row-group;
            /* prevents repeating header on new pages */
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }

        th {
            background-color: #f0f0f0;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }
    </style>

</head>

<body>
    <h4 class="title">Summary Report</h4>

    <div class="info">
        <strong>Crop Year:</strong> {{ $cropYear }}<br>
        <strong>Week Range:</strong> {{ $weekFrom ?: 'All' }} to {{ $weekTo ?: 'All' }}
        @if (!empty($partLabel))
            <br><strong>{{ $partLabel }}</strong>
        @endif
    </div>

    {{-- One table per page: dompdf slows down sharply on a single table over many pages --}}
    @php $offset = $offset ?? 0; @endphp
    @forelse ($summaries->values()->chunk(40) as $pageRows)
        <table style="{{ $loop->last ? '' : 'page-break-after: always;' }}">
            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th>Crop Year</th>
                    <th>Week No</th>
                    <th>Planter Code</th>
                    <th>Planter Name</th>
                    <th class="text-right">Net Cane</th>
                    <th class="text-right">Net Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pageRows as $i => $summary)
                    <tr>
                        <td class="text-center">{{ $offset + $i + 1 }}</td>
                        <td>{{ $summary->crop_year }}</td>
                        <td>{{ $summary->week_no }}</td>
                        <td>{{ $summary->planter_code }}</td>
                        <td>{{ $summary->planter_name }}</td>
                        <td class="text-right">{{ number_format($summary->net_cane, 3) }}</td>
                        <td class="text-right">{{ number_format($summary->net_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <table>
            <tbody><tr><td class="text-center">No data available.</td></tr></tbody>
        </table>
    @endforelse
</body>

</html>
