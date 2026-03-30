<?php

namespace App\Http\Controllers\Api;

use App\Models\User;

class MemberWithCircleController extends BaseApiController
{
    public function index()
    {
        $members = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'display_name',
                'email',
                'phone',
                'designation',
                'company_name',
                'profile_photo_url',
                'city_id',
                'city',
                'membership_status',
                'membership_expiry',
                'coins_balance',
                'public_profile_slug',
                'last_login_at',
                'status',
                'created_at',
            ])
            ->with([
                'city:id,name',
                'circleMembers' => function ($query) {
                    $query->select(['id', 'user_id', 'circle_id', 'role', 'status'])
                        ->with('circle:id,name');
                },
            ])
            ->orderByDesc('created_at')
            ->get();

        $items = $members->map(function (User $member): array {
            $fullName = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $name = $member->display_name ?: ($fullName !== '' ? $fullName : $member->email);

            $circles = $member->circleMembers
                ->map(function ($circleMember): array {
                    return [
                        'circle_member_id' => $circleMember->id,
                        'circle_id' => $circleMember->circle_id,
                        'circle_name' => $circleMember->circle?->name,
                        'role' => $circleMember->role,
                        'status' => $circleMember->status,
                    ];
                })
                ->values();

            return [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'display_name' => $member->display_name,
                'name' => $name,
                'email' => $member->email,
                'phone' => $member->phone,
                'designation' => $member->designation,
                'company_name' => $member->company_name,
                'profile_photo_url' => $member->profile_photo_url,
                'profile_image_url' => $member->profile_photo_url,
                'city_id' => $member->city_id,
                'city_name' => $member->city?->name ?? $member->city,
                'country_name' => null,
                'membership_status' => $member->membership_status,
                'membership_expiry' => $member->membership_expiry,
                'coins_balance' => $member->coins_balance,
                'public_profile_slug' => $member->public_profile_slug,
                'last_login_at' => $member->last_login_at,
                'status' => $member->status,
                'created_at' => $member->created_at,
                'circles_count' => $circles->count(),
                'circles' => $circles,
            ];
        })->values();

        return $this->success([
            'items' => $items,
        ]);
    }
}
