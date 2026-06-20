<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your PeersGlobal Membership Has Been Approved</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#333333;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f3f4f6;padding:20px 10px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#26006b;padding:16px 20px;text-align:center;">
                            @if(!empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="PeersGlobal" style="width:170px;max-width:170px;height:auto;display:block;margin:0 auto;">
                            @else
                                <div style="color:#ffffff;font-size:22px;font-weight:700;line-height:1.2;">PeersGlobal</div>
                                <div style="color:#ffffff;font-size:11px;line-height:1.3;">Community of Collaboration</div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#ffffff;padding:12px 10px 10px 10px;color:#333333;font-size:16px;line-height:1.5;">
                            <p style="margin:0 0 24px 0;">
                                Dear <strong>{{ $userName ?: 'Peer' }}</strong>,
                            </p>

                            <p style="margin:0 0 24px 0;">
                                Congratulations! Your PeersGlobal membership has been approved and upgraded to
                                <strong>Only Unity Peer</strong>.
                            </p>

                            <p style="margin:0 0 24px 0;">
                                Your membership is valid from
                                <strong>{{ $membershipStartsAt ?? '—' }}</strong>
                                to
                                <strong>{{ $membershipEndsAt ?? '—' }}</strong>.
                            </p>

                            <p style="margin:0 0 8px 0;">
                                <strong>Membership Details:</strong>
                            </p>

                            <p style="margin:0 0 24px 0;">
                                Name: <strong>{{ $userName ?: '—' }}</strong><br>
                                Email: <strong>{{ $user->email ?: '—' }}</strong><br>
                                Phone: <strong>{{ $user->phone ?: '—' }}</strong><br>
                                Membership: <strong>Only Unity Peer</strong><br>
                                Membership Starts At: <strong>{{ $membershipStartsAt ?? '—' }}</strong><br>
                                Membership Ends At: <strong>{{ $membershipEndsAt ?? '—' }}</strong>
                            </p>

                            <p style="margin:0 0 24px 0;">
                                Thank you for being a part of PeersGlobal Community of Collaboration.
                            </p>

                            <p style="margin:0;">
                                With appreciation,<br>
                                <strong>Peers Global Team</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#26006b;padding:16px 20px;text-align:center;">
                            <p style="margin:0;color:#ffffff;font-size:15px;line-height:1.4;font-weight:700;">
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
