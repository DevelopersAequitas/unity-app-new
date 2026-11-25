<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\ReportPostRequest;
use App\Leader\Services\LeaderNotificationDispatcherService;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\PostReportReason;
use Illuminate\Http\JsonResponse;

class PostReportController extends BaseApiController
{
    public function store(ReportPostRequest $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if ($post->is_deleted || $post->deleted_at) {
            return $this->error('Post not available', 404);
        }

        if ($post->user_id === $user->id) {
            return $this->error('You cannot report your own post', 422);
        }

        $existingReport = PostReport::query()
            ->where('post_id', $post->id)
            ->where('reporter_user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();

        if ($existingReport) {
            return $this->success([
                'id' => $existingReport->id,
                'status' => $existingReport->status,
            ], 'Already reported');
        }

        $data = $request->validated();
        $reason = PostReportReason::query()->find($data['reason_id']);

        $report = PostReport::create([
            'post_id' => $post->id,
            'reporter_user_id' => $user->id,
            'reason_id' => $data['reason_id'],
            'reason' => $reason?->title,
            'status' => 'open',
        ]);

        try {
            app(LeaderNotificationDispatcherService::class)->dispatchEvent(
                'post_reported',
                'Post Reported for Moderation',
                'A peer post was reported ('.(string) ($reason?->title ?? 'Policy review').') and requires moderation.',
                [
                    'circle_id' => (string) ($post->circle_id ?? ''),
                    'actor_id' => (string) $user->id,
                    'entity_type' => 'peer',
                    'entity_id' => (string) $post->user_id,
                    'reference_id' => (string) $report->id,
                    'idempotency_window' => (string) $report->id,
                ]
            );
        } catch (\Throwable) {
            // Non-blocking leadership notification
        }

        try {
            app(\App\Leader\Services\LeaderNotificationDispatcherService::class)->dispatchEvent(
                'post_reported',
                'Post Reported for Moderation',
                'A peer post was reported (' . (string) ($reason?->title ?? 'Policy review') . ') and requires moderation.',
                [
                    'circle_id' => (string) ($post->circle_id ?? ''),
                    'actor_id' => (string) $user->id,
                    'entity_type' => 'peer',
                    'entity_id' => (string) $post->user_id,
                    'reference_id' => (string) $report->id,
                    'idempotency_window' => (string) $report->id,
                ]
            );
        } catch (\Throwable) {
            // Non-blocking leadership notification
        }

        return $this->success([
            'id' => $report->id,
            'status' => $report->status,
        ], 'Post reported');
    }
}
