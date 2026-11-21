<!-- resources/views/pdf/payment-receipt.blade.php -->
{{-- Over-the-counter (cash) repayment of a cash advance or loan: half a letter page --}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $receipt_no }}</title>
    <style>
        @page {
            margin: 0.35in 0.45in;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #111;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 2.5px 0;
            vertical-align: top;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .label {
            width: 30%;
            color: #444;
        }

        .box {
            border: 1px solid #111;
            padding: 6px 10px;
            margin-top: 8px;
        }

        .amount {
            font-size: 15pt;
            font-weight: bold;
        }

        .line {
            border-top: 0.8px solid #111;
            margin: 26px 18px 2px;
        }
    </style>
</head>

<body>
    <table>
        <tr>
            <td style="width: 62%">
                <div style="font-weight: bold; font-size: 10pt">{{ $org['name'] }}</div>
                <div>{{ $org['address'] }}@if (!empty($org['telephone'])) · Tel. {{ $org['telephone'] }}@endif</div>
            </td>
            <td class="right">
                <div style="font-weight: bold; font-size: 11pt; letter-spacing: 1px">OFFICIAL RECEIPT</div>
                <div>No. <b>{{ $receipt_no }}</b></div>
                <div>{{ $paid_date }}</div>
            </td>
        </tr>
    </table>

    <div class="center" style="margin-top: 8px; font-weight: bold; letter-spacing: 2px">{{ $title }} · OVER THE COUNTER (CASH)</div>

    <table style="margin-top: 8px">
        <tr>
            <td class="label">Received from</td>
            <td><b>{{ $planter_name }}</b> ({{ $planter_code }})</td>
        </tr>
        <tr>
            <td class="label">Payment for</td>
            <td>{{ $account }} · {{ $applied_to }}</td>
        </tr>
        @if (!empty($reference))
            <tr>
                <td class="label">Reference</td>
                <td>{{ $reference }}</td>
            </tr>
        @endif
        @if (!empty($remarks))
            <tr>
                <td class="label">Remarks</td>
                <td>{{ $remarks }}</td>
            </tr>
        @endif
    </table>

    <div class="box">
        <table>
            <tr>
                <td class="label" style="vertical-align: middle">Amount received</td>
                <td class="right amount">₱{{ number_format($amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="font-size: 8pt; font-style: italic">{{ $amount_words }}</td>
            </tr>
            <tr>
                <td class="label">Balance after payment</td>
                <td class="right"><b>₱{{ number_format($balance_after, 2) }}</b></td>
            </tr>
        </table>
    </div>

    <table style="margin-top: 4px">
        <tr>
            <td class="center" style="width: 50%">
                <div class="line"></div>
                Received by (Cashier){{ $received_by ? ': ' . $received_by : '' }}
            </td>
            <td class="center" style="width: 50%">
                <div class="line"></div>
                Paid by (Planter)
            </td>
        </tr>
    </table>
    <div class="center" style="margin-top: 6px; font-size: 7pt; color: #555">
        Paid in cash over the counter; this amount is not deducted again on the Check Voucher.
    </div>
</body>

</html>
