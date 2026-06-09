<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <meta name="supported-color-schemes" content="dark light">
    <title>{{ $subject ?? 'Impact Approved Successfully' }}</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-shell {
                padding: 14px 8px !important;
            }
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .email-header {
                padding: 28px 18px !important;
            }
            .email-body {
                padding: 30px 24px 34px 24px !important;
            }
            .main-text {
                font-size: 23px !important;
                line-height: 1.35 !important;
            }
            .footer-text {
                font-size: 21px !important;
                line-height: 1.3 !important;
            }
            .brand-logo {
                max-width: 260px !important;
            }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#202020; font-family:Arial, Helvetica, sans-serif; color:#f2f2f2;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="email-shell" style="width:100%; background:#202020; margin:0; padding:24px 12px; border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:0;">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" class="email-container" style="width:620px; max-width:620px; background:#111111; border-radius:18px; overflow:hidden; border-collapse:separate;">
                    <tr>
                        <td align="center" class="email-header" style="background:#2d0d66; padding:34px 24px;">
                            @if (! empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="PeersGlobal" width="300" class="brand-logo" style="display:block; max-width:300px; width:100%; height:auto; border:0; outline:none; text-decoration:none;">
                            @else
                                <div style="color:#ffffff; font-size:34px; font-weight:700; line-height:1.1; font-family:Arial, Helvetica, sans-serif;">
                                    PeersGlobal
                                </div>
                                <div style="color:#ffffff; font-size:15px; font-weight:600; line-height:1.3; margin-top:4px; font-family:Arial, Helvetica, sans-serif;">
                                    Community of Collaboration
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td class="email-body" style="background:#111111; padding:42px 34px 46px 34px;">
                            <div class="main-text" style="color:#e8e8e8; font-size:30px; line-height:1.35; font-weight:400; font-family:Arial, Helvetica, sans-serif;">
                                <p style="margin:0 0 34px 0; color:#f2f2f2;">
                                    Dear <strong style="font-weight:700; color:#ffffff;">{{ $userName ?? '-' }}</strong>,
                                </p>

                                <p style="margin:0 0 34px 0; color:#f2f2f2;">
                                    Great news! Your Impact has been approved successfully.
                                </p>

                                <p style="margin:0 0 34px 0; color:#d9d9d9;">
                                    <strong style="font-weight:700; color:#ffffff;">Action:</strong> {{ $actionTitle ?? '-' }}<br>
                                    <strong style="font-weight:700; color:#ffffff;">Impact Date:</strong> {{ $impactDate ?? '-' }}<br>
                                    <strong style="font-weight:700; color:#ffffff;">Story:</strong> {{ $story ?? '-' }}<br>
                                    <strong style="font-weight:700; color:#ffffff;">Status:</strong> Approved<br>
                                    <strong style="font-weight:700; color:#ffffff;">Life Impacted:</strong> {{ $lifeImpacted ?? '-' }}<br>
                                    <strong style="font-weight:700; color:#ffffff;">Total Life Impacted:</strong> {{ $totalLifeImpacted ?? '-' }}
                                </p>

                                <p style="margin:0 0 34px 0; color:#f2f2f2;">
                                    Thank you for creating meaningful impact.
                                </p>

                                <p style="margin:0; color:#f2f2f2;">
                                    With appreciation,<br>
                                    <strong style="font-weight:700; color:#ffffff;">Peers Global Team</strong>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="background:#2d0d66; padding:34px 30px;">
                            <div class="footer-text" style="color:#ffffff; font-size:28px; line-height:1.25; font-weight:700; font-family:Arial, Helvetica, sans-serif;">
                                Peers are partners in business and friends in life.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
