<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Activity Creative' }}</title>
    <style>
        :root{--blue:#0f43c9;--red:#ea1010;--purple:#6f1fad;}
        body{font-family:Arial,sans-serif;background:#eef1f6;margin:0;padding:24px;color:#1f2937}
        .canvas{width:1080px;min-height:1350px;margin:0 auto;background:linear-gradient(180deg,#fff 0%,#f9fbff 100%);position:relative;overflow:hidden;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.08)}
        .generic{padding:36px}
        .brand{position:absolute;right:40px;top:28px;text-align:right}
        .brand .logo{font-size:56px;font-weight:700;line-height:1;background:linear-gradient(90deg,var(--red),var(--blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .brand .tag{font-size:22px;color:#1f2937}
        .pill{display:inline-block;padding:18px 42px;border-radius:999px;background:linear-gradient(90deg,var(--blue),var(--red));color:#fff;font-weight:800;font-size:42px;letter-spacing:1px}
        .heading{font-size:106px;line-height:1.03;font-weight:900;text-align:center;margin:48px 60px;background:linear-gradient(90deg,var(--blue),var(--red));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .content{display:flex;justify-content:space-between;align-items:flex-start;padding:0 88px;margin-top:40px}
        .person{width:360px;text-align:center}
        .ring{width:300px;height:300px;border-radius:50%;padding:8px;background:linear-gradient(120deg,var(--blue),var(--red));margin:0 auto 20px}
        .ring-inner{width:100%;height:100%;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .photo{width:100%;height:100%;object-fit:cover}
        .placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#eef2ff;color:#334155;font-size:92px;font-weight:800}
        .name{font-size:54px;font-weight:900;text-transform:uppercase;background:linear-gradient(90deg,var(--blue),var(--red));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .meta{font-size:38px;line-height:1.35;color:#111827}
        .meta .company{color:#1d4ed8;font-weight:700}
        .center-icon{margin-top:120px;width:150px;height:150px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--red));display:flex;align-items:center;justify-content:center;color:#fff;font-size:72px;box-shadow:0 10px 24px rgba(71,85,105,.25)}
        .amount{margin:34px 0 8px;text-align:center;font-size:42px;font-weight:700;color:#111827}
        .deal-details{text-align:center;font-size:24px;color:#374151}
        .footer-wave{position:absolute;left:0;right:0;bottom:0;height:260px;background:linear-gradient(90deg,#0b3ac9,#8f1aa9,#ea1010);border-top-left-radius:60% 120px;border-top-right-radius:60% 120px;color:#fff;text-align:center;padding-top:92px}
        .footer-wave .line{font-size:48px;font-style:italic}
        .footer-wave .web{font-size:52px;margin-top:20px}
        @media print{body{padding:0;background:#fff}.canvas{box-shadow:none;border-radius:0;margin:0}}
    </style>
</head>
<body>
@if(($activityType ?? '') === 'business_deal')
    <div class="canvas">
        <div class="brand">
            <div class="logo">PeersGlobal</div>
            <div class="tag">Community of Collaboration</div>
        </div>

        <div style="padding-top:180px;text-align:center;">
            <span class="pill">BUSINESS DEAL COMPLETED</span>
        </div>

        <div class="heading">{{ $heading ?? 'MEANINGFUL BUSINESS CONNECTION' }}</div>

        @if(!empty($dealAmount))
            <div class="amount">Deal Amount: ₹{{ $dealAmount }}</div>
        @endif
        <div class="deal-details">
            @if(!empty($dealDate)) {{ \Illuminate\Support\Carbon::parse($dealDate)->format('d M Y') }} @endif
            @if(!empty($businessType)) • {{ $businessType }} @endif
            @if(!empty($comment)) • {{ $comment }} @endif
        </div>

        <div class="content">
            <div class="person">
                <div class="ring"><div class="ring-inner">
                    @if(!empty($fromUser['profile_photo_url']))<img class="photo" src="{{ $fromUser['profile_photo_url'] }}" alt="From User">@else<div class="placeholder">{{ strtoupper(substr($fromUser['name'] ?? 'P',0,1)) }}</div>@endif
                </div></div>
                <div class="name">{{ $fromUser['name'] ?? 'Peer' }}</div>
                <div class="meta">{{ $fromUser['designation'] ?? 'Member' }}</div>
                <div class="meta company">{{ $fromUser['company_name'] ?? 'Peers Member' }}</div>
                <div class="meta">{{ $fromUser['category'] ?? 'Business Category' }}</div>
                <div class="meta">{{ $fromUser['city'] ?? '' }}</div>
            </div>

            <div class="center-icon">☕</div>

            <div class="person">
                <div class="ring"><div class="ring-inner">
                    @if(!empty($toUser['profile_photo_url']))<img class="photo" src="{{ $toUser['profile_photo_url'] }}" alt="To User">@else<div class="placeholder">{{ strtoupper(substr($toUser['name'] ?? 'P',0,1)) }}</div>@endif
                </div></div>
                <div class="name">{{ $toUser['name'] ?? 'Peer' }}</div>
                <div class="meta">{{ $toUser['designation'] ?? 'Member' }}</div>
                <div class="meta company">{{ $toUser['company_name'] ?? 'Peers Member' }}</div>
                <div class="meta">{{ $toUser['category'] ?? 'Business Category' }}</div>
                <div class="meta">{{ $toUser['city'] ?? '' }}</div>
            </div>
        </div>

        <div class="footer-wave">
            <div class="line">Peers are Partners in Business & Friends in Life.</div>
            <div class="web">PeersGlobal.com</div>
        </div>
    </div>
@else
    <div class="canvas generic">
        <h1>{{ $title ?? 'Activity Creative' }}</h1>
        <p><strong>Name:</strong> {{ $userName ?? 'N/A' }}</p>
        <p><strong>Company:</strong> {{ $companyName ?? 'N/A' }}</p>
        <p><strong>Designation:</strong> {{ $designation ?? 'N/A' }}</p>
        <p><strong>City:</strong> {{ $city ?? 'N/A' }}</p>
        <p><strong>Date:</strong> {{ $date ?? now()->format('d M Y') }}</p>
        <hr>
        <p style="white-space: pre-line; font-size: 20px; line-height: 1.6;">{{ $creativeText ?? '' }}</p>
    </div>
@endif
</body>
</html>
