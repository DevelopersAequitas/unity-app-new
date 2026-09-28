<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ask\AskFlow;
use App\Models\Ask\AskOption;
use App\Models\Ask\AskOptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AskSystemSeeder extends Seeder
{
    /**
     * Run the database seeds with the PEARS GLOBAL Human-Centred Communication Framework wording.
     */
    public function run(): void
    {
        // 1. Seed or update Ask Flows with Human-Centred Orientation
        $flowsData = [
            [
                'code' => 'collaboration',
                'name' => 'Find a Collaborator',
                'description' => 'Find the right peer to build and create together.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'referral',
                'name' => 'Help me make the right connection',
                'description' => 'Connect with the right peer for meaningful relationships and opportunities.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'help',
                'name' => 'Seek Guidance & Support',
                'description' => 'How can your Peers support you today? Guidance, advice, and community backing.',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        $flowModels = [];
        foreach ($flowsData as $data) {
            $flow = AskFlow::query()->firstOrNew(['code' => $data['code']]);
            if (! $flow->exists) {
                $flow->id = Str::uuid()->toString();
            }
            $flow->name = $data['name'];
            $flow->description = $data['description'];
            $flow->sort_order = $data['sort_order'];
            $flow->is_active = $data['is_active'];
            $flow->save();

            $flowModels[$data['code']] = $flow;
        }

        // 2. Seed or update Ask Option Groups with Human-Centred Wording
        $groupsData = [
            [
                'code' => 'collaboration_bring',
                'name' => 'What I can contribute',
                'description' => 'Strengths, capabilities, and value you bring to the collaboration.',
                'input_type' => 'multi_select',
                'is_multi_select' => true,
                'sort_order' => 5,
                'options' => [
                    ['code' => 'capital', 'label' => 'Capital / Investment'],
                    ['code' => 'distribution', 'label' => 'Distribution Network'],
                    ['code' => 'technology', 'label' => 'Technology / Product Expertise'],
                    ['code' => 'operations', 'label' => 'Manufacturing / Operations'],
                    ['code' => 'domain_knowledge', 'label' => 'Domain Knowledge & Experience'],
                    ['code' => 'market_access', 'label' => 'Market Access & Client Relationships'],
                ],
            ],
            [
                'code' => 'collaboration_need',
                'name' => 'Where I could use support',
                'description' => 'Areas where peer collaboration and support would accelerate your journey.',
                'input_type' => 'multi_select',
                'is_multi_select' => true,
                'sort_order' => 6,
                'options' => [
                    ['code' => 'strategic_capital', 'label' => 'Strategic Growth Capital'],
                    ['code' => 'go_to_market', 'label' => 'Go-To-Market & Sales Acceleration'],
                    ['code' => 'co_founder', 'label' => 'Co-Founder / Leadership Partner'],
                    ['code' => 'regulatory_guidance', 'label' => 'Compliance & Regulatory Guidance'],
                    ['code' => 'global_expansion', 'label' => 'International & Cross-Border Expansion'],
                    ['code' => 'tech_infrastructure', 'label' => 'Technical Architecture & Infrastructure'],
                ],
            ],
            [
                'code' => 'expected_outcome',
                'name' => 'Expected Impact',
                'description' => 'What this connection and collaboration can make possible.',
                'input_type' => 'single_select',
                'is_multi_select' => false,
                'sort_order' => 7,
                'options' => [
                    ['code' => 'joint_venture', 'label' => 'Joint Venture / Consortium'],
                    ['code' => 'strategic_partnership', 'label' => 'Strategic Long-Term Partnership'],
                    ['code' => 'knowledge_exchange', 'label' => 'Knowledge Exchange & Mentorship'],
                    ['code' => 'commercial_collaboration', 'label' => 'Commercial Deal / Co-Selling'],
                ],
            ],
            [
                'code' => 'help_topic',
                'name' => 'Support Area',
                'description' => 'What would make your journey easier today?',
                'input_type' => 'single_select',
                'is_multi_select' => false,
                'sort_order' => 8,
                'options' => [
                    ['code' => 'career_guidance', 'label' => 'Career Architecture & Strategy'],
                    ['code' => 'business_operations', 'label' => 'Business Operations & Scaling'],
                    ['code' => 'fundraising', 'label' => 'Fundraising & Investor Readiness'],
                    ['code' => 'technology_architecture', 'label' => 'Technology Architecture & Digital'],
                    ['code' => 'legal_tax', 'label' => 'Legal, Tax & Corporate Structuring'],
                ],
            ],
        ];

        foreach ($groupsData as $gData) {
            $group = AskOptionGroup::query()->firstOrNew(['code' => $gData['code']]);
            if (! $group->exists) {
                $group->id = Str::uuid()->toString();
            }
            $group->name = $gData['name'];
            $group->description = $gData['description'];
            $group->input_type = $gData['input_type'];
            $group->is_multi_select = $gData['is_multi_select'];
            $group->sort_order = $gData['sort_order'];
            $group->is_active = true;
            $group->save();

            if (! empty($gData['options'])) {
                foreach ($gData['options'] as $idx => $optData) {
                    $opt = AskOption::query()->firstOrNew([
                        'option_group_id' => $group->id,
                        'code' => $optData['code'],
                    ]);
                    if (! $opt->exists) {
                        $opt->id = Str::uuid()->toString();
                    }
                    $opt->label = $optData['label'];
                    $opt->sort_order = $idx + 1;
                    $opt->is_active = true;
                    $opt->save();
                }
            }
        }
    }
}
