<?php

namespace App\Services\Posts;

use App\Jobs\SendPushNotificationJob;
use App\Models\Impact;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\Post;
use App\Models\User;
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
     * Deactivate a post, reverse awarded coins & life impact score, and notify user.
     *
     * @param  Post  $post
     * @param  string  $remarks
     * @param  string|null  $adminId
     * @param  bool  $reverseCoins
     * @param  bool  $reverseImpact
     * @param  bool  $notifyUser
     * @return array
     */
    public function deactivatePost(
        Post $post,
        string $remarks,
        ?string $adminId = null,
        bool $reverseCoins = true,
        bool $reverseImpact = true,
        bool $notifyUser = true
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

        $result = DB::transaction(function () use ($post, $remarks, $adminId, $reverseCoins, $reverseImpact, $notifyUser) {
            $userId = (string) $post->user_id;

            // 2. Identify exact coins awarded for this post or source activity
            $coinsRewardData = $this->findAwardedCoins($post);
            $coinsToReverse = $coinsRewardData['amount'];
            $matchedCoinTxIds = $coinsRewardData['tx_ids'];

            // 3. Identify exact life impact points awarded for this post or source activity
            $impactRewardData = $this->findAwardedImpactPoints($post);
            $impactToReverse = $impactRewardData['points'];
            $matchedImpactIds = $impactRewardData['history_ids'];

            // 4. Reverse Coins if requested and any were awarded
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

            // 5. Reverse Life Impact Points if requested and any were awarded
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
                $parts[] = $reversedImpact . ' life impact points';
            }

            $msg = 'Post deactivated successfully.';
            if (! empty($parts)) {
                $msg .= ' Reversed ' . implode(' and ', $parts) . '.';
            } else {
                $msg .= ' Remarks recorded.';
            }

            if ($notifyUser) {
                $msg .= ' Member notified.';
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

        // 7. Send in-app & push notification to the post owner (after transaction successfully commits)
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
        $userId = (string) $post->user_id;
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
                if (Schema::hasColumn('coins_ledger', 'reference_type') && Schema::hasColumn('coins_ledger', 'reference_id')) {
                    $q->where(function ($sub) use ($post) {
                        $sub->where('reference_type', 'POST')
                            ->where('reference_id', (string) $post->id);
                    });
                }
                if (Schema::hasColumn('coins_ledger', 'source_type') && Schema::hasColumn('coins_ledger', 'source_id')) {
                    $q->orWhere(function ($sub) use ($post) {
                        $sub->where('source_type', 'post')
                            ->where('source_id', (string) $post->id);
                    });
                }
                $q->orWhere('activity_id', (string) $post->id);
                $q->orWhere('reference', 'LIKE', '%Post%'.$post->id.'%');
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
                    $q->where('activity_id', (string) $sourceId);
                    if (Schema::hasColumn('coins_ledger', 'source_id')) {
                        $q->orWhere('source_id', (string) $sourceId);
                    }
                    if (Schema::hasColumn('coins_ledger', 'reference_id')) {
                        $q->orWhere('reference_id', (string) $sourceId);
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
        $userId = (string) $post->user_id;
        $sourceType = $post->source_type;
        $sourceId = $post->source_id;

        $totalImpact = 0;
        $historyIds = [];

        if (! Schema::hasTable('life_impact_histories')) {
            return ['points' => 0, 'history_ids' => []];
        }

        $tableName = $this->getLifeImpactTable();

        $alreadyReversedImpact = DB::table($tableName)
            ->where('user_id', $userId)
            ->where('impact_value', '<', 0)
            ->where('activity_id', (string) $post->id)
            ->exists();

        if ($alreadyReversedImpact) {
            return ['points' => 0, 'history_ids' => []];
        }

        $query = DB::table($tableName)
            ->where('user_id', $userId)
            ->where('impact_value', '>', 0)
            ->where(function ($q) use ($post, $sourceId, $sourceType) {
                $q->where('activity_id', (string) $post->id);
                if (filled($sourceId)) {
                    $q->orWhere('activity_id', (string) $sourceId);
                    if (filled($sourceType)) {
                        $q->orWhere(function ($sub) use ($sourceType, $sourceId) {
                            $sub->where('activity_type', $sourceType)
                                ->where('activity_id', (string) $sourceId);
                        });
                    }
                }
            });

        $histories = $query->get();

        foreach ($histories as $ih) {
            if (! in_array($ih->id, $historyIds, true)) {
                $historyIds[] = $ih->id;
                $totalImpact += (int) $ih->impact_value;
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
     * Send in-app and push notification to the post owner.
     */
    protected function sendDeactivationNotification(
        Post $post,
        string $remarks,
        int $reversedCoins,
        int $reversedImpact
    ): void {
        try {
            $user = User::find($post->user_id);
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

            // 1. AppNotification (Peers mobile app notification engine)
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
                if (Schema::hasColumn('app_notifications', 'data')) {
                    $appData['data'] = [
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
                }
                if (Schema::hasColumn('app_notifications', 'dedupe_key')) {
                    $appData['dedupe_key'] = 'post_deactivation_' . $post->id . '_' . time();
                }

                AppNotification::create($appData);
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
                if (Schema::hasColumn('notifications', 'payload')) {
                    $notifData['payload'] = [
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
                }
                if (Schema::hasColumn('notifications', 'data')) {
                    $notifData['data'] = [
                        'post_id' => (string) $post->id,
                        'remarks' => $remarks,
                        'coins_reversed' => $reversedCoins,
                        'impact_reversed' => $reversedImpact,
                        'message' => $body,
                    ];
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
                    SendPushNotificationJob::dispatch(
                        $user,
                        $title,
                        $body,
                        [
                            'post_id' => (string) $post->id,
                            'type' => 'post_deactivated',
                            'notification_type' => 'post_deactivated',
                            'screen' => '/notifications',
                            'coins_reversed' => (string) $reversedCoins,
                            'impact_reversed' => (string) $reversedImpact,
                        ]
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
        bool $notifyUser = true
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

        $result = DB::transaction(function () use ($impact, $remarks, $adminId, $reverseImpact, $notifyUser) {
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
            $impactPoints = (int) ($impact->life_impacted ?? 1);
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

            if (class_exists(AppNotification::class) && Schema::hasTable('app_notifications')) {
                AppNotification::create([
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
                    'data' => [
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
                    ],
                    'sent_at' => now(),
                ]);
            }

            if (class_exists(Notification::class) && Schema::hasTable('notifications')) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'activity_update',
                    'title' => $title,
                    'message' => $body,
                    'source_type' => 'impact',
                    'source_id' => (string) $impact->id,
                    'payload' => [
                        'impact_id' => (string) $impact->id,
                        'remarks' => $remarks,
                        'impact_reversed' => $reversedImpact,
                        'title' => $title,
                        'body' => $body,
                        'notification_type' => 'post_deactivated',
                        'screen' => '/notifications',
                    ],
                    'data' => [
                        'impact_id' => (string) $impact->id,
                        'remarks' => $remarks,
                        'impact_reversed' => $reversedImpact,
                    ],
                    'is_read' => false,
                    'created_at' => now(),
                ]);
            }

            try {
                if (class_exists(SendPushNotificationJob::class)) {
                    SendPushNotificationJob::dispatch(
                        $user,
                        $title,
                        $body,
                        [
                            'impact_id' => (string) $impact->id,
                            'type' => 'post_deactivated',
                            'notification_type' => 'post_deactivated',
                            'screen' => '/notifications',
                            'impact_reversed' => (string) $reversedImpact,
                        ]
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
        $user = $post->user ?: User::find($post->user_id);
        $coinsData = $this->findAwardedCoins($post);
        $impactData = $this->findAwardedImpactPoints($post);

        $currentCoins = (int) ($user?->coins_balance ?? 0);
        $currentImpact = (int) ($user?->life_impacted_count ?? 0);

        $coinsToReverse = (int) ($coinsData['amount'] ?? 0);
        $impactToReverse = (int) ($impactData['points'] ?? 0);

        return [
            'item_id' => (string) $post->id,
            'item_type' => 'post',
            'user_id' => (string) ($user?->id ?? $post->user_id),
            'user_name' => $user?->display_name ?: $user?->name ?: 'Member',
            'user_company' => $user?->company_name ?? $user?->company ?? $user?->business_name ?? '—',
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

        return [
            'item_id' => (string) $impact->id,
            'item_type' => 'impact',
            'user_id' => (string) ($user?->id ?? $impact->user_id),
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

    /**
     * Efficiently batch-calculate deactivation previews for a collection of posts and impacts.
     */
    public function getBatchDeactivationPreviews(Collection $posts, Collection $impacts): array
    {
        $results = ['posts' => [], 'impacts' => []];

        $allPostIds = $posts->pluck('id')->map(fn ($id) => (string) $id)->all();
        $allSourceIds = $posts->pluck('source_id')->filter()->map(fn ($id) => (string) $id)->all();
        $allUserIds = $posts->pluck('user_id')->merge($impacts->pluck('user_id'))->filter()->map(fn ($id) => (string) $id)->unique()->all();

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
            $coins = $coinsByTarget[$pId] ?? ($sId ? ($coinsByTarget[$sId] ?? 0) : 0);
            $impact = $impactByTarget[$pId] ?? ($sId ? ($impactByTarget[$sId] ?? 0) : 0);

            $user = $post->user;
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
