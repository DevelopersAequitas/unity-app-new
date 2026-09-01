<?php

namespace App\Services\LifeImpact;

use App\Models\Impact;
use App\Models\ImpactAction;
use App\Models\LifeImpactHistory;
<<<<<<< HEAD
use App\Models\Post;
use App\Models\User;
use App\Services\Creative\LifeImpactCreativeGenerator;
=======
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
use App\Services\MilestoneBadgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LifeImpactService
{
    public function addLifeImpact(
        string $userId,
        ?string $triggeredByUserId,
        string $activityType,
        ?string $activityId = null,
        int $impactValue = 0,
        string $title = '',
        ?string $description = null,
        array $meta = [],
    ): int {
        $impactValue = (int) $impactValue;
        $activityId = (is_string($activityId) && Str::isUuid($activityId))
            ? $activityId
            : null;

        if ($impactValue === 0) {
            return $this->getCurrentTotal($userId);
        }

        $oldTotal = $this->getCurrentTotal($userId);

        $newTotal = (int) DB::transaction(function () use ($userId, $impactValue, $activityType, $title, $triggeredByUserId, $activityId, $description, $meta) {
            $historyTable = $this->lifeImpactHistoriesTable();

            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'life_impacted_count' => DB::raw('COALESCE(life_impacted_count, 0) + '.$impactValue),
                    'updated_at' => now(),
                ]);

            app(MilestoneBadgeService::class)->calculateForUserId($userId);

            $newTotal = $this->getCurrentTotal($userId);

            $normalizedMeta = null;
            if (! empty($meta)) {
                $encodedMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $normalizedMeta = $encodedMeta === false ? null : $encodedMeta;
            }

            $actionKey = Str::of($activityType)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
            $actionLabel = Str::of($activityType)->replace('_', ' ')->title()->value();

            $payload = [
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

<<<<<<< HEAD
            if (Schema::hasColumn($historyTable, 'triggered_by_user_id')) {
                $payload['triggered_by_user_id'] = $triggeredByUserId;
            }

            if (Schema::hasColumn($historyTable, 'activity_type')) {
                $payload['activity_type'] = $activityType;
            }

            if (Schema::hasColumn($historyTable, 'activity_id')) {
                $payload['activity_id'] = $activityId;
            }

            if (Schema::hasColumn($historyTable, 'impact_value')) {
                $payload['impact_value'] = $impactValue;
            }

            if (Schema::hasColumn($historyTable, 'title')) {
                $payload['title'] = $title;
            }

            if (Schema::hasColumn($historyTable, 'description')) {
                $payload['description'] = $description;
            }

            if (Schema::hasColumn($historyTable, 'meta')) {
                $payload['meta'] = $normalizedMeta;
            }

=======
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
            if (Schema::hasColumn($historyTable, 'impact_after')) {
                $payload['impact_after'] = $newTotal;
            }

            if (Schema::hasColumn($historyTable, 'life_impacted')) {
                $payload['life_impacted'] = $impactValue;
            }

            if (Schema::hasColumn($historyTable, 'counted_in_total')) {
                $payload['counted_in_total'] = true;
            }

            if (Schema::hasColumn($historyTable, 'impact_category')) {
                $payload['impact_category'] = $activityType;
            }

            if (Schema::hasColumn($historyTable, 'action_key')) {
                $payload['action_key'] = $actionKey !== '' ? $actionKey : 'referral_registration';
            }

            if (Schema::hasColumn($historyTable, 'action_label')) {
                $payload['action_label'] = $actionLabel !== '' ? $actionLabel : 'Referral Registration';
            }

            if (Schema::hasColumn($historyTable, 'remarks')) {
                $payload['remarks'] = $description
                    ?? ($title !== '' ? $title : 'Referral registration impact awarded.');
            }

            if (Schema::hasColumn($historyTable, 'status')) {
                $payload['status'] = 'approved';
            }

            DB::table($historyTable)->insert($payload);

            return $this->getCurrentTotal($userId);
        });

        $this->checkAndPublishLifeImpactTimelinePosts($userId, $oldTotal, $newTotal);

        return $newTotal;
    }

    public function incrementAndLog(
        string $userId,
        int $points,
        string $activityType,
        string $title,
        ?string $triggeredByUserId = null,
        ?string $activityId = null,
        ?string $description = null,
        ?array $meta = null,
    ): int {
        return $this->addLifeImpact(
            $userId,
            $triggeredByUserId,
            $activityType,
            $activityId,
            (int) $points,
            $title,
            $description,
            $meta ?? []
        );
    }

    public function getCurrentTotal(string $userId): int
    {
        return (int) (DB::table('users')->where('id', $userId)->value('life_impacted_count') ?? 0);
    }

    public function recordApprovedImpactHistory(Impact $impact, ?string $approvedByAdminId = null): array
    {
        $impactId = (string) $impact->id;
        $userId = (string) $impact->user_id;
        $triggeredByUserId = (string) $impact->user_id;
        $impactValue = (int) ($impact->life_impacted ?? 0);

        if ($impactValue <= 0) {
            $impactValue = $this->resolveImpactScore($impact);
        }

        $impactValue = max(1, $impactValue);
        $actionLabel = trim((string) ($impact->action ?? 'Impact Approved'));
        $actionKey = Str::of($actionLabel)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
        $remarks = $impact->additional_remarks ?: $impact->review_remarks;

        return DB::transaction(function () use (
            $impact,
            $impactId,
            $userId,
            $triggeredByUserId,
            $impactValue,
            $actionLabel,
            $actionKey,
            $remarks,
            $approvedByAdminId
        ): array {
            Log::info('impact.approval.started', [
                'impact_id' => $impactId,
                'user_id' => $userId,
                'triggered_by_user_id' => $triggeredByUserId,
                'action' => $actionKey,
            ]);

            $existing = LifeImpactHistory::query()
                ->where('activity_type', 'impact')
                ->where('activity_id', $impactId)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                Log::info('impact.approval.history_exists', [
                    'impact_id' => $impactId,
                    'user_id' => $userId,
                    'history_id' => (string) $existing->id,
                ]);

                $total = $this->recomputeTotalFromHistory($userId);

                Log::info('impact.approval.total_recomputed', [
                    'impact_id' => $impactId,
                    'user_id' => $userId,
                    'total_life_impacted' => $total,
                ]);

                return [
                    'created' => false,
                    'history_id' => (string) $existing->id,
                    'total_life_impacted' => $total,
                ];
            }

            $metaPayload = array_filter([
                'impact_id' => $impactId,
                'impact_date' => optional($impact->impact_date)?->toDateString(),
                'action' => $this->normalizeNullableString($impact->action),
                'action_key' => $actionKey,
                'action_label' => $actionLabel,
                'impact_value' => $impactValue,
                'impacted_peer_id' => $impact->impacted_peer_id ? (string) $impact->impacted_peer_id : null,
                'affected_user_id' => $impact->impacted_peer_id ? (string) $impact->impacted_peer_id : null,
                'story_to_share' => $this->normalizeNullableString($impact->story_to_share),
                'additional_remarks' => $this->normalizeNullableString($impact->additional_remarks),
                'review_remarks' => $this->normalizeNullableString($impact->review_remarks),
                'approved_by' => $approvedByAdminId,
                'approved_at' => optional($impact->approved_at)->toISOString(),
            ], fn ($value) => $value !== null && $value !== '');

            $title = $this->normalizeNullableString($actionLabel) ?? 'Impact Approved';
            $description = $this->normalizeNullableString('Impact approved: '.($actionLabel !== '' ? $actionLabel : 'Impact action'));
            $normalizedRemarks = $this->normalizeNullableString($remarks);
            $meta = null;
            if (! empty($metaPayload)) {
                $encodedMeta = json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $meta = $encodedMeta === false ? null : $encodedMeta;
            }

            Log::info('impact.approval.payload_types', [
                'impact_id' => $impactId,
                'title_type' => gettype($title),
                'description_type' => gettype($description),
                'remarks_type' => gettype($normalizedRemarks),
                'meta_payload_type' => gettype($metaPayload),
            ]);

            $payload = [
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'triggered_by_user_id' => $triggeredByUserId !== '' ? $triggeredByUserId : null,
                'activity_type' => 'impact',
                'activity_id' => $impactId,
                'impact_value' => $impactValue,
                'title' => $title,
                'description' => $description,
                'meta' => $meta,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn($this->lifeImpactHistoriesTable(), 'life_impacted')) {
                $payload['life_impacted'] = $impactValue;
            }

            if (Schema::hasColumn($this->lifeImpactHistoriesTable(), 'counted_in_total')) {
                $payload['counted_in_total'] = true;
            }

            if (Schema::hasColumn($this->lifeImpactHistoriesTable(), 'impact_category')) {
                $payload['impact_category'] = null;
            }

            if (Schema::hasColumn($this->lifeImpactHistoriesTable(), 'action_key')) {
                $payload['action_key'] = $actionKey !== '' ? $actionKey : null;
            }

            if (Schema::hasColumn($this->lifeImpactHistoriesTable(), 'action_label')) {
                $payload['action_label'] = $actionLabel !== '' ? $actionLabel : null;
            }

            if (Schema::hasColumn($this->lifeImpactHistoriesTable(), 'remarks')) {
                $payload['remarks'] = $normalizedRemarks;
            }

            DB::table($this->lifeImpactHistoriesTable())->insert($payload);

            $historyId = (string) $payload['id'];
            $total = $this->recomputeTotalFromHistory($userId);

            if (Schema::hasColumn($this->lifeImpactHistoriesTable(), 'impact_after')) {
                DB::table($this->lifeImpactHistoriesTable())->where('id', $historyId)->update(['impact_after' => $total]);
            }

            Log::info('impact.approval.history_created', [
                'impact_id' => $impactId,
                'user_id' => $userId,
                'history_id' => $historyId,
                'impact_value' => $impactValue,
            ]);

            Log::info('impact.approval.total_recomputed', [
                'impact_id' => $impactId,
                'user_id' => $userId,
                'total_life_impacted' => $total,
            ]);

            return [
                'created' => true,
                'history_id' => $historyId,
                'total_life_impacted' => $total,
            ];
        });
    }

    public function recomputeTotalFromHistory(string $userId): int
    {
        $query = DB::table($this->lifeImpactHistoriesTable())->where('user_id', $userId);

        if (Schema::hasColumn($this->lifeImpactHistoriesTable(), 'counted_in_total')) {
            $query->where(function ($subQuery): void {
                $subQuery->where('counted_in_total', true)
                    ->orWhereNull('counted_in_total');
            });
        }

        $sumExpression = Schema::hasColumn($this->lifeImpactHistoriesTable(), 'life_impacted')
            ? 'COALESCE(life_impacted, impact_value, 0)'
            : 'COALESCE(impact_value, 0)';

        $sum = (int) $query->sum(DB::raw($sumExpression));

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'life_impacted_count' => $sum,
                'updated_at' => now(),
            ]);

        app(MilestoneBadgeService::class)->calculateForUserId($userId);

