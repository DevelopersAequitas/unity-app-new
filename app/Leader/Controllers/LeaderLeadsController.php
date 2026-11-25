<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Services\LeaderDashboardService;
use App\Leader\Services\LeaderPermissionService;
use App\Models\AdminUser;
use App\Models\BecomeMentorSubmission;
use App\Models\BecomeSpeakerSubmission;
use App\Models\EntrepreneurCertificationSubmission;
use App\Models\LeadershipCertificationSubmission;
use App\Models\PartnerWithUsSubmission;
use App\Models\SmeBusinessStorySubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeaderLeadsController extends Controller
{
    public function __construct(
        private readonly LeaderDashboardService $dashboardService,
        private readonly LeaderPermissionService $permissionService,
    ) {}

    /**
     * Return scoped lead submission counts for the authenticated leader.
     */
    public function counts(Request $request): JsonResponse
    {
        $scope = $this->resolveScopeOrFail($request);
        $scopedUserIds = $scope['scoped_member_user_ids'];
        $scopedEmails = $this->resolveScopedEmails($scopedUserIds);

        $counts = [];
        foreach ($this->leadConfigs() as $slug => $config) {
            $counts[$slug] = $this->scopedQuery($config, $scopedUserIds, $scopedEmails)->count();
        }

        return response()->json([
            'status' => true,
            'success' => true,
            'data' => $counts,
        ]);
    }

    /**
     * Return paginated, organization-scoped lead submissions by type.
     */
    public function index(Request $request, string $type): JsonResponse
    {
        $slug = str_replace('-', '_', strtolower(trim($type)));
        $configs = $this->leadConfigs();
        $config = $configs[$slug] ?? null;

        if (! $config) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => "Invalid lead type: {$type}",
            ], 404);
        }

        $scope = $this->resolveScopeOrFail($request);
        $scopedUserIds = $scope['scoped_member_user_ids'];
        $scopedEmails = $this->resolveScopedEmails($scopedUserIds);

        $query = $this->scopedQuery($config, $scopedUserIds, $scopedEmails)
            ->orderByDesc('created_at');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function (Builder $q) use ($config, $like): void {
                if (is_array($config['name_field'])) {
                    foreach ($config['name_field'] as $nf) {
                        $q->orWhere($nf, 'ILIKE', $like);
                    }
                } elseif (! empty($config['name_field'])) {
                    $q->orWhere($config['name_field'], 'ILIKE', $like);
                }
                if (! empty($config['business_field'])) {
                    $q->orWhere($config['business_field'], 'ILIKE', $like);
                }
                if (! empty($config['email_field'])) {
                    $q->orWhere($config['email_field'], 'ILIKE', $like);
                }
                if (! empty($config['phone_field'])) {
                    $q->orWhere($config['phone_field'], 'ILIKE', $like);
                }
            });
        }

        $status = trim((string) $request->query('status', 'all'));
        if ($status !== '' && $status !== 'all') {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }

        $fromDate = trim((string) $request->query('from_date', ''));
        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        $toDate = trim((string) $request->query('to_date', ''));
        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $paginator = $query->paginate($perPage);

        $statuses = $this->scopedQuery($config, $scopedUserIds, $scopedEmails)
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->filter()
            ->values();

        $items = collect($paginator->items())->map(function ($row) use ($config): array {
            $raw = $row->toArray();

            if (is_array($config['name_field'])) {
                $parts = array_map(fn (string $f): string => (string) ($raw[$f] ?? ''), $config['name_field']);
                $fullName = trim(implode(' ', array_filter($parts)));
            } else {
                $fullName = (string) ($raw[$config['name_field']] ?? '');
            }

            $businessName = ! empty($config['business_field']) ? (string) ($raw[$config['business_field']] ?? '') : '';
            $email = ! empty($config['email_field']) ? (string) ($raw[$config['email_field']] ?? '') : '';
            $phone = ! empty($config['phone_field']) ? (string) ($raw[$config['phone_field']] ?? '') : '';

            return array_merge($raw, [
                'full_name' => $fullName !== '' ? $fullName : ($raw['full_name'] ?? '—'),
                'business_name' => $businessName !== '' ? $businessName : ($raw['business_name'] ?? '—'),
                'email' => $email !== '' ? $email : ($raw['email'] ?? '—'),
                'contact_no' => $phone !== '' ? $phone : ($raw['contact_no'] ?? ($raw['phone'] ?? '—')),
                'formatted_created_at' => ! empty($row->created_at) ? Carbon::parse($row->created_at)->format('Y-m-d H:i') : '—',
            ]);
        });

        return response()->json([
            'status' => true,
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
            'statuses' => $statuses,
            'config' => [
                'title' => $config['title'],
                'columns' => $config['columns'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveScopeOrFail(Request $request): array
    {
        /** @var User|AdminUser|null $user */
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $role = (string) ($this->permissionService->resolveUserRole($user)['role'] ?? 'guest');
        if ($role === 'guest') {
            abort(403, 'Unauthorized. Leadership role required.');
        }

        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        return $this->dashboardService->resolveLeaderScope($circleId, $districtId, $user);
    }

    /**
     * @param  array<int, string>|null  $scopedUserIds
     * @return array<int, string>|null
     */
    private function resolveScopedEmails(?array $scopedUserIds): ?array
    {
        if ($scopedUserIds === null) {
            return null;
        }

        if ($scopedUserIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $scopedUserIds)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($email): string => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>|null  $scopedUserIds
     * @param  array<int, string>|null  $scopedEmails
     */
    private function scopedQuery(array $config, ?array $scopedUserIds, ?array $scopedEmails): Builder
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $instance = new $modelClass;
        $table = $instance->getTable();
        $query = $modelClass::query();

        if ($scopedUserIds === null) {
            return $query;
        }

        if ($scopedUserIds === [] && empty($scopedEmails)) {
            return $query->whereRaw('1 = 0');
        }

        $hasUserId = Schema::hasTable($table) && Schema::hasColumn($table, 'user_id');
        $emailField = $config['email_field'] ?? null;
        $hasEmailField = is_string($emailField) && $emailField !== '' && Schema::hasTable($table) && Schema::hasColumn($table, $emailField);

        if (! $hasUserId && ! $hasEmailField) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($hasUserId, $hasEmailField, $emailField, $scopedUserIds, $scopedEmails): void {
            if ($hasUserId && ! empty($scopedUserIds)) {
                $q->orWhereIn('user_id', $scopedUserIds);
            }
            if ($hasEmailField && ! empty($scopedEmails)) {
                $q->orWhereIn(DB::raw("LOWER(TRIM({$emailField}))"), $scopedEmails);
            }
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function leadConfigs(): array
    {
        return [
            'entrepreneur_certification' => [
                'model' => EntrepreneurCertificationSubmission::class,
                'name_field' => 'full_name',
                'business_field' => 'business_name',
                'email_field' => 'email',
                'phone_field' => 'contact_no',
                'title' => 'Entrepreneur Certification',
                'columns' => ['full_name', 'business_name', 'email', 'contact_no', 'status', 'notes', 'created_at'],
            ],
            'leadership_certification' => [
                'model' => LeadershipCertificationSubmission::class,
                'name_field' => 'full_name',
                'business_field' => 'business_name',
                'email_field' => 'email',
                'phone_field' => 'contact_no',
                'title' => 'Leadership Certification',
                'columns' => ['full_name', 'business_name', 'email', 'contact_no', 'status', 'notes', 'created_at'],
            ],
            'partner_with_us' => [
                'model' => PartnerWithUsSubmission::class,
                'name_field' => ['first_name', 'last_name'],
                'business_field' => 'brand_or_company_name',
                'email_field' => 'email_id',
                'phone_field' => 'mobile_number',
                'title' => 'Partner With Us',
                'columns' => ['first_name', 'last_name', 'email_id', 'mobile_number', 'city', 'brand_or_company_name', 'industry', 'status', 'notes', 'created_at'],
            ],
            'become_speaker' => [
                'model' => BecomeSpeakerSubmission::class,
                'name_field' => ['first_name', 'last_name'],
                'business_field' => 'company_name',
                'email_field' => 'email',
                'phone_field' => 'phone',
                'title' => 'Become Speaker',
                'columns' => ['first_name', 'last_name', 'email', 'phone', 'city', 'linkedin_profile_url', 'company_name', 'brief_bio', 'topics_to_speak_on', 'status', 'notes', 'created_at'],
            ],
            'become_mentor' => [
                'model' => BecomeMentorSubmission::class,
                'name_field' => ['first_name', 'last_name'],
                'business_field' => null,
                'email_field' => 'email',
                'phone_field' => 'phone',
                'title' => 'Become Mentor',
                'columns' => ['first_name', 'last_name', 'email', 'phone', 'city', 'linkedin_profile', 'status', 'notes', 'created_at'],
            ],
            'story_submissions' => [
                'model' => SmeBusinessStorySubmission::class,
                'name_field' => 'full_name',
                'business_field' => 'business_name',
                'email_field' => 'email',
                'phone_field' => 'contact_number',
                'title' => 'Story Submissions',
                'columns' => ['full_name', 'business_name', 'email', 'contact_number', 'status', 'notes', 'created_at'],
            ],
        ];
    }
}
