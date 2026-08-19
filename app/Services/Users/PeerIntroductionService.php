<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Post;
use App\Models\User;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Creative\IntroductionImageGenerator;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PeerIntroductionService
{
    public function __construct(
        private readonly IntroductionImageGenerator $imageGenerator,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle the full post-introduction flow: generate creative, create timeline post, send notification.
     */
    public function handlePeerIntroduction(User $introducer, User $introduced): void
    {
        Log::info("[PeerIntroductionService] Starting introduction flow for introducer {$introducer->id} and introduced {$introduced->id}");

        try {
            // 1. Generate the congratulations creative image
            $fileRecord = $this->imageGenerator->generate($introducer, $introduced);
            $imageUrl = url('/api/v1/files/'.$fileRecord->id);

            // Names for text messages
            $introducerName = $introducer->display_name ?: trim(($introducer->first_name ?? '').' '.($introducer->last_name ?? ''));
            if (empty($introducerName)) {
                $introducerName = 'Peer Member';
            }

            $introducedName = $introduced->display_name ?: trim(($introduced->first_name ?? '').' '.($introduced->last_name ?? ''));
            if (empty($introducedName)) {
                $introducedName = 'New Member';
            }

            $description = "Congratulations to {$introducerName} for introducing {$introducedName} to the Peers Global Community of Collaboration. Wishing you both a successful journey filled with meaningful connections, collaboration, and endless opportunities. 🎉🤝";

            // 2. Create the timeline announcement post
            // Find a system/admin fallback account to own the automated post
            $systemUser = User::where('email', 'info@peersglobal.com')->first();
            if (! $systemUser) {
                $systemUser = User::create([
                    'id' => (string) Str::uuid(),
                    'first_name' => 'PeersGlobal',
                    'last_name' => 'Unity',
                    'display_name' => 'PeersGlobal Unity',
                    'email' => 'info@peersglobal.com',
                    'password_hash' => bcrypt(Str::random(16)),
                    'status' => 'active',
                ]);
            }
            $authorUserId = $systemUser ? $systemUser->id : $introducer->id;

            $post = Post::create([
                'user_id' => $authorUserId,
                'circle_id' => null,
                'content_text' => $description,
                'media' => [
                    [
                        'id' => $fileRecord->id,
                        'type' => 'image',
                        'url' => $imageUrl,
                    ],
                ],
                'tags' => ['introduction'],
                'visibility' => 'public',
                'moderation_status' => 'approved',
                'sponsored' => false,
                'is_deleted' => false,
                'source_type' => 'introduction',
                'source_id' => $introduced->id,
                'source_event' => 'introduction',
                'post_type' => 'introduction',
                'title' => 'New Peer Introduced! 🎉',
                'description' => $description,
                'image' => $imageUrl,
                'status' => 'active',
            ]);

            Log::info("[PeerIntroductionService] Created timeline post ID {$post->id} owned by {$authorUserId}");

            // 3. Dispatch push notification to the introducer
            $notification = $this->notificationService->sendToUser(
                $introducer,
                'member_introduced', // Custom enum/notification type
                'Member Introduced Successfully! 🎉',
                "Hi, you have introduced {$introducedName} to the community.",
                [
                    'screen' => 'profile',
                    'tap_destination' => 'profile',
                    'user_id' => (string) $introduced->id,
                    'reference_type' => 'user',
                    'reference_id' => (string) $introduced->id,
                ],
                [
                    'channel' => 'push',
                    'reference_type' => 'user',
                    'reference_id' => (string) $introduced->id,
                    'bypass_daily_limit' => true,
                ]
            );

            if ($notification) {
                Log::info("[PeerIntroductionService] Dispatched push notification ID {$notification->id} to introducer {$introducer->id}");
            } else {
                Log::info("[PeerIntroductionService] Push notification suppressed/deduplicated for introducer {$introducer->id}");
            }

            // 4. Automatically trigger Growth Honour Timeline Post if a threshold (1, 3, 5, 10...) is hit
            $introducedCount = User::query()->where('introduced_by', $introducer->id)->count();
            $generator = app(IntroducedPeerCreativeGenerator::class);
            $honours = $generator->getAllHonours();

            if (array_key_exists($introducedCount, $honours)) {
                $meta = $generator->getHonourMeta($introducedCount);
                $title = "BIG CONGRATULATIONS: {$meta['title']} — ".($introducer->display_name ?: $introducer->name);

                $exists = Post::where('post_type', 'growth_honour')
                    ->where('source_type', 'member_introduction')
                    ->where('source_id', $introducer->id)
                    ->where('title', $title)
                    ->exists();

                if (! $exists) {
                    Log::info("[PeerIntroductionService] Introducer {$introducer->id} reached threshold {$introducedCount}. Generating automatic Growth Honour timeline post...");
                    try {
                        $fileRecord = $generator->generate($introducer, $introducedCount);
                        $imageUrl = url('/api/v1/files/'.$fileRecord->id);
                        $caption = $generator->formatCaption($introducer, $introducedCount);

                        Post::create([
                            'user_id' => $authorUserId,
                            'circle_id' => null,
                            'content_text' => $caption,
                            'media' => [
                                [
                                    'id' => $fileRecord->id,
                                    'type' => 'image',
                                    'url' => $imageUrl,
                                ],
                            ],
                            'tags' => ['introduction', 'growth_honour', 'member_introducer', (string) $introducer->id, "growth_honour_{$introducedCount}"],
                            'visibility' => 'public',
                            'moderation_status' => 'approved',
                            'sponsored' => false,
                            'is_deleted' => false,
                            'source_type' => 'member_introduction',
                            'source_id' => $introducer->id,
                            'source_event' => 'growth_honour',
                            'post_type' => 'growth_honour',
                            'title' => $title,
                            'description' => $caption,
                            'image' => $imageUrl,
                            'status' => 'active',
                        ]);
                    } catch (\Throwable $regenEx) {
                        Log::error('[PeerIntroductionService] Failed generating automatic Growth Honour post: '.$regenEx->getMessage());
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('[PeerIntroductionService] Failed handling introduction flow: '.$e->getMessage(), [
                'exception' => $e,
            ]);
            if (app()->environment('testing')) {
                throw $e;
            }
        }
    }
}
