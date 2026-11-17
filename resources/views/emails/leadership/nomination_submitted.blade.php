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
                                Dear {{ $candidateName }},
                            </p>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                Thank you for stepping forward to lead. Your nomination has been successfully submitted.
                            </p>

                            <!-- Submission Details Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#182234; border:1px solid #283548; border-radius:12px; margin:20px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:18px 22px; font-size:14.5px; line-height:1.8; color:#cbd5e1;">
                                        <div style="margin-bottom:6px;">
                                            <span style="color:#94a3b8;">&bull; Application No:</span> <strong style="color:#ffffff; font-family:'Courier New', monospace;">{{ $applicationNumber }}</strong>
                                        </div>
                                        <div style="margin-bottom:6px;">
                                            <span style="color:#94a3b8;">&bull; Applied Role:</span> <strong style="color:#38bdf8;">{{ $roleName }}</strong>
                                        </div>
                                        <div>
                                            <span style="color:#94a3b8;">&bull; Status:</span> <strong style="color:#f59e0b;">Under Review</strong>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Next Step Section -->
                            <div style="margin:24px 0 20px 0;">
                                <div style="font-size:15px; font-weight:700; color:#ffffff; margin-bottom:8px;">
                                    Next Step:
                                </div>
                                <p style="margin:0 0 18px 0; font-size:14.5px; line-height:1.6; color:#cbd5e1;">
                                    Our Scrutiny Committee is reviewing your submission. Shortlisted candidates will be invited for the Jury Evaluation round.
                                </p>
                            </div>

                            <p style="margin:0 0 24px 0; font-size:14px; line-height:1.6; color:#94a3b8;">
                                We are a global network of entrepreneurs built on genuine collaboration, not competition. We look for leaders who build high-trust circles, mentor others, and drive real business impact.
                            </p>

                            <!-- CTA Button -->
                            <div style="text-align:center; margin:30px 0 24px 0;">
                                <a href="https://peersglobal.com/leadership" target="_blank" style="display:inline-block; background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color:#ffffff; font-size:14.5px; font-weight:700; text-decoration:none; padding:13px 32px; border-radius:10px; box-shadow:0 8px 20px rgba(79, 70, 229, 0.4); letter-spacing:0.02em;">
                                    [ View Leadership Portal ] &rarr; https://peersglobal.com/leadership
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
