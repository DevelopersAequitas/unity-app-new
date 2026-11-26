<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $otp }} is your Peers Global verification code</title>
    <style>
        * { box-sizing: border-box; }
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        @media only screen and (max-width: 600px) {
            .email-wrapper { padding: 12px 6px !important; }
            .email-container { width: 100% !important; max-width: 100% !important; border-radius: 12px !important; }
            .email-body { padding: 22px 16px !important; }
            .email-header { padding: 20px 16px !important; }
            .otp-box { padding: 12px 14px !important; width: 100% !important; max-width: 270px !important; }
            .otp-code { font-size: 26px !important; letter-spacing: 5px !important; margin-left: 5px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#0b0f19; font-family:'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing:antialiased; -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; color:#e2e8f0;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-wrapper" style="width:100%; margin:0; padding:24px 12px; background-color:#0b0f19; border-collapse:collapse;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width:560px; width:100%; margin:0 auto; background-color:#121824; border-radius:16px; overflow:hidden; border:1px solid #1e293b; box-shadow:0 20px 40px rgba(0,0,0,0.5); border-collapse:collapse;">
                    
                    <!-- Branded Header with Logo -->
                    <tr>
                        <td align="center" class="email-header" style="background:linear-gradient(135deg, #1e085a 0%, #2e1065 50%, #4338ca 100%); padding:28px 24px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1);">
                            <img src="{{ $logoUrl }}" alt="Peers Global" width="170" style="border:0; outline:none; text-decoration:none; vertical-align:middle; display:inline-block; max-width:170px; height:auto;" />
                            <div style="color:#c7d2fe; font-size:11px; letter-spacing:0.16em; text-transform:uppercase; margin-top:10px; font-weight:600;">
                                Leadership Selection 2026 • Identity Verification
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td class="email-body" style="padding:32px 28px; color:#cbd5e1; font-size:15px; line-height:1.65;">
                            
                            <p style="margin:0 0 16px 0; font-size:16px; font-weight:600; color:#f1f5f9;">
                                Hello {{ $name }},
                            </p>

                            <p style="margin:0 0 20px 0; color:#cbd5e1; font-size:15px; line-height:1.6;">
                                Use this code to verify your email and continue your leadership nomination:
                            </p>

                            <!-- OTP Box -->
                            <div style="margin:24px 0; text-align:center;">
                                <div class="otp-box" style="display:inline-block; box-sizing:border-box; max-width:100%; background-color:#182234; border:2px dashed #4338ca; border-radius:12px; padding:16px 28px; text-align:center;">
                                    <span class="otp-code" style="font-family:'Courier New', Courier, monospace; font-size:32px; font-weight:800; letter-spacing:6px; color:#ffffff; display:inline-block; margin-left:6px; text-shadow:0 2px 4px rgba(0,0,0,0.4);">
                                        {{ $otp }}
                                    </span>
                                </div>
                                <div style="margin-top:12px; font-size:13.5px; color:#a5b4fc; font-weight:500;">
                                    This code is valid for 10 minutes. Enter it on the nomination form.
                                </div>
                            </div>

                            <p style="margin:0 0 14px 0; color:#94a3b8; font-size:13.5px; line-height:1.6;">
                                Do not share this code with anyone. Peers Global will never ask you for it.<br>
                                If you did not request this, you can safely ignore this email.
                            </p>

                            <!-- About Peers Global Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#182234; border:1px solid #283548; border-radius:10px; margin:24px 0 24px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:13.5px; line-height:1.6; color:#cbd5e1;">
                                        Peers Global is a Community of Collaboration where Peers are Partners in Business and Friends in Life. We select leaders who believe in mutual growth and giving first.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0 0; font-size:14.5px; color:#94a3b8; font-weight:500;">
                                &mdash; Peers Global Leadership Team
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#0d121c; padding:20px 24px; text-align:center; border-top:1px solid #1e293b;">
                            <p style="margin:0 0 6px 0; color:#64748b; font-size:12px;">
                                &copy; {{ date('Y') }} Peers Global. All rights reserved.
                            </p>
                            <p style="margin:0; font-size:11px; color:#475569;">
                                Automated notification from Peers Global Leadership Selection System &bull; <a href="{{ $websiteUrl }}" style="color:#818cf8; text-decoration:none;">{{ parse_url($websiteUrl, PHP_URL_HOST) ?? 'peersglobal.com' }}</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
