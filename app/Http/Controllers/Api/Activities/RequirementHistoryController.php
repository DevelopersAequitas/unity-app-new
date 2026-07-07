<?php

namespace App\Http\Controllers\Api\Activities;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\TableRowResource;
use App\Models\Requirement;
use Illuminate\Http\Request;

class RequirementHistoryController extends BaseApiController
{
    public function index(Request $request)
    {
        $authUserId = $request->user()->id;
        $debugMode = $request->boolean('debug');
        $filter = 'given';
        $whereParts = [];

        $query = Requirement::query();

        $query->where('user_id', $authUserId);
        $whereParts[] = 'user_id = "'.$authUserId.'"';

        $query->whereNull('deleted_at');
        $whereParts[] = 'deleted_at IS NULL';

        $items = TableRowResource::collection(
            $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
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
}
