<?php

namespace App\Http\Middleware;

use App\Services\IndustryDirector\IndustryScopeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIndustryDirector
{
    public function __construct(private readonly IndustryScopeService $industryScope)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $hasRole = DB::table('admin_user_roles')
            ->join('roles', 'roles.id', '=', 'admin_user_roles.role_id')
            ->where('admin_user_roles.user_id', $admin->id)
            ->where('roles.key', 'industry_director')
            ->exists();

        if (! $hasRole) {
            abort(403);
        }

        $industryId = $this->industryScope->assignedIndustryIdForAdmin((string) $admin->id);

        if (! $industryId) {
            abort(403, 'Industry Director industry assignment missing.');
        }

        $request->attributes->set('industry_id', $industryId);
        session(['industry_director.industry_id' => $industryId]);

        return $next($request);
    }
}
