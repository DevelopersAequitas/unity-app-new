<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if ($search === '') {
            return response()->json([]);
        }

        $words = array_filter(explode(' ', $search));

        $users = User::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($words): void {
                foreach ($words as $word) {
                    $like = "%{$word}%";
                    $query->where(function ($sub) use ($like): void {
                        $sub->where('display_name', 'ILIKE', $like)
                            ->orWhere('first_name', 'ILIKE', $like)
                            ->orWhere('last_name', 'ILIKE', $like)
                            ->orWhere('email', 'ILIKE', $like)
                            ->orWhere('company_name', 'ILIKE', $like)
                            ->orWhere('city', 'ILIKE', $like)
                            ->orWhere('phone', 'ILIKE', $like);

                        $driver = DB::connection()->getDriverName();
                        if ($driver === 'sqlite') {
                            $sub->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", [strtolower($like)]);
                        } else {
                            $sub->orWhereRaw("TRIM(CONCAT_WS(' ', COALESCE(first_name, ''), COALESCE(last_name, ''))) ILIKE ?", [$like]);
                        }
                    });
                }
            })
            ->with(['circleMembers' => function ($query) {
                $query->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }])
            ->orderByRaw("COALESCE(NULLIF(display_name,''), NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)),''), email) ASC")
            ->limit(10)
            ->get();

        if ($users->isEmpty()) {
            Log::info('admin.users.search.no_results', [
                'search' => $search,
            ]);
        }

        $results = $users->map(function (User $user): array {
            [$name, $company, $city, $circle] = $user->adminDisplayParts();

            return [
                'id' => $user->id,
                'name' => $name,
                'company' => $company,
                'city' => $city,
                'circle' => $circle,
                'label' => $user->adminDisplayLabel(),
                'label_inline' => $user->adminDisplayInlineLabel(),
            ];
        })->values();

        return response()->json($results);
    }
}
