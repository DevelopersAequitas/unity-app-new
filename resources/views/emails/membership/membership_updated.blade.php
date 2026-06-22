<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Peers Global Membership Has Been Updated</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="width:100%;background-color:#f4f6fb;margin:0;padding:24px 12px;">
        <tr>
            <td align="center" style="padding:0;">
                <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="width:100%;max-width:640px;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e6e9f2;box-shadow:0 8px 26px rgba(36,14,92,0.08);">
                    <tr>
                        <td align="center" style="background-color:#240e5c;padding:26px 24px;text-align:center;">
                            @if(! empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="Peers Global" style="display:block;margin:0 auto;max-width:160px;width:160px;height:auto;border:0;outline:none;text-decoration:none;">
                            @else
                                <div style="font-size:24px;font-weight:700;line-height:1.2;color:#ffffff;">Peers Global</div>
                                <div style="font-size:13px;line-height:1.4;color:#e6ddff;">Peers Global Unity</div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 28px 16px 28px;">
                            <h1 style="margin:0 0 18px 0;font-size:24px;line-height:1.3;color:#240e5c;font-weight:700;text-align:center;">Membership Update Notification</h1>

                            <p style="margin:0 0 14px 0;font-size:16px;line-height:1.6;color:#334155;">Dear <strong>{{ $peerName }}</strong>,</p>

                            <p style="margin:0 0 22px 0;font-size:16px;line-height:1.6;color:#334155;">
                                Your Peers Global membership details have been updated successfully. Please find the updated membership information below.
                            </p>

                            <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="width:100%;border-collapse:separate;border-spacing:0;border:1px solid #dfe6f3;border-radius:12px;overflow:hidden;background-color:#ffffff;">
                                <tr>
                                    <td colspan="2" style="padding:14px 18px;background-color:#f7f4ff;color:#240e5c;font-size:16px;line-height:1.4;font-weight:700;border-bottom:1px solid #dfe6f3;">Membership Details</td>
                                </tr>
                                @foreach([
                                    'Peer Name' => $peerName,
                                    'Email' => $details['email'] ?? '—',
                                    'Old Membership Status' => $details['old_status'] ?? '—',
                                    'New Membership Status' => $details['new_status'] ?? '—',
                                    'Old Membership Expiry' => $details['old_expiry'] ?? '—',
                                    'New Membership Expiry' => $details['new_expiry'] ?? '—',
                                    'Updated At' => $details['updated_at'] ?? '—',
                                ] as $label => $value)
                                    <tr>
                                        <td width="42%" style="padding:12px 18px;border-bottom:1px solid #edf1f7;color:#64748b;font-size:14px;line-height:1.5;vertical-align:top;">{{ $label }}</td>
                                        <td style="padding:12px 18px;border-bottom:1px solid #edf1f7;color:#111827;font-size:14px;line-height:1.5;font-weight:600;vertical-align:top;word-break:break-word;">{{ filled($value) ? $value : '—' }}</td>
                                    </tr>
                                @endforeach
                            </table>

                            @if(! empty($details['admin_note']))
                                <div style="margin:20px 0 0 0;padding:14px 16px;background-color:#fff8e6;border:1px solid #f4d58d;border-radius:12px;color:#59400b;font-size:14px;line-height:1.6;">
                                    <strong>Admin Note:</strong> {{ $details['admin_note'] }}
                                </div>
                            @endif

                            <p style="margin:24px 0 0 0;font-size:16px;line-height:1.7;color:#334155;">
                                Warm regards,<br>
                                <strong style="color:#240e5c;">Team Peers Global</strong><br>
                                Peers Global Unity
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px;background-color:#240e5c;text-align:center;">
                            <p style="margin:0 0 8px 0;font-size:14px;line-height:1.5;color:#ffffff;font-weight:700;">Peers are partners in business and friends in life.</p>
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#e6ddff;">This is an automated notification from Peers Global. Please do not reply to this email.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
