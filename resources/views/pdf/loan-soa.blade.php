<!-- resources/views/pdf/loan-soa.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statement of Account - {{ $loan->loan_number }}</title>
    <style>
        @include('pdf.partials.formal-font')

        body { font-family: 'Tinos', 'Times New Roman', serif; font-size: 11px; margin: 25px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #7c3aed; padding-bottom: 10px; }
        .header h2 { color: #7c3aed; margin: 0; font-size: 18px; }
        .soa-title { text-align: center; font-size: 14px; font-weight: bold; margin: 10px 0; }
        .info-table { width: 100%; margin: 10px 0; font-size: 10px; }
        .info-table td { padding: 4px 8px; }
        .info-table td:first-child { font-weight: bold; width: 30%; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9px; }
        th { background: #f5f3ff; color: #7c3aed; padding: 6px 8px; text-align: center; border: 1px solid #ddd; font-size: 9px; }
        td { padding: 5px 8px; border: 1px solid #ddd; text-align: center; font-size: 9px; }
        .summary { margin: 15px 0; padding: 10px; background: #fafafa; border: 1px solid #ddd; font-size: 10px; }
        .summary td { padding: 3px 8px; border: none; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KUSUG-PA</h2>
        <p>Statement of Account</p>
    </div>

    <table class="info-table">
        <tr>
            <td>Loan Number:</td><td>{{ $loan->loan_number }}</td>
            <td>Date Generated:</td><td>{{ $generatedDate }}</td>
        </tr>
        <tr>
            <td>Planter:</td><td>{{ $loan->planter_name }}</td>
            <td>Code:</td><td>{{ $loan->planter_code }}</td>
        </tr>
    </table>

    <div class="summary">
        <table>
            <tr><td style="font-weight:bold;">Total Loan Amount:</td><td style="text-align:right;">₱{{ number_format($totalDue, 2) }}</td></tr>
            <tr><td style="font-weight:bold;">Total Paid:</td><td style="text-align:right; color:#16a34a;">₱{{ number_format($totalPaid, 2) }}</td></tr>
            <tr><td style="font-weight:bold;">Outstanding Balance:</td><td style="text-align:right; color:{{ $balance > 0 ? '#dc2626' : '#16a34a' }};">₱{{ number_format($balance, 2) }}</td></tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Due Date</th>
                <th>Amount Due</th>
                <th>Paid Date</th>
                <th>Amount Paid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loan->amortizations as $amort)
            <tr>
                <td>{{ $amort->payment_number }}</td>
                <td>{{ $amort->due_date->format('M d, Y') }}</td>
                <td style="text-align:right;">₱{{ number_format($amort->amount_due, 2) }}</td>
                <td>{{ $amort->paid_date ? $amort->paid_date->format('M d, Y') : '-' }}</td>
                <td style="text-align:right;">₱{{ number_format($amort->amount_paid, 2) }}</td>
                <td>{{ ucfirst($amort->status) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;">No records</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This is a system-generated document. | KUSUG-PA System</p>
    </div>
</body>
</html>