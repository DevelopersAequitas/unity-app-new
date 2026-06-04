<?php

namespace App\Http\Controllers\Api\V1\Ded;

use App\Models\User;
use App\Support\AdminCircleScope;
use Illuminate\Http\Request;

class DedPeersController extends DedBaseController
{
    public function index(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string'], 'circle_id' => ['nullable', 'string'], 'membership_status' => ['nullable', 'string'], 'status' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $admin = $this->admin($request);
        $query = $this->ded->usersQuery($admin)->with('circleMemberships.circle')
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.$request->query('search').'%';
                $q->where(fn ($inner) => $inner->where('display_name', 'ILIKE', $like)->orWhere('first_name', 'ILIKE', $like)->orWhere('last_name', 'ILIKE', $like)->orWhere('email', 'ILIKE', $like)->orWhere('phone', 'ILIKE', $like)->orWhere('company_name', 'ILIKE', $like)->orWhere('city', 'ILIKE', $like));
            })
            ->when($request->filled('membership_status'), fn ($q) => $q->where('membership_status', $request->query('membership_status')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));
        $this->ded->applyCircleFilter($query, $admin, $request->query('circle_id'), ['users.id']);
        $items = $query->latest('created_at')->paginate($this->ded->perPage($request))->withQueryString();
        return $this->success($items->items(), 'DED peers fetched successfully.', $this->ded->serializePaginator($items));
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Api\Ded\DedApiService;
use Illuminate\Http\Request;

class DedPeersController extends Controller
{
    public function __construct(private readonly DedApiService $ded) {}

    public function circles(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = $this->ded->circlesIndex($this->ded->admin($request), $request);

        return $this->ded->success($paginator->items(), 'DED circles loaded.', $this->ded->paginationMeta($paginator));
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'circle_id' => ['nullable', 'uuid'],
            'membership_status' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $admin = $this->ded->admin($request);
        $this->ded->assertCircleInScope($admin, $request->query('circle_id'));
        $paginator = $this->ded->peersIndex($admin, $request);
        $items = collect($paginator->items())->map(fn (User $user) => $this->ded->userSummary($user))->values();

        return $this->ded->success($items, 'DED peers loaded.', $this->ded->paginationMeta($paginator));
    }

    public function show(Request $request, string $id)
    {
        $admin = $this->admin($request);
        $this->ded->assertUserInDistrict($admin, $id);
        $peer = User::query()->with('circleMemberships.circle')->findOrFail($id);
        return $this->success($peer, 'DED peer fetched successfully.');
        $admin = $this->ded->admin($request);
        $this->ded->assertUserInScope($admin, $id);
        $user = User::query()->with(['city', 'activeCircle', 'circleMemberships.circle'])->findOrFail($id);

        return $this->ded->success($user, 'DED peer loaded.');
    }
}
