<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Congratulations! - Deployment Success</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at 50% 50%, #1a1b2f 0%, #0d0e15 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        /* Background Glows */
        .glow {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(123, 97, 255, 0.15) 0%, rgba(0, 0, 0, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 1;
        }
        .glow-1 { top: -100px; left: -100px; }
        .glow-2 { bottom: -100px; right: -100px; }

        /* Card Container */
        .card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px rgba(255, 255, 255, 0.08) solid;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            padding: 3rem 2rem;
            border-radius: 24px;
            max-width: 480px;
            width: 90%;
            text-align: center;
            z-index: 2;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            animation: fadeInUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Confetti animation placeholder */
        .icon-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #7b61ff 0%, #00ffd1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 8px 24px rgba(123, 97, 255, 0.4);
            animation: pulse 2s infinite;
        }

        h1 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 30%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.1rem;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .badge {
            background: rgba(0, 255, 209, 0.1);
            border: 1px rgba(0, 255, 209, 0.2) solid;
            color: #00ffd1;
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background: linear-gradient(135deg, #7b61ff 0%, #6366f1 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
            background: linear-gradient(135deg, #8a72ff 0%, #4f46e5 100%);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>

    <div class="card">
        <div class="icon-container">
            <div class="icon">🎉</div>
        </div>
        <div class="badge">Production Live</div>
        <h1>Congratulations!</h1>
        <p>The Peers Global Unity production deployment pipeline is working perfectly. The codebase has been updated automatically via GitHub Actions.</p>
        <a href="/" class="btn">Go to Homepage</a>
    </div>
</body>
</html>
