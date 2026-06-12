<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use App\Support\UserOptionLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CirclePeersController extends Controller
{
    public function peerOptions(Request $request, Circle $circle): JsonResponse
    {
        $this->authorizeCirclePeerSearch($request, $circle);

        $queryString = trim((string) $request->query('term', $request->query('q', '')));

        $hasDeletedAt = Schema::hasColumn('users', 'deleted_at');
        $hasName = Schema::hasColumn('users', 'name');
        $hasDisplayName = Schema::hasColumn('users', 'display_name');
        $hasFirstName = Schema::hasColumn('users', 'first_name');
        $hasLastName = Schema::hasColumn('users', 'last_name');
        $hasPhone = Schema::hasColumn('users', 'phone');
        $hasCompanyName = Schema::hasColumn('users', 'company_name');
        $hasCompany = Schema::hasColumn('users', 'company');
        $hasCity = Schema::hasColumn('users', 'city');

        $fullNameExpr = ($hasFirstName || $hasLastName)
            ? "TRIM(CONCAT_WS(' ', COALESCE(users.first_name, ''), COALESCE(users.last_name, '')))"
            : "''";
        $nameCandidates = [];
        if ($hasName) {
            $nameCandidates[] = "NULLIF(TRIM(users.name), '')";
        }
        if ($hasDisplayName) {
            $nameCandidates[] = "NULLIF(TRIM(users.display_name), '')";
        }
        $nameCandidates[] = "NULLIF({$fullNameExpr}, '')";
        $nameCandidates[] = "users.email";
        $nameExpr = 'COALESCE('.implode(', ', $nameCandidates).')';

        $companyExpr = $hasCompanyName
            ? 'users.company_name'
            : ($hasCompany ? 'users.company' : "''");

        $cityExpr = $hasCity ? 'users.city' : "''";

        $rows = DB::table('users')
            ->when($hasDeletedAt, fn ($query) => $query->whereNull('users.deleted_at'))
            ->whereNotIn('users.id', function ($subQuery) use ($circle): void {
                $subQuery->select('user_id')
                    ->from('circle_members')
                    ->where('circle_id', $circle->id)
                    ->whereNull('deleted_at');
            })
            ->when($queryString !== '', function ($query) use ($queryString, $nameExpr, $companyExpr, $cityExpr, $hasDisplayName, $hasFirstName, $hasLastName, $hasPhone): void {
                $like = "%{$queryString}%";

                $query->where(function ($searchQuery) use ($like, $nameExpr, $companyExpr, $cityExpr, $hasDisplayName, $hasFirstName, $hasLastName, $hasPhone): void {
                    $searchQuery->whereRaw("{$nameExpr} ILIKE ?", [$like])
                        ->orWhere('users.email', 'ILIKE', $like)
                        ->orWhereRaw("COALESCE({$companyExpr}, '') ILIKE ?", [$like])
                        ->orWhereRaw("COALESCE({$cityExpr}, '') ILIKE ?", [$like]);

                    if ($hasDisplayName) {
                        $searchQuery->orWhere('users.display_name', 'ILIKE', $like);
                    }
                    if ($hasFirstName) {
                        $searchQuery->orWhere('users.first_name', 'ILIKE', $like);
                    }
                    if ($hasLastName) {
                        $searchQuery->orWhere('users.last_name', 'ILIKE', $like);
                    }
                    if ($hasPhone) {
                        $searchQuery->orWhere('users.phone', 'ILIKE', $like);
                    }
                });
            })
            ->selectRaw(
                "users.id,
                users.email,
                {$nameExpr} as name,
                COALESCE({$companyExpr}, '') as company,
                COALESCE({$cityExpr}, '') as city,
                COALESCE((
                    SELECT c.name
                    FROM circle_members cm
                    JOIN circles c ON c.id = cm.circle_id
                    WHERE cm.user_id = users.id
                      AND cm.deleted_at IS NULL
                    ORDER BY cm.created_at DESC
                    LIMIT 1
                ), '') as circle"
            )
            ->orderByRaw("{$nameExpr} ASC")
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $rows
                ->map(function ($row): array {
                    $name = trim((string) ($row->name ?? '')) ?: 'Unknown';
                    $email = trim((string) ($row->email ?? ''));

                    return [
                        'id' => $row->id,
                        'text' => $email !== '' ? "{$name} - {$email}" : UserOptionLabel::makeFromRow((array) $row),
                    ];
                })
                ->values(),
        ]);
    }
    private function authorizeCirclePeerSearch(Request $request, Circle $circle): void
    {
        $admin = Auth::guard('admin')->user();

        if (AdminAccess::isGlobalAdmin($admin)) {
            return;
        }

        $allowedCircleIds = $request->attributes->get('allowed_circle_ids');
        if (is_array($allowedCircleIds) && in_array((string) $circle->id, array_map('strval', $allowedCircleIds), true)) {
            return;
        }

        if (AdminAccess::isDed($admin)) {
            $query = Circle::query()->whereKey($circle->id);
            AdminCircleScope::applyToCirclesQuery($query, $admin);
            if ($query->exists()) {
                return;
            }
        }

        if (is_array($allowedCircleIds)) {
            Log::warning('Circle peer search access denied', [
                'admin_id' => $admin?->id,
                'role' => AdminAccess::adminRoleKeys($admin),
                'circle_id' => $circle->id,
            ]);
            abort(403);
        }
    }
}
