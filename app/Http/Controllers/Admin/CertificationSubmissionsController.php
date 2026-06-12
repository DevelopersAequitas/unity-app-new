<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificationSubmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CertificationSubmissionsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'approved', 'rejected'])],
            'type' => ['nullable', Rule::in(['leadership', 'entrepreneur'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = CertificationSubmission::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('certification_type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                foreach (['full_name', 'business_name', 'email', 'contact_no'] as $column) {
                    $q->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . strtolower($search) . '%']);
                }
            });
        }

        $items = $query->latest()->paginate(15)->withQueryString();

        return view('admin.certifications.index', [
            'items' => $items,
            'filters' => $filters,
        ]);
    }

    public function approve(Request $request, string $id)
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
        ]);

        $submission = CertificationSubmission::findOrFail($id);

        if ($submission->status === CertificationSubmission::STATUS_APPROVED) {
            return back()->with('success', 'Certification submission is already approved.');
        }

        $submission->forceFill([
            'status' => CertificationSubmission::STATUS_APPROVED,
            'admin_note' => $data['admin_note'] ?? null,
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
        ])->save();

        return back()->with('success', 'Certification submission approved successfully.');
    }

    public function reject(Request $request, string $id)
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string'],
        ]);

        $submission = CertificationSubmission::findOrFail($id);

        $submission->forceFill([
            'status' => CertificationSubmission::STATUS_REJECTED,
            'admin_note' => $data['admin_note'],
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => auth('admin')->id(),
            'rejected_at' => now(),
        ])->save();

        return back()->with('success', 'Certification submission rejected successfully.');
    }
}
