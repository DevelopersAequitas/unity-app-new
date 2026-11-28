<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;

class SpeedCollaborationExportService
{
    public function __construct(
        protected SpeedCollaborationAnalyticsService $analyticsService
    ) {}

    /**
     * Build a multi-sheet .xlsx export for a specific Speed Collaboration event, respecting report_type and active filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, content: string}
     */
    public function buildExcelExport(string $eventId, string $reportType = 'full', array $filters = []): array
    {
        $overview = $this->analyticsService->getEventOverview($eventId);
        $attendeesRes = $this->analyticsService->getEventAttendees($eventId, array_merge($filters, ['per_page' => 200, 'page' => 1]));
        $connectionsRes = $this->analyticsService->getEventConnections($eventId, array_merge($filters, ['per_page' => 200, 'page' => 1]));
        $rewardsRes = $this->analyticsService->getEventRewardsAnalytics($eventId, $filters);
        $liveRes = $this->analyticsService->getLiveMonitorSnapshot($eventId, $filters);
        $reportsRes = $this->analyticsService->getEventReports($eventId);

        $event = $overview['event'];
        $kpis = $overview['kpis'];
        $builder = new SimpleXlsxBuilder;

        $includeAll = $reportType === 'full' || $reportType === 'summary';

        // Sheet 1: Event Summary
        $summaryRows = [
            ['Event Name', $event['title']],
            ['Status', $event['status_label']],
            ['Scheduled Start (UTC)', $event['scheduled_start_time'] ?: 'N/A'],
            ['Scheduled End (UTC)', $event['scheduled_end_time'] ?: 'N/A'],
            ['Round Configuration', "{$event['total_rounds']} rounds x ".(int) round($event['round_duration_seconds'] / 60).' min'],
            ['Preview Timer (Seconds)', (int) $event['preview_duration_seconds']],
            ['Max Skip Limit', (int) $event['max_skip_limit']],
            ['Applied Filters', $this->formatFiltersSummary($filters)],
            ['Report Generated At (UTC)', Carbon::now()->utc()->toDateTimeString()],
            ['', ''],
            ['Registered Peers', (int) $kpis['registered_peers']],
            ['Attended Peers', (int) $kpis['attended_peers']],
            ['Not Attended Peers', (int) $kpis['not_attended_peers']],
            ['Canceled Registrations', (int) $kpis['canceled_registrations']],
            ['Attendance Rate (%)', (float) $kpis['attendance_percentage']],
            ['Connections Made (Valid)', (int) $kpis['total_connections_made']],
            ['Completed Meetings', (int) $kpis['completed_meetings']],
            ['Qualifying P2P (>= 2 min)', (int) $kpis['qualifying_p2p_connections']],
            ['Total Match Sessions', (int) $kpis['total_connection_sessions']],
            ['Unique Pairs Connected', (int) $kpis['unique_pairs_connected']],
            ['Unique Peers Connected', (int) $kpis['unique_peers_connected']],
            ['Avg Connections / Attendee', (float) $kpis['average_connections_per_attendee']],
            ['Total Coins Earned (Event)', (int) $kpis['total_coins_earned']],
            ['Total Life Impact Generated (Event)', (int) $kpis['total_life_impact_generated']],
            ['Total Skips Recorded', (int) $kpis['total_skips_count']],
            ['Total Blocks Recorded', (int) $kpis['total_blocks_count']],
        ];

        if ($includeAll || $reportType === 'summary') {
            $builder->addSheet('Event Summary', ['Metric', 'Value'], $summaryRows);
        }

        // Sheet 2: Attendees
        if ($reportType === 'full' || $reportType === 'attendees') {
            $attendeeRows = array_map(static function (array $a): array {
                return [
                    $a['name'],
                    $a['company'],
                    $a['email'],
                    $a['attendance_status'],
                    (int) $a['connections_made'],
                    (int) $a['unique_peers_met'],
                    (int) $a['repeated_connections'],
                    (int) $a['skips_given'],
                    (int) $a['coins_earned'],
                    (int) $a['life_impact_earned'],
                    (int) $a['total_duration_seconds'],
                    $a['human_live_status'],
                ];
            }, $attendeesRes['data']);

            if ($attendeeRows !== [] || $reportType === 'attendees') {
                $builder->addSheet(
                    'Attendees',
                    ['Peer Name', 'Company', 'Email', 'Attendance', 'Connections Made', 'Unique Peers Met', 'Repeat Sessions', 'Skips', 'Coins Earned', 'Life Impact', 'Talk Time (s)', 'Live Status'],
                    $attendeeRows
                );
            }
        }

        // Sheet 3: Connections ("Who Met Whom")
        if ($reportType === 'full' || $reportType === 'connections') {
            $connRows = array_map(static function (array $c): array {
                return [
                    (int) $c['round_number'],
                    (int) $c['table_number'],
                    $c['user_a']['name'] ?? '',
                    $c['user_a']['company'] ?? '',
                    $c['user_b']['name'] ?? '',
                    $c['user_b']['company'] ?? '',
                    $c['started_at'] ?? '',
                    $c['ended_at'] ?? '',
                    (int) $c['duration_seconds'],
                    $c['human_status'],
                    $c['qualifying_p2p'] ? 'Yes' : 'No',
                    (int) $c['coins_earned_total'],
                    (int) $c['life_impact_total'],
                ];
            }, $connectionsRes['connections']);

            if ($connRows !== [] || $reportType === 'connections') {
                $builder->addSheet(
                    'Connections',
                    ['Round', 'Table', 'Peer A', 'Peer A Company', 'Peer B', 'Peer B Company', 'Start Time (UTC)', 'End Time (UTC)', 'Duration (s)', 'Status', 'Qualifying P2P (>=2m)', 'Coins Earned', 'Life Impact'],
                    $connRows
                );
            }
        }

        // Sheet 4: Peer Analytics / Performance
        if ($reportType === 'full' || $reportType === 'peer_performance' || $reportType === 'participation') {
            $peerRows = array_map(static function (array $p): array {
                return [
                    $p['name'],
                    $p['company'],
                    $p['email'],
                    $p['attendance_status'],
                    (int) $p['connections_made'],
                    (int) $p['completed_connections'],
                    (int) $p['unique_peers_met'],
                    (int) $p['repeated_connections'],
                    (int) $p['skips_given'],
                    (int) $p['skips_received'],
                    (int) $p['estimated_waiting_seconds'],
                    (int) $p['avg_duration_seconds'],
                    (int) $p['total_duration_seconds'],
                    (int) $p['coins_earned'],
                    (int) $p['life_impact_earned'],
                ];
            }, $attendeesRes['data']);

            if ($peerRows !== [] || $reportType === 'peer_performance' || $reportType === 'participation') {
                $builder->addSheet(
                    'Peer Analytics',
                    ['Peer Name', 'Company', 'Email', 'Attendance', 'Connections Made', 'Completed', 'Unique Peers Met', 'Repeated Sessions', 'Skips Given', 'Skips Received', 'Est. Wait Time (s)', 'Avg Call (s)', 'Total Talk (s)', 'Event Coins', 'Event Life Impact'],
                    $peerRows
                );
            }
        }

        // Sheet 5: Coin Transactions
        if ($reportType === 'full' || $reportType === 'coins') {
            $coinRows = array_map(static function (array $tx) use ($event): array {
                return [
                    $tx['peer_name'],
                    (int) $tx['amount'],
                    $tx['created_at'] ?? '',
                    $tx['source_type'],
                    $tx['partner_name'] ?? '',
                    $tx['round_number'] !== null ? (int) $tx['round_number'] : '',
                    $event['title'],
                    $tx['reference'],
                    $tx['remark'],
                    $tx['status'],
                ];
            }, $rewardsRes['coins']['transactions']);

            if ($coinRows !== [] || $reportType === 'coins') {
                $builder->addSheet(
                    'Coin Transactions',
                    ['Peer Name', 'Coins Credited', 'Transaction Time (UTC)', 'Source Type', 'Related Partner', 'Round', 'Event', 'Reference', 'Remark', 'Status'],
                    $coinRows
                );
            }
        }

        // Sheet 6: Life Impact Transactions
        if ($reportType === 'full' || $reportType === 'life_impact') {
            $impactRows = array_map(static function (array $tx) use ($event): array {
                return [
                    $tx['peer_name'],
                    (int) $tx['impact_value'],
                    $tx['created_at'] ?? '',
                    $tx['activity_type'],
                    $tx['partner_name'] ?? '',
                    $tx['round_number'] !== null ? (int) $tx['round_number'] : '',
                    $event['title'],
                    $tx['title'],
                    $tx['description'],
                    $tx['status'],
                ];
            }, $rewardsRes['life_impact']['transactions']);

            if ($impactRows !== [] || $reportType === 'life_impact') {
                $builder->addSheet(
                    'Life Impact Transactions',
                    ['Peer Name', 'Impact Amount', 'Date/Time (UTC)', 'Activity Type', 'Related Partner', 'Round', 'Event', 'Title', 'Description', 'Status'],
                    $impactRows
                );
            }
        }

        // Sheet 7: Skip Activity
        if ($reportType === 'full' || $reportType === 'live_activity' || $reportType === 'participation') {
            $skipRows = array_map(static function (array $s) use ($event): array {
                return [
                    $s['user']['name'] ?? '',
                    $s['skipped_peer']['name'] ?? '',
                    $event['title'],
                    (int) $s['skip_count'],
                    (int) $s['max_skip_limit'],
                    $s['limit_reached'] ? 'Limit Reached' : 'Eligible Again',
                    $s['updated_at'] ?? '',
                ];
            }, $liveRes['skips']);

            if ($skipRows !== []) {
                $builder->addSheet(
                    'Skip Activity',
                    ['Skipped By', 'Skipped Peer', 'Event', 'Skip Count', 'Max Limit', 'Status', 'Last Skip Time (UTC)'],
                    $skipRows
                );
            }
        }

        // Sheet 8: Waiting Activity
        if ($reportType === 'full' || $reportType === 'live_activity' || $reportType === 'participation') {
            $waitList = $liveRes['waiting_participants'] !== []
                ? $liveRes['waiting_participants']
                : $reportsRes['participation']['high_waiting_peers'];

            $waitRows = array_map(static function (array $w): array {
                return [
                    $w['name'] ?? '',
                    $w['company'] ?? '',
                    $w['waiting_since'] ?? '',
                    (int) ($w['waiting_duration_seconds'] ?? $w['estimated_waiting_seconds'] ?? 0),
                    (int) ($w['completed_connections'] ?? 0),
                    $w['human_status'] ?? $w['human_live_status'] ?? 'Waiting',
                ];
            }, $waitList);

            if ($waitRows !== []) {
                $builder->addSheet(
                    'Waiting Activity',
                    ['Peer Name', 'Company', 'Waiting Since (UTC)', 'Waiting Duration (s)', 'Completed Connections', 'Current Status'],
                    $waitRows
                );
            }
        }

        $slug = Str::slug($event['title']) ?: 'speed-collaboration';
        $filename = "{$slug}-{$reportType}-report.xlsx";

        return [
            'filename' => $filename,
            'content' => $builder->buildBinary(),
        ];
    }

