{{-- Trucking Allowance slip: laid out like KUSUG-PA's printed dot-matrix "TRUCKING ALLOWANCE WEEK NO." slip --}}
@php
    // Printed like the dot-matrix slip: 0 -> ".00", 0.5 -> ".50", 2903.55 -> "2,903.55"
    $n = function ($value, $decimals = 2) {
        $text = number_format(abs((float) $value), $decimals);
        $text = str_starts_with($text, '0.') ? substr($text, 1) : $text;
        return ((float) $value < 0 ? '-' : '') . $text;
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ count($slips) === 1 ? 'Trucking Allowance - ' . $slips[0]['upload']->planter_name . ' - Week ' . $slips[0]['weekNo'] : 'Trucking Allowance Slips (' . count($slips) . ')' }}</title>
    <style>
        @page { margin: 26px 40px 20px 40px; }
        body { font-family: 'Courier', monospace; font-size: 13px; color: #222; margin: 0; }
        .head { text-align: center; line-height: 1.35; }
        .meta { width: 100%; margin-top: 18px; border-collapse: collapse; }
        .meta td { padding: 0; }
        .spaced { letter-spacing: 4px; }
        table.lines { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 18px; }
        table.lines td { padding: 2.5px 0; white-space: nowrap; vertical-align: bottom; }
        .r { text-align: right; }
        .c-label { width: 37%; }
        .c-wt { width: 12%; }
        .c-rate { width: 13%; }
        .c-amt { width: 17%; }
        .c-total { width: 21%; }
        .rule { line-height: 8px; }
        .indent { padding-left: 58px !important; }
        .words { margin-top: 26px; padding-left: 40px; }
        .sign { margin-top: 34px; width: 100%; border-collapse: collapse; }
        .sign td { padding: 0; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@foreach ($slips as $slip)
    @php extract($slip); @endphp
    <div class="{{ $loop->last ? '' : 'page-break' }}">
    <div class="head">
        TRUCKING ALLOWANCE WEEK NO. : &nbsp; {{ $weekNo }}<br>
        CROP YEAR {{ $cropYear }}
    </div>

    <table class="meta">
        <tr>
            <td><span class="spaced">PAYEE</span> : {{ strtoupper($upload->planter_name) }}</td>
            <td class="r"><span class="spaced">DATE</span> : {{ $date }}</td>
        </tr>
    </table>

    <table class="lines">
        <tr>
            <td class="c-label" style="padding-left: 120px; padding-bottom: 8px;"><span class="spaced" style="letter-spacing: 12px;">PARTICULARS</span></td>
            <td class="c-wt"></td>
            <td class="c-rate"></td>
            <td class="c-amt"></td>
            <td class="c-total r" style="padding-bottom: 8px;"><span class="spaced">AMOUNT</span></td>
        </tr>
        {{-- First line of the printed slip: blank label, no weight, rate and amount .00 --}}
        <tr>
            <td></td><td></td>
            <td class="r">{{ $n(0) }}</td>
            <td class="r">{{ $n(0) }}</td>
            <td></td>
        </tr>
        {{-- Trucking allowance: TA weight x rate per ton = TA amount --}}
        <tr>
            <td></td>
            <td class="r">{{ $n($taWt, 3) }}</td>
            <td class="r">{{ $n($taRate) }}</td>
            <td class="r">{{ $n($ta) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>FRESH CANE INCENTIVE</td><td></td><td></td>
            <td class="r">{{ $n($freshCane) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>MUDPRESS INCENTIVE</td><td></td><td></td>
            <td class="r">{{ $n($mudpress) }}</td>
            <td class="r">{{ $n($gross) }}</td>
        </tr>
        <tr class="rule"><td></td><td></td><td></td><td class="r">--------</td><td></td></tr>

        <tr><td colspan="5" style="height: 6px;"></td></tr>
        <tr>
            <td>LESS: GASOLINE ACCOUNT</td><td></td><td></td>
            <td class="r">{{ $n($gasoline) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="indent">TRUCK RENTAL</td><td></td><td></td>
            <td class="r">{{ $n($rental) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="indent">UNDER LOAD CHARGES</td><td></td><td></td>
            <td class="r">{{ $n($underload) }}</td>
            <td class="r">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $n($less) }})</td>
        </tr>
        <tr class="rule"><td></td><td></td><td></td><td class="r">--------</td><td class="r">--------</td></tr>

        <tr><td colspan="5" style="height: 16px;"></td></tr>
        <tr>
            <td style="padding-left: 8px;">NET TRUCKING ALLOWANCE &amp; INCENTIVES</td><td></td><td></td><td></td>
            <td class="r">{{ $n($net) }}</td>
        </tr>
        <tr class="rule"><td></td><td></td><td></td><td></td><td class="r">==========</td></tr>
    </table>

    <div class="words">*{{ $words }}*</div>

    <table class="sign">
        <tr>
            <td style="width: 58%;"></td>
            <td>Received by: ---------------</td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-left: 105px; padding-top: 6px;">Signature</td>
        </tr>
    </table>
    </div>
@endforeach
</body>
</html>
