<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CoinClaimRequest;
use App\Models\Event;
use App\Models\Impact;
use App\Models\Industry;
use App\Models\LeaderInterestSubmission;
use App\Models\Payment;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminExecutionController extends Controller
{
    public function leadership(Request $request)
    {
        $applications = LeaderInterestSubmission::query()
            ->leftJoin('users as applicant', 'applicant.id', '=', 'leader_interest_submissions.user_id')
            ->select([
                'leader_interest_submissions.*',
                DB::raw("COALESCE(NULLIF(applicant.display_name, ''), NULLIF(TRIM(CONCAT_WS(' ', applicant.first_name, applicant.last_name)), ''), applicant.email, '-') as applicant_name"),
                DB::raw("COALESCE(applicant.email, '-') as applicant_email"),
            ])
            ->latest('leader_interest_submissions.created_at')
            ->paginate(20, ['*'], 'applications_page');

        $assignments = DB::table('circle_members as cm')
            ->join('users', 'users.id', '=', 'cm.user_id')
            ->join('circles', 'circles.id', '=', 'cm.circle_id')
            ->whereIn(DB::raw('cm.role::text'), ['founder', 'director', 'chair', 'vice_chair', 'secretary', 'committee_leader'])
            ->selectRaw("cm.id, cm.user_id, cm.circle_id, cm.role::text as role, cm.status, COALESCE(NULLIF(users.display_name, ''), NULLIF(TRIM(CONCAT_WS(' ', users.first_name, users.last_name)), ''), users.email, cm.user_id::text) as user_name, circles.name as circle_name, cm.created_at")
            ->orderByDesc('cm.created_at')
            ->paginate(20, ['*'], 'assignments_page');

        $performance = DB::table('circle_members as cm')
            ->leftJoin('impacts', 'impacts.user_id', '=', 'cm.user_id')
            ->join('users', 'users.id', '=', 'cm.user_id')
            ->whereIn(DB::raw('cm.role::text'), ['founder', 'director', 'chair', 'vice_chair', 'secretary', 'committee_leader'])
            ->where(function ($q): void {
                $q->whereNull('impacts.status')->orWhere('impacts.status', 'approved');
            })
            ->selectRaw("cm.user_id, COALESCE(NULLIF(users.display_name, ''), NULLIF(TRIM(CONCAT_WS(' ', users.first_name, users.last_name)), ''), users.email, '-') as display_name, cm.role::text as role, SUM(COALESCE(impacts.life_impacted, 0)) as impact_score")
            ->groupBy('cm.user_id', 'users.display_name', 'users.first_name', 'users.last_name', 'users.email', DB::raw('cm.role::text'))
            ->orderByDesc('impact_score')
            ->limit(20)
            ->get();

        return view('admin/execution/leadership', compact('applications', 'assignments', 'performance'));
    }

    public function industries()
    {
        $industries = Industry::query()->paginate(20);

        $circles = Circle::query()->select('id', 'industry_tags')->whereNull('deleted_at')->get();
        $industries->setCollection($industries->getCollection()->map(function (Industry $industry) use ($circles) {
            $industry->circles_count = $circles->filter(function (Circle $circle) use ($industry): bool {
                return $this->circleMatchesIndustry($circle->industry_tags, (string) $industry->id, (string) $industry->name);
            })->count();

            return $industry;
        }));

        return view('admin/execution/industries', compact('industries'));
    }



    private function circleMatchesIndustry(mixed $industryTags, string $industryId, string $industryName): bool
    {
        $raw = is_array($industryTags) ? $industryTags : (is_string($industryTags) ? json_decode($industryTags, true) : []);
        $raw = is_array($raw) ? $raw : [];
        $values = collect($raw)->flatMap(function ($tag) {
            if (is_array($tag)) {
                return array_filter([
                    (string) ($tag['id'] ?? ''),
                    (string) ($tag['uuid'] ?? ''),
                    (string) ($tag['value'] ?? ''),
                    (string) ($tag['name'] ?? ''),
                    (string) ($tag['label'] ?? ''),
                ]);
            }

            return [(string) $tag];
        })->map(fn ($v) => strtolower(trim((string) $v)))->filter()->unique()->values()->all();

        $id = strtolower(trim($industryId));
        $name = strtolower(trim($industryName));

        return in_array($id, $values, true) || ($name !== '' && in_array($name, $values, true));
    }
}
