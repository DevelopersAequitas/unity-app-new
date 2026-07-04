<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CirclePeersController extends Controller
{
    public function peerOptions(Request $request, Circle $circle): JsonResponse
    {
        $isCircleScoped = (bool) $request->attributes->get('is_circle_scoped');
        $allowedCircleIds = $request->attributes->get('allowed_circle_ids');

        if ($isCircleScoped && is_array($allowedCircleIds) && ! in_array($circle->id, $allowedCircleIds, true)) {
            abort(403);
        }

        $queryString = trim((string) $request->query('q', ''));

        $hasName = Schema::hasColumn('users', 'name');
        $hasDisplayName = Schema::hasColumn('users', 'display_name');
        $hasCompanyName = Schema::hasColumn('users', 'company_name');
        $hasCompany = Schema::hasColumn('users', 'company');
        $hasCity = Schema::hasColumn('users', 'city');

        $nameExpr = $hasName
            ? 'users.name'
            : ($hasDisplayName
                ? 'users.display_name'
                : "TRIM(CONCAT_WS(' ', COALESCE(users.first_name, ''), COALESCE(users.last_name, '')))"
            );

        $companyExpr = $hasCompanyName
            ? 'users.company_name'
            : ($hasCompany ? 'users.company' : "''");

        $cityExpr = $hasCity ? 'users.city' : "''";

        $duplicateNames = DB::table('users')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->selectRaw("{$nameExpr} as name, count(*) as count")
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->pluck('count', 'name')
            ->toArray();

        $page = (int) $request->query('page', 1);
        $perPage = 30;

        $rows = DB::table('users')
            ->leftJoin('circles as c_active', 'c_active.id', '=', 'users.active_circle_id')
            ->whereNull('users.deleted_at')
            ->where('users.status', 'active')
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '')
            ->where(function ($q): void {
                $q->whereNotNull('users.display_name')
                    ->where('users.display_name', '!=', '')
                    ->orWhereNotNull('users.first_name')
                    ->where('users.first_name', '!=', '');
            })
            ->whereNotIn('users.id', function ($subQuery) use ($circle): void {
                $subQuery->select('user_id')
                    ->from('circle_members')
                    ->where('circle_id', $circle->id)
                    ->whereNull('deleted_at');
            })
            ->when($queryString !== '', function ($query) use ($queryString, $nameExpr, $companyExpr, $cityExpr): void {
                $like = "%{$queryString}%";

                $query->where(function ($searchQuery) use ($like, $nameExpr, $companyExpr, $cityExpr): void {
                    $searchQuery->whereRaw("{$nameExpr} ILIKE ?", [$like])
                        ->orWhere('users.email', 'ILIKE', $like)
                        ->orWhereRaw("COALESCE({$companyExpr}, '') ILIKE ?", [$like])
                        ->orWhereRaw("COALESCE({$cityExpr}, '') ILIKE ?", [$like])
                        ->orWhere('c_active.name', 'ILIKE', $like)
                        ->orWhereExists(function ($sub) use ($like) {
                            $sub->select(DB::raw(1))
                                ->from('circle_members as cm_search')
                                ->join('circles as c_search', 'c_search.id', '=', 'cm_search.circle_id')
                                ->whereRaw('cm_search.user_id = users.id')
                                ->whereNull('cm_search.deleted_at')
                                ->where('c_search.name', 'ILIKE', $like);
                        });
                });
            })
            ->selectRaw(
                "users.id,
                {$nameExpr} as name,
                users.email,
                COALESCE({$companyExpr}, '') as company,
                COALESCE({$cityExpr}, '') as city,
                COALESCE(
                    c_active.name,
                    (
                        SELECT c.name
                        FROM circle_members cm
                        JOIN circles c ON c.id = cm.circle_id
                        WHERE cm.user_id = users.id
                          AND cm.deleted_at IS NULL
                        ORDER BY cm.created_at DESC
                        LIMIT 1
                    ),
                    ''
                ) as circle"
            )
            ->orderByRaw("{$nameExpr} ASC")
            ->paginate($perPage, ['*'], 'page', $page);

        $results = collect($rows->items())->map(function ($row) use ($duplicateNames) {
            $name = trim((string) $row->name);
            $company = trim((string) $row->company);
            $city = trim((string) $row->city);
            $email = trim((string) $row->email);
            $circleName = trim((string) $row->circle);

            $text = $name;
            if (isset($duplicateNames[$name])) {
                if ($company !== '') {
                    $text .= " ({$company})";
                } elseif ($city !== '') {
                    $text .= " ({$city})";
                }
            }

            return [
                'id' => $row->id,
                'text' => $text,
                'name' => $name,
                'company' => $company,
                'city' => $city,
                'email' => $email,
                'circle' => $circleName,
            ];
        })->values();

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => $rows->hasMorePages(),
            ],
        ]);
    }
}
