<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiChatbotSetting;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiChatbotService
{
    public function __construct(
        protected GeminiService $geminiService,
        protected AiChatbotToolsService $toolsService
    ) {}

    /**
     * Ensure deleted_by_peer_at column exists on ai_chat_messages without requiring manual CLI migration.
     */
    private function ensurePeerDeleteColumn(): bool
    {
        if (! Schema::hasTable('ai_chat_messages')) {
            return false;
        }

        if (! Schema::hasColumn('ai_chat_messages', 'deleted_by_peer_at')) {
            try {
                Schema::table('ai_chat_messages', function (Blueprint $table): void {
                    $table->timestamp('deleted_by_peer_at')->nullable()->after('follow_ups_json')->index();
                });
            } catch (\Throwable) {
                return false;
            }
        }

        return Schema::hasColumn('ai_chat_messages', 'deleted_by_peer_at');
    }

    /**
     * Retrieve or initialize the singleton AiChatbotSetting record.
     */
    public function getSettings(): AiChatbotSetting
    {
        if (Schema::hasTable('ai_chatbot_settings')) {
            $setting = AiChatbotSetting::query()->first();
            if ($setting) {
                return $setting;
            }
        }

        return new AiChatbotSetting([
            'is_enabled' => true,
            'show_floating_button' => true,
            'show_app_bar_icon' => true,
            'show_home_banner' => false,
            'button_title' => 'PG Genie',
            'button_subtitle' => 'Ask anything about Peers Global',
            'bot_name' => 'PG Genie',
            'bot_subtitle' => 'Always here to help you connect & grow',
            'bot_avatar_url' => null,
            'welcome_title' => 'Hello, I am PG Genie! How can I assist you today?',
            'welcome_subtitle' => 'Ask about Peers Global guidelines, find peers by category, check upcoming events, or track your impact.',
            'empty_history_title' => 'PG Genie Assistant',
            'empty_history_subtitle' => 'Select any topic below or type a question to begin.',
            'input_placeholder' => 'Ask PG Genie anything...',
            'thinking_text' => 'PG Genie is thinking...',
            'model_name' => 'gemini-2.0-flash',
            'temperature' => 0.30,
            'master_system_prompt' => AiChatbotSetting::DEFAULT_MASTER_PROMPT,
            'inner_options_json' => AiChatbotSetting::DEFAULT_INNER_OPTIONS,
            'starter_chips_json' => AiChatbotSetting::DEFAULT_STARTER_CHIPS,
            'enabled_tools_json' => AiChatbotSetting::DEFAULT_ENABLED_TOOLS,
        ]);
    }

    /**
     * Endpoint 1: Build dynamic UI configuration payload for Flutter.
     *
     * @return array<string, mixed>
     */
    public function getConfig(?User $user): array
    {
        $s = $this->getSettings();

        $welcomeTitle = (string) ($s->welcome_title ?: 'Hello, I am PG Genie! How can I assist you today?');
        if ($user) {
            $firstName = trim((string) ($user->first_name ?: explode(' ', (string) $user->display_name)[0]));
            if ($firstName !== '' && str_starts_with($welcomeTitle, 'Hello,')) {
                $welcomeTitle = "Hello {$firstName},".substr($welcomeTitle, 6);
            }
        }

        return [
            'is_enabled' => (bool) $s->is_enabled,
            'show_floating_button' => (bool) $s->show_floating_button,
            'show_app_bar_icon' => (bool) $s->show_app_bar_icon,
            'show_home_banner' => (bool) $s->show_home_banner,
            'button_title' => (string) ($s->button_title ?: 'PG Genie'),
            'button_subtitle' => (string) ($s->button_subtitle ?: 'Ask anything about Peers Global'),
            'bot_name' => (string) ($s->bot_name ?: 'PG Genie'),
            'bot_subtitle' => (string) ($s->bot_subtitle ?: 'Always here to help you connect & grow'),
            'bot_avatar_url' => $s->bot_avatar_url,
            'welcome_title' => $welcomeTitle,
            'welcome_subtitle' => (string) ($s->welcome_subtitle ?: 'Ask about Peers Global guidelines, find peers by category, check upcoming events, or track your impact.'),
            'empty_history_title' => (string) ($s->empty_history_title ?: 'PG Genie Assistant'),
            'empty_history_subtitle' => (string) ($s->empty_history_subtitle ?: 'Select any topic below or type a question to begin.'),
            'input_placeholder' => (string) ($s->input_placeholder ?: 'Ask PG Genie anything...'),
            'thinking_text' => (string) ($s->thinking_text ?: 'PG Genie is thinking...'),
            'inner_options' => is_array($s->inner_options_json) && $s->inner_options_json !== []
                ? $s->inner_options_json
                : AiChatbotSetting::DEFAULT_INNER_OPTIONS,
            'starter_chips' => is_array($s->starter_chips_json) && $s->starter_chips_json !== []
                ? $s->starter_chips_json
                : AiChatbotSetting::DEFAULT_STARTER_CHIPS,
        ];
    }

    /**
     * Endpoint 2: Process user message with RAG & Live DB Tools and persist history.
     *
     * @return array<string, mixed>
     */
    public function sendMessage(?User $user, string $userText, ?string $sessionId = null): array
    {
        $settings = $this->getSettings();
        $resolvedSessionId = filled($sessionId) ? (string) $sessionId : 'sess_'.Str::random(12);

        $contents = [];

        if (Schema::hasTable('ai_chat_messages')) {
            $hasDeleteCol = $this->ensurePeerDeleteColumn();
            $recentHistory = AiChatMessage::query()
                ->where('session_id', $resolvedSessionId)
                ->when($user !== null, fn ($q) => $q->where('user_id', $user->id))
                ->when($hasDeleteCol, fn ($q) => $q->whereNull('deleted_by_peer_at'))
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse();

            foreach ($recentHistory as $msg) {
                $contents[] = [
                    'role' => $msg->role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string) $msg->content]],
                ];
            }

            AiChatMessage::create([
                'session_id' => $resolvedSessionId,
                'user_id' => $user?->id,
                'role' => 'user',
                'content' => $userText,
            ]);
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userText]],
        ];

        $masterPrompt = (string) ($settings->master_system_prompt ?: AiChatbotSetting::DEFAULT_MASTER_PROMPT);
        $userContext = $this->toolsService->buildUserContext($user);
        $strictDomainGuardrail = "\n\nSTRICT DOMAIN & APP NAVIGATION GUARDRAIL:\n- You are ONLY permitted to answer questions about Peers Global Unity, its peers, B2B networking, P2P meetings, Unity Circles, Speed Networking, Events, Referrals, Coins, Lives Impacted, Business Deals, Testimonials, Asks/Collaborations, Brand Partners, Peers Store, and official Peers Global policies.\n- If the peer asks ANY unrelated or outside question (e.g., general coding, math, recipes, politics, movies, sports, weather, or general trivia), politely refuse and reply: \"I am PG Genie, your dedicated Peers Global Unity assistant. I can only assist with Peers Global networking, finding peers, P2P meetings, Unity Circles, Speed Networking, Events, Referrals, Coins, and your Life Impact score. How can I help you within Peers Global today?\"\n- Whenever the peer asks about their Coins balance, Coin History/Ledger, Coin Claims, Life Impact history, P2P meetings history, Referrals list, Business Deals, or Testimonials, ALWAYS call `get_referral_and_impact_summary` and list the real items returned (`recent_coin_history`, `recent_coin_claims`, `recent_life_impact_history`, `recent_p2p_meetings`, `recent_referrals`, `recent_business_deals`, `recent_testimonials`) directly in your reply.\n- The peer is ALREADY inside the Peers Global Unity mobile app! NEVER tell them to \"Open the Unity App\" and NEVER invent or guess menu/tab paths like \"Navigate to Profile -> Wallet/Coins -> Transaction History\". Instead, show their real data from the tool and tell them they can tap the action button directly below your message (e.g., **My Coins**, **Claim Coins**, **P2P Meetings**, **My Referrals**, **Life Impact Score**, **Business Deals**, **Testimonials**) to open that screen immediately.\n- Always use the word 'peer' or 'peers' instead of 'member' or 'members'.\n- Return your final response strictly as a JSON object with keys: \"reply_markdown\" (string), \"attachments\" (array), and \"suggested_follow_ups\" (array of 2-3 strings).";
        $fullSystemInstruction = $masterPrompt."\n\n".$userContext.$strictDomainGuardrail;

        $enabledTools = is_array($settings->enabled_tools_json)
            ? $settings->enabled_tools_json
            : AiChatbotSetting::DEFAULT_ENABLED_TOOLS;

        $functionDeclarations = $this->toolsService->getFunctionDeclarations($enabledTools);
        $collectedAttachments = [];

        $aiResult = $this->geminiService->generateChatWithTools(
            contents: $contents,
            systemInstruction: $fullSystemInstruction,
            model: (string) ($settings->model_name ?: 'gemini-2.0-flash'),
            temperature: (float) ($settings->temperature ?? 0.30),
            functionDeclarations: $functionDeclarations,
            toolExecutor: fn (string $fnName, array $fnArgs): array => $this->toolsService->executeTool(
                $fnName,
                $fnArgs,
                $user,
                $collectedAttachments
            )
        );

        $replyMarkdown = (string) ($aiResult['reply_markdown'] ?? '');
        $attachments = array_values(array_merge(
            $collectedAttachments,
            is_array($aiResult['attachments'] ?? null) ? $aiResult['attachments'] : []
        ));
        $attachments = $this->toolsService->enrichContextualButtons($userText, $replyMarkdown, $attachments);
        $followUps = is_array($aiResult['suggested_follow_ups'] ?? null) && $aiResult['suggested_follow_ups'] !== []
            ? array_values(array_map('strval', $aiResult['suggested_follow_ups']))
            : [
                'Find peers in my city',
                'Upcoming events near me',
                'How do I earn Coins?',
            ];

        $msgId = 'msg_'.Str::random(10);
        $createdAt = now()->toIso8601String();

        if (Schema::hasTable('ai_chat_messages')) {
            $savedMsg = AiChatMessage::create([
                'session_id' => $resolvedSessionId,
                'user_id' => $user?->id,
                'role' => 'assistant',
                'content' => $replyMarkdown,
                'attachments_json' => $attachments,
                'follow_ups_json' => $followUps,
            ]);
            $msgId = (string) $savedMsg->id;
            $createdAt = $savedMsg->created_at?->toIso8601String() ?? $createdAt;
        }

        return [
            'session_id' => $resolvedSessionId,
            'message' => [
                'id' => $msgId,
                'session_id' => $resolvedSessionId,
                'role' => 'assistant',
                'reply_markdown' => $replyMarkdown,
                'content' => $replyMarkdown,
                'created_at' => $createdAt,
                'attachments' => $attachments,
                'suggested_follow_ups' => $followUps,
            ],
        ];
    }

    /**
     * Endpoint 3: Return last 30 messages for the authenticated user's active session.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(?User $user, ?string $sessionId = null): array
    {
        if (! Schema::hasTable('ai_chat_messages')) {
            return [];
        }

        if ($user === null && blank($sessionId)) {
            return [];
        }

        $hasDeleteCol = $this->ensurePeerDeleteColumn();

        $query = AiChatMessage::query()
            ->when($user !== null, fn ($q) => $q->where('user_id', $user->id))
            ->when($hasDeleteCol, fn ($q) => $q->whereNull('deleted_by_peer_at'))
            ->when(filled($sessionId), fn ($q) => $q->where('session_id', (string) $sessionId));

        if (blank($sessionId) && $user !== null) {
            $latestSession = AiChatMessage::query()
                ->where('user_id', $user->id)
                ->when($hasDeleteCol, fn ($q) => $q->whereNull('deleted_by_peer_at'))
                ->orderBy('created_at', 'desc')
                ->value('session_id');

            if (! $latestSession) {
                return [];
            }
            $query->where('session_id', (string) $latestSession);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(function (AiChatMessage $m): array {
                $item = [
                    'id' => (string) $m->id,
                    'session_id' => (string) $m->session_id,
                    'role' => (string) $m->role,
                    'content' => (string) $m->content,
                    'created_at' => $m->created_at?->toIso8601String(),
                ];

                if ($m->role === 'assistant') {
                    $item['reply_markdown'] = (string) $m->content;
                    $item['attachments'] = $m->attachments_json ?? [];
                    $item['suggested_follow_ups'] = $m->follow_ups_json ?? [];
                }

                return $item;
            })
            ->all();
    }

    /**
     * Endpoint 4: Soft-mark a specific chat session or all chat sessions as deleted by the peer
     * so it disappears from the peer's mobile app while remaining visible in the Admin Panel with a "Peer Deleted" badge.
     */
    public function clearHistory(?User $user, ?string $sessionId = null): void
    {
        if (! Schema::hasTable('ai_chat_messages')) {
            return;
        }

        if ($user === null && blank($sessionId)) {
            return;
        }

        $hasDeleteCol = $this->ensurePeerDeleteColumn();

        $query = AiChatMessage::query()
            ->when($user !== null, fn ($q) => $q->where('user_id', $user->id))
            ->when(filled($sessionId), fn ($q) => $q->where('session_id', (string) $sessionId));

        if ($hasDeleteCol) {
            $query->whereNull('deleted_by_peer_at')->update([
                'deleted_by_peer_at' => now(),
            ]);
        } else {
            $query->delete();
        }
    }

    /**
     * Endpoint 5: List recent chat sessions for the authenticated user (excluding peer-deleted sessions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSessions(?User $user): array
    {
        if (! Schema::hasTable('ai_chat_messages') || $user === null) {
            return [];
        }

        $hasDeleteCol = $this->ensurePeerDeleteColumn();

        $messages = AiChatMessage::query()
            ->where('user_id', $user->id)
            ->when($hasDeleteCol, fn ($q) => $q->whereNull('deleted_by_peer_at'))
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        $grouped = [];
        foreach ($messages as $m) {
            $sid = (string) $m->session_id;
            if ($sid === '') {
                continue;
            }
            if (! isset($grouped[$sid])) {
                $grouped[$sid] = [
                    'session_id' => $sid,
                    'title' => '',
                    'preview' => '',
                    'message_count' => 0,
                    'updated_at' => $m->created_at?->toIso8601String(),
                ];
            }
            $grouped[$sid]['message_count']++;
            if ($m->role === 'user' && $grouped[$sid]['title'] === '') {
                $grouped[$sid]['title'] = Str::limit(trim((string) $m->content), 60);
            }
            if ($grouped[$sid]['preview'] === '') {
                $grouped[$sid]['preview'] = Str::limit(trim((string) $m->content), 90);
            }
        }

        foreach ($grouped as $sid => &$session) {
            if ($session['title'] === '') {
                $session['title'] = $session['preview'] !== '' ? $session['preview'] : 'Chat Session';
            }
        }

        return array_values($grouped);
    }

    /**
     * Admin Panel: Retrieve complete chat analytics, all peer sessions, and messages (including peer-deleted chats).
     *
     * @return array{
     *   total_peers_chatted: int,
     *   total_sessions: int,
     *   total_messages: int,
     *   peer_deleted_sessions: int,
     *   sessions: array<int, array<string, mixed>>
     * }
     */
    public function getAdminChatOverview(): array
    {
        if (! Schema::hasTable('ai_chat_messages')) {
            return [
                'total_peers_chatted' => 0,
                'total_sessions' => 0,
                'total_messages' => 0,
                'peer_deleted_sessions' => 0,
                'sessions' => [],
            ];
        }

        $hasDeleteCol = $this->ensurePeerDeleteColumn();

        $totalPeersChatted = (int) AiChatMessage::query()
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $totalSessions = (int) AiChatMessage::query()
            ->distinct('session_id')
            ->count('session_id');

        $totalMessages = (int) AiChatMessage::query()->count();

        $peerDeletedSessions = $hasDeleteCol
            ? (int) AiChatMessage::query()
                ->whereNotNull('deleted_by_peer_at')
                ->distinct('session_id')
                ->count('session_id')
            : 0;

        $messages = AiChatMessage::query()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(600)
            ->get();

        $grouped = [];
        foreach ($messages as $m) {
            $sid = (string) ($m->session_id ?: 'unknown');
            $user = $m->user;
            $peerName = $user
                ? (trim((string) ($user->display_name ?: ($user->first_name.' '.$user->last_name))) ?: 'Peer')
                : 'Guest Peer';
            $peerContact = $user ? (string) ($user->email ?: ($user->phone ?? '')) : '';
            $peerCity = $user ? (string) ($user->city ?: ($user->business_city ?? '')) : '';
            $deletedAt = $hasDeleteCol && $m->deleted_by_peer_at ? $m->deleted_by_peer_at : null;

            if (! isset($grouped[$sid])) {
                $grouped[$sid] = [
                    'session_id' => $sid,
                    'peer_name' => $peerName,
                    'peer_contact' => $peerContact,
                    'peer_city' => $peerCity,
                    'title' => '',
                    'message_count' => 0,
                    'is_peer_deleted' => false,
                    'deleted_by_peer_at' => null,
                    'updated_at' => $m->created_at?->format('d M Y, h:i A') ?? '',
                    'messages' => [],
                ];
            }

            $grouped[$sid]['message_count']++;
            if ($deletedAt !== null) {
                $grouped[$sid]['is_peer_deleted'] = true;
                $grouped[$sid]['deleted_by_peer_at'] ??= $deletedAt->format('d M Y, h:i A');
            }
            if ($m->role === 'user') {
                $grouped[$sid]['title'] = Str::limit(trim((string) $m->content), 70);
            }

            $grouped[$sid]['messages'][] = [
                'id' => (string) $m->id,
                'role' => (string) $m->role,
                'content' => (string) $m->content,
                'created_at' => $m->created_at?->format('d M Y, h:i A') ?? '',
                'is_peer_deleted' => $deletedAt !== null,
                'deleted_by_peer_at' => $deletedAt?->format('d M Y, h:i A'),
            ];
        }

        foreach ($grouped as &$session) {
            $session['messages'] = array_reverse($session['messages']);
            if ($session['title'] === '') {
                $firstMsg = $session['messages'][0]['content'] ?? 'Chat Session';
                $session['title'] = Str::limit(trim((string) $firstMsg), 70);
            }
        }

        return [
            'total_peers_chatted' => $totalPeersChatted,
            'total_sessions' => $totalSessions,
            'total_messages' => $totalMessages,
            'peer_deleted_sessions' => $peerDeletedSessions,
            'sessions' => array_slice(array_values($grouped), 0, 100),
        ];
    }
}

