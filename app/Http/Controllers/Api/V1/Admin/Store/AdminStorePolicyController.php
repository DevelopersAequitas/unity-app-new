<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminPolicyRequest;
use App\Models\Store\PolicyPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStorePolicyController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $policies = PolicyPage::orderBy('key', 'asc')->orderBy('version', 'desc')->get();

        return $this->success($policies, 'Admin policies retrieved');
    }

    public function store(AdminPolicyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user() ? $request->user()->id : null;

        $policy = PolicyPage::create($data);

        return $this->success($policy, 'Policy created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $policy = PolicyPage::findOrFail($id);
        $policy->update($request->all());

        return $this->success($policy, 'Policy updated');
    }

    public function publish(string $id): JsonResponse
    {
        $policy = PolicyPage::findOrFail($id);
        $policy->update([
            'status' => 'PUBLISHED',
            'published_at' => now(),
        ]);

        return $this->success($policy, 'Policy version published');
    }
}
