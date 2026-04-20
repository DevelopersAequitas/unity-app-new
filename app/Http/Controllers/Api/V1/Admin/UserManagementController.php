<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CircleMember;
use App\Models\Impact;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends BaseApiController
{
    public function __construct(private readonly AdminScopeService $scope, private readonly AdminAuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = User::query()->with(['roles:id,key,name'])->withCount('circleMemberships');
        $this->scope->applyUserScope($q, $request->user());

        if ($search = $request->string('search')->toString()) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $q->where(fn ($x) => $x->where('first_name', 'ILIKE', $term)->orWhere('last_name', 'ILIKE', $term)->orWhere('display_name', 'ILIKE', $term)->orWhere('email', 'ILIKE', $term)->orWhere('phone', 'ILIKE', $term));
        }

        $q->when($request->filled('membership_status'), fn ($x) => $x->where('membership_status', $request->string('membership_status')))
            ->when($request->filled('is_active'), fn ($x) => $x->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('role'), fn ($x) => $x->whereHas('roles', fn ($r) => $r->where('key', $request->string('role'))))
            ->when($request->filled('circle_id'), fn ($x) => $x->whereHas('circleMemberships', fn ($m) => $m->where('circle_id', $request->string('circle_id'))->whereNull('deleted_at')))
            ->orderByDesc('created_at');

        return $this->success($q->paginate((int) $request->input('per_page', 20)));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::query()->with(['roles:id,key,name', 'circleMemberships.circle:id,name'])->findOrFail($id);
        $this->scope->applyUserScope(User::query()->where('id', $id), $request->user())->firstOrFail();

        return $this->success($user);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $target = User::query()->findOrFail($id);
        $this->scope->applyUserScope(User::query()->where('id', $id), $request->user())->firstOrFail();

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:25'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'membership_status' => ['sometimes', 'string'],
        ]);

        $old = $target->only(array_keys($validated));
        $target->fill($validated)->save();

        $this->audit->log($request->user(), 'admin.user.update', 'users', $target->id, $old, $validated, $request);

        return $this->success($target->fresh());
    }

    public function patchStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        return $this->update($request->merge($validated), $id);
    }

    public function patchMembershipStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['membership_status' => ['required', 'string']]);
        return $this->update($request->merge($validated), $id);
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['role' => ['required', 'string']]);
        $target = User::query()->findOrFail($id);
        $role = Role::query()->where('key', $validated['role'])->firstOrFail();
        $target->roles()->syncWithoutDetaching([$role->id]);
        $this->audit->log($request->user(), 'admin.user.assign_role', 'users', $target->id, [], ['role' => $validated['role']], $request);

        return $this->success($target->load('roles'));
    }

    public function removeRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['role' => ['required', 'string']]);
        $target = User::query()->findOrFail($id);
        $role = Role::query()->where('key', $validated['role'])->firstOrFail();
        $target->roles()->detach($role->id);
        $this->audit->log($request->user(), 'admin.user.remove_role', 'users', $target->id, ['role' => $validated['role']], [], $request);

        return $this->success($target->load('roles'));
    }

    public function activitySummary(string $id): JsonResponse
    {
        return $this->success([
            'impacts' => Impact::query()->where('user_id', $id)->count(),
            'approved_impacts' => Impact::query()->where('user_id', $id)->where('status', 'approved')->count(),
            'payments' => Payment::query()->where('user_id', $id)->count(),
            'circles' => CircleMember::query()->where('user_id', $id)->whereNull('deleted_at')->count(),
        ]);
    }

    public function paymentHistory(string $id): JsonResponse
    {
        return $this->success(Payment::query()->where('user_id', $id)->latest('created_at')->paginate(20));
    }

    public function impactHistory(string $id): JsonResponse
    {
        return $this->success(Impact::query()->where('user_id', $id)->latest('created_at')->paginate(20));
    }

    public function circleMemberships(string $id): JsonResponse
    {
        return $this->success(CircleMember::query()->with('circle:id,name')->where('user_id', $id)->whereNull('deleted_at')->get());
    }
}
