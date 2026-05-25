<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ActivityCreative;
use App\Models\BusinessDeal;
use App\Models\CollaborationPost;
use App\Models\P2PMeetingRequest;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Requirement;
use App\Models\RequirementInterest;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\ActivityCreativeService;
use Illuminate\Http\Request;
use Throwable;

class ActivityCreativeController extends BaseApiController
{
    public function __construct(private readonly ActivityCreativeService $creativeService) {}

    public function download(Request $request, string $activityType, string $activityId)
    {
        try {
            $type = $this->creativeService->normalizeActivityType($activityType);
            if ($type === '' || $activityId === '') {
                return $this->error('Creative not found.', 404);
            }
            $creative = ActivityCreative::where('activity_type', $type)->where('activity_id', $activityId)->first();

            if (! $creative) {
                if ($type === 'p2p_meeting_request') {
                    return $this->error('Creative not found.', 404);
                }

                $model = $this->resolveActivityModel($type, $activityId);
                if (! $model) {
                    return $this->error('Creative not found.', 404);
                }
                $creative = $this->creativeService->createOrUpdateCreative($type, $activityId, (string) data_get($model, 'id', $activityId), $this->creativeService->buildCreativePayload($type, $model));
            }

            $creative->increment('downloaded_count');
            $creative->last_downloaded_at = now();
            $creative->save();

            $creativeUser = $this->resolveUser($type, $activityId, $creative?->user_id);

            $html = view('creatives.activity', [
                'activityType' => $type,
                'title' => $creative->creative_title,
                'creativeText' => $creative->creative_text,
                'userName' => $creativeUser?->display_name ?? 'Peer',
                'profilePhotoUrl' => $creativeUser?->profile_photo_url,
                'companyName' => $creativeUser?->company_name,
                'designation' => $creativeUser?->designation,
                'city' => $creativeUser?->city,
                'date' => $type === 'birthday_celebration' ? optional($creativeUser?->dob)->format('d M') : now()->format('d M Y'),
            ])->render();

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$type.'-'.$activityId.'-creative.html"']);
        } catch (Throwable $e) {
            return $this->error('Creative not found.', 404);
        }
    }

    private function resolveActivityModel(string $type, string $id): mixed
    {
        return match ($type) {
            'p2p_meeting_request' => P2PMeetingRequest::find($id),
            'p2p_meeting' => P2pMeeting::find($id),
            'referral' => Referral::find($id),
            'collaboration', 'collaboration_accept' => CollaborationPost::find($id),
            'requirement' => Requirement::find($id),
            'requirement_interest' => RequirementInterest::find($id) ?? RequirementInterest::where('requirement_id', $id)->latest('created_at')->first(),
            'business_deal' => BusinessDeal::find($id),
            'testimonial' => Testimonial::find($id),
            'birthday_celebration' => User::find($id),
            default => null,
        };
    }

    private function resolveUser(string $type, string $activityId, ?string $creativeUserId = null): ?User
    {
        if ($type === 'birthday_celebration') {
            return User::find($activityId);
        }

        return $creativeUserId ? User::find($creativeUserId) : ActivityCreative::query()->where('activity_type', $type)->where('activity_id', $activityId)->first()?->user;
    }
}
