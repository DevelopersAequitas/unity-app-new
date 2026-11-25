<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebPartnershipProposal;
use App\Models\Web\WebPublicInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InquiryAdminController extends Controller
{
    /**
     * Get summary KPI statistics across all inquiries & proposals
     */
    public function getStats(): JsonResponse
    {
        $totalPartnerships = WebPartnershipProposal::count();
        $newPartnerships = WebPartnershipProposal::where('status', 'new')->count();
        $underReviewPartnerships = WebPartnershipProposal::whereIn('status', ['under_review', 'discussion', 'in_review'])->count();
        $convertedPartnerships = WebPartnershipProposal::whereIn('status', ['converted', 'approved', 'closed'])->count();

        $totalInquiries = WebPublicInquiry::count();
        $newInquiries = WebPublicInquiry::where('status', 'new')->count();
        $inquiriesByType = WebPublicInquiry::selectRaw('source_type, count(*) as count')
            ->groupBy('source_type')
            ->pluck('count', 'source_type');

        return response()->json([
            'success' => true,
            'stats' => [
                'partnerships' => [
                    'total' => $totalPartnerships,
                    'new' => $newPartnerships,
                    'under_review' => $underReviewPartnerships,
                    'converted' => $convertedPartnerships,
                ],
                'inquiries' => [
                    'total' => $totalInquiries,
                    'new' => $newInquiries,
                    'by_type' => $inquiriesByType,
                ],
                'overall_total' => $totalPartnerships + $totalInquiries,
                'overall_new' => $newPartnerships + $newInquiries,
            ],
        ]);
    }

    /**
     * List partnership proposals
     */
    public function getPartnerships(Request $request): JsonResponse
    {
        $query = WebPartnershipProposal::query()->orderBy('created_at', 'desc');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('organisation', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('proposal_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($relationshipType = $request->query('relationship_type')) {
            if ($relationshipType !== 'all') {
                $query->where('relationship_type', $relationshipType);
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Get single partnership proposal details
     */
    public function getPartnershipDetails(string $id): JsonResponse
    {
        $proposal = WebPartnershipProposal::find($id);

        if (! $proposal) {
            return response()->json([
                'success' => false,
                'message' => 'Partnership proposal not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'proposal' => $proposal,
        ]);
    }

    /**
     * Update status or admin notes on partnership proposal
     */
    public function updatePartnership(Request $request, string $id): JsonResponse
    {
        $proposal = WebPartnershipProposal::find($id);

        if (! $proposal) {
            return response()->json([
                'success' => false,
                'message' => 'Partnership proposal not found',
            ], 404);
        }

        $fields = [];
        if ($request->has('status')) {
            $fields['status'] = $request->input('status');
        }
        if ($request->has('admin_notes')) {
            $fields['admin_notes'] = $request->input('admin_notes');
        }

        $fields['reviewed_by'] = $request->user()?->name ?? 'Admin User';
        $fields['reviewed_at'] = Carbon::now();

        $proposal->update($fields);

        return response()->json([
            'success' => true,
            'message' => 'Partnership proposal updated successfully',
            'proposal' => $proposal->fresh(),
        ]);
    }

    /**
     * Delete partnership proposal
     */
    public function deletePartnership(string $id): JsonResponse
    {
        $proposal = WebPartnershipProposal::find($id);

        if (! $proposal) {
            return response()->json([
                'success' => false,
                'message' => 'Partnership proposal not found',
            ], 404);
        }

        $proposal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Partnership proposal removed successfully',
        ]);
    }

    /**
     * List public inquiries by type (general, city_request, speaker, investor, other)
     */
    public function getInquiries(Request $request): JsonResponse
    {
        $query = WebPublicInquiry::query()->orderBy('created_at', 'desc');

        if ($sourceType = $request->query('source_type')) {
            if ($sourceType !== 'all') {
                $query->where('source_type', $sourceType);
            }
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('organisation', 'like', "%{$search}%")
                    ->orWhere('inquiry_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Get single inquiry details
     */
    public function getInquiryDetails(string $id): JsonResponse
    {
        $inquiry = WebPublicInquiry::find($id);

        if (! $inquiry) {
            return response()->json([
                'success' => false,
                'message' => 'Inquiry not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'inquiry' => $inquiry,
        ]);
    }

    /**
     * Update status or admin notes on inquiry
     */
    public function updateInquiry(Request $request, string $id): JsonResponse
    {
        $inquiry = WebPublicInquiry::find($id);

        if (! $inquiry) {
            return response()->json([
                'success' => false,
                'message' => 'Inquiry not found',
            ], 404);
        }

        $fields = [];
        if ($request->has('status')) {
            $fields['status'] = $request->input('status');
        }
        if ($request->has('admin_notes')) {
            $fields['admin_notes'] = $request->input('admin_notes');
        }

        $fields['reviewed_by'] = $request->user()?->name ?? 'Admin User';
        $fields['reviewed_at'] = Carbon::now();

        $inquiry->update($fields);

        return response()->json([
            'success' => true,
            'message' => 'Inquiry updated successfully',
            'inquiry' => $inquiry->fresh(),
        ]);
    }

    /**
     * Delete inquiry
     */
    public function deleteInquiry(string $id): JsonResponse
    {
        $inquiry = WebPublicInquiry::find($id);

        if (! $inquiry) {
            return response()->json([
                'success' => false,
                'message' => 'Inquiry not found',
            ], 404);
        }

        $inquiry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inquiry removed successfully',
        ]);
    }
}
