@php
    $score = $submission->total_score ?? 0;
    $percentage = $submission->percentage === null ? null : rtrim(rtrim(number_format((float) $submission->percentage, 2), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrepreneur Certification Approved</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb; padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px; background:#ffffff; border:1px solid #dbeafe; border-radius:18px; overflow:hidden; box-shadow:0 12px 34px rgba(15, 23, 42, 0.10);">
                    <tr>
                        <td style="background:#0f172a; padding:26px 28px; text-align:center;">
                            <div style="color:#bfdbfe; font-size:13px; letter-spacing:2px; text-transform:uppercase; font-weight:bold;">Peers Global Unity</div>
                            <h1 style="margin:10px 0 0; color:#ffffff; font-size:28px; line-height:1.25;">Certificate of Achievement</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 30px 18px; text-align:center;">
                            <div style="display:inline-block; padding:8px 14px; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:13px; font-weight:bold; border:1px solid #bfdbfe;">Entrepreneur Certification</div>
                            <p style="margin:24px 0 10px; color:#64748b; font-size:15px;">This certificate is proudly presented to</p>
                            <div style="font-family:Georgia, 'Times New Roman', serif; color:#111827; font-size:34px; line-height:1.2; font-weight:bold;">{{ $submission->full_name }}</div>
                            <p style="margin:16px auto 0; max-width:520px; color:#475569; font-size:16px; line-height:1.65;">
                                Congratulations! Your Entrepreneur Certification request has been approved. We appreciate your commitment to business growth, learning, and the Peers Global Unity community.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 30px 4px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                                <tr>
                                    <td style="padding:18px; background:#f8fafc; border-bottom:1px solid #e5e7eb;" colspan="2">
                                        <strong style="color:#0f172a; font-size:16px;">Certification Details</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; color:#64748b; width:42%; border-bottom:1px solid #eef2f7;">Business Name</td>
                                    <td style="padding:14px 18px; color:#111827; border-bottom:1px solid #eef2f7;">{{ $submission->business_name ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; color:#64748b; border-bottom:1px solid #eef2f7;">Email</td>
                                    <td style="padding:14px 18px; color:#111827; border-bottom:1px solid #eef2f7;">{{ $submission->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; color:#64748b; border-bottom:1px solid #eef2f7;">Contact Number</td>
                                    <td style="padding:14px 18px; color:#111827; border-bottom:1px solid #eef2f7;">{{ $submission->contact_no ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; color:#64748b; border-bottom:1px solid #eef2f7;">Certification Tier</td>
                                    <td style="padding:14px 18px; color:#166534; font-weight:bold; border-bottom:1px solid #eef2f7;">{{ $submission->certification_tier ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; color:#64748b; border-bottom:1px solid #eef2f7;">Total Score</td>
                                    <td style="padding:14px 18px; color:#111827; border-bottom:1px solid #eef2f7;">{{ $score }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; color:#64748b; border-bottom:1px solid #eef2f7;">Percentage</td>
                                    <td style="padding:14px 18px; color:#111827; border-bottom:1px solid #eef2f7;">{{ $percentage === null ? '—' : $percentage.'%' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; color:#64748b;">Approval Date</td>
                                    <td style="padding:14px 18px; color:#111827;">{{ $approvalDate }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 30px 34px; text-align:center;">
                            <p style="margin:0; color:#475569; font-size:15px; line-height:1.6;">Thank you for being part of Peers Global Unity. We wish you continued success in your entrepreneurial journey.</p>
                            <p style="margin:22px 0 0; color:#0f172a; font-weight:bold;">Warm regards,<br>Peers Global Unity</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
