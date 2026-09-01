<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Opening {{ $appName }}...</title>

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
        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 36px 28px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-left-color: #6366F1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 8px; color: #FFFFFF; }
        p { font-size: 14px; color: #94A3B8; margin-bottom: 28px; line-height: 1.5; }
        .btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 12px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            color: #FFFFFF;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        }
        .btn-primary:active { transform: scale(0.98); }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #CBD5E1;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner" id="spinner"></div>
        <h1>Opening {{ $appName }}</h1>
        <p id="status-text">Redirecting you to the app. If it doesn't open automatically, tap below.</p>

        <a href="{{ $appScheme }}" class="btn btn-primary" id="open-app-btn">Open in {{ $appName }}</a>
        <a href="{{ $storeUrl }}" class="btn btn-secondary" id="store-btn">Download from Store</a>
    </div>

    <script>
        (function() {
            var appScheme = @json($appScheme);
            var storeUrl = @json($storeUrl);
            var isMobile = @json($isMobile);
            var isiOS = @json($isiOS);

            function tryOpenApp() {
                var clickedAt = +new Date();

                // Trigger custom scheme
                window.location.href = appScheme;

                // Fallback to store only if user stays on page (i.e. app is not installed)
                setTimeout(function() {
                    var elapsed = +new Date() - clickedAt;
                    // If user is still on page and visible, navigate to App Store / Play Store
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