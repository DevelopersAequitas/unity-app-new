<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peers Global Unity | Download the App</title>
    <style>
        :root {
            color-scheme: dark;
            --bg-start: #0a0f1f;
            --bg-end: #101833;
            --card-bg: rgba(255, 255, 255, 0.08);
            --card-border: rgba(255, 255, 255, 0.14);
            --text-primary: #f5f7ff;
            --text-secondary: #c6c9e5;
            --accent: #6be4ff;
            --accent-strong: #0088cc;
            --shadow: 0 24px 60px rgba(4, 8, 26, 0.45);
        }

        /* --- RESET & BASE --- */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at top, #1a2247 0%, var(--bg-end) 45%, var(--bg-start) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            /* Prevents horizontal scrolling on mobile */
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        a { color: inherit; text-decoration: none; }

        /* --- LAYOUT CONTAINER --- */
        .page { 
            width: 100%;
            max-width: 1200px;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex: 1;
        }

        .hero-card {
            width: 100%;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 32px;
            padding: 50px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
            position: relative;
            z-index: 1;
            /* This is crucial to stop content spilling out */
            overflow: hidden; 
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        /* --- TYPOGRAPHY --- */
        .text-content {
            z-index: 3;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(107, 228, 255, 0.15);
            color: var(--accent);
            font-weight: 700;
            margin-bottom: 24px;
        }

        .headline {
            /* Fluid font size: scales between 2.5rem and 3.5rem */
            font-size: clamp(2.5rem, 5vw, 3.8rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
            letter-spacing: -0.02em;
        }

        .subheadline {
            font-size: clamp(1.1rem, 2.5vw, 1.4rem);
            color: var(--text-secondary);
            margin-bottom: 24px;
            font-weight: 500;
            line-height: 1.4;
        }

        .description {
            font-size: 1.05rem;
            line-height: 1.7;
            color: rgba(243, 246, 255, 0.8);
            margin-bottom: 40px;
            max-width: 90%;
        }

        /* --- BUTTONS --- */
        .cta-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        .store-button {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            justify-content: center; /* Centered text looks better */
            gap: 12px;
            padding: 14px 24px;
            border-radius: 18px;
            background: linear-gradient(180deg, #1a1a1a 0%, #000000 100%);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            transition: all 0.2s ease;
            cursor: pointer;
            text-align: left; /* Keep internal text left aligned */
        }
        
        /* Inner alignment for button text */
        .store-button-inner {
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }

        .store-button:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
        }

        .store-icon { width: 30px; height: 30px; flex-shrink: 0; }
        .store-text { display: flex; flex-direction: column; line-height: 1.2; color: #fff; }
        .store-label { font-size: 0.7rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.6); letter-spacing: 0.5px; }
        .store-name { font-size: 1.2rem; font-weight: 700; }

        /* --- PHONE SECTION --- */
        .phone-column {
            display: flex;
            justify-content: center;
            position: relative;
        }

        /* The Glow Effect */
        .phone-glow {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(107, 228, 255, 0.2) 0%, rgba(31, 109, 220, 0.1) 40%, transparent 70%);
            filter: blur(60px);
            z-index: -1;
            pointer-events: none;
            animation: pulseGlow 6s ease-in-out infinite alternate;
        }

        .phone {
            width: 340px;
            height: 680px;
            background: #000;
            border-radius: 55px;
            padding: 12px;
            box-shadow: 
                0 0 0 2px #333,
                0 0 0 5px #111,
                0 40px 100px rgba(0,0,0,0.7);
            position: relative;
            z-index: 10;
        }

        /* Phone Screen */
        .phone-screen {
            background: linear-gradient(180deg, #141824 0%, #000000 100%);
            border-radius: 44px;
            width: 100%;
            height: 100%;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* Dynamic Island */
        .dynamic-island {
            width: 100px; height: 28px; background: #000;
            position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
            border-radius: 20px; z-index: 20;
            display: flex; align-items: center; justify-content: flex-end; padding-right: 8px;
        }
        .di-camera { width: 8px; height: 8px; background: #1a1b25; border-radius: 50%; }

        /* Screen Content */
        .screen-content {
            padding: 55px 20px 20px;
            display: flex; flex-direction: column; gap: 16px;
            height: 100%;
        }

        .phone-header { display: flex; align-items: center; gap: 14px; margin-bottom: 10px; }
        .phone-app-icon { 
            width: 58px; height: 58px; background: #fff; 
            border-radius: 16px; overflow: hidden; 
            display: grid; place-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .phone-app-icon img { width: 100%; height: 100%; object-fit: contain; }
        .phone-app-text h3 { font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 2px; }
        .phone-app-text p { font-size: 13px; color: #888; }

        .mock-widget {
            background: rgba(40, 45, 60, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px; padding: 16px;
            backdrop-filter: blur(10px);
            animation: slideUp 0.8s ease-out forwards;
            opacity: 0; transform: translateY(20px);
        }
        
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-box { background: rgba(255,255,255,0.05); padding: 12px; border-radius: 12px; text-align: center; }
        .stat-num { font-size: 18px; font-weight: 700; color: var(--accent); display: block; }
        .stat-label { font-size: 10px; color: #aaa; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

        .event-tag { background: rgba(255, 77, 77, 0.2); color: #ff6b6b; font-size: 10px; padding: 4px 10px; border-radius: 6px; font-weight: 700; display: inline-block; margin-bottom: 8px; }
        .event-title { font-size: 14px; font-weight: 600; margin-bottom: 6px; }
        .event-date { font-size: 12px; color: #ccc; margin-bottom: 12px; }
        .btn-join { width: 100%; background: var(--accent); color: #090909; border: none; padding: 10px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; transition: opacity 0.2s; }
        .btn-join:hover { opacity: 0.9; }

        .user-row { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, #444, #666); border-radius: 50%; }
        .user-name { font-size: 13px; font-weight: 600; }
        .user-action { font-size: 11px; color: var(--accent); }

        .install-fab {
            margin-top: auto; background: #fff; color: #000;
            text-align: center; padding: 14px; border-radius: 100px;
            font-weight: 700; font-size: 14px;
            box-shadow: 0 0 25px rgba(255,255,255,0.15);
            animation: pulse 2s infinite;
        }

        /* --- KEYFRAMES --- */
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.02); } 100% { transform: scale(1); } }
        @keyframes pulseGlow { 0% { opacity: 0.4; } 100% { opacity: 0.7; } }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

        @media (min-width: 961px) {
            .phone { animation: float 6s ease-in-out infinite; }
        }

        /* --- MOBILE RESPONSIVE FIXES --- */
        @media (max-width: 960px) {
            .page {
                padding: 20px 16px;
            }

            .hero-card {
                padding: 40px 24px;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .hero-grid {
                display: flex;
                flex-direction: column;
                width: 100%;
                gap: 50px;
            }

            .headline {
                font-size: 2.8rem;
                margin-top: 10px;
            }

            .description {
                margin: 0 auto 30px;
                font-size: 1rem;
            }

            .cta-buttons {
                flex-direction: column;
                width: 100%;
                max-width: 400px;
                margin: 0 auto;
            }

            .store-button {
                width: 100%;
                justify-content: center;
            }
            
            .store-button-inner {
                width: auto; 
            }

            .phone-column {
                width: 100%;
                margin-top: 10px;
            }

            .phone {
                width: min(320px, 90vw);
                height: auto;
                aspect-ratio: 1 / 2;
            }
            
            .screen-content {
                padding: 45px 16px 16px;
            }
            
            .phone-glow {
                width: 100vw;
                height: 100vw;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="hero-card">
            <div class="hero-grid">
                
                <div class="text-content">
                    <span class="badge">DIRECT ACCESS FROM PEERSGLOBAL</span>
                    <h1 class="headline">I’m officially Live &amp; Ready.</h1>
                    <p class="subheadline">The Ultimate Growth & Collaboration Platform for Entrepreneurs</p>
                    <p class="description">
                        Step into a world of limitless opportunity. Peers Global Unity is more than an app—it’s your digital gateway to building high-value connections, tracking real-time referrals, and accessing exclusive global events.
                    </p>

                    <div class="cta-buttons">
                        <a class="store-button" href="https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share" target="_blank">
                            <div class="store-button-inner">
                                <span class="store-icon">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M3.7 2.6C2.9 2 1.8 2.6 1.8 3.6v16.8c0 1 1.1 1.6 1.9 1L13.6 12 3.7 2.6z" fill="#00d2ff"/>
                                        <path d="M13.6 12l3.6-3.4 4.1 2.4c1 .6 1 .9 0 1.5l-4.1 2.4L13.6 12z" fill="#ffe000"/>
                                        <path d="M3.7 21.4L13.6 12l3.6 3.4-5.5 3.2c-.7.4-1.6.4-2.3-.1l-5.7-4.1z" fill="#00f076"/>
                                    </svg>
                                </span>
                                <div class="store-text">
                                    <span class="store-label">Get it on</span>
                                    <span class="store-name">Google Play</span>
                                </div>
                            </div>
                        </a>

                        <a class="store-button" href="https://apps.apple.com/in/app/peers-global-unity/id6739198477" target="_blank">
                            <div class="store-button-inner">
                                <span class="store-icon">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M15.9 5.7c-1 .8-1.7 1.9-1.6 3.1 1.1.1 2.2-.6 3-1.5.7-.9 1.2-2.1 1.1-3.4-1.2.1-2.4.8-3.5 1.8z" fill="#ffffff"/>
                                        <path d="M19.3 16.9c-.6 1.4-1.2 2.7-2.3 2.7-1 0-1.3-.7-2.5-.7-1.3 0-1.7.7-2.7.7-1.1 0-1.9-1.2-2.5-2.6-1.4-2.6-1.5-5.6-.7-7 .6-1 1.7-1.7 2.9-1.7 1.1 0 1.8.7 2.7.7.8 0 1.8-.8 3.1-.7 1 .1 2 .5 2.7 1.4-2.4 1.4-2 5.1-.7 6.2z" fill="#ffffff"/>
                                    </svg>
                                </span>
                                <div class="store-text">
                                    <span class="store-label">Download on the</span>
                                    <span class="store-name">App Store</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="phone-column">
                    <div class="phone-glow"></div>
                    <div class="phone">
                        <div class="phone-screen">
                            <div class="dynamic-island"><div class="di-camera"></div></div>
                            <div class="screen-content">
                                <div class="phone-header">
                                    <div class="phone-app-icon">
                                        <img src="{{ url('/api/v1/files/019be538-1251-705b-b26e-5460ee4ef526') }}" alt="Peers Logo">
                                    </div>
                                    <div class="phone-app-text">
                                        <h3>Peers Global Unity</h3>
                                        <p>Vyapaar Jagat</p>
                                    </div>
                                </div>

                                <div class="mock-widget" style="animation-delay: 0.1s;">
                                    <div class="stats-grid">
                                        <div class="stat-box">
                                            <span class="stat-num">1.2k+</span>
                                            <span class="stat-label">Connections</span>
                                        </div>
                                        <div class="stat-box">
                                            <span class="stat-num">85</span>
                                            <span class="stat-label">Referrals</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mock-widget" style="animation-delay: 0.2s;">
                                    <span class="event-tag">LIVE NOW</span>
                                    <div class="event-title">Global Entrepreneur Summit</div>
                                    <div class="event-date">Main Hall • 450 joined</div>
                                    <button class="btn-join">Join Session</button>
                                </div>

                                <div class="mock-widget" style="animation-delay: 0.3s;">
                                    <div class="user-row">
                                        <div class="user-avatar"></div>
                                        <div class="user-info">
                                            <div class="user-name">Sarah Jenkins</div>
                                            <div class="user-action">Sent you a request</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="install-fab">Get the App</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <footer>
            © 2026 Peers Global Unity • Official Download Page
        </footer>
    </div>
    
    <script>window.$zoho=window.$zoho || {};$zoho.salesiq=$zoho.salesiq||{ready:function(){}}</script><script id="zsiqscript" src="https://salesiq.zohopublic.in/widget?wc=siq562ff461f3e0bb558e823904742f628af186f76d1cd9ee7dbedb3fd935b95ee10481df70ef316a7d25f11c1bc50fa6ee" defer></script>
</body>
</html>