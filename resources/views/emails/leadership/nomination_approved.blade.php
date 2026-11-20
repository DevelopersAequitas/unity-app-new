<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your nomination is approved – voting is open | {{ $roleWithRegion }}</title>
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
                                Leadership Selection Management System • Official Candidate
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
                                Congratulations! Your nomination for <strong style="color:#38bdf8;">{{ $roleWithRegion }}</strong> has been officially <strong style="color:#10b981;">APPROVED</strong> by the Scrutiny Committee. Your candidate profile is now live on the Peers Global portal.
                            </p>

                            <!-- Voting Window Box -->
                            <div style="background-color:#0f172a; border:1px solid #1e293b; border-radius:10px; padding:20px; margin:22px 0;">
                                <div style="font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:12px; border-bottom:1px solid #1e293b; padding-bottom:8px;">
                                    VOTING WINDOW
                                </div>
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px; color:#cbd5e1;">
                                    <tr>
                                        <td style="padding:6px 0; width:90px; color:#64748b; font-weight:600;">Opens:</td>
                                        <td style="padding:6px 0; color:#10b981; font-weight:700;">{{ $votingStart }} IST</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; color:#64748b; font-weight:600;">Closes:</td>
                                        <td style="padding:6px 0; color:#f87171; font-weight:700;">{{ $votingEnd }} IST</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- CTA Button / Link Box -->
                            <div style="background-color:#182234; border:1px solid #283548; border-radius:12px; padding:22px 20px; text-align:center; margin:24px 0;">
                                <div style="margin-bottom:12px;">
                                    <a href="{{ $votingLink }}" target="_blank" style="display:inline-block; background:linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:14px 28px; border-radius:10px; box-shadow:0 8px 20px rgba(37, 99, 235, 0.35); letter-spacing:0.02em;">
                                        [ Open Your Voting Page ]
                                    </a>
                                </div>
                                <div style="font-size:13px; color:#94a3b8; word-break:break-all;">
                                    <a href="{{ $votingLink }}" target="_blank" style="color:#60a5fa; text-decoration:underline;">{{ $votingLink }}</a>
                                </div>
                            </div>

                            <!-- Private Candidate Ledger Link -->
                            <div style="background-color:#1e293b; border:1px solid #3b82f6; border-radius:12px; padding:18px 20px; text-align:center; margin:20px 0;">
                                <div style="font-size:12px; font-weight:700; color:#38bdf8; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:8px;">
                                    CONFIDENTIAL CANDIDATE DASHBOARD
                                </div>
                                <p style="margin:0 0 12px 0; font-size:13.5px; color:#cbd5e1;">
                                    Track live voters and supporters in real-time using your secure OTP-protected ledger:
                                </p>
                                <a href="{{ $nomineeLedgerUrl ?? url('/leadership/nomination/' . $nomination->id . '/live-votes') }}" target="_blank" style="display:inline-block; background-color:#2563eb; color:#ffffff; font-size:14px; font-weight:700; text-decoration:none; padding:12px 24px; border-radius:8px;">
                                    [ View Your Live Voters Ledger ]
                                </a>
                                <div style="font-size:11.5px; color:#94a3b8; margin-top:8px;">
                                    (Requires OTP verification sent to this email • Expires when voting closes)
                                </div>
                            </div>

                            <!-- Campaign Kit & Creative Poster -->
                            <div style="background-color:#141c2c; border:1px solid #233044; border-radius:14px; padding:22px 20px; margin:26px 0;">
                                <div style="font-size:12px; font-weight:700; color:#38bdf8; text-transform:uppercase; letter-spacing:0.12em; margin-bottom:10px;">
                                    YOUR CAMPAIGN KIT
                                </div>
                                <p style="margin:0 0 14px 0; font-size:14px; color:#cbd5e1; line-height:1.5;">
                                    Share your voting page with your Circle and network. Every vote from an active Peer member counts.
                                </p>

                                @php
                                    $embedCreativeSrc = null;
                                    if (!empty($creativePath) && file_exists($creativePath) && isset($message)) {
                                        try {
                                            $embedCreativeSrc = $message->embed($creativePath);
                                        } catch (\Throwable $e) {
                                            $embedCreativeSrc = null;
                                        }
                                    }
                                    if (empty($embedCreativeSrc)) {
                                        $embedCreativeSrc = $creativeUrl ?? null;
                                    }
                                @endphp

                                @if (!empty($embedCreativeSrc))
                                <div style="text-align:center; margin:18px 0;">
                                    <a href="{{ $creativeUrl ?? '#' }}" target="_blank" style="text-decoration:none; display:inline-block; max-width:420px; width:100%;">
                                        <img src="{{ $embedCreativeSrc }}" alt="Official Leadership Announcement Creative" width="420" style="max-width:100%; height:auto; border-radius:12px; box-shadow:0 12px 28px rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.1); display:block; margin:0 auto;" />
                                    </a>
                                </div>
                                @endif

                                <div style="text-align:center; margin-top:14px;">
                                    <span style="font-size:13.5px; color:#e2e8f0; font-weight:500;">Ready-to-share creative and message:</span><br>
                                    @if (!empty($creativeUrl))
                                    <a href="{{ $creativeUrl }}" target="_blank" style="display:inline-block; margin-top:8px; background-color:#1e293b; border:1px solid #3b82f6; color:#60a5fa; font-size:13.5px; font-weight:700; text-decoration:none; padding:8px 20px; border-radius:8px;">
                                        [ Download Creative ]
                                    </a>
                                    @else
                                    <span style="font-size:12.5px; color:#94a3b8;">(Creative attached to this email)</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Voting Rules for Peers -->
                            <div style="background-color:#0f172a; border-left:4px solid #3b82f6; border-radius:6px; padding:16px 20px; margin:24px 0;">
                                <div style="font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:10px;">
                                    VOTING RULES FOR PEERS
                                </div>
                                <ul style="margin:0; padding-left:18px; color:#cbd5e1; font-size:13.5px; line-height:1.7;">
                                    <li>Only active, verified Peer members can vote.</li>
                                    <li>One vote per member for this role.</li>
                                    <li>Self-voting is permitted for verified candidates.</li>
                                </ul>
                            </div>

                            <!-- Culture Note -->
                            <p style="margin:0 0 24px 0; font-size:14px; line-height:1.6; color:#94a3b8;">
                                Peers Global is a Community of Collaboration where Peers are Partners in Business and Friends in Life. Engage with integrity, represent your values, and inspire your circle.
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
                                        <td style="padding:6px 0; color:#64748b; font-weight:600;">Status:</td>
                                        <td style="padding:6px 0; color:#10b981; font-weight:700;">Approved – Voting Live</td>
                                    </tr>
                                </table>
                            </div>

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
