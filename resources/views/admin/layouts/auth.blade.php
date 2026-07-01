<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Login')</title>
    <style>
        :root {
            --bg-gradient: radial-gradient(circle at 10% 20%, #1f2937 0%, #0b1220 25%, #0a0f1d 50%, #0b1224 75%, #0f172a 100%);
            --card-bg: rgba(18, 24, 38, 0.9);
            --border: rgba(255, 255, 255, 0.08);
            --accent: #8b5cf6;
            --accent-strong: #c084fc;
            --text: #e5e7eb;
            --muted: #9ca3af;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            color: var(--text);
        }
        .page-container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 32px 20px 48px; }
        .auth-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { width: 100%; max-width: 520px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 32px; box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45); backdrop-filter: blur(10px); }
        .card-header { margin-bottom: 20px; }
        .eyebrow { letter-spacing: 0.08em; text-transform: uppercase; font-size: 12px; color: var(--muted); margin: 0 0 8px; }
        h1 { margin: 0; color: #f9fafb; font-size: 28px; font-weight: 700; letter-spacing: -0.02em; }
        p { color: var(--muted); margin: 6px 0 0; line-height: 1.6; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #f3f4f6; letter-spacing: -0.01em; }
        input { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border); background: rgba(255, 255, 255, 0.04); color: #f3f4f6; font-size: 16px; transition: border-color 0.2s, box-shadow 0.2s; }
        input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.25); }
        .input-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: center; }
        .btn { border: none; cursor: pointer; padding: 14px 18px; border-radius: 12px; font-weight: 700; font-size: 16px; transition: transform 0.08s ease, box-shadow 0.2s; }
        .btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn.primary { background: linear-gradient(120deg, var(--accent), var(--accent-strong)); color: #0b0f1a; box-shadow: 0 14px 30px rgba(139, 92, 246, 0.35); }
        .btn.secondary { background: rgba(255, 255, 255, 0.08); color: #f9fafb; border: 1px solid var(--border); }
        .btn:not(:disabled):hover { transform: translateY(-1px); }
        .otp-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 12px; }
        .otp-input { text-align: center; font-size: 22px; letter-spacing: 0.08em; }
        .status { border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-weight: 600; display: none; }
        .status.show { display: block; }
        .status.success { background: rgba(74, 222, 128, 0.12); border: 1px solid rgba(74, 222, 128, 0.4); color: #bbf7d0; }
        .status.error { background: rgba(248, 113, 113, 0.12); border: 1px solid rgba(248, 113, 113, 0.4); color: #fecdd3; }
        .muted { color: var(--muted); font-size: 14px; }
        @media (max-width: 640px) { .card { padding: 24px; } .input-row { grid-template-columns: 1fr; } .btn { width: 100%; } }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
