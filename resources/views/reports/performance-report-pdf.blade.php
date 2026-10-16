<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['file_name'] ?? 'Performance Report' }}</title>
    <style>
        @page {
            margin: 28px 32px 36px 32px;
            size: a4 portrait;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.45;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .header {
            border-bottom: 2px solid #6366f1;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e1b4b;
            letter-spacing: -0.5px;
            margin: 0 0 2px 0;
        }
        .logo-sub {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6366f1;
            font-weight: 700;
        }
        .report-badge {
            display: inline-block;
            background: #eef2ff;
            color: #4338ca;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #c7d2fe;
        }
        .status-badge {
            display: inline-block;
            background: #ecfdf5;
            color: #047857;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            border: 1px solid #a7f3d0;
        }
        .status-badge.review {
            background: #fffbeb;
            color: #b45309;
            border-color: #fde68a;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 16px;
        }
        .meta-grid td {
            padding: 8px 12px;
            vertical-align: top;
            width: 25%;
            border-right: 1px solid #e2e8f0;
        }
        .meta-grid td:last-child {
            border-right: none;
        }
        .meta-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .meta-value {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 0 -10px 16px -10px;
        }
        .kpi-cell {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .kpi-cell.primary {
            background: #eef2ff;
            border-color: #c7d2fe;
        }
        .kpi-cell.success {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .kpi-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }
        .kpi-cell.primary .kpi-value {
            color: #4338ca;
        }
        .kpi-cell.success .kpi-value {
            color: #047857;
        }
        .section-title {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 14px 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .card p {
            margin: 0;
            color: #334155;
            line-height: 1.5;
        }
        .roster-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .roster-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            padding: 7px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
        }
        .roster-table th:last-child {
            border-right: none;
        }
        .roster-table td {
            padding: 6px 8px;
            font-size: 10px;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            color: #1e293b;
        }
        .roster-table td:last-child {
            border-right: none;
        }
        .roster-table tr:nth-child(even) td {
            background: #fcfcfd;
        }
        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="vertical-align: middle;">
                    <div class="logo-title">PEERS UNITY</div>
                    <div class="logo-sub">Leadership Performance Report</div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <span class="report-badge">{{ $report['report_type'] ?? 'Monthly' }} Report</span>
                    <span class="status-badge {{ strtolower($report['status'] ?? '') === 'under review' ? 'review' : '' }}">
                        {{ $report['status'] ?? 'Approved' }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-grid">
        <tr>
            <td>
                <div class="meta-label">Circle</div>
                <div class="meta-value">{{ $report['circle_name'] ?? 'Mumbai Tech Sunrise' }}</div>
            </td>
            <td>
                <div class="meta-label">District &amp; Industry</div>
                <div class="meta-value">{{ $report['district_name'] ?? 'District Mumbai' }} &bull; {{ $report['industry_name'] ?? 'Technology' }}</div>
            </td>
            <td>
                <div class="meta-label">Submitted By</div>
                <div class="meta-value">{{ $report['submitted_by'] ?? 'Arjun Patel' }} ({{ $report['submitter_role'] ?? 'Circle Chair' }})</div>
            </td>
            <td>
                <div class="meta-label">Period</div>
                <div class="meta-value">{{ $report['period'] ?? now()->format('F Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell primary" style="width: 33.33%;">
                <div class="kpi-label">Attendance Rate</div>
                <div class="kpi-value">{{ number_format((float) ($report['attendance_percentage'] ?? 94.5), 1) }}%</div>
            </td>
            <td class="kpi-cell success" style="width: 33.33%;">
                <div class="kpi-label">Deals Closed Value</div>
                <div class="kpi-value">{{ $report['deals_closed_value'] ?? '₹18.5L' }}</div>
            </td>
            <td class="kpi-cell" style="width: 33.33%;">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value">{{ $report['total_revenue'] ?? '₹24.0L' }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Executive Summary</div>
    <div class="card">
        <p>{{ $report['summary_text'] ?? 'Strong monthly participation with new peer referrals closed across industry categories.' }}</p>
    </div>

    @if(!empty($report['highlights']))
    <div class="section-title">Key Highlights &amp; Wins</div>
    <div class="card">
        <p>{{ $report['highlights'] }}</p>
    </div>
    @endif

    @if(!empty($report['challenges_faced']))
    <div class="section-title">Challenges &amp; Bottlenecks</div>
    <div class="card">
        <p>{{ $report['challenges_faced'] }}</p>
    </div>
    @endif

    @if(!empty($report['action_items']))
    <div class="section-title">Action Items &amp; Next Steps</div>
    <div class="card">
        <p>{{ $report['action_items'] }}</p>
    </div>
    @endif

    @if(!empty($report['peers_roster']) && is_array($report['peers_roster']))
    <div class="section-title">Circle Peers Roster &amp; Engagement ({{ count($report['peers_roster']) }})</div>
    <table class="roster-table">
        <thead>
            <tr>
                <th>Peer Name</th>
                <th>Company &amp; Designation</th>
                <th>Status</th>
                <th>Attendance</th>
                <th>Deals Closed</th>
                <th>P2P</th>
                <th>Referrals</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['peers_roster'] as $peer)
            <tr>
                <td style="font-weight: 700;">{{ $peer['name'] ?? 'Peer Member' }}</td>
                <td>{{ $peer['company'] ?? '-' }} &bull; {{ $peer['designation'] ?? '-' }}</td>
                <td>{{ $peer['status'] ?? 'Active' }}</td>
                <td>{{ $peer['attendance'] ?? '-' }}</td>
                <td>{{ $peer['deals_closed'] ?? '-' }}</td>
                <td>{{ $peer['p2p_count'] ?? 0 }}</td>
                <td>{{ $peer['referrals_count'] ?? 0 }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        Peers Unity Leadership System &bull; Report ID: {{ $report['id'] ?? '' }} &bull; Generated: {{ now()->format('d M Y, h:i A') }} (UTC) &bull; Confidential
    </div>
</body>
</html>