<<<<<<< HEAD
        $this->checkAndPublishLifeImpactTimelinePosts($userId, 0, $sum);

=======
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
        return $sum;
    }

    /**
     * Check and publish Life Impact Recognition creative post to Timeline if user unlocked a new tier.
     */
    public function checkAndPublishLifeImpactTimelinePosts(string $userId, int $oldTotal, int $newTotal): void
    {
        try {
            $user = User::find($userId);
            if (! $user) {
                return;
            }

            /** @var LifeImpactCreativeGenerator $generator */
            $generator = app(LifeImpactCreativeGenerator::class);
            $levels = $generator->getAllRecognitionLevels();

            $systemUser = User::where('email', 'info@peersglobal.com')->first();
            $authorUserId = $systemUser ? $systemUser->id : $user->id;

            foreach ($levels as $threshold => $meta) {
                if ($newTotal >= $threshold) {
                    $existingPost = Post::query()
                        ->where('source_type', 'life_impact')
                        ->where('source_id', $user->id)
                        ->where('source_event', "level_{$threshold}")
                        ->first();

                    if (! $existingPost && Schema::hasTable('posts')) {
                        try {
                            $fileRecord = $generator->generate($user, (int) $threshold, (int) $threshold);
                            $creativeImageUrl = url('/api/v1/files/'.$fileRecord->id);
                            $media = [
                                [
                                    'id' => $fileRecord->id,
                                    'type' => 'image',
                                    'url' => $creativeImageUrl,
                                ],
                            ];
                        } catch (\Throwable $creativeEx) {
                            Log::error("[LifeImpactService] Failed generating creative for threshold {$threshold}: ".$creativeEx->getMessage());
                            $creativeImageUrl = ! empty($meta['badge_image']) ? asset($meta['badge_image']) : url('/images/life_impact_badges/Impact Creator.png');
                            $media = [
                                [
                                    'id' => (string) Str::uuid(),
                                    'type' => 'image',
                                    'url' => $creativeImageUrl,
                                ],
                            ];
                        }

                        $caption = $generator->formatCaption($user, (int) $threshold, $meta);
                        $userName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                        if (empty($userName)) {
                            $userName = $user->name ?: 'Peer Member';
                        }

                        Post::create([
                            'user_id' => $authorUserId,
                            'circle_id' => null,
                            'content_text' => $caption,
                            'media' => $media,
                            'tags' => ['life_impact_recognition', 'life_impact', (string) $user->id, $meta['hashtag'], "level_{$threshold}"],
                            'visibility' => 'public',
                            'moderation_status' => 'approved',
                            'sponsored' => false,
                            'is_deleted' => false,
                            'source_type' => 'life_impact',
                            'source_id' => $user->id,
                            'source_event' => "level_{$threshold}",
                            'post_type' => 'life_impact_recognition',
                            'title' => "🎉 Big Congratulations! {$userName} became a {$meta['title']}",
                            'description' => $caption,
                            'image' => $creativeImageUrl,
                            'status' => 'active',
                        ]);

                        Log::info("[LifeImpactService] Automatically published Life Impact recognition post for user {$user->id} reaching level {$meta['title']} ({$threshold} lives)");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('[LifeImpactService] Failed checking and publishing life impact timeline posts: '.$e->getMessage(), [
                'exception' => $e,
                'user_id' => $userId,
            ]);
        }
    }

    private function resolveImpactScore(Impact $impact): int
    {
        $legacyLifeImpacted = (int) ($impact->life_impacted ?? 0);

        if (! Schema::hasTable('impact_actions')) {
            return max(1, $legacyLifeImpacted ?: 1);
        }

        $actionName = trim((string) ($impact->action ?? ''));

        if ($actionName === '') {
            return max(1, $legacyLifeImpacted ?: 1);
        }

        $impactAction = ImpactAction::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($actionName)])
            ->first(['impact_score']);

        $dynamicScore = (int) ($impactAction?->impact_score ?? 0);

        if ($dynamicScore > 0) {
            return $dynamicScore;
        }

        return max(1, $legacyLifeImpacted ?: 1);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function lifeImpactHistoriesTable(): string
    {
        $searchPath = (string) config('database.connections.'.config('database.default').'.search_path', 'public');
        $schema = trim((string) explode(',', $searchPath)[0], " \t\n\r\0\x0B\"");

        return ($schema !== '' ? $schema.'.' : '').'life_impact_histories';
    }
}
