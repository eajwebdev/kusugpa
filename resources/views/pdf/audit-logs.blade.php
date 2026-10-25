<!-- resources/views/pdf/audit-logs.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Audit Logs Report</title>
    <style>
        @include('pdf.partials.formal-font')

        body { font-family: 'Tinos', 'Times New Roman', serif; font-size: 9px; margin: 15px; }
        .header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #334155; padding-bottom: 8px; }
        .header h2 { color: #334155; margin: 0; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #334155; padding: 5px; text-align: left; border: 1px solid #ddd; font-size: 8px; }
        td { padding: 4px 5px; border: 1px solid #ddd; font-size: 8px; }
        .footer { margin-top: 15px; text-align: center; font-size: 7px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KUSUG-PA Audit Logs Report</h2>
        @if($dateFrom || $dateTo)<p>Period: {{ $dateFrom ?? 'Start' }} - {{ $dateTo ?? 'End' }}</p>@endif
        @if (!empty($partLabel))<p><b>{{ $partLabel }}</b></p>@endif
    </div>
    {{-- One table per page: dompdf slows down sharply on a single table over many pages --}}
    @foreach ($logs->values()->chunk(35) as $pageLogs)
    <table style="{{ $loop->last ? '' : 'page-break-after: always;' }}">
        <thead>
            <tr>
                <th>Date/Time</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageLogs as $log)
            <tr>
                <td>{{ $log->created_at->format('M d, Y H:i') }}</td>
                <td>{{ $log->user_name }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->module }}</td>
                <td>{{ $log->description }}</td>
                <td>{{ $log->ip_address }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach
    <div class="footer">Generated on {{ $generatedDate }} | KUSUG-PA System</div>
</body>
</html>