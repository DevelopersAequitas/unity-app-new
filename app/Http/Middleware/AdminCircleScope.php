<?php

namespace App\Http\Middleware;

use App\Services\IndustryDirector\IndustryScopeService;
use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AdminCircleScope
{
    public function __construct(private readonly IndustryScopeService $industryScope)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        if (AdminAccess::isSuper($admin)) {
            $request->attributes->set('is_circle_scoped', false);
            $request->attributes->set('is_industry_scoped', false);
            return $next($request);
        }

        if (AdminAccess::isIndustryScoped($admin)) {
            $industryId = $this->industryScope->assignedIndustryIdForAdmin((string) $admin->id);

            if (! $industryId) {
                abort(403, 'Industry Director industry assignment missing.');
            }

            $allowedCircleIds = $this->industryScope->industryCircleIds($industryId);
            $request->attributes->set('industry_id', $industryId);
            $request->attributes->set('allowed_circle_ids', $allowedCircleIds);
            $request->attributes->set('is_circle_scoped', false);
            $request->attributes->set('is_industry_scoped', true);
            session(['industry_director.industry_id' => $industryId]);

            $routeName = $request->route()?->getName() ?? '';
            $allowedPrefixes = [
                'admin.industry-director.',
                'admin.users.',
                'admin.activities.',
                'admin.posts.',
                'admin.visitor-registrations.',
                'admin.coin-claims.',
                'admin.circle-joining-requests.',
                'admin.circles.',
                'admin.circle-peers.',
                'admin.coins.',
                'admin.life-impact.',
                'admin.impacts.pending',
            ];
            $allowedRoutes = ['admin.logout', 'admin.files.upload'];

            if (in_array($routeName, ['admin.dashboard', 'admin.home'], true)) {
                return redirect()->route('admin.industry-director.dashboard');
            }

            if ($routeName !== '' && ! in_array($routeName, $allowedRoutes, true) && ! Str::startsWith($routeName, $allowedPrefixes)) {
                abort(403);
            }

            return $next($request);
        }

        if (AdminAccess::isCircleScoped($admin)) {
            $allowedCircleIds = AdminAccess::allowedCircleIds($admin);
            $request->attributes->set('allowed_circle_ids', $allowedCircleIds);
            $request->attributes->set('is_circle_scoped', true);
            $request->attributes->set('primary_circle_role_label', AdminAccess::primaryCircleRoleLabel($admin));

            $routeName = $request->route()?->getName() ?? '';
            $allowedPrefixes = ['admin.users.', 'admin.activities.', 'admin.coins.', 'admin.visitor-registrations.', 'admin.circle-joining-requests.'];
            $allowedRoutes = ['admin.logout', 'admin.files.upload'];

            if (in_array($routeName, ['admin.dashboard', 'admin.home'], true) || Str::startsWith($routeName, 'admin.circles.')) {
                return redirect()->route('admin.users.index');
            }

            if ($routeName !== '' && ! in_array($routeName, $allowedRoutes, true) && ! Str::startsWith($routeName, $allowedPrefixes)) {
                abort(403);
            }

            return $next($request);
        }

        $request->attributes->set('allowed_circle_ids', []);
        $request->attributes->set('is_circle_scoped', false);
        $request->attributes->set('is_industry_scoped', false);

        return $next($request);
    }
}
