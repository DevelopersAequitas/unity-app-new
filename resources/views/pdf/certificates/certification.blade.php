<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate</title>
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            padding: 0;
            background: #FFFDF5;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
        }

        .page {
            width: 100%;
            height: 100%;
            padding: 28px;
            box-sizing: border-box;
            background: #FFFDF5;
        }

        .outer-border {
            border: 8px solid #0B1B3A;
            height: 100%;
            padding: 10px;
            box-sizing: border-box;
        }

        .middle-border {
            border: 3px solid #C9A227;
            height: 100%;
            padding: 18px;
            box-sizing: border-box;
        }

        .inner-border {
            border: 1px solid #C9A227;
            height: 100%;
            padding: 28px 46px;
            box-sizing: border-box;
            position: relative;
        }

        .corner {
            position: absolute;
            width: 58px;
            height: 58px;
            border-color: #C9A227;
        }

        .corner.tl { top: 12px; left: 12px; border-top: 4px solid #C9A227; border-left: 4px solid #C9A227; }
        .corner.tr { top: 12px; right: 12px; border-top: 4px solid #C9A227; border-right: 4px solid #C9A227; }
        .corner.bl { bottom: 12px; left: 12px; border-bottom: 4px solid #C9A227; border-left: 4px solid #C9A227; }
        .corner.br { bottom: 12px; right: 12px; border-bottom: 4px solid #C9A227; border-right: 4px solid #C9A227; }

        .certificate-no {
            position: absolute;
            right: 54px;
            top: 44px;
            font-size: 11px;
            color: #6B7280;
        }

        .certificate-no strong { color: #111827; }

        .header {
            text-align: center;
            margin-top: 8px;
        }

        .brand {
            font-size: 34px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #0B1B3A;
            margin: 0;
        }

        .subtitle {
            font-size: 13px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #6B7280;
            margin-top: 8px;
        }

        .program-line {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #C9A227;
            margin-top: 5px;
        }

        .gold-line {
            width: 260px;
            height: 3px;
            background: #C9A227;
            margin: 16px auto 20px auto;
        }

        .certificate-title {
            text-align: center;
            font-size: 30px;
            font-weight: 700;
            color: #0B1B3A;
            margin: 0;
        }

        .presented {
            text-align: center;
            font-size: 16px;
            margin-top: 24px;
            color: #374151;
        }

        .recipient {
            text-align: center;
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 42px;
            font-weight: 700;
            color: #111827;
            margin-top: 12px;
            text-transform: capitalize;
        }

        .name-line {
            width: 440px;
            border-bottom: 2px solid #C9A227;
            margin: 8px auto 14px auto;
        }

        .description {
            width: 78%;
            margin: 0 auto;
            text-align: center;
            font-size: 15px;
            line-height: 1.7;
            color: #374151;
        }

        .details-box {
            width: 82%;
            margin: 24px auto 0 auto;
            border: 1px solid #E5E7EB;
            background: #FFFFFF;
            padding: 12px 18px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .details-table td {
            padding: 7px 8px;
            vertical-align: top;
        }

        .label {
            color: #6B7280;
            font-weight: 700;
            width: 24%;
        }

        .value {
            color: #111827;
            font-weight: 600;
            width: 26%;
        }

        .seal {
            position: absolute;
            left: 50%;
            bottom: 58px;
            margin-left: -45px;
            width: 90px;
            height: 90px;
            border: 4px solid #C9A227;
            border-radius: 50%;
            text-align: center;
            color: #0B1B3A;
            background: #FFF8D6;
        }

        .seal .seal-text-1 {
            font-size: 12px;
            font-weight: 700;
            margin-top: 23px;
            letter-spacing: 1px;
        }

        .seal .seal-text-2 {
            font-size: 10px;
            margin-top: 4px;
            letter-spacing: 1px;
        }

        .footer {
            position: absolute;
            left: 70px;
            right: 70px;
            bottom: 34px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            width: 180px;
            border-bottom: 1.5px solid #111827;
            margin: 0 auto 8px auto;
        }

        .signature-title {
            font-size: 12px;
            font-weight: 700;
            color: #111827;
        }

        .signature-subtitle {
            font-size: 10px;
            color: #6B7280;
            margin-top: 2px;
        }
    </style>
</head>
<body>
@php
    $type = strtolower($submission->certification_type ?? '');
    $title = $type === 'entrepreneur' ? 'Entrepreneur Certification' : 'Leadership Certification';
    $typeLabel = $type === 'entrepreneur' ? 'Entrepreneur' : 'Leadership';
    $issuedDate = $submission->issued_at ? \Carbon\Carbon::parse($submission->issued_at)->format('d M Y') : now()->format('d M Y');
    $approvedDate = $submission->approved_at ? \Carbon\Carbon::parse($submission->approved_at)->format('d M Y') : $issuedDate;
@endphp

<div class="page">
    <div class="outer-border">
        <div class="middle-border">
            <div class="inner-border">
                <div class="corner tl"></div>
                <div class="corner tr"></div>
                <div class="corner bl"></div>
                <div class="corner br"></div>

                <div class="certificate-no">
                    Certificate No: <strong>{{ $submission->certificate_number }}</strong>
                </div>

                <div class="header">
                    <h1 class="brand">Peers Global Unity</h1>
                    <div class="subtitle">Certificate of Achievement</div>
                    <div class="program-line">Official Certification Program</div>
                    <div class="gold-line"></div>
                </div>

                <h2 class="certificate-title">{{ $title }}</h2>

                <div class="presented">This certificate is proudly presented to</div>

                <div class="recipient">{{ $submission->full_name }}</div>
                <div class="name-line"></div>

                <div class="description">
                    In recognition of successfully completing the official
                    {{ strtolower($title) }} assessment and demonstrating the required knowledge,
                    values, and commitment expected by Peers Global Unity.
                </div>

                <div class="details-box">
                    <table class="details-table">
                        <tr>
                            <td class="label">Certification Type</td>
                            <td class="value">{{ $typeLabel }}</td>
                            <td class="label">Business Name</td>
                            <td class="value">{{ $submission->business_name ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Certification Level</td>
                            <td class="value">{{ $submission->certification_level ?: '-' }}</td>
                            <td class="label">Score</td>
                            <td class="value">{{ (int) ($submission->total_score ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Percentage</td>
                            <td class="value">{{ (int) ($submission->percentage ?? 0) }}%</td>
                            <td class="label">Issued Date</td>
                            <td class="value">{{ $issuedDate }}</td>
                        </tr>
                        <tr>
                            <td class="label">Certificate Number</td>
                            <td class="value">{{ $submission->certificate_number }}</td>
                            <td class="label">Approved Date</td>
                            <td class="value">{{ $approvedDate }}</td>
                        </tr>
                    </table>
                </div>

                <div class="seal">
                    <div class="seal-text-1">CERTIFIED</div>
                    <div class="seal-text-2">APPROVED</div>
                </div>

                <div class="footer">
                    <table class="signature-table">
                        <tr>
                            <td>
                                <div class="signature-line"></div>
                                <div class="signature-title">Program Director</div>
                                <div class="signature-subtitle">Peers Global Unity</div>
                            </td>
                            <td></td>
                            <td>
                                <div class="signature-line"></div>
                                <div class="signature-title">Authorized Signatory</div>
                                <div class="signature-subtitle">Certification Department</div>
                            </td>
                        </tr>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
</body>
</html>
