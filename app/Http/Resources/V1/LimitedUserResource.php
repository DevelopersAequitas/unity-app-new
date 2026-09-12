<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\City;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class LimitedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        $name = $user->display_name
            ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        $cityName = null;
        $countryCode = 'IN';

        $cityRelation = $user->relationLoaded('city') ? $user->getRelation('city') : null;
        if ($cityRelation instanceof City) {
            $cityName = $cityRelation->name;
            $countryCode = $cityRelation->country_code ?: ($cityRelation->country ? ($cityRelation->country === 'India' ? 'IN' : strtoupper(substr((string) $cityRelation->country, 0, 2))) : 'IN');
        } else {
            $cityName = is_string($user->city) ? $user->city : ($user->city_of_residence ?? null);
            if (! empty($user->country)) {
                $countryCode = $user->country === 'India' ? 'IN' : strtoupper(substr((string) $user->country, 0, 2));
            }
        }

        $formattedCity = null;
        if (filled($cityName)) {
            $cityName = trim((string) $cityName);
            if (str_contains($cityName, ',')) {
                $formattedCity = $cityName;
            } else {
                $formattedCity = "{$cityName}, {$countryCode}";
            }
        }

        $authUser = auth('sanctum')->user() ?: ($request instanceof Request ? $request->user() : null);
        $isBookmark = false;
        if ($authUser) {
            $bookmarks = $authUser->bookmarks ?? [];
            $isBookmark = in_array((string) $user->id, $bookmarks, true);
        }

        $rawVerified = $user->is_verified ?? null;
        if ($rawVerified !== null) {
            $isVerified = (bool) $rawVerified;
        } elseif (method_exists($user, 'isPaidMember')) {
            $isVerified = (bool) $user->isPaidMember();
        } else {
            $isVerified = false;
        }

        $isConnected = false;
        $connectionStatus = null;
        $isRequested = false;
        $canSendConnectionRequest = true;

        if ($user->getAttribute('is_connected') !== null) {
            $isConnected = (bool) $user->getAttribute('is_connected');
            $connectionStatus = $user->getAttribute('connection_status');
            $isRequested = (bool) $user->getAttribute('is_requested');
            $canSendConnectionRequest = (bool) $user->getAttribute('can_send_connection_request');
        } elseif ($authUser && Schema::hasTable('connections')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $user->id;

            if ($authUserId !== $targetId) {
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
                    $canSendConnectionRequest = false;
                }
            }
        }

        return [
            'id' => $user->id,
            'name' => $name !== '' ? trim((string) $name) : null,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'city' => $formattedCity,
            'company_name' => $user->company_name,
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'profile_photo_image' => $user->profile_photo_url,
            'designation' => $user->designation,
            'level4_category' => $user->level4Category ? $user->level4Category->name : null,
            'is_bookmark' => $isBookmark,
            'is_verified' => $isVerified,
            'is_connected' => $isConnected,
            'connection_status' => $connectionStatus,
            'is_requested' => $isRequested,
            'can_send_connection_request' => $canSendConnectionRequest,
            'match_percentage' => (int) ($user->match_percentage ?? 0),
        ];
    }
}
