<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MilestoneBadge;
use App\Models\Post;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
<<<<<<< HEAD
use App\Services\Creative\LifeImpactCreativeGenerator;
=======
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MilestoneBadgeService
{
    public function calculateForUserId(string $userId): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $this->calculateForUser($user);
    }

    public function calculateForUser(User $user): void
    {
        try {
            if (! Schema::hasTable('milestone_badges') || ! Schema::hasTable('user_milestone_badges')) {
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('[MilestoneBadgeService] Schema check skipped: '.$e->getMessage());

            return;
        }

        $lifeImpactCount = (int) ($user->life_impacted_count ?? 0);
        $coinsBalance = (int) ($user->coins_balance ?? 0);
        $membersIntroducedCount = (int) ($user->members_introduced_count ?? 0);

        if ($membersIntroducedCount === 0 && Schema::hasColumn('users', 'introduced_by')) {
            $dbCount = User::query()->where('introduced_by', $user->id)->count();
            if ($dbCount > 0) {
                $membersIntroducedCount = $dbCount;
            }
        }

        $categories = [
            MilestoneBadge::TYPE_LIFE_IMPACT => $lifeImpactCount,
            MilestoneBadge::TYPE_COINS => $coinsBalance,
            MilestoneBadge::TYPE_MEMBER_INTRODUCTION => $membersIntroducedCount,
        ];

        $newlyEarnedBadges = [];

        try {
            DB::transaction(function () use ($user, $categories, &$newlyEarnedBadges): void {
                foreach ($categories as $type => $currentValue) {
                    $badges = MilestoneBadge::query()
                        ->where('type', $type)
                        ->where('is_active', true)
                        ->orderBy('required_count', 'asc')
                        ->get();

                    foreach ($badges as $badge) {
                        $existingRecord = UserMilestoneBadge::query()
                            ->where('user_id', $user->id)
                            ->where('badge_id', $badge->id)
                            ->first();

                        if ($currentValue >= $badge->required_count) {
                            if ($existingRecord) {
                                if ($existingRecord->status !== UserMilestoneBadge::STATUS_EARNED) {
                                    $existingRecord->update([
                                        'status' => UserMilestoneBadge::STATUS_EARNED,
                                        'achieved_count' => $currentValue,
                                        'earned_at' => now(),
                                        'revoked_at' => null,
                                    ]);
                                    $newlyEarnedBadges[] = $badge;
                                } else {
                                    $existingRecord->update([
                                        'achieved_count' => $currentValue,
                                    ]);
                                }
                            } else {
                                UserMilestoneBadge::create([
                                    'user_id' => $user->id,
                                    'badge_id' => $badge->id,
                                    'milestone_type' => $type,
                                    'achieved_count' => $currentValue,
                                    'status' => UserMilestoneBadge::STATUS_EARNED,
                                    'earned_at' => now(),
                                    'revoked_at' => null,
                                ]);
                                $newlyEarnedBadges[] = $badge;
                            }
                        } else {
                            if ($existingRecord && $existingRecord->status === UserMilestoneBadge::STATUS_EARNED) {
                                $existingRecord->update([
                                    'status' => UserMilestoneBadge::STATUS_REVOKED,
                                    'revoked_at' => now(),
                                    'achieved_count' => $currentValue,
                                ]);
                            }
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::warning('[MilestoneBadgeService] Badge calculation failed: '.$e->getMessage());
        }

        if (! empty($newlyEarnedBadges)) {
            $this->handleNewlyEarnedBadges($user, $newlyEarnedBadges);
        }
    }

    /**
     * Post timeline announcements & push notifications for newly earned milestone honours.
     *
     * @param  array<int, MilestoneBadge>  $badges
     */
    protected function handleNewlyEarnedBadges(User $user, array $badges): void
    {
        $userName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if (empty($userName)) {
            $userName = 'Peer Member';
        }

        $systemUser = User::where('email', 'info@peersglobal.com')->first();
        $authorUserId = $systemUser ? $systemUser->id : $user->id;

        foreach ($badges as $badge) {
            try {
                $existingPost = Post::query()
                    ->where('source_type', 'milestone_badge')
                    ->where('source_id', $badge->id)
                    ->where('tags', 'like', "%{$user->id}%")
                    ->first();

                if (! $existingPost && Schema::hasTable('posts')) {
<<<<<<< HEAD
                    if ($badge->type === MilestoneBadge::TYPE_LIFE_IMPACT) {
                        $lifeImpactGenerator = app(LifeImpactCreativeGenerator::class);
                        $meta = $lifeImpactGenerator->getRecognitionMeta((int) $badge->required_count);
                        $description = $lifeImpactGenerator->formatCaption($user, (int) $badge->required_count, $meta);
                        $postTitle = "🎉 Big Congratulations! {$userName} became a {$meta['title']}";
                        $postType = 'life_impact_recognition';
                        $tags = ['milestone_honour', 'life_impact', 'life_impact_recognition', (string) $user->id, $meta['hashtag']];

                        try {
                            $fileRecord = $lifeImpactGenerator->generate($user, (int) $badge->required_count, (int) $badge->required_count);
                            $creativeImageUrl = url('/api/v1/files/'.$fileRecord->id);
                            $media = [
                                [
                                    'id' => $fileRecord->id,
                                    'type' => 'image',
                                    'url' => $creativeImageUrl,
                                ],
                            ];
                        } catch (\Throwable $creativeEx) {
                            Log::error("[MilestoneBadgeService] Failed generating life impact creative for badge {$badge->title}: ".$creativeEx->getMessage());
                            $creativeImageUrl = ! empty($meta['badge_image']) ? asset($meta['badge_image']) : url('/images/life_impact_badges/Impact Creator.png');
                            $media = [
                                [
                                    'id' => (string) Str::uuid(),
                                    'type' => 'image',
                                    'url' => $creativeImageUrl,
                                ],
                            ];
                        }
                    } else {
                        $description = "Congratulations to {$userName} for unlocking the \"{$badge->title}\" Honour in Track 1 — Growth for introducing {$badge->required_count} paid members to Peers Global! 🎉\n\n\"{$badge->description}\"";
                        $postTitle = "🏆 Track 1 Growth Honour Unlocked: {$badge->title}! 🎉";
                        $postType = 'growth_honour';
                        $tags = ['milestone_honour', 'growth_track', 'growth_honour', (string) $user->id];

                        try {
                            $generator = app(IntroducedPeerCreativeGenerator::class);
                            $fileRecord = $generator->generate($user, (int) $badge->required_count);
                            $creativeImageUrl = url('/api/v1/files/'.$fileRecord->id);
                            $media = [
                                [
                                    'id' => $fileRecord->id,
                                    'type' => 'image',
                                    'url' => $creativeImageUrl,
                                ],
                            ];
                        } catch (\Throwable $creativeEx) {
                            Log::error("[MilestoneBadgeService] Failed generating composite creative for badge {$badge->title}: ".$creativeEx->getMessage());
                            $creativeImageUrl = $badge->badge_image_url ?: url('/images/introduction-template.png');
                            $media = [
                                [
                                    'id' => (string) Str::uuid(),
                                    'type' => 'image',
                                    'url' => $creativeImageUrl,
                                ],
                            ];
                        }
=======
                    $description = "Congratulations to {$userName} for unlocking the \"{$badge->title}\" Honour in Track 1 — Growth for introducing {$badge->required_count} paid members to Peers Global! 🎉\n\n\"{$badge->description}\"";

                    try {
                        $generator = app(IntroducedPeerCreativeGenerator::class);
                        $fileRecord = $generator->generate($user, (int) $badge->required_count);
                        $creativeImageUrl = url('/api/v1/files/'.$fileRecord->id);
                        $media = [
                            [
                                'id' => $fileRecord->id,
                                'type' => 'image',
                                'url' => $creativeImageUrl,
                            ],
                        ];
                    } catch (\Throwable $creativeEx) {
                        Log::error("[MilestoneBadgeService] Failed generating composite creative for badge {$badge->title}: ".$creativeEx->getMessage());
                        $creativeImageUrl = $badge->badge_image_url ?: url('/images/introduction-template.png');
                        $media = [
                            [
                                'id' => (string) Str::uuid(),
                                'type' => 'image',
                                'url' => $creativeImageUrl,
                            ],
                        ];
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
                    }

                    Post::create([
                        'user_id' => $authorUserId,
                        'circle_id' => null,
                        'content_text' => $description,
                        'media' => $media,
<<<<<<< HEAD
                        'tags' => $tags,
=======
                        'tags' => ['milestone_honour', 'growth_track', 'growth_honour', (string) $user->id],
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
                        'visibility' => 'public',
                        'moderation_status' => 'approved',
                        'sponsored' => false,
                        'is_deleted' => false,
                        'source_type' => 'milestone_badge',
                        'source_id' => $badge->id,
                        'source_event' => 'badge_unlocked',
<<<<<<< HEAD
                        'post_type' => $postType,
                        'title' => $postTitle,
=======
                        'post_type' => 'growth_honour',
                        'title' => "🏆 Track 1 Growth Honour Unlocked: {$badge->title}! 🎉",
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
                        'description' => $description,
                        'image' => $creativeImageUrl,
                        'status' => 'active',
                    ]);

                    Log::info("[MilestoneBadgeService] Published timeline post for user {$user->id} unlocking badge {$badge->title}");
                }

                if (class_exists(NotificationService::class)) {
                    /** @var NotificationService $notificationService */
                    $notificationService = app(NotificationService::class);
                    $notificationService->sendToUser(
                        $user,
                        'milestone_badge_unlocked',
                        '🏆 Track 1 Growth Honour Unlocked!',
                        "Congratulations! You unlocked the \"{$badge->title}\" Honour for introducing {$badge->required_count} members to Peers Global.",
                        [
                            'screen' => 'profile',
                            'badge_id' => (string) $badge->id,
                            'badge_title' => $badge->title,
                        ],
                        [
                            'channel' => 'push',
                            'bypass_daily_limit' => true,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::error('[MilestoneBadgeService] Failed handling newly earned badge: '.$e->getMessage(), [
                    'exception' => $e,
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                ]);
            }
        }
    }
}
