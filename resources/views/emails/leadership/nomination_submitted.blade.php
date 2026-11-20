<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nomination Received: {{ $roleName }} [{{ $applicationNumber }}] | Peers Global</title>
</head>
<body style="margin:0; padding:0; background-color:#0b0f19; font-family:'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing:antialiased; -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; color:#e2e8f0;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#0b0f19; padding:32px 12px; border-collapse:collapse;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px; width:100%; background-color:#121824; border-radius:16px; overflow:hidden; border:1px solid #1e293b; box-shadow:0 20px 40px rgba(0,0,0,0.5); border-collapse:collapse;">
                    
                    <!-- Branded Header with Logo -->
                    <tr>
                        <td align="center" style="background:linear-gradient(135deg, #1e085a 0%, #2e1065 50%, #4338ca 100%); padding:32px 24px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1);">
                            <img src="{{ $logoUrl }}" alt="Peers Global" width="180" style="border:0; outline:none; text-decoration:none; vertical-align:middle; display:inline-block; max-width:180px; height:auto;" />
                            <div style="color:#c7d2fe; font-size:12px; letter-spacing:0.15em; text-transform:uppercase; margin-top:12px; font-weight:600;">
                                Leadership Selection Management System
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td style="padding:36px 32px; color:#cbd5e1; font-size:15px; line-height:1.65;">
                            
                            <p style="margin:0 0 16px 0; font-size:16px; color:#f1f5f9; font-weight:600;">
                                Dear {{ $fullName }},
                            </p>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                Thank you for stepping forward to lead. Your nomination has been submitted.
                            </p>

                            <!-- Submission Details Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#182234; border:1px solid #283548; border-radius:12px; margin:20px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:18px 22px; font-size:14px; line-height:1.8; color:#cbd5e1;">
                                        <div style="font-weight:700; color:#38bdf8; font-size:13px; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:10px;">
                                            APPLICATION DETAILS
                                        </div>
                                        <div style="margin-bottom:6px;">
                                            <span style="color:#94a3b8;">Application No:</span> &nbsp; <strong style="color:#ffffff; font-family:'Courier New', monospace;">{{ $applicationNumber }}</strong>
                                        </div>
                                        <div style="margin-bottom:6px;">
                                            <span style="color:#94a3b8;">Applied Role:</span> &nbsp; <strong style="color:#ffffff;">{{ $roleName }} &ndash; {{ $region }}</strong>
                                        </div>
                                        <div style="margin-bottom:6px;">
                                            <span style="color:#94a3b8;">Submitted on:</span> &nbsp; <strong style="color:#ffffff;">{{ $submittedDateTime }} IST</strong>
                                        </div>
                                        <div>
                                            <span style="color:#94a3b8;">Status:</span> &nbsp; <strong style="color:#f59e0b;">Under Review</strong>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- What Happens Next Section -->
                            <div style="margin:24px 0 20px 0;">
                                <div style="font-size:14px; font-weight:700; color:#38bdf8; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:10px;">
                                    WHAT HAPPENS NEXT
                                </div>
                                <ol style="margin:0 0 16px 0; padding-left:20px; font-size:14.5px; line-height:1.7; color:#cbd5e1;">
                                    <li style="margin-bottom:6px;">Our Scrutiny Committee reviews every submission.</li>
                                    <li style="margin-bottom:6px;">Shortlisted candidates are informed by email and receive their public voting link.</li>
                                    <li>Candidates then go through the Jury Evaluation round.</li>
                                </ol>
                            </div>

                            <p style="margin:0 0 18px 0; font-size:14px; line-height:1.6; color:#94a3b8;">
                                Not every nomination will be shortlisted. We will email you at <strong style="color:#cbd5e1;">{{ $email }}</strong> once the review is complete. Please keep your Application No. for reference.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#182234; border:1px solid #283548; border-radius:10px; margin:20px 0 24px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:13.5px; line-height:1.6; color:#cbd5e1;">
                                        Peers Global is a Community of Collaboration. We look for leaders who build high-trust Circles, mentor others and create impact for others. Peers are Partners in Business and Friends in Life.
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <div style="text-align:center; margin:28px 0 24px 0;">
                                <a href="{{ $statusLink }}" target="_blank" style="display:inline-block; background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color:#ffffff; font-size:14.5px; font-weight:700; text-decoration:none; padding:13px 32px; border-radius:10px; box-shadow:0 8px 20px rgba(79, 70, 229, 0.4); letter-spacing:0.02em;">
                                    Track Your Application &nbsp;&rarr;&nbsp; {{ $statusLink }}
                                </a>
                            </div>

                            <p style="margin:28px 0 0 0; font-size:14.5px; color:#94a3b8; font-weight:500;">
                                &mdash; Peers Global Election Board
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color:#0f172a; padding:24px; border-top:1px solid #1e293b; color:#64748b; font-size:12px; line-height:1.6; text-align:center;">
                            <div style="color:#94a3b8; font-weight:600; font-size:13px; margin-bottom:6px;">
                                Peers Global Unity
                            </div>
                            <div style="margin-bottom:8px; color:#64748b;">
                                Peers are partners in business and friends in life.
                            </div>
                            <div style="font-size:11px; color:#475569;">
                                Automated notification from Peers Global Leadership Selection System &bull; <a href="https://peersglobal.com" style="color:#818cf8; text-decoration:none;">peersglobal.com</a>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
