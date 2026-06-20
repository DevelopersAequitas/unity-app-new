<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your PeersGlobal Membership Has Been Approved</title>
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
                                Dear <strong>{{ $userName ?: 'Peer' }}</strong>,
                            </p>

                            <p style="margin:0 0 20px 0;font-size:17px;line-height:1.6;color:#d9d9d9;">
                                Congratulations! Your PeersGlobal membership has been approved and upgraded to
                                <strong style="color:#ffffff;">Only Unity Peer</strong>.
                            </p>

                            <p style="margin:0 0 22px 0;font-size:17px;line-height:1.6;color:#d9d9d9;">
                                Your membership is valid from
                                <strong style="color:#ffffff;">{{ $membershipStartsAt ?? '—' }}</strong>
                                to
                                <strong style="color:#ffffff;">{{ $membershipEndsAt ?? '—' }}</strong>.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#181818;border:1px solid #333333;border-radius:12px;overflow:hidden;margin:24px 0;">
                                <tr>
                                    <td colspan="2" style="padding:14px 18px;background:#202020;color:#ffffff;font-size:16px;font-weight:700;border-bottom:1px solid #333333;">
                                        Membership Details
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;width:42%;">Name</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $userName ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Email</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $user->email ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Phone</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $user->phone ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Membership</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">Only Unity Peer</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;border-bottom:1px solid #2f2f2f;">Membership Starts At</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;border-bottom:1px solid #2f2f2f;">{{ $membershipStartsAt ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px;color:#a8a8a8;font-size:14px;">Membership Ends At</td>
                                    <td style="padding:12px 18px;color:#ffffff;font-size:14px;font-weight:700;">{{ $membershipEndsAt ?? '—' }}</td>
                                </tr>
                            </table>

                            <p style="margin:0 0 22px 0;font-size:17px;line-height:1.6;color:#d9d9d9;">
                                Thank you for being a part of PeersGlobal Community of Collaboration.
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
