<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiPromptFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AiPromptFlowService
{
    public function __construct(
        protected GeminiService $geminiService
    ) {}

    /**
     * Default fallback prompt flows when not defined or customized in the database.
     *
     * @var array<string, array{name: string, model: string, system_instruction: string, prompt_template: string, temperature: float}>
     */
    protected array $defaultFlows = [
        'business_category_suggestion' => [
            'name' => 'Business Category Suggestion',
            'model' => 'gemini-1.5-flash',
            'system_instruction' => 'You are an intelligent business classification AI for Peers Global Unity network. Your task is to accurately map a business or professional to the most relevant main business category and subcategory from the provided allowed category list. Always return valid JSON matching the specified schema.',
            'prompt_template' => <<<'PROMPT'
Analyze the following professional and company details:
- Company Name: {{company_name}}
- Designation / Title: {{designation}}
- Business Description / Bio: {{description}}
- Industry / Keywords: {{industry}}

Allowed Main Business Categories:
{{categories_list}}

Instructions:
1. First, select the single best matching Main Category from the Allowed Categories list above.
2. If the user fits an existing subcategory or specific domain, suggest a concise subcategory name.
3. Provide a confidence score between 0.0 and 1.0.
4. Provide a brief 1-sentence reasoning.

Respond strictly in the following JSON structure:
{
  "main_category_id": <number or null if no match>,
  "main_category_name": "<exact name from the allowed list>",
  "sub_category_id": <number or null>,
  "sub_category_name": "<suggested subcategory name>",
  "confidence": <number between 0.0 and 1.0>,
  "reasoning": "<short explanation>"
}
PROMPT,
            'temperature' => 0.2,
        ],
    ];

    /**
     * Execute a prompt flow by slug with the provided variable replacements.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function execute(string $slug, array $variables = []): array
    {
        $flowConfig = $this->resolveFlowConfig($slug);

        $prompt = $flowConfig['prompt_template'];
        foreach ($variables as $key => $value) {
            $stringValue = is_array($value) ? json_encode($value) : (string) $value;
            $prompt = str_replace('{{'.$key.'}}', $stringValue, $prompt);
        }

        $rawResponse = $this->geminiService->generateContent(
            prompt: $prompt,
            systemInstruction: $flowConfig['system_instruction'] ?? null,
            model: $flowConfig['model'] ?? null,
            temperature: (float) ($flowConfig['temperature'] ?? 0.2),
            jsonMode: true
        );

        $decoded = json_decode($rawResponse, true);
        if (! is_array($decoded)) {
            Log::warning('AiPromptFlowService: failed to decode JSON response from Gemini', [
                'raw' => $rawResponse,
            ]);

            throw new RuntimeException('Gemini did not return valid JSON for flow: '.$slug);
        }

        return $decoded;
    }

    /**
     * Suggest the best matching main and sub category based on professional profile details.
     *
     * @param  array<string, mixed>  $input
     * @return array{suggested_main_category: array{id: int|null, name: string|null}, suggested_sub_category: array{id: int|null, name: string|null}, confidence: float, reasoning: string}
     */
    public function suggestCategory(array $input): array
    {
        $categoriesList = $this->getActiveMainCategoriesFormatted();

        $variables = [
            'company_name' => $input['company_name'] ?? 'Not specified',
            'designation' => $input['designation'] ?? 'Not specified',
            'description' => $input['description'] ?? ($input['business_description'] ?? 'Not specified'),
            'industry' => $input['industry'] ?? ($input['linkedin_headline'] ?? 'Not specified'),
            'categories_list' => $categoriesList,
        ];

        $aiResult = $this->execute('business_category_suggestion', $variables);

        return [
            'suggested_main_category' => [
                'id' => isset($aiResult['main_category_id']) ? (int) $aiResult['main_category_id'] : null,
                'name' => isset($aiResult['main_category_name']) ? (string) $aiResult['main_category_name'] : null,
            ],
            'suggested_sub_category' => [
                'id' => isset($aiResult['sub_category_id']) ? (int) $aiResult['sub_category_id'] : null,
                'name' => isset($aiResult['sub_category_name']) ? (string) $aiResult['sub_category_name'] : null,
            ],
            'confidence' => (float) ($aiResult['confidence'] ?? 0.8),
            'reasoning' => (string) ($aiResult['reasoning'] ?? ''),
        ];
    }

    /**
     * Fetch active main categories (level 1) formatted for the prompt.
     */
    protected function getActiveMainCategoriesFormatted(): string
    {
        if (Schema::hasTable('circle_categories')) {
            $categories = DB::table('circle_categories')
                ->select(['id', 'name'])
                ->where(function ($q): void {
                    $q->where('level', 1)->orWhereNull('parent_id');
                })
                ->when(Schema::hasColumn('circle_categories', 'is_active'), function ($q): void {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($categories->isNotEmpty()) {
                return $categories->map(function ($cat): string {
                    $cleanName = trim(preg_replace('/\s+Circles?\b/i', '', (string) $cat->name));

                    return "- ID {$cat->id}: {$cleanName}";
                })->implode("\n");
            }
        }

        // Fallback default 18 categories if database table is not loaded
        return <<<'LIST'
- ID 1: Agriculture & Farming
- ID 2: Automotive & Transportation
- ID 3: Construction & Real Estate
- ID 4: Education & Training
- ID 5: Energy, Environment & Sustainability
- ID 6: Financial Services & Banking
- ID 7: Food, Beverages & Hospitality
- ID 8: Healthcare & Pharmaceuticals
- ID 9: Industrial Machinery & Manufacturing
- ID 10: Information Technology & Software
- ID 11: Legal & Professional Services
- ID 12: Marketing, Advertising & PR
- ID 13: Media & Entertainment
- ID 14: Non-Profit & Social Enterprises
- ID 15: Retail & E-Commerce
- ID 16: Telecommunications
- ID 17: Textiles & Fashion
- ID 18: Travel, Tourism & Logistics
LIST;
    }

    /**
     * Resolve flow configuration from database if exists, or fallback to default configuration.
     *
     * @return array{name: string, model: string, system_instruction: ?string, prompt_template: string, temperature: float}
     */
    protected function resolveFlowConfig(string $slug): array
    {
        if (Schema::hasTable('ai_prompt_flows')) {
            /** @var AiPromptFlow|null $dbFlow */
            $dbFlow = AiPromptFlow::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if ($dbFlow) {
                return [
                    'name' => (string) $dbFlow->name,
                    'model' => (string) ($dbFlow->model ?: 'gemini-1.5-flash'),
                    'system_instruction' => $dbFlow->system_instruction,
                    'prompt_template' => (string) $dbFlow->prompt_template,
                    'temperature' => (float) ($dbFlow->temperature ?? 0.2),
                ];
            }
        }

        if (isset($this->defaultFlows[$slug])) {
            return $this->defaultFlows[$slug];
        }

        throw new RuntimeException("AI Prompt Flow '{$slug}' is not configured.");
    }
}
