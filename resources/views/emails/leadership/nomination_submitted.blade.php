<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nomination Application Received - {{ $applicationNumber }}</title>
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
                            <img src="{{ $logoUrl }}" alt="Peers Global Unity" width="180" style="border:0; outline:none; text-decoration:none; vertical-align:middle; display:inline-block; max-width:180px; height:auto;" />
                            <div style="color:#c7d2fe; font-size:12px; letter-spacing:0.15em; text-transform:uppercase; margin-top:12px; font-weight:600;">
                                Leadership Selection Management System
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td style="padding:36px 32px; color:#cbd5e1; font-size:15px; line-height:1.65;">
                            
                            <!-- Success Checkmark & Title -->
                            <div style="text-align:center; margin-bottom:28px;">
                                <div style="display:inline-block; width:56px; height:56px; line-height:56px; border-radius:50%; background-color:rgba(16, 185, 129, 0.15); border:2px solid #10b981; color:#10b981; font-size:26px; text-align:center; margin-bottom:12px;">
                                    ✓
                                </div>
                                <h2 style="margin:0 0 8px 0; color:#ffffff; font-size:24px; font-weight:700; letter-spacing:-0.02em;">
                                    Nomination Application Submitted
                                </h2>
                                <p style="margin:0; color:#94a3b8; font-size:15px;">
                                    Your nomination for <strong style="color:#ffffff;">{{ $roleName }}</strong> has been officially received and routed to the Election Governance Committee.
                                </p>
                            </div>

                            <!-- Greeting -->
                            <p style="margin:0 0 20px 0; font-size:16px; color:#f1f5f9;">
                                Dear <strong>{{ $candidateName }}</strong>,
                            </p>

                            <p style="margin:0 0 24px 0; font-size:15px; line-height:1.6; color:#94a3b8;">
                                Thank you for offering your candidacy to serve in a key leadership capacity within the Peers Global network. Your application has been logged into the central register and is currently undergoing initial administrative scrutiny.
                            </p>

                            <!-- Official Filing Record Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#182234; border:1px solid #283548; border-radius:12px; overflow:hidden; margin:0 0 28px 0; border-collapse:collapse;">
                                <tr>
                                    <td colspan="2" style="padding:16px 20px; background-color:#1e293b; border-bottom:1px solid #283548;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em;">
                                                    Official Filing Record
                                                </td>
                                                <td align="right">
                                                    <span style="display:inline-block; padding:4px 12px; font-size:11px; font-weight:700; color:#34d399; background-color:rgba(16, 185, 129, 0.18); border:1px solid rgba(16, 185, 129, 0.35); border-radius:20px; text-transform:uppercase; letter-spacing:0.05em;">
                                                        ● Under Scrutiny
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding:20px; border-bottom:1px solid #222f42;">
                                        <div style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; letter-spacing:0.08em; margin-bottom:4px;">
                                            Application Reference
                                        </div>
                                        <div style="font-size:24px; font-weight:800; color:#818cf8; font-family:'Courier New', monospace; letter-spacing:0.05em;">
                                            {{ $applicationNumber }}
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" style="padding:14px 20px; border-right:1px solid #222f42; border-bottom:1px solid #222f42;">
                                        <div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:600;">Candidate</div>
                                        <div style="font-size:14px; font-weight:700; color:#f8fafc; margin-top:2px;">{{ $candidateName }}</div>
                                    </td>
                                    <td width="50%" style="padding:14px 20px; border-bottom:1px solid #222f42;">
                                        <div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:600;">Target Role</div>
                                        <div style="font-size:14px; font-weight:700; color:#38bdf8; margin-top:2px;">{{ $roleName }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" style="padding:14px 20px; border-right:1px solid #222f42;">
                                        <div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:600;">Jurisdiction / Scope</div>
                                        <div style="font-size:14px; font-weight:700; color:#f8fafc; margin-top:2px;">{{ $jurisdiction }}</div>
                                    </td>
                                    <td width="50%" style="padding:14px 20px;">
                                        <div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:600;">Submission Date</div>
                                        <div style="font-size:14px; font-weight:700; color:#f8fafc; margin-top:2px;">{{ $submittedAt }}</div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Next Steps Section -->
                            <div style="background-color:#0f172a; border-left:4px solid #6366f1; padding:18px 20px; border-radius:8px; margin-bottom:28px;">
                                <div style="font-size:14px; font-weight:700; color:#ffffff; margin-bottom:10px;">
                                    What Happens Next:
                                </div>
                                <ul style="margin:0; padding-left:18px; color:#94a3b8; font-size:13px; line-height:1.7;">
                                    <li><strong style="color:#e2e8f0;">Credential Verification:</strong> The Scrutiny Committee verifies all submitted responses, leadership track record, and supporting KYC documents in accordance with the Peers Global Charter.</li>
                                    <li><strong style="color:#e2e8f0;">Candidate Shortlist:</strong> Formal notification will be dispatched via SMS & registered email prior to the public announcement and voting launch.</li>
                                    <li><strong style="color:#e2e8f0;">Jury Evaluation:</strong> Shortlisted candidates will receive a secure invitation link to complete Form 2 for the Jury evaluation phase.</li>
                                </ul>
                            </div>

                            <!-- CTA Button -->
                            <div style="text-align:center; margin:32px 0 20px 0;">
                                <a href="{{ $websiteUrl }}" target="_blank" style="display:inline-block; background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:14px 36px; border-radius:10px; box-shadow:0 8px 20px rgba(79, 70, 229, 0.4); letter-spacing:0.02em;">
                                    View Campaign Portal
                                </a>
                            </div>

                            <p style="margin:28px 0 0 0; font-size:14px; line-height:1.6; color:#94a3b8; text-align:center;">
                                Please keep your Application Reference <strong style="color:#818cf8;">{{ $applicationNumber }}</strong> handy for all future communications.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color:#0f172a; padding:28px 24px; border-top:1px solid #1e293b; color:#64748b; font-size:12px; line-height:1.6; text-align:center;">
                            <div style="color:#94a3b8; font-weight:600; font-size:13px; margin-bottom:6px;">
                                Peers Global Unity
                            </div>
                            <div style="margin-bottom:12px; color:#64748b;">
                                Peers are partners in business and friends in life.
                            </div>
                            <div style="font-size:11px; color:#475569;">
                                This is an automated notification from the Peers Global Leadership Selection System.<br>
                                If you need assistance, please contact the Election Governance Committee at <a href="mailto:support@peersglobal.com" style="color:#818cf8; text-decoration:none;">support@peersglobal.com</a>.
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

