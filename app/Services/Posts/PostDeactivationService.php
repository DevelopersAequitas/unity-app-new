<?php

namespace App\Services\Posts;

use App\Jobs\SendPushNotificationJob;
use App\Models\File;
use App\Models\Impact;
use App\Models\LifeImpactCreative;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\Post;
use App\Models\User;
use App\Services\Creative\LifeImpactCreativeGenerator;
use App\Services\LifeImpact\LifeImpactService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PostDeactivationService
{
    /**
     * Resolve the actual peer/member targeted by the post.
     * For automated recognition posts (life impact, milestones, honours, birthdays) created by system genie,
     * the real recipient member is in source_id or tags.
     */
    public function resolveTargetUser(Post $post): ?User
    {
        $isRecognition = in_array($post->post_type, [
            'life_impact_recognition',
            'growth_honour',
            'milestone_honour',
            'birthday',
            'anniversary',
            'welcome',
        ], true) || ($post->source_type === 'life_impact');

        $isSystemAuthor = false;
        if ($post->user) {
            $authorEmail = strtolower((string) $post->user->email);
            $authorName = strtolower((string) ($post->user->display_name ?: $post->user->first_name));
            if (str_contains($authorEmail, 'peersglobal') || str_contains($authorName, 'genie') || str_contains($authorName, 'system')) {
                $isSystemAuthor = true;
            }
        }

        if (($isRecognition || $isSystemAuthor) && filled($post->source_id)) {
            $celebratedUser = User::find($post->source_id);
            if ($celebratedUser) {
                return $celebratedUser;
            }
        }

        return $post->user ?: User::find($post->user_id);
    }

    /**
     * Deactivate a post, reverse awarded coins & life impact score, and notify user.
     *
     * @param  Post  $post
     * @param  string  $remarks
     * @param  string|null  $adminId
     * @param  bool  $reverseCoins
     * @param  bool  $reverseImpact
     * @param  bool  $notifyUser
     * @param  int|null  $customCoinsAmount
     * @param  int|null  $customImpactAmount
     * @return array
     */
    public function deactivatePost(
        Post $post,
        string $remarks,
        ?string $adminId = null,
        bool $reverseCoins = true,
        bool $reverseImpact = true,
        bool $notifyUser = true,
        ?int $customCoinsAmount = null,
        ?int $customImpactAmount = null
    ): array {
        $remarks = trim($remarks);

        // 1. Idempotency Check: Don't deactivate or reverse rewards twice
        if ($this->isPostAlreadyDeactivated($post)) {
            return [
                'success' => false,
                'already_deactivated' => true,
                'message' => 'This post is already deactivated or its rewards have already been reversed.',
                'coins_reversed' => 0,
                'impact_reversed' => 0,
                'post_id' => $post->id,
            ];
        }

        $result = DB::transaction(function () use ($post, $remarks, $adminId, $reverseCoins, $reverseImpact, $notifyUser, $customCoinsAmount, $customImpactAmount) {
            // Resolve actual target member (celebrated user for recognition posts or post author)
            $targetUser = $this->resolveTargetUser($post);
            $userId = (string) ($targetUser?->id ?? $post->user_id);

            // 2. Identify coins awarded for this post or source activity (or use admin custom amount)
            if ($customCoinsAmount !== null) {
                $coinsToReverse = max(0, $customCoinsAmount);
                $matchedCoinTxIds = [];
            } else {
                $coinsRewardData = $this->findAwardedCoins($post);
                $coinsToReverse = $coinsRewardData['amount'];
                $matchedCoinTxIds = $coinsRewardData['tx_ids'];
            }

            // 3. Identify life impact points awarded for this post or source activity (or use admin custom amount)
            if ($customImpactAmount !== null) {
                $impactToReverse = max(0, $customImpactAmount);
                $matchedImpactIds = [];
            } else {
                $impactRewardData = $this->findAwardedImpactPoints($post);
                $impactToReverse = $impactRewardData['points'];
                $matchedImpactIds = $impactRewardData['history_ids'];
            }

            // 4. Reverse Coins if requested and any were awarded / specified
            $reversedCoins = 0;
            if ($reverseCoins && $coinsToReverse > 0) {
                $reversedCoins = $this->reverseCoins(
                    $userId,
                    $coinsToReverse,
                    $post,
                    $remarks,
                    $adminId,
                    $matchedCoinTxIds
                );
            }

            // 5. Reverse Life Impact Points if requested and any were awarded / specified
            $reversedImpact = 0;
            if ($reverseImpact && $impactToReverse > 0) {
                $reversedImpact = $this->reverseImpactPoints(
                    $userId,
                    $impactToReverse,
                    $post,
                    $remarks,
                    $adminId,
                    $matchedImpactIds
                );
            }

            // 6. Update post status, timestamps, and deactivation remarks
            $this->updatePostDeactivatedStatus($post, $remarks, $adminId);

            Log::info('[PostDeactivationService] Post deactivated successfully', [
                'post_id' => $post->id,
                'user_id' => $userId,
                'coins_reversed' => $reversedCoins,
                'impact_reversed' => $reversedImpact,
                'admin_id' => $adminId,
                'remarks' => $remarks,
                'reverse_coins' => $reverseCoins,
                'reverse_impact' => $reverseImpact,
                'notify_user' => $notifyUser,
            ]);

            $parts = [];
            if ($reversedCoins > 0) {
                $parts[] = number_format($reversedCoins) . ' coins';
            }
            if ($reversedImpact > 0) {
                $parts[] = number_format($reversedImpact) . ' life impact points';
            }

            $targetName = $targetUser?->display_name ?: $targetUser?->name ?: 'Member';
            $msg = 'Post deactivated successfully.';
            if (! empty($parts)) {
                $msg .= ' Reversed ' . implode(' and ', $parts) . ' for ' . $targetName . '.';
            } else {
                $msg .= ' No coin or life impact balance deducted.';
            }

            if ($notifyUser) {
                $msg .= ' ' . $targetName . ' notified.';
            }

            return [
                'success' => true,
                'already_deactivated' => false,
                'coins_reversed' => $reversedCoins,
                'impact_reversed' => $reversedImpact,
                'user_notified' => $notifyUser,
                'post_id' => $post->id,
                'message' => $msg,
            ];
        });

        // 7. Send in-app & push notification to the target member (after transaction successfully commits)
        if ($notifyUser) {
            $this->sendDeactivationNotification(
                $post,
                $remarks,
                (int) ($result['coins_reversed'] ?? 0),
                (int) ($result['impact_reversed'] ?? 0)
            );
        }

        return $result;
    }

    /**
     * Check if a post was already deactivated.
     */
    public function isPostAlreadyDeactivated(Post $post): bool
    {
        $isDeletedAttr = (bool) ($post->is_deleted ?? false);
        $trashed = method_exists($post, 'trashed') && $post->trashed();
        $deletedAtSet = ! is_null($post->deleted_at);

        return $isDeletedAttr || $trashed || $deletedAtSet;
    }

    /**
     * Locate coins awarded specifically for this post or its source activity.
     */
    public function findAwardedCoins(Post $post): array
    {
        $targetUser = $this->resolveTargetUser($post);
        $userId = (string) ($targetUser?->id ?? $post->user_id);
        $sourceType = $post->source_type;
        $sourceId = $post->source_id;

        $totalCoins = 0;
        $txIds = [];

        if (! Schema::hasTable('coins_ledger')) {
            return ['amount' => 0, 'tx_ids' => []];
        }

        // If coins for this post were already reversed, return 0 to prevent double deduction
        $alreadyReversedCoins = DB::table('coins_ledger')
            ->where('user_id', $userId)
            ->where('amount', '<', 0)
            ->where(function ($q) use ($post) {
                if (Schema::hasColumn('coins_ledger', 'reference_type') && Schema::hasColumn('coins_ledger', 'reference_id')) {
                    $q->where('reference_type', 'POST_DEACTIVATION')
                        ->where('reference_id', (string) $post->id);
                }
                $q->orWhere('reference', 'LIKE', '%Post deactivation reversal: ' . $post->id . '%');
            })
            ->exists();

        if ($alreadyReversedCoins) {
            return ['amount' => 0, 'tx_ids' => []];
        }

        // Check coins directly linked to post
        $postLedgers = DB::table('coins_ledger')
            ->where('user_id', $userId)
            ->where('amount', '>', 0)
            ->where(function ($q) use ($post) {
                $pId = (string) $post->id;
                if (Schema::hasColumn('coins_ledger', 'reference_type') && Schema::hasColumn('coins_ledger', 'reference_id')) {
                    $q->where(function ($sub) use ($pId) {
                        $sub->where('reference_type', 'POST');
                        if (Str::isUuid($pId)) {
                            $sub->where('reference_id', $pId);
                        }
                    });
                }
                if (Schema::hasColumn('coins_ledger', 'source_type') && Schema::hasColumn('coins_ledger', 'source_id')) {
                    $q->orWhere(function ($sub) use ($pId) {
                        $sub->where('source_type', 'post');
                        if (Str::isUuid($pId)) {
                            $sub->where('source_id', $pId);
                        }
                    });
                }
                if (Str::isUuid($pId)) {
                    $q->orWhere('activity_id', $pId);
                }
                $q->orWhere('reference', 'LIKE', '%Post%'.$pId.'%');
            })
            ->get();

        foreach ($postLedgers as $row) {
            if (! in_array($row->transaction_id, $txIds, true)) {
                $txIds[] = $row->transaction_id;
                $totalCoins += (int) $row->amount;
            }
        }

        // Check coins linked to source activity (e.g. business_deal, requirement, etc.)
        if (filled($sourceId)) {
            $sourceLedgers = DB::table('coins_ledger')
                ->where('user_id', $userId)
                ->where('amount', '>', 0)
                ->where(function ($q) use ($sourceId) {
                    $sId = (string) $sourceId;
                    if (Str::isUuid($sId)) {
                        $q->where('activity_id', $sId);
                        if (Schema::hasColumn('coins_ledger', 'source_id')) {
                            $q->orWhere('source_id', $sId);
                        }
                        if (Schema::hasColumn('coins_ledger', 'reference_id')) {
                            $q->orWhere('reference_id', $sId);
                        }
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->get();

            foreach ($sourceLedgers as $row) {
                if (! in_array($row->transaction_id, $txIds, true)) {
                    $txIds[] = $row->transaction_id;
                    $totalCoins += (int) $row->amount;
                }
            }

            // Fallback: check activities table if activity has recorded coins_awarded
            if (empty($txIds) && Schema::hasTable('activities')) {
                $actRow = DB::table('activities')->where('id', $sourceId)->first();
                if ($actRow && (int) ($actRow->coins_awarded ?? 0) > 0) {
                    $totalCoins = max($totalCoins, (int) $actRow->coins_awarded);
                }
            }
        }

        return [
            'amount' => $totalCoins,
            'tx_ids' => $txIds,
        ];
    }

    /**
     * Locate life impact points awarded specifically for this post or its source activity.
     */
    public function findAwardedImpactPoints(Post $post): array
    {
        $targetUser = $this->resolveTargetUser($post);
        $userId = (string) ($targetUser?->id ?? $post->user_id);
        $sourceType = $post->source_type;
        $sourceId = $post->source_id;

        $totalImpact = 0;
        $historyIds = [];

        if (! Schema::hasTable('life_impact_histories')) {
            return ['points' => 0, 'history_ids' => []];
        }

        $tableName = $this->getLifeImpactTable();
        $pId = (string) $post->id;
        $sId = (string) ($sourceId ?? '');

        if (Str::isUuid($pId)) {
            $alreadyReversedImpact = DB::table($tableName)
                ->where('user_id', $userId)
                ->where('impact_value', '<', 0)
                ->where('activity_id', $pId)
                ->exists();

            if ($alreadyReversedImpact) {
                return ['points' => 0, 'history_ids' => []];
            }
        }

        $query = DB::table($tableName)
            ->where('user_id', $userId)
            ->where('impact_value', '>', 0)
            ->where(function ($q) use ($pId, $sId, $sourceType) {
                $hasCond = false;
                if (Str::isUuid($pId)) {
                    $q->where('activity_id', $pId);
                    $hasCond = true;
                }
                if (Str::isUuid($sId)) {
                    if ($hasCond) {
                        $q->orWhere('activity_id', $sId);
                    } else {
                        $q->where('activity_id', $sId);
                        $hasCond = true;
                    }
                    if (filled($sourceType)) {
                        $q->orWhere(function ($sub) use ($sourceType, $sId) {
                            $sub->where('activity_type', $sourceType)
                                ->where('activity_id', $sId);
                        });
                    }
                }
                if (! $hasCond) {
                    $q->whereRaw('1 = 0');
                }
            });

        $histories = $query->get();

        foreach ($histories as $ih) {
            if (! in_array($ih->id, $historyIds, true)) {
                $historyIds[] = $ih->id;
                $totalImpact += (int) $ih->impact_value;
            }
        }

        // Recognition posts (e.g. Life Impact Legacy Maker / 2,500 lives) fallback
        if ($totalImpact === 0 && ($post->post_type === 'life_impact_recognition' || $sourceType === 'life_impact')) {
            if (preg_match('/level_(\d+)/', (string) $post->source_event, $matches)) {
                $totalImpact = (int) $matches[1];
            } elseif (preg_match('/impacting\s*([\d,]+)\s*lives/i', (string) ($post->content_text ?: $post->title), $matches)) {
                $totalImpact = (int) str_replace(',', '', $matches[1]);
            } elseif (is_array($post->tags)) {
                foreach ($post->tags as $tag) {
                    if (preg_match('/level_(\d+)/', (string) $tag, $matches)) {
                        $totalImpact = (int) $matches[1];
                        break;
                    }
                }
            }
        }

        return [
            'points' => $totalImpact,
            'history_ids' => $historyIds,
        ];
    }

    /**
     * Resolve the correct schema-qualified life_impact_histories table name.
     */
    public function getLifeImpactTable(): string
    {
        $defaultConn = config('database.default', 'pgsql');
        if ($defaultConn === 'sqlite') {
            return 'life_impact_histories';
        }

        $searchPath = (string) config('database.connections.'.$defaultConn.'.search_path', 'public');
        $schema = trim((string) explode(',', $searchPath)[0], " \t\n\r\0\x0B\"");

        return ($schema !== '' ? $schema.'.' : '').'life_impact_histories';
    }

    /**
     * Reverse coins in user balance and create negative ledger entry.
     */
    protected function reverseCoins(
        string $userId,
        int $coinsToReverse,
        Post $post,
        string $remarks,
        ?string $adminId,
        array $matchedCoinTxIds
    ): int {
        $user = User::query()->where('id', $userId)->lockForUpdate()->first();
        if (! $user) {
            return 0;
        }

        $currentBalance = (int) ($user->coins_balance ?? 0);
        $newBalance = max(0, $currentBalance - $coinsToReverse);
        $actualDeducted = $currentBalance - $newBalance;

        $user->coins_balance = $newBalance;
        $user->save();

        $ledgerRemark = 'Post deactivation reversal: ' . $remarks;
        if ($actualDeducted < $coinsToReverse) {
            $ledgerRemark .= " (Note: User available balance was {$currentBalance}; deducted {$actualDeducted} of {$coinsToReverse} awarded coins).";
        }

        $activityIdForLedger = null;
        if (filled($post->source_id) && Schema::hasTable('activities')) {
            $existsInActivities = DB::table('activities')->where('id', (string) $post->source_id)->exists();
            if ($existsInActivities) {
                $activityIdForLedger = (string) $post->source_id;
            }
        }

        $creatorUuid = null;
        if ($adminId && Str::isUuid((string) $adminId) && DB::table('users')->where('id', (string) $adminId)->exists()) {
            $creatorUuid = (string) $adminId;
        } elseif (Str::isUuid((string) $userId) && DB::table('users')->where('id', (string) $userId)->exists()) {
            $creatorUuid = (string) $userId;
        }

        $coinInsert = [
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'amount' => -$coinsToReverse,
            'balance_after' => $newBalance,
            'activity_id' => $activityIdForLedger,
            'reference' => 'Post deactivation reversal: ' . $post->id,
            'created_by' => $creatorUuid,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('coins_ledger', 'remark')) {
            $coinInsert['remark'] = $ledgerRemark;
        }
        if (Schema::hasColumn('coins_ledger', 'entry_type')) {
            $coinInsert['entry_type'] = 'DEACTIVATION_REVERSAL';
        }
        if (Schema::hasColumn('coins_ledger', 'bucket')) {
            $coinInsert['bucket'] = 'EARNED';
        }
        if (Schema::hasColumn('coins_ledger', 'reference_type')) {
            $coinInsert['reference_type'] = 'POST_DEACTIVATION';
        }
        if (Schema::hasColumn('coins_ledger', 'reference_id')) {
            $coinInsert['reference_id'] = (string) $post->id;
        }
        if (Schema::hasColumn('coins_ledger', 'metadata')) {
            $coinInsert['metadata'] = json_encode([
                'post_id' => (string) $post->id,
                'source_type' => $post->source_type,
                'source_id' => $post->source_id,
                'admin_remarks' => $remarks,
                'admin_id' => $adminId,
                'awarded_coins' => $coinsToReverse,
                'actual_deducted' => $actualDeducted,
                'matched_tx_ids' => $matchedCoinTxIds,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        DB::table('coins_ledger')->insert($coinInsert);

        return $coinsToReverse;
    }

    /**
     * Reverse life impact score and create negative impact history entry.
     */
    protected function reverseImpactPoints(
        string $userId,
        int $impactToReverse,
        Post $post,
        string $remarks,
        ?string $adminId,
        array $matchedImpactIds
    ): int {
        try {
            $user = User::query()->where('id', $userId)->lockForUpdate()->first();
            if (! $user) {
                return 0;
            }

            $currentScore = (int) ($user->life_impacted_count ?? 0);
            $newScore = max(0, $currentScore - $impactToReverse);
            $user->life_impacted_count = $newScore;
            $user->save();

            $tableName = $this->getLifeImpactTable();
            if (Schema::hasTable('life_impact_histories')) {
                $triggeredBy = null;
                if ($adminId && Str::isUuid((string) $adminId) && DB::table('users')->where('id', (string) $adminId)->exists()) {
                    $triggeredBy = (string) $adminId;
                } elseif (Str::isUuid((string) $userId) && DB::table('users')->where('id', (string) $userId)->exists()) {
                    $triggeredBy = (string) $userId;
                }

                $insertData = [
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'triggered_by_user_id' => $triggeredBy,
                    'activity_type' => 'post_deactivation_reversal',
                    'activity_id' => (string) $post->id,
                    'impact_value' => -$impactToReverse,
                    'title' => 'Post Deactivated - Life Impact Reversed',
                    'description' => 'Life impact reversed due to post deactivation. Reason: ' . $remarks,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('life_impact_histories', 'impact_after')) {
                    $insertData['impact_after'] = $newScore;
                }
                if (Schema::hasColumn('life_impact_histories', 'life_impacted')) {
                    $insertData['life_impacted'] = -$impactToReverse;
                }
                if (Schema::hasColumn('life_impact_histories', 'action_key')) {
                    $insertData['action_key'] = 'post_deactivation_reversal';
                }
                if (Schema::hasColumn('life_impact_histories', 'action_label')) {
                    $insertData['action_label'] = 'Post Deactivation Reversal';
                }
                if (Schema::hasColumn('life_impact_histories', 'impact_category')) {
                    $insertData['impact_category'] = 'moderation';
                }
                if (Schema::hasColumn('life_impact_histories', 'status')) {
                    $insertData['status'] = 'approved';
                }
                if (Schema::hasColumn('life_impact_histories', 'remarks')) {
                    $insertData['remarks'] = $remarks;
                }
                if (Schema::hasColumn('life_impact_histories', 'counted_in_total')) {
                    $insertData['counted_in_total'] = true;
                }
                if (Schema::hasColumn('life_impact_histories', 'meta')) {
                    $insertData['meta'] = json_encode([
                        'post_id' => (string) $post->id,
                        'source_type' => $post->source_type,
                        'source_id' => $post->source_id,
                        'admin_remarks' => $remarks,
                        'admin_id' => $adminId,
                        'reversed_points' => $impactToReverse,
                        'matched_impact_ids' => $matchedImpactIds,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                DB::table($tableName)->insert($insertData);
            }

            try {
                if (class_exists(\App\Services\MilestoneBadgeService::class)) {
                    app(\App\Services\MilestoneBadgeService::class)->calculateForUserId($userId);
                }
            } catch (\Throwable $e) {
                // non-critical milestone sync error
            }

            return $impactToReverse;
        } catch (\Throwable $e) {
            Log::error('[PostDeactivationService] Failed reversing life impact: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update post status and audit fields.
     */
    protected function updatePostDeactivatedStatus(Post $post, string $remarks, ?string $adminId): void
    {
        if (Schema::hasColumn('posts', 'is_active')) {
            $post->setAttribute('is_active', false);
        }
        if (array_key_exists('is_deleted', $post->getAttributes()) || Schema::hasColumn('posts', 'is_deleted')) {
            $post->is_deleted = true;
        }
        $post->active = false;
        $post->status = 'inactive';

        if (Schema::hasColumn('posts', 'deactivation_remarks')) {
            $post->deactivation_remarks = $remarks;
        }
        if (Schema::hasColumn('posts', 'deactivated_by')) {
            $post->deactivated_by = ($adminId && Str::isUuid((string) $adminId)) ? (string) $adminId : null;
        }
        if (Schema::hasColumn('posts', 'deactivated_at')) {
            $post->deactivated_at = now();
        }

        $post->save();

        if (method_exists($post, 'delete') && ! $post->trashed()) {
            $post->delete();
        }
    }

    /**
     * Ensure any image or media URL is a fully qualified public URL accessible by mobile devices and FCM.
     */
    public function normalizePublicUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $publicBase = class_exists(LifeImpactCreativeGenerator::class)
            ? LifeImpactCreativeGenerator::getPublicBaseUrl()
            : 'https://peersunity.com';

        // If it starts with http:// or https://
        if (preg_match('~^https?://~i', $url)) {
            // If it's a localhost, 127.0.0.1, or private IP, swap with public base URL
            if (preg_match('~^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0|10\.0\.2\.2)(:\d+)?(/.*)?$~i', $url, $matches)) {
                $path = $matches[3] ?? '';
                $url = rtrim($publicBase, '/') . '/' . ltrim($path, '/');
            }
        } else {
            // Relative path
            $url = rtrim($publicBase, '/') . '/' . ltrim($url, '/');
        }

        // Encode spaces in URL path so FCM HTTP v1 / Android doesn't drop the image
        return str_replace(' ', '%20', $url);
    }

    /**
     * Resolve the best publicly accessible image URL representing the post.
     */
    public function resolvePostImageUrl(Post $post): ?string
    {
        $publicBase = class_exists(LifeImpactCreativeGenerator::class)
            ? LifeImpactCreativeGenerator::getPublicBaseUrl()
            : 'https://peersunity.com';

        // 1. Direct post image attribute
        if (! empty($post->image)) {
            $normalized = $this->normalizePublicUrl((string) $post->image);
            if ($normalized) {
                return $normalized;
            }
        }

        // 2. Media array (e.g. uploaded media or generated file items)
        $mediaItems = is_array($post->media) ? $post->media : [];
        if (! empty($mediaItems)) {
            foreach ($mediaItems as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $fileId = $item['id'] ?? $item['file_id'] ?? null;
                if ($fileId && Str::isUuid((string) $fileId)) {
                    if (class_exists(File::class)) {
                        $fileModel = File::find($fileId);
                        if ($fileModel && ! empty($fileModel->s3_key)) {
                            return $this->normalizePublicUrl('storage/' . ltrim($fileModel->s3_key, '/'));
                        }
                    }

                    return $this->normalizePublicUrl("api/v1/files/{$fileId}");
                }

                if (! empty($item['url'])) {
                    $normalized = $this->normalizePublicUrl((string) $item['url']);
                    if ($normalized) {
                        return $normalized;
                    }
                }
            }
        }

        // 3. Post media_url attribute
        if (! empty($post->media_url)) {
            $normalized = $this->normalizePublicUrl((string) $post->media_url);
            if ($normalized) {
                return $normalized;
            }
        }

        // 4. Special recognition / Life Impact creative handling
        if ($post->post_type === 'life_impact_recognition' || $post->source_type === 'life_impact') {
            $targetUser = $this->resolveTargetUser($post);

            $threshold = 2500;
            if (preg_match('/level_(\d+)/', (string) $post->source_event, $matches)) {
                $threshold = (int) $matches[1];
            } elseif (preg_match('/impacting\s*([\d,]+)\s*lives/i', (string) ($post->content_text ?: $post->title), $matches)) {
                $threshold = (int) str_replace(',', '', $matches[1]);
            } elseif ($targetUser && (int) ($targetUser->life_impacted_count ?? 0) > 0) {
                $threshold = (int) $targetUser->life_impacted_count;
            }

            // Check existing LifeImpactCreative record
            if ($targetUser && Schema::hasTable('life_impact_creatives') && class_exists(LifeImpactCreative::class)) {
                try {
                    $existing = LifeImpactCreative::query()
                        ->where('user_id', $targetUser->id)
                        ->where('threshold', $threshold)
                        ->latest()
                        ->first();
                    if ($existing && ! empty($existing->image_url)) {
                        return $this->normalizePublicUrl((string) $existing->image_url);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // Try generating high-res personalized certificate
            if ($targetUser && class_exists(LifeImpactCreativeGenerator::class)) {
                try {
                    $generator = app(LifeImpactCreativeGenerator::class);
                    $fileModel = $generator->generate($targetUser, $threshold, $threshold);
                    if ($fileModel && ! empty($fileModel->s3_key)) {
                        return $this->normalizePublicUrl('storage/' . ltrim($fileModel->s3_key, '/'));
                    }
                } catch (\Throwable $e) {
                    Log::warning('[PostDeactivationService] Failed generating creative for deactivation notification: ' . $e->getMessage());
                }
            }

            // Fallback to milestone badge template
            if (class_exists(LifeImpactCreativeGenerator::class)) {
                $meta = app(LifeImpactCreativeGenerator::class)->getRecognitionMeta($threshold);
                if (! empty($meta['badge_image'])) {
                    return $this->normalizePublicUrl($meta['badge_image']);
                }
            }

            return $this->normalizePublicUrl('images/life_impact_badges/Legacy Maker.png');
        }

        return null;
    }

    /**
     * Resolve the best image URL for an Impact post.
     */
    public function resolveImpactImageUrl(Impact $impact): ?string
    {
        // 1. Proof image / media
        if (! empty($impact->proof_image)) {
            $normalized = $this->normalizePublicUrl((string) $impact->proof_image);
            if ($normalized) {
                return $normalized;
            }
        }

        // 2. Attached media / files if relationship exists
        if (method_exists($impact, 'files') && $impact->relationLoaded('files')) {
            $first = $impact->files->first();
            if ($first && ! empty($first->s3_key)) {
                return $this->normalizePublicUrl('storage/' . ltrim($first->s3_key, '/'));
            }
        }

        // 3. Fallback to life impact default badge
        $threshold = max(25, (int) ($impact->life_impacted ?? 25));
        if (class_exists(LifeImpactCreativeGenerator::class)) {
            $meta = app(LifeImpactCreativeGenerator::class)->getRecognitionMeta($threshold);
            if (! empty($meta['badge_image'])) {
                return $this->normalizePublicUrl($meta['badge_image']);
            }
        }

        return $this->normalizePublicUrl('images/life_impact_badges/Impact Creator.png');
    }

    /**
     * Send in-app and push notification to the target peer.
     */
    protected function sendDeactivationNotification(
        Post $post,
        string $remarks,
        int $reversedCoins,
        int $reversedImpact
    ): void {
        try {
            $user = $this->resolveTargetUser($post);
            if (! $user) {
                return;
            }

            $decreaseParts = [];
            if ($reversedCoins > 0) {
                $decreaseParts[] = '-' . number_format($reversedCoins) . ' Peers Coins';
            }
            if ($reversedImpact > 0) {
                $decreaseParts[] = '-' . number_format($reversedImpact) . ' Life Impact';
            }

            if (! empty($decreaseParts)) {
                $title = 'Your Post Was Deactivated & Rewards Reversed';
                $body = "Your post was deactivated by admin. Reason: \"{$remarks}\". Deductions applied: " . implode(' and ', $decreaseParts) . ' from your account.';
            } else {
                $title = 'Your Post Has Been Deactivated';
                $body = "Your post was deactivated by admin. Reason: \"{$remarks}\".";
            }

            $postImageUrl = $this->resolvePostImageUrl($post);
            $postSnippet = Str::limit(trim((string) ($post->content_text ?: $post->title ?: '')), 140);

            // 1. AppNotification (Peers mobile app notification engine)
            $createdAppNotif = null;
            if (class_exists(AppNotification::class) && Schema::hasTable('app_notifications')) {
                $appData = [
                    'user_id' => $user->id,
                    'type' => 'post_deactivated',
                ];
                if (Schema::hasColumn('app_notifications', 'channel')) {
                    $appData['channel'] = 'push';
                }
                if (Schema::hasColumn('app_notifications', 'priority')) {
                    $appData['priority'] = 'high';
                }
                if (Schema::hasColumn('app_notifications', 'status')) {
                    $appData['status'] = 'sent';
                }
                if (Schema::hasColumn('app_notifications', 'title')) {
                    $appData['title'] = $title;
                }
                if (Schema::hasColumn('app_notifications', 'body')) {
                    $appData['body'] = $body;
                }
                if (Schema::hasColumn('app_notifications', 'category')) {
                    $appData['category'] = 'moderation';
                }
                if (Schema::hasColumn('app_notifications', 'reference_type')) {
                    $appData['reference_type'] = 'post';
                }
                if (Schema::hasColumn('app_notifications', 'reference_id')) {
                    $appData['reference_id'] = (string) $post->id;
                }
                if (Schema::hasColumn('app_notifications', 'screen')) {
                    $appData['screen'] = '/notifications';
                }
                if ($postImageUrl && Schema::hasColumn('app_notifications', 'image_url')) {
                    $appData['image_url'] = $postImageUrl;
                }

                $dataPayload = [
                    'post_id' => (string) $post->id,
                    'remarks' => $remarks,
                    'coins_reversed' => $reversedCoins,
                    'impact_reversed' => $reversedImpact,
                    'decrease_summary' => implode(' and ', $decreaseParts),
                    'notification_type' => 'post_deactivated',
                    'type' => 'post_deactivated',
                    'title' => $title,
                    'body' => $body,
                    'screen' => '/notifications',
                    'tap_destination' => '/notifications',
                ];
                if ($postImageUrl) {
                    $dataPayload['image_url'] = $postImageUrl;
                    $dataPayload['post_image'] = $postImageUrl;
                    $dataPayload['media_url'] = $postImageUrl;
                }
                if (! empty($postSnippet)) {
                    $dataPayload['post_content'] = $postSnippet;
                }
                if (! empty($post->title)) {
                    $dataPayload['post_title'] = $post->title;
                }

                if (Schema::hasColumn('app_notifications', 'data')) {
                    $appData['data'] = $dataPayload;
                }
                if (Schema::hasColumn('app_notifications', 'dedupe_key')) {
                    $appData['dedupe_key'] = 'post_deactivation_' . $post->id . '_' . time();
                }

                $createdAppNotif = AppNotification::create($appData);
            }

            // 2. Legacy Notification model (used by /api/notifications)
            if (class_exists(Notification::class) && Schema::hasTable('notifications')) {
                $notifData = [
                    'user_id' => $user->id,
                    'type' => 'activity_update',
                ];

                if (Schema::hasColumn('notifications', 'title')) {
                    $notifData['title'] = $title;
                }
                if (Schema::hasColumn('notifications', 'message')) {
                    $notifData['message'] = $body;
                }
                if (Schema::hasColumn('notifications', 'body')) {
                    $notifData['body'] = $body;
                }
                if (Schema::hasColumn('notifications', 'source_type')) {
                    $notifData['source_type'] = 'post';
                }
                if (Schema::hasColumn('notifications', 'source_id')) {
                    $notifData['source_id'] = (string) $post->id;
                }
                if ($postImageUrl && Schema::hasColumn('notifications', 'image_url')) {
                    $notifData['image_url'] = $postImageUrl;
                }

                $legacyPayload = [
                    'post_id' => (string) $post->id,
                    'remarks' => $remarks,
                    'coins_reversed' => $reversedCoins,
                    'impact_reversed' => $reversedImpact,
                    'decrease_summary' => implode(' and ', $decreaseParts),
                    'notification_type' => 'post_deactivated',
                    'type' => 'post_deactivated',
                    'title' => $title,
                    'message' => $body,
                    'body' => $body,
                    'screen' => '/notifications',
                ];
                if ($postImageUrl) {
                    $legacyPayload['image_url'] = $postImageUrl;
                    $legacyPayload['post_image'] = $postImageUrl;
                    $legacyPayload['media_url'] = $postImageUrl;
                }
                if (! empty($postSnippet)) {
                    $legacyPayload['post_content'] = $postSnippet;
                }

                if (Schema::hasColumn('notifications', 'payload')) {
                    $notifData['payload'] = $legacyPayload;
                }
                if (Schema::hasColumn('notifications', 'data')) {
                    $notifData['data'] = $legacyPayload;
                }
                if (Schema::hasColumn('notifications', 'is_read')) {
                    $notifData['is_read'] = false;
                }
                if (Schema::hasColumn('notifications', 'created_at')) {
                    $notifData['created_at'] = now();
                }

                Notification::create($notifData);
            }

            // 3. Dispatch real-time Push Notification to peer device
            try {
                if (class_exists(SendPushNotificationJob::class)) {
                    $pushPayload = [
                        'post_id' => (string) $post->id,
                        'type' => 'post_deactivated',
                        'notification_type' => 'post_deactivated',
                        'screen' => '/notifications',
                        'coins_reversed' => (string) $reversedCoins,
                        'impact_reversed' => (string) $reversedImpact,
                    ];
                    if ($postImageUrl) {
                        $pushPayload['image_url'] = $postImageUrl;
                        $pushPayload['post_image'] = $postImageUrl;
                        $pushPayload['media_url'] = $postImageUrl;
                    }
                    if (! empty($postSnippet)) {
                        $pushPayload['post_content'] = $postSnippet;
                    }
                    if ($createdAppNotif && $createdAppNotif->id) {
                        $pushPayload['notification_id'] = (string) $createdAppNotif->id;
                    }

                    SendPushNotificationJob::dispatch(
                        $user,
                        $title,
                        $body,
                        $pushPayload
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[PostDeactivationService] Failed dispatching push notification for post deactivation: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::warning('[PostDeactivationService] Failed sending deactivation notification: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate an impact story post, optionally reverse life impact score, and notify peer.
     */
    public function deactivateImpact(
        Impact $impact,
        string $remarks,
        ?string $adminId = null,
        bool $reverseCoins = false,
        bool $reverseImpact = true,
        bool $notifyUser = true,
        ?int $customImpactAmount = null
    ): array {
        $remarks = trim($remarks);

        if ($impact->timeline_posted_at === null) {
            return [
                'success' => false,
                'already_deactivated' => true,
                'message' => 'This impact post is already deactivated.',
                'coins_reversed' => 0,
                'impact_reversed' => 0,
                'post_id' => $impact->id,
            ];
        }

        $result = DB::transaction(function () use ($impact, $remarks, $adminId, $reverseImpact, $notifyUser, $customImpactAmount) {
            $userId = (string) $impact->user_id;

            // 1. Deactivate timeline publication
            $impact->timeline_posted_at = null;
            if (Schema::hasColumn('impacts', 'additional_remarks')) {
                $prevRemarks = $impact->additional_remarks ? $impact->additional_remarks . ' | ' : '';
                $impact->additional_remarks = Str::limit($prevRemarks . 'Deactivated: ' . $remarks, 1000);
            }
            $impact->save();

            // 2. Reverse Life Impact Points if requested
            $reversedImpact = 0;
            $impactPoints = $customImpactAmount !== null ? max(0, $customImpactAmount) : (int) ($impact->life_impacted ?? 1);
            if ($reverseImpact && $impactPoints > 0) {
                $user = User::query()->where('id', $userId)->lockForUpdate()->first();
                if ($user) {
                    $currentScore = (int) ($user->life_impacted_count ?? 0);
                    $newScore = max(0, $currentScore - $impactPoints);
                    $user->life_impacted_count = $newScore;
                    $user->save();

                    $tableName = $this->getLifeImpactTable();
                    if (Schema::hasTable('life_impact_histories')) {
                        $insertData = [
                            'id' => (string) Str::uuid(),
                            'user_id' => $userId,
                            'triggered_by_user_id' => ($adminId && Str::isUuid((string) $adminId)) ? (string) $adminId : null,
                            'activity_type' => 'impact_deactivation_reversal',
                            'activity_id' => (string) $impact->id,
                            'impact_value' => -$impactPoints,
                            'title' => 'Impact Post Deactivated - Score Reversed',
                            'description' => 'Life impact reversed due to impact deactivation. Reason: ' . $remarks,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        if (Schema::hasColumn('life_impact_histories', 'impact_after')) {
                            $insertData['impact_after'] = $newScore;
                        }
                        if (Schema::hasColumn('life_impact_histories', 'life_impacted')) {
                            $insertData['life_impacted'] = -$impactPoints;
                        }
                        if (Schema::hasColumn('life_impact_histories', 'action_key')) {
                            $insertData['action_key'] = 'impact_deactivation_reversal';
                        }
                        if (Schema::hasColumn('life_impact_histories', 'action_label')) {
                            $insertData['action_label'] = 'Impact Deactivation Reversal';
                        }
                        if (Schema::hasColumn('life_impact_histories', 'status')) {
                            $insertData['status'] = 'approved';
                        }
                        if (Schema::hasColumn('life_impact_histories', 'remarks')) {
                            $insertData['remarks'] = $remarks;
                        }
                        DB::table($tableName)->insert($insertData);
                    }
                    $reversedImpact = $impactPoints;
                }
            }

            $msg = 'Impact post deactivated successfully.';
            if ($reversedImpact > 0) {
                $msg .= ' Reversed ' . $reversedImpact . ' life impact points.';
            } else {
                $msg .= ' Remarks recorded.';
            }

            if ($notifyUser) {
                $msg .= ' Member notified.';
            }

            return [
                'success' => true,
                'already_deactivated' => false,
                'coins_reversed' => 0,
                'impact_reversed' => $reversedImpact,
                'user_notified' => $notifyUser,
                'post_id' => $impact->id,
                'message' => $msg,
            ];
        });

        if ($notifyUser) {
            $this->sendImpactDeactivationNotification(
                $impact,
                $remarks,
                (int) ($result['impact_reversed'] ?? 0)
            );
        }

        return $result;
    }

    /**
     * Send in-app and push notification to the peer for impact deactivation.
     */
    protected function sendImpactDeactivationNotification(
        Impact $impact,
        string $remarks,
        int $reversedImpact
    ): void {
        try {
            $user = User::find($impact->user_id);
            if (! $user) {
                return;
            }

            if ($reversedImpact > 0) {
                $title = 'Your Impact Post Was Deactivated & Score Reversed';
                $body = "Your impact post was deactivated by admin. Reason: \"{$remarks}\". Deducted: -{$reversedImpact} Life Impact score from your account.";
            } else {
                $title = 'Your Impact Post Was Deactivated';
                $body = "Your impact post was deactivated by admin. Reason: \"{$remarks}\".";
            }

            $impactImageUrl = $this->resolveImpactImageUrl($impact);
            $impactSnippet = Str::limit(trim((string) ($impact->story_to_share ?: $impact->action ?: '')), 140);

            $createdAppNotif = null;
            if (class_exists(AppNotification::class) && Schema::hasTable('app_notifications')) {
                $appData = [
                    'user_id' => $user->id,
                    'type' => 'post_deactivated',
                    'category' => 'moderation',
                    'channel' => 'push',
                    'priority' => 'high',
                    'status' => 'sent',
                    'title' => $title,
                    'body' => $body,
                    'reference_type' => 'impact',
                    'reference_id' => (string) $impact->id,
                    'screen' => '/notifications',
                ];
                if ($impactImageUrl && Schema::hasColumn('app_notifications', 'image_url')) {
                    $appData['image_url'] = $impactImageUrl;
                }

                $dataPayload = [
                    'impact_id' => (string) $impact->id,
                    'remarks' => $remarks,
                    'impact_reversed' => $reversedImpact,
                    'coins_reversed' => 0,
                    'title' => $title,
                    'body' => $body,
                    'type' => 'post_deactivated',
                    'notification_type' => 'post_deactivated',
                    'screen' => '/notifications',
                    'tap_destination' => '/notifications',
                ];
                if ($impactImageUrl) {
                    $dataPayload['image_url'] = $impactImageUrl;
                    $dataPayload['post_image'] = $impactImageUrl;
                    $dataPayload['media_url'] = $impactImageUrl;
                }
                if (! empty($impactSnippet)) {
                    $dataPayload['post_content'] = $impactSnippet;
                }

                $appData['data'] = $dataPayload;
                $appData['sent_at'] = now();

                $createdAppNotif = AppNotification::create($appData);
            }

            if (class_exists(Notification::class) && Schema::hasTable('notifications')) {
                $legacyPayload = [
                    'impact_id' => (string) $impact->id,
                    'remarks' => $remarks,
                    'impact_reversed' => $reversedImpact,
                    'title' => $title,
                    'body' => $body,
                    'notification_type' => 'post_deactivated',
                    'screen' => '/notifications',
                ];
                if ($impactImageUrl) {
                    $legacyPayload['image_url'] = $impactImageUrl;
                    $legacyPayload['post_image'] = $impactImageUrl;
                    $legacyPayload['media_url'] = $impactImageUrl;
                }
                if (! empty($impactSnippet)) {
                    $legacyPayload['post_content'] = $impactSnippet;
                }

                $notifData = [
                    'user_id' => $user->id,
                    'type' => 'activity_update',
                    'title' => $title,
                    'message' => $body,
                    'source_type' => 'impact',
                    'source_id' => (string) $impact->id,
                    'payload' => $legacyPayload,
                    'data' => $legacyPayload,
                    'is_read' => false,
                    'created_at' => now(),
                ];
                if ($impactImageUrl && Schema::hasColumn('notifications', 'image_url')) {
                    $notifData['image_url'] = $impactImageUrl;
                }

                Notification::create($notifData);
            }

            try {
                if (class_exists(SendPushNotificationJob::class)) {
                    $pushPayload = [
                        'impact_id' => (string) $impact->id,
                        'type' => 'post_deactivated',
                        'notification_type' => 'post_deactivated',
                        'screen' => '/notifications',
                        'impact_reversed' => (string) $reversedImpact,
                    ];
                    if ($impactImageUrl) {
                        $pushPayload['image_url'] = $impactImageUrl;
                        $pushPayload['post_image'] = $impactImageUrl;
                        $pushPayload['media_url'] = $impactImageUrl;
                    }
                    if (! empty($impactSnippet)) {
                        $pushPayload['post_content'] = $impactSnippet;
                    }
                    if ($createdAppNotif && $createdAppNotif->id) {
                        $pushPayload['notification_id'] = (string) $createdAppNotif->id;
                    }

                    SendPushNotificationJob::dispatch(
                        $user,
                        $title,
                        $body,
                        $pushPayload
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[PostDeactivationService] Failed dispatching push notification for impact deactivation: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::warning('[PostDeactivationService] Failed sending impact deactivation notification: ' . $e->getMessage());
        }
    }

    /**
     * Get preview data for post deactivation (coins & impact points to reverse, current balance, etc.).
     */
    public function getDeactivationPreview(Post $post): array
    {
        $user = $this->resolveTargetUser($post);
        $coinsData = $this->findAwardedCoins($post);
        $impactData = $this->findAwardedImpactPoints($post);

        $currentCoins = (int) ($user?->coins_balance ?? 0);
        $currentImpact = (int) ($user?->life_impacted_count ?? 0);

        $coinsToReverse = (int) ($coinsData['amount'] ?? 0);
        $impactToReverse = (int) ($impactData['points'] ?? 0);

        $imageUrl = $this->resolvePostImageUrl($post);

        return [
            'item_id' => (string) $post->id,
            'item_type' => 'post',
            'user_id' => (string) ($user?->id ?? $post->user_id),
            'user_name' => $user?->display_name ?: $user?->name ?: 'Member',
            'user_company' => $user?->company_name ?? $user?->company ?? $user?->business_name ?? '—',
            'image_url' => $imageUrl,
            'current_coins' => $currentCoins,
            'current_impact' => $currentImpact,
            'coins_to_reverse' => $coinsToReverse,
            'impact_to_reverse' => $impactToReverse,
            'coins_after' => max(0, $currentCoins - $coinsToReverse),
            'impact_after' => max(0, $currentImpact - $impactToReverse),
        ];
    }

    /**
     * Get preview data for impact deactivation.
     */
    public function getImpactDeactivationPreview(Impact $impact): array
    {
        $user = $impact->user ?: User::find($impact->user_id);
        $impactPoints = (int) ($impact->life_impacted ?? 1);

        $currentCoins = (int) ($user?->coins_balance ?? 0);
        $currentImpact = (int) ($user?->life_impacted_count ?? 0);
        $imageUrl = $this->resolveImpactImageUrl($impact);

        return [
            'item_id' => (string) $impact->id,
            'item_type' => 'impact',
            'user_id' => (string) ($user?->id ?? $impact->user_id),
            'user_name' => $user?->display_name ?: $user?->name ?: 'Member',
            'user_company' => $user?->company_name ?? $user?->company ?? $user?->business_name ?? '—',
            'image_url' => $imageUrl,
            'current_coins' => $currentCoins,
            'current_impact' => $currentImpact,
            'coins_to_reverse' => 0,
            'impact_to_reverse' => $impactPoints,
            'coins_after' => $currentCoins,
            'impact_after' => max(0, $currentImpact - $impactPoints),
        ];
    }

    /**
     * Efficiently batch-calculate deactivation previews for a collection of posts and impacts.
     */
    public function getBatchDeactivationPreviews(Collection $posts, Collection $impacts): array
    {
        $results = ['posts' => [], 'impacts' => []];

        $targetUserMap = [];
        foreach ($posts as $p) {
            $targetUser = $this->resolveTargetUser($p);
            $targetUserMap[(string) $p->id] = $targetUser;
        }

        $allPostIds = $posts->pluck('id')->map(fn ($id) => (string) $id)->all();
        $allSourceIds = $posts->pluck('source_id')->filter()->map(fn ($id) => (string) $id)->all();
        $allUserIds = collect(array_values($targetUserMap))->filter()->pluck('id')->merge($impacts->pluck('user_id'))->filter()->map(fn ($id) => (string) $id)->unique()->all();

        $combinedTargetIds = array_unique(array_merge($allPostIds, $allSourceIds));

        // 1. Batch query coins_ledger
        $coinsByTarget = [];
        if (! empty($combinedTargetIds) && Schema::hasTable('coins_ledger')) {
            $ledgerRows = DB::table('coins_ledger')
                ->whereIn('user_id', $allUserIds)
                ->where('amount', '>', 0)
                ->where(function ($q) use ($combinedTargetIds) {
                    if (Schema::hasColumn('coins_ledger', 'reference_id')) {
                        $q->whereIn('reference_id', $combinedTargetIds);
                    }
                    if (Schema::hasColumn('coins_ledger', 'source_id')) {
                        $q->orWhereIn('source_id', $combinedTargetIds);
                    }
                    $q->orWhereIn('activity_id', $combinedTargetIds);
                })
                ->get();

            foreach ($ledgerRows as $row) {
                $amt = (int) $row->amount;
                if (isset($row->reference_id) && $row->reference_id) {
                    $coinsByTarget[(string) $row->reference_id] = ($coinsByTarget[(string) $row->reference_id] ?? 0) + $amt;
                }
                if (isset($row->source_id) && $row->source_id) {
                    $coinsByTarget[(string) $row->source_id] = ($coinsByTarget[(string) $row->source_id] ?? 0) + $amt;
                }
                if (isset($row->activity_id) && $row->activity_id) {
                    $coinsByTarget[(string) $row->activity_id] = ($coinsByTarget[(string) $row->activity_id] ?? 0) + $amt;
                }
            }
        }

        // 2. Batch query life_impact_histories
        $impactByTarget = [];
        if (! empty($combinedTargetIds) && Schema::hasTable('life_impact_histories')) {
            $tableName = $this->getLifeImpactTable();
            $historyRows = DB::table($tableName)
                ->whereIn('user_id', $allUserIds)
                ->where('impact_value', '>', 0)
                ->whereIn('activity_id', $combinedTargetIds)
                ->get();

            foreach ($historyRows as $h) {
                $val = (int) $h->impact_value;
                $impactByTarget[(string) $h->activity_id] = ($impactByTarget[(string) $h->activity_id] ?? 0) + $val;
            }
        }

        foreach ($posts as $post) {
            $pId = (string) $post->id;
            $sId = (string) ($post->source_id ?? '');
            $user = $targetUserMap[$pId] ?? $this->resolveTargetUser($post);
            $coins = $coinsByTarget[$pId] ?? ($sId ? ($coinsByTarget[$sId] ?? 0) : 0);
            $impact = $impactByTarget[$pId] ?? ($sId ? ($impactByTarget[$sId] ?? 0) : 0);

            // Recognition post fallback if not in impact_histories
            if ($impact === 0 && ($post->post_type === 'life_impact_recognition' || $post->source_type === 'life_impact')) {
                if (preg_match('/level_(\d+)/', (string) $post->source_event, $matches)) {
                    $impact = (int) $matches[1];
                } elseif (preg_match('/impacting\s*([\d,]+)\s*lives/i', (string) ($post->content_text ?: $post->title), $matches)) {
                    $impact = (int) str_replace(',', '', $matches[1]);
                }
            }

            $currentCoins = (int) ($user?->coins_balance ?? 0);
            $currentImpact = (int) ($user?->life_impacted_count ?? 0);

            $results['posts'][$pId] = [
                'item_id' => $pId,
                'item_type' => 'post',
                'user_id' => (string) ($user?->id ?? $post->user_id),
                'user_name' => $user?->display_name ?: $user?->name ?: 'Member',
                'user_company' => $user?->company_name ?? $user?->company ?? $user?->business_name ?? '—',
                'current_coins' => $currentCoins,
                'current_impact' => $currentImpact,
                'coins_to_reverse' => $coins,
                'impact_to_reverse' => $impact,
                'coins_after' => max(0, $currentCoins - $coins),
                'impact_after' => max(0, $currentImpact - $impact),
            ];
        }

        foreach ($impacts as $imp) {
            $impId = (string) $imp->id;
            $impactPoints = (int) ($imp->life_impacted ?? 1);
            $user = $imp->user;
            $currentCoins = (int) ($user?->coins_balance ?? 0);
            $currentImpact = (int) ($user?->life_impacted_count ?? 0);

            $results['impacts'][$impId] = [
                'item_id' => $impId,
                'item_type' => 'impact',
                'user_id' => (string) ($user?->id ?? $imp->user_id),
                'user_name' => $user?->display_name ?: $user?->name ?: 'Member',
                'user_company' => $user?->company_name ?? $user?->company ?? $user?->business_name ?? '—',
                'current_coins' => $currentCoins,
                'current_impact' => $currentImpact,
                'coins_to_reverse' => 0,
                'impact_to_reverse' => $impactPoints,
                'coins_after' => $currentCoins,
                'impact_after' => max(0, $currentImpact - $impactPoints),
            ];
        }

        return $results;
    }
}
