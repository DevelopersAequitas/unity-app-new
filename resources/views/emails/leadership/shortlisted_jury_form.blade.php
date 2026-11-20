<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You are shortlisted – complete your Jury Form | {{ $roleWithRegion }}</title>
</head>
<body style="margin:0; padding:0; background-color:#0b0f19; font-family:'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#e2e8f0;">
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
                                Leadership Selection Management System • Stage 2
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td style="padding:36px 32px; color:#cbd5e1; font-size:15px; line-height:1.65;">
                            
                            <p style="margin:0 0 18px 0; font-size:16px; color:#f1f5f9; font-weight:600;">
                                Dear {{ $candidateName }},
                            </p>

                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                Congratulations! The Scrutiny Committee has shortlisted your candidacy for <strong style="color:#38bdf8;">{{ $roleWithRegion }}</strong> for the Jury Evaluation stage.
                            </p>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                Shortlisting is not the final selection. Your next step is the Jury Form, where you present your business track record and leadership vision to the Jury Panel.
                            </p>

                            <!-- Deadline Alert Box -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#1e293b; border-left:4px solid #ef4444; border-radius:6px; margin:20px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:14px 18px; font-size:14px; line-height:1.6; color:#f8fafc;">
                                        <strong>Complete by:</strong> <span style="color:#f87171; font-weight:700;">{{ $deadline }} IST</span><br>
                                        <span style="font-size:13px; color:#94a3b8;">Forms not submitted by the deadline cannot be evaluated.</span>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button / Link Box -->
                            <div style="background-color:#182234; border:1px solid #283548; border-radius:12px; padding:24px 20px; text-align:center; margin:24px 0;">
                                <div style="margin-bottom:14px;">
                                    <a href="{{ $secureLink }}" target="_blank" style="display:inline-block; background:linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:14px 28px; border-radius:10px; box-shadow:0 8px 20px rgba(37, 99, 235, 0.35); letter-spacing:0.02em;">
                                        [ Open Jury Form ]
                                    </a>
                                </div>
                                <div style="font-size:13px; color:#94a3b8; word-break:break-all;">
                                    (Secure link: <a href="{{ $secureLink }}" target="_blank" style="color:#60a5fa; text-decoration:underline;">{{ $secureLink }}</a>)
                                </div>
                            </div>

                            <!-- Unlock Verification Note -->
                            <p style="margin:0 0 24px 0; font-size:14px; line-height:1.6; color:#94a3b8;">
                                To unlock the form, you will verify with a code sent to this email address (<strong style="color:#e2e8f0;">{{ $email }}</strong>). This link is personal to you. Please do not share or forward it.
                            </p>

                            <!-- Application Details Table -->
                            <div style="background-color:#0f172a; border:1px solid #1e293b; border-radius:10px; padding:20px; margin:24px 0;">
                                <div style="font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:14px; border-bottom:1px solid #1e293b; padding-bottom:8px;">
                                    APPLICATION DETAILS
                                </div>
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px; color:#cbd5e1;">
                                    <tr>
                                        <td style="padding:6px 0; width:140px; color:#64748b; font-weight:600;">Application No:</td>
                                        <td style="padding:6px 0; color:#f1f5f9; font-weight:700; font-family:monospace;">{{ $applicationNumber }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; color:#64748b; font-weight:600;">Applied Role:</td>
                                        <td style="padding:6px 0; color:#38bdf8; font-weight:600;">{{ $roleWithRegion }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; color:#64748b; font-weight:600;">Stage:</td>
                                        <td style="padding:6px 0; color:#f59e0b; font-weight:600;">Jury Evaluation (Stage 2)</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Community Collaboration Note -->
                            <p style="margin:0 0 24px 0; font-size:14px; line-height:1.6; color:#94a3b8;">
                                Peers Global is a Community of Collaboration. We look for leaders who build high-trust Circles, mentor others and create impact for others. Peers are Partners in Business and Friends in Life.
                            </p>

                            <p style="margin:28px 0 0 0; font-size:14.5px; color:#94a3b8; font-weight:600;">
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
