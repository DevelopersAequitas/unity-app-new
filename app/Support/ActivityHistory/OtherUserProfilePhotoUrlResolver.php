<?php

namespace App\Support\ActivityHistory;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class OtherUserProfilePhotoUrlResolver
{
    /**
     * @var array<string, User|null>
     */
    private static array $userCache = [];

    public function resolve(?Authenticatable $authUser, mixed $row): ?string
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

        if (! $user) {
            return null;
        }

        $profilePhotoFileId = null;

        if (isset($user->profile_photo_file_id)) {
            $profilePhotoFileId = $user->profile_photo_file_id;
        } elseif (isset($user->profile_photo_id)) {
            $profilePhotoFileId = $user->profile_photo_id;
        }

        if (! $profilePhotoFileId) {
            return null;
        }

        return url('/api/v1/files/'.$profilePhotoFileId);
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
}
