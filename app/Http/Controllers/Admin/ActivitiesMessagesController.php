<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\IndustryScopeService;
use App\Support\ActivityUserFilter;
use App\Support\AdminCircleScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivitiesMessagesController extends Controller
{
    // ──────────────────────────────────────────────
    // Main Index
    // ──────────────────────────────────────────────

    public function index(Request $request): View
    {
        $filters = $this->buildFilters($request);

        $summary = $this->buildSummary($filters);
        $trends = $this->buildTrends($filters);
        $baseQuery = $this->baseQuery($filters);
        $total = (clone $baseQuery)->count();

        $items = (clone $baseQuery)
            ->select([
                'm.id',
                'm.chat_id',
                'm.sender_id',
                'm.is_read',
                'm.content',
                'm.attachments',
                'm.created_at',
                DB::raw("CASE WHEN m.attachments IS NOT NULL AND m.attachments != 'null'::jsonb AND jsonb_array_length(m.attachments) > 0 THEN 'media' ELSE 'text' END as message_type"),
                'sender.display_name as sender_display_name',
                'sender.first_name as sender_first_name',
                'sender.last_name as sender_last_name',
                'sender.email as sender_email',
                'sender.city as sender_city',
                'sender.membership_status as sender_membership_status',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', sender.first_name, sender.last_name)), ''), sender.display_name, '—') as sender_name"),
                // Receiver: the other participant in the chat
                'receiver.display_name as receiver_display_name',
                'receiver.first_name as receiver_first_name',
                'receiver.last_name as receiver_last_name',
                'receiver.email as receiver_email',
                'receiver.city as receiver_city',
                'receiver.membership_status as receiver_membership_status',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', receiver.first_name, receiver.last_name)), ''), receiver.display_name, '—') as receiver_name"),
            ])
            ->orderBy($this->resolveSortColumn($filters['sort']), $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        // Conversation analytics (top 10 most active)
        $topConversations = $this->topConversations($filters);
        $topSenders = $this->topSenders($filters);
        $topReceivers = $this->topReceivers($filters);

        return view('admin.activities.messages.index', [
            'items' => $items,
            'filters' => $filters,
            'summary' => $summary,
            'trends' => $trends,
            'topConversations' => $topConversations,
            'topSenders' => $topSenders,
            'topReceivers' => $topReceivers,
            'total' => $total,
            'circles' => $this->circleOptions(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Export (no message content)
    // ──────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->buildFilters($request);
        $filename = 'message_analytics_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', '0');
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $handle = fopen('php://output', 'w');

            try {
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [
                    'Message ID',
                    'Conversation ID',
                    'Sender Name',
                    'Sender Email',
                    'Sender City',
                    'Sender Membership',
                    'Receiver Name',
                    'Receiver Email',
                    'Receiver City',
                    'Receiver Membership',
                    'Message Type',
                    'Read Status',
                    'Message Date',
                    'Message Time',
                ]);

                $this->baseQuery($filters)
                    ->select([
                        'm.id', 'm.chat_id', 'm.is_read', 'm.created_at',
                        DB::raw("CASE WHEN m.attachments IS NOT NULL AND m.attachments != 'null'::jsonb AND jsonb_array_length(m.attachments) > 0 THEN 'media' ELSE 'text' END as message_type"),
                        'sender.first_name as s_first', 'sender.last_name as s_last', 'sender.display_name as s_display',
                        'sender.email as s_email', 'sender.city as s_city', 'sender.membership_status as s_membership',
                        'receiver.first_name as r_first', 'receiver.last_name as r_last', 'receiver.display_name as r_display',
                        'receiver.email as r_email', 'receiver.city as r_city', 'receiver.membership_status as r_membership',
                    ])
                    ->orderBy('m.created_at')
                    ->chunk(500, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            $at = $row->created_at ? Carbon::parse($row->created_at) : null;
                            fputcsv($handle, [
                                $row->id,
                                $row->chat_id,
                                $this->formatName($row->s_display, $row->s_first, $row->s_last),
                                $row->s_email ?? '',
                                $row->s_city ?? '',
                                $row->s_membership ?? '',
                                $this->formatName($row->r_display, $row->r_first, $row->r_last),
                                $row->r_email ?? '',
                                $row->r_city ?? '',
                                $row->r_membership ?? '',
                                $row->message_type ?? 'text',
                                $row->is_read ? 'Read' : 'Unread',
                                $at ? $at->format('Y-m-d') : '',
                                $at ? $at->format('H:i:s') : '',
                            ]);
                        }
                    });
            } finally {
                fclose($handle);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    // ──────────────────────────────────────────────
    // Filters
    // ──────────────────────────────────────────────

    private function buildFilters(Request $request): array
    {
        $preset = (string) $request->query('date_preset', '');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        [$fromDt, $toDt] = $this->resolveDateRange($preset, $from, $to);

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 20;
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'date_preset' => $preset,
            'from' => $from,
            'to' => $to,
            'from_dt' => $fromDt,
            'to_dt' => $toDt,
            'circle_id' => (string) $request->query('circle_id', ''),
            'sender_name' => trim((string) $request->query('sender_name', '')),
            'sender_email' => trim((string) $request->query('sender_email', '')),
            'sender_city' => trim((string) $request->query('sender_city', '')),
            'sender_membership' => trim((string) $request->query('sender_membership', '')),
            'receiver_name' => trim((string) $request->query('receiver_name', '')),
            'receiver_email' => trim((string) $request->query('receiver_email', '')),
            'receiver_city' => trim((string) $request->query('receiver_city', '')),
            'receiver_membership' => trim((string) $request->query('receiver_membership', '')),
            'message_type' => trim((string) $request->query('message_type', '')),
            'read_status' => trim((string) $request->query('read_status', '')),
            'per_page' => $perPage,
            'sort' => (string) $request->query('sort', 'created_at'),
            'direction' => strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    // ──────────────────────────────────────────────
    // Base Query
    // ──────────────────────────────────────────────

    private function baseQuery(array $filters)
    {
        // Join: messages → chats → derive receiver as the other chat participant
        $query = DB::table('messages as m')
            ->whereNull('m.deleted_at')  // exclude soft-deleted messages
            ->join('chats as c', 'c.id', '=', 'm.chat_id')
            ->join('users as sender', 'sender.id', '=', 'm.sender_id')
            // Receiver = the other user in the chat
            ->joinSub(
                DB::table('chats')->select('id', DB::raw('CASE WHEN user1_id = user2_id THEN user2_id ELSE user1_id END as receiver_id')),
                'chat_receiver',
                fn ($join) => $join->on('chat_receiver.id', '=', 'm.chat_id')
            )
            ->join('users as receiver', function ($join) {
                $join->on('receiver.id', '=', DB::raw('CASE WHEN c.user1_id = m.sender_id THEN c.user2_id ELSE c.user1_id END'));
            });

        // Global search (no message content for privacy)
        if ($filters['q'] !== '') {
            $like = '%'.$this->escapeLike($filters['q']).'%';
            $query->where(function ($q) use ($like) {
                $q->where('sender.display_name', 'ILIKE', $like)
                    ->orWhere('sender.first_name', 'ILIKE', $like)
                    ->orWhere('sender.last_name', 'ILIKE', $like)
                    ->orWhere('sender.email', 'ILIKE', $like)
                    ->orWhere('receiver.display_name', 'ILIKE', $like)
                    ->orWhere('receiver.first_name', 'ILIKE', $like)
                    ->orWhere('receiver.last_name', 'ILIKE', $like)
                    ->orWhere('receiver.email', 'ILIKE', $like);
            });
        }

        if ($filters['from_dt']) {
            $query->where('m.created_at', '>=', $filters['from_dt']);
        }
        if ($filters['to_dt']) {
            $query->where('m.created_at', '<=', $filters['to_dt']);
        }

        if ($filters['message_type'] === 'text') {
            $query->where(function ($q) {
                $q->whereNull('m.attachments')
                    ->orWhereRaw("m.attachments = 'null'::jsonb")
                    ->orWhereRaw('jsonb_array_length(m.attachments) = 0');
            });
        } elseif ($filters['message_type'] === 'media') {
            $query->whereRaw("m.attachments IS NOT NULL AND m.attachments != 'null'::jsonb AND jsonb_array_length(m.attachments) > 0");
        }

        if ($filters['read_status'] === 'read') {
            $query->where('m.is_read', true);
        } elseif ($filters['read_status'] === 'unread') {
            $query->where('m.is_read', false);
        }

        // Sender filters
        if ($filters['sender_name'] !== '') {
            $like = '%'.$this->escapeLike($filters['sender_name']).'%';
            $query->whereRaw("coalesce(nullif(trim(concat_ws(' ', sender.first_name, sender.last_name)), ''), sender.display_name, '') ILIKE ?", [$like]);
        }
        if ($filters['sender_email'] !== '') {
            $query->where('sender.email', 'ILIKE', '%'.$this->escapeLike($filters['sender_email']).'%');
        }
        if ($filters['sender_city'] !== '') {
            $query->where('sender.city', 'ILIKE', '%'.$this->escapeLike($filters['sender_city']).'%');
        }
        if ($filters['sender_membership'] !== '') {
            $query->where('sender.membership_status', $filters['sender_membership']);
        }

        // Receiver filters
        if ($filters['receiver_name'] !== '') {
            $like = '%'.$this->escapeLike($filters['receiver_name']).'%';
            $query->whereRaw("coalesce(nullif(trim(concat_ws(' ', receiver.first_name, receiver.last_name)), ''), receiver.display_name, '') ILIKE ?", [$like]);
        }
        if ($filters['receiver_email'] !== '') {
            $query->where('receiver.email', 'ILIKE', '%'.$this->escapeLike($filters['receiver_email']).'%');
        }
        if ($filters['receiver_city'] !== '') {
            $query->where('receiver.city', 'ILIKE', '%'.$this->escapeLike($filters['receiver_city']).'%');
        }
        if ($filters['receiver_membership'] !== '') {
            $query->where('receiver.membership_status', $filters['receiver_membership']);
        }

        if ($filters['circle_id'] !== '') {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('circle_members as cm_filter')
                    ->whereColumn('cm_filter.user_id', 'sender.id')
                    ->where('cm_filter.circle_id', $filters['circle_id']);
            });
        }

        $this->applyAdminScope($query, 'm.sender_id', null);

        return $query;
    }

    // ──────────────────────────────────────────────
    // Summary Stats
    // ──────────────────────────────────────────────

    private function buildSummary(array $filters): array
    {
        // Scoped base message query
        $mq = DB::table('messages as m')
            ->whereNull('m.deleted_at')
            ->join('users as sender', 'sender.id', '=', 'm.sender_id');
        ActivityUserFilter::applyToActivityQuery($mq, 'm.sender_id', 'sender');

        if ($filters['from_dt']) {
            $mq->where('m.created_at', '>=', $filters['from_dt']);
        }
        if ($filters['to_dt']) {
            $mq->where('m.created_at', '<=', $filters['to_dt']);
        }

        $stats = (clone $mq)->selectRaw('
            COUNT(*) as total_messages,
            COUNT(*) FILTER (WHERE m.attachments IS NULL OR jsonb_array_length(m.attachments) = 0) as total_text,
            COUNT(*) FILTER (WHERE m.attachments IS NOT NULL AND jsonb_array_length(m.attachments) > 0) as total_media,
            COUNT(*) FILTER (WHERE m.is_read = true) as total_read,
            COUNT(*) FILTER (WHERE m.is_read = false) as total_unread,
            COUNT(DISTINCT m.sender_id) as unique_senders,
            COUNT(DISTINCT m.chat_id) as total_conversations
        ')->first();

        // Unique active receivers
        $uniqReceivers = DB::table('messages as m')
            ->whereNull('m.deleted_at')
            ->join('chats as c', 'c.id', '=', 'm.chat_id')
            ->when($filters['from_dt'], fn ($q) => $q->where('m.created_at', '>=', $filters['from_dt']))
            ->when($filters['to_dt'], fn ($q) => $q->where('m.created_at', '<=', $filters['to_dt']))
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.user1_id = m.sender_id THEN c.user2_id ELSE c.user1_id END) as cnt')
            ->value('cnt');

        $total = (int) ($stats->total_messages ?? 0);
        $totalText = (int) ($stats->total_text ?? 0);
        $totalMedia = (int) ($stats->total_media ?? 0);
        $totalRead = (int) ($stats->total_read ?? 0);
        $totalUnread = (int) ($stats->total_unread ?? 0);
        $totalConvs = (int) ($stats->total_conversations ?? 0);
        $uniqueSenders = (int) ($stats->unique_senders ?? 0);

        $avgPerConv = $totalConvs > 0 ? round($total / $totalConvs, 2) : 0;
        $avgPerMember = max(1, $uniqueSenders) > 0 ? round($total / max(1, $uniqueSenders), 2) : 0;

        return [
            'total_messages' => $total,
            'total_text' => $totalText,
            'total_media' => $totalMedia,
            'total_read' => $totalRead,
            'total_unread' => $totalUnread,
            'unique_senders' => $uniqueSenders,
            'unique_receivers' => (int) $uniqReceivers,
            'total_conversations' => $totalConvs,
            'avg_per_conversation' => $avgPerConv,
            'avg_per_member' => $avgPerMember,
        ];
    }

    // ──────────────────────────────────────────────
    // Trends
    // ──────────────────────────────────────────────

    private function buildTrends(array $filters): array
    {
        $trendFrom = $filters['from_dt'] ?? now()->subDays(29)->startOfDay();
        $trendTo = $filters['to_dt'] ?? now()->endOfDay();

        if (Carbon::parse($trendFrom)->diffInDays(Carbon::parse($trendTo)) > 90) {
            $trendFrom = Carbon::parse($trendTo)->subDays(89)->startOfDay();
        }

        $query = DB::table('messages as m')
            ->whereNull('m.deleted_at')
            ->join('users as sender', 'sender.id', '=', 'm.sender_id');

        ActivityUserFilter::applyToActivityQuery($query, 'm.sender_id', 'sender');

        if ($filters['circle_id'] !== '') {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')->from('circle_members as cm_f')
                    ->whereColumn('cm_f.user_id', 'm.sender_id')
                    ->where('cm_f.circle_id', $filters['circle_id']);
            });
        }

        $query->where('m.created_at', '>=', $trendFrom)->where('m.created_at', '<=', $trendTo);

        $rows = $query->selectRaw("
            DATE(m.created_at AT TIME ZONE 'UTC') as day,
            COUNT(*) as total,
            COUNT(DISTINCT m.chat_id) as conversations
        ")
            ->groupByRaw("DATE(m.created_at AT TIME ZONE 'UTC')")
            ->orderByRaw("DATE(m.created_at AT TIME ZONE 'UTC')")
            ->get();

        $fromDate = Carbon::parse($trendFrom)->startOfDay();
        $toDate = Carbon::parse($trendTo)->endOfDay();

        if ($rows->isEmpty() && empty($filters['from_dt']) && empty($filters['to_dt'])) {
            $latestDate = DB::table('messages')->whereNull('deleted_at')->max('created_at');
            if ($latestDate) {
                $toDate = Carbon::parse($latestDate)->endOfDay();
                $fromDate = $toDate->copy()->subDays(29)->startOfDay();

                $reQuery = DB::table('messages as m')
                    ->whereNull('m.deleted_at')
                    ->join('users as sender', 'sender.id', '=', 'm.sender_id');
                ActivityUserFilter::applyToActivityQuery($reQuery, 'm.sender_id', 'sender');
                if ($filters['circle_id'] !== '') {
                    $reQuery->whereExists(function ($sub) use ($filters) {
                        $sub->selectRaw('1')->from('circle_members as cm_f')
                            ->whereColumn('cm_f.user_id', 'm.sender_id')
                            ->where('cm_f.circle_id', $filters['circle_id']);
                    });
                }
                $reQuery->where('m.created_at', '>=', $fromDate)->where('m.created_at', '<=', $toDate);
                $rows = $reQuery->selectRaw("
                    DATE(m.created_at AT TIME ZONE 'UTC') as day,
                    COUNT(*) as total,
                    COUNT(DISTINCT m.chat_id) as conversations
                ")
                    ->groupByRaw("DATE(m.created_at AT TIME ZONE 'UTC')")
                    ->orderByRaw("DATE(m.created_at AT TIME ZONE 'UTC')")
                    ->get();
            }
        }

        $dataByDay = [];
        foreach ($rows as $row) {
            $dataByDay[$row->day] = $row;
        }

        $labels = [];
        $totals = [];
        $conversations = [];

        $curr = $fromDate->copy();
        while ($curr->lte($toDate)) {
            $dayKey = $curr->format('Y-m-d');
            $labels[] = $curr->format('d M');
            $row = $dataByDay[$dayKey] ?? null;
            $totals[] = $row ? (int) $row->total : 0;
            $conversations[] = $row ? (int) $row->conversations : 0;
            $curr->addDay();
        }

        return compact('labels', 'totals', 'conversations');
    }

    // ──────────────────────────────────────────────
    // Top Reports
    // ──────────────────────────────────────────────

    private function topSenders(array $filters)
    {
        return DB::table('messages as m')
            ->whereNull('m.deleted_at')
            ->join('users as sender', 'sender.id', '=', 'm.sender_id')
            ->when($filters['from_dt'], fn ($q) => $q->where('m.created_at', '>=', $filters['from_dt']))
            ->when($filters['to_dt'], fn ($q) => $q->where('m.created_at', '<=', $filters['to_dt']))
            ->tap(fn ($q) => ActivityUserFilter::applyToActivityQuery($q, 'm.sender_id', 'sender'))
            ->groupBy('m.sender_id', 'sender.display_name', 'sender.first_name', 'sender.last_name', 'sender.email', 'sender.city', 'sender.membership_status')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->select([
                'm.sender_id as user_id',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', sender.first_name, sender.last_name)), ''), sender.display_name, '—') as member_name"),
                'sender.email as member_email',
                'sender.city as member_city',
                'sender.membership_status',
                DB::raw('COUNT(*) as total_sent'),
                DB::raw('COUNT(DISTINCT m.chat_id) as total_conversations'),
            ])
            ->get();
    }

    private function topReceivers(array $filters)
    {
        return DB::table('messages as m')
            ->whereNull('m.deleted_at')
            ->join('chats as c', 'c.id', '=', 'm.chat_id')
            ->join('users as receiver', function ($join) {
                $join->on('receiver.id', '=', DB::raw('CASE WHEN c.user1_id = m.sender_id THEN c.user2_id ELSE c.user1_id END'));
            })
            ->when($filters['from_dt'], fn ($q) => $q->where('m.created_at', '>=', $filters['from_dt']))
            ->when($filters['to_dt'], fn ($q) => $q->where('m.created_at', '<=', $filters['to_dt']))
            ->groupBy('receiver.id', 'receiver.display_name', 'receiver.first_name', 'receiver.last_name', 'receiver.email', 'receiver.city', 'receiver.membership_status')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->select([
                'receiver.id as user_id',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', receiver.first_name, receiver.last_name)), ''), receiver.display_name, '—') as member_name"),
                'receiver.email as member_email',
                'receiver.city as member_city',
                'receiver.membership_status',
                DB::raw('COUNT(*) as total_received'),
            ])
            ->get();
    }

    private function topConversations(array $filters)
    {
        return DB::table('chats as c')
            ->join('users as u1', 'u1.id', '=', 'c.user1_id')
            ->join('users as u2', 'u2.id', '=', 'c.user2_id')
            ->leftJoin('messages as m', function ($join) use ($filters) {
                $join->on('m.chat_id', '=', 'c.id')->whereNull('m.deleted_at');
                if ($filters['from_dt']) {
                    $join->where('m.created_at', '>=', $filters['from_dt']);
                }
                if ($filters['to_dt']) {
                    $join->where('m.created_at', '<=', $filters['to_dt']);
                }
            })
            ->groupBy(
                'c.id', 'c.created_at', 'c.last_message_at',
                'u1.id', 'u1.display_name', 'u1.first_name', 'u1.last_name', 'u1.email',
                'u2.id', 'u2.display_name', 'u2.first_name', 'u2.last_name', 'u2.email'
            )
            ->orderByDesc(DB::raw('COUNT(m.id)'))
            ->limit(10)
            ->select([
                'c.id as chat_id',
                'c.created_at as conversation_started',
                'c.last_message_at',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', u1.first_name, u1.last_name)), ''), u1.display_name, '—') as user1_name"),
                'u1.email as user1_email',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', u2.first_name, u2.last_name)), ''), u2.display_name, '—') as user2_name"),
                'u2.email as user2_email',
                DB::raw('COUNT(m.id) as total_messages'),
            ])
            ->get();
    }

    // ──────────────────────────────────────────────
    // Full Conversation / Chat View Endpoint
    // ──────────────────────────────────────────────

    public function conversation(Request $request, string $chatId)
    {
        $chat = DB::table('chats as c')
            ->leftJoin('users as u1', 'u1.id', '=', 'c.user1_id')
            ->leftJoin('users as u2', 'u2.id', '=', 'c.user2_id')
            ->where('c.id', $chatId)
            ->select([
                'c.id as chat_id',
                'c.user1_id',
                'c.user2_id',
                'c.last_message_at',
                'c.created_at as chat_created_at',
                // User 1
                'u1.display_name as u1_display_name',
                'u1.first_name as u1_first_name',
                'u1.last_name as u1_last_name',
                'u1.email as u1_email',
                'u1.phone as u1_phone',
                'u1.city as u1_city',
                'u1.membership_status as u1_membership',
                'u1.company_name as u1_company',
                'u1.designation as u1_designation',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', u1.first_name, u1.last_name)), ''), u1.display_name, '—') as u1_name"),
                // User 2
                'u2.display_name as u2_display_name',
                'u2.first_name as u2_first_name',
                'u2.last_name as u2_last_name',
                'u2.email as u2_email',
                'u2.phone as u2_phone',
                'u2.city as u2_city',
                'u2.membership_status as u2_membership',
                'u2.company_name as u2_company',
                'u2.designation as u2_designation',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', u2.first_name, u2.last_name)), ''), u2.display_name, '—') as u2_name"),
            ])
            ->first();

        if (! $chat) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found.',
                ], 404);
            }
            return redirect()->route('admin.activities.messages.index')->with('error', 'Conversation not found.');
        }

        $messagesQuery = DB::table('messages as m')
            ->leftJoin('users as sender', 'sender.id', '=', 'm.sender_id')
            ->where('m.chat_id', $chatId)
            ->whereNull('m.deleted_at')
            ->select([
                'm.id',
                'm.chat_id',
                'm.sender_id',
                'm.content',
                'm.attachments',
                'm.is_read',
                'm.created_at',
                'sender.display_name as sender_display_name',
                'sender.first_name as sender_first_name',
                'sender.last_name as sender_last_name',
                'sender.email as sender_email',
                'sender.city as sender_city',
                'sender.membership_status as sender_membership',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', sender.first_name, sender.last_name)), ''), sender.display_name, '—') as sender_name"),
            ])
            ->orderBy('m.created_at', 'asc');

        $messages = $messagesQuery->get()->map(function ($msg) use ($chat) {
            $at = $msg->created_at ? Carbon::parse($msg->created_at) : null;
            $rawAtts = $msg->attachments;
            if (is_string($rawAtts)) {
                $decoded = json_decode($rawAtts, true);
                $atts = is_array($decoded) ? $decoded : [];
            } elseif (is_array($rawAtts)) {
                $atts = $rawAtts;
            } else {
                $atts = [];
            }

            $isMedia = ! empty($atts);
            $isUser1 = ((string) $msg->sender_id === (string) $chat->user1_id);

            return [
                'id'                   => $msg->id,
                'chat_id'              => $msg->chat_id,
                'sender_id'            => $msg->sender_id,
                'sender_name'          => $msg->sender_name,
                'sender_email'         => $msg->sender_email ?? '',
                'sender_city'          => $msg->sender_city ?? '',
                'sender_membership'    => $msg->sender_membership ?? 'Peer',
                'is_user1'             => $isUser1,
                'sender_type'          => $isUser1 ? 'user1' : 'user2',
                'content'              => $msg->content ?? '',
                'attachments'          => $atts,
                'is_media'             => $isMedia,
                'is_read'              => (bool) $msg->is_read,
                'created_at'           => $at ? $at->toIso8601String() : null,
                'created_at_formatted' => $at ? $at->format('M d, Y h:i A') : '—',
                'date_formatted'       => $at ? $at->format('M d, Y') : '—',
                'time_formatted'       => $at ? $at->format('h:i A') : '—',
            ];
        });

        $responseData = [
            'success' => true,
            'data'    => [
                'chat_id'         => $chat->chat_id,
                'total_messages'  => $messages->count(),
                'last_message_at' => $chat->last_message_at ? Carbon::parse($chat->last_message_at)->format('M d, Y h:i A') : null,
                'user1'           => [
                    'id'          => $chat->user1_id,
                    'name'        => $chat->u1_name,
                    'email'       => $chat->u1_email,
                    'phone'       => $chat->u1_phone,
                    'city'        => $chat->u1_city,
                    'company'     => $chat->u1_company,
                    'designation' => $chat->u1_designation,
                    'membership'  => $chat->u1_membership ?? 'Peer',
                ],
                'user2'           => [
                    'id'          => $chat->user2_id,
                    'name'        => $chat->u2_name,
                    'email'       => $chat->u2_email,
                    'phone'       => $chat->u2_phone,
                    'city'        => $chat->u2_city,
                    'company'     => $chat->u2_company,
                    'designation' => $chat->u2_designation,
                    'membership'  => $chat->u2_membership ?? 'Peer',
                ],
                'messages'        => $messages,
            ],
        ];

        return response()->json($responseData);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function resolveDateRange(string $preset, string $from, string $to): array
    {
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'last_quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                $from !== '' ? Carbon::parse($from, $tz)->startOfDay() : null,
                $to !== '' ? Carbon::parse($to, $tz)->endOfDay() : null,
            ],
            default => [null, null],
        };
    }

    private function resolveSortColumn(string $sort): string
    {
        return match ($sort) {
            'sender_name' => 'sender.first_name',
            'receiver_name' => 'receiver.first_name',
            'is_read' => 'm.is_read',
            default => 'm.created_at',
        };
    }

    private function applyAdminScope($query, string $primaryColumn, ?string $peerColumn): void
    {
        $admin = auth('admin')->user();
        AdminCircleScope::applyToActivityQuery($query, $admin, $primaryColumn, $peerColumn);
        app(IndustryScopeService::class)->applyToActivityQuery($query, $admin, array_filter([$primaryColumn, $peerColumn]));
        ActivityUserFilter::applyToActivityQuery($query, $primaryColumn, 'sender');
    }

    private function circleOptions()
    {
        return DB::table('circles')->select(['id', 'name'])->orderBy('name')->get();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    private function formatName(?string $display, ?string $first, ?string $last): string
    {
        $full = trim(($first ?? '').' '.($last ?? ''));

        return $full !== '' ? $full : ($display ?? '—');
    }
}
