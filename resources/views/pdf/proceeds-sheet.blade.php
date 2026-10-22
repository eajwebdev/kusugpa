<!-- resources/views/pdf/proceeds-sheet.blade.php -->
{{-- Laid out like KUSUG-PA's printed "Proceeds of Quedan, Molasses & Trucking Allowance" signature sheet --}}
@php
    $money = fn ($v) => round((float) $v, 2) != 0 ? number_format((float) $v, 2) : '';
    $total = fn ($rows, $key) => $rows->sum($key);
    $pageCount = max(1, $pages->count());
    // Column widths (inches); the rest of the page width is the signature space
    $w = ['no' => 0.45, 'name' => 1.65, 'check' => 0.6, 'truck' => 0.8, 'a' => 0.8, 'b' => 0.85, 'ded' => 0.8, 'total' => 0.85];
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Proceeds of Quedan, Molasses &amp; Trucking Allowance - Week {{ $weekLabel }}</title>
    <style>
        @page {
            margin: 0.45in 0.3in 0.35in 0.3in;
        }

        body {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8.5pt;
            color: #111;
            margin: 0;
        }

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            padding: 0 2px;
            vertical-align: bottom;
        }

        .center {
            text-align: center;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .head th {
            font-weight: normal;
            text-align: center;
            line-height: 1.35;
            padding-bottom: 3px;
            border-bottom: 1px dashed #111;
        }

        .head th.left {
            text-align: left;
        }

        .head th.gap {
            border-bottom: none;
        }

        .row td {
            height: 0.44in;
        }

        .sign {
            border-bottom: 0.8px solid #111;
        }

        .totals td {
            padding-top: 4px;
        }

        .rule td.ruled {
            border-bottom: 1px dashed #111;
        }
    </style>
</head>

<body>
    @forelse ($pages as $pageIndex => $pageRows)
        <div class="page">
            <!-- Header -->
            <div class="center" style="line-height: 1.45">
                <div>KABANKALAN UNITED SUGAR PLANTERS ASSN., INC.</div>
                <div>( {{ implode(' ', mb_str_split(str_replace(' ', '', $org['short_name']))) }} )</div>
                <div>Kabankalan City, Neg. Occ.</div>
            </div>

            <table style="margin-top: 0.3in">
                <tr>
                    <td style="width: 1.9in"></td>
                    <td style="width: 6in; white-space: nowrap">PROCEEDS OF QUEDAN, MOLASSES &amp; TRUCKING ALLOWANCE</td>
                </tr>
            </table>
            <table style="margin-top: 2px">
                <tr>
                    <td style="width: 1.0in"></td>
                    <td style="width: 0.8in">WEEK NO:</td>
                    <td style="width: 2.5in">{{ $weekLabel }}</td>
                    <td style="width: 1.0in">DATE ISSUED:</td>
                    <td style="width: 1.0in">{{ $dateIssued }}</td>
                    <td style="white-space: nowrap">PAGE NO. {{ str_pad($pageIndex + 1, 3, '0', STR_PAD_LEFT) }}</td>
                </tr>
            </table>

            <!-- Planters -->
            <table style="margin-top: 0.3in">
                <thead>
                    <tr class="head">
                        <th class="left" style="width: {{ $w['no'] }}in">NUMBER</th>
                        <th class="left" style="width: {{ $w['name'] }}in">NAME OF PLANTERS</th>
                        <th style="width: {{ $w['check'] }}in">CHECK<br>NUMBER</th>
                        <th style="width: {{ $w['truck'] }}in">TRUCKING<br>ALLOWANCE</th>
                        @if ($showQuedanA)
                            <th style="width: {{ $w['a'] }}in">-A- QUEDAN<br>PROCEEDS</th>
                        @endif
                        <th style="width: {{ $w['b'] }}in">-B- &amp; MOL.<br>PROCEEDS</th>
                        @if ($showDeduction)
                            <th style="width: {{ $w['ded'] }}in">LESS<br>C.A./LOAN</th>
                        @endif
                        <th style="width: {{ $w['total'] }}in">TOTAL NET<br>PROCEEDS</th>
                        <th>SIGNATURE</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pageRows as $r)
                        <tr class="row">
                            <td>{{ $r['code'] }}</td>
                            <td>{{ $r['name'] }}</td>
                            <td class="center">{{ $r['check_no'] }}</td>
                            <td class="num">{{ $money($r['trucking']) }}</td>
                            @if ($showQuedanA)
                                <td class="num">{{ $money($r['quedan_a']) }}</td>
                            @endif
                            <td class="num">{{ $money($r['quedan_b_mol']) }}</td>
                            @if ($showDeduction)
                                <td class="num">{{ $money($r['deduction']) }}</td>
                            @endif
                            <td class="num">{{ $money($r['total']) }}</td>
                            <td style="padding-left: 10px"><div class="sign">&nbsp;</div></td>
                        </tr>
                    @endforeach

                    <!-- Page totals -->
                    <tr class="rule">
                        <td colspan="3" style="height: 0.25in"></td>
                        <td class="ruled"></td>
                        @if ($showQuedanA)
                            <td class="ruled"></td>
                        @endif
                        <td class="ruled"></td>
                        @if ($showDeduction)
                            <td class="ruled"></td>
                        @endif
                        <td class="ruled"></td>
                        <td></td>
                    </tr>
                    <tr class="totals">
                        <td colspan="3">PAGE-TOTALS</td>
                        <td class="num">{{ $money($total($pageRows, 'trucking')) }}</td>
                        @if ($showQuedanA)
                            <td class="num">{{ $money($total($pageRows, 'quedan_a')) }}</td>
                        @endif
                        <td class="num">{{ $money($total($pageRows, 'quedan_b_mol')) }}</td>
                        @if ($showDeduction)
                            <td class="num">{{ $money($total($pageRows, 'deduction')) }}</td>
                        @endif
                        <td class="num">{{ $money($total($pageRows, 'total')) }}</td>
                        <td></td>
                    </tr>
                    @if ($loop->last && $pageCount > 1)
                        <tr class="totals">
                            <td colspan="3" style="padding-top: 8px">GRAND-TOTALS</td>
                            <td class="num" style="padding-top: 8px">{{ $money($total($rows, 'trucking')) }}</td>
                            @if ($showQuedanA)
                                <td class="num" style="padding-top: 8px">{{ $money($total($rows, 'quedan_a')) }}</td>
                            @endif
                            <td class="num" style="padding-top: 8px">{{ $money($total($rows, 'quedan_b_mol')) }}</td>
                            @if ($showDeduction)
                                <td class="num" style="padding-top: 8px">{{ $money($total($rows, 'deduction')) }}</td>
                            @endif
                            <td class="num" style="padding-top: 8px">{{ $money($total($rows, 'total')) }}</td>
                            <td style="padding-top: 8px; padding-left: 10px">{{ $rows->count() }} planter(s)</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @empty
        <div class="center" style="margin-top: 2in">No planter proceeds for week {{ $weekLabel }}.</div>
    @endforelse
</body>

</html>
