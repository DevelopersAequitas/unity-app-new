<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Peers Global Speed Collaboration Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 18px 20px;
            line-height: 1.4;
        }
        .header-bar {
            background-color: #1d4ed8;
            color: #ffffff;
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 14px;
        }
        .brand {
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 4px 0;
        }
        .meta {
            font-size: 9px;
            opacity: 0.92;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin: 14px 0 8px 0;
        }
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 8px;
        }
        .kpi-cell {
            width: 25%;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 8px 10px;
            vertical-align: top;
        }
        .kpi-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .kpi-val {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }
        .kpi-sub {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-align: left;
            padding: 6px 7px;
            border-bottom: 1px solid #cbd5e1;
            font-size: 8px;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 5px 7px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            background-color: #e0e7ff;
            color: #3730a3;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-green {
            background-color: #dcfce7;
            color: #166534;
        }
        .notes {
            margin-top: 12px;
            padding: 8px 10px;
            background-color: #f8fafc;
            border-left: 3px solid #1d4ed8;
            font-size: 8px;
            color: #475569;
        }
        .filter-banner {
            font-size: 8.5px;
            color: #475569;
            margin-bottom: 10px;
            background: #f1f5f9;
            padding: 5px 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <div class="brand">Peers Global Unity • Speed Collaboration Analytics</div>
        <div class="title">{{ $event['title'] }}</div>
        <div class="meta">
            Status: {{ $event['status_label'] }}
            @if(!empty($event['scheduled_start_time']))
                &nbsp;|&nbsp; Event Date: {{ \Carbon\Carbon::parse($event['scheduled_start_time'])->utc()->format('d M Y, H:i') }} UTC
            @endif
            &nbsp;|&nbsp; Config: {{ $event['total_rounds'] }} Rounds ({{ round($event['round_duration_seconds'] / 60, 1) }} min/round)
            &nbsp;|&nbsp; Generated: {{ $generatedAt }}
        </div>
    </div>

    <div class="filter-banner">
        <strong>Report Scope:</strong> {{ strtoupper(str_replace('_', ' ', $reportType)) }}
        &nbsp;|&nbsp; <strong>Applied Filters:</strong> {{ $filtersSummary }}
    </div>

    <div class="section-title">Event KPI Summary</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Registered Peers</div>
                <div class="kpi-val">{{ number_format($kpis['registered_peers']) }}</div>
                <div class="kpi-sub">{{ $kpis['canceled_registrations'] }} canceled</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Attended Peers</div>
                <div class="kpi-val">{{ number_format($kpis['attended_peers']) }}</div>
                <div class="kpi-sub">{{ $kpis['attendance_percentage'] }}% attendance rate</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Connections Made</div>
                <div class="kpi-val">{{ number_format($kpis['total_connections_made']) }}</div>
                <div class="kpi-sub">{{ $kpis['unique_peers_connected'] }} unique peers</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Qualifying P2P (&ge;2m)</div>
                <div class="kpi-val">{{ number_format($kpis['qualifying_p2p_connections']) }}</div>
                <div class="kpi-sub">{{ $kpis['completed_meetings'] }} completed meetings</div>
            </td>
        </tr>
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Unique Pairs Connected</div>
                <div class="kpi-val">{{ number_format($kpis['unique_pairs_connected']) }}</div>
                <div class="kpi-sub">{{ $kpis['average_connections_per_attendee'] }} avg / attendee</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Coins Earned (Event)</div>
                <div class="kpi-val">{{ number_format($kpis['total_coins_earned']) }}</div>
                <div class="kpi-sub">{{ $kpis['peers_rewarded_coins'] }} peers rewarded</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Life Impact Generated</div>
                <div class="kpi-val">{{ number_format($kpis['total_life_impact_generated']) }}</div>
                <div class="kpi-sub">{{ $kpis['peers_generated_impact'] }} peers contributed</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Skips / Blocks</div>
                <div class="kpi-val">{{ $kpis['total_skips_count'] }} / {{ $kpis['total_blocks_count'] }}</div>
                <div class="kpi-sub">Avg talk: {{ floor($kpis['average_connection_duration_seconds'] / 60) }}m {{ $kpis['average_connection_duration_seconds'] % 60 }}s</div>
            </td>
        </tr>
    </table>

    @if(in_array($reportType, ['full', 'coins'], true) && count($coins['per_peer']) > 0)
        <div class="section-title">Event-Wise Coin Analytics</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Peer</th>
                    <th>Company</th>
                    <th>Connections</th>
                    <th>Qualifying P2P</th>
                    <th>Transactions</th>
                    <th>Coins Earned (Event)</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($coins['per_peer'], 0, 50) as $row)
                    <tr>
                        <td><strong>{{ $row['name'] }}</strong></td>
                        <td>{{ $row['company'] ?: '—' }}</td>
                        <td>{{ $row['connections_made'] }}</td>
                        <td>{{ $row['qualifying_p2p_count'] }}</td>
                        <td>{{ $row['transactions_count'] }}</td>
                        <td><strong>{{ number_format($row['coins_earned_during_event']) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(in_array($reportType, ['full', 'life_impact'], true) && count($lifeImpact['per_peer']) > 0)
        <div class="section-title">Event-Wise Life Impact Analytics</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Peer</th>
                    <th>Company</th>
                    <th>Connections</th>
                    <th>Impact Activities</th>
                    <th>Life Impact Generated</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($lifeImpact['per_peer'], 0, 50) as $row)
                    <tr>
                        <td><strong>{{ $row['name'] }}</strong></td>
                        <td>{{ $row['company'] ?: '—' }}</td>
                        <td>{{ $row['connections_made'] }}</td>
                        <td>{{ $row['transactions_count'] }}</td>
                        <td><strong>{{ number_format($row['life_impact_earned_during_event']) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(in_array($reportType, ['full', 'attendees', 'peer_performance', 'participation'], true) && count($attendees) > 0)
        <div class="section-title">Attendee &amp; Peer Performance</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Peer</th>
                    <th>Company</th>
                    <th>Attendance</th>
                    <th>Connections</th>
                    <th>Unique Peers</th>
                    <th>Skips</th>
                    <th>Coins</th>
                    <th>Impact</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($attendees, 0, 80) as $att)
                    <tr>
                        <td><strong>{{ $att['name'] }}</strong><br><span style="color:#64748b;font-size:8px;">{{ $att['email'] }}</span></td>
                        <td>{{ $att['company'] ?: '—' }}</td>
                        <td><span class="badge {{ $att['attended'] ? 'badge-green' : '' }}">{{ $att['attendance_status'] }}</span></td>
                        <td>{{ $att['connections_made'] }}</td>
                        <td>{{ $att['unique_peers_met'] }}</td>
                        <td>{{ $att['skips_given'] }}</td>
                        <td>{{ number_format($att['coins_earned']) }}</td>
                        <td>{{ $att['life_impact_earned'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(in_array($reportType, ['full', 'connections'], true) && count($connections) > 0)
        <div class="section-title">Who Met Whom — Connection Details</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Round</th>
                    <th>Peer A</th>
                    <th>Peer B</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Coins / Impact</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($connections, 0, 100) as $conn)
                    <tr>
                        <td>R{{ $conn['round_number'] }} (#{{ $conn['table_number'] }})</td>
                        <td><strong>{{ $conn['user_a']['name'] ?? '—' }}</strong><br><span style="color:#64748b;font-size:8px;">{{ $conn['user_a']['company'] ?? '' }}</span></td>
                        <td><strong>{{ $conn['user_b']['name'] ?? '—' }}</strong><br><span style="color:#64748b;font-size:8px;">{{ $conn['user_b']['company'] ?? '' }}</span></td>
                        <td>{{ floor($conn['duration_seconds'] / 60) }}m {{ str_pad((string) ($conn['duration_seconds'] % 60), 2, '0', STR_PAD_LEFT) }}s</td>
                        <td>{{ $conn['human_status'] }}</td>
                        <td>{{ number_format($conn['coins_earned_total']) }} coins / {{ $conn['life_impact_total'] }} impact</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="notes">
        @foreach($dataNotes as $note)
            <div>• {{ $note }}</div>
        @endforeach
    </div>
</body>
</html>
