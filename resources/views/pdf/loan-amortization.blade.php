<!-- resources/views/pdf/loan-amortization.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Amortization Schedule - {{ $loan->loan_number }}</title>
    <style>
        @include('pdf.partials.formal-font')

        body { font-family: 'Tinos', 'Times New Roman', serif; font-size: 10px; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h2 { color: #2563eb; margin: 0; font-size: 18px; }
        .header p { margin: 3px 0 0; color: #666; font-size: 10px; }
        .info { margin-bottom: 15px; font-size: 10px; }
        .info span { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 10px; }
        th { background: #eff6ff; color: #2563eb; padding: 6px 8px; text-align: center; border: 1px solid #ddd; font-size: 9px; }
        td { padding: 5px 8px; border: 1px solid #ddd; text-align: center; font-size: 9px; }
        td.right { text-align: right; }
        .paid { background: #dcfce7; }
        .overdue { background: #fee2e2; }
        .partial { background: #fef9c3; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KUSUG-PA Amortization Schedule</h2>
        <p>Loan #{{ $loan->loan_number }} | {{ $loan->planter_name }}</p>
    </div>

    <div class="info">
        <p><span>Principal:</span> ₱{{ number_format($loan->principal_amount, 2) }} | 
        <span>Interest:</span> {{ $loan->interest_rate }}% | 
        <span>Term:</span> {{ $loan->term_months }} months | 
        <span>Monthly:</span> ₱{{ number_format($loan->monthly_amortization, 2) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Due Date</th>
                <th>Amount Due</th>
                <th>Principal</th>
                <th>Interest</th>
                <th>Amount Paid</th>
                <th>Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loan->amortizations as $amort)
            <tr class="{{ $amort->status === 'paid' ? 'paid' : ($amort->status === 'overdue' ? 'overdue' : ($amort->status === 'partial' ? 'partial' : '')) }}">
                <td>{{ $amort->payment_number }}</td>
                <td>{{ $amort->due_date->format('M d, Y') }}</td>
                <td class="right">₱{{ number_format($amort->amount_due, 2) }}</td>
                <td class="right">₱{{ number_format($amort->principal_paid, 2) }}</td>
                <td class="right">₱{{ number_format($amort->interest_paid, 2) }}</td>
                <td class="right">₱{{ number_format($amort->amount_paid, 2) }}</td>
                <td class="right">₱{{ number_format($amort->balance_after, 2) }}</td>
                <td>{{ ucfirst($amort->status) }}</td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;">No amortization schedule available</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on {{ $generatedDate }} | KUSUG-PA System</p>
    </div>
</body>
</html>