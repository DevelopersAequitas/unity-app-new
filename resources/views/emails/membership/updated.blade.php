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
                        <h2 style="margin: 0 0 16px; color: #240e5c;">Membership Updated</h2>
                        <p>Dear <strong>{{ $peerName }}</strong>,</p>
                        <p>Your Peers Global Unity membership details have been updated.</p>
                        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin: 18px 0; font-size: 14px;">
                            <tbody>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Peer Name</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $peerName }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Email</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $user->email ?? '—' }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Old Membership Status</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $oldMembershipStatus }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">New Membership Status</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $newMembershipStatus }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Old Membership Expiry</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $oldMembershipExpiry }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">New Membership Expiry</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $newMembershipExpiry }}</td></tr>
                            <tr><td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: bold;">Updated At</td><td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $updatedAtLabel }}</td></tr>
                            </tbody>
                        </table>
                        <p>Warm regards,<br /><strong>Team Peers Global</strong><br />Peers Global Unity</p>
                        <p style="font-size: 13px; color: #6b7280;">This is an automated notification from Peers Global. Please do not reply to this email.</p>
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
