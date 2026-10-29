<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiChatbotSetting;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiChatbotService
{
    public function __construct(
        protected GeminiService $geminiService,
        protected AiChatbotToolsService $toolsService
    ) {}

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
            $recentHistory = AiChatMessage::query()
                ->where('session_id', $resolvedSessionId)
                ->when($user !== null, fn ($q) => $q->where('user_id', $user->id))
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
        $jsonInstruction = "\n\nIMPORTANT: Always use the word 'peer' or 'peers' instead of 'member' or 'members'. Return your final response strictly as a JSON object with keys: \"reply_markdown\" (string), \"attachments\" (array), and \"suggested_follow_ups\" (array of 2-3 strings).";
        $fullSystemInstruction = $masterPrompt."\n\n".$userContext.$jsonInstruction;

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

        $query = AiChatMessage::query()
            ->when($user !== null, fn ($q) => $q->where('user_id', $user->id))
            ->when(filled($sessionId), fn ($q) => $q->where('session_id', (string) $sessionId));

        if (blank($sessionId) && $user !== null) {
            $latestSession = AiChatMessage::query()
                ->where('user_id', $user->id)
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
     * Endpoint 4: Clear a specific chat session or all chat sessions for the user.
     */
    public function clearHistory(?User $user, ?string $sessionId = null): void
    {
        if (! Schema::hasTable('ai_chat_messages')) {
            return;
        }

        if ($user === null && blank($sessionId)) {
            return;
        }

        AiChatMessage::query()
            ->when($user !== null, fn ($q) => $q->where('user_id', $user->id))
            ->when(filled($sessionId), fn ($q) => $q->where('session_id', (string) $sessionId))
            ->delete();
    }

    /**
     * Endpoint 5: List recent chat sessions for the authenticated user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSessions(?User $user): array
    {
        if (! Schema::hasTable('ai_chat_messages') || $user === null) {
            return [];
        }

        $messages = AiChatMessage::query()
            ->where('user_id', $user->id)
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
}

