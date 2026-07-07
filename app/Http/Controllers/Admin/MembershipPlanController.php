<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MembershipPlans\StoreMembershipPlanRequest;
use App\Http\Requests\Admin\MembershipPlans\UpdateMembershipPlanRequest;
use App\Models\MembershipPlan;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MembershipPlanController extends Controller
{
    public function index(Request $request): View
    {
        $plans = MembershipPlan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $canEdit = AdminAccess::isGlobalAdmin($request->user('admin'));

        return view('admin.unity-peers-plans.index', [
            'plans' => $plans,
            'canEdit' => $canEdit,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeGlobalAdmin($request);

        return view('admin.unity-peers-plans.create');
    }

    public function store(StoreMembershipPlanRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['slug'] = $this->generateUniqueSlug($payload['name']);
        $plan = MembershipPlan::query()->create($payload);

        return redirect()
            ->route('admin.unity-peers-plans.edit', $plan->id)
            ->with('success', 'Membership plan created successfully.');
    }

    public function edit(Request $request, MembershipPlan $plan): View
    {
        $this->authorizeGlobalAdmin($request);

        return view('admin.unity-peers-plans.edit', [
            'plan' => $plan,
        ]);
    }

    public function update(UpdateMembershipPlanRequest $request, MembershipPlan $plan): RedirectResponse
    {
        $payload = $request->validated();
        $payload['slug'] = $this->generateUniqueSlug($payload['name'], $plan->id);
        $plan->update($payload);

        return redirect()
            ->route('admin.unity-peers-plans.index')
            ->with('success', 'Membership plan updated successfully.');
    }

    private function authorizeGlobalAdmin(Request $request): void
    {
        if (! AdminAccess::isGlobalAdmin($request->user('admin'))) {
            abort(403);
        }
    }

    private function generateUniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (MembershipPlan::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
