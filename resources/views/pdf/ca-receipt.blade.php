<!-- resources/views/pdf/ca-receipt.blade.php -->
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $date = optional($ca->approved_date ?? $ca->application_date)->format('m/d/Y');
    $cy = (string) $ca->crop_year;
    $cropYear = preg_match('/^\d{8}$/', $cy) ? substr($cy, 0, 4) . '-' . substr($cy, 4) : $cy;
    $rate = rtrim(rtrim(number_format($weeklyInterest, 2), '0'), '.');
    $userName = fn ($u) => $u ? trim($u->fname . ' ' . $u->lname) : '';
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Cash Advance Receipt - {{ $ca->ca_number }}</title>
    <style>
        @include('pdf.partials.formal-font')

        @page {
            margin: 0.4in 0.6in 0.35in 0.6in;
        }

        body {
            font-family: 'Tinos', 'Times New Roman', serif;
            font-size: 10.5pt;
            color: #111;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            vertical-align: top;
            padding: 0;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .fill {
            border-bottom: 0.8px solid #111;
            padding: 0 4px;
            font-weight: bold;
        }

        .blank {
            display: inline-block;
            border-bottom: 0.8px solid #111;
        }

        /* Header */
        .org {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        .title {
            margin: 8px 0 8px;
            font-size: 12.5pt;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        p.body {
            margin: 0 0 6px;
            line-height: 1.4;
            text-align: justify;
        }

        .indent {
            text-indent: 0.55in;
        }

        .sign td {
            padding-top: 4px;
        }

        .sign-line {
            border-bottom: 0.8px solid #111;
            height: 22px;
        }

        /* Voucher */
        .voucher {
            margin-top: 12px;
            border: 1.2px solid #111;
        }

        .voucher-title {
            padding: 5px 0;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .vhead td {
            padding: 2px 8px;
        }

        .vtable th {
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 4px 8px;
            font-weight: normal;
            letter-spacing: 0.5px;
        }

        .vtable > tr > td,
        .vtable > tbody > tr > td {
            padding: 3px 8px;
        }

        .vtable .amount-col {
            width: 22%;
            border-left: 1px solid #111;
        }

        /* only the row's own cells get the rule, not tables nested inside them */
        .vtotal > td {
            border-top: 1px solid #111;
            padding: 5px 8px;
        }

        .vfoot {
            border-top: 1px solid #111;
        }

        .vfoot td {
            padding: 4px 8px;
        }

        .nowrap {
            white-space: nowrap;
        }

        .label {
            color: #333;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="center">
        <div class="org">{{ $org['name'] }}</div>
        <div>{{ $org['address'] }}</div>
        <div>Tel. No. {{ $org['telephone'] }}</div>
    </div>
    <div class="right" style="margin-top: 4px">Date: <span class="fill">{{ $date }}</span></div>

    <div class="title center">CASH ADVANCE RECEIPT AND SUGAR ASSIGNMENT</div>

    <!-- Receipt & assignment -->
    <p class="body indent">
        Receives from KABANKALAN UNITED SUGAR PLANTERS ASSOCIATION, ({{ $org['short_name'] }}) INC the amount of
        <span class="fill">{{ $amountInWords }}</span>
        (Php <span class="fill">{{ $money($ca->amount) }}</span>) as cash advance per pesada of
        <span class="blank" style="width: 60px">&nbsp;</span> tons of cane delivered to {{ $org['mill'] }}.
        I agree to pay {{ $org['short_name'] }} {{ $rate }}% per week interest. By against the loan/cash advance.
        For this purpose, I/We hereby authorize the {{ $org['short_name'] }} in my/our behalf, to endorse, sell, and transfer
        to any person at a price they may deem fit and proper and to apply the proceeds thereof to my/our cash advances.
        Provided that whatever remaining balance in excess thereof will be returned to me/us.
    </p>
    <p class="body indent">
        I/We further authorize the {{ $org['short_name'] }} or its authorized representative to get and receive all my/our
        weekly quedans from <b>{{ $org['mill'] }}</b> for crop year <b>{{ $cropYear }}</b> and I/We hereby hold
        <b>{{ $org['mill'] }}</b> harmless / accountable in releasing quedans to {{ $org['short_name'] }} until all my/our
        loan/cash advance incurred now and thereafter are fully paid.
    </p>

    <!-- Planter signature -->
    <table class="sign" style="margin-top: 6px">
        <tr>
            <td style="width: 52%"></td>
            <td style="width: 17%" class="right">Signature:&nbsp;</td>
            <td class="sign-line"></td>
        </tr>
        <tr>
            <td></td>
            <td class="right">Planter's Name:&nbsp;</td>
            <td class="center"><b>{{ $ca->planter_name }}</b></td>
        </tr>
    </table>

    <!-- Committee / co-maker -->
    <table class="sign" style="margin-top: 8px">
        <tr>
            <td style="width: 48%">Approved Loan Committee:</td>
            <td style="width: 4%"></td>
            <td>Co Maker:</td>
        </tr>
        <tr>
            <td>
                <table>
                    <tr>
                        <td style="width: 30px; padding-top: 16px">By:</td>
                        <td class="sign-line center" style="vertical-align: bottom">{{ $userName($ca->approvedByUser) }}</td>
                    </tr>
                </table>
            </td>
            <td></td>
            <td class="sign-line"></td>
        </tr>
    </table>

    <!-- Cash / Check Voucher -->
    <div class="voucher">
        <div class="voucher-title center">CASH / CHECK VOUCHER</div>
        <table class="vhead">
            <tr>
                <td style="width: 12%"><i>Payee:</i></td>
                <td style="width: 52%"><b><i>{{ $ca->planter_name }}</i></b></td>
                <td style="width: 14%" class="right">CV No:</td>
                <td class="right"><b>{{ $ca->cv_no }}</b></td>
            </tr>
            <tr>
                <td><i>Address:</i></td>
                <td>{{ optional($ca->planter)->address }}</td>
                <td class="right">Date:</td>
                <td class="right">{{ $date }}</td>
            </tr>
        </table>

        <table class="vtable">
            <tr>
                <th class="center">DESCRIPTION</th>
                <th class="center amount-col">AMOUNT</th>
            </tr>
            <tr>
                <td>Payment For:</td>
                <td class="amount-col"></td>
            </tr>
            <tr>
                <td style="padding-left: 40px">CASH ADVANCE AGAINST PESADA W/ {{ $rate }}% INTEREST PER WEEK</td>
                <td class="amount-col num">{{ $money($ca->amount) }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 8px 4px 40px">
                    <table>
                        <tr>
                            <td style="width: 50%">
                                <table>
                                    <tr>
                                        <td style="width: 82px">Crop Year:</td>
                                        <td><b>{{ $cropYear }}</b></td>
                                    </tr>
                                    <tr>
                                        <td style="padding-top: 4px">Week No.:</td>
                                        <td style="padding-top: 4px">
                                            @forelse ($ca->deductions as $d)
                                                <div><b>{{ $d->week_no }}</b> = {{ $money($d->amount) }}</div>
                                            @empty
                                                <span class="blank" style="width: 120px">&nbsp;</span>
                                            @endforelse
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td>
                                <table>
                                    <tr>
                                        <td class="right nowrap" style="width: 110px">Date Delivered:&nbsp;</td>
                                        <td><span class="blank" style="width: 110px">&nbsp;</span></td>
                                    </tr>
                                    <tr>
                                        <td class="right nowrap" style="padding-top: 3px">Net Cane:&nbsp;</td>
                                        <td style="padding-top: 3px"><span class="blank" style="width: 110px">&nbsp;</span></td>
                                    </tr>
                                    <tr>
                                        <td class="right nowrap" style="padding-top: 3px">LKg / Tc:&nbsp;</td>
                                        <td style="padding-top: 3px"><span class="blank" style="width: 110px">&nbsp;</span></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="amount-col"></td>
            </tr>
            <tr class="vtotal">
                <td>
                    <table>
                        <tr>
                            <td style="width: 50%">Check No: <b>{{ $ca->check_no }}</b></td>
                            <td class="right" style="letter-spacing: 3px">TOTAL:</td>
                        </tr>
                    </table>
                </td>
                <td class="amount-col num"><b>{{ $money($ca->amount) }}</b></td>
            </tr>
        </table>

        <table class="vfoot">
            <tr>
                <td colspan="3" style="line-height: 1.6; padding-top: 6px">
                    Received from KABANKALAN UNITED SUGAR - PLANTERS ASSOCIATION, INC.({{ $org['short_name'] }})<br>
                    the amount of <span class="blank" style="width: 430px">&nbsp;</span><br>
                    (Php <span class="blank" style="width: 130px">&nbsp;</span>).
                </td>
            </tr>
            <tr>
                <td style="width: 33%; padding-top: 6px">Prepared By:</td>
                <td style="width: 34%; padding-top: 6px">Approved By:</td>
                <td style="padding-top: 6px">Received By:</td>
            </tr>
            <tr>
                <td style="padding: 14px 16px 8px 8px">
                    <div class="sign-line center" style="height: auto">{{ $userName($ca->createdByUser) }}&nbsp;</div>
                </td>
                <td style="padding: 14px 16px 8px 8px">
                    <div class="sign-line center" style="height: auto">{{ $userName($ca->approvedByUser) }}&nbsp;</div>
                </td>
                <td style="padding: 14px 8px 8px 8px">
                    <div class="sign-line" style="height: auto">&nbsp;</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
