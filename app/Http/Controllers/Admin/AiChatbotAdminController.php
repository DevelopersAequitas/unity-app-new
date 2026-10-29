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
    public function index(): View
    {
        $settings = $this->chatbotService->getSettings();
        $documents = collect();

        if (Schema::hasTable('ai_knowledge_documents')) {
            $documents = AiKnowledgeDocument::query()
                ->withCount('chunks')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('admin.ai-chatbot.index', compact('settings', 'documents'));
    }

    /**
     * Update Peers AI Chatbot configuration in ai_chatbot_settings.
     */
    public function updateSettings(UpdateAiChatbotSettingRequest $request): RedirectResponse
    {
        if (! Schema::hasTable('ai_chatbot_settings')) {
            return redirect()->route('admin.ai-chatbot.index')
                ->with('error', "Database table 'ai_chatbot_settings' is missing. Please run migrations.");
        }

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

        return redirect()->route('admin.ai-chatbot.index')
            ->with('success', 'Peers AI Chatbot configuration saved successfully.');
    }

    /**
     * Store a new RAG knowledge document and generate chunks + embeddings.
     */
    public function storeDocument(StoreAiKnowledgeDocumentRequest $request): RedirectResponse
    {
        if (! Schema::hasTable('ai_knowledge_documents')) {
            return redirect()->route('admin.ai-chatbot.index')
                ->with('error', "Database table 'ai_knowledge_documents' is missing. Please run migrations.");
        }

        $validated = $request->validated();

        $document = AiKnowledgeDocument::create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'raw_content' => $validated['raw_content'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $chunkCount = $this->ragService->syncDocumentChunks($document);

        return redirect()->route('admin.ai-chatbot.index')
            ->with('success', "Knowledge document '{$document->title}' uploaded and indexed into {$chunkCount} searchable chunks.");
    }

    /**
     * Toggle active status of a RAG knowledge document.
     */
    public function toggleDocument(string $id): RedirectResponse
    {
        $doc = AiKnowledgeDocument::query()->findOrFail($id);
        $doc->is_active = ! $doc->is_active;
        $doc->save();

        return redirect()->route('admin.ai-chatbot.index')
            ->with('success', "Document '{$doc->title}' status updated.");
    }

    /**
     * Delete a RAG knowledge document and its chunks.
     */
    public function destroyDocument(string $id): RedirectResponse
    {
        $doc = AiKnowledgeDocument::query()->findOrFail($id);
        $title = $doc->title;
        $doc->delete();

        return redirect()->route('admin.ai-chatbot.index')
            ->with('success', "Document '{$title}' deleted.");
    }
}

