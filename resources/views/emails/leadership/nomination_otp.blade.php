<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $otp }} is your Peers Global verification code</title>
    <style>
        * { box-sizing: border-box; }
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        @media only screen and (max-width: 600px) {
            .email-wrapper { padding: 12px 6px !important; }
            .email-container { width: 100% !important; max-width: 100% !important; border-radius: 12px !important; }
            .email-body { padding: 22px 16px !important; }
            .email-header { padding: 24px 16px 16px 16px !important; }
            .otp-box { padding: 12px 14px !important; width: 100% !important; max-width: 270px !important; }
            .otp-code { font-size: 28px !important; letter-spacing: 5px !important; margin-left: 5px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#334155;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-wrapper" style="width:100%; margin:0; padding:32px 12px; background-color:#f1f5f9; border-collapse:collapse;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width:560px; width:100%; margin:0 auto; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 4px 25px rgba(0,0,0,0.06); border-collapse:collapse;">
                    
                    <!-- Top Brand Gradient Accent Bar -->
                    <tr>
                        <td style="height:5px; background:linear-gradient(90deg, #4f46e5 0%, #9333ea 50%, #e11d48 100%); line-height:5px; font-size:1px;">&nbsp;</td>
                    </tr>

                    <!-- Clean Branded Header with Logo -->
                    <tr>
                        <td align="center" class="email-header" style="background-color:#ffffff; padding:30px 24px 18px 24px; text-align:center; border-bottom:1px solid #f1f5f9;">
                            <img src="{{ $logoUrl }}" alt="Peers Global" width="170" style="border:0; outline:none; text-decoration:none; vertical-align:middle; display:inline-block; max-width:170px; height:auto;" />
                            <div style="margin-top:12px;">
                                <span style="display:inline-block; background-color:#f8fafc; color:#475569; font-size:11px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; padding:5px 14px; border-radius:20px; border:1px solid #e2e8f0;">
                                    Leadership Selection &bull; Identity Verification
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td class="email-body" style="padding:32px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            
                            <p style="margin:0 0 16px 0; font-size:17px; font-weight:700; color:#0f172a;">
                                Hello {{ $name }},
                            </p>

                            <p style="margin:0 0 20px 0; color:#334155; font-size:15px; line-height:1.6;">
                                Use the verification code below to confirm your email and continue your leadership nomination:
                            </p>

                            <!-- OTP Box -->
                            <div style="margin:26px 0; text-align:center;">
                                <div class="otp-box" style="display:inline-block; box-sizing:border-box; max-width:100%; background-color:#eef2ff; border:2px dashed #6366f1; border-radius:12px; padding:18px 32px; text-align:center;">
                                    <span class="otp-code" style="font-family:'Courier New', Courier, monospace; font-size:34px; font-weight:800; letter-spacing:6px; color:#4338ca; display:inline-block; margin-left:6px;">
                                        {{ $otp }}
                                    </span>
                                </div>
                                <div style="margin-top:12px; font-size:13.5px; color:#6366f1; font-weight:600;">
                                    This code is valid for 10 minutes.
                                </div>
                            </div>

                            <p style="margin:0 0 16px 0; color:#64748b; font-size:13.5px; line-height:1.6;">
                                Do not share this code with anyone. Peers Global will never ask you for it.<br>
                                If you did not request this code, you can safely ignore this email.
                            </p>

                            <!-- About Peers Global Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin:24px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:13.5px; line-height:1.6; color:#475569; font-style:italic;">
                                        Peers Global is a Community of Collaboration where Peers are Partners in Business and Friends in Life. We select leaders who believe in mutual growth and giving first.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0 0; font-size:14.5px; color:#475569; font-weight:600;">
                                &mdash; Peers Global Leadership Team
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8fafc; padding:22px 24px; text-align:center; border-top:1px solid #e2e8f0;">
                            <p style="margin:0 0 4px 0; color:#0f172a; font-weight:700; font-size:13px;">
                                Peers Global Unity
                            </p>
                            <p style="margin:0 0 6px 0; color:#64748b; font-size:12px;">
                                &copy; {{ date('Y') }} Peers Global. All rights reserved.
                            </p>
                            <p style="margin:0; font-size:11px; color:#94a3b8;">
                                Automated notification from Peers Global Leadership Selection System &bull; <a href="{{ $websiteUrl }}" style="color:#4f46e5; text-decoration:none; font-weight:600;">{{ parse_url($websiteUrl, PHP_URL_HOST) ?? 'peersglobal.com' }}</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
