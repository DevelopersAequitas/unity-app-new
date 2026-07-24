<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use App\Services\Creative\GlobalPeerCertificateImageGenerator;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendGlobalPeerCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:send-global-peer
                            {--user-id= : Target a specific user ID for testing/manual dispatch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and deliver a Global Peer Certificate to every paid-tier member who has not yet received one.';

    /**
     * Membership statuses that are considered "paid" and qualify for the certificate.
     */
    private const PAID_STATUSES = GlobalPeerCertificateImageGenerator::PAID_STATUSES;

    /**
     * Execute the console command.
     */
    public function handle(
        GlobalPeerCertificateImageGenerator $generator,
        NotificationService $notificationService,
    ): int {
        $logPrefix = '[GlobalPeerCertificate]';
        Log::info("{$logPrefix} Run started at ".now()->toIso8601String());
        $this->info('Global Peer Certificate dispatch started.');

        $targetUserId = $this->option('user-id');

        // ── Build query ──────────────────────────────────────────────────────
        $query = User::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNull('global_peer_certificate_sent_at')
            ->whereIn('membership_status', self::PAID_STATUSES);

        if ($targetUserId) {
            $this->info("Manual mode – targeting user ID: {$targetUserId}");
            Log::info("{$logPrefix} Manual trigger for user ID: {$targetUserId}");
            $query->where('id', $targetUserId);
        }

        $users = $query->get();

        $this->info("Found {$users->count()} eligible user(s).");
        Log::info("{$logPrefix} Found {$users->count()} eligible user(s).");

        // ── Resolve / create system user ─────────────────────────────────────
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
        $authorUserId = $systemUser ? $systemUser->id : null;

        // ── Process each user ─────────────────────────────────────────────────
        $processed = 0;

        foreach ($users as $user) {
            $this->line("Processing user: {$user->display_name} ({$user->id})");
            Log::info("{$logPrefix} Processing user {$user->id} ({$user->display_name})");

            try {
                // 1. Guard: re-check stamp inside loop (race condition safety)
                $freshUser = User::query()->find($user->id);
                if (! $freshUser) {
                    continue;
                }
                if (filled($freshUser->global_peer_certificate_sent_at) && ! $targetUserId) {
                    $this->line('  → Already sent. Skipping.');

                    continue;
                }

                // 2. Generate certificate image
                $fileModel = $generator->generate($freshUser);
                $imageUrl = url("/api/v1/files/{$fileModel->id}");

                // 3. Create timeline post (owned by system user)
                $displayName = $freshUser->display_name ?: trim(($freshUser->first_name ?? '').' '.($freshUser->last_name ?? ''));
                $postText = "🎉 Congratulations {$displayName}! You are now an esteemed Global Peer of Peers Global – a community of entrepreneurs, professionals and leaders collaborating for growth, learning and creating positive impact. Welcome aboard! 🌟";

                Post::create([
                    'user_id' => $authorUserId ?? $freshUser->id,
                    'content_text' => $postText,
                    'post_type' => 'global_peer_certificate',
                    'active' => true,
                    'visibility' => 'public',
                    'moderation_status' => 'approved',
                    'source_type' => 'global_peer_certificate',
                    'source_id' => $freshUser->id,
                    'source_event' => 'global_peer_certificate',
                    'media' => [
                        [
                            'id' => $fileModel->id,
                            'type' => 'image',
                            'url' => $imageUrl,
                        ],
                    ],
                ]);

                // 4. Send push notification to the peer
                $notificationService->sendToUser(
                    $freshUser,
                    'global_peer_certificate',
                    '🎉 Your Global Peer Certificate is Here!',
                    'Congratulations! You have received your Global Peer Certificate. Tap to view your achievement!',
                    [
                        'screen' => 'profile',
                        'tap_destination' => 'profile',
                        'user_id' => (string) $freshUser->id,
                        'reference_type' => 'user',
                        'reference_id' => (string) $freshUser->id,
                    ],
                    [
                        'channel' => 'push',
                        'reference_type' => 'user',
                        'reference_id' => (string) $freshUser->id,
                        'dedupe_key' => 'global_peer_certificate:'.$freshUser->id,
                        'bypass_daily_limit' => true,
                    ]
                );

                // 5. Stamp sent timestamp to prevent re-sending
                $freshUser->forceFill(['global_peer_certificate_sent_at' => now()])->save();

                $this->info("  ✓ Certificate sent for user: {$freshUser->display_name} (post media ID: {$fileModel->id})");
                Log::info("{$logPrefix} Certificate dispatched for user {$freshUser->id}, file {$fileModel->id}");
                $processed++;
            } catch (Throwable $e) {
                $this->error("  ✗ Failed for user {$user->id}: ".$e->getMessage());
                Log::error("{$logPrefix} Failed for user {$user->id}: ".$e->getMessage(), [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->info("Completed. Certificates dispatched: {$processed}.");
        Log::info("{$logPrefix} Run completed. Processed: {$processed}.");

        return self::SUCCESS;
    }
}
