<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Successful! | Unity App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(17, 25, 40, 0.75);
            --primary-grad: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);
            --text-color: #f3f4f6;
            --text-muted: #9ca3af;
            --success-color: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(236, 72, 153, 0.15) 0px, transparent 50%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-color);
            overflow: hidden;
        }

        .container {
            position: relative;
            background: var(--card-bg);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.125);
            border-radius: 24px;
            padding: 3rem 2rem;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(40px);
        }

        .badge-container {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .badge {
            width: 96px;
            height: 96px;
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.2);
            animation: scaleIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s forwards;
            opacity: 0;
            transform: scale(0.5);
        }

        .badge svg {
            width: 48px;
            height: 48px;
            stroke: var(--success-color);
            stroke-width: 3;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            animation: drawCheck 0.8s ease-in-out 0.8s forwards;
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
        }

        h1 {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: var(--primary-grad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        p {
            font-size: 1.125rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 2rem;
            font-weight: 300;
        }

        .details {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 2.5rem;
            font-family: monospace;
            font-size: 0.9rem;
            color: #a855f7;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
        }

        .details span {
            color: var(--text-color);
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            color: white;
            background: var(--primary-grad);
            background-size: 200% auto;
            border-radius: 50px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 20px -10px rgba(168, 85, 247, 0.5);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -10px rgba(168, 85, 247, 0.7);
            background-position: right center;
        }

        .btn:active {
            transform: translateY(0);
        }

        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes scaleIn {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes drawCheck {
            to {
                stroke-dashoffset: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="badge-container">
            <div class="badge">
                <svg viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
        </div>
        <h1>Deployment Live!</h1>
        <p>Congratulations! The automatic GitHub pipeline has successfully built and deployed the latest code to the QA Server.</p>
        
        <div class="details">
            <div>Pipeline: <span>GitHub Actions</span></div>
            <div>Target Branch: <span>develop</span></div>
            <div>Timestamp: <span id="time">--:--:--</span></div>
        </div>

        <a href="#" class="btn" onclick="window.location.reload(); return false;">Refresh Page</a>
    </div>

    <script>
        const now = new Date();
        document.getElementById('time').innerText = now.toLocaleString();
    </script>
</body>
</html>
