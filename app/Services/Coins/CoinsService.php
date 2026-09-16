<?php

namespace App\Services\Coins;

use App\Models\CoinsLedger;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CoinsService
{
    public function rewardForActivity(
        User $user,
        string $activityType,
        $activityId = null,
        ?string $reference = null,
        ?string $createdBy = null,
        ?string $sourceType = null,
        mixed $sourceId = null
    ): ?CoinsLedger {
        $amount = config('coins.activity_rewards')[$activityType] ?? 0;

        if ($amount === 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $activityType, $reference, $createdBy, $sourceType, $sourceId, $amount) {
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($sourceType !== null && $sourceId !== null && $this->hasSourceColumns()) {
                $existingLedger = CoinsLedger::query()
                    ->where('user_id', $user->id)
                    ->where('source_type', $sourceType)
                    ->where('source_id', (string) $sourceId)
                    ->first();

                if ($existingLedger) {
                    return $existingLedger;
                }
            }

            $newBalance = $user->coins_balance + $amount;

            $user->update([
                'coins_balance' => $newBalance,
            ]);

            $ledgerData = [
                'transaction_id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference' => $reference ?? ucfirst(str_replace('_', ' ', $activityType)).' reward',
                'created_by' => $createdBy ?? $user->id,
                'created_at' => now(),
            ];

            if ($sourceType !== null && $sourceId !== null && $this->hasSourceColumns()) {
                $ledgerData['source_type'] = $sourceType;
                $ledgerData['source_id'] = (string) $sourceId;
            }

            return CoinsLedger::create($ledgerData);
        });
    }

    public function reward(User $user, int $amount, string $reference, array|string|null $meta = null, ?string $createdBy = null): ?CoinsLedger
    {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $amount, $reference, $meta, $createdBy) {
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $newBalance = $user->coins_balance + $amount;

            $user->update([
                'coins_balance' => $newBalance,
            ]);

            $resolvedCreatedBy = $createdBy;
            $remark = null;

            if (is_string($meta) && $resolvedCreatedBy === null) {
                // Backward compatibility for old call sites passing createdBy as 4th argument.
                $resolvedCreatedBy = $meta;
            } elseif (is_array($meta) && ! empty($meta)) {
                $encodedMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $remark = $encodedMeta === false ? null : $encodedMeta;
            }

            $hasRemark = Schema::hasColumn('coins_ledger', 'remark');
            $hasSource = $this->hasSourceColumns();

            $ledgerData = [
                'transaction_id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference' => $reference,
                'created_by' => $resolvedCreatedBy ?? $user->id,
                'created_at' => now(),
            ];

            if ($hasRemark && $remark !== null) {
                $ledgerData['remark'] = $remark;
            }

            if ($hasSource && is_array($meta) && isset($meta['source'])) {
                $ledgerData['source_type'] = $meta['source'];
                $ledgerData['source_id'] = (string) ($meta['claim_id'] ?? $user->id);
            }

            return CoinsLedger::create($ledgerData);
        });
    }

    /**
     * Award 1,000 coins to the member when adding an introduction video for the first time.
     * Idempotent: Subsequent video replacements/updates will not award additional coins.
     */
    public function rewardForIntroVideo(User $user): ?CoinsLedger
    {
        $amount = (int) (config('coins.activity_rewards.introduction_video') ?? 1000);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $amount) {
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            // Check if coins have already been awarded for introduction video
            $hasLedgerColumns = $this->hasSourceColumns();
            $hasRemarkColumn = Schema::hasColumn('coins_ledger', 'remark');

            $alreadyRewarded = CoinsLedger::query()
                ->where('user_id', $user->id)
                ->where(function ($q) use ($hasLedgerColumns, $hasRemarkColumn) {
                    if ($hasLedgerColumns) {
                        $q->where('source_type', 'introduction_video');
                    }
                    $q->orWhere('reference', 'LIKE', '%Introduction Video%')
                        ->orWhere('reference', 'LIKE', '%introduction_video%');
                    if ($hasRemarkColumn) {
                        $q->orWhere('remark', 'LIKE', '%introduction_video%');
                    }
                })
                ->exists();

            if ($alreadyRewarded) {
                return null;
            }

            $newBalance = (int) $user->coins_balance + $amount;

            $user->update([
                'coins_balance' => $newBalance,
            ]);

            $ledgerData = [
                'transaction_id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference' => 'Introduction Video reward',
                'created_by' => $user->id,
                'created_at' => now(),
            ];

            if ($hasRemarkColumn) {
                $ledgerData['remark'] = json_encode(['activity_code' => 'introduction_video', 'action' => 'first_intro_video_upload']);
            }

            if ($hasLedgerColumns) {
                $ledgerData['source_type'] = 'introduction_video';
                $ledgerData['source_id'] = (string) $user->id;
            }

            $ledger = CoinsLedger::create($ledgerData);

            // Send notification for introduction video coin reward
            try {
                if (class_exists(Notification::class)) {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'activity_update',
                        'payload' => [
                            'notification_type' => 'introduction_video_reward',
                            'title' => 'Introduction Video Coins Added 🎥',
                            'body' => 'Congratulations! 1,000 coins have been added to your account for adding your introduction video.',
                            'coins_awarded' => $amount,
                            'coins_balance' => $newBalance,
                        ],
                        'is_read' => false,
                        'created_at' => now(),
                    ]);
                }

                if (class_exists(AppNotification::class)) {
                    AppNotification::create([
                        'user_id' => $user->id,
                        'type' => 'introduction_video_reward',
                        'category' => 'coin_reward',
                        'title' => 'Introduction Video Coins Added 🎥',
                        'body' => 'Congratulations! 1,000 coins have been added to your account for adding your introduction video.',
                        'message' => 'Congratulations! 1,000 coins have been added to your account for adding your introduction video.',
                        'channel' => 'push',
                        'priority' => 'medium',
                        'screen' => 'coin_wallet',
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Intro video notification failed', ['error' => $e->getMessage()]);
            }

            return $ledger;
        });
    }

    private function hasSourceColumns(): bool
    {
        return Schema::hasColumn('coins_ledger', 'source_type')
            && Schema::hasColumn('coins_ledger', 'source_id');
    }
}
