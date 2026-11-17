<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved! Your Candidate Voting Link is Live | Peers Global</title>
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
                                Leadership Selection Management System • Official Candidate
                            </div>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td style="padding:36px 32px; color:#cbd5e1; font-size:15px; line-height:1.65;">
                            
                            <p style="margin:0 0 16px 0; font-size:16px; color:#f1f5f9; font-weight:600;">
                                Dear {{ $candidateName }},
                            </p>

                            <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                Your nomination for <strong style="color:#38bdf8;">{{ $roleName }}</strong> has been officially <strong style="color:#10b981;">APPROVED</strong>! Your candidate profile is now live for voting.
                            </p>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                Share your official voting link with your network and fellow peers to receive their votes:
                            </p>

                            @if (!empty($creativeUrl))
                            <!-- Official Leadership Announcement Creative Poster -->
                            <div style="background-color:#141c2c; border:1px solid #233044; border-radius:14px; padding:20px 16px; margin:24px 0; text-align:center;">
                                <div style="font-size:12px; font-weight:700; color:#38bdf8; text-transform:uppercase; letter-spacing:0.12em; margin-bottom:14px;">
                                    ✨ Official Candidate Announcement Creative
                                </div>
                                <div style="display:inline-block; max-width:440px; width:100%;">
                                    <a href="{{ $creativeUrl }}" target="_blank" style="text-decoration:none; display:block;">
                                        <img src="{{ $creativeUrl }}" alt="Official Candidate Announcement - {{ $candidateName }}" width="440" style="max-width:100%; height:auto; border-radius:12px; box-shadow:0 12px 28px rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.1); display:block; margin:0 auto;" />
                                    </a>
                                </div>
                                <div style="margin-top:14px; font-size:12.5px; color:#94a3b8;">
                                    (Attached to this email. You can also <a href="{{ $creativeUrl }}" target="_blank" style="color:#60a5fa; text-decoration:underline; font-weight:600;">download HD creative</a> to share on WhatsApp, LinkedIn &amp; social channels)
                                </div>
                            </div>
                            @endif

                            <!-- CTA Button / Link Box -->
                            <div style="background-color:#182234; border:1px solid #283548; border-radius:12px; padding:24px 20px; text-align:center; margin:24px 0;">
                                <div style="margin-bottom:14px;">
                                    <a href="{{ $votingLink }}" target="_blank" style="display:inline-block; background:linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:14px 28px; border-radius:10px; box-shadow:0 8px 20px rgba(37, 99, 235, 0.35); letter-spacing:0.02em;">
                                        👉 [ Open Your Voting link ]
                                    </a>
                                </div>
                                <div style="font-size:13px; color:#94a3b8; word-break:break-all;">
                                    <a href="{{ $votingLink }}" target="_blank" style="color:#60a5fa; text-decoration:underline;">{{ $votingLink }}</a>
                                </div>
                            </div>

                            <!-- The Peers Global Culture Note -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#182234; border:1px solid #283548; border-radius:10px; margin:24px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:13.5px; line-height:1.6; color:#cbd5e1;">
                                        <strong style="color:#ffffff; font-size:14px;">The Peers Global :</strong><br>
                                        As a candidate, you represent our core culture: &ldquo;Partners in business, friends in life.&rdquo; Engage your peers with integrity and share your vision for growing your circle.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                Best of luck with your campaign!
                            </p>

                            <p style="margin:24px 0 0 0; font-size:14.5px; color:#94a3b8; font-weight:500;">
                                &mdash; Peers Global Election Committee
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
