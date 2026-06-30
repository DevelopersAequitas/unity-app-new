<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine ?? 'Admin Login OTP' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <p>Hello {{ $adminUser->name ?: 'Admin' }},</p>
    <p>Use the one-time password (OTP) below to complete your admin login to Peers Global Unity.</p>
    <p style="font-size: 28px; font-weight: 700; letter-spacing: 6px; margin: 24px 0;">{{ $otp }}</p>
    <p>This OTP expires in 5 minutes.</p>
    <p>For your security, do not share this OTP with anyone.</p>
</body>
</html>
