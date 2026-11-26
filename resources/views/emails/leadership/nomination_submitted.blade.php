<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Nomination Received: {{ $roleName }} [{{ $applicationNumber }}] | Peers Global</title>
    <style>
        * { box-sizing: border-box; }
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        @media only screen and (max-width: 600px) {
            .email-wrapper { padding: 12px 6px !important; }
            .email-container { width: 100% !important; max-width: 100% !important; border-radius: 12px !important; }
            .email-body { padding: 22px 16px !important; }
            .email-header { padding: 24px 16px 16px 16px !important; }
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
                                    Leadership Selection &bull; Application Received
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td class="email-body" style="padding:32px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            
                            <p style="margin:0 0 16px 0; font-size:17px; color:#0f172a; font-weight:700;">
                                Dear {{ $fullName }},
                            </p>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.65; color:#334155;">
                                Thank you for stepping forward to lead. Your leadership nomination has been successfully submitted and logged in our system.
                            </p>

                            <!-- Submission Details Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin:22px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:18px 22px; font-size:14px; line-height:1.8; color:#334155;">
                                        <div style="font-weight:700; color:#475569; font-size:11.5px; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:10px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                                            APPLICATION DETAILS
                                        </div>
                                        <div style="margin-bottom:6px;">
                                            <span style="color:#64748b; font-weight:600;">Application No:</span> &nbsp; <strong style="color:#0f172a; font-family:'Courier New', monospace;">{{ $applicationNumber }}</strong>
                                        </div>
                                        <div style="margin-bottom:6px;">
                                            <span style="color:#64748b; font-weight:600;">Applied Role:</span> &nbsp; <strong style="color:#4f46e5;">{{ $roleName }} &ndash; {{ $region }}</strong>
                                        </div>
                                        <div style="margin-bottom:6px;">
                                            <span style="color:#64748b; font-weight:600;">Submitted on:</span> &nbsp; <strong style="color:#0f172a;">{{ $submittedDateTime }} IST</strong>
                                        </div>
                                        <div>
                                            <span style="color:#64748b; font-weight:600;">Status:</span> &nbsp; <strong style="color:#d97706; background-color:#fffbeb; padding:2px 8px; border-radius:4px; border:1px solid #fde68a;">Under Review</strong>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- What Happens Next Section -->
                            <div style="margin:24px 0 20px 0;">
                                <div style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:12px;">
                                    WHAT HAPPENS NEXT
                                </div>
                                <ol style="margin:0 0 16px 0; padding-left:20px; font-size:14.5px; line-height:1.75; color:#334155;">
                                    <li style="margin-bottom:8px;">Our Scrutiny Committee reviews every submission against eligibility benchmarks.</li>
                                    <li style="margin-bottom:8px;">Shortlisted candidates are notified by email and receive their official public voting link.</li>
                                    <li>Candidates then progress through the Jury Evaluation stage.</li>
                                </ol>
                            </div>

                            <p style="margin:0 0 20px 0; font-size:13.5px; line-height:1.6; color:#64748b;">
                                Not every nomination will be shortlisted. We will email you at <strong style="color:#334155;">{{ $email }}</strong> once the review is complete. Please retain your Application No. for future reference.
                            </p>

                            <!-- Cultural Belief -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin:20px 0 24px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:13.5px; line-height:1.6; color:#475569; font-style:italic;">
                                        Peers Global is a Community of Collaboration. We look for leaders who build high-trust Circles, mentor others and create impact for others. Peers are Partners in Business and Friends in Life.
                                    </td>
                                </tr>
                            </table>

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
