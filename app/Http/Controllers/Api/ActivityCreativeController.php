<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityCreative;
use App\Models\BusinessDeal;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class ActivityCreativeController extends BaseApiController
{
    public function download(Request $request, string $activityType, string $activityId)
    {
        $user = $request->user();

        try {
            [$normalizedType, $recordType] = $this->normalizeType($activityType);

            if (! $normalizedType) {
                return $this->error('Invalid activity type', 422);
            }

            $activity = $this->findActivity($normalizedType, $activityId, (string) $user->id);

            if (! $activity) {
                return $this->error('Activity not found', 404);
            }

            $creative = ActivityCreative::firstOrCreate(
                [
                    'activity_type' => $recordType,
                    'activity_id' => $activityId,
                    'user_id' => (string) $user->id,
                ],
                $this->buildCreativePayload($normalizedType, $activity, $user)
            );

            $creative->increment('downloaded_count');
            $creative->last_downloaded_at = now();
            $creative->save();

            $html = view('creatives.activity', [
                'brand' => 'Peers Global Unity',
                'activityTypeTitle' => $creative->creative_title,
                'creativeText' => $creative->creative_text,
                'activityDate' => $this->activityDate($activity),
                'userName' => $this->displayName($user),
                'userCompany' => $this->companyName($user),
            ])->render();

            $filename = $recordType . '-' . $activityId . '-creative.html';

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Throwable $e) {
            return $this->error('Unable to download creative right now', 500);
        }
    }

    private function normalizeType(string $type): array
    {
        $type = str_replace('-', '_', strtolower($type));

        return match ($type) {
            'business_deal', 'business_deals' => ['business_deal', 'business_deal'],
            'testimonial', 'testimonials' => ['testimonial', 'testimonial'],
            default => [null, null],
        };
    }

    private function findActivity(string $type, string $activityId, string $userId): BusinessDeal|Testimonial|null
    {
        if ($type === 'business_deal') {
            return BusinessDeal::where('id', $activityId)
                ->where('is_deleted', false)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($userId) {
                    $q->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
                })
                ->first();
        }

        return Testimonial::where('id', $activityId)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
            })
            ->first();
    }

    public static function buildCreativePayload(string $type, BusinessDeal|Testimonial $activity, User $creator): array
    {
        return $type === 'business_deal'
            ? self::businessDealPayload($activity, $creator)
            : self::testimonialPayload($activity, $creator);
    }

    private static function businessDealPayload(BusinessDeal $deal, User $creator): array
    {
        $peer = User::find($deal->to_user_id);
        $peerDetails = trim((string) ($peer?->display_name ?: $peer?->name ?: ''));
        $peerCompany = trim((string) ($peer?->company_name ?: ''));

        return [
            'creative_title' => 'Business Deal Completed',
            'creative_text' => trim('Amount: ' . ($deal->deal_amount ?? 'N/A')
                . '\nPeer: ' . ($peerDetails !== '' ? $peerDetails : 'N/A')
                . ($peerCompany !== '' ? ' (' . $peerCompany . ')' : '')
                . '\nComment: ' . ($deal->comment ?? 'N/A')),
            'creative_image_path' => null,
            'creative_image_url' => null,
        ];
    }

    private static function testimonialPayload(Testimonial $testimonial, User $creator): array
    {
        $receiver = User::find($testimonial->to_user_id);
        $receiverDetails = trim((string) ($receiver?->display_name ?: $receiver?->name ?: ''));
        $receiverCompany = trim((string) ($receiver?->company_name ?: ''));

        return [
            'creative_title' => 'Testimonial Shared',
            'creative_text' => trim('Message: ' . ($testimonial->content ?? 'N/A')
                . '\nFor: ' . ($receiverDetails !== '' ? $receiverDetails : 'N/A')
                . ($receiverCompany !== '' ? ' (' . $receiverCompany . ')' : '')),
            'creative_image_path' => null,
            'creative_image_url' => null,
        ];
    }

    private function displayName(User $user): string
    {
        return trim((string) ($user->display_name ?: $user->name ?: $user->first_name . ' ' . $user->last_name)) ?: 'Peer';
    }

    private function companyName(User $user): string
    {
        return trim((string) ($user->company_name ?? ''));
    }

    private function activityDate(BusinessDeal|Testimonial $activity): string
    {
        $date = $activity instanceof BusinessDeal ? $activity->deal_date : $activity->created_at;

        return Carbon::parse($date)->format('d M Y');
    }
}
