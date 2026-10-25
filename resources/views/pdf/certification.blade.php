<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>KUSUG-PA Production Certification</title>
    <style>
        @include('pdf.partials.formal-font')

        @page { margin: 55px 60px 50px; }
        body { font-family: 'Tinos', 'Times New Roman', serif; color: #111; font-size: 12.5px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .sans { font-family: Helvetica, Arial, sans-serif; }

        .letterhead { width: 100%; border-collapse: collapse; }
        .letterhead td { vertical-align: middle; padding: 0; }
        .letterhead .logo { width: 100px; }
        .letterhead .logo img { width: 92px; height: 92px; }
        .org-name { font-family: Helvetica, Arial, sans-serif; font-weight: bold; font-size: 17px; color: #14301f; letter-spacing: -0.2px; }
        .org-line { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #333; text-align: center; line-height: 1.55; margin-left: 40px; }

        h1 { text-align: center; font-size: 25px; font-weight: bold; letter-spacing: 7px; margin: 70px 0 50px; }

        .lead { text-indent: 95px; text-align: justify; line-height: 1.45; font-size: 13.5px; }
        .lead .name { font-family: Helvetica, Arial, sans-serif; font-weight: bold; font-size: 13px; }
        .mill { letter-spacing: 2px; }

        table.production { width: 88%; margin: 18px auto 22px; border-collapse: collapse; }
        table.production th, table.production td { border: 1.2px solid #111; padding: 3px 6px; font-size: 12.5px; }
        table.production th { font-weight: normal; text-align: center; line-height: 1.25; }
        table.production td { text-align: right; height: 16px; }
        table.production td.year { text-align: center; font-weight: bold; }
        table.production tr.total td { font-weight: bold; }

        .issued { text-indent: 95px; text-align: justify; font-size: 13.5px; margin: 0; }
        .given { font-size: 12.5px; margin: 4px 0 0; }
        .given em { font-style: italic; }
        .given .fill { font-family: Helvetica, Arial, sans-serif; font-weight: bold; text-decoration: underline; font-size: 12px; }

        .signatures { width: 100%; margin-top: 75px; border-collapse: collapse; }
        .signatures td { width: 50%; vertical-align: top; padding: 0; }
        .signatures .label { font-style: italic; font-size: 13px; margin: 0 0 55px 50px; }
        .signatures .approved .label { margin-left: 160px; }
        .signatures .sig-name { font-family: Helvetica, Arial, sans-serif; font-weight: bold; font-size: 13px; }
        .signatures .sig-title { font-family: Helvetica, Arial, sans-serif; font-size: 13px; padding-left: 12px; }
        .signatures .approved .block { margin-left: 150px; }

        .empty { margin-top: 80px; text-align: center; color: #555; }
    </style>
</head>
<body>
    @forelse ($certificates as $certificate)
        <div class="page">
            <table class="letterhead">
                <tr>
                    <td class="logo"><img src="{{ $logoPath }}" alt="KUSUG-PA logo"></td>
                    <td>
                        <div class="org-name">KABANKALAN UNITED SUGAR PLANTERS ASSOCIATION, INC.</div>
                        <div class="org-line">
                            Door # 3 NZ Arcade, Repullo Street, Kabankalan City<br>
                            Tel. # 471-0021<br>
                            Tin # 450-634-331
                        </div>
                    </td>
                </tr>
            </table>

            <h1>CERTIFICATION</h1>

            <p class="lead">
                This is to certify that <span class="name">{{ strtoupper($certificate['planter_name']) }}</span>
                has the following production{{ $weekRange ? ' for ' . $weekRange : '' }} based on Sugar and Molasses Production of
                <span class="mill">{{ strtoupper($signatories['mill_name']) }}</span>.
            </p>

            <table class="production">
                <thead>
                    <tr>
                        <th>CROP<br>YEAR</th>
                        <th>NET<br>TONS</th>
                        <th>NET<br>SUGAR(LKG)</th>
                        <th>NET<br>MOL( metric ton)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td></td><td></td><td></td><td></td></tr>
                    @foreach ($certificate['rows'] as $row)
                        <tr>
                            <td class="year">{{ $row['crop_year_label'] }}</td>
                            <td>{{ number_format($row['net_tons'], 2) }}</td>
                            <td>{{ number_format($row['net_lkg'], 2) }}</td>
                            <td>{{ number_format($row['net_mol'], 3) }}</td>
                        </tr>
                    @endforeach
                    @if ($certificate['rows']->count() > 1)
                        <tr class="total">
                            <td class="year">TOTAL</td>
                            <td>{{ number_format($certificate['rows']->sum('net_tons'), 2) }}</td>
                            <td>{{ number_format($certificate['rows']->sum('net_lkg'), 2) }}</td>
                            <td>{{ number_format($certificate['rows']->sum('net_mol'), 3) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <p class="issued">This certificate is issued upon the request of the planter stated above.</p>
            <p class="given">
                <em>Given this</em>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="fill">{{ $issuedDay }}</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <em>day of</em>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="fill">{{ $issuedMonthYear }}.</span>
            </p>

            <table class="signatures">
                <tr>
                    <td>
                        <p class="label">Prepared by:</p>
                        <div class="sig-name">{{ strtoupper($signatories['prepared_name']) }}</div>
                        <div class="sig-title">{{ strtoupper($signatories['prepared_title']) }}</div>
                    </td>
                    <td class="approved">
                        <p class="label">Approved by:</p>
                        <div class="block">
                            <div class="sig-name">{{ strtoupper($signatories['approved_name']) }}</div>
                            <div class="sig-title">{{ strtoupper($signatories['approved_title']) }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @empty
        <p class="empty">No production records found for the selected planters.</p>
    @endforelse
</body>
</html>
