<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your PeersGlobal Membership Has Been Approved</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f6f8;padding:30px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 10px 30px rgba(17,24,39,0.08);">
                    <tr>
                        <td style="background:#101828;padding:26px 30px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:26px;font-weight:700;letter-spacing:0.2px;">PeersGlobal</h1>
                            <p style="margin:6px 0 0;color:#d1d5db;font-size:14px;">Community of Collaboration</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:34px 30px;">
                            <h2 style="margin:0 0 16px;color:#111827;font-size:24px;line-height:1.25;">Membership Approved</h2>

                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#374151;">
                                Hello {{ $userName }},
                            </p>

                            <p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#374151;">
                                Congratulations! Your PeersGlobal membership has been approved and upgraded to
                                <strong>Only Unity Peer</strong>.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:24px 0;">
                                <tr>
                                    <td colspan="2" style="background:#f9fafb;padding:14px 18px;font-size:16px;font-weight:700;color:#111827;">
                                        Membership Details
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:14px;width:38%;">Name</td>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#111827;font-size:14px;font-weight:600;">{{ $userName ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:14px;">Email</td>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#111827;font-size:14px;font-weight:600;">{{ $user->email ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:14px;">Phone</td>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#111827;font-size:14px;font-weight:600;">{{ $user->phone ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:14px;">Membership</td>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#111827;font-size:14px;font-weight:600;">Only Unity Peer</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:14px;">Membership Starts At</td>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#111827;font-size:14px;font-weight:600;">{{ $membershipStartsAt ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:14px;">Membership Ends At</td>
                                    <td style="padding:12px 18px;border-top:1px solid #e5e7eb;color:#111827;font-size:14px;font-weight:600;">{{ $membershipEndsAt ?? '—' }}</td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0;font-size:15px;line-height:1.6;color:#374151;">
                                Thank you for being a part of PeersGlobal Community of Collaboration.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f9fafb;padding:18px 30px;text-align:center;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;color:#6b7280;font-size:13px;">
                                © {{ $currentYear ?? date('Y') }} PeersGlobal. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
