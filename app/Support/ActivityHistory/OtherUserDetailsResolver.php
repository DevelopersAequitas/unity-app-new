<?php

declare(strict_types=1);

namespace App\Support\ActivityHistory;

use App\Models\CircleCategoryLevel4;
use App\Models\CustomCategoryRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OtherUserDetailsResolver
{
    /**
     * @var array<string, User|null>
     */
    private static array $userCache = [];

    /**
     * @return array{id: string, name: ?string, display_name: ?string, profile_photo: ?string, profile_photo_url: ?string, designation: ?string, company_name: ?string, city: ?string, category: ?string, level4_category: ?string, life_impacted_count: int, is_pro: bool}|null
     */
    public function resolve(?Authenticatable $authUser, mixed $row): ?array
    {
        if (! $authUser) {
            return null;
        }

        $attributes = $this->extractAttributes($row);
        $otherUserId = $this->resolveOtherUserId($attributes, (string) $authUser->getAuthIdentifier());

        if (! $otherUserId) {
            return null;
        }

        $user = $this->loadUser($otherUserId);

        return $this->formatUserDetails($user);
    }

    /**
     * @return array{id: string, name: ?string, display_name: ?string, profile_photo: ?string, profile_photo_url: ?string, designation: ?string, company_name: ?string, city: ?string, category: ?string, level4_category: ?string, life_impacted_count: int, is_pro: bool}|null
     */
    public function formatUserDetails(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $name = $this->buildName($user);
        $profilePhotoUrl = $this->buildProfilePhotoUrl($user);
        $designation = filled($user->designation ?? $user->job_title ?? null) ? (string) ($user->designation ?? $user->job_title) : null;
        $companyName = filled($user->company_name ?? null) ? (string) $user->company_name : null;
        $city = filled($user->city ?? $user->business_city ?? $user->city_of_residence ?? null)
            ? (string) ($user->city ?? $user->business_city ?? $user->city_of_residence)
            : null;
        $level4Category = $this->resolveLevel4Category($user);
        $lifeImpactedCount = (int) ($user->life_impacted_count ?? 0);
        $isPro = method_exists($user, 'isPro') ? (bool) $user->isPro() : false;

        return [
            'id' => (string) $user->id,
            'name' => $name,
            
            'profile_photo_url' => $profilePhotoUrl,
            'designation' => $designation,
            'company_name' => $companyName,
            'city' => $city,
           
            'level4_category' => $level4Category,
            'life_impacted_count' => $lifeImpactedCount,
            'is_pro' => $isPro,
        ];
    }

    private function extractAttributes(mixed $row): array
    {
        if (is_object($row) && method_exists($row, 'getAttributes')) {
            return $row->getAttributes();
        }

        return (array) $row;
    }

    private function resolveOtherUserId(array $attributes, string $authUserId): ?string
    {
        if (array_key_exists('initiator_user_id', $attributes) && array_key_exists('peer_user_id', $attributes)) {
            return $attributes['initiator_user_id'] === $authUserId
                ? ($attributes['peer_user_id'] ?? null)
                : ($attributes['initiator_user_id'] ?? null);
        }

        if (array_key_exists('from_user_id', $attributes) && array_key_exists('to_user_id', $attributes)) {
            return $attributes['from_user_id'] === $authUserId
                ? ($attributes['to_user_id'] ?? null)
                : ($attributes['from_user_id'] ?? null);
        }

        return null;
    }

    private function loadUser(string $userId): ?User
    {
        if (! array_key_exists($userId, self::$userCache)) {
            self::$userCache[$userId] = User::find($userId);
        }

        return self::$userCache[$userId];
    }

    private function buildName(User $user): ?string
    {
        $displayName = trim((string) ($user->display_name ?? ''));
        if ($displayName !== '') {
            return $displayName;
        }

        $fullName = trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? '')));
        if ($fullName !== '') {
            return $fullName;
        }

        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $email = (string) ($user->email ?? '');
        if ($email !== '') {
            return Str::before($email, '@');
        }

        return null;
    }

    private function buildProfilePhotoUrl(User $user): ?string
    {
        $profilePhotoFileId = $user->profile_photo_file_id
            ?? $user->profile_photo_id
            ?? $user->profile_image_id
            ?? null;

        if ($profilePhotoFileId) {
            return url('/api/v1/files/'.$profilePhotoFileId);
        }

        return $user->profile_photo_url ?? null;
    }

    private function resolveLevel4Category(User $user): ?string
    {
        if ($user->relationLoaded('level4Category') && $user->level4Category) {
            return $user->level4Category->name ?? null;
        }

        if (filled($user->business_sub_category ?? null)) {
            return trim((string) $user->business_sub_category);
        }

        if (blank($user->business_category_id ?? null) && $user->id && class_exists(CustomCategoryRequest::class) && Schema::hasTable('custom_category_requests')) {
            $query = CustomCategoryRequest::query()->where('user_id', (string) $user->id);
            if (! empty($user->main_business_category_id)) {
                $query->where('level1_category_id', (int) $user->main_business_category_id);
            }
            $otherCategoryReq = $query->latest()->first();
            if ($otherCategoryReq && filled($otherCategoryReq->category_name)) {
                return trim((string) $otherCategoryReq->category_name);
            }
        }

        if (! empty($user->business_category_id) && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            $cat = CircleCategoryLevel4::find($user->business_category_id);
            if ($cat && filled($cat->name)) {
                return trim((string) $cat->name);
            }
        }

        if (Schema::hasTable('circle_members') && Schema::hasColumn('circle_members', 'level_4_category_id') && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            try {
                $level4Id = DB::table('circle_members')
                    ->where('user_id', (string) $user->id)
                    ->whereNotNull('level_4_category_id')
                    ->where('level_4_category_id', '>', 0)
                    ->value('level_4_category_id');

                if ($level4Id) {
                    $name = DB::table('circle_category_level4')->where('id', $level4Id)->value('name');
                    if (filled($name)) {
                        return trim((string) $name);
                    }
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
