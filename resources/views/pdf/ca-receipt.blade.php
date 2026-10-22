<!-- resources/views/pdf/ca-receipt.blade.php -->
{{-- Laid out, worded and line-broken like KUSUG-PA's printed "Cash Advance Receipt and Sugar Assignment" form --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $date = optional($ca->approved_date ?? $ca->application_date)->format('m/d/Y');
    $cy = (string) $ca->crop_year;
    $cropYear = preg_match('/^\d{8}$/', $cy) ? substr($cy, 0, 4) . '-' . substr($cy, 4) : $cy;
    $rate = rtrim(rtrim(number_format($weeklyInterest, 2), '0'), '.');
    $short = $org['short_name'];
    $mill = $org['mill'];

    // Very long amounts in words shrink to stay on one line (about 70 characters fit at full size)
    $wordsSize = max(7, min(10.1, round(10.1 * 70 / max(1, strlen($amountInWords)), 1)));

    // Planter's name stays on one line under the signature: long names lose the indent, then shrink
    $nameLen = mb_strlen((string) $ca->planter_name);
    // (about 0.094in per character at 10pt; 2.43in from the line's start to the margin)
    $nameIndent = max(0, round(0.3 - max(0, $nameLen - 22) * 0.094, 2));
    $nameSize = $nameLen > 25 ? max(6, round(10 * 25 / $nameLen, 1)) : 10;

    // The pesada blank narrows as the amount gets longer, keeping "I agree to pay" on its line
    $pesadaGap = max(1, 7 - 2 * (strlen($money($ca->amount)) - 9));

    // Week No.: one per week (as on the form) up to 4 weeks; more weeks are grouped into
    // runs of the same amount ("3 - 7 = 4,000.00 each") so the voucher keeps its height
    $weeks = $ca->deductions->values();
    if ($weeks->count() <= 4) {
        $weekItems = $weeks->map(fn ($d) => $d->week_no . ' = ' . $money($d->amount));
        $weekRows = $weekItems->chunk($weekItems->count() > 2 ? 2 : 1);
    } else {
        $runs = [];
        foreach ($weeks as $d) {
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last]['amount'] == $d->amount && (int) $d->week_no === (int) $runs[$last]['to'] + 1) {
                $runs[$last]['to'] = $d->week_no;
            } else {
                $runs[] = ['from' => $d->week_no, 'to' => $d->week_no, 'amount' => $d->amount];
            }
        }
        $weekRows = collect($runs)->map(fn ($r) => collect([$r['from'] === $r['to']
            ? $r['from'] . ' = ' . $money($r['amount'])
            : $r['from'] . ' - ' . $r['to'] . ' = ' . $money($r['amount']) . ' each']));
    }
    $compactWeeks = $weekRows->count() > 2;
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Cash Advance Receipt - {{ $ca->ca_number }}</title>
    <style>
        @page {
            margin: 0.4in 0.28in 0.25in 0.28in;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.15;
            color: #111;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            vertical-align: bottom;
            padding: 0;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .nowrap {
            white-space: nowrap;
        }

        .u {
            text-decoration: underline;
        }

        .line {
            border-bottom: 0.8px solid #111;
        }

        .sig {
            text-align: center;
            font-size: 9.5pt;
        }

        /* Header */
        .org {
            font-size: 11.3pt;
            font-weight: bold;
        }

        .addr {
            font-size: 11pt;
            margin-top: 3px;
        }

        .title {
            font-size: 11pt;
            font-weight: bold;
        }

        /* Paragraphs: same size and leading as the form. Lines without data keep the form's
           breaks (nowrap); lines with the mill name / crop year may wrap */
        .para {
            font-size: 10.1pt;
            line-height: 1.22;
        }

        .para .fixed {
            white-space: nowrap;
        }

        /* Cash / Check Voucher */
        .voucher {
            border: 1.3px solid #111;
            font-size: 11pt;
        }

        .vt td {
            padding: 0;
        }

        .amount-col {
            width: 1.3in;
            border-left: 1px solid #111;
            text-align: right;
            padding-right: 0.08in !important;
        }

        .rule > td {
            border-top: 1px solid #111;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="center" style="padding-top: 0.04in">
        <div class="org">{{ $org['name'] }}</div>
        <div class="addr">{{ $org['address'] }}</div>
        <div class="addr">Tel. No. {{ $org['telephone'] }}</div>
    </div>
    <table style="margin-top: 0.01in">
        <tr>
            <td style="width: 6.35in"></td>
            <td style="width: 0.67in; font-size: 11pt">Date:</td>
            <td style="font-size: 11pt">{{ $date }}</td>
        </tr>
    </table>

    <div class="title center" style="margin-top: 0.11in">CASH ADVANCE RECEIPT AND SUGAR ASSIGNMENT</div>

    <!-- Receipt & assignment -->
    <div class="para" style="margin-top: 0.08in">
        <div class="fixed" style="padding-left: 0.79in">Receives from KABANKALAN UNITED SUGAR PLANTERS ASSOCIATION, ({{ str_replace('-', ' - ', $short) }}) INC</div>
        <div class="fixed">the amount of&nbsp; <span class="u" style="font-size: {{ $wordsSize }}pt">{{ $amountInWords }}</span></div>
        <div>(Php&nbsp;&nbsp; {{ $money($ca->amount) }}&nbsp;&nbsp;&nbsp;) as cash advance per pesada of {!! str_repeat('&nbsp;', $pesadaGap) !!} tons of cane delivered to {{ $mill }}. I agree to pay</div>
        <div class="fixed">{{ $short }}&nbsp; {{ $rate }}% per week interest. By against the loan/cash advance. For this purpose, I/We hereby authorize the</div>
        <div class="fixed">{{ $short }} in my/our behalf, to endorse, sell, and transfer to any person at a price they may deem fit and</div>
        <div class="fixed">proper and to apply the proceeds to apply the proceeds thereof to my/our cash advances. Provided that</div>
        <div class="fixed">whatever remaining balance in excess therefore will be returned to me/us.</div>
    </div>
    <div class="para" style="margin-top: 0.25in">
        <div class="fixed" style="padding-left: 0.63in">I/We further authorize the {{ $short }} or its authorize representative to get and received all my/our</div>
        <div>
            weekly quedans from&nbsp; {{ $mill }}&nbsp; for crop year&nbsp; {{ $cropYear }}&nbsp;&nbsp; and I/We hereby hold&nbsp; {{ $mill }} harmless
            / accountable in releasing quedans to {{ $short }} until all my/our loan/ cash advance incurred now and
            thereafter are fullypaid.
        </div>
    </div>

    <!-- Planter signature -->
    <table style="margin-top: 0.05in">
        <tr>
            <td style="width: 4.3in"></td>
            <td style="width: 1.38in; height: 0.2in" class="right">Signature:</td>
            <td style="width: 0.11in"></td>
            <td class="line"></td>
        </tr>
        <tr>
            <td></td>
            <td class="right" style="height: 0.24in">Planter's Name:</td>
            <td></td>
            <td class="nowrap" style="padding-left: {{ $nameIndent }}in; font-size: {{ $nameSize }}pt">{{ $ca->planter_name }}</td>
        </tr>
    </table>

    <!-- Approved Loan Committee / Co Maker -->
    <table style="margin-top: 0.15in">
        <tr>
            <td style="width: 0.6in"></td>
            <td style="width: 4.45in">Approved Loan Committee:</td>
            <td>Co Maker:</td>
        </tr>
    </table>
    <table style="margin-top: 0.16in">
        <tr>
            <td style="width: 0.13in"></td>
            <td style="width: 0.45in">By:</td>
            <td style="width: 2.53in" class="line sig">{{ $signatories['committee'] }}</td>
            <td style="width: 2.64in"></td>
            <td class="line sig" style="height: 0.24in">{{ $signatories['co_maker'] }}</td>
            <td style="width: 0.06in"></td>
        </tr>
    </table>

    <!-- Cash / Check Voucher -->
    <div class="voucher" style="margin-top: 0.18in">
        <div class="center" style="font-size: 9.5pt; font-weight: bold; padding-top: 0.08in">CASH / CHECK VOUCHER</div>
        <table class="vt" style="margin-top: 0.02in">
            <tr>
                <td style="width: 0.03in"></td>
                <td style="width: 0.87in"><i>Payee:</i></td>
                <td><i>{{ $ca->planter_name }}</i></td>
                <td style="width: 0.8in">CV No:</td>
                <td style="width: 0.98in">{{ $ca->cv_no }}</td>
            </tr>
            <tr>
                <td style="height: 0.24in"></td>
                <td><i>Address:</i></td>
                <td><i>{{ optional($ca->planter)->address }}</i></td>
                <td style="padding-left: 0.12in">Date:</td>
                <td>{{ $date }}</td>
            </tr>
        </table>

        <table class="vt" style="margin-top: 0.04in">
            <tr class="rule">
                <td class="center" style="height: 0.25in; vertical-align: middle">DESCRIPTION</td>
                <td class="amount-col center" style="text-align: center; vertical-align: middle">AMOUNT</td>
            </tr>
            <tr class="rule">
                <td style="height: 0.26in; padding-left: 0.06in">Payment For:</td>
                <td class="amount-col"></td>
            </tr>
            <tr>
                <td style="height: 0.32in; padding-left: 0.42in; font-size: 11pt">CASH ADVANCE AGAINST PESADA W/ {{ $rate }}% INTEREST PER WEEK</td>
                <td class="amount-col" style="font-size: 11pt">{{ $money($ca->amount) }}</td>
            </tr>
            <tr>
                <td style="padding: {{ $compactWeeks ? '0.1in 0 0.04in' : '0.2in 0 0.12in' }} 0.33in; vertical-align: top">
                    <table>
                        <tr>
                            <td style="width: 0.88in; height: 0.26in">Crop Year:</td>
                            <td style="width: 1.45in">{{ $cropYear }}</td>
                            <td class="right" style="width: 1.55in">Date Delivered:</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td style="height: 0.26in; vertical-align: top; padding-top: 0.05in">Week No.:</td>
                            <td style="vertical-align: top; padding-top: 0.05in; {{ $compactWeeks ? 'font-size: 8.5pt; line-height: 1.05' : '' }}">
                                @foreach ($weekRows as $row)
                                    <div class="nowrap">{!! $row->map(fn ($item) => e($item))->implode('&nbsp;&nbsp;&nbsp;') !!}</div>
                                @endforeach
                            </td>
                            <td class="right" style="vertical-align: top; padding-top: 0.05in">
                                <div>Net Cane:</div>
                                <div style="margin-top: 0.05in">LKg / Tc:</div>
                            </td>
                            <td></td>
                        </tr>
                    </table>
                </td>
                <td class="amount-col"></td>
            </tr>
            <tr class="rule">
                <td style="height: 0.27in; vertical-align: middle">
                    <table>
                        <tr>
                            <td style="width: 0.1in"></td>
                            <td style="width: 1.0in">Check No:</td>
                            <td>{{ $ca->check_no }}</td>
                            <td class="right nowrap" style="width: 1.2in; letter-spacing: 2.5px; padding-right: 0.15in">TOTAL:</td>
                        </tr>
                    </table>
                </td>
                <td class="amount-col" style="vertical-align: middle">{{ $money($ca->amount) }}</td>
            </tr>
        </table>

        <table class="vt" style="border-top: 1px solid #111">
            <tr>
                <td style="height: 0.27in; padding-left: 0.03in">Received from KABANKALAN UNITED SUGAR - PLANTERS ASSOCIATION, INC.({{ $short }})</td>
            </tr>
        </table>
        <table class="vt">
            <tr>
                <td style="width: 0.03in"></td>
                <td style="width: 1.34in; height: 0.25in">the amount of.</td>
                <td class="line"></td>
                <td style="width: 0.04in"></td>
            </tr>
        </table>
        <table class="vt">
            <tr>
                <td style="width: 0.05in"></td>
                <td style="width: 0.52in; height: 0.25in">(Php</td>
                <td style="width: 1.33in" class="line"></td>
                <td>).</td>
            </tr>
        </table>
        <table class="vt" style="margin-top: 0.12in">
            <tr>
                <td style="width: 0.03in"></td>
                <td style="width: 3.32in">Prepared By:</td>
                <td style="width: 2.85in">Approved By:</td>
                <td>Received By:</td>
            </tr>
        </table>
        <table class="vt" style="margin: 0.08in 0 0.2in">
            <tr>
                <td style="width: 0.19in"></td>
                <td style="width: 2.33in; height: 0.2in" class="line sig">{{ $signatories['prepared'] }}</td>
                <td style="width: 0.67in"></td>
                <td style="width: 2.38in" class="line sig">{{ $signatories['approved'] }}</td>
                <td style="width: 0.36in"></td>
                <td class="line sig">{{ $signatories['received'] }}</td>
                <td style="width: 0.04in"></td>
            </tr>
        </table>
    </div>
</body>

</html>
