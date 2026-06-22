@php
    $peerName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer';
    $statusLabel = \App\Support\MembershipDisplay::statusLabel($user->membership_status ?? null);
    $startDate = \App\Support\MembershipDisplay::dateLabel($user->membership_starts_at ?? null);
    $expiryDate = \App\Support\MembershipDisplay::dateLabel($user->membership_ends_at ?? $user->membership_expiry ?? null);
    $paymentDate = \App\Support\MembershipDisplay::dateLabel($user->last_payment_at ?? null);
@endphp
<table width="100%" cellspacing="0" cellpadding="0" style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 30px;">
    <tbody>
    <tr>
        <td align="center">
            <table width="600" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <tbody>
                <tr>
                    <td style="padding: 14px 14px; background-color: #240e5c; text-align: center;">
                        <img src="https://unity.peersglobal.com/wp-content/uploads/2025/08/peersglobal_white-removebg-preview.png" alt="Peers Global" width="135" style="vertical-align: middle;" />
                    </td>
                </tr>
                <tr>
                    <td style="padding: 24px 22px; font-size: 16px; line-height: 1.65; color: #333333;">
                        <h2 style="margin: 0 0 16px; color: #240e5c;">Welcome to Peers Global Unity</h2>
                        Dear <strong>{{ $peerName }}</strong>,<br /><br />

                        Welcome to <strong>Peers Global Unity</strong>.<br /><br />

                        We are pleased to confirm that your membership has been successfully activated. Your welcome kit and membership documents are attached for your reference when available.<br /><br />

                        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin: 18px 0; font-size: 14px;">
                            <tbody>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Peer Name</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $peerName }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Email</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $user->email ?? '—' }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Membership Status / Plan</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $statusLabel }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Membership Start Date</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $startDate }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Membership Expiry Date</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $expiryDate }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Payment Date</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $paymentDate }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Plan Code</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $user->zoho_plan_code ?: '—' }}</td></tr>
                            </tbody>
                        </table>

                        Thank you for joining the Peers Global community. We look forward to your active participation and growth journey with us.<br /><br />

                        Warm regards,<br />
                        <strong>Team Peers Global</strong><br />
                        Peers Global Unity<br /><br />
                        <span style="font-size: 13px; color: #6b7280;">This is an automated notification from Peers Global. Please do not reply to this email.</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 14px; background-color: #240e5c; text-align: center; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                        <p style="font-size: 14px; font-weight: bold; color: #ffffff; margin: 4px 0;">Peers are partners in business and friends in life.</p>
                    </td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>
