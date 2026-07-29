<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Registration Request Received</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.6;">
<p>Hello {{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer' }},</p>

<p>Thank you for registering with us. Your registration has been received successfully.</p>

<p>We are glad to have you as part of the Peers Global Unity community.</p>

<p>Have a great day!</p>

<p>Warm regards,<br>Peers Global Unity Team</p>
</body>
</html>
