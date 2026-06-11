<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leadership Certification Approved</title>
</head>
<body style="margin:0; padding:0; background-color:#2f2f35; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; background-color:#2f2f35; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; max-width:600px; border-collapse:separate; border-spacing:0; overflow:hidden; border-radius:14px;">
                    <tr>
                        <td align="center" style="background-color:#240e5c; padding:18px 24px; border-top-left-radius:14px; border-top-right-radius:14px;">
                            <img src="https://unity.peersglobal.com/wp-content/uploads/2025/08/peersglobal_white-removebg-preview.png" alt="PeersGlobal" width="145" style="display:block; width:145px; max-width:145px; height:auto; margin:0 auto; border:0; outline:none; text-decoration:none;" />
                            <div style="color:#e9ddff; font-size:12px; line-height:18px; letter-spacing:.6px; margin-top:4px;">Community of Collaboration</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#111111; padding:34px 30px 30px; color:#ffffff;">
                            <p style="margin:0 0 18px; font-size:18px; line-height:28px; color:#ffffff;">Dear <strong style="color:#c9a8ff;">{{ $recipientName }}</strong>,</p>

                            <h1 style="margin:0 0 14px; color:#ffffff; font-size:28px; line-height:36px; font-weight:700; text-align:center;">Certificate of Achievement</h1>
                            <p style="margin:0 auto 22px; max-width:500px; color:#e5e7eb; font-size:16px; line-height:26px; text-align:center;">
                                Congratulations! Your <strong style="color:#c9a8ff;">{{ $certificateTitle }}</strong> has been successfully approved and issued by PeersGlobal.
                            </p>
                            <p style="margin:0 0 24px; color:#f4f4f5; font-size:16px; line-height:27px;">
                                This certificate recognizes your achievement and successful completion of the certification requirements.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; border:1px solid #4b2a91; border-radius:14px; background-color:#1b1b1f; margin:0 0 24px; border-collapse:separate; border-spacing:0; overflow:hidden;">
                                <tr>
                                    <td style="padding:22px 20px; text-align:center; border-bottom:1px solid #33205f; background-color:#17171b;">
                                        <div style="font-family:Georgia, 'Times New Roman', serif; color:#ffffff; font-size:30px; line-height:38px; font-weight:bold;">{{ $recipientName }}</div>
                                        <div style="margin-top:10px; color:#d8c7ff; font-size:15px; line-height:23px;">has achieved</div>
                                        <div style="margin-top:8px; color:#ffffff; font-size:20px; line-height:28px; font-weight:bold;">{{ $achievementLabel }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; border-collapse:collapse;">
                                            <tr>
                                                <td colspan="2" style="padding:16px 20px; color:#ffffff; font-size:18px; line-height:26px; font-weight:bold; background-color:#211637; border-bottom:1px solid #4b2a91;">Certification Details</td>
                                            </tr>
                                            @foreach($certificationDetails as $label => $value)
                                                <tr>
                                                    <td style="padding:13px 20px; color:#b8b8c4; font-size:14px; line-height:20px; width:44%; border-bottom:1px solid #33205f; vertical-align:top; background-color:#18181c;">{{ $label }}</td>
                                                    <td style="padding:13px 20px; color:#ffffff; font-size:14px; line-height:20px; font-weight:bold; border-bottom:1px solid #33205f; vertical-align:top; background-color:#1d1d22;">{{ $value }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 18px; color:#f4f4f5; font-size:16px; line-height:27px;">
                                We appreciate your commitment to growth, leadership, collaboration, excellence, and the PeersGlobal community.
                            </p>
                            <p style="margin:0; color:#f4f4f5; font-size:16px; line-height:27px;">
                                Wishing you continued success,<br />
                                <strong style="color:#c9a8ff;">PeersGlobal Team</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:#240e5c; padding:16px 20px; border-bottom-left-radius:14px; border-bottom-right-radius:14px;">
                            <p style="font-size:14px; font-weight:bold; color:#ffffff; line-height:22px; margin:0;">Peers are partners in business and friends in life.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
