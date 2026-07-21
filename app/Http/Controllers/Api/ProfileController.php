<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Profile\StoreIntroducedPeerRequest;
use App\Http\Requests\Profile\StoreUserLinkRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateTimezoneRequest;
use App\Http\Requests\Profile\UpdateUserLinkRequest;
use App\Http\Resources\UserLinkResource;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\V1\LimitedUserResource;
use App\Models\User;
use App\Services\Blocks\PeerBlockService;
use App\Services\ProfileVisibilityService;
use App\Services\Users\IntroducedPeerService;
use App\Services\Users\PublicProfileSlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProfileController extends BaseApiController
{
    public function show(Request $request)
    {
        $user = $request->user()->load([
            'profilePhotoFile',
            'coverPhotoFile',
            'userLinks',
        ]);

        return $this->success(new UserProfileResource($user), 'Profile fetched successfully');
    }

    public function updateTimezone(UpdateTimezoneRequest $request)
    {
        $user = $request->user();
        $timezone = $request->validated('timezone');

        if ($user->timezone !== $timezone) {
            $user->timezone = $timezone;
            $user->saveOrFail();
        }

        return $this->success([
            'timezone' => $timezone,
        ], 'Timezone updated successfully.');
    }

    public function update(UpdateProfileRequest $request, PublicProfileSlugService $publicProfileSlugService)
    {
        $user = $request->user();
        $validated = $request->validated();
        $data = collect($validated)
            ->only($this->profileUpdateFields())
            ->toArray();

        foreach ($this->arrayProfileFields() as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] ?? [];
            }
        }

        if (array_key_exists('social_links', $validated) && is_array($validated['social_links'])) {
            $data['social_links'] = $validated['social_links'];

            $socialLinkColumnMap = [
                'linkedin' => 'linkedin_profile',
                'facebook' => 'facebook_profile',
                'instagram' => 'instagram_handle',
                'website' => 'other_website',
            ];

            foreach ($socialLinkColumnMap as $legacyKey => $column) {
                if (! empty($validated['social_links'][$legacyKey]) && empty($data[$column])) {
                    $data[$column] = $validated['social_links'][$legacyKey];
                }
            }
        }

        if (array_key_exists('media', $data)) {
            $data['media'] = $this->formatMediaPayload($data['media']);
        }

        if (array_key_exists('city_of_residence', $data) && ! array_key_exists('city', $data)) {
            $data['city'] = $data['city_of_residence'];
        }

        if (array_key_exists('city', $data) && ! array_key_exists('city_of_residence', $data)) {
            $data['city_of_residence'] = $data['city'];
        }

        if (array_key_exists('about', $data)) {
            $data['short_bio'] = $data['about'];
            unset($data['about']);
        }

        if (array_key_exists('profile_photo_id', $data)) {
            $data['profile_photo_file_id'] = $data['profile_photo_id'];
            unset($data['profile_photo_id']);
        }

        if (array_key_exists('cover_photo_id', $data)) {
            $data['cover_photo_file_id'] = $data['cover_photo_id'];
            unset($data['cover_photo_id']);
        }

        if (array_key_exists('intro_video_id', $data)) {
            $data['profile_video_id'] = $data['intro_video_id'];
            unset($data['intro_video_id']);
        }

        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $displayName = trim(($data['first_name'] ?? $user->first_name ?? '').' '.($data['last_name'] ?? $user->last_name ?? ''));
            $data['display_name'] = $displayName !== '' ? $displayName : $user->email;
        }

        $user->forceFill($data);

        if (empty($user->public_profile_slug)) {
            $user->public_profile_slug = $publicProfileSlugService->generateUniqueForUser($user);
        }

        $user->saveOrFail();
        $this->persistProfilePayloadToUsersTable($user, $data);
        $user->refresh();

        Log::info('profile_update_saved', [
            'user_id' => $user->id,
            'payload_keys' => array_keys($data),
            'secondary_mobile_db' => $user->secondary_mobile,
            'linkedin_profile_db' => $user->linkedin_profile,
        ]);

        $user->load(['profilePhotoFile', 'coverPhotoFile', 'userLinks']);

        return $this->success(new UserProfileResource($user), 'Profile updated successfully');
    }

    private function persistProfilePayloadToUsersTable($user, array $payload): void
    {
        if ($payload === []) {
            return;
        }

        $attributes = $user->getAttributes();
        $databasePayload = [];

        foreach (array_keys($payload) as $column) {
            if (array_key_exists($column, $attributes)) {
                $databasePayload[$column] = $attributes[$column];
            }
        }

        if ($databasePayload === []) {
            return;
        }

        if ($user->usesTimestamps() && $user->getUpdatedAtColumn()) {
            $databasePayload[$user->getUpdatedAtColumn()] = $user->freshTimestampString();
        }

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update($databasePayload);
    }

    /**
     * @param  array<int, array<string, mixed>>  $media
     * @return array<int, array{id: string, url: string, type: string}>
     */
    private function formatMediaPayload(array $media): array
    {
        return collect($media)
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'url' => url('/api/v1/files/'.$item['id']),
                'type' => (string) $item['type'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function profileUpdateFields(): array
    {
        $fields = [
            'first_name',
            'last_name',
            'phone',
            'company_name',
            'designation',
            'business_type',
            'about',
            'gender',
            'dob',
            'anniversary_date',
            'experience_years',
            'experience_summary',
            'city_id',
            'city',
            'city_of_residence',
            'state',
            'country',
            'timezone',
            'preferred_language',
            'skills',
            'interests',
            'media',
            'social_links',
            'profile_photo_id',
            'cover_photo_id',
            'intro_video_id',
            'profile_video_id',
            'business_logo_id',
            'business_category_id',
            'business_sub_category',
            'company_type',
            'year_of_establishment',
            'annual_revenue_range',
            'number_of_employees',
            'gst_number',
            'business_website',
            'superpower',
            'i_can_help_with',
            'i_am_looking_for',
            'business_keywords',
            'products_services_offered',
            'secondary_mobile',
            'linkedin_profile',
            'instagram_handle',
            'twitter_handle',
            'facebook_profile',
            'youtube_channel',
            'other_website',
            'profile_visibility',
            'contact_visibility',
            'business_address',
            'business_city',
            'business_state',
            'business_pincode',
            'business_country',
            'google_maps_latitude',
            'google_maps_longitude',
            'industries_of_interest',
            'collaboration_goals',
            'preferred_meeting_format',
            'willing_to_mentor',
            'open_to_cross_city_collaboration',
            'open_to_speaking_at_events',
        ];

        if (! Schema::hasColumn('users', 'profile_visibility')) {
            $fields = array_values(array_diff($fields, ['profile_visibility']));
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    private function arrayProfileFields(): array
    {
        return [
            'skills',
            'interests',
            'social_links',
            'i_can_help_with',
            'i_am_looking_for',
            'business_keywords',
            'industries_of_interest',
            'collaboration_goals',
        ];
    }

    public function links(Request $request)
    {
        $user = $request->user();
        $links = $user->userLinks()->orderByDesc('created_at')->get();

        return $this->success(UserLinkResource::collection($links));
    }

    public function storeLink(StoreUserLinkRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $link = $user->userLinks()->create($data);

        return $this->success(new UserLinkResource($link), 'Link created successfully', 201);
    }

    public function updateLink(UpdateUserLinkRequest $request, string $id)
    {
        $user = $request->user();
        $data = $request->validated();

        $link = $user->userLinks()->where('id', $id)->first();

        if (! $link) {
            return $this->error('Link not found', 404);
        }

        $link->fill($data);
        $link->save();

        return $this->success(new UserLinkResource($link), 'Link updated successfully');
    }

    public function destroyLink(Request $request, string $id)
    {
        $user = $request->user();
        $link = $user->userLinks()->where('id', $id)->first();

        if (! $link) {
            return $this->error('Link not found', 404);
        }

        $link->delete();

        return $this->success(null, 'Link deleted successfully');
    }

    public function introducedPeers(Request $request, IntroducedPeerService $service): JsonResponse
    {
        $user = $request->user();
        $peers = $service->getIntroducedPeers($user);

        return $this->success(
            LimitedUserResource::collection($peers),
            'Introduced peers fetched successfully'
        );
    }

    public function introducer(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('introducedBy');

        if (! $user->introducedBy) {
            return $this->success(null, 'No introducer set for this peer.');
        }

        return $this->success(
            new LimitedUserResource($user->introducedBy),
            'Introducer fetched successfully'
        );
    }

    public function addIntroducedPeer(StoreIntroducedPeerRequest $request, IntroducedPeerService $service): JsonResponse
    {
        $user = $request->user();
        $peerId = $request->validated('peer_id');

        try {
            $introduced = $service->introducePeer($user, $peerId);

            return $this->success(
                new LimitedUserResource($introduced),
                'Peer introduced successfully',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function memberIntroducedPeers(
        Request $request,
        string $memberId,
        PeerBlockService $peerBlockService,
        ProfileVisibilityService $profileVisibilityService
    ): JsonResponse {
        if (! Str::isUuid($memberId)) {
            return $this->error('Member not found', 404);
        }

        $member = User::query()
            ->with(['city', 'profilePhotoFile'])
            ->whereNull('deleted_at')
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            ->find($memberId);

        if (! $member) {
            return $this->error('Member not found', 404);
        }

        $authUser = $request->user();
        if ($peerBlockService->isBlockedEitherWay((string) $authUser->id, (string) $member->id)) {
            return $this->error('Peer not found.', 404);
        }

        if (! $profileVisibilityService->canView($authUser, $member)) {
            return $this->error('Profile is restricted.', 403);
        }

        // Real count from the database: COUNT users WHERE introduced_by = selected member ID
        $introducedPeersCount = User::query()
            ->where('introduced_by', $member->id)
            ->whereNull('deleted_at')
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            ->count();

        // Get list of introduced peers, excluding blocked ones and applying visibility rules
        $excludedUserIds = array_values(array_unique(array_filter(array_merge(
            $peerBlockService->blockedUserIdsFor((string) $authUser->id),
            $peerBlockService->usersWhoBlockedMeIdsFor((string) $authUser->id)
        ))));

        $peersQuery = User::query()
            ->where('introduced_by', $member->id)
            ->whereNull('deleted_at')
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            });

        if ($authUser) {
            $profileVisibilityService->applyVisibleTo($peersQuery, $authUser);
        }

        if (! empty($excludedUserIds)) {
            $peersQuery->whereNotIn('id', $excludedUserIds);
        }

        // Avoid N+1 queries by eager loading necessary relations
        $introducedPeers = $peersQuery
            ->with(['city', 'profilePhotoFile', 'coverPhotoFile', 'introducedBy', 'level4Category'])
            ->get();

        // Build member object response
        $cityName = null;
        $cityRelation = $member->relationLoaded('city') ? $member->getRelation('city') : null;
        if ($cityRelation) {
            $cityName = $cityRelation->name;
        } else {
            $cityName = is_string($member->city) ? $member->city : ($member->city_of_residence ?? null);
        }

        $memberName = $member->display_name ?? trim(($member->first_name ?? '').' '.($member->last_name ?? ''));

        $memberData = [
            'id' => $member->id,
            'name' => $memberName !== '' ? trim((string) $memberName) : null,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'city' => $cityName,
            'business' => $member->company_name,
            'designation' => $member->designation,
            'profile_photo_image' => $member->profile_photo_url,
        ];

        return $this->success([
            'member' => $memberData,
            'introduced_peers_count' => (int) $introducedPeersCount,
            'introduced_peers' => LimitedUserResource::collection($introducedPeers),
        ], 'Member introduced peers fetched successfully.');
    }
}
