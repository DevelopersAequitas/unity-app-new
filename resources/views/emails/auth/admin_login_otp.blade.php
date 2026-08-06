<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login Verification OTP</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f7fb; font-family:'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f7fb; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e6e9f0; box-shadow:0 10px 30px rgba(17,24,39,0.06);">
                    <tr>
                        <td style="background:linear-gradient(90deg,#1d4ed8,#0ea5e9); padding:22px 28px; color:#ffffff; font-size:20px; font-weight:700; letter-spacing:0.2px;">
                            Peers Global Unity
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 12px; color:#0f172a; font-size:18px; font-weight:700;">Hi {{ $name }},</p>
                            <p style="margin:0 0 16px; color:#334155; font-size:15px; line-height:22px;">
                                Use the one-time password (OTP) below to complete your secure login. Enter this code on the verification screen to continue.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:22px 0;">
                                <tr>
                                    <td align="center" style="background-color:#0f172a; color:#ffffff; font-size:30px; font-weight:800; letter-spacing:8px; padding:18px 24px; border-radius:10px;">
                                        {{ $otp }}
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px; color:#334155; font-size:15px; line-height:22px;">
                                For your security, do not share this OTP with anyone. Peers Global Unity will never ask for your OTP.
                            </p>
                            <p style="margin:0 0 8px; color:#0f172a; font-size:15px; font-weight:600;">OTP valid for 5 minutes.</p>
                            <p style="margin:0 0 16px; color:#475569; font-size:14px; line-height:21px;">
                                If you did not attempt to sign in, please secure your account or contact support immediately.
                            </p>
                            <p style="margin:0; color:#94a3b8; font-size:13px; line-height:20px;">
                                Thank you,<br>
                                The Peers Global Unity Team
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8fafc; padding:18px 28px; color:#94a3b8; font-size:12px; line-height:18px; border-top:1px solid #e2e8f0;">
                            This email was sent to verify a secure login attempt. If this was not you, no further action is required unless you continue to receive these emails.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
