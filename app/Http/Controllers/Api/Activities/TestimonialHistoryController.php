<?php

namespace App\Http\Controllers\Api\Activities;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\TableRowResource;
use App\Models\Testimonial;
use App\Support\ActivityHistory\OtherUserNameResolver;
use Illuminate\Http\Request;

class TestimonialHistoryController extends BaseApiController
{
    public function index(Request $request)
    {
        $authUserId = $request->user()->id;
        $filter = $request->query('filter', 'given');
        $debugMode = $request->boolean('debug');

        $query = Testimonial::query();
        $whereParts = [];

        $query->where(function ($q) use (&$whereParts) {
            $q->where('is_deleted', false)
                ->orWhereNull('is_deleted');

            $whereParts[] = '(is_deleted = false OR is_deleted IS NULL)';
        });

        $query->whereNull('deleted_at');
        $whereParts[] = 'deleted_at IS NULL';

        if ($filter === 'received') {
            $query->where('to_user_id', $authUserId);
            $whereParts[] = 'to_user_id = "'.$authUserId.'"';
        } else {
            $query->where('from_user_id', $authUserId);
            $whereParts[] = 'from_user_id = "'.$authUserId.'"';
            $filter = 'given';
        }

        $items = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $nameResolver = app(OtherUserNameResolver::class);

        $otherUserIds = $items->map(fn (Testimonial $testimonial): ?string => $this->resolveOtherUserId($testimonial, $authUserId));
        $nameMap = $nameResolver->mapNames($otherUserIds);

        $items = TableRowResource::collection(
            $items->map(function (Testimonial $testimonial) use ($nameMap, $authUserId) {
                $attributes = $testimonial->getAttributes();
                $otherUserId = $this->resolveOtherUserId($testimonial, $authUserId);
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

    public function show(Request $request, string $id)
    {
        $authUserId = $request->user()->id;
        $debugMode = $request->boolean('debug');
        $filterUsed = null;
        $whereParts = [];

        $query = Testimonial::query();

        $query->where('id', $id);
        $whereParts[] = 'id = "'.$id.'"';

        $query->where(function ($q) use (&$whereParts) {
            $q->where('is_deleted', false)
                ->orWhereNull('is_deleted');

            $whereParts[] = '(is_deleted = false OR is_deleted IS NULL)';
        });

        $query->whereNull('deleted_at');
        $whereParts[] = 'deleted_at IS NULL';

        $query->where(function ($q) use ($authUserId, &$whereParts, &$filterUsed) {
            $q->where('from_user_id', $authUserId)
                ->orWhere('to_user_id', $authUserId);

            $whereParts[] = '(from_user_id = "'.$authUserId.'" OR to_user_id = "'.$authUserId.'")';
            $filterUsed = 'all';
        });

        $testimonial = $query->first();

        if (! $testimonial) {
            return $this->error('Testimonial not found', 404);
        }

        $nameResolver = app(OtherUserNameResolver::class);
        $otherUserId = $this->resolveOtherUserId($testimonial, $authUserId);
        $nameMap = $nameResolver->mapNames(collect([$otherUserId]));

        $response = $testimonial->getAttributes();
        $response['other_user_name'] = $otherUserId ? ($nameMap[$otherUserId] ?? null) : null;

        if ($debugMode) {
            $response = [
                'item' => $response,
                'debug' => [
                    'auth_user_id' => $authUserId,
                    'filter' => $filterUsed,
                    'where' => implode(' AND ', $whereParts),
                ],
            ];
        }

        return $this->success($response);
    }

    private function resolveOtherUserId(Testimonial $testimonial, string $authUserId): ?string
    {
        if ($testimonial->from_user_id === $authUserId) {
            return $testimonial->to_user_id;
        }

        return $testimonial->from_user_id;
    }
}