    /**
     * Build a formatted PDF report using Dompdf.
     *
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, content: string}
     */
    public function buildPdfExport(string $eventId, string $reportType = 'full', array $filters = []): array
    {
        $overview = $this->analyticsService->getEventOverview($eventId);
        $attendeesRes = $this->analyticsService->getEventAttendees($eventId, array_merge($filters, ['per_page' => 200, 'page' => 1]));
        $connectionsRes = $this->analyticsService->getEventConnections($eventId, array_merge($filters, ['per_page' => 200, 'page' => 1]));
        $rewardsRes = $this->analyticsService->getEventRewardsAnalytics($eventId, $filters);
        $reportsRes = $this->analyticsService->getEventReports($eventId);

        $html = view('reports.speed-collaboration-event-pdf', [
            'reportType' => $reportType,
            'generatedAt' => Carbon::now()->utc()->format('d M Y, H:i').' UTC',
            'filtersSummary' => $this->formatFiltersSummary($filters),
            'event' => $overview['event'],
            'kpis' => $overview['kpis'],
            'attendees' => $attendeesRes['data'],
            'connections' => $connectionsRes['connections'],
            'uniqueAnalysis' => $connectionsRes['unique_analysis'],
            'coins' => $rewardsRes['coins'],
            'lifeImpact' => $rewardsRes['life_impact'],
            'participation' => $reportsRes['participation'],
            'dataNotes' => $overview['data_notes'],
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $slug = Str::slug($overview['event']['title']) ?: 'speed-collaboration';

        return [
            'filename' => "{$slug}-{$reportType}-report.pdf",
            'content' => $dompdf->output(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function formatFiltersSummary(array $filters): string
    {
        $parts = [];
        if (! empty($filters['search'])) {
            $parts[] = 'Search: '.$filters['search'];
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $parts[] = 'Status: '.$filters['status'];
        }
        if (! empty($filters['round_number'])) {
            $parts[] = 'Round: '.$filters['round_number'];
        }
        if (! empty($filters['qualifying_only'])) {
            $parts[] = 'Qualifying P2P (>=2 min) only';
        }
        if (! empty($filters['peer_id'])) {
            $parts[] = 'Filtered by Peer';
        }

        return $parts !== [] ? implode(' | ', $parts) : 'None (Full Event Data)';
    }
}
