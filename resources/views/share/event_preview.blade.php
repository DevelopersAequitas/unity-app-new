<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $title }} | {{ $appName }}</title>

    <!-- Open Graph / Facebook / WhatsApp / LinkedIn -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->fullUrl() }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($description, 200) }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:site_name" content="{{ $appName }}">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ request()->fullUrl() }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit($description, 200) }}">
    <meta name="twitter:image" content="{{ $image }}">

    @if(!empty($appId))
    <!-- Native iOS Safari Smart App Banner with in-app deep link arguments -->
    <meta name="apple-itunes-app" content="app-id={{ $appId }}, app-argument={{ $appScheme }}">
    @endif

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            color: #FFFFFF;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .preview-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: left;
        }
        .banner-container {
            width: 100%;
            height: 200px;
            background-color: #0F172A;
            position: relative;
            overflow: hidden;
        }
        .banner-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .banner-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4338CA 0%, #6366F1 100%);
            color: #FFFFFF;
            font-size: 32px;
            font-weight: 700;
        }
        .tag-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            color: #A5B4FC;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border: 1px solid rgba(165, 180, 252, 0.2);
        }
        .card-body {
            padding: 24px 20px 28px;
        }
        .event-title {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.35;
            margin-bottom: 12px;
            color: #FFFFFF;
        }
        .event-circle {
            font-size: 13px;
            color: #818CF8;
            font-weight: 600;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .event-desc {
            font-size: 14px;
            color: #94A3B8;
            line-height: 1.55;
            margin-bottom: 24px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 12px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            color: #FFFFFF;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
        }
        .btn-primary:active { transform: scale(0.98); }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #E2E8F0;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .footer-note {
            font-size: 12px;
            color: #64748B;
            text-align: center;
            margin-top: 14px;
        }
    </style>
</head>
<body>
    <div class="preview-card">
        <div class="banner-container">
            @if(!empty($image) && $image !== asset('images/og-default.jpg'))
                <img src="{{ $image }}" alt="{{ $title }}" class="banner-img">
            @else
                <div class="banner-placeholder">
                    {{ substr($title, 0, 1) }}
                </div>
            @endif
            <div class="tag-badge">Event Invitation</div>
        </div>

        <div class="card-body">
            <h1 class="event-title">{{ $title }}</h1>
            @if($event->circle)
                <div class="event-circle">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m4.93 4.93 4.24 4.24"></path><path d="m14.83 9.17 4.24-4.24"></path><path d="m14.83 14.83 4.24 4.24"></path><path d="m9.17 14.83-4.24 4.24"></path></svg>
                    <span>{{ $event->circle->name }}</span>
                </div>
            @endif
            <p class="event-desc">{{ $description }}</p>

            <a href="{{ $appScheme }}" class="btn btn-primary" id="open-app-btn">Open in {{ $appName }}</a>
            <a href="{{ $storeUrl }}" class="btn btn-secondary" id="store-btn">Download App</a>

            <div class="footer-note">
                Shared via {{ $appName }}
            </div>
        </div>
    </div>

    <script>
        (function() {
            var appScheme = @json($appScheme);
            var storeUrl = @json($storeUrl);
            var isMobile = @json($isMobile);
            var isiOS = @json($isiOS);

            function tryOpenApp() {
                var clickedAt = +new Date();
                window.location.href = appScheme;

                setTimeout(function() {
                    var elapsed = +new Date() - clickedAt;
                    if (elapsed < 3000 && !document.hidden && !document.webkitHidden) {
                        window.location.href = storeUrl;
                    }
                }, 2500);
            }

            if (isMobile) {
                tryOpenApp();
            }

            document.getElementById('open-app-btn').addEventListener('click', function(e) {
                window.location.href = appScheme;
            });
        })();
    </script>
</body>
</html>
