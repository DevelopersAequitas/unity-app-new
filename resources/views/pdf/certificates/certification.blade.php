<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate</title>
    <style>
        @page {
            margin: 0;
            size: A4 landscape;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            background: #fff7df;
        }

        .certificate-page {
            width: 1122px;
            height: 793px;
            padding: 24px;
            background: #fff7df;
            position: relative;
        }

        .outer {
            width: 100%;
            height: 100%;
            border: 10px solid #0B1B3A;
            padding: 9px;
            background: #fffdf5;
        }

        .gold-border {
            width: 100%;
            height: 100%;
            border: 4px solid #C9A227;
            padding: 12px;
            background: #fffdf5;
        }

        .inner {
            width: 100%;
            height: 100%;
            border: 1px solid #D7B94F;
            position: relative;
            padding: 28px 56px 34px 56px;
            background: #fffdf5;
            overflow: hidden;
        }

        .watermark {
            position: absolute;
            top: 285px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 82px;
            font-weight: 700;
            color: #0B1B3A;
            opacity: 0.045;
            letter-spacing: 8px;
            z-index: 0;
        }

        .corner {
            position: absolute;
            width: 76px;
            height: 76px;
            z-index: 2;
        }

        .corner-tl {
            top: 18px;
            left: 18px;
            border-top: 5px solid #C9A227;
            border-left: 5px solid #C9A227;
        }

        .corner-tr {
            top: 18px;
            right: 18px;
            border-top: 5px solid #C9A227;
            border-right: 5px solid #C9A227;
        }

        .corner-bl {
            bottom: 18px;
            left: 18px;
            border-bottom: 5px solid #C9A227;
            border-left: 5px solid #C9A227;
        }

        .corner-br {
            bottom: 18px;
            right: 18px;
            border-bottom: 5px solid #C9A227;
            border-right: 5px solid #C9A227;
        }

        .content {
            position: relative;
            z-index: 5;
        }

        .certificate-number-top {
            position: absolute;
            top: 22px;
            right: 52px;
            font-size: 11px;
            color: #6B7280;
        }

        .certificate-number-top strong {
            color: #0B1B3A;
        }

        .logo-wrap {
            text-align: center;
            height: 70px;
            margin-top: 4px;
        }

        .logo {
            max-height: 58px;
            max-width: 190px;
            margin: 0 auto;
        }

        .logo-fallback {
            width: 62px;
            height: 62px;
            margin: 0 auto;
            border: 3px solid #C9A227;
            border-radius: 50%;
            background: #0B1B3A;
            color: #F7E7A1;
            line-height: 56px;
            text-align: center;
            font-size: 19px;
            font-weight: 900;
            letter-spacing: 1px;
        }

        .brand {
            text-align: center;
            font-size: 34px;
            font-weight: 800;
            color: #0B1B3A;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .subtitle {
            text-align: center;
            margin-top: 6px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: #6B7280;
            font-weight: 700;
        }

        .program-line {
            text-align: center;
            margin-top: 5px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #C9A227;
            font-weight: 800;
        }

        .gold-line {
            width: 300px;
            height: 4px;
            margin: 15px auto 18px auto;
            background: #C9A227;
        }

        .title {
            text-align: center;
            font-size: 34px;
            line-height: 1;
            font-weight: 800;
            color: #0B1B3A;
            margin: 0;
        }

        .presented {
            text-align: center;
            font-size: 16px;
            color: #374151;
            margin-top: 22px;
        }

        .recipient {
            text-align: center;
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 48px;
            font-weight: 700;
            color: #111827;
            text-transform: capitalize;
            margin-top: 8px;
            line-height: 1.1;
        }

        .recipient-line {
            width: 460px;
            height: 2px;
            background: #C9A227;
            margin: 8px auto 14px auto;
        }

        .description {
            width: 82%;
            margin: 0 auto;
            text-align: center;
            font-size: 15px;
            line-height: 1.55;
            color: #374151;
        }

        .details-box {
            width: 86%;
            margin: 20px auto 0 auto;
            border: 1.5px solid #D7B94F;
            background: #ffffff;
            padding: 12px 16px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        .details-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #F3E8B5;
            vertical-align: top;
        }

        .details-table tr:last-child td {
            border-bottom: none;
        }

        .label {
            width: 22%;
            color: #6B7280;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }

        .value {
            width: 28%;
            color: #111827;
            font-weight: 700;
        }

        .seal {
            position: absolute;
            left: 50%;
            bottom: 54px;
            margin-left: -48px;
            width: 96px;
            height: 96px;
            border: 5px solid #C9A227;
            border-radius: 50%;
            background: #FFF4C2;
            color: #0B1B3A;
            text-align: center;
            z-index: 8;
        }

        .seal-inner {
            width: 76px;
            height: 76px;
            margin: 5px auto 0 auto;
            border: 1px solid #C9A227;
            border-radius: 50%;
            padding-top: 20px;
        }

        .seal-main {
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 1px;
        }

        .seal-sub {
            font-size: 9px;
            margin-top: 4px;
            letter-spacing: 1.5px;
            font-weight: 700;
        }

        .footer {
            position: absolute;
            left: 70px;
            right: 70px;
            bottom: 34px;
            z-index: 7;
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
            width: 190px;
            height: 1px;
            border-bottom: 2px solid #111827;
            margin: 0 auto 8px auto;
        }

        .signature-title {
            font-size: 12px;
            font-weight: 800;
            color: #111827;
        }

        .signature-subtitle {
            font-size: 10px;
            color: #6B7280;
            margin-top: 3px;
        }

        .ribbon-left {
            position: absolute;
            top: 126px;
            left: 0;
            width: 120px;
            height: 30px;
            background: #0B1B3A;
            color: #ffffff;
            font-size: 11px;
            line-height: 30px;
            text-align: center;
            letter-spacing: 1px;
            font-weight: 800;
        }

        .ribbon-right {
            position: absolute;
            top: 126px;
            right: 0;
            width: 120px;
            height: 30px;
            background: #C9A227;
            color: #0B1B3A;
            font-size: 11px;
            line-height: 30px;
            text-align: center;
            letter-spacing: 1px;
            font-weight: 900;
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

<div class="certificate-page">
    <div class="outer">
        <div class="gold-border">
            <div class="inner">
                <div class="watermark">CERTIFIED</div>

                <div class="corner corner-tl"></div>
                <div class="corner corner-tr"></div>
                <div class="corner corner-bl"></div>
                <div class="corner corner-br"></div>

                <div class="ribbon-left">OFFICIAL</div>
                <div class="ribbon-right">APPROVED</div>

                <div class="certificate-number-top">
                    Certificate No: <strong>{{ $submission->certificate_number }}</strong>
                </div>

                <div class="content">
                    <div class="logo-wrap">
                        @if (! empty($logoSrc))
                            <img src="{{ $logoSrc }}" class="logo" alt="Peers Global Unity Logo">
                        @else
                            <div class="logo-fallback">PGU</div>
                        @endif
                    </div>

                    <div class="brand">Peers Global Unity</div>
                    <div class="subtitle">Certificate of Achievement</div>
                    <div class="program-line">Official Certification Program</div>
                    <div class="gold-line"></div>

                    <h1 class="title">{{ $title }}</h1>

                    <div class="presented">This certificate is proudly presented to</div>

                    <div class="recipient">{{ $submission->full_name }}</div>
                    <div class="recipient-line"></div>

                    <div class="description">
                        In recognition of successfully completing the official
                        <strong>{{ $title }}</strong> assessment and demonstrating the values,
                        knowledge, commitment, and excellence expected by Peers Global Unity.
                    </div>

                    <div class="details-box">
                        <table class="details-table">
                            <tr>
                                <td class="label">Certification Type</td>
                                <td class="value">{{ $typeLabel }}</td>
                                <td class="label">Business Name</td>
                                <td class="value">{{ $submission->business_name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Certification Level</td>
                                <td class="value">{{ $submission->certification_level ?? '-' }}</td>
                                <td class="label">Score</td>
                                <td class="value">{{ $submission->total_score ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td class="label">Percentage</td>
                                <td class="value">{{ $submission->percentage ?? 0 }}%</td>
                                <td class="label">Certificate Number</td>
                                <td class="value">{{ $submission->certificate_number }}</td>
                            </tr>
                            <tr>
                                <td class="label">Issued Date</td>
                                <td class="value">{{ $issuedDate }}</td>
                                <td class="label">Approved Date</td>
                                <td class="value">{{ $approvedDate }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="seal">
                    <div class="seal-inner">
                        <div class="seal-main">CERTIFIED</div>
                        <div class="seal-sub">APPROVED</div>
                    </div>
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
