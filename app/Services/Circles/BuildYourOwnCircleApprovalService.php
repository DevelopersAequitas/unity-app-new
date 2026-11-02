<?php

declare(strict_types=1);

namespace App\Services\Circles;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\JoinedCircleCategory;
use App\Models\User;
use App\Models\UserCircleRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Handles automatic Circle creation when admin approves a "Build Your Own Circle" request.
 */
final class BuildYourOwnCircleApprovalService
{
    public function __construct(
        private readonly ?UserCircleNomineeStatusResolver $nomineeResolver = null,
    ) {}
    /**
     * Approve the request and auto-create a Circle from it.
     *
     * @param  UserCircleRequest  $circleRequest
     * @param  string|null        $adminUserId      Admin user ID performing the action
     * @param  string|null        $adminNotes
     * @param  array<string,mixed> $overrides        Optional overrides for the circle (e.g. city_id, status)
     * @return array{circle: Circle, circle_request: UserCircleRequest}
     */
    public function approve(
        UserCircleRequest $circleRequest,
        ?string $adminUserId,
        ?string $adminNotes = null,
        array $overrides = [],
    ): array {
        if ($circleRequest->status === UserCircleRequest::STATUS_APPROVED && $circleRequest->created_circle_id) {
            // Already approved and circle already created — return existing
            $circle = Circle::find($circleRequest->created_circle_id);
            if ($circle) {
                return ['circle' => $circle, 'circle_request' => $circleRequest];
            }
        }

        return DB::transaction(function () use ($circleRequest, $adminUserId, $adminNotes, $overrides): array {
            // ── 1. Build Circle payload from request ──────────────────────────────
            $circleName = (string) $circleRequest->circle_name;
            $baseSlug   = Str::slug($circleName) ?: 'circle';
            $slug       = $baseSlug;
            $counter    = 1;
            while (Circle::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Resolve city_id from city name if not overridden
            $cityId = $overrides['city_id'] ?? null;
            if (! $cityId && $circleRequest->city && Schema::hasTable('cities')) {
                $cityId = DB::table('cities')
                    ->where('name', 'ilike', trim($circleRequest->city))
                    ->value('id');
            }

            // Founder = the user who submitted the request
            $founderId = (string) $circleRequest->user_id;

            $circleStatus = $overrides['status'] ?? 'active';

            $calendar = [
                'settings' => array_filter([
                    'meeting_mode'      => $circleRequest->meeting_mode,
                    'meeting_frequency' => $circleRequest->meeting_frequency,
                ]),
                'leadership' => [
                    'circle_founder_user_id' => $founderId,
                ],
            ];

            $circleData = array_merge([
                'name'                   => $circleName,
                'slug'                   => $slug,
                'description'            => $circleRequest->description,
                'city_id'                => $cityId,
                'city'                   => $circleRequest->city,
                'country'                => $circleRequest->country ?? 'India',
                'meeting_mode'           => $circleRequest->meeting_mode,
                'meeting_frequency'      => $circleRequest->meeting_frequency,
                'circle_founder_user_id' => $founderId,
                'status'                 => $circleStatus,
                'type'                   => 'public',
                'calendar'               => $calendar,
            ], $overrides);

            // Remove keys that aren't fillable on the Circle model
            $circle = new Circle();
            foreach ($circleData as $key => $value) {
                if (Schema::hasColumn('circles', $key) || in_array($key, ['calendar'], true)) {
                    $circle->{$key} = $value;
                }
            }
            $circle->slug = $slug;
            $circle->save();

            // ── Attach Level 1 Category if provided ──────────────────────────────
            $categoryId = $overrides['category_id'] ?? $circleRequest->category_id;
            if ($categoryId) {
                if (Schema::hasColumn('circles', 'template_id')) {
                    $circle->template_id = (int) $categoryId;
                    $circle->save();
                }

                if (method_exists($circle, 'categories')) {
                    $circle->categories()->syncWithoutDetaching([(int) $categoryId]);
                }
            }

            // ── 2. Add submitter as Circle Founder member ─────────────────────────
            $this->addFounderMember($circle->id, $founderId);

            // ── 3. Add nominated registered users as members (role: member) ───────
            $this->addNomineeMembers($circle->id, $circleRequest);

            // ── 4. Mark request as approved and store the created circle ID ───────
            $circleRequest->status            = UserCircleRequest::STATUS_APPROVED;
            $circleRequest->admin_notes       = $adminNotes ?? $circleRequest->admin_notes;
            $circleRequest->reviewed_by       = $adminUserId;
            $circleRequest->reviewed_at       = now();
            $circleRequest->created_circle_id = $circle->id;
            $circleRequest->circle_created_at = now();
            $circleRequest->save();

            // ── 5. Sync founder user's active circle ──────────────────────────────
            $this->syncFounderActiveCircle($founderId, $circle);

            $circle->load(['cityRef', 'founder', 'members']);

            return [
                'circle'         => $circle,
                'circle_request' => $circleRequest,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function addFounderMember(string $circleId, string $userId): void
    {
        $existing = CircleMember::withTrashed()
            ->where('circle_id', $circleId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->forceFill([
                'role'      => 'circle_founder',
                'status'    => 'approved',
                'joined_at' => now(),
                'left_at'   => null,
            ])->save();
        } else {
            CircleMember::create([
                'circle_id' => $circleId,
                'user_id'   => $userId,
                'role'      => 'circle_founder',
                'status'    => 'approved',
                'joined_at' => now(),
            ]);
        }

        Circle::syncLeadershipFromMembers($circleId);
    }

    private function addNomineeMembers(string $circleId, UserCircleRequest $circleRequest): void
    {
        // Resolve all nominees (auto-linking any registered users by email or phone)
        $resolver = $this->nomineeResolver ?? new UserCircleNomineeStatusResolver();
        $resolved = $resolver->resolveNominees($circleRequest);

        foreach ($resolved as $nomineeData) {
            $userId = $nomineeData['nominee_user_id'] ?? null;

            if (! $userId) {
                continue;
            }

            // Skip if this is the founder (already added)
            if ($userId === (string) $circleRequest->user_id) {
                continue;
            }

            // Verify user exists
            if (! User::where('id', $userId)->exists()) {
                continue;
            }

            $existing = CircleMember::withTrashed()
                ->where('circle_id', $circleId)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->forceFill([
                    'role'      => 'member',
                    'status'    => 'approved',
                    'joined_at' => now(),
                    'left_at'   => null,
                ])->save();
            } else {
                CircleMember::create([
                    'circle_id' => $circleId,
                    'user_id'   => $userId,
                    'role'      => 'member',
                    'status'    => 'approved',
                    'joined_at' => now(),
                ]);
            }
        }
    }

    private function syncFounderActiveCircle(string $founderId, Circle $circle): void
    {
        $user = User::find($founderId);
        if (! $user) {
            return;
        }

        if (Schema::hasColumn('users', 'active_circle_id') && empty($user->active_circle_id)) {
            $user->active_circle_id = $circle->id;
            if (Schema::hasColumn('users', 'circle_joined_at')) {
                $user->circle_joined_at = Carbon::now();
            }
            $user->save();
        }
    }
}

