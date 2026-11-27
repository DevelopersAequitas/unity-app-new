<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>You are shortlisted – complete your Jury Form | {{ $roleWithRegion }}</title>
    <style>
        * { box-sizing: border-box; }
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        @media only screen and (max-width: 600px) {
            .email-wrapper { padding: 12px 6px !important; }
            .email-container { width: 100% !important; max-width: 100% !important; border-radius: 12px !important; }
            .email-body { padding: 22px 18px !important; }
            .email-header { padding: 24px 18px 16px 18px !important; }
            .mobile-btn { width: 100% !important; display: block !important; padding: 13px 16px !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#334155;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-wrapper" style="width:100%; margin:0; padding:32px 12px; background-color:#f8fafc; border-collapse:collapse;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width:600px; width:100%; margin:0 auto; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 4px 20px rgba(0,0,0,0.05); border-collapse:collapse;">
                    
                    <!-- Top Brand Gradient Accent Bar -->
                    <tr>
                        <td style="height:5px; background:linear-gradient(90deg, #1d4ed8 0%, #2563eb 50%, #e11d48 100%); line-height:5px; font-size:1px;">&nbsp;</td>
                    </tr>

                    <!-- Clean Branded Header with Logo -->
                    <tr>
                        <td align="center" class="email-header" style="background-color:#ffffff; padding:32px 28px 20px 28px; text-align:center; border-bottom:1px solid #f1f5f9;">
                            <img src="{{ $logoUrl }}" alt="Peers Global" width="180" style="border:0; outline:none; text-decoration:none; vertical-align:middle; display:inline-block; max-width:180px; height:auto;" />
                            <div style="margin-top:14px;">
                                <span style="display:inline-block; background-color:#f8fafc; color:#475569; font-size:11px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; padding:6px 16px; border-radius:20px; border:1px solid #e2e8f0;">
                                    Leadership Selection &bull; Jury Evaluation Stage
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td class="email-body" style="padding:32px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            
                            <p style="margin:0 0 16px 0; font-size:16.5px; color:#0f172a; font-weight:700;">
                                Dear {{ $candidateName }},
                            </p>

                            <p style="margin:0 0 16px 0; font-size:15px; line-height:1.65; color:#334155;">
                                Congratulations! The Scrutiny Committee has shortlisted your candidacy for <strong style="color:#0f172a;">{{ $roleWithRegion }}</strong> for the Jury Evaluation stage.
                            </p>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.65; color:#334155;">
                                Shortlisting is not the final selection. Your next step is the Jury Form, where you present your business track record and leadership vision to the Jury Panel.
                            </p>

                            <!-- Deadline Alert Box -->
                            <div style="background-color:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:10px; padding:16px 18px; margin:22px 0;">
                                <div style="font-size:14.5px; line-height:1.6; color:#991b1b;">
                                    <strong>Complete by:</strong> <span style="color:#dc2626; font-weight:700;">{{ $deadline }} IST</span><br>
                                    <span style="font-size:13px; color:#b91c1c;">Forms not submitted by the deadline cannot be evaluated.</span>
                                </div>
                            </div>

                            <!-- CTA Button / Link Box -->
                            <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:24px 20px; text-align:center; margin:24px 0;">
                                <div style="margin-bottom:12px;">
                                    <a href="{{ $secureLink }}" target="_blank" class="mobile-btn" style="display:inline-block; box-sizing:border-box; max-width:100%; background:linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:14px 34px; border-radius:10px; box-shadow:0 4px 14px rgba(29, 78, 216, 0.25); letter-spacing:0.02em;">
                                        Open Jury Form
                                    </a>
                                </div>
                                <div style="font-size:12.5px; color:#64748b; word-break:break-all;">
                                    Direct link: <a href="{{ $secureLink }}" target="_blank" style="color:#2563eb; text-decoration:underline;">{{ $secureLink }}</a>
                                </div>
                            </div>

                            <!-- Unlock Verification Note -->
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:1.6; color:#475569;">
                                To unlock the form, you will verify with a code sent to this email address. This link is personal to you. Please do not share or forward it.
                            </p>

                            <!-- Optional Voting Link Note -->
                            <p style="margin:0 0 22px 0; font-size:13.5px; line-height:1.6; color:#64748b; font-style:italic;">
                                Your public voting link will follow in a separate email.
                            </p>

                            <!-- Application Number Details Box -->
                            <div style="background-color:#f1f5f9; border-radius:10px; padding:12px 18px; margin:20px 0; font-size:14px; color:#334155;">
                                <strong>Application No:</strong> <span style="font-family:monospace; font-weight:700; color:#0f172a; margin-left:6px;">{{ $applicationNumber }}</span>
                            </div>

                            <p style="margin:28px 0 0 0; font-size:14.5px; color:#475569; font-weight:600;">
                                &mdash; Peers Global Election Board
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color:#f8fafc; padding:22px 20px; border-top:1px solid #e2e8f0; color:#64748b; font-size:12px; line-height:1.6; text-align:center;">
                            <div style="color:#0f172a; font-weight:700; font-size:13px; margin-bottom:4px;">
                                Peers Global
                            </div>
                            <div style="margin-bottom:6px; color:#64748b;">
                                Peers are partners in business and friends in life.
                            </div>
                            <div style="font-size:11px; color:#94a3b8;">
                                Automated notification from Peers Global Leadership Selection System &bull; <a href="https://peersglobal.com" style="color:#2563eb; text-decoration:none; font-weight:600;">peersglobal.com</a>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
