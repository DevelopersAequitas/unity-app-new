<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Unity Peer Membership Status Updated</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#10233f;color:#ffffff;padding:28px 32px;">
                            <h1 style="margin:0;font-size:24px;line-height:32px;">Peers Global / Unity Peer</h1>
                            <p style="margin:8px 0 0;font-size:15px;color:#dbeafe;">Membership status update</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 18px;font-size:16px;line-height:24px;">Dear {{ $userName }},</p>
                            <p style="margin:0 0 22px;font-size:16px;line-height:24px;">Your Unity Peer membership status has been updated. Please find the current membership details below.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 24px;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;border:1px solid #e5e7eb;font-weight:bold;">Member Name</td>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;">{{ $userName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;border:1px solid #e5e7eb;font-weight:bold;">Membership Status</td>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;">{{ $membershipStatus }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;border:1px solid #e5e7eb;font-weight:bold;">Membership Expiry Date</td>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;">{{ $membershipExpiryDate }}</td>
                                </tr>
                            </table>

                            <p style="margin:0;font-size:15px;line-height:23px;">Regards,<br><strong>Peers Global / Unity Peer Team</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:18px 32px;color:#6b7280;font-size:12px;line-height:18px;">
                            &copy; {{ $currentYear }} Peers Global / Unity Peer. This is a membership notification email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
