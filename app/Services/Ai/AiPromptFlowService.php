<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiPromptFlow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AiPromptFlowService
{
    /**
     * The official 18 Circle Categories with their exact database IDs.
     *
     * @var array<int, array{id: int, name: string, slug: string}>
     */
    public const OFFICIAL_18_CATEGORIES = [
        ['id' => 1, 'name' => 'Manufacturing & Engineering', 'slug' => 'manufacturing-engineering'],
        ['id' => 2, 'name' => 'Real Estate, Construction & Infrastructure', 'slug' => 'real-estate-construction-infrastructure'],
        ['id' => 3, 'name' => 'Technology, IT & Digital Services', 'slug' => 'technology-it-digital'],
        ['id' => 4, 'name' => 'Healthcare, Wellness & Life Sciences', 'slug' => 'healthcare-wellness'],
        ['id' => 5, 'name' => 'Education, Training & Skill Development', 'slug' => 'education-training'],
        ['id' => 6, 'name' => 'Events, Fashion, Apparel & Lifestyle', 'slug' => 'events-fashion'],
        ['id' => 7, 'name' => 'CSR, NGOs, Impact & Nation-Building', 'slug' => 'csr-ngos'],
        ['id' => 18, 'name' => 'Franchise & Licensing', 'slug' => 'franchise-licensing'],
        ['id' => 8, 'name' => 'Sustainable & ESG Business', 'slug' => 'sustainable-esg'],
        ['id' => 9, 'name' => 'Import, Export & Global Trade', 'slug' => 'import-export'],
        ['id' => 10, 'name' => 'Startup Founders', 'slug' => 'startup-founders'],
        ['id' => 11, 'name' => 'SME IPO Goal', 'slug' => 'sme-ipo'],
        ['id' => 12, 'name' => 'Investors', 'slug' => 'investors'],
        ['id' => 13, 'name' => 'Global Expansion (Cross-Border)', 'slug' => 'global-expansion'],
        ['id' => 14, 'name' => 'MSME Entrepreneurs', 'slug' => 'msme-entrepreneurs'],
        ['id' => 15, 'name' => 'Family Business', 'slug' => 'family-business'],
        ['id' => 16, 'name' => 'Young Entrepreneurs (Below 35)', 'slug' => 'young-entrepreneurs'],
        ['id' => 17, 'name' => 'Leadership & Transformation', 'slug' => 'leadership-transformation'],
    ];

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
     * Suggest the best matching main category based on professional profile details.
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     suggested_main_category: array{id: int|null, name: string|null},
     *     confidence: float,
     *     reasoning: string
     * }
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

        $mainCatId = isset($aiResult['main_category_id']) ? (int) $aiResult['main_category_id'] : null;
        $mainCatName = isset($aiResult['main_category_name']) ? (string) $aiResult['main_category_name'] : null;

        // If main_category_id is missing or not numeric, attempt to resolve from name
        if ($mainCatId === null && filled($mainCatName)) {
            $matchedMain = collect(self::OFFICIAL_18_CATEGORIES)->first(function ($c) use ($mainCatName) {
                return strtolower($c['name']) === strtolower(trim((string) $mainCatName))
                    || str_contains(strtolower($c['name']), strtolower(trim((string) $mainCatName)))
                    || str_contains(strtolower(trim((string) $mainCatName)), strtolower($c['name']));
            });

            if ($matchedMain) {
                $mainCatId = (int) $matchedMain['id'];
                $mainCatName = (string) $matchedMain['name'];
            }
        }

        return [
            'suggested_main_category' => [
                'id' => $mainCatId,
                'name' => $mainCatName,
            ],
            'confidence' => (float) ($aiResult['confidence'] ?? 0.85),
            'reasoning' => (string) ($aiResult['reasoning'] ?? ''),
        ];
    }

    /**
     * Fetch active main categories (level 1) formatted for the prompt.
     */
    public function getActiveMainCategoriesFormatted(): string
    {
        $categories = collect();

        if (Schema::hasTable('circle_categories')) {
            try {
                $categories = DB::table('circle_categories')
                    ->select(['id', 'name', 'sort_order'])
                    ->where(function ($q): void {
                        $q->where('level', 1)->orWhereNull('parent_id');
                    })
                    ->when(Schema::hasColumn('circle_categories', 'is_active'), function ($q): void {
                        $q->where('is_active', true)->orWhereNull('is_active');
                    })
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
            } catch (Throwable) {
                // Ignore DB error and fallback to official list
            }
        }

        if ($categories->isNotEmpty()) {
            return $categories->map(function ($cat): string {
                $cleanName = trim(preg_replace('/\s+Circles?\b/i', '', (string) $cat->name));

                return "- ID {$cat->id}: {$cleanName}";
            })->implode("\n");
        }

        // Exact fallback of the 18 official circle categories with their real IDs
        return collect(self::OFFICIAL_18_CATEGORIES)->map(function ($cat): string {
            return "- ID {$cat['id']}: {$cat['name']}";
        })->implode("\n");
    }

    /**
     * Get level 4 subcategories for a given main category ID.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function getSubcategoriesForMainCategory(int $mainCategoryId): Collection
    {
        $table = null;
        if (Schema::hasTable('level4_categories')) {
            $table = 'level4_categories';
        } elseif (Schema::hasTable('circle_category_level4')) {
            $table = 'circle_category_level4';
        }

        if ($table !== null) {
            try {
                $query = DB::table($table)->where('circle_category_id', $mainCategoryId);

                if (Schema::hasColumn($table, 'is_active')) {
                    $query->where(function ($q): void {
                        $q->where('is_active', true)->orWhereNull('is_active');
                    });
                }

                if (Schema::hasColumn($table, 'sort_order')) {
                    $query->orderBy('sort_order', 'asc');
                }

                $results = $query->orderBy('id', 'asc')
                    ->get(['id', 'name'])
                    ->map(fn ($r): array => [
                        'id' => (int) $r->id,
                        'name' => (string) $r->name,
                    ]);

                if ($results->isNotEmpty()) {
                    return $results;
                }
            } catch (Throwable) {
                // fallback
            }
        }

        // Built-in catalog fallback for ID 2 (Real Estate, Construction & Infrastructure)
        if ($mainCategoryId === 2) {
            return collect([
                ['id' => 4592, 'name' => 'Residential Developer (Mid-Segment)'],
                ['id' => 4593, 'name' => 'Luxury Residential Developer'],
                ['id' => 4594, 'name' => 'Affordable Housing Developer'],
                ['id' => 4595, 'name' => 'Commercial Real Estate Developer'],
                ['id' => 4596, 'name' => 'Mixed-use Developer'],
                ['id' => 4597, 'name' => 'Township Developer'],
                ['id' => 4598, 'name' => 'Plotting & Land Development'],
                ['id' => 4599, 'name' => 'Redevelopment Developer'],
                ['id' => 4600, 'name' => 'Industrial Park, Estate & SEZ Developer'],
                ['id' => 4601, 'name' => 'Warehousing & Logistics Park Developer'],
                ['id' => 4602, 'name' => 'Hospitality & Resort Developer'],
                ['id' => 4603, 'name' => 'Senior Living Developer'],
                ['id' => 4604, 'name' => 'Residential Property Broker'],
                ['id' => 4605, 'name' => 'Commercial Property Broker (Sale & Leasing)'],
                ['id' => 4606, 'name' => 'Industrial & Warehouse Property Consultant'],
                ['id' => 4607, 'name' => 'Land Aggregator & Land Broker'],
                ['id' => 4608, 'name' => 'Society Redevelopment Consultant'],
                ['id' => 4609, 'name' => 'Co-living & Student Housing Operator'],
                ['id' => 4610, 'name' => 'Co-working & Serviced Office Operator'],
                ['id' => 4611, 'name' => 'Turnkey & EPC Project Contractor'],
                ['id' => 4612, 'name' => 'Individual House & Villa Construction'],
                ['id' => 4613, 'name' => 'Apartment & High-Rise Construction'],
                ['id' => 4614, 'name' => 'Renovation & Remodelling Contractor'],
                ['id' => 4615, 'name' => 'Heritage & Building Restoration'],
                ['id' => 4616, 'name' => 'Office & Retail Building Construction'],
                ['id' => 4617, 'name' => 'Hotel & Hospitality Construction'],
                ['id' => 4618, 'name' => 'Hospital & Healthcare Facility Construction'],
                ['id' => 4619, 'name' => 'School, University & Institutional Construction'],
                ['id' => 4620, 'name' => 'Convention, Exhibition & Sports Facility Construction'],
                ['id' => 4621, 'name' => 'Factory & Manufacturing Plant Construction'],
            ]);
        }

        return collect();
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
                    'model' => (string) ($dbFlow->model ?: 'gemini-2.5-flash'),
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
