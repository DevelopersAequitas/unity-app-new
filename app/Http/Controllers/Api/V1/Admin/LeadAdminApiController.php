<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BecomeMentorSubmission;
use App\Models\BecomeSpeakerSubmission;
use App\Models\CertificationSubmission;
use App\Models\EntrepreneurCertificationSubmission;
use App\Models\LeadershipCertificationSubmission;
use App\Models\PartnerWithUsSubmission;
use App\Models\SmeBusinessStorySubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadAdminApiController extends Controller
{
    protected function getModelConfig(string $type): ?array
    {
        $configs = [
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

        $slug = str_replace('-', '_', strtolower($type));
        return $configs[$slug] ?? null;
    }

    public function counts(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'entrepreneur_certification' => EntrepreneurCertificationSubmission::count(),
                'leadership_certification' => LeadershipCertificationSubmission::count(),
                'partner_with_us' => PartnerWithUsSubmission::count(),
                'become_speaker' => BecomeSpeakerSubmission::count(),
                'become_mentor' => BecomeMentorSubmission::count(),
                'story_submissions' => SmeBusinessStorySubmission::count(),
            ],
        ]);
    }

    public function index(Request $request, string $type): JsonResponse
    {
        $config = $this->getModelConfig($type);
        if (!$config) {
            return response()->json(['status' => false, 'message' => "Invalid lead type: {$type}"], 404);
        }

        $modelClass = $config['model'];
        $query = $modelClass::query()->orderByDesc('created_at');

        // Global search
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($config, $like) {
                if (is_array($config['name_field'])) {
                    foreach ($config['name_field'] as $nf) {
                        $q->orWhere($nf, 'ILIKE', $like);
                    }
                } elseif ($config['name_field']) {
                    $q->orWhere($config['name_field'], 'ILIKE', $like);
                }
                if (!empty($config['business_field'])) {
                    $q->orWhere($config['business_field'], 'ILIKE', $like);
                }
                if (!empty($config['email_field'])) {
                    $q->orWhere($config['email_field'], 'ILIKE', $like);
                }
                if (!empty($config['phone_field'])) {
                    $q->orWhere($config['phone_field'], 'ILIKE', $like);
                }
            });
        }

        // Status filter
        $status = trim((string) $request->query('status', 'all'));
        if ($status !== '' && $status !== 'all') {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }

        // Date range
        $fromDate = trim((string) $request->query('from_date', ''));
        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        $toDate = trim((string) $request->query('to_date', ''));
        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Column filters (matching screenshot)
        $filterName = trim((string) $request->query('name', ''));
        if ($filterName !== '') {
            $like = "%{$filterName}%";
            $query->where(function ($q) use ($config, $like) {
                if (is_array($config['name_field'])) {
                    foreach ($config['name_field'] as $nf) {
                        $q->orWhere($nf, 'ILIKE', $like);
                    }
                } elseif ($config['name_field']) {
                    $q->orWhere($config['name_field'], 'ILIKE', $like);
                }
            });
        }

        $filterCompany = trim((string) $request->query('company', ''));
        if ($filterCompany !== '' && !empty($config['business_field'])) {
            $query->where($config['business_field'], 'ILIKE', "%{$filterCompany}%");
        }

        $filterEmail = trim((string) $request->query('email', ''));
        if ($filterEmail !== '' && !empty($config['email_field'])) {
            $query->where($config['email_field'], 'ILIKE', "%{$filterEmail}%");
        }

        $filterPhone = trim((string) $request->query('phone', ''));
        if ($filterPhone !== '' && !empty($config['phone_field'])) {
            $query->where($config['phone_field'], 'ILIKE', "%{$filterPhone}%");
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $paginator = $query->paginate($perPage);

        // Distinct available statuses for dropdown
        $statuses = $modelClass::query()
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->filter()
            ->values();

        // Format items uniformly
        $items = collect($paginator->items())->map(function ($row) use ($config) {
            $raw = $row->toArray();

            $fullName = '';
            if (is_array($config['name_field'])) {
                $parts = array_map(fn($f) => $raw[$f] ?? '', $config['name_field']);
                $fullName = trim(implode(' ', array_filter($parts)));
            } else {
                $fullName = $raw[$config['name_field']] ?? '';
            }

            $businessName = !empty($config['business_field']) ? ($raw[$config['business_field']] ?? '') : '';
            $email = !empty($config['email_field']) ? ($raw[$config['email_field']] ?? '') : '';
            $phone = !empty($config['phone_field']) ? ($raw[$config['phone_field']] ?? '') : '';

            return array_merge($raw, [
                'full_name' => $fullName ?: ($raw['full_name'] ?? '—'),
                'business_name' => $businessName ?: ($raw['business_name'] ?? '—'),
                'email' => $email ?: ($raw['email'] ?? '—'),
                'contact_no' => $phone ?: ($raw['contact_no'] ?? ($raw['phone'] ?? '—')),
                'formatted_created_at' => !empty($row->created_at) ? Carbon::parse($row->created_at)->format('Y-m-d H:i') : '—',
            ]);
        });

        return response()->json([
            'status' => true,
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

    public function show(string $type, string $id): JsonResponse
    {
        $config = $this->getModelConfig($type);
        if (!$config) {
            return response()->json(['status' => false, 'message' => "Invalid lead type: {$type}"], 404);
        }

        $row = $config['model']::find($id);
        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Lead submission not found'], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $row,
            'config' => [
                'title' => $config['title'],
                'columns' => $config['columns'],
            ],
        ]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $config = $this->getModelConfig($type);
        if (!$config) {
            return response()->json(['status' => false, 'message' => "Invalid lead type: {$type}"], 404);
        }

        $modelClass = $config['model'];
        $data = $request->all();

        if (empty($data['id'])) {
            $data['id'] = (string) Str::uuid();
        }

        if (empty($data['status'])) {
            $data['status'] = 'new';
        }

        if (is_array($config['name_field'])) {
            if (!empty($data['full_name']) && empty($data['first_name'])) {
                $parts = explode(' ', trim($data['full_name']), 2);
                $data['first_name'] = $parts[0] ?? '';
                $data['last_name'] = $parts[1] ?? '';
            }
        }

        if (!empty($config['business_field']) && !empty($data['business_name'])) {
            $data[$config['business_field']] = $data['business_name'];
        }

        if (!empty($config['email_field']) && !empty($data['email'])) {
            $data[$config['email_field']] = $data['email'];
        }

        if (!empty($config['phone_field']) && (!empty($data['contact_no']) || !empty($data['phone']))) {
            $data[$config['phone_field']] = $data['contact_no'] ?? $data['phone'];
        }

        $created = $modelClass::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Lead created successfully.',
            'data' => $created,
        ], 201);
    }

    public function update(Request $request, string $type, string $id): JsonResponse
    {
        $config = $this->getModelConfig($type);
        if (!$config) {
            return response()->json(['status' => false, 'message' => "Invalid lead type: {$type}"], 404);
        }

        $row = $config['model']::find($id);
        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Lead submission not found'], 404);
        }

        $data = $request->all();
        unset($data['id']);

        if (is_array($config['name_field']) && !empty($data['full_name'])) {
            $parts = explode(' ', trim($data['full_name']), 2);
            $data['first_name'] = $parts[0] ?? '';
            $data['last_name'] = $parts[1] ?? '';
        }

        if (!empty($config['business_field']) && !empty($data['business_name'])) {
            $data[$config['business_field']] = $data['business_name'];
        }

        if (!empty($config['email_field']) && !empty($data['email'])) {
            $data[$config['email_field']] = $data['email'];
        }

        if (!empty($config['phone_field']) && (!empty($data['contact_no']) || !empty($data['phone']))) {
            $data[$config['phone_field']] = $data['contact_no'] ?? $data['phone'];
        }

        $row->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Lead updated successfully.',
            'data' => $row->fresh(),
        ]);
    }

    public function updateStatus(Request $request, string $type, string $id): JsonResponse
    {
        $config = $this->getModelConfig($type);
        if (!$config) {
            return response()->json(['status' => false, 'message' => "Invalid lead type: {$type}"], 404);
        }

        $row = $config['model']::find($id);
        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Lead submission not found'], 404);
        }

        $status = strtolower(trim((string) $request->input('status', 'approved')));
        $notes = $request->input('notes') ?? $request->input('admin_note');

        $updates = ['status' => $status];
        if ($notes !== null) {
            $updates['notes'] = $notes;
        }

        $row->update($updates);

        return response()->json([
            'status' => true,
            'message' => "Lead marked as {$status} successfully.",
            'data' => $row->fresh(),
        ]);
    }

    public function destroy(string $type, string $id): JsonResponse
    {
        $config = $this->getModelConfig($type);
        if (!$config) {
            return response()->json(['status' => false, 'message' => "Invalid lead type: {$type}"], 404);
        }

        $row = $config['model']::find($id);
        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Lead submission not found'], 404);
        }

        $row->delete();

        return response()->json([
            'status' => true,
            'message' => 'Lead submission deleted successfully.',
        ]);
    }
}
