<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AiCategorySuggestionRequest;
use App\Models\AiPromptFlow;
use App\Models\User;
use App\Services\Ai\AiChatbotService;
use App\Services\Ai\AiPromptFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AiController extends Controller
{
    public function __construct(
        protected AiPromptFlowService $aiPromptFlowService,
        protected AiChatbotService $aiChatbotService
    ) {}

    /**
     * Suggest the best matching main business category & subcategory.
     */
    public function suggestCategory(AiCategorySuggestionRequest $request): JsonResponse
    {
        try {
            $result = $this->aiPromptFlowService->suggestCategory($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Category suggestion generated successfully.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Execute any generic AI prompt flow dynamically.
     */
    public function executeFlow(string $slug, Request $request): JsonResponse
    {
        try {
            if ($slug === 'peers_chatbot') {
                /** @var User|null $user */
                $user = $request->user('sanctum') ?? $request->user();
                $text = trim((string) ($request->input('message') ?: $request->input('prompt', '')));
                $result = $this->aiChatbotService->sendMessage($user, $text, $request->input('session_id'));

                return response()->json([
                    'success' => true,
                    'session_id' => $result['session_id'],
                    'message' => $result['message'],
                    'data' => $result['message'],
                ]);
            }

            $variables = $request->all();
            $result = $this->aiPromptFlowService->execute($slug, $variables);

            return response()->json([
                'success' => true,
                'message' => "AI flow '{$slug}' executed successfully.",
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * List AI prompt flows (for Admin configuration).
     */
    public function flows(): JsonResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return response()->json([
                'success' => true,
                'message' => 'AI prompt flows loaded (default memory list).',
                'data' => [
                    [
                        'id' => 'a0000000-0000-0000-0000-000000000001',
                        'slug' => 'business_category_suggestion',
                        'name' => 'Business Category Suggestion',
                        'description' => 'Classifies user company name, designation, and bio into one of the 18 main business categories using Gemini.',
                        'model' => 'gemini-2.5-flash',
                        'temperature' => 0.20,
                        'is_active' => true,
                    ],
                ],
            ]);
        }

        $flows = AiPromptFlow::query()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'AI prompt flows retrieved successfully.',
            'data' => $flows,
        ]);
    }

    /**
     * Get a single AI prompt flow by ID or Slug.
     */
    public function getFlow(string $idOrSlug): JsonResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return response()->json([
                'success' => false,
                'message' => "The 'ai_prompt_flows' database table has not been created yet.",
                'data' => null,
            ], 404);
        }

        $flow = AiPromptFlow::query()
            ->where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->first();

        if (! $flow) {
            return response()->json([
                'success' => false,
                'message' => "AI prompt flow '{$idOrSlug}' not found.",
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'AI prompt flow retrieved successfully.',
            'data' => $flow,
        ]);
    }

    /**
     * Create a new AI prompt flow.
     */
    public function createFlow(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return response()->json([
                'success' => false,
                'message' => "The 'ai_prompt_flows' database table has not been created yet.",
                'data' => null,
            ], 500);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:100'],
            'system_instruction' => ['nullable', 'string'],
            'prompt_template' => ['required', 'string'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $cleanSlug = Str::slug((string) $validated['slug'], '_');

        if (AiPromptFlow::query()->where('slug', $cleanSlug)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "An AI prompt flow with slug '{$cleanSlug}' already exists.",
            ], 400);
        }

        $validated['slug'] = $cleanSlug;
        $validated['id'] = (string) Str::uuid();
        $validated['model'] = $validated['model'] ?? 'gemini-2.5-flash';
        $validated['temperature'] = isset($validated['temperature']) ? (float) $validated['temperature'] : 0.20;
        $validated['is_active'] = $request->boolean('is_active', true);

        $flow = AiPromptFlow::create($validated);

        return response()->json([
            'success' => true,
            'message' => "AI prompt flow '{$flow->name}' created successfully.",
            'data' => $flow,
        ], 201);
    }

    /**
     * Update an AI prompt flow by ID or Slug.
     */
    public function updateFlow(string $idOrSlug, Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return response()->json([
                'success' => false,
                'message' => "The 'ai_prompt_flows' database table has not been created yet.",
                'data' => null,
            ], 500);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:100'],
            'system_instruction' => ['nullable', 'string'],
            'prompt_template' => ['required', 'string'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $flow = AiPromptFlow::query()
            ->where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->first();

        if (! $flow) {
            // If doesn't exist, create it with $idOrSlug as slug
            $flow = new AiPromptFlow([
                'id' => (string) Str::uuid(),
                'slug' => Str::slug($idOrSlug, '_'),
            ]);
        }

        $flow->fill(array_merge($validated, [
            'name' => $validated['name'] ?? $flow->name ?? ucwords(str_replace('_', ' ', $idOrSlug)),
            'model' => $validated['model'] ?? $flow->model ?? 'gemini-2.5-flash',
            'temperature' => isset($validated['temperature']) ? (float) $validated['temperature'] : ($flow->temperature ?? 0.20),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : ($flow->is_active ?? true),
        ]));

        $flow->save();

        return response()->json([
            'success' => true,
            'message' => "AI prompt flow '{$flow->name}' updated successfully.",
            'data' => $flow,
        ]);
    }

    /**
     * Delete an AI prompt flow by ID or Slug.
     */
    public function deleteFlow(string $idOrSlug): JsonResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return response()->json([
                'success' => false,
                'message' => "The 'ai_prompt_flows' database table has not been created yet.",
            ], 500);
        }

        $flow = AiPromptFlow::query()
            ->where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->first();

        if (! $flow) {
            return response()->json([
                'success' => false,
                'message' => 'AI prompt flow not found.',
            ], 404);
        }

        if ($flow->slug === 'business_category_suggestion') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system core flow.',
            ], 400);
        }

        $name = $flow->name;
        $flow->delete();

        return response()->json([
            'success' => true,
            'message' => "AI prompt flow '{$name}' deleted successfully.",
        ]);
    }

    /**
     * Test a prompt live with Gemini.
     */
    public function testPrompt(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string'],
            'designation' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'industry' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        try {
            $slug = (string) $request->input('slug', 'business_category_suggestion');

            if ($slug === 'business_category_suggestion') {
                $result = $this->aiPromptFlowService->suggestCategory([
                    'company_name' => $request->input('company_name', 'SolarTech Clean Energy Pvt Ltd'),
                    'designation' => $request->input('designation', 'Managing Director'),
                    'description' => $request->input('description', 'Manufacturing solar inverters and battery storage modules for residential and commercial projects.'),
                    'industry' => $request->input('industry', 'Renewable Energy & Solar'),
                ]);
            } else {
                $variables = (array) $request->input('variables', $request->all());
                $result = $this->aiPromptFlowService->execute($slug, $variables);
            }

            return response()->json([
                'success' => true,
                'message' => 'Test executed successfully.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }
}
