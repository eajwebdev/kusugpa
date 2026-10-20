<!-- resources/views/pdf/loan-details.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Loan Details - {{ $loan->loan_number }}</title>
    <style>
        @include('pdf.partials.formal-font')

        body { font-family: 'Tinos', 'Times New Roman', serif; font-size: 12px; margin: 30px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #16a34a; padding-bottom: 15px; }
        .header h2 { color: #16a34a; margin: 0; font-size: 20px; }
        .header p { margin: 5px 0 0; color: #666; font-size: 11px; }
        .loan-number { font-size: 14px; font-weight: bold; color: #16a34a; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #f0fdf4; color: #16a34a; padding: 8px 10px; text-align: left; font-size: 11px; border: 1px solid #ddd; }
        td { padding: 8px 10px; border: 1px solid #ddd; font-size: 11px; }
        .info-table td:first-child { font-weight: bold; width: 40%; background: #fafafa; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .status-active { background: #dcfce7; color: #16a34a; }
        .status-pending { background: #fef9c3; color: #a16207; }
        .status-approved { background: #dbeafe; color: #2563eb; }
        .status-completed { background: #f3f4f6; color: #374151; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KUSUG-PA</h2>
        <p>Sugarcane Crop Management System</p>
    </div>

    <div class="loan-number">Loan Details: {{ $loan->loan_number }}</div>

    <table class="info-table">
        <tr><td>Loan Number</td><td>{{ $loan->loan_number }}</td></tr>
        <tr><td>Planter Name</td><td>{{ $loan->planter_name }}</td></tr>
        <tr><td>Planter Code</td><td>{{ $loan->planter_code }}</td></tr>
        <tr><td>Loan Type</td><td>{{ $loan->loanType->name ?? 'N/A' }}</td></tr>
        <tr><td>Status</td><td><span class="status-badge status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td></tr>
        <tr><td>Principal Amount</td><td>₱{{ number_format($loan->principal_amount, 2) }}</td></tr>
        <tr><td>Interest Rate</td><td>{{ $loan->interest_rate }}%</td></tr>
        <tr><td>Term</td><td>{{ $loan->term_months }} months</td></tr>
        <tr><td>Monthly Amortization</td><td>₱{{ number_format($loan->monthly_amortization, 2) }}</td></tr>
        <tr><td>Total Amount</td><td>₱{{ number_format($loan->total_amount, 2) }}</td></tr>
        <tr><td>Remaining Balance</td><td>₱{{ number_format($remainingBalance, 2) }}</td></tr>
        <tr><td>Total Paid</td><td>₱{{ number_format($totalPaid, 2) }}</td></tr>
        <tr><td>Crop Year</td><td>{{ $loan->crop_year }}</td></tr>
        <tr><td>Application Date</td><td>{{ $loan->application_date->format('F d, Y') }}</td></tr>
        @if($loan->start_date)<tr><td>Start Date</td><td>{{ $loan->start_date->format('F d, Y') }}</td></tr>@endif
        @if($loan->end_date)<tr><td>End Date</td><td>{{ $loan->end_date->format('F d, Y') }}</td></tr>@endif
        @if($loan->purpose)<tr><td>Purpose</td><td>{{ $loan->purpose }}</td></tr>@endif
        @if($loan->approvedByUser)<tr><td>Approved By</td><td>{{ $loan->approvedByUser->fname }} {{ $loan->approvedByUser->lname }}</td></tr>@endif
    </table>

    <div class="footer">
        <p>Generated on {{ $generatedDate }} | KUSUG-PA System</p>
    </div>
</body>
</html>