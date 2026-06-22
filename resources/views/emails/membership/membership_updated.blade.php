<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Peers Membership Has Been Updated</title>
</head>
<body style="font-family: Arial, sans-serif; color: #172033; line-height: 1.5;">
    <p>Dear {{ $peerName }},</p>

    <p>{{ $details['notification_body'] }}</p>

    <table cellpadding="8" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
        <tr>
            <th align="left" style="border: 1px solid #d9e2ec; background: #f7fafc;">Field</th>
            <th align="left" style="border: 1px solid #d9e2ec; background: #f7fafc;">Previous</th>
            <th align="left" style="border: 1px solid #d9e2ec; background: #f7fafc;">Updated</th>
        </tr>
        <tr>
            <td style="border: 1px solid #d9e2ec;">Membership Status</td>
            <td style="border: 1px solid #d9e2ec;">{{ $details['old_status'] }}</td>
            <td style="border: 1px solid #d9e2ec;">{{ $details['new_status'] }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d9e2ec;">Membership Expiry</td>
            <td style="border: 1px solid #d9e2ec;">{{ $details['old_expiry'] }}</td>
            <td style="border: 1px solid #d9e2ec;">{{ $details['new_expiry'] }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d9e2ec;">Update Date/Time</td>
            <td colspan="2" style="border: 1px solid #d9e2ec;">{{ $details['updated_at'] }}</td>
        </tr>
        @if(! empty($details['admin_note']))
            <tr>
                <td style="border: 1px solid #d9e2ec;">Admin Note</td>
                <td colspan="2" style="border: 1px solid #d9e2ec;">{{ $details['admin_note'] }}</td>
            </tr>
        @endif
    </table>

    <p>If you have questions about this update, please contact the Peers support team.</p>

    <p>Regards,<br>Peers Team</p>
</body>
</html>
