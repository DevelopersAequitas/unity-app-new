<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Hierarchy Map — Full View</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --neu-bg: #f0f2f7;
            --neu-ink: #0f172a;
            --neu-sub: #64748b;
            --neu-accent: #6366f1;
            --neu-shadow-d: #d2d6df;
            --neu-shadow-l: #ffffff;
            --neu-connector: rgba(99, 102, 241, 0.25);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--neu-bg);
            min-height: 100vh;
            overflow: auto;
            color: var(--neu-ink);
            -webkit-font-smoothing: antialiased;
        }

        /* ─── Topbar ─── */
        .fullmap-topbar {
            position: sticky;
            top: 0;
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            height: 64px;
            background: var(--neu-bg);
            box-shadow: 4px 4px 10px var(--neu-shadow-d), -4px -4px 10px var(--neu-shadow-l);
            border-bottom: none;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .topbar-logo {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--neu-bg);
            box-shadow: 3px 3px 6px var(--neu-shadow-d), -3px -3px 6px var(--neu-shadow-l);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--neu-accent);
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .topbar-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--neu-ink);
            letter-spacing: -0.02em;
        }
        .topbar-title span {
            color: var(--neu-sub);
            font-weight: 500;
            font-size: 0.82rem;
            margin-left: 8px;
        }
        .badge-live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #dcfce7;
            border: none;
            color: #15803d;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            box-shadow: 3px 3px 6px var(--neu-shadow-d), -3px -3px 6px var(--neu-shadow-l);
        }
        .badge-live::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #22c55e;
            border-radius: 50%;
            animation: livepulse 2s infinite;
        }
        @keyframes livepulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .topbar-stat {
            font-size: 0.78rem;
            color: var(--neu-sub);
        }
        .topbar-stat strong {
            color: var(--neu-ink);
            font-weight: 700;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            background: var(--neu-bg);
            border: none;
            border-radius: 12px;
            box-shadow: 4px 4px 8px var(--neu-shadow-d), -4px -4px 8px var(--neu-shadow-l);
            color: var(--neu-ink);
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-back:hover {
            box-shadow: 2px 2px 4px var(--neu-shadow-d), -2px -2px 4px var(--neu-shadow-l);
            transform: translateY(1px);
        }

        /* ─── Canvas ─── */
        .tree-canvas {
            position: relative;
            z-index: 1;
            padding: 56px 80px 100px;
            min-height: calc(100vh - 64px);
            overflow: auto;
        }

        /* ─── Tree Structure ─── */
        .hierarchy-tree {
            display: flex;
            justify-content: center;
        }
        .hierarchy-tree ul {
            padding-top: 24px;
            position: relative;
            display: flex;
            justify-content: center;
            gap: 0;
            list-style: none;
        }
        .hierarchy-tree li {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            list-style: none;
            position: relative;
            padding: 24px 12px 0;
            transition: all 0.5s;
        }

        /* ─── Connectors ─── */
        .hierarchy-tree li::before,
        .hierarchy-tree li::after {
            content: '';
            position: absolute;
            top: 0;
            right: 50%;
            border-top: 2px solid var(--neu-connector);
            width: 50%;
            height: 24px;
        }
        .hierarchy-tree li::after {
            right: auto;
            left: 50%;
            border-left: 2px solid var(--neu-connector);
        }
        .hierarchy-tree li:only-child::after,
        .hierarchy-tree li:only-child::before { display: none; }
        .hierarchy-tree li:only-child { padding-top: 0; }
        .hierarchy-tree li:first-child::before,
        .hierarchy-tree li:last-child::after { border: 0 none; }
        .hierarchy-tree li:last-child::before {
            border-right: 2px solid var(--neu-connector);
            border-radius: 0 8px 0 0;
        }
        .hierarchy-tree li:first-child::after {
            border-radius: 8px 0 0 0;
        }
        .hierarchy-tree ul ul::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            border-left: 2px solid var(--neu-connector);
            width: 0;
            height: 24px;
        }

        /* ─── Node Card ─── */
        .node-box {
            display: inline-block;
            padding: 18px 24px;
            min-width: 170px;
            border-radius: 18px;
            background: var(--neu-bg);
            border: none;
            box-shadow: 7px 7px 14px var(--neu-shadow-d), -7px -7px 14px var(--neu-shadow-l);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 10;
            color: var(--neu-ink);
            cursor: pointer;
        }
        .node-box:hover {
            transform: translateY(-4px);
            box-shadow: 9px 9px 18px var(--neu-shadow-d), -9px -9px 18px var(--neu-shadow-l), 0 0 0 2px var(--neu-accent) inset;
        }

        /* Root Node */
        .node-box.node-root {
            background: linear-gradient(135deg, var(--neu-accent), #818cf8);
            box-shadow: 6px 6px 16px var(--neu-shadow-d), -4px -4px 12px var(--neu-shadow-l);
            color: #fff;
        }

        /* ─── Node Icon ─── */
        .node-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            margin: 0 auto 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: var(--neu-bg);
            box-shadow: inset 3px 3px 7px var(--neu-shadow-d), inset -3px -3px 7px var(--neu-shadow-l);
            color: var(--neu-accent);
            transition: all 0.3s;
        }
        .node-box:hover .node-icon {
            color: #818cf8;
        }
        .node-root .node-icon {
            background: rgba(255, 255, 255, 0.16) !important;
            box-shadow: none !important;
            color: #fff !important;
        }

        /* ─── Node Text ─── */
        .node-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 0.95rem;
        }
        .node-meta {
            font-size: 0.72rem;
            color: var(--neu-sub);
            margin-top: 2px;
        }
        .node-root .node-meta {
            color: #e0e7ff;
        }

        /* ─── Badges ─── */
        .node-badge {
            display: inline-block;
            margin-top: 9px;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }
        .badge-system { background-color: #fee2e2; color: #ef4444; }
        .badge-admin { background-color: #e0e7ff; color: var(--neu-accent); }
        .badge-user { background-color: #dcfce7; color: #15803d; }

        /* ─── Action Buttons ─── */
        .node-actions {
            position: absolute;
            top: -10px;
            right: -10px;
            display: flex;
            gap: 6px;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
        }
        .node-box-interactive:hover .node-actions {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
        }
        .node-action-btn {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 3px 3px 6px var(--neu-shadow-d), -3px -3px 6px var(--neu-shadow-l);
        }
        .node-edit-btn { background: var(--neu-accent); color: white; }
        .node-edit-btn:hover { background: #4f46e5; }
        .node-delete-btn { background: #ef4444; color: white; }
        .node-delete-btn:hover { background: #dc2626; }

        /* ─── Subtree Badge ─── */
        .subtree-link-badge {
            margin-top: 12px;
            font-size: 0.65rem;
            padding: 4px 12px;
            background: rgba(99,102,241,0.06);
            border: 1px dashed rgba(99,102,241,0.25);
            border-radius: 20px;
            color: #818cf8;
            letter-spacing: 0.02em;
        }

        /* ─── Zoom Controls ─── */
        .zoom-controls {
            position: fixed;
            bottom: 28px;
            right: 28px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            z-index: 300;
        }
        .zoom-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: none;
            background: var(--neu-bg);
            color: var(--neu-ink);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 4px 4px 8px var(--neu-shadow-d), -4px -4px 8px var(--neu-shadow-l);
            transition: all 0.2s;
        }
        .zoom-btn:hover {
            box-shadow: 2px 2px 4px var(--neu-shadow-d), -2px -2px 4px var(--neu-shadow-l);
            color: var(--neu-accent);
            transform: translateY(1px);
        }
        .zoom-level {
            background: var(--neu-bg);
            border-radius: 8px;
            padding: 5px 6px;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--neu-accent);
            text-align: center;
            box-shadow: inset 2px 2px 4px var(--neu-shadow-d), inset -2px -2px 4px var(--neu-shadow-l);
        }

        /* ─── Empty State ─── */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 80px 20px;
            color: var(--neu-sub);
        }
        .empty-state i { font-size: 3.5rem; color: var(--neu-sub); opacity: 0.4; }
        .empty-state p { font-size: 0.9rem; }
    </style>
</head>
<body>
    <!-- Topbar -->
    <header class="fullmap-topbar">
        <div class="topbar-left">
            <div class="topbar-logo"><i class="bi bi-diagram-3-fill"></i></div>
            <div>
                <div class="topbar-title">
                    Role Hierarchy Map
                    <span>Visual Tree</span>
                </div>
            </div>
            <span class="badge-live">Live</span>
        </div>
        <div class="topbar-right">
            <div class="topbar-stat">
                <strong>{{ count($roles) }}</strong> roles &nbsp;&bull;&nbsp; <strong>{{ count($roots) }}</strong> root(s)
            </div>
            <a href="{{ route('admin.rbac.hierarchy') }}" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Settings
            </a>
        </div>
    </header>

    <!-- Tree Canvas -->
    <div class="tree-canvas">
        <div id="zoomTarget" style="transform-origin: top center; transition: transform 0.2s;">
            <div class="hierarchy-tree">
                @if(empty($roots))
                    <div class="empty-state">
                        <i class="bi bi-diagram-3"></i>
                        <p>No active role mappings found.</p>
                    </div>
                @else
                    @php $GLOBALS['rendered_subtrees'] = []; @endphp
                    <ul>
                        @foreach($roots as $root)
                            @include('admin.rbac.tree_node', ['role' => $root])
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <!-- Zoom Controls -->
    <div class="zoom-controls">
        <button class="zoom-btn" onclick="adjustZoom(0.1)" title="Zoom In"><i class="bi bi-plus-lg"></i></button>
        <div class="zoom-level" id="zoomLabel">100%</div>
        <button class="zoom-btn" onclick="adjustZoom(-0.1)" title="Zoom Out"><i class="bi bi-dash-lg"></i></button>
        <button class="zoom-btn" onclick="resetZoom()" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></button>
    </div>

    <script>
        let zoomLevel = 1;
        const target = document.getElementById('zoomTarget');
        const label  = document.getElementById('zoomLabel');

        function adjustZoom(delta) {
            zoomLevel = Math.min(2.5, Math.max(0.25, zoomLevel + delta));
            target.style.transform = `scale(${zoomLevel})`;
            label.textContent = Math.round(zoomLevel * 100) + '%';
        }

        function resetZoom() {
            zoomLevel = 1;
            target.style.transform = 'scale(1)';
            label.textContent = '100%';
        }

        // Ctrl/Cmd + scroll to zoom
        document.querySelector('.tree-canvas').addEventListener('wheel', function(e) {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                adjustZoom(e.deltaY < 0 ? 0.06 : -0.06);
            }
        }, { passive: false });
    </script>
</body>
</html>
