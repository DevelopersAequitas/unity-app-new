<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MyCircleMembershipResource;
use App\Models\CircleMember;
use Illuminate\Http\Request;

class MyCircleController extends BaseApiController
{
    public function index(Request $request)
    {
        $memberships = CircleMember::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->whereNull('left_at')
            ->with([
                'circle' => function ($query) {
                    $query->with([
                        'city:id,name,state,district,country,country_code',
                        'founder:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                        'founder.cityRelation:id,name',
                        'director:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                        'director.cityRelation:id,name',
                        'industryDirector:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                        'industryDirector.cityRelation:id,name',
                        'ded:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                        'ded.cityRelation:id,name',
                    ])
                        ->withCount([
                            'members as members_count' => function ($query) {
                                $query->where('status', 'approved');
                            },
                            'members as peers_count' => function ($query) {
                                $query->where('status', 'approved');
                            },
                        ]);
                },
            ])
            ->orderByRaw('CASE WHEN paid_starts_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('paid_starts_at')
            ->orderByDesc('joined_at')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'items' => MyCircleMembershipResource::collection($memberships),
        ], 'My circles fetched successfully.');
    }
}
