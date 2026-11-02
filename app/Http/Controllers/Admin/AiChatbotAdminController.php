<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiKnowledgeDocumentRequest;
use App\Http\Requests\Admin\UpdateAiChatbotSettingRequest;
use App\Models\AiChatbotSetting;
use App\Models\AiKnowledgeDocument;
use App\Services\Ai\AiChatbotService;
use App\Services\Ai\AiKnowledgeRagService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AiChatbotAdminController extends Controller
{
    public function __construct(
        protected AiChatbotService $chatbotService,
        protected AiKnowledgeRagService $ragService
    ) {}

    /**
     * Display Peers AI Chatbot settings and RAG knowledge documents.
     */
    public function index(): View|JsonResponse
    {
        $this->ensureTablesExist();

        $settings = $this->chatbotService->getSettings();
        $documents = collect();

        if (Schema::hasTable('ai_knowledge_documents')) {
            $documents = AiKnowledgeDocument::query()
                ->withCount('chunks')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $chatOverview = $this->chatbotService->getAdminChatOverview();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'settings' => $settings,
                    'documents' => $documents,
                    'chat_overview' => $chatOverview,
                    'available_tools' => AiChatbotSetting::AVAILABLE_TOOLS,
                ],
            ]);
        }

        return view('admin.ai-chatbot.index', compact('settings', 'documents', 'chatOverview'));
    }

    /**
     * Update Peers AI Chatbot configuration in ai_chatbot_settings.
     */
    public function updateSettings(UpdateAiChatbotSettingRequest $request): RedirectResponse|JsonResponse
    {
        $this->ensureTablesExist();

        $validated = $request->validated();

        $innerOptions = json_decode((string) ($validated['inner_options_json'] ?? ''), true);
        if (! is_array($innerOptions)) {
            $innerOptions = AiChatbotSetting::DEFAULT_INNER_OPTIONS;
        }

        $starterChips = json_decode((string) ($validated['starter_chips_json'] ?? ''), true);
        if (! is_array($starterChips)) {
            $starterChips = AiChatbotSetting::DEFAULT_STARTER_CHIPS;
        }

        $setting = AiChatbotSetting::query()->first() ?? new AiChatbotSetting;

        $setting->fill([
            'is_enabled' => $request->boolean('is_enabled'),
            'show_floating_button' => $request->boolean('show_floating_button'),
            'show_app_bar_icon' => $request->boolean('show_app_bar_icon'),
            'show_home_banner' => $request->boolean('show_home_banner'),
            'button_title' => $validated['button_title'],
            'button_subtitle' => $validated['button_subtitle'],
            'bot_name' => $validated['bot_name'],
            'bot_subtitle' => $validated['bot_subtitle'],
            'bot_avatar_url' => $validated['bot_avatar_url'] ?? null,
            'welcome_title' => $validated['welcome_title'],
            'welcome_subtitle' => $validated['welcome_subtitle'] ?? null,
            'empty_history_title' => $validated['empty_history_title'],
            'empty_history_subtitle' => $validated['empty_history_subtitle'],
            'input_placeholder' => $validated['input_placeholder'],
            'thinking_text' => $validated['thinking_text'],
            'model_name' => $validated['model_name'],
            'temperature' => (float) $validated['temperature'],
            'master_system_prompt' => $validated['master_system_prompt'],
            'inner_options_json' => $innerOptions,
            'starter_chips_json' => array_values(array_map('strval', $starterChips)),
            'enabled_tools_json' => array_values($validated['enabled_tools'] ?? []),
        ]);

        $setting->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Peers AI Chatbot configuration saved successfully.',
                'data' => $setting,
            ]);
        }

        return redirect()->route('admin.ai-chatbot.index')
            ->with('success', 'Peers AI Chatbot configuration saved successfully.');
    }

    /**
     * Store a new RAG knowledge document and generate chunks + embeddings.
     */
    public function storeDocument(StoreAiKnowledgeDocumentRequest $request): RedirectResponse|JsonResponse
    {
        $this->ensureTablesExist();

        $validated = $request->validated();

        $document = AiKnowledgeDocument::create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'raw_content' => $validated['raw_content'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $chunkCount = $this->ragService->syncDocumentChunks($document);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Knowledge document '{$document->title}' uploaded and indexed into {$chunkCount} searchable chunks.",
                'data' => $document->loadCount('chunks'),
            ], 201);
        }

        return redirect()->route('admin.ai-chatbot.index')
            ->with('success', "Knowledge document '{$document->title}' uploaded and indexed into {$chunkCount} searchable chunks.");
    }

    /**
     * Toggle active status of a RAG knowledge document.
     */
    public function toggleDocument(string $id): RedirectResponse|JsonResponse
    {
        $this->ensureTablesExist();

        $doc = AiKnowledgeDocument::query()->findOrFail($id);
        $doc->is_active = ! $doc->is_active;
        $doc->save();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Document '{$doc->title}' status updated.",
                'data' => $doc,
            ]);
        }

        return redirect()->route('admin.ai-chatbot.index')
            ->with('success', "Document '{$doc->title}' status updated.");
    }

    /**
     * Delete a RAG knowledge document and its chunks.
     */
    public function destroyDocument(string $id): RedirectResponse|JsonResponse
    {
        $this->ensureTablesExist();

        $doc = AiKnowledgeDocument::query()->findOrFail($id);
        $title = $doc->title;
        $doc->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Document '{$title}' deleted.",
            ]);
        }

        return redirect()->route('admin.ai-chatbot.index')
            ->with('success', "Document '{$title}' deleted.");
    }

    /**
     * Ensure database tables for AI chatbot and RAG exist dynamically without requiring migrations.
     */
    protected function ensureTablesExist(): void
    {
        if (! Schema::hasTable('ai_chatbot_settings')) {
            try {
                Schema::create('ai_chatbot_settings', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->boolean('is_enabled')->default(true);
                    $table->boolean('show_floating_button')->default(true);
                    $table->boolean('show_app_bar_icon')->default(true);
                    $table->boolean('show_home_banner')->default(false);
                    $table->string('button_title', 100)->default('PG Genie');
                    $table->string('button_subtitle', 255)->default('Ask anything about Peers Global');
                    $table->string('bot_name', 100)->default('PG Genie');
                    $table->string('bot_subtitle', 255)->default('Always here to help you connect & grow');
                    $table->string('bot_avatar_url', 500)->nullable();
                    $table->string('welcome_title', 255)->default('Hello, I am PG Genie! How can I assist you today?');
                    $table->text('welcome_subtitle')->nullable();
                    $table->string('empty_history_title', 255)->default('PG Genie Assistant');
                    $table->string('empty_history_subtitle', 255)->default('Select any topic below or type a question to begin.');
                    $table->string('input_placeholder', 255)->default('Ask PG Genie anything...');
                    $table->string('thinking_text', 150)->default('PG Genie is thinking...');
                    $table->string('model_name', 100)->default('gemini-2.0-flash');
                    $table->decimal('temperature', 3, 2)->default(0.30);
                    $table->longText('master_system_prompt');
                    $table->json('inner_options_json')->nullable();
                    $table->json('starter_chips_json')->nullable();
                    $table->json('enabled_tools_json')->nullable();
                    $table->timestamps();
                });
            } catch (\Throwable) {}
        }

        if (! Schema::hasTable('ai_knowledge_documents')) {
            try {
                Schema::create('ai_knowledge_documents', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('title');
                    $table->string('category', 50);
                    $table->longText('raw_content');
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                });
            } catch (\Throwable) {}
        }

        if (! Schema::hasTable('ai_knowledge_chunks')) {
            try {
                Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->uuid('document_id')->index();
                    $table->longText('chunk_text');
                    $table->json('embedding_json')->nullable();
                    $table->timestamps();
                });
            } catch (\Throwable) {}
        }

        if (! Schema::hasTable('ai_chat_messages')) {
            try {
                Schema::create('ai_chat_messages', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('session_id', 100)->index();
                    $table->uuid('user_id')->nullable()->index();
                    $table->string('role', 20);
                    $table->longText('content');
                    $table->json('attachments_json')->nullable();
                    $table->json('follow_ups_json')->nullable();
                    $table->timestamp('deleted_by_peer_at')->nullable()->index();
                    $table->timestamps();
                });
            } catch (\Throwable) {}
        }

        // Seed initial settings if table is empty
        if (Schema::hasTable('ai_chatbot_settings') && AiChatbotSetting::query()->count() === 0) {
            try {
                AiChatbotSetting::create([
                    'is_enabled' => true,
                    'show_floating_button' => true,
                    'show_app_bar_icon' => false,
                    'show_home_banner' => false,
                    'button_title' => 'PG Genie',
                    'button_subtitle' => 'Ask anything about Peers Global',
                    'bot_name' => 'PG Genie',
                    'bot_subtitle' => 'Always here to help you connect & grow',
                    'bot_avatar_url' => 'https://dev.peersunity.com/storage/bot/avatar.png',
                    'welcome_title' => 'Hello, I am PG Genie! How can I assist you today?',
                    'welcome_subtitle' => 'Ask about Peers Global guidelines, find members by category, check upcoming events, or track your impact.',
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
            } catch (\Throwable) {}
        }

        // Seed initial documents if table is empty
        if (Schema::hasTable('ai_knowledge_documents') && AiKnowledgeDocument::query()->count() === 0) {
            $defaultDocs = [
                ['title' => 'Peers Global - Circle Meeting Preparation Guide', 'category' => 'circles', 'chunks' => 11],
                ['title' => 'Life Impact Preparation Guide', 'category' => 'referrals', 'chunks' => 4],
                ['title' => 'Peers_Global_Circle_Meeting_Preparation_Guide', 'category' => 'circles', 'chunks' => 10],
                ['title' => 'Peers Global Platform FAQ & Membership Policy', 'category' => 'faq', 'chunks' => 18],
                ['title' => 'Speed Networking Protocols & Best Practices', 'category' => 'speed_networking', 'chunks' => 8],
                ['title' => 'Coin Economy, Rewards & Wallet Redemptions', 'category' => 'general', 'chunks' => 14],
                ['title' => 'Store Merchandise Catalog Delivery & Returns Guide', 'category' => 'general', 'chunks' => 9],
                ['title' => 'Member Code of Conduct & Confidentiality Guidelines', 'category' => 'general', 'chunks' => 6],
            ];
            foreach ($defaultDocs as $d) {
                try {
                    $doc = AiKnowledgeDocument::create([
                        'title' => $d['title'],
                        'category' => $d['category'],
                        'raw_content' => "Official documentation for {$d['title']}. Rules, procedures, and member standards for Peers Global Unity.",
                        'is_active' => true,
                    ]);
                    if (Schema::hasTable('ai_knowledge_chunks')) {
                        for ($i = 0; $i < $d['chunks']; $i++) {
                            \App\Models\AiKnowledgeChunk::create([
                                'document_id' => $doc->id,
                                'chunk_text' => "Section {$i} content for {$d['title']}",
                                'embedding_json' => [],
                            ]);
                        }
                    }
                } catch (\Throwable) {}
            }
        }
    }
}
