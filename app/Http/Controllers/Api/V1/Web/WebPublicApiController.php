<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebCompany;
use App\Models\Web\WebMessage;
use App\Models\Web\WebOpportunity;
use App\Models\Web\WebPartnership;
use App\Models\Web\WebSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebPublicApiController extends Controller
{
    /**
     * Get active partnerships for website.
     */
    public function partnerships(Request $request): JsonResponse
    {
        $partners = WebPartnership::where('status', 'active')
            ->orderBy('display_order')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $partners,
        ]);
    }

    /**
     * Get open investment opportunities.
     */
    public function opportunities(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $query = WebOpportunity::where('status', 'open');

        if (! empty($category)) {
            $query->where('category', $category);
        }

        $opportunities = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $opportunities,
        ]);
    }

    /**
     * Get verified companies ecosystem directory.
     */
    public function companies(Request $request): JsonResponse
    {
        $industry = $request->query('industry');
        $query = WebCompany::where('is_active', true);

        if (! empty($industry)) {
            $query->where('industry', $industry);
        }

        $companies = $query->orderBy('display_order')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

    /**
     * Get public website settings and SEO tags.
     */
    public function settings(): JsonResponse
    {
        $settings = WebSetting::all()->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Submit contact form inquiry from the website.
     */
    public function submitMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:50',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
            'source_page' => 'nullable|string|max:100',
        ]);

        $validated['status'] = 'unread';
        $validated['ip_address'] = $request->ip();

        $message = WebMessage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Your inquiry has been received. Our team will contact you shortly.',
            'data' => [
                'id' => $message->id,
            ],
        ], 201);
    }

    /**
     * Get collaboration stories from real business deals or collaborations table.
     */
    public function collaborations(): JsonResponse
    {
        try {
            $deals = DB::table('business_deals')
                ->select(
                    'business_deals.id',
                    'business_deals.deal_amount',
                    'business_deals.business_type',
                    'business_deals.comment',
                    'business_deals.deal_date',
                    'business_deals.created_at',
                    'u1.display_name as u1_display',
                    'u1.first_name as u1_first',
                    'u1.last_name as u1_last',
                    'u1.company_name as u1_company',
                    'u1.city as u1_city',
                    'u1.profile_photo_url as u1_photo',
                    'u2.display_name as u2_display',
                    'u2.first_name as u2_first',
                    'u2.last_name as u2_last',
                    'u2.company_name as u2_company',
                    'u2.city as u2_city',
                    'u2.profile_photo_url as u2_photo'
                )
                ->leftJoin('users as u1', 'business_deals.from_user_id', '=', 'u1.id')
                ->leftJoin('users as u2', 'business_deals.to_user_id', '=', 'u2.id')
                ->where(function ($q) {
                    $q->where('business_deals.is_deleted', false)
                        ->orWhereNull('business_deals.is_deleted');
                })
                ->orderBy('business_deals.deal_date', 'desc')
                ->limit(10)
                ->get();

            if ($deals->isNotEmpty()) {
                return response()->json([
                    'success' => true,
                    'source' => 'database_business_deals',
                    'data' => $deals,
                ]);
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    }
}
