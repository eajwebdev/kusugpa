<!-- resources/views/pdf/loan-active-report.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Active Loans Report</title>
    <style>
        @include('pdf.partials.formal-font')

        body { font-family: 'Tinos', 'Times New Roman', serif; font-size: 10px; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #dc2626; padding-bottom: 10px; }
        .header h2 { color: #dc2626; margin: 0; font-size: 18px; }
        .summary { margin: 15px 0; padding: 10px; background: #fef2f2; border: 1px solid #fecaca; font-size: 10px; display: flex; justify-content: space-around; }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 16px; font-weight: bold; color: #dc2626; }
        .summary-item .label { font-size: 9px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9px; }
        th { background: #fef2f2; color: #dc2626; padding: 6px 8px; text-align: left; border: 1px solid #ddd; font-size: 9px; }
        td { padding: 5px 8px; border: 1px solid #ddd; font-size: 9px; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KUSUG-PA Active Loans Report</h2>
        <p>@if($cropYear) Crop Year: {{ $cropYear }} @else All Crop Years @endif</p>
    </div>

    <div class="summary">
        <div class="summary-item"><div class="value">{{ $loans->count() }}</div><div class="label">Active Loans</div></div>
        <div class="summary-item"><div class="value">₱{{ number_format($totalPrincipal, 0) }}</div><div class="label">Total Principal</div></div>
        <div class="summary-item"><div class="value">₱{{ number_format($totalBalance, 0) }}</div><div class="label">Total Balance</div></div>
        <div class="summary-item"><div class="value">₱{{ number_format($totalMonthlyCollection, 0) }}</div><div class="label">Monthly Collection</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Loan #</th>
                <th>Planter</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Balance</th>
                <th>Monthly</th>
                <th>Start Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loans as $loan)
            <tr>
                <td>{{ $loan->loan_number }}</td>
                <td>{{ $loan->planter_name }}</td>
                <td>{{ $loan->loanType->name ?? 'N/A' }}</td>
                <td>₱{{ number_format($loan->principal_amount, 2) }}</td>
                <td>₱{{ number_format($loan->balance, 2) }}</td>
                <td>₱{{ number_format($loan->monthly_amortization, 2) }}</td>
                <td>{{ $loan->start_date ? $loan->start_date->format('M d, Y') : 'N/A' }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;">No active loans found</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on {{ $generatedDate }} | KUSUG-PA System</p>
    </div>
</body>
</html>