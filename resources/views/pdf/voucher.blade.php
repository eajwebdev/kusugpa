@php
    if (!function_exists('convertToWords')) {
    function convertToWords($amount)
    {
        $isNegative = $amount < 0;
        $amount = abs($amount);

        $pesos = floor($amount);
        $centavos = round(($amount - $pesos) * 100);

        $words = [
            '',
            'One',
            'Two',
            'Three',
            'Four',
            'Five',
            'Six',
            'Seven',
            'Eight',
            'Nine',
            'Ten',
            'Eleven',
            'Twelve',
            'Thirteen',
            'Fourteen',
            'Fifteen',
            'Sixteen',
            'Seventeen',
            'Eighteen',
            'Nineteen',
            'Twenty',
            30 => 'Thirty',
            40 => 'Forty',
            50 => 'Fifty',
            60 => 'Sixty',
            70 => 'Seventy',
            80 => 'Eighty',
            90 => 'Ninety',
        ];

        $convert = function ($n) use (&$words, &$convert) {
            if ($n < 21) {
                return $words[$n];
            }
            if ($n < 100) {
                return $words[intval($n / 10) * 10] . ($n % 10 ? '-' . $words[$n % 10] : '');
            }
            if ($n < 1000) {
                return $words[intval($n / 100)] . ' Hundred' . ($n % 100 ? ' ' . $convert($n % 100) : '');
            }
            if ($n < 1000000) {
                return $convert(intval($n / 1000)) . ' Thousand' . ($n % 1000 ? ' ' . $convert($n % 1000) : '');
            }
            if ($n < 1000000000) {
                return $convert(intval($n / 1000000)) . ' Million' . ($n % 1000000 ? ' ' . $convert($n % 1000000) : '');
            }
            return '';
        };

        $pesoWords = $convert($pesos);
        $centavos = str_pad($centavos, 2, '0', STR_PAD_LEFT);

        $finalText = ($isNegative ? 'Negative ' : '') . "{$pesoWords} Pesos and {$centavos}/100";

        return ucfirst($finalText);
    }
    }
@endphp

<!DOCTYPE html>
<html>

<head>
    <title>Check Voucher</title>
    <style>
        @include('pdf.partials.formal-font')

        body {
            font-family: 'Tinos', 'Times New Roman', serif;
            margin: 0;
            padding: 0;
        }

        .page {
            page-break-after: always;
            padding: 40px 50px;
            font-size: 12px;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .header {
            margin-bottom: 20px;
        }

        .header p {
            margin: 2px 0;
            font-size: 13px;
        }

        .company-name {
            font-weight: bold;
            font-size: 14px;
        }

        .info-table {
            width: 100%;
            margin-bottom: 15px;
        }

        .info-table td {
            padding: 3px 0;
            font-size: 12px;
        }

        .voucher-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .voucher-table th,
        .voucher-table td {
            padding: 6px 8px;
            font-size: 11px;
            text-align: left;
            vertical-align: top;
        }

        .voucher-table th {
            font-weight: bold;
            font-size: 10px;
        }

        .totals-section {
            margin-top: 30px;
            width: 100%;
        }

        .totals-section table {
            width: 60%;
            float: right;
        }

        .totals-section td {
            padding: 4px 8px;
            font-size: 12px;
        }

        .amount-words {
            margin-top: 30px;
            padding: 10px 0;
            font-size: 12px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 30%;
        }

        .signature-line {
            margin-top: 40px;
            border-top: 1px solid #000;
            padding-top: 5px;
            font-size: 11px;
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>


</head>

<body>
    @foreach ($summaryData as $record)
        @php
            // Amounts per planter (shared with the Proceeds Sheet)
            extract(\App\Services\PlanterProceeds::compute($record));
        @endphp

        <div class="page">
            <div class="header">
                <p class="company-name">Kabankalan United Sugar Planter Association</p>
                <p>Repullo St., Kabankalan City</p>
            </div>

            <table class="info-table">
                <tr>
                    <td class="text-center" width="70%">
                        {{ $record['planter_name'] }}
                    </td>
                    <td width="30%" class="text-right">
                        <strong>Date:</strong> {{ now()->format('F d, Y') }}
                    </td>
                </tr>
                <tr>
                    <td class="text-center">
                        {{ $record['week_no'] }}
                    </td>
                    <td class="text-right">
                    </td>
                </tr>
            </table>

            <p style="margin: 15px 0; font-size: 13px;">
                <strong>QUEDAN AND MOLASSES LIQUIDATION WEEK ENDING:
                    {{ $record['week_end_date'] ? date('m-d-Y', strtotime($record['week_end_date'])) : '' }}</strong>
            </p>

            <table class="voucher-table">
                <thead>
                    <tr>
                        <th>DOC TYPE.</th>
                        <th>QTY IN LKG/MT</th>
                        <th>PRICE</th>
                        <th>GROSS PROCEEDS</th>
                        <th>QUEDAN LIENS</th>
                        <th>SERVICE CHARGE</th>
                        <th>INSURANCE HANDLING</th>
                        <th>W/HOLDING TAX</th>
                        <th>TOTAL NET DEDUCTION</th>
                        <th>TOTAL NET PROCEEDS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>A</strong></td>
                        <td>{{ $quedan_a_lkg ? number_format($quedan_a_lkg, 3) : '' }}</td>
                        <td>{{ $quedan_a_price ? number_format($quedan_a_price, 2) : '' }}</td>
                        <td>{{ $quedan_a_gross ? number_format($quedan_a_gross, 2) : '' }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>{{ $quedan_a_gross ? number_format($quedan_a_gross, 2) : '' }}</td>
                    </tr>

                    <tr>
                        <td><strong>B</strong></td>
                        <td>{{ $quedan_b_lkg ? number_format($quedan_b_lkg, 3) : '' }}</td>
                        <td>{{ $quedan_b_price ? number_format($quedan_b_price, 2) : '' }}</td>
                        <td>{{ $quedan_b_gross ? number_format($quedan_b_gross, 2) : '' }}</td>
                        <td>{{ $quedan_b_liens ? number_format($quedan_b_liens, 2) : '' }}</td>
                        <td>{{ $quedan_b_service ? number_format($quedan_b_service, 2) : '' }}</td>
                        <td>{{ $quedan_b_insurance ? number_format($quedan_b_insurance, 2) : '' }}</td>
                        <td>{{ $quedan_b_tax ? number_format($quedan_b_tax, 2) : '' }}</td>
                        <td>{{ $quedan_b_total_deductions ? number_format($quedan_b_total_deductions, 2) : '' }}</td>
                        <td>{{ $quedan_b_net ? number_format($quedan_b_net, 2) : '' }}</td>
                    </tr>

                    <tr>
                        <td><strong>D</strong></td>
                        <td>{{ $quedan_d_lkg ? number_format($quedan_d_lkg, 3) : '' }}</td>
                        <td>{{ $quedan_d_price ? number_format($quedan_d_price, 2) : '' }}</td>
                        <td>{{ $quedan_d_gross ? number_format($quedan_d_gross, 2) : '' }}</td>
                        <td>{{ $quedan_d_liens ? number_format($quedan_d_liens, 2) : '' }}</td>
                        <td>{{ $quedan_d_service ? number_format($quedan_d_service, 2) : '' }}</td>
                        <td>{{ $quedan_d_insurance ? number_format($quedan_d_insurance, 2) : '' }}</td>
                        <td>{{ $quedan_d_tax ? number_format($quedan_d_tax, 2) : '' }}</td>
                        <td>{{ $quedan_d_total_deductions ? number_format($quedan_d_total_deductions, 2) : '' }}</td>
                        <td>{{ $quedan_d_net ? number_format($quedan_d_net, 2) : '' }}</td>
                    </tr>

                    <tr>
                        <td><strong>M</strong></td>
                        <td>{{ $mol_net ? number_format($mol_net, 3) : '' }}</td>
                        <td>{{ $mol_price ? number_format($mol_price, 2) : '' }}</td>
                        <td>{{ $molasses_gross ? number_format($molasses_gross, 2) : '' }}</td>
                        <td>{{ $molasses_liens ? number_format($molasses_liens, 2) : '' }}</td>
                        <td>{{ $molasses_service ? number_format($molasses_service, 2) : '' }}</td>
                        <td>{{ $molasses_insurance ? number_format($molasses_insurance, 2) : '' }}</td>
                        <td>{{ $molasses_tax ? number_format($molasses_tax, 2) : '' }}</td>
                        <td>{{ $molasses_total_deductions ? number_format($molasses_total_deductions, 2) : '' }}</td>
                        <td>{{ $molasses_net ? number_format($molasses_net, 2) : '' }}</td>
                    </tr>

                    <tr>
                        <td><strong>T</strong></td>
                        <td>{{ $trucking_net_cane ? number_format($trucking_net_cane, 3) : '' }}</td>
                        <td></td>
                        <td>{{ $consolidated_display ? number_format($consolidated_display, 2) : '' }}</td>
                        <td colspan="4"></td>
                        <td>{{ $consolidated_fuel_amount ? number_format($consolidated_fuel_amount, 2) : '' }}</td>
                        <td>{{ $consolidated_net ? number_format($consolidated_net, 2) : '' }}</td>
                    </tr>

                    <tr style="border-top: 2px solid #000; border-bottom: 2px solid #000;">
                        <td></td>
                        <td><strong>TOTAL >>>>>>></strong></td>
                        <td></td>
                        <td class="font-bold">{{ $totalGrossProceeds ? number_format($totalGrossProceeds, 2) : '' }}
                        </td>
                        <td class="font-bold">
                            {{ $quedanTotalLiens + $molasses_liens ? number_format($quedanTotalLiens + $molasses_liens, 2) : '' }}
                        </td>
                        <td class="font-bold">
                            {{ $quedanTotalServiceCharge + $molasses_service ? number_format($quedanTotalServiceCharge + $molasses_service, 2) : '' }}
                        </td>
                        <td class="font-bold">
                            {{ $quedanTotalInsurance + $molasses_insurance ? number_format($quedanTotalInsurance + $molasses_insurance, 2) : '' }}
                        </td>
                        <td class="font-bold">
                            {{ $quedanTotalTax + $molasses_tax ? number_format($quedanTotalTax + $molasses_tax, 2) : '' }}
                        </td>
                        <td class="font-bold">{{ $totalDeductions ? number_format($totalDeductions, 2) : '' }}</td>
                        <td class="font-bold">{{ $totalNetProceeds ? number_format($totalNetProceeds, 2) : '' }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="voucher-paper">
                <!-- clear both as original (though no floats, just structural) -->
                <div style="clear: both;"></div>


                <table
                    style="width: 100%; margin-top: 0; font-size: 11px; border-collapse: collapse; table-layout: fixed;">
                    <!-- FIRST ROW: includes Prepared by (rowspan), deduction labels, amounts, payment approved -->
                    <tr>
                        <!-- LEFT CELL: Prepared by with signature line - rowspan 2, so it spans both deduction rows and the NET PROCEEDS row -->
                        <td rowspan="2" style="width: 28%; vertical-align: bottom; padding-right: 20px;">
                            <p style="margin: 0 0 2px 0; font-weight: 500;">Prepared by:</p>
                            <!-- underline as per image -->
                            <div style="border-top: 1px solid #000; width: 90%; margin-top: 3px;"></div>
                            <!-- additional space to mimic signature area -->
                            <div style="height: 18px;"></div>
                        </td>

                        <!-- LABEL COLUMN: LOAN PAYMENT, CASH ADVANCE, OTHERS, FUEL -->
                        <td style="width: 38%; vertical-align: top; padding-right: 12px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 3px 0;" class="label-cell">LOAN PAYMENT</td>
                                </tr>
                                <tr>
                                    <td style="padding: 3px 0;" class="label-cell">CASH ADVANCE PAYMENT</td>
                                </tr>
                                <tr>
                                    <td style="padding: 3px 0;" class="label-cell">OTHERS ACCOUNT</td>
                                </tr>
                                <tr>
                                    <td style="padding: 3px 0;" class="label-cell">FUEL PAYMENT</td>
                                </tr>
                            </table>
                        </td>

                        <!-- AMOUNT COLUMN: dynamic numeric values with bottom border (formatting preserved) -->
                        <td style="width: 22%; vertical-align: top; padding-right: 12px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="text-align: right; padding: 3px 0;">
                                        {{ number_format($loanPayment, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: right; padding: 3px 0;">
                                        {{ number_format($caPayment, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: right; padding: 3px 0;">
                                        {{ number_format($othersPayment, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: right; padding: 3px 0;">
                                        {{ number_format($fuelPayment, 2) }}
                                    </td>
                                </tr>
                            </table>
                        </td>

                        <!-- RIGHT COLUMN: Payment Approved with signature line (aligned at bottom) -->
                        <td style="width: 22%; vertical-align: bottom; text-align: right; padding-left: 8px;">
                            <p style="text-align: right; margin: 0; font-weight: 500;">Payment Approved</p>
                            <div style="border-top: 1px solid #000; width: 100%; margin-top: 6px;"></div>
                            <div style="height: 16px;"></div>
                        </td>
                    </tr>

                    <!-- SECOND ROW: NET PROCEEDS section - positioned directly under the label & amount columns, spanning inside the same grid -->
                    <tr>
                        <!-- NET PROCEEDS label column (replaces deduction labels area) -->
                        <td style="width: 38%; vertical-align: middle; padding-top: 12px;">
                            <p style="margin: 0; font-weight: 700; font-size: 11px;">NET PROCEEDS</p>
                        </td>
                        <!-- NET PROCEEDS amount column -->
                        <td style="width: 22%; vertical-align: middle; padding-top: 12px;">
                            <p style="font-size: 13px; font-weight: 800; margin: 0;" class="net-amount-value">
                                {{ number_format($netProceedsPaid, 2) }}
                            </p>
                        </td>
                        <!-- rightmost cell remains empty (Payment Approved already handled in row1) -->
                        <td style="width: 22%; vertical-align: middle;"></td>
                    </tr>
                </table>

                <!-- RECEIVED SECTION (exactly as original, positioning preserved) -->
                <div style="margin-top: 28px; padding-top: 6px;">
                    <p style="font-size: 11px; margin-bottom: 5px;">
                        Received the sum of
                        <strong> ₱ {{ number_format($netProceedsPaid, 2) }}</strong>
                        in full payment of the above account
                    </p>
                </div>

                <!-- CREDITOR SIGNATURE (positioned right, same as original) -->
                <div style="text-align: right; margin-top: 30px;">
                    <div style="display: inline-block; text-align: center;">
                        <div style="border-top: 1px solid #000; width: 220px; padding-top: 4px; font-size: 11px;">
                            (Creditor)
                        </div>
                    </div>
                </div>


            </div>



            <div class="amount-words">
                <strong>Amount in Words:</strong> {{ convertToWords($netProceedsPaid) }}
            </div>


        </div>
    @endforeach
</body>


</html>
