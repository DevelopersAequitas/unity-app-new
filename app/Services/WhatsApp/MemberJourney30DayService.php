<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\MemberJourneySend;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class MemberJourney30DayService
{
    /**
     * Master mapping of Day 1 through Day 30 to verified approved Meta WhatsApp templates.
     *
     * @var array<int, array{name: string, title: string, cta: string, language: string}>
     */
    public const JOURNEY_TEMPLATES = [
        1 => ['name' => 'day_1_your_peer_profile', 'title' => 'Your Peer Profile', 'cta' => 'Complete Profile', 'language' => 'en_IN'],
        2 => ['name' => 'day_2_your_business_', 'title' => 'Your Business', 'cta' => 'Update Profile', 'language' => 'en_IN'],
        3 => ['name' => 'day_3_your_face', 'title' => 'Your Face', 'cta' => 'Add Photo', 'language' => 'en_IN'],
        4 => ['name' => 'day_4_the_mission', 'title' => 'The Mission', 'cta' => 'Explore Mission', 'language' => 'en_IN'],
        5 => ['name' => 'day_5_discover_peers', 'title' => 'Discover Peers', 'cta' => 'Discover Peers', 'language' => 'en_IN'],
        6 => ['name' => 'day_6_start_one_conversation', 'title' => 'Start One Conversation', 'cta' => 'Connect', 'language' => 'en_IN'],
        7 => ['name' => 'day_7_your_circle', 'title' => 'Your Circle', 'cta' => 'Explore My Circle', 'language' => 'en_IN'],
        8 => ['name' => 'day_8_your_story', 'title' => 'Your Story', 'cta' => 'Share My Story', 'language' => 'en_IN'],
        9 => ['name' => 'day_9_give_first', 'title' => 'Give First', 'cta' => 'Help a Peer', 'language' => 'en_IN'],
        10 => ['name' => 'day_10_ask_your_peers', 'title' => 'Ask Your Peers', 'cta' => 'Ask Your Peers', 'language' => 'en_IN'],
        11 => ['name' => 'day_11_collaboration', 'title' => 'Collaboration', 'cta' => 'Explore Collaboration', 'language' => 'en_IN'],
        12 => ['name' => 'day_12_meet_in_person', 'title' => 'Meet in Person', 'cta' => 'View Events', 'language' => 'en_IN'],
        13 => ['name' => 'day_13_learn_from_a_peer', 'title' => 'Learn From a Peer', 'cta' => 'Learn', 'language' => 'en_IN'],
        14 => ['name' => 'day_14_reflect', 'title' => 'Reflect', 'cta' => 'View My Impact', 'language' => 'en_IN'],
        15 => ['name' => 'day_15_learning', 'title' => 'Learning', 'cta' => 'Explore Learning', 'language' => 'en_IN'],
        16 => ['name' => 'day_16_sales', 'title' => 'Sales', 'cta' => 'Explore Opportunities', 'language' => 'en_IN'],
        17 => ['name' => 'day_17_resources', 'title' => 'Resources', 'cta' => 'Find Resources', 'language' => 'en_IN'],
        18 => ['name' => 'day_18_introduce_a_peer', 'title' => 'Introduce a Peer', 'cta' => 'Introduce a Peer', 'language' => 'en_IN'],
        19 => ['name' => 'day_19_show_how_you_can_help', 'title' => 'Show How You Can Help', 'cta' => 'Update How I Can Help', 'language' => 'en_IN'],
        20 => ['name' => 'day_20_beyond_your_industry', 'title' => 'Beyond Your Industry', 'cta' => 'Explore Circles', 'language' => 'en_IN'],
        21 => ['name' => 'day_21_peer_success', 'title' => 'Peer Success', 'cta' => 'Read Story', 'language' => 'en_IN'],
        22 => ['name' => 'day_22_onetoone', 'title' => 'One-To-One', 'cta' => 'Find a Peer', 'language' => 'en_IN'],
        23 => ['name' => 'day_23_share_your_win', 'title' => 'Share Your Win', 'cta' => 'Share My Win', 'language' => 'en_IN'],
        24 => ['name' => 'day_24_recognise_a_peer', 'title' => 'Recognise a Peer', 'cta' => 'Recognise a Peer', 'language' => 'en_IN'],
        25 => ['name' => 'day_25_share_a_resource', 'title' => 'Share a Resource', 'cta' => 'Share Resource', 'language' => 'en_IN'],
        26 => ['name' => 'day_26_think_about_impact_', 'title' => 'Think About Impact', 'cta' => 'View My Impact', 'language' => 'en_IN'],
        27 => ['name' => 'day_27_invest_in_your_circle', 'title' => 'Invest in Your Circle', 'cta' => 'Connect With Circle', 'language' => 'en_IN'],
        28 => ['name' => 'day_28_your_next_goal', 'title' => 'Your Next Goal', 'cta' => 'Set My LSR Goal', 'language' => 'en_IN'],
        29 => ['name' => 'day_29_give_more', 'title' => 'Give More', 'cta' => 'Help a Peer', 'language' => 'en_IN'],
        30 => ['name' => 'day_30_you_are_a_peer', 'title' => 'You Are a Peer', 'cta' => 'Enter My Circle', 'language' => 'en_IN'],
    ];

    public function __construct(
        protected MetaWhatsAppService $metaWhatsAppService
    ) {}

    /**
     * Initialize the 30-day onboarding journey schedule for a member.
     * Each day N is scheduled at (base date + N days).
     * If member was registered over 30 days ago, base date defaults to today so past days are not falsely due.
     *
     * @return int Number of newly scheduled days
     */
    public function initializeJourney(User $user, ?Carbon $baseDate = null, bool $fromToday = false): int
    {
        if ($fromToday) {
            $baseDate = now();
        } elseif ($baseDate === null) {
            $userCreatedAt = $user->created_at ? Carbon::parse($user->created_at) : now();
            // If user registered over 30 days ago, schedule progressive journey starting from today
            $baseDate = $userCreatedAt->lt(now()->subDays(30)) ? now() : $userCreatedAt;
        }

        $createdCount = 0;

        for ($day = 1; $day <= 30; $day++) {
            $exists = MemberJourneySend::where('user_id', $user->id)
                ->where('day_number', $day)
                ->exists();

            if (! $exists) {
                $templateInfo = self::JOURNEY_TEMPLATES[$day];
                $scheduledAt = $baseDate->copy()->addDays($day);

                MemberJourneySend::create([
                    'user_id' => $user->id,
                    'day_number' => $day,
                    'template_name' => $templateInfo['name'],
                    'language' => $templateInfo['language'],
                    'scheduled_at' => $scheduledAt,
                    'status' => 'scheduled',
                ]);

                $createdCount++;
            }
        }

        Log::info('[MemberJourney30DayService] Initialized 30-day journey for user.', [
            'user_id' => $user->id,
            'newly_created_days' => $createdCount,
            'base_date' => $baseDate->toIso8601String(),
        ]);

        return $createdCount;
    }

    /**
     * Bulk initialize 30-day journey for all members who have not been initialized yet.
     *
     * @return array{users_initialized: int, total_days_created: int}
     */
    public function initializeAllUsers(?int $limit = 500): array
    {
        $users = User::whereDoesntHave('memberJourneySends')
            ->limit($limit ?? 500)
            ->get();

        $initializedCount = 0;
        $totalDaysCreated = 0;

        foreach ($users as $user) {
            $created = $this->initializeJourney($user, fromToday: true);
            if ($created > 0) {
                $initializedCount++;
                $totalDaysCreated += $created;
            }
        }

        Log::info('[MemberJourney30DayService] Bulk initialized journey for members.', [
            'users_initialized' => $initializedCount,
            'total_days_created' => $totalDaysCreated,
        ]);

        return [
            'users_initialized' => $initializedCount,
            'total_days_created' => $totalDaysCreated,
        ];
    }

    /**
     * Check if a specific Day should be conditionally skipped based on user profile state.
     *
     * @return string|null Reason for skip, or null if the message should proceed.
     */
    public function evaluateSkipCondition(User $user, int $dayNumber): ?string
    {
        // Day 1: Profile Completeness (Headline & Bio/Superpower)
        if ($dayNumber === 1) {
            $hasBio = ! empty($user->short_bio) || ! empty($user->long_bio_html) || ! empty($user->superpower) || ! empty($user->i_can_help_with);
            $hasDesignation = ! empty($user->designation);

            if ($hasBio && $hasDesignation) {
                return 'Profile bio and professional headline are already complete.';
            }
        }

        // Day 2: Business Information
        if ($dayNumber === 2) {
            $hasCompany = ! empty($user->company_name);
            $hasBusinessDesc = ! empty($user->business_type) || ! empty($user->industry_tags) || ! empty($user->experience_summary) || ! empty($user->business_category_id) || ! empty($user->business_website);

            if ($hasCompany && $hasBusinessDesc) {
                return 'Business details and company description are already provided.';
            }
        }

        // Day 3: Profile Photograph
        if ($dayNumber === 3) {
            $hasPhoto = ! empty($user->profile_photo_url) || ! empty($user->profile_photo_id) || ! empty($user->profile_photo_path);

            if ($hasPhoto) {
                return 'Profile photograph is already uploaded.';
            }
        }

        return null;
    }

    /**
     * Dispatch a specific Day's WhatsApp template message to a member.
     * If Days 1, 2, or 3 are skipped because profile conditions are already met,
     * it automatically cascades to send the next scheduled day immediately.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     provider_message_id: ?string,
     *     error: ?string
     * }
     */
    public function sendDay(
        MemberJourneySend $journeySend,
        bool $force = false,
        ?string $overridePhone = null
    ): array {
        $user = $journeySend->user;

        if (! $user) {
            $journeySend->update([
                'status' => 'failed',
                'error_message' => 'Associated member record not found.',
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'Member record not found.',
                'provider_message_id' => null,
                'error' => 'Member record not found.',
            ];
        }

        // Evaluate smart conditional skip for Days 1, 2, 3 unless explicitly forced by admin
        if (! $force && $journeySend->day_number <= 3) {
            $skipReason = $this->evaluateSkipCondition($user, $journeySend->day_number);

            if ($skipReason !== null) {
                $journeySend->update([
                    'status' => 'skipped',
                    'skip_reason' => $skipReason,
                    'error_message' => null,
                ]);

                Log::info('[MemberJourney30DayService] Day skipped due to profile completion. Cascading to next day.', [
                    'user_id' => $user->id,
                    'day_number' => $journeySend->day_number,
                    'reason' => $skipReason,
                ]);

                // Cascade to next day in flow as requested
                $nextSend = MemberJourneySend::where('user_id', $user->id)
                    ->where('day_number', '>', $journeySend->day_number)
                    ->where('status', 'scheduled')
                    ->orderBy('day_number')
                    ->first();

                if ($nextSend) {
                    return $this->sendDay($nextSend, force: false, overridePhone: $overridePhone);
                }

                return [
                    'success' => true,
                    'status' => 'skipped',
                    'message' => "Day {$journeySend->day_number} skipped: {$skipReason}",
                    'provider_message_id' => null,
                    'error' => null,
                ];
            }
        }

        $rawPhone = $overridePhone ?? $user->phone ?? $user->secondary_mobile;
        if (blank($rawPhone)) {
            $journeySend->update([
                'status' => 'failed',
                'error_message' => 'User phone number is empty or missing.',
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'Phone number missing for member.',
                'provider_message_id' => null,
                'error' => 'Phone number missing.',
            ];
        }

        // Backfill user phone if missing and override phone was supplied
        if (! empty($overridePhone) && empty($user->phone)) {
            $user->update(['phone' => $overridePhone]);
        }

        // Resolve First Name for dynamic parameter {{1}}
        $firstName = trim((string) ($user->first_name ?? ''));
        if ($firstName === '') {
            $parts = explode(' ', trim((string) ($user->display_name ?? $user->name ?? '')));
            $firstName = ! empty($parts[0]) ? $parts[0] : 'Peer';
        }

        $templateInfo = self::JOURNEY_TEMPLATES[$journeySend->day_number] ?? [
            'name' => $journeySend->template_name,
            'language' => $journeySend->language ?: 'en_IN',
        ];

        $templateName = $templateInfo['name'];
        $languageCode = $templateInfo['language'];

        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $firstName,
                    ],
                ],
            ],
        ];

        $payloadJson = [
            'FirstName' => $firstName,
            'day_number' => $journeySend->day_number,
            'template_name' => $templateName,
            'language' => $languageCode,
            'recipient_phone' => $rawPhone,
        ];

        try {
            $response = $this->metaWhatsAppService->sendTemplateMessage(
                recipientPhone: (string) $rawPhone,
                templateName: $templateName,
                languageCode: $languageCode,
                components: $components,
                userId: $user->id
            );

            $metaMessageId = $response['meta_message_id'] ?? null;

            $journeySend->update([
                'status' => 'sent',
                'sent_at' => now(),
                'payload_json' => $payloadJson,
                'provider_response_json' => $response,
                'provider_message_id' => $metaMessageId,
                'error_message' => null,
                'is_test' => ! empty($overridePhone),
            ]);

            Log::info('[MemberJourney30DayService] Dispatched journey template successfully.', [
                'user_id' => $user->id,
                'day_number' => $journeySend->day_number,
                'template_name' => $templateName,
                'meta_message_id' => $metaMessageId,
            ]);

            return [
                'success' => true,
                'status' => 'sent',
                'message' => "Day {$journeySend->day_number} ('{$templateName}') sent successfully.",
                'provider_message_id' => $metaMessageId,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();

            $journeySend->update([
                'status' => 'failed',
                'payload_json' => $payloadJson,
                'error_message' => $errorMsg,
            ]);

            Log::error('[MemberJourney30DayService] Failed to send journey template.', [
                'user_id' => $user->id,
                'day_number' => $journeySend->day_number,
                'template_name' => $templateName,
                'error' => $errorMsg,
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => "Failed to send Day {$journeySend->day_number}: {$errorMsg}",
                'provider_message_id' => null,
                'error' => $errorMsg,
            ];
        }
    }

    /**
     * Process all scheduled journey sends that are due for dispatch right now.
     *
     * @return array{total_due: int, sent: int, skipped: int, failed: int}
     */
    public function processDueSends(): array
    {
        $dueSends = MemberJourneySend::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->with('user')
            ->orderBy('scheduled_at')
            ->limit(100)
            ->get();

        $stats = [
            'total_due' => $dueSends->count(),
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($dueSends as $send) {
            $result = $this->sendDay($send);

            if ($result['status'] === 'sent') {
                $stats['sent']++;
            } elseif ($result['status'] === 'skipped') {
                $stats['skipped']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Retrieve complete 30-day journey records for a user, filling in template details.
     *
     * @return Collection<int, MemberJourneySend>
     */
    public function getMemberJourney(string $userId): Collection
    {
        return MemberJourneySend::where('user_id', $userId)
            ->orderBy('day_number')
            ->get();
    }
}
