<!-- resources/views/pdf/loan-monthly-report.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Loan Report - {{ $month }}</title>
    <style>
        @include('pdf.partials.formal-font')

        body { font-family: 'Tinos', 'Times New Roman', serif; font-size: 10px; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #ea580c; padding-bottom: 10px; }
        .header h2 { color: #ea580c; margin: 0; font-size: 18px; }
        .summary { margin: 15px 0; padding: 10px; background: #fff7ed; border: 1px solid #fed7aa; font-size: 10px; display: flex; justify-content: space-around; }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 16px; font-weight: bold; color: #ea580c; }
        .summary-item .label { font-size: 9px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9px; }
        th { background: #fff7ed; color: #ea580c; padding: 6px 8px; text-align: left; border: 1px solid #ddd; font-size: 9px; }
        td { padding: 5px 8px; border: 1px solid #ddd; font-size: 9px; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KUSUG-PA Monthly Loan Report</h2>
        <p>{{ $month }}</p>
    </div>

    <div class="summary">
        <div class="summary-item"><div class="value">{{ $newLoans }}</div><div class="label">New Loans</div></div>
        <div class="summary-item"><div class="value">{{ $approvedLoans }}</div><div class="label">Approved</div></div>
        <div class="summary-item"><div class="value">₱{{ number_format($collections, 0) }}</div><div class="label">Collections</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Loan #</th>
                <th>Planter</th>
                <th>Amount</th>
                <th>Monthly Due</th>
                <th>Payments Due</th>
                <th>Payments Paid</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loans as $loan)
            <tr>
                <td>{{ $loan->loan_number }}</td>
                <td>{{ $loan->planter_name }}</td>
                <td>₱{{ number_format($loan->principal_amount, 2) }}</td>
                <td>₱{{ number_format($loan->monthly_amortization, 2) }}</td>
                <td>{{ $loan->amortizations->count() }}</td>
                <td>{{ $loan->amortizations->where('status', 'paid')->count() }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;">No active loans</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on {{ $generatedDate }} | KUSUG-PA System</p>
    </div>
</body>
</html>