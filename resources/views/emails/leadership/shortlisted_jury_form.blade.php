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
            .email-body { padding: 22px 16px !important; }
            .email-header { padding: 24px 16px 16px 16px !important; }
            .mobile-btn { width: 100% !important; display: block !important; padding: 13px 16px !important; text-align: center !important; }
            .app-table td { display: block !important; width: 100% !important; padding: 3px 0 !important; }
            .app-table td.label-td { padding-top: 8px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#334155;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-wrapper" style="width:100%; margin:0; padding:32px 12px; background-color:#f1f5f9; border-collapse:collapse;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width:600px; width:100%; margin:0 auto; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 4px 25px rgba(0,0,0,0.06); border-collapse:collapse;">
                    
                    <!-- Top Brand Gradient Accent Bar -->
                    <tr>
                        <td style="height:5px; background:linear-gradient(90deg, #4f46e5 0%, #9333ea 50%, #e11d48 100%); line-height:5px; font-size:1px;">&nbsp;</td>
                    </tr>

                    <!-- Clean Branded Header with Logo -->
                    <tr>
                        <td align="center" class="email-header" style="background-color:#ffffff; padding:32px 28px 20px 28px; text-align:center; border-bottom:1px solid #f1f5f9;">
                            <img src="{{ $logoUrl }}" alt="Peers Global" width="180" style="border:0; outline:none; text-decoration:none; vertical-align:middle; display:inline-block; max-width:180px; height:auto;" />
                            <div style="margin-top:14px;">
                                <span style="display:inline-block; background-color:#f8fafc; color:#475569; font-size:11px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; padding:6px 16px; border-radius:20px; border:1px solid #e2e8f0;">
                                    Leadership Selection &bull; Stage 2 Shortlist
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td class="email-body" style="padding:32px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            
                            <p style="margin:0 0 16px 0; font-size:17px; color:#0f172a; font-weight:700;">
                                Dear {{ $candidateName }},
                            </p>

                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.65; color:#334155;">
                                Congratulations! The Scrutiny Committee has shortlisted your candidacy for <strong style="color:#4f46e5;">{{ $roleWithRegion }}</strong> for the Stage 2 Jury Evaluation round.
                            </p>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.65; color:#334155;">
                                Shortlisting is an important milestone. Your next step is to complete your <strong>Jury Form (Form 2)</strong>, where you present your business track record and leadership vision to the evaluation panel.
                            </p>

                            <!-- Deadline Alert Box -->
                            <div style="background-color:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:10px; padding:16px 20px; margin:22px 0;">
                                <div style="font-size:14.5px; line-height:1.6; color:#991b1b;">
                                    <strong>Submission Deadline:</strong> <span style="color:#dc2626; font-weight:700;">{{ $deadline }} IST</span><br>
                                    <span style="font-size:13px; color:#b91c1c;">Forms submitted after this deadline cannot be processed by the jury.</span>
                                </div>
                            </div>

                            <!-- CTA Button / Link Box -->
                            <div style="background-color:#faf5ff; border:1px solid #e9d5ff; border-radius:14px; padding:24px 20px; text-align:center; margin:24px 0;">
                                <div style="margin-bottom:12px;">
                                    <a href="{{ $secureLink }}" target="_blank" class="mobile-btn" style="display:inline-block; box-sizing:border-box; max-width:100%; background:linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:14px 32px; border-radius:10px; box-shadow:0 4px 14px rgba(79, 70, 229, 0.3); letter-spacing:0.02em;">
                                        Open Your Jury Form
                                    </a>
                                </div>
                                <div style="font-size:13px; color:#64748b; word-break:break-all;">
                                    Direct link: <a href="{{ $secureLink }}" target="_blank" style="color:#6366f1; text-decoration:underline;">{{ $secureLink }}</a>
                                </div>
                            </div>

                            <!-- Unlock Verification Note -->
                            <p style="margin:0 0 24px 0; font-size:13.5px; line-height:1.6; color:#64748b;">
                                To access the form, you will verify your identity via a one-time code sent to this email address (<strong style="color:#334155;">{{ $email }}</strong>). This link is personalized and secure; please do not forward it.
                            </p>

                            <!-- Application Details Table -->
                            <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px 20px; margin:24px 0;">
                                <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                                    APPLICATION DETAILS
                                </div>
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="app-table" style="font-size:14px; color:#334155;">
                                    <tr>
                                        <td class="label-td" style="padding:6px 0; width:140px; color:#64748b; font-weight:600;">Application No:</td>
                                        <td style="padding:6px 0; color:#0f172a; font-weight:700; font-family:monospace;">{{ $applicationNumber }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" style="padding:6px 0; color:#64748b; font-weight:600;">Applied Role:</td>
                                        <td style="padding:6px 0; color:#4f46e5; font-weight:600;">{{ $roleWithRegion }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" style="padding:6px 0; color:#64748b; font-weight:600;">Stage:</td>
                                        <td style="padding:6px 0; color:#d97706; font-weight:700;">Jury Evaluation (Stage 2)</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Culture Note -->
                            <p style="margin:0 0 24px 0; font-size:13.5px; line-height:1.6; color:#64748b; font-style:italic;">
                                Peers Global is a Community of Collaboration. We look for leaders who build high-trust Circles, mentor others and create impact for others. Peers are Partners in Business and Friends in Life.
                            </p>

                            <p style="margin:28px 0 0 0; font-size:14.5px; color:#475569; font-weight:600;">
                                &mdash; Peers Global Election Board
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color:#f8fafc; padding:24px 20px; border-top:1px solid #e2e8f0; color:#64748b; font-size:12px; line-height:1.6; text-align:center;">
                            <div style="color:#0f172a; font-weight:700; font-size:13px; margin-bottom:4px;">
                                Peers Global Unity
                            </div>
                            <div style="margin-bottom:8px; color:#64748b;">
                                Peers are partners in business and friends in life.
                            </div>
                            <div style="font-size:11px; color:#94a3b8;">
                                Automated notification from Peers Global Leadership Selection System &bull; <a href="https://peersglobal.com" style="color:#4f46e5; text-decoration:none; font-weight:600;">peersglobal.com</a>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
