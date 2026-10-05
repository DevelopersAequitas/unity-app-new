<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ask\AskFlow;
use App\Models\Ask\AskOption;
use App\Models\Ask\AskOptionGroup;
use App\Models\Ask\AskType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AskSystemSeeder extends Seeder
{
    /**
     * Run the database seeds with the PEARS GLOBAL Human-Centred Communication Framework wording.
     */
    public function run(): void
    {
        // 1. Seed or update Ask Flows
        $flowsData = [
            [
                'code' => 'collaboration',
                'name' => 'Find a Collaborator',
                'description' => 'Find the right peer for collaboration.',
                'sort_order' => 1,
                'is_active' => true,
                'types' => [
                    ['code' => 'joint_venture', 'name' => 'Joint Venture'],
                    ['code' => 'co_founder', 'name' => 'Co-Founder'],
                    ['code' => 'strategic_partner', 'name' => 'Strategic Partner'],
                    ['code' => 'technology_partner', 'name' => 'Technology Partner'],
                    ['code' => 'manufacturing_partner', 'name' => 'Manufacturing Partner'],
                    ['code' => 'distribution_partner', 'name' => 'Distribution Partner'],
                    ['code' => 'vendor_service_partner', 'name' => 'Vendor / Service Partner'],
                    ['code' => 'channel_partner', 'name' => 'Channel Partner'],
                    ['code' => 'marketing_partner', 'name' => 'Marketing Partner'],
                    ['code' => 'implementation_partner', 'name' => 'Implementation Partner'],
                    ['code' => 'event_partner', 'name' => 'Event Partner'],
                    ['code' => 'cross_border_partner', 'name' => 'Cross-Border Partner'],
                    ['code' => 'other', 'name' => 'Other'],
                ],
            ],
            [
                'code' => 'referral',
                'name' => 'Ask for a Referral',
                'description' => 'Ask peers for a relevant introduction.',
                'sort_order' => 2,
                'is_active' => true,
                'types' => [
                    ['code' => 'customer', 'name' => 'Customer'],
                    ['code' => 'client', 'name' => 'Client'],
                    ['code' => 'dealer', 'name' => 'Dealer'],
                    ['code' => 'distributor', 'name' => 'Distributor'],
                    ['code' => 'buyer', 'name' => 'Buyer'],
                    ['code' => 'supplier', 'name' => 'Supplier'],
                    ['code' => 'strategic_partner', 'name' => 'Strategic Partner'],
                    ['code' => 'investor', 'name' => 'Investor'],
                    ['code' => 'international_contact', 'name' => 'International Contact'],
                    ['code' => 'media_pr_contact', 'name' => 'Media / PR Contact'],
                    ['code' => 'industry_expert', 'name' => 'Industry Expert'],
                    ['code' => 'other', 'name' => 'Other'],
                ],
            ],
            [
                'code' => 'help',
                'name' => 'Get Help',
                'description' => 'Ask peers for advice, mentorship, tasks, introductions and support.',
                'sort_order' => 3,
                'is_active' => true,
                'types' => [
                    ['code' => 'founder_mentorship', 'name' => 'Founder mentorship'],
                    ['code' => 'advice', 'name' => 'Advice'],
                    ['code' => 'industry_mentorship', 'name' => 'Industry mentorship'],
                    ['code' => 'mentorship', 'name' => 'Mentorship'],
                    ['code' => 'small_task', 'name' => 'Small Task'],
                    ['code' => 'expert_guidance', 'name' => 'Expert guidance'],
                    ['code' => 'introduction', 'name' => 'Introduction'],
                    ['code' => 'accountability', 'name' => 'Accountability'],
                    ['code' => 'capital_support', 'name' => 'Capital Support'],
                    ['code' => 'emotional_peer_support', 'name' => 'Emotional / Peer Support'],
                    ['code' => 'review_feedback', 'name' => 'Review / Feedback'],
                    ['code' => 'other', 'name' => 'Other'],
                ],
            ],
        ];

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

            if (! empty($data['types'])) {
                foreach ($data['types'] as $tIdx => $tData) {
                    $type = AskType::query()->firstOrNew([
                        'flow_id' => $flow->id,
                        'code' => $tData['code'],
                    ]);
                    if (! $type->exists) {
                        $type->id = Str::uuid()->toString();
                    }
                    $type->name = $tData['name'];
                    $type->sort_order = $tIdx + 1;
                    $type->is_active = true;
                    $type->save();
                }
            }
        }

        // Shared Image 3 12 pills for What I Bring and What I Need
        $bringNeedPills = [
            ['code' => 'customers', 'label' => 'Customers'],
            ['code' => 'capital', 'label' => 'Capital'],
            ['code' => 'technology', 'label' => 'Technology'],
            ['code' => 'manufacturing', 'label' => 'Manufacturing'],
            ['code' => 'distribution', 'label' => 'Distribution'],
            ['code' => 'expertise', 'label' => 'Expertise'],
            ['code' => 'brand', 'label' => 'Brand'],
            ['code' => 'network', 'label' => 'Network'],
            ['code' => 'market_access', 'label' => 'Market access'],
            ['code' => 'geography', 'label' => 'Geography'],
            ['code' => 'ip', 'label' => 'IP'],
            ['code' => 'other', 'label' => 'Other'],
        ];

        // 2. Seed or update Ask Option Groups
        $groupsData = [
            [
                'code' => 'collaboration_bring',
                'name' => 'What I Bring',
                'description' => 'Strengths, capabilities, and assets you contribute to the collaboration.',
                'input_type' => 'multi_select',
                'is_multi_select' => true,
                'sort_order' => 1,
                'metadata' => ['flows' => ['collaboration']],
                'options' => $bringNeedPills,
            ],
            [
                'code' => 'collaboration_need',
                'name' => 'What I Need',
                'description' => 'What you are seeking from your collaborator partner.',
                'input_type' => 'multi_select',
                'is_multi_select' => true,
                'sort_order' => 2,
                'metadata' => ['flows' => ['collaboration']],
                'options' => $bringNeedPills,
            ],
            [
                'code' => 'industry',
                'name' => 'Industry',
                'description' => 'Industry focus area.',
                'input_type' => 'single_select',
                'is_multi_select' => false,
                'sort_order' => 3,
                'metadata' => ['flows' => ['collaboration', 'help']],
                'options' => [
                    ['code' => 'food', 'label' => 'Food'],
                    ['code' => 'manufacturing', 'label' => 'Manufacturing'],
                    ['code' => 'retail', 'label' => 'Retail'],
                    ['code' => 'tech', 'label' => 'Tech'],
                    ['code' => 'services', 'label' => 'Services'],
                ],
            ],
            [
                'code' => 'geography',
                'name' => 'Geography',
                'description' => 'Geographic focus or reach.',
                'input_type' => 'single_select',
                'is_multi_select' => false,
                'sort_order' => 4,
                'metadata' => ['flows' => ['collaboration', 'help']],
                'options' => [
                    ['code' => 'my_city', 'label' => 'My city'],
                    ['code' => 'india', 'label' => 'India'],
                    ['code' => 'international', 'label' => 'International'],
                ],
            ],
            [
                'code' => 'business_stage',
                'name' => 'Business Stage',
                'description' => 'Current lifecycle stage of the business.',
                'input_type' => 'single_select',
                'is_multi_select' => false,
                'sort_order' => 5,
                'metadata' => ['flows' => ['collaboration']],
                'options' => [
                    ['code' => 'start', 'label' => 'Start'],
                    ['code' => 'grow', 'label' => 'Grow'],
                    ['code' => 'scale', 'label' => 'Scale'],
                    ['code' => 'pre_ipo', 'label' => 'Pre-IPO'],
                    ['code' => 'public', 'label' => 'Public'],
                ],
            ],
            [
                'code' => 'timeline',
                'name' => 'Timeline',
                'description' => 'Urgency and preferred timeline.',
                'input_type' => 'single_select',
                'is_multi_select' => false,
                'sort_order' => 6,
                'metadata' => ['flows' => ['collaboration']],
                'options' => [
                    ['code' => 'immediate', 'label' => 'Immediate (Within 7 days)'],
                    ['code' => 'one_month', 'label' => 'Within 30 days'],
                    ['code' => 'flexible', 'label' => 'Flexible'],
                ],
            ],
            [
                'code' => 'expected_outcome',
                'name' => 'Expected Impact',
                'description' => 'What this connection and collaboration can make possible.',
                'input_type' => 'single_select',
                'is_multi_select' => false,
                'sort_order' => 7,
                'metadata' => ['flows' => ['collaboration']],
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
                'metadata' => ['flows' => ['help']],
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
            $group->metadata = $gData['metadata'] ?? null;
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
