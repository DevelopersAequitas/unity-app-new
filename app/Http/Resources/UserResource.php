<?php

namespace App\Http\Resources;

use App\Models\Connection;
use App\Models\User;
use App\Models\UserFollow;
use App\Services\ProfileMatchService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $profilePhotoId = $this->profile_photo_file_id ?? $this->profile_photo_id;
        $profilePhotoUrl = $profilePhotoId
            ? url('/api/v1/files/'.$profilePhotoId)
            : null;
        $coverPhotoId = $this->cover_photo_file_id;
        $coverPhotoUrl = $coverPhotoId
            ? url('/api/v1/files/'.$coverPhotoId)
            : null;
        $profileVideoId = $this->profile_video_id;
        $profileVideoUrl = $this->resolveProfileVideoUrl();

        $circleMemberships = $this->resolveCircleMemberships();
        $circleCount = count($circleMemberships);
        $isMultiCircle = $circleCount > 1;

        $membershipStatus = $this->effective_membership_status ?? $this->membership_status;
        $normalizedStatus = strtolower(trim(str_replace(' ', '_', (string) $membershipStatus)));

        if (in_array($normalizedStatus, ['free_peer', 'free_trial_peer', 'circle_peer', 'multi_circle_peer', 'only_unity_peer', 'global_peer', ''], true)) {
            if ($circleCount > 1) {
                $membershipStatus = 'multi_circle_peer';
            } elseif ($circleCount === 1) {
                $membershipStatus = 'circle_peer';
            } elseif (in_array($normalizedStatus, ['circle_peer', 'multi_circle_peer'], true)) {
                $membershipStatus = 'free_peer';
            }
        }

        $resolvedCircle = $this->resolvePrimaryCircleContext();
        $resolvedCircleInfo = $resolvedCircle['circle'] ?? null;

        $resolvedCity = null;
        $authUser = auth('sanctum')->user() ?: ($request instanceof Request ? $request->user() : null);
        $isBookmark = false;
        if ($authUser) {
            $bookmarks = $authUser->bookmarks ?? [];
            $isBookmark = in_array((string) $this->id, $bookmarks, true);
        }

        $isConnected = false;
        $connectionStatus = 'none';
        $isRequested = false;

        if ($this->getAttribute('is_connected') !== null) {
            $isConnected = (bool) $this->getAttribute('is_connected');
            $connectionStatus = $this->getAttribute('connection_status') ?? ($isConnected ? 'connected' : 'none');
            $isRequested = (bool) $this->getAttribute('is_requested');
        } elseif ($authUser && Schema::hasTable('connections')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $this->id;

            if ($authUserId === $targetId) {
                $isConnected = false;
                $connectionStatus = 'self';
            } else {
                $connection = Connection::query()
                    ->where(function ($q) use ($authUserId, $targetId) {
                        $q->where('requester_id', $authUserId)->where('addressee_id', $targetId);
                    })
                    ->orWhere(function ($q) use ($authUserId, $targetId) {
                        $q->where('addressee_id', $authUserId)->where('requester_id', $targetId);
                    })
                    ->first();

                if ($connection) {
                    $isConnected = (bool) $connection->is_approved;
                    $isRequested = ! $connection->is_approved && (string) $connection->requester_id === $authUserId;
                    $connectionStatus = $isConnected
                        ? 'connected'
                        : ($isRequested ? 'pending_sent' : 'pending_received');
                }
            }
        }

        $isFollowing = false;
        if ($this->getAttribute('is_following') !== null) {
            $isFollowing = (bool) $this->getAttribute('is_following');
        } elseif ($authUser && Schema::hasTable('user_follows')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $this->id;

            if ($authUserId !== $targetId) {
                $isFollowing = UserFollow::query()
                    ->where('follower_id', $authUserId)
                    ->where('following_id', $targetId)
                    ->whereIn('status', ['accepted', 'pending'])
                    ->exists();
            }
        }

        $isPro = false;
        if ($this->getAttribute('is_pro') !== null) {
            $isPro = (bool) $this->getAttribute('is_pro');
        } elseif (isset($this->is_verified) && $this->is_verified !== null && (bool) $this->is_verified) {
            $isPro = true;
        } elseif (method_exists($this->resource, 'isPaidMember')) {
            $isPro = (bool) $this->resource->isPaidMember();
        } else {
            $status = strtolower(trim((string) ($membershipStatus ?? '')));
            $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
        }
        if ($this->relationLoaded('city') && $this->city) {
            $resolvedCity = $this->city;
        } elseif (filled($this->getAttribute('city'))) {
            $resolvedCity = $this->getAttribute('city');
        }

        $otherCategoryName = blank($this->business_category_id) ? $this->business_sub_category : null;
        $isOtherCategory = blank($this->business_category_id) && ($otherCategoryName !== null && $otherCategoryName !== '');

        $welcomeCreativeUrl = $this->resolveWelcomeCreativeUrl();

        return [
            'id' => $this->id,
            'peer_id' => $this->peer_id,
            'public_profile_slug' => $this->public_profile_slug,
            'profile_photo_id' => $profilePhotoId,
            'cover_photo_id' => $coverPhotoId,
            'profile_video_id' => $profileVideoId,
            'profile_video' => $profileVideoId ? [
                'id' => (string) $profileVideoId,
                'url' => $profileVideoUrl,
            ] : null,
            'profile_video_url' => $profileVideoUrl,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'name' => $this->display_name ?: trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'company_name' => $this->company_name,
            'designation' => $this->designation,
            'email' => $this->email,
            'phone' => $this->phone,
            'introduced_by' => $this->introduced_by,
            'introduced_count' => (int) ($this->introduced_count ?? ($this->relationLoaded('introducedPeers') ? $this->introducedPeers->count() : ($this->members_introduced_count ?? 0))),
            'introduced_by_user' => $this->relationLoaded('introducedBy') && $this->introducedBy ? [
                'id' => $this->introducedBy->id,
                'name' => $this->introducedBy->display_name ?: trim(($this->introducedBy->first_name ?? '').' '.($this->introducedBy->last_name ?? '')),
                'profile_photo_url' => $this->introducedBy->profile_photo_url,
            ] : null,
            'city' => $resolvedCity ? new CityResource($resolvedCity) : null,
            'membership_status' => $membershipStatus,
            'membership_status_label' => match (strtolower(trim(str_replace(' ', '_', (string) $membershipStatus)))) {
                'free_trial_peer' => 'Free Trial Peer',
                'free_peer' => 'Free Peer',
                'only_unity_peer' => 'Global Peer',
                'unity_peer' => 'Green Member',
                'chartered_peer' => 'Premium Green Member',
                'charter_investor' => 'Green Investor',
                'circle_peer' => 'Circle Peer',
                'multi_circle_peer' => 'Multi Circle Peer',
                default => Str::headline(str_replace('_', ' ', (string) $membershipStatus)),
            },
            'is_multi_circle_peer' => $isMultiCircle,
            'membership_starts_at' => $this->membership_starts_at ?? $this->membership_start_date,
            'membership_ends_at' => $this->membership_ends_at ?? $this->membership_expiry ?? $this->membership_end_date,
            'zoho_plan_code' => $this->zoho_plan_code,
            'zoho_last_invoice_id' => $this->zoho_last_invoice_id,
            'active_circle_id' => $resolvedCircle['circle_id'] ?? $this->active_circle_id,
            'active_circle_addon_code' => $resolvedCircle['addon_code'] ?? $this->active_circle_addon_code,
            'active_circle_addon_name' => $resolvedCircle['addon_name'] ?? $this->active_circle_addon_name,
            'circle_joined_at' => $resolvedCircle['joined_at'] ?? $this->circle_joined_at,
            'circle_expires_at' => $resolvedCircle['expires_at'] ?? $this->circle_expires_at,
            'active_circle_subscription_id' => $resolvedCircle['circle_subscription_id'] ?? $this->active_circle_subscription_id,
            'active_circle' => $resolvedCircleInfo
                ? [
                    'id' => $resolvedCircleInfo->id,
                    'name' => $resolvedCircleInfo->name,
                    'slug' => $resolvedCircleInfo->slug,
                    'city' => $resolvedCircleInfo->relationLoaded('cityRef') ? [
                        'id' => optional($resolvedCircleInfo->cityRef)->id,
                        'name' => optional($resolvedCircleInfo->cityRef)->name,
                    ] : null,
                ]
                : $this->whenLoaded('activeCircle', function () {
                    $circle = $this->activeCircle;

                    if (! $circle) {
                        return null;
                    }

                    return [
                        'id' => $circle->id,
                        'name' => $circle->name,
                        'slug' => $circle->slug,
                        'city' => $circle->relationLoaded('cityRef') ? [
                            'id' => optional($circle->cityRef)->id,
                            'name' => optional($circle->cityRef)->name,
                        ] : null,
                    ];
                }),
            'circle_memberships' => $circleMemberships,
            'contact_visibility' => $this->contact_visibility ?? 'public',
            'connection_count' => (int) ($this->connection_count ?? ($this->approved_sent_count ?? 0) + ($this->approved_received_count ?? 0)),
            'followers_count' => (int) ($this->followers_count ?? 0),
            'following_count' => (int) ($this->following_count ?? 0),
            'posts_count' => (int) ($this->posts_count ?? 0),
            'coins_balance' => $this->coins_balance,
            'life_impacted_count' => (int) ($this->life_impacted_count ?? 0),
            'total_life_impact' => (int) ($this->life_impacted_count ?? 0),
            'lifeImpactedCount' => (int) ($this->life_impacted_count ?? 0),
            'impact_score' => (int) ($this->life_impacted_count ?? 0),
            'lives_impacted' => (int) ($this->life_impacted_count ?? 0),
            'lives_impacted_count' => (int) ($this->life_impacted_count ?? 0),
            'badges_count' => (int) ($this->badges_count ?? 0),
            'my_badges_count' => (int) ($this->my_badges_count ?? ($this->badges_count ?? 0)),
            'p2p_meetings_count' => (int) ($this->p2p_meetings_count ?? ($this->p2p_count ?? 0)),
            'p2p_count' => (int) ($this->p2p_count ?? ($this->p2p_meetings_count ?? 0)),
            'referrals_count' => (int) ($this->referrals_count ?? 0),
            'given_referrals_count' => (int) ($this->given_referrals_count ?? 0),
            'received_referrals_count' => (int) ($this->received_referrals_count ?? 0),
            'business_deals_count' => (int) ($this->business_deals_count ?? ($this->deals_count ?? 0)),
            'deals_count' => (int) ($this->deals_count ?? ($this->business_deals_count ?? 0)),
            'given_business_deals_count' => (int) ($this->given_business_deals_count ?? 0),
            'received_business_deals_count' => (int) ($this->received_business_deals_count ?? 0),
            'business_type' => $this->business_type,
            'turnover_range' => $this->turnover_range,
            'gender' => $this->gender,
            'dob' => optional($this->dob)?->format('Y-m-d'),
            'anniversary_date' => optional($this->anniversary_date)?->format('Y-m-d'),
            'experience_years' => $this->experience_years,
            'experience_summary' => $this->experience_summary,
            'bio' => $this->short_bio,
            'long_bio_html' => $this->long_bio_html,
            'industry_tags' => $this->industry_tags ?? [],
            'skills' => $this->skills ?? [],
            'interests' => $this->interests ?? [],
            'target_regions' => $this->target_regions ?? [],
            'target_business_categories' => $this->target_business_categories ?? [],
            'hobbies_interests' => $this->hobbies_interests ?? [],
            'leadership_roles' => $this->leadership_roles ?? [],
            'special_recognitions' => $this->special_recognitions ?? [],
            'social_links' => $this->resolveSocialLinks(),
            'media' => $this->mediaValue(),
            'profile_photo_url' => $profilePhotoUrl,
            'profile_image' => $profilePhotoUrl,
            'cover_photo_url' => $coverPhotoUrl,
            'welcome_creative_url' => $welcomeCreativeUrl,
            'address' => $this->address ?? null,
            'state' => $this->state ?? null,
            'country' => $this->country ?? null,
            'timezone' => $this->timezone ?? null,
            'pincode' => $this->pincode ?? null,
            'is_verified' => $this->is_verified ?? null,
            'is_sponsored_member' => $this->is_sponsored_member ?? null,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'website' => $this->website,
            'sustainability_contribution' => $this->sustainability_contribution,
            'sustainability_areas' => $this->sustainability_areas ?? [],
            'greenpreneur_goals' => $this->greenpreneur_goals ?? [],
            'community_directory_listing' => $this->community_directory_listing,
            'is_bookmark' => $isBookmark,
            'is_connected' => (bool) $isConnected,
            'is_following' => (bool) $isFollowing,
            'is_pro' => (bool) $isPro,
            'connection_status' => $connectionStatus,
            'is_requested' => (bool) $isRequested,
            'is_other_category' => (bool) $isOtherCategory,
            'other_category_name' => $isOtherCategory ? $otherCategoryName : null,
            'business_sub_category' => $isOtherCategory ? $otherCategoryName : $this->business_sub_category,
            'business_category' => (! $isOtherCategory && (($this->relationLoaded('businessCategory') && $this->businessCategory) || ($this->relationLoaded('level4Category') && $this->level4Category)))
                ? [
                    'id' => ($this->relationLoaded('businessCategory') && $this->businessCategory ? $this->businessCategory->id : $this->level4Category->id),
                    'name' => ($this->relationLoaded('businessCategory') && $this->businessCategory ? $this->businessCategory->name : $this->level4Category->name),
                ]
                : null,
            'story_link' => $this->story_link ?? null,
            'profile_match' => $this->when(
                $request->attributes->get('profile_match_enabled', false),
                fn () => $this->resolveProfileMatch($request)
            ),
        ];
    }

    private function resolveProfileMatch($request): ?array
    {
        if (! $request->attributes->get('profile_match_enabled', false)) {
            return null;
        }

        $authUser = $request->attributes->get('profile_match_auth_user')
            ?? $request->user('sanctum')
            ?? $request->user();

        if (! $authUser instanceof User) {
            return null;
        }

        $precomputed = $this->resource->getAttribute('profile_match');
        if (is_array($precomputed)) {
            return $this->publicProfileMatch($precomputed);
        }

        $profileMatchService = $request->attributes->get('profile_match_service');
        if (! $profileMatchService instanceof ProfileMatchService) {
            $profileMatchService = app(ProfileMatchService::class);
        }

        $profileMatch = $profileMatchService->calculate($authUser, $this->resource);

        return is_array($profileMatch) ? $this->publicProfileMatch($profileMatch) : null;
    }

    private function publicProfileMatch(array $profileMatch): array
    {
        return [
            'score' => $profileMatch['score'] ?? 0,
            'percentage' => $profileMatch['percentage'] ?? 0,
            'level' => $profileMatch['level'] ?? null,
            'matched_fields' => $profileMatch['matched_fields'] ?? [],
        ];
    }

    private function resolveProfileVideoUrl(): ?string
    {
        $profileVideoId = $this->profile_video_id;
        if (! blank($profileVideoId)) {
            return url('/api/v1/files/'.$profileVideoId);
        }

        $media = $this->media;

        if (is_string($media) && $media !== '') {
            $decoded = json_decode($media, true);
            $media = is_array($decoded) ? $decoded : [];
        }

        if (is_array($media) && $media !== []) {
            $firstMedia = array_values($media)[0] ?? null;

            if (is_array($firstMedia)) {
                if (! blank($firstMedia['url'] ?? null)) {
                    return (string) $firstMedia['url'];
                }

                if (! blank($firstMedia['id'] ?? null)) {
                    return url('/api/v1/files/'.$firstMedia['id']);
                }
            }
        }

        return $this->profile_video_url;
    }

    /**
     * @return array<int, array{id: string, url: string, type: string}>
     */
    private function mediaValue(): array
    {
        $media = $this->media;

        if (! is_array($media)) {
            return [];
        }

        return collect($media)
            ->filter(fn ($item): bool => is_array($item) && ! blank($item['id'] ?? null) && ! blank($item['type'] ?? null))
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'url' => url('/api/v1/files/'.$item['id']),
                'type' => (string) $item['type'],
            ])
            ->values()
            ->all();
    }

    private function resolveCircleMemberships(): array
    {
        if (! $this->resource->relationLoaded('circleMemberships')) {
            return [];
        }

        $memberships = $this->resource->circleMemberships;
        if (! $memberships instanceof Collection || $memberships->isEmpty()) {
            return [];
        }

        return $memberships->map(function ($membership): array {
            return [
                'circle_member_id' => $membership->id,
                'circle_id' => $membership->circle_id,
                'circle_name' => optional($membership->circle)->name,
                'circle_slug' => optional($membership->circle)->slug,
                'member_status' => $membership->status,
                'member_role' => $membership->role,
                'joined_at' => $membership->joined_at,
                'expires_at' => $this->resolveCircleMembershipExpiry($membership),
                'joined_via' => $membership->joined_via,
                'payment_status' => $membership->payment_status,
                'zoho_addon_code' => $membership->zoho_addon_code,
                'addon_name' => $membership->addon_name,
                'circle_subscription_id' => $membership->circle_subscription_id,
                'subscription_status' => $membership->subscription_status,
                'selected_category_path' => null,
            ];
        })->values()->all();
    }

    private function resolveCircleMembershipExpiry($membership): mixed
    {
        return $membership->expires_at
            ?? $membership->paid_ends_at
            ?? $this->membership_ends_at
            ?? $this->membership_expiry
            ?? null;
    }

    private function resolvePrimaryCircleContext(): array
    {
        if (! $this->resource->relationLoaded('circleMemberships')) {
            return [];
        }

        $membership = $this->resource->circleMemberships->first();
        if (! $membership) {
            return [];
        }

        return [
            'circle_id' => $membership->circle_id,
            'addon_code' => $membership->zoho_addon_code,
            'addon_name' => $membership->addon_name,
            'joined_at' => $membership->joined_at,
            'expires_at' => $membership->expires_at ?? $membership->paid_ends_at,
            'circle_subscription_id' => $membership->circle_subscription_id,
            'circle' => $membership->circle,
        ];
    }

    private function resolveSocialLinks(): ?array
    {
        $platforms = ['facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'website'];
        $storedLinks = $this->social_links;

        if (is_string($storedLinks)) {
            $decoded = json_decode($storedLinks, true);
            $storedLinks = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $columnMap = [
            'linkedin' => 'linkedin_profile',
            'facebook' => 'facebook_profile',
            'instagram' => 'instagram_handle',
            'twitter' => 'twitter_handle',
            'youtube' => 'youtube_channel',
            'website' => 'other_website',
        ];

        $links = [];
        foreach ($platforms as $platform) {
            $value = is_array($storedLinks) ? ($storedLinks[$platform] ?? null) : null;

            if (blank($value)) {
                $column = $columnMap[$platform] ?? null;
                $value = $column ? $this->getAttribute($column) : null;
            }

            if (blank($value)) {
                $columnValue = $this->getAttribute($platform);
                $value = blank($columnValue) ? null : $columnValue;
            }

            $links[$platform] = $value;
        }

        return collect($links)->filter(fn ($link) => ! blank($link))->isNotEmpty()
            ? $links
            : null;
    }

    private function resolveConnectionCount(): int
    {
        if (! Schema::hasTable('connections')) {
            return 0;
        }

        if (isset($this->approved_sent_count) && isset($this->approved_received_count)) {
            return (int) ($this->approved_sent_count + $this->approved_received_count);
        }

        return (int) (Connection::where('is_approved', true)
            ->where(function ($query) {
                $query->where('requester_id', $this->id)
                    ->orWhere('addressee_id', $this->id);
            })->count());
    }

    protected function resolveBadgesCount(): int
    {
        if (isset($this->badges_count)) {
            return (int) $this->badges_count;
        }

        if (isset($this->my_badges_count)) {
            return (int) $this->my_badges_count;
        }

        if (! Schema::hasTable('user_milestone_badges')) {
            return 0;
        }

        return (int) DB::table('user_milestone_badges')
            ->where('user_id', $this->id)
            ->where('status', 'earned')
            ->count();
    }

    protected function resolveP2pMeetingsCount(): int
    {
        if (isset($this->p2p_meetings_count)) {
            return (int) $this->p2p_meetings_count;
        }

        if (isset($this->p2p_count)) {
            return (int) $this->p2p_count;
        }

        if (! Schema::hasTable('p2p_meetings')) {
            return 0;
        }

        $query = DB::table('p2p_meetings')
            ->where(function ($q) {
                $q->where('initiator_user_id', $this->id)
                    ->orWhere('peer_user_id', $this->id);
            });

        if (Schema::hasColumn('p2p_meetings', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (Schema::hasColumn('p2p_meetings', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    protected function resolveReferralsCount(): int
    {
        if (isset($this->referrals_count)) {
            return (int) $this->referrals_count;
        }

        if (! Schema::hasTable('referrals')) {
            return 0;
        }

        $query = DB::table('referrals')
            ->where(function ($q) {
                $q->where('from_user_id', $this->id)
                    ->orWhere('to_user_id', $this->id);
            });

        if (Schema::hasColumn('referrals', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (Schema::hasColumn('referrals', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    protected function resolveGivenReferralsCount(): int
    {
        if (isset($this->given_referrals_count)) {
            return (int) $this->given_referrals_count;
        }

        if (! Schema::hasTable('referrals')) {
            return 0;
        }

        $query = DB::table('referrals')->where('from_user_id', $this->id);

        if (Schema::hasColumn('referrals', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (Schema::hasColumn('referrals', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    protected function resolveReceivedReferralsCount(): int
    {
        if (isset($this->received_referrals_count)) {
            return (int) $this->received_referrals_count;
        }

        if (! Schema::hasTable('referrals')) {
            return 0;
        }

        $query = DB::table('referrals')->where('to_user_id', $this->id);

        if (Schema::hasColumn('referrals', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (Schema::hasColumn('referrals', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    protected function resolveBusinessDealsCount(): int
    {
        if (isset($this->business_deals_count)) {
            return (int) $this->business_deals_count;
        }

        if (isset($this->deals_count)) {
            return (int) $this->deals_count;
        }

        if (! Schema::hasTable('business_deals')) {
            return 0;
        }

        $query = DB::table('business_deals')
            ->where(function ($q) {
                $q->where('from_user_id', $this->id)
                    ->orWhere('to_user_id', $this->id);
            });

        if (Schema::hasColumn('business_deals', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (Schema::hasColumn('business_deals', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    protected function resolveGivenBusinessDealsCount(): int
    {
        if (isset($this->given_business_deals_count)) {
            return (int) $this->given_business_deals_count;
        }

        if (! Schema::hasTable('business_deals')) {
            return 0;
        }

        $query = DB::table('business_deals')->where('from_user_id', $this->id);

        if (Schema::hasColumn('business_deals', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (Schema::hasColumn('business_deals', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    protected function resolveReceivedBusinessDealsCount(): int
    {
        if (isset($this->received_business_deals_count)) {
            return (int) $this->received_business_deals_count;
        }

        if (! Schema::hasTable('business_deals')) {
            return 0;
        }

        $query = DB::table('business_deals')->where('to_user_id', $this->id);

        if (Schema::hasColumn('business_deals', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (Schema::hasColumn('business_deals', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }
}
