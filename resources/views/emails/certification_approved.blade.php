<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your {{ ucfirst($submission->certification_type) }} Certification Has Been Approved</title>
</head>
<body style="margin:0;padding:0;background:#1f1f1f;font-family:Arial,Helvetica,sans-serif;color:#e5e7eb;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#1f1f1f;padding:28px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px;background:#101010;border-radius:16px;overflow:hidden;border:1px solid #2a2a2a;">
                    <tr>
                        <td style="background:#26006b;padding:28px 24px;text-align:center;">
                            @if(!empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="PeersGlobal" style="width:260px;max-width:260px;height:auto;display:block;margin:0 auto;">
                            @else
                                <div style="color:#ffffff;font-size:26px;font-weight:700;line-height:1.2;">PeersGlobal</div>
                                <div style="color:#ffffff;font-size:13px;line-height:1.2;">Community of Collaboration</div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#101010;padding:30px 28px;color:#d9d9d9;font-size:16px;line-height:1.6;">
                            <p style="margin:0 0 22px 0;font-size:22px;line-height:1.4;color:#ffffff;">
                                Dear <strong>{{ $submission->full_name }}</strong>,
                            </p>

                            <p style="margin:0 0 20px 0;font-size:17px;line-height:1.6;color:#d9d9d9;">
                                Congratulations! Your request for <strong style="color:#ffffff;">{{ ucfirst($submission->certification_type) }} Certification</strong> has been reviewed and approved by our team.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#181818;border:1px solid #333333;border-radius:12px;overflow:hidden;margin:24px 0;">
                                <tr>
                                    <td colspan="2" style="padding:14px 18px;background:#202020;color:#ffffff;font-size:16px;font-weight:700;border-bottom:1px solid #333333;">
                                        Certification Details
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;width:42%;">Recipient Name</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $submission->full_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Certification Type</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ ucfirst($submission->certification_type) }}</td>
                                </tr>
                                @if(!empty($submission->business_name))
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Business Name</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $submission->business_name }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Certification Level</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $submission->certification_level ?: 'Standard' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Assessment Score</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $submission->total_score }} ({{ $submission->percentage }}%)</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Certificate Number</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $submission->certificate_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;">Issued Date</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;">{{ optional($submission->issued_at)->format('d M Y') ?: now()->format('d M Y') }}</td>
                                </tr>
                            </table>

                            @if(!empty($submission->admin_note))
                            <div style="background:#181818;border:1px dashed #444444;border-radius:8px;padding:14px 18px;margin-bottom:24px;color:#cccccc;font-size:14px;">
                                <strong style="color:#ffffff;">Admin Note:</strong> {{ $submission->admin_note }}
                            </div>
                            @endif

                            @if(!empty($submission->certificate_download_url))
                            <div style="text-align:center;margin:32px 0;">
                                <a href="{{ $submission->certificate_download_url }}" target="_blank" rel="noopener" style="background:#4f46e5;color:#ffffff;padding:14px 28px;text-decoration:none;font-size:16px;font-weight:700;border-radius:8px;display:inline-block;">
                                    View & Download Certificate
                                </a>
                            </div>
                            @endif

                            <p style="margin:0 0 22px 0;font-size:17px;line-height:1.6;color:#d9d9d9;">
                                Thank you for your commitment to excellence and growth in the PeersGlobal community.
                            </p>

                            <p style="margin:0;font-size:17px;line-height:1.6;color:#d9d9d9;">
                                With appreciation,<br>
                                <strong style="color:#ffffff;">Peers Global Team</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#26006b;padding:18px 24px;text-align:center;">
                            <p style="margin:0;color:#ffffff;font-size:16px;line-height:1.4;font-weight:700;">
                                Peers are partners in business and friends in life.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
