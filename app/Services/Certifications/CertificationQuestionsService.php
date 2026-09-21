<?php

declare(strict_types=1);

namespace App\Services\Certifications;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CertificationQuestionsService
{
    /**
     * @return array<string, mixed>
     */
    public function getLeadershipCertificationQuestions(): array
    {
        $questions = $this->getQuestionsFromDatabase('leadership');

        if ($questions === null) {
            $questions = [
                [
                    'id' => 1,
                    'key' => 'team_struggling_action',
                    'question' => 'When a team member is struggling with a task, what do you usually do?',
                    'options' => [
                        'Offer help and ask what’s stopping them',
                        'Take over the task and complete it myself',
                        'Wait until the deadline to address the issue',
                        'Assign the task to someone else without discussion',
                    ],
                ],
                [
                    'id' => 2,
                    'key' => 'leader_definition',
                    'question' => 'In your view, what defines a true leader?',
                    'options' => [
                        'Helps others succeed',
                        'Holds the highest authority in the room',
                        'Focuses solely on individual performance',
                        'Commands and gives orders without feedback',
                    ],
                ],
                [
                    'id' => 3,
                    'key' => 'junior_challenged_idea',
                    'question' => 'If a junior team member challenges your idea, how do you respond?',
                    'options' => [
                        'Think openly and discuss',
                        'Dismiss their opinion immediately',
                        'Insist on your authority and experience',
                        'Ignore the input and proceed as planned',
                    ],
                ],
                [
                    'id' => 4,
                    'key' => 'leader_when_wrong',
                    'question' => 'What should a leader do when things go wrong or a mistake occurs?',
                    'options' => [
                        'Takes responsibility and finds a solution',
                        'Blame team members or external factors',
                        'Hide the mistake to protect reputation',
                        'Wait for someone else to resolve the problem',
                    ],
                ],
                [
                    'id' => 5,
                    'key' => 'team_motivation',
                    'question' => 'What is the most effective way to keep your team motivated?',
                    'options' => [
                        'Appreciation, trust and clear goals',
                        'Strict monitoring and penalties',
                        'Only offering monetary incentives',
                        'Creating aggressive competition among members',
                    ],
                ],
                [
                    'id' => 6,
                    'key' => 'leadership_meaning',
                    'question' => 'What does leadership mean to you fundamentally?',
                    'options' => [
                        'Taking people forward together',
                        'Holding power and control over others',
                        'Achieving personal recognition and titles',
                        'Managing tasks without personal connection',
                    ],
                ],
                [
                    'id' => 7,
                    'key' => 'different_background_team_first_step',
                    'question' => 'When leading a team of people from diverse backgrounds, what is your first step?',
                    'options' => [
                        'Know them and align goals',
                        'Impose a uniform working style immediately',
                        'Separate them based on their backgrounds',
                        'Focus only on output without personal alignment',
                    ],
                ],
                [
                    'id' => 8,
                    'key' => 'group_task_approach',
                    'question' => 'How do you approach leading a complex group project or task?',
                    'options' => [
                        'Involve everyone and guide the team',
                        'Do the majority of the work alone',
                        'Delegate everything without clear guidance',
                        'Leave everyone to figure out their own roles',
                    ],
                ],
                [
                    'id' => 9,
                    'key' => 'team_conflict_action',
                    'question' => 'When conflict arises between team members, what action do you take?',
                    'options' => [
                        'Listen to both and solve calmly',
                        'Take sides based on personal preference',
                        'Tell them to resolve it outside work',
                        'Avoid the issue hoping it resolves itself',
                    ],
                ],
                [
                    'id' => 10,
                    'key' => 'leader_makes_others_feel',
                    'question' => 'A great leader consistently makes other people feel:',
                    'options' => [
                        'Important and confident',
                        'Dependent on the leader for every decision',
                        'Intimidated and cautious',
                        'Obligated to agree at all times',
                    ],
                ],
                [
                    'id' => 11,
                    'key' => 'team_big_achievement_action',
                    'question' => 'When your team achieves a major milestone, how do you handle it?',
                    'options' => [
                        'Celebrate with them',
                        'Take sole credit as the leader',
                        'Quickly move on without acknowledging success',
                        'Highlight only what could have been better',
                    ],
                ],
                [
                    'id' => 12,
                    'key' => 'guide_new_entrepreneurs',
                    'question' => 'When asked to mentor or guide new entrepreneurs, how do you react?',
                    'options' => [
                        'Happily share your journey and tips',
                        'Refuse to share business insights',
                        'Demand consulting fees upfront',
                        'Keep key learnings confidential',
                    ],
                ],
                [
                    'id' => 13,
                    'key' => 'local_business_group_thought',
                    'question' => 'What is your perspective on participating in a local business group or circle?',
                    'options' => [
                        'Interesting – I like helping and learning',
                        'A waste of productive business time',
                        'Only useful for direct selling and pitching',
                        'I prefer working in complete isolation',
                    ],
                ],
                [
                    'id' => 14,
                    'key' => 'silent_team_meeting_action',
                    'question' => 'If team members are quiet and not speaking up during a meeting, what do you do?',
                    'options' => [
                        'Ask open questions to involve them',
                        'End the meeting abruptly',
                        'Do all the talking yourself',
                        'Call out individuals aggressively',
                    ],
                ],
                [
                    'id' => 15,
                    'key' => 'leadership_starts_with',
                    'question' => 'Where does genuine leadership begin?',
                    'options' => [
                        'Self-awareness and action',
                        'Receiving a formal title or position',
                        'Having authority over others',
                        'Accumulating wealth and power',
                    ],
                ],
                [
                    'id' => 16,
                    'key' => 'business_community_approach',
                    'question' => 'What is your primary approach when joining a business community?',
                    'options' => [
                        'Learn, contribute, and connect with others',
                        'Spam members with promotional messages',
                        'Only observe without participating',
                        'Seek immediate personal favors',
                    ],
                ],
                [
                    'id' => 17,
                    'key' => 'low_confidence_person_action',
                    'question' => 'How do you support a team member who lacks self-confidence?',
                    'options' => [
                        'Encourage and support them',
                        'Criticize their hesitation publicly',
                        'Reduce their responsibilities permanently',
                        'Replace them with a more confident member',
                    ],
                ],
                [
                    'id' => 18,
                    'key' => 'support_most_in_team',
                    'question' => 'Who in your team deserves your most active support?',
                    'options' => [
                        'Anyone trying to grow',
                        'Only top-performing favorites',
                        'Those who never question your decisions',
                        'Only people with senior titles',
                    ],
                ],
                [
                    'id' => 19,
                    'key' => 'good_leadership_means',
                    'question' => 'Ultimately, good leadership means:',
                    'options' => [
                        'Building trust and results together',
                        'Enforcing strict obedience',
                        'Achieving targets regardless of people’s wellbeing',
                        'Maintaining personal authority',
                    ],
                ],
                [
                    'id' => 20,
                    'key' => 'feedback_frequency',
                    'question' => 'How often should constructive feedback and recognition be shared?',
                    'options' => [
                        'Regularly',
                        'Only during annual appraisals',
                        'Only when major mistakes happen',
                        'Rarely or never',
                    ],
                ],
                [
                    'id' => 21,
                    'key' => 'unhappy_customer_action',
                    'question' => 'When dealing with an unhappy or upset customer, what is your approach?',
                    'options' => [
                        'Listen fully and fix the issue',
                        'Argue to prove the business was right',
                        'Ignore their complaint if unreasonable',
                        'Blame your front-line employees',
                    ],
                ],
                [
                    'id' => 22,
                    'key' => 'new_network_person_action',
                    'question' => 'When meeting a new person in your business network, how do you greet them?',
                    'options' => [
                        'Welcome and ask what they do',
                        'Immediately pitch your products or services',
                        'Wait for them to approach and impress you',
                        'Engage only if they have high status',
                    ],
                ],
                [
                    'id' => 23,
                    'key' => 'local_event_speaking_action',
                    'question' => 'When invited to speak or share knowledge at a local event, what do you do?',
                    'options' => [
                        'Accept and prepare to share something useful',
                        'Decline because it does not pay',
                        'Accept only to promote your own business',
                        'Attend unprepared and speak off the cuff',
                    ],
                ],
                [
                    'id' => 24,
                    'key' => 'leadership_role_offer_action',
                    'question' => 'If offered a leadership role or responsibility in your community, what do you do?',
                    'options' => [
                        'Ask for clarity and say yes if it fits your goals',
                        'Accept immediately without knowing expectations',
                        'Decline outright to avoid extra responsibility',
                        'Accept only for the title and prestige',
                    ],
                ],
                [
                    'id' => 25,
                    'key' => 'great_leader_opinion',
                    'question' => 'In your opinion, the most effective leaders are:',
                    'options' => [
                        'Focused, kind, and action-oriented',
                        'Aggressive and uncompromising',
                        'Strict and emotionally detached',
                        'Passive and avoiding decisions',
                    ],
                ],
            ];
        }

        return [
            'title' => 'Leadership Certification Assessment',
            'total_questions' => count($questions),
            'scoring' => [
                'points_per_question' => 4,
                'maximum_score' => 100,
                'levels' => [
                    ['level' => 'Established Leader', 'min_score' => 81, 'max_score' => 100],
                    ['level' => 'Growing Leader', 'min_score' => 61, 'max_score' => 80],
                    ['level' => 'Aspiring Leader', 'min_score' => 40, 'max_score' => 60],
                    ['level' => 'Needs Improvement', 'min_score' => 0, 'max_score' => 39],
                ],
            ],
            'questions' => $questions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEntrepreneurCertificationQuestions(): array
    {
        $questions = $this->getQuestionsFromDatabase('entrepreneur');

        if ($questions === null) {
            $questions = [
                [
                    'id' => 1,
                    'key' => 'business_start_reason',
                    'question' => 'What was the core reason you started your own business?',
                    'options' => [
                        'To follow my passion and build something of my own',
                        'To avoid having a boss and work less',
                        'Purely for quick financial gain',
                        'Because I had no other career options',
                    ],
                ],
                [
                    'id' => 2,
                    'key' => 'business_failure_reaction',
                    'question' => 'How do you react when a business project or initiative does not succeed?',
                    'options' => [
                        'Pause, analyze, and find what I can improve',
                        'Give up on the entire idea immediately',
                        'Blame the team or market conditions',
                        'Ignore the outcome and repeat the exact same steps',
                    ],
                ],
                [
                    'id' => 3,
                    'key' => 'successful_entrepreneur_definition',
                    'question' => 'How do you define a truly successful entrepreneur?',
                    'options' => [
                        'Someone who learns and grows from every failure',
                        'Someone who never makes mistakes',
                        'Someone whose primary measure is personal wealth',
                        'Someone who dominates competitors at all costs',
                    ],
                ],
                [
                    'id' => 4,
                    'key' => 'business_purpose_frequency',
                    'question' => 'How often do you reflect on your business purpose and vision?',
                    'options' => [
                        'Regularly – I know why I am doing this',
                        'Only when facing a business crisis',
                        'Never, daily operations take all my time',
                        'Only when writing a pitch deck for investors',
                    ],
                ],
                [
                    'id' => 5,
                    'key' => 'business_challenge_approach',
                    'question' => 'When faced with a complex business challenge, what is your approach?',
                    'options' => [
                        'Break it into smaller steps and solve',
                        'Procrastinate until the situation forces action',
                        'Panic and change business direction immediately',
                        'Wait for someone else to solve it',
                    ],
                ],
                [
                    'id' => 6,
                    'key' => 'finance_tracking_frequency',
                    'question' => 'How frequently do you track your business cash flow, revenue, and expenses?',
                    'options' => [
                        'Weekly or more often',
                        'Once a year at tax filing time',
                        'Only when bank balance runs low',
                        'Monthly or quarterly without detailed checks',
                    ],
                ],
                [
                    'id' => 7,
                    'key' => 'pricing_decision_method',
                    'question' => 'How do you determine the pricing for your products or services?',
                    'options' => [
                        'Based on costs + value to customers',
                        'Undercutting everyone with lowest possible price',
                        'Guessing based on what feels right',
                        'Copying competitors without understanding margins',
                    ],
                ],
                [
                    'id' => 8,
                    'key' => 'business_systems_status',
                    'question' => 'Do you have structured operational systems (billing, inventory, workflow) in place?',
                    'options' => [
                        'Yes, I follow clear systems (billing, tracking, etc.)',
                        'No, everything is managed verbally or in memory',
                        'Partially, but we rarely follow them',
                        'Only when clients specifically demand them',
                    ],
                ],
                [
                    'id' => 9,
                    'key' => 'unhappy_customer_response',
                    'question' => 'What is your first reaction to a dissatisfied customer complaint?',
                    'options' => [
                        'Say sorry and promise to fix it',
                        'Explain why the customer was wrong',
                        'Ignore the review or complaint',
                        'Offer a refund and refuse future business',
                    ],
                ],
                [
                    'id' => 10,
                    'key' => 'money_separation_status',
                    'question' => 'Do you maintain a strict separation between personal finances and business accounts?',
                    'options' => [
                        'Yes, always',
                        'No, I mix business and personal expenses freely',
                        'Only when accountants review the books',
                        'Rarely, as my business funds my lifestyle',
                    ],
                ],
                [
                    'id' => 11,
                    'key' => 'failure_recovery_action',
                    'question' => 'What is your primary action to recover from setbacks and downturns?',
                    'options' => [
                        'Review what went wrong and improve it',
                        'Shut down operations and start over completely',
                        'Blame external economic factors',
                        'Pretend nothing happened and continue normally',
                    ],
                ],
                [
                    'id' => 12,
                    'key' => 'major_decision_method',
                    'question' => 'How do you approach making critical business decisions?',
                    'options' => [
                        'Analyze data, feedback, and expert opinions',
                        'Rely purely on gut feeling without data',
                        'Ask friends with no business experience',
                        'Delay the decision until the opportunity passes',
                    ],
                ],
                [
                    'id' => 13,
                    'key' => 'competitor_growth_response',
                    'question' => 'How do you react when a direct competitor experiences rapid growth?',
                    'options' => [
                        'Learn from them and innovate better',
                        'Complain about unfair competition',
                        'Copy their offerings blindly',
                        'Engage in negative marketing against them',
                    ],
                ],
                [
                    'id' => 14,
                    'key' => 'new_idea_action',
                    'question' => 'When you come up with an exciting new business concept, what is your first step?',
                    'options' => [
                        'I try a small pilot or test it quickly',
                        'Invest all savings immediately without validation',
                        'Keep it a secret and never execute',
                        'Wait until market conditions are 100% risk-free',
                    ],
                ],
                [
                    'id' => 15,
                    'key' => 'risk_approach',
                    'question' => 'What is your mindset towards business risk?',
                    'options' => [
                        'Take small calculated risks',
                        'Avoid any form of risk at all costs',
                        'Gamble high stakes without contingency plans',
                        'Rely on luck rather than preparation',
                    ],
                ],
                [
                    'id' => 16,
                    'key' => 'networking_belief',
                    'question' => 'What is your belief regarding professional networking and peer connections?',
                    'options' => [
                        'I believe in connecting and learning from others',
                        'Networking is only useful if someone buys immediately',
                        'I prefer running my business without outside contacts',
                        'Networking is an unnecessary expense',
                    ],
                ],
                [
                    'id' => 17,
                    'key' => 'conflict_handling',
                    'question' => 'How do you handle disagreements or conflicts with partners or team members?',
                    'options' => [
                        'Discuss openly and resolve quickly',
                        'Avoid difficult conversations and stay silent',
                        'Insist on my way because it is my business',
                        'Terminate relationships at the first dispute',
                    ],
                ],
                [
                    'id' => 18,
                    'key' => 'team_motivation_method',
                    'question' => 'How do you maintain enthusiasm and morale within your organization?',
                    'options' => [
                        'Through appreciation and sharing success stories',
                        'By constantly threatening job security',
                        'By promising rewards that are never delivered',
                        'By keeping all company milestones confidential',
                    ],
                ],
                [
                    'id' => 19,
                    'key' => 'business_meet_frequency',
                    'question' => 'How often do you participate in business meetups, forums, or networking events?',
                    'options' => [
                        'Regularly, I attend business meets',
                        'Never, I do not see value in events',
                        'Once every couple of years',
                        'Only when free food or gifts are offered',
                    ],
                ],
                [
                    'id' => 20,
                    'key' => 'community_growth_belief',
                    'question' => 'Do you believe collaborative communities help business growth?',
                    'options' => [
                        'Yes, I believe collaboration leads to growth',
                        'No, businesses should only view peers as competitors',
                        'Communities only benefit beginners',
                        'Solo work always outperforms collaborative effort',
                    ],
                ],
                [
                    'id' => 21,
                    'key' => 'five_year_business_vision',
                    'question' => 'What is your long-term plan for your enterprise over the next 5 years?',
                    'options' => [
                        'Growing with clear goals',
                        'No plan, I take things day by day',
                        'Hoping to exit or sell quickly without building value',
                        'Staying at the exact current size without change',
                    ],
                ],
                [
                    'id' => 22,
                    'key' => 'success_meaning',
                    'question' => 'How do you define personal and business success?',
                    'options' => [
                        'Recognition, stability, and impact',
                        'Showing off luxury lifestyle on social media',
                        'Accumulating wealth with zero social contribution',
                        'Just having a registered company name',
                    ],
                ],
                [
                    'id' => 23,
                    'key' => 'work_life_balance_method',
                    'question' => 'How do you manage the demands of entrepreneurship alongside personal life?',
                    'options' => [
                        'I plan time for family and self-care',
                        'Work 24/7 without rest or vacations',
                        'Neglect personal health until burnout occurs',
                        'Abandon business duties whenever overwhelmed',
                    ],
                ],
                [
                    'id' => 24,
                    'key' => 'society_value_belief',
                    'question' => 'Do you believe your business contributes positive value to society?',
                    'options' => [
                        'Yes, I solve real problems for people',
                        'No, business is strictly about taking profit',
                        'Society’s needs are not the concern of entrepreneurs',
                        'Value to society is irrelevant as long as it sells',
                    ],
                ],
                [
                    'id' => 25,
                    'key' => 'future_mentorship_belief',
                    'question' => 'What is your perspective on mentoring the next generation of business builders?',
                    'options' => [
                        'Yes, I believe in giving back',
                        'No, beginners must struggle on their own as I did',
                        'Only if there is high monetary compensation',
                        'I do not want to create future competitors',
                    ],
                ],
            ];
        }

        return [
            'title' => 'Entrepreneur Certification Assessment',
            'total_questions' => count($questions),
            'scoring' => [
                'points_per_question' => 4,
                'maximum_score' => 100,
                'tiers' => [
                    ['tier' => 'Established Entrepreneur', 'min_score' => 81, 'max_score' => 100],
                    ['tier' => 'Growing Entrepreneur', 'min_score' => 61, 'max_score' => 80],
                    ['tier' => 'Aspiring Entrepreneur', 'min_score' => 40, 'max_score' => 60],
                    ['tier' => 'Needs Improvement', 'min_score' => 0, 'max_score' => 39],
                ],
            ],
            'questions' => $questions,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function getQuestionsFromDatabase(string $type): ?array
    {
        try {
            if (! Schema::hasTable('certification_questions')) {
                return null;
            }

            $rows = DB::table('certification_questions')
                ->where('certification_type', $type)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            if ($rows->isEmpty()) {
                return null;
            }

            return $rows->map(function ($row, int $index): array {
                $options = $row->options;
                if (is_string($options)) {
                    $decoded = json_decode($options, true);
                    $options = is_array($decoded) ? $decoded : [];
                }

                return [
                    'id' => (int) ($row->sort_order ?? ($index + 1)),
                    'key' => (string) $row->key,
                    'question' => (string) $row->question,
                    'options' => (array) $options,
                ];
            })->values()->all();
        } catch (\Throwable) {
            return null;
        }
    }
}
