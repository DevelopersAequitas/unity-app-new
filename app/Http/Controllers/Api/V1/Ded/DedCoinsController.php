<?php

namespace App\Http\Controllers\Api\V1\Ded;

use App\Models\CoinsLedger;
use Illuminate\Http\Request;

class DedCoinsController extends DedBaseController
{
    public function index(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string'], 'circle_id' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $admin = $this->admin($request);
        $query = $this->ded->usersQuery($admin)->select(['users.id', 'display_name', 'first_name', 'last_name', 'email', 'company_name', 'city', 'coins_balance']);
        $this->ded->applyCircleFilter($query, $admin, $request->query('circle_id'), ['users.id']);
        if ($request->filled('search')) {
            $like = '%'.$request->query('search').'%';
            $query->where(fn ($q) => $q->where('display_name', 'ILIKE', $like)->orWhere('email', 'ILIKE', $like)->orWhere('company_name', 'ILIKE', $like)->orWhere('city', 'ILIKE', $like));
        }
        $items = $query->orderByDesc('coins_balance')->paginate($this->ded->perPage($request))->withQueryString();
        return $this->success($items->items(), 'DED coin summary fetched successfully.', $this->ded->serializePaginator($items));
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Api\Ded\DedApiService;
use Illuminate\Http\Request;

class DedCoinsController extends Controller
{
    public function __construct(private readonly DedApiService $ded) {}

    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'circle_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $admin = $this->ded->admin($request);
        $this->ded->assertCircleInScope($admin, $request->query('circle_id'));
        $paginator = $this->ded->peersIndex($admin, $request);
        $items = collect($paginator->items())->map(fn (User $user) => [
            'user' => $this->ded->userSummary($user),
            'coins_balance' => (int) ($user->coins_balance ?? 0),
        ])->values();

        return $this->ded->success($items, 'DED coin summary loaded.', $this->ded->paginationMeta($paginator));
    }

    public function history(Request $request)
    {
        $request->validate(['user_id' => ['nullable', 'string'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $admin = $this->admin($request);
        if ($request->filled('user_id')) $this->ded->assertUserInDistrict($admin, $request->query('user_id'));
        $query = CoinsLedger::query()->with('user');
        $this->ded->applyActivityScope($query, $admin, 'coins_ledger.user_id');
        if ($request->filled('user_id')) $query->where('user_id', $request->query('user_id'));
        $this->ded->applyDates($query, $request, 'created_at');
        $items = $query->orderByDesc('created_at')->paginate($this->ded->perPage($request))->withQueryString();
        return $this->success($items->items(), 'DED coin history fetched successfully.', $this->ded->serializePaginator($items));
        $request->validate([
            'user_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = $this->ded->coinLedgerQuery($this->ded->admin($request), $request)
            ->latest('created_at')
            ->paginate($this->ded->perPage($request));

        return $this->ded->success($paginator->items(), 'DED coin history loaded.', $this->ded->paginationMeta($paginator));
    }
}
