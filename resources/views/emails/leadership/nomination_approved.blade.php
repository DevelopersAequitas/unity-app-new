<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Your nomination is approved – voting is open | {{ $roleWithRegion }}</title>
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
                <!-- Main Card Container -->
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
                                    Leadership Selection &bull; Official Candidate
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

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.65; color:#334155;">
                                Congratulations! Your nomination for <strong style="color:#4f46e5;">{{ $roleWithRegion }}</strong> has been officially <strong style="color:#059669; background-color:#ecfdf5; padding:2px 8px; border-radius:4px; border:1px solid #a7f3d0;">APPROVED</strong> by the Scrutiny Committee. Your candidate profile is now live on the Peers Global portal.
                            </p>

                            <!-- Voting Window Box -->
                            <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px 20px; margin:22px 0;">
                                <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                                    VOTING WINDOW
                                </div>
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px; color:#334155;">
                                    <tr>
                                        <td style="padding:6px 0; width:90px; color:#64748b; font-weight:600;">Opens:</td>
                                        <td style="padding:6px 0; color:#059669; font-weight:700;">{{ $votingStart }} IST</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; color:#64748b; font-weight:600;">Closes:</td>
                                        <td style="padding:6px 0; color:#dc2626; font-weight:700;">{{ $votingEnd }} IST</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- CTA Button / Link Box -->
                            <div style="background-color:#faf5ff; border:1px solid #e9d5ff; border-radius:14px; padding:24px 20px; text-align:center; margin:24px 0;">
                                <div style="margin-bottom:12px;">
                                    <a href="{{ $votingLink }}" target="_blank" class="mobile-btn" style="display:inline-block; box-sizing:border-box; max-width:100%; background:linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:14px 32px; border-radius:10px; box-shadow:0 4px 14px rgba(79, 70, 229, 0.3); letter-spacing:0.02em;">
                                        Open Your Voting Page
                                    </a>
                                </div>
                                <div style="font-size:13px; color:#64748b; word-break:break-all;">
                                    <a href="{{ $votingLink }}" target="_blank" style="color:#6366f1; text-decoration:underline;">{{ $votingLink }}</a>
                                </div>
                            </div>

                            <!-- Confidential Candidate Dashboard Box -->
                            <div style="background-color:#eef2ff; border:1px solid #c7d2fe; border-radius:12px; padding:20px; text-align:center; margin:22px 0;">
                                <div style="font-size:11.5px; font-weight:700; color:#4338ca; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:8px;">
                                    CONFIDENTIAL CANDIDATE DASHBOARD
                                </div>
                                <p style="margin:0 0 14px 0; font-size:13.5px; color:#475569; line-height:1.5;">
                                    Track live voters and supporters in real-time using your secure OTP-protected ledger:
                                </p>
                                <a href="{{ $nomineeLedgerUrl ?? url('/leadership/nomination/' . $nomination->id . '/live-votes') }}" target="_blank" class="mobile-btn" style="display:inline-block; box-sizing:border-box; max-width:100%; background:linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color:#ffffff; font-size:14px; font-weight:700; text-decoration:none; padding:12px 28px; border-radius:8px; box-shadow:0 3px 10px rgba(37, 99, 235, 0.25);">
                                    Track Your Votes
                                </a>
                                <div style="font-size:11.5px; color:#64748b; margin-top:10px;">
                                    (Requires OTP verification sent to this email &bull; Expires when voting closes)
                                </div>
                            </div>

                            <!-- Campaign Kit & Creative Poster -->
                            <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:22px 20px; margin:24px 0;">
                                <div style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:10px;">
                                    YOUR CAMPAIGN KIT
                                </div>
                                <p style="margin:0 0 14px 0; font-size:14px; color:#475569; line-height:1.55;">
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
                                    <a href="{{ $creativeViewUrl ?? $creativeUrl ?? '#' }}" target="_blank" style="text-decoration:none; display:inline-block; max-width:420px; width:100%;">
                                        <img src="{{ $embedCreativeSrc }}" alt="Official Leadership Announcement Creative" width="420" style="max-width:100%; height:auto; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.08); border:1px solid #e2e8f0; display:block; margin:0 auto;" />
                                    </a>
                                </div>
                                @endif

                                <div style="text-align:center; margin-top:14px;">
                                    <span style="font-size:13.5px; color:#334155; font-weight:500;">Ready-to-share creative</span><br>
                                    @if (!empty($creativeDownloadUrl) || !empty($creativeUrl))
                                    <a href="{{ $creativeDownloadUrl ?? $creativeUrl }}" target="_blank" style="display:inline-block; margin-top:10px; background-color:#ffffff; border:1px solid #cbd5e1; color:#0f172a; font-size:13.5px; font-weight:600; text-decoration:none; padding:9px 22px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.04);">
                                        Download Creative
                                    </a>
                                    @else
                                    <span style="font-size:12.5px; color:#64748b;">(Creative attached to this email)</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Voting Rules for Peers -->
                            <div style="background-color:#eff6ff; border-left:4px solid #3b82f6; border:1px solid #dbeafe; border-left-width:4px; border-radius:8px; padding:16px 20px; margin:24px 0;">
                                <div style="font-size:11.5px; font-weight:700; color:#1e40af; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:10px;">
                                    VOTING RULES FOR PEERS
                                </div>
                                <ul style="margin:0; padding-left:18px; color:#1e3a8a; font-size:13.5px; line-height:1.7;">
                                    <li>Only active, verified Peer members can vote.</li>
                                    <li>One vote per member for this role.</li>
                                    <li>Self-voting is permitted for verified candidates.</li>
                                </ul>
                            </div>

                            <!-- Culture Note -->
                            <p style="margin:0 0 24px 0; font-size:13.5px; line-height:1.6; color:#64748b; font-style:italic;">
                                Peers Global is a Community of Collaboration where Peers are Partners in Business and Friends in Life. Engage with integrity, represent your values, and inspire your circle.
                            </p>

                            <!-- Application Details Table -->
                            <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px 20px; margin:24px 0;">
                                <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
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
                                        <td class="label-td" style="padding:6px 0; color:#64748b; font-weight:600;">Status:</td>
                                        <td style="padding:6px 0; color:#059669; font-weight:700;">Approved – Voting Live</td>
                                    </tr>
                                </table>
                            </div>

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
