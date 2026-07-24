<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open in {{ $appName }}</title>
    <!-- Modern Premium Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Branding colors based on the app instance */
            --primary-color: {{ $isGreenpreneur ? '#2E7D32' : '#1976D2' }};
            --primary-hover: {{ $isGreenpreneur ? '#1B5E20' : '#1565C0' }};
            --bg-gradient: {{ $isGreenpreneur ? 'linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%)' : 'linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%)' }};
            --text-dark: #1E293B;
            --text-muted: #64748B;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-gradient);
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 40px 30px;
            text-align: center;
            max-width: 420px;
            width: 100%;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
        }

        .icon-container {
            position: relative;
            display: inline-block;
            margin-bottom: 24px;
        }

        .app-icon {
            width: 96px;
            height: 96px;
            border-radius: 22px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            object-fit: cover;
            animation: pulse 2s infinite ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        p {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn {
            display: block;
            background-color: var(--primary-color);
            color: white;
            padding: 14px 28px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
            width: 100%;
        }

        .btn:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            transform: scale(1.01);
        }

        .store-badges {
            margin-top: 32px;
            display: flex;
            justify-content: center;
            gap: 16px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding-top: 24px;
        }

        .store-badge img {
            height: 40px;
            transition: transform 0.2s ease;
        }

        .store-badge img:hover {
            transform: scale(1.05);
        }
    </style>
    <script>
        function redirect() {
            var appScheme = "{!! $appScheme !!}";
            var storeUrl = "{!! $storeUrl !!}";
            var isMobile = {{ $isMobile ? 'true' : 'false' }};
            if (isMobile) {
                // 1. Attempt opening the app using custom scheme URL
                window.location.href = appScheme;
                // 2. Set timeout fallback: if app does not open within 2.5s, direct to the store
                var start = Date.now();
                setTimeout(function() {
                    if (!document.hidden && Date.now() - start < 3000) {
                        window.location.href = storeUrl;
                    }
                }, 2500);
            }
        }
        window.onload = redirect;
    </script>
</head>
<body>
    <div class="card">
        <div class="icon-container">
            <img class="app-icon" src="{{ $isGreenpreneur ? asset('assets/greenpreneur_icon.png') : asset('assets/peers_icon.png') }}" onerror="this.src='https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=120&auto=format&fit=crop&q=60';" alt="App Icon">
        </div>
        
        <h1>Opening in {{ $appName }}...</h1>
        <p>We are redirecting you to the application. If it doesn't open automatically, please click the button below.</p>
        
        <a class="btn" href="{{ $appScheme }}" onclick="setTimeout(function(){ window.location.href = '{{ $storeUrl }}'; }, 2000);">
            Open in App
        </a>

        @if (!$isMobile)
            <div class="store-badges">
                <a class="store-badge" href="{{ $playStoreUrl }}" target="_blank">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Google Play">
                </a>
                <a class="store-badge" href="{{ $appStoreUrl }}" target="_blank">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/3/3c/Download_on_the_App_Store_Badge.svg" alt="App Store">
                </a>
            </div>
        @endif
    </div>
</body>
</html>
