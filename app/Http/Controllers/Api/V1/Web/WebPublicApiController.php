<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebCompany;
use App\Models\Web\WebMessage;
use App\Models\Web\WebOpportunity;
use App\Models\Web\WebPartnership;
use App\Models\Web\WebSetting;
use App\Models\Web\WebSuccessStory;
use App\Services\Web\WebSuccessStoryService;
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

    /**
     * Get active homepage success stories (YouTube video links + cover media).
     */
    public function successStories(Request $request): JsonResponse
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : null;

        $query = WebSuccessStory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $stories = $query->get();

        $mapped = $stories->map(function (WebSuccessStory $item): array {
            return [
                'id' => $item->id,
                'personName' => $item->person_name,
                'designation' => $item->designation,
                'company' => $item->company,
                'storyTitle' => $item->story_title,
                'quote' => $item->quote,
                'youtubeUrl' => $item->youtube_url,
                'youtubeVideoId' => $item->youtube_video_id,
                'youtubeEmbedUrl' => $item->youtube_embed_url,
                'coverImageUrl' => $item->cover_image_url,
                'hasCustomCover' => $item->has_custom_cover,
                'youtubeThumbnailUrl' => $item->youtube_thumbnail_url ?: WebSuccessStory::buildYouTubeThumbnailUrl($item->youtube_video_id),
                'sortOrder' => $item->sort_order,
                'isActive' => (bool) $item->is_active,
                'createdAt' => $item->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $mapped->count(),
            'data' => $mapped,
        ]);
    }

    /**
     * Upload / Create a new success story via API.
     */
    public function storeStory(Request $request, WebSuccessStoryService $service): JsonResponse
    {
        $validated = $request->validate([
            'person_name' => 'required|string|max:150',
            'designation' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:150',
            'story_title' => 'nullable|string|max:255',
            'quote' => 'nullable|string|max:3000',
            'youtube_url' => 'required|string|url',
            'custom_cover_image' => 'nullable|image|max:10240',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $coverFile = $request->file('custom_cover_image');
        $story = $service->create($validated, $coverFile);

        return response()->json([
            'success' => true,
            'message' => 'Success story created successfully',
            'data' => [
                'id' => $story->id,
                'personName' => $story->person_name,
                'designation' => $story->designation,
                'company' => $story->company,
                'storyTitle' => $story->story_title,
                'quote' => $story->quote,
                'youtubeUrl' => $story->youtube_url,
                'youtubeVideoId' => $story->youtube_video_id,
                'youtubeEmbedUrl' => $story->youtube_embed_url,
                'coverImageUrl' => $story->cover_image_url,
                'hasCustomCover' => $story->has_custom_cover,
                'youtubeThumbnailUrl' => $story->youtube_thumbnail_url ?: WebSuccessStory::buildYouTubeThumbnailUrl($story->youtube_video_id),
                'sortOrder' => $story->sort_order,
                'isActive' => (bool) $story->is_active,
            ],
        ], 201);
    }

    /**
     * Get single success story details.
     */
    public function showStory(string $id): JsonResponse
    {
        $story = WebSuccessStory::find($id);

        if (! $story) {
            return response()->json([
                'success' => false,
                'message' => 'Success story not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $story->id,
                'personName' => $story->person_name,
                'designation' => $story->designation,
                'company' => $story->company,
                'storyTitle' => $story->story_title,
                'quote' => $story->quote,
                'youtubeUrl' => $story->youtube_url,
                'youtubeVideoId' => $story->youtube_video_id,
                'youtubeEmbedUrl' => $story->youtube_embed_url,
                'coverImageUrl' => $story->cover_image_url,
                'hasCustomCover' => $story->has_custom_cover,
                'youtubeThumbnailUrl' => $story->youtube_thumbnail_url ?: WebSuccessStory::buildYouTubeThumbnailUrl($story->youtube_video_id),
                'sortOrder' => $story->sort_order,
                'isActive' => (bool) $story->is_active,
            ],
        ]);
    }

    /**
     * Update existing success story via API.
     */
    public function updateStory(Request $request, string $id, WebSuccessStoryService $service): JsonResponse
    {
        $story = $service->findById($id);

        if (! $story) {
            return response()->json([
                'success' => false,
                'message' => 'Success story not found',
            ], 404);
        }

        $validated = $request->validate([
            'person_name' => 'nullable|string|max:150',
            'designation' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:150',
            'story_title' => 'nullable|string|max:255',
            'quote' => 'nullable|string|max:3000',
            'youtube_url' => 'nullable|string|url',
            'custom_cover_image' => 'nullable|image|max:10240',
            'remove_custom_cover' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $coverFile = $request->file('custom_cover_image');
        $removeCover = $request->boolean('remove_custom_cover');

        $updated = $service->update($story, $validated, $coverFile, $removeCover);

        return response()->json([
            'success' => true,
            'message' => 'Success story updated successfully',
            'data' => [
                'id' => $updated->id,
                'personName' => $updated->person_name,
                'designation' => $updated->designation,
                'company' => $updated->company,
                'storyTitle' => $updated->story_title,
                'quote' => $updated->quote,
                'youtubeUrl' => $updated->youtube_url,
                'youtubeVideoId' => $updated->youtube_video_id,
                'youtubeEmbedUrl' => $updated->youtube_embed_url,
                'coverImageUrl' => $updated->cover_image_url,
                'hasCustomCover' => $updated->has_custom_cover,
                'youtubeThumbnailUrl' => $updated->youtube_thumbnail_url ?: WebSuccessStory::buildYouTubeThumbnailUrl($updated->youtube_video_id),
                'sortOrder' => $updated->sort_order,
                'isActive' => (bool) $updated->is_active,
            ],
        ]);
    }

    /**
     * Delete success story via API.
     */
    public function destroyStory(string $id, WebSuccessStoryService $service): JsonResponse
    {
        $story = $service->findById($id);

        if (! $story) {
            return response()->json([
                'success' => false,
                'message' => 'Success story not found',
            ], 404);
        }

        $service->delete($story);

        return response()->json([
            'success' => true,
            'message' => 'Success story deleted successfully',
        ]);
    }
}
