<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leadership Nomination Verification Code</title>
</head>
<body style="margin:0; padding:0; background-color:#0b0f19; font-family:'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing:antialiased; -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; color:#e2e8f0;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#0b0f19; padding:32px 12px; border-collapse:collapse;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" cellpadding="0" cellspacing="0" width="560" style="max-width:560px; width:100%; background-color:#121824; border-radius:16px; overflow:hidden; border:1px solid #1e293b; box-shadow:0 20px 40px rgba(0,0,0,0.5); border-collapse:collapse;">
                    
                    <!-- Branded Header with Logo -->
                    <tr>
                        <td align="center" style="background:linear-gradient(135deg, #1e085a 0%, #2e1065 50%, #4338ca 100%); padding:28px 24px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1);">
                            <img src="{{ $logoUrl }}" alt="Peers Global Unity" width="170" style="border:0; outline:none; text-decoration:none; vertical-align:middle; display:inline-block; max-width:170px; height:auto;" />
                            <div style="color:#c7d2fe; font-size:11px; letter-spacing:0.16em; text-transform:uppercase; margin-top:10px; font-weight:600;">
                                Leadership Selection 2026 • Identity Verification
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td style="padding:36px 32px; color:#cbd5e1; font-size:15px; line-height:1.65;">
                            
                            <!-- Key Icon & Title -->
                            <div style="text-align:center; margin-bottom:24px;">
                                <div style="display:inline-block; width:52px; height:52px; line-height:52px; border-radius:50%; background-color:rgba(99, 102, 241, 0.15); border:2px solid #6366f1; color:#818cf8; font-size:24px; text-align:center; margin-bottom:12px;">
                                    🔒
                                </div>
                                <h2 style="margin:0 0 8px 0; color:#ffffff; font-size:22px; font-weight:700; letter-spacing:-0.02em;">
                                    Verification Code
                                </h2>
                                <p style="margin:0; color:#94a3b8; font-size:14px;">
                                    Use the one-time code below to verify your email address and continue your leadership nomination application.
                                </p>
                            </div>

                            <!-- OTP Box -->
                            <div style="margin:28px 0; text-align:center;">
                                <div style="display:inline-block; background-color:#182234; border:2px dashed #4338ca; border-radius:12px; padding:18px 36px;">
                                    <span style="font-family:'Courier New', Courier, monospace; font-size:36px; font-weight:800; letter-spacing:8px; color:#ffffff; text-shadow:0 2px 4px rgba(0,0,0,0.4);">
                                        {{ $otp }}
                                    </span>
                                </div>
                                <div style="margin-top:10px; font-size:13px; color:#a5b4fc; font-weight:500;">
                                    ⏱️ Valid for {{ $expiresInMinutes }} minutes
                                </div>
                            </div>

                            <!-- Instructions -->
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:1.6; color:#94a3b8; text-align:center;">
                                Enter this verification code on the nomination portal to securely unlock and submit your candidate profile.
                            </p>

                            <!-- Security Notice Box -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#182234; border:1px solid #283548; border-radius:10px; margin:24px 0 0 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:14px 18px; font-size:12px; line-height:1.5; color:#94a3b8;">
                                        <strong style="color:#f1f5f9;">Security Notice:</strong>
                                        Peers Global Unity will never ask for your verification code or passwords via chat or phone. If you did not request this verification, please disregard this email.
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#0d121c; padding:20px 24px; text-align:center; border-top:1px solid #1e293b;">
                            <p style="margin:0 0 6px 0; color:#64748b; font-size:12px;">
                                © {{ date('Y') }} Peers Global Unity. All rights reserved.
                            </p>
                            <p style="margin:0; font-size:11px; color:#475569;">
                                Automated notification from Peers Global Leadership Selection System • <a href="{{ $websiteUrl }}" style="color:#818cf8; text-decoration:none;">{{ parse_url($websiteUrl, PHP_URL_HOST) ?? 'peersglobal.com' }}</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
