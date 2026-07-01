<?php

namespace App\Http\Controllers\Api\Activities;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\TableRowResource;
use App\Models\Referral;
use App\Support\ActivityHistory\OtherUserNameResolver;
use Illuminate\Http\Request;

class ReferralHistoryController extends BaseApiController
{
    public function index(Request $request)
    {
        $authUserId = $request->user()->id;
        $filter = $request->query('filter', 'all');
        $debugMode = $request->boolean('debug');

        $query = Referral::query();
        $whereParts = [];

        $query->where(function ($q) use (&$whereParts) {
            $q->where('is_deleted', false)
                ->orWhereNull('is_deleted');

            $whereParts[] = '(is_deleted = false OR is_deleted IS NULL)';
        });

        $query->whereNull('deleted_at');
        $whereParts[] = 'deleted_at IS NULL';

        if ($filter === 'given') {
            $query->where('from_user_id', $authUserId);
            $whereParts[] = 'from_user_id = "'.$authUserId.'"';
        } elseif ($filter === 'received') {
            $query->where('to_user_id', $authUserId);
            $whereParts[] = 'to_user_id = "'.$authUserId.'"';
        } else {
            $query->where(function ($q) use ($authUserId, &$whereParts) {
                $q->where('from_user_id', $authUserId)
                    ->orWhere('to_user_id', $authUserId);

                $whereParts[] = '(from_user_id = "'.$authUserId.'" OR to_user_id = "'.$authUserId.'")';
            });
            $filter = 'all';
        }

        $items = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $nameResolver = app(OtherUserNameResolver::class);

        $otherUserIds = $items->map(fn (Referral $referral): ?string => $this->resolveOtherUserId($referral, $authUserId));
        $nameMap = $nameResolver->mapNames($otherUserIds);

        $items = TableRowResource::collection(
            $items->map(function (Referral $referral) use ($nameMap, $authUserId) {
                $attributes = $referral->getAttributes();
                $otherUserId = $this->resolveOtherUserId($referral, $authUserId);
                $attributes['other_user_name'] = $otherUserId ? ($nameMap[$otherUserId] ?? null) : null;

                return $attributes;
            })
        );

        $response = [
            'items' => $items,
        ];

        if ($debugMode) {
            $response['debug'] = [
                'auth_user_id' => $authUserId,
                'filter' => $filter,
                'where' => implode(' AND ', $whereParts),
            ];
        }

        return $this->success($response);
    }

    private function resolveOtherUserId(Referral $referral, string $authUserId): ?string
    {
        if ($referral->from_user_id === $authUserId) {
            return $referral->to_user_id;
        }

        return $referral->from_user_id;
    }
}
