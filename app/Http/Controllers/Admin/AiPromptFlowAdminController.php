<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiPromptFlowRequest;
use App\Http\Requests\Admin\UpdateAiPromptFlowRequest;
use App\Models\AiPromptFlow;
use App\Services\Ai\AiPromptFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AiPromptFlowAdminController extends Controller
{
    public function __construct(
        protected AiPromptFlowService $aiPromptFlowService
    ) {}

    /**
     * Display a listing of all AI Prompt Flows.
     */
    public function index(): View
    {
        $flows = collect();

        if (Schema::hasTable('ai_prompt_flows')) {
            $flows = AiPromptFlow::query()->orderBy('name')->get();
        }

        // If no records in database, provide the default business category flow
        if ($flows->isEmpty()) {
            $flows = collect([
                (object) [
                    'id' => 'default-category-flow',
                    'slug' => 'business_category_suggestion',
                    'name' => 'Business Category Suggestion',
                    'model' => 'gemini-2.5-flash',
                    'description' => 'Maps user company name, designation, and description to one of the 18 main business categories.',
                    'temperature' => 0.20,
                    'is_active' => true,
                    'is_virtual' => true,
                ],
            ]);
        }

        return view('admin.ai-flows.index', compact('flows'));
    }

    /**
     * Show form for creating a new AI Prompt Flow.
     */
    public function create(): View
    {
        $flow = new AiPromptFlow([
            'id' => (string) Str::uuid(),
            'slug' => '',
            'name' => '',
            'description' => '',
            'model' => 'gemini-2.5-flash',
            'system_instruction' => 'You are an intelligent AI assistant for Peers Global Unity network. Return strictly valid JSON.',
            'prompt_template' => '',
            'temperature' => 0.20,
            'is_active' => true,
        ]);

        return view('admin.ai-flows.create', compact('flow'));
    }

    /**
     * Store a newly created AI Prompt Flow in the database.
     */
    public function store(StoreAiPromptFlowRequest $request): RedirectResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return redirect()->route('admin.ai-flows.index')
                ->with('error', "Database table 'ai_prompt_flows' is missing. Please run the provided manual SQL query.");
        }

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');
        $validated['slug'] = Str::slug((string) $validated['slug'], '_');

        // Check for slug uniqueness
        if (AiPromptFlow::query()->where('slug', $validated['slug'])->exists()) {
            return back()->withInput()->withErrors(['slug' => "An AI Flow with slug '{$validated['slug']}' already exists."]);
        }

        $flow = new AiPromptFlow($validated);
        $flow->id = (string) Str::uuid();
        $flow->save();

        return redirect()->route('admin.ai-flows.index')
            ->with('success', "AI prompt flow '{$flow->name}' created successfully. You can now execute it via POST /api/v1/ai/execute/{$flow->slug}");
    }

    /**
     * Show form for editing an AI Prompt Flow.
     */
    public function edit(string $id): View
    {
        $flow = null;

        if (Schema::hasTable('ai_prompt_flows')) {
            $flow = AiPromptFlow::query()->where('id', $id)->orWhere('slug', $id)->first();
        }

        if (! $flow) {
            $flow = new AiPromptFlow([
                'id' => (string) Str::uuid(),
                'slug' => 'business_category_suggestion',
                'name' => 'Business Category Suggestion',
                'description' => 'Classifies users into 1 of the 18 main business categories using Google Gemini AI.',
                'model' => 'gemini-2.5-flash',
                'system_instruction' => 'You are an intelligent business classification AI for Peers Global Unity network. Your task is to accurately map a business or professional to the single best matching main business category from the official 18 categories. Always return strictly valid JSON matching the specified schema.',
                'prompt_template' => <<<'PROMPT'
Analyze the following professional and company details:
- Company Name: {{company_name}}
- Designation / Title: {{designation}}
- Business Description / Bio: {{description}}
- Industry / Keywords: {{industry}}

Allowed 18 Official Main Business Categories:
{{categories_list}}

Instructions:
1. Carefully evaluate the user's company name, job designation, business description, and industry keywords.
2. Select the single best-fitting Main Category strictly from the Allowed 18 Categories list above.
3. Use the exact ID number and exact Name from the list.
4. Provide a confidence score between 0.0 and 1.0.
5. Provide a brief 1-sentence reasoning explaining why this category best fits their business profile.

Respond strictly in the following JSON structure:
{
  "main_category_id": <exact number from allowed list>,
  "main_category_name": "<exact name from allowed list>",
  "confidence": <number between 0.0 and 1.0>,
  "reasoning": "<short explanation>"
}
PROMPT,
                'temperature' => 0.20,
                'max_tokens' => 1024,
                'is_active' => true,
            ]);
        }

        return view('admin.ai-flows.edit', compact('flow'));
    }

    /**
     * Update an AI Prompt Flow.
     */
    public function update(UpdateAiPromptFlowRequest $request, string $id): RedirectResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return redirect()->route('admin.ai-flows.index')
                ->with('error', "Database table 'ai_prompt_flows' is missing. Please run the provided SQL query.");
        }

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');
        $validated['slug'] = Str::slug((string) $validated['slug'], '_');

        // Check if slug taken by another flow
        $existing = AiPromptFlow::query()
            ->where('slug', $validated['slug'])
            ->where('id', '!=', $id)
            ->first();

        if ($existing) {
            return back()->withInput()->withErrors(['slug' => "The slug '{$validated['slug']}' is already used by another flow."]);
        }

        $flow = AiPromptFlow::query()->where('id', $id)->orWhere('slug', $id)->first();
        if (! $flow) {
            $flow = new AiPromptFlow;
            $flow->id = (string) Str::uuid();
        }

        $flow->fill($validated);
        $flow->save();

        return redirect()->route('admin.ai-flows.index')
            ->with('success', "AI prompt flow '{$validated['name']}' saved successfully.");
    }

    /**
     * Delete an AI Prompt Flow.
     */
    public function destroy(string $id): RedirectResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return redirect()->route('admin.ai-flows.index')
                ->with('error', "Database table 'ai_prompt_flows' is missing.");
        }

        $flow = AiPromptFlow::query()->where('id', $id)->orWhere('slug', $id)->first();
        if (! $flow) {
            return redirect()->route('admin.ai-flows.index')
                ->with('error', 'AI prompt flow not found.');
        }

        $flowName = $flow->name;
        $flow->delete();

        return redirect()->route('admin.ai-flows.index')
            ->with('success', "AI prompt flow '{$flowName}' deleted successfully.");
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
            'variables' => ['nullable', 'array'],
        ]);

        try {
            $slug = (string) $request->input('slug', 'business_category_suggestion');

            if ($slug === 'business_category_suggestion') {
                $result = $this->aiPromptFlowService->suggestCategory([
                    'company_name' => $request->input('company_name', 'SolarTech Clean Energy Pvt Ltd'),
                    'designation' => $request->input('designation', 'Managing Director'),
                    'description' => $request->input('description', 'Manufacturing solar inverters and battery storage modules for residential and commercial projects.'),
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
