<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Forms\SubmitStoryRequest;
use App\Mail\StorySubmittedMail;
use App\Models\Role;
use App\Models\SmeBusinessStorySubmission;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Media\FileUploadService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StorySubmissionApiController extends BaseApiController
{
    public function __construct(
        private readonly FileUploadService $fileUploadService,
        private readonly NotifyUserService $notifyUserService,
        private readonly EmailLogService $emailLogService
    ) {}

    public function submit(SubmitStoryRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        Log::info('Authenticated user story submission started', [
            'user_id' => $user->id,
            'title' => $request->input('title'),
        ]);

        try {
            // Process cover image (could be UploadedFile or UUID string)
            $coverImageUuid = null;
            if ($request->hasFile('cover_image')) {
                $fileModel = $this->fileUploadService->store($request->file('cover_image'), $user);
                $coverImageUuid = $fileModel->id;
            } elseif ($request->filled('cover_image') && Str::isUuid($request->input('cover_image'))) {
                $coverImageUuid = $request->input('cover_image');
            }

            // Process attachments (could be UploadedFiles or UUID strings)
            $attachmentsUuids = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file instanceof UploadedFile) {
                        $fileModel = $this->fileUploadService->store($file, $user);
                        $attachmentsUuids[] = $fileModel->id;
                    }
                }
            }
            if ($request->filled('attachments') && is_array($request->input('attachments'))) {
                foreach ($request->input('attachments') as $attachment) {
                    if (is_string($attachment) && Str::isUuid($attachment)) {
                        $attachmentsUuids[] = $attachment;
                    }
                }
            }

            $fullName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $email = $user->email ?? '';
            $phone = $user->phone ?? '—';
            $businessName = $user->company_name ?? '—';

            $submission = SmeBusinessStorySubmission::create([
                'user_id' => $user->id,
                'title' => $request->input('title'),
                'story' => $request->input('story'),
                'short_description' => $request->input('short_description'),
                'cover_image' => $coverImageUuid,
                'attachments' => $attachmentsUuids,
                'status' => 'pending',
                'submitted_at' => now(),

                // Backwards compatibility with the legacy guest table schema
                'full_name' => $fullName,
                'email' => $email,
                'contact_number' => $phone,
                'business_name' => $businessName,
                'company_introduction' => $request->input('story'),
            ]);

            // Notify global admins
            $this->notifyAdmins($submission, $user);

            return $this->success($this->mapSubmission($submission), 'Story submitted successfully.', 201);
        } catch (\Throwable $exception) {
            Log::error('Authenticated user story submission failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->error($exception->getMessage(), 500);
        }
    }

    public function mySubmissions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $query = SmeBusinessStorySubmission::query()
            ->where('user_id', $user->id)
            ->latest('created_at');

        $items = $query->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json([
            'status' => true,
            'message' => 'Stories fetched successfully.',
            'data' => collect($items->items())->map(fn (SmeBusinessStorySubmission $item) => $this->mapSubmission($item))->values(),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $submission = SmeBusinessStorySubmission::find($id);

        if (! $submission) {
            return response()->json([
                'status' => false,
                'message' => 'Story not found.',
                'data' => null,
            ], 404);
        }

        // Authorization: Owner or Admin
        if ((string) $submission->user_id !== (string) $user->id && ! $this->canReview($user)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to view this story.',
                'data' => null,
            ], 403);
        }

        return $this->success($this->mapSubmission($submission));
    }

    private function notifyAdmins(SmeBusinessStorySubmission $submission, User $submittingUser): void
    {
        // 1. Fetch admins
        $roleIds = Role::query()->whereIn('key', ['global_admin'])->pluck('id');
        $admins = User::query()->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))->get();

        foreach ($admins as $admin) {
            // Push & DB notification
            $this->notifyUserService->notifyUser(
                $admin,
                $submittingUser,
                'story_submitted',
                [
                    'title' => 'New Story Submission',
                    'body' => "{$submittingUser->display_name} has submitted a new story: {$submission->title}",
                    'story_submission_id' => (string) $submission->id,
                ],
                $submission
            );

            // Send email
            try {
                if ($admin->email) {
                    $mailable = new StorySubmittedMail($submission);
                    Mail::to($admin->email)->send($mailable);

                    $this->emailLogService->logMailableSent($mailable, [
                        'user_id' => $admin->id,
                        'to_email' => $admin->email,
                        'to_name' => $admin->display_name,
                        'template_key' => 'story_submitted',
                        'source_module' => 'StorySubmissions',
                        'related_type' => SmeBusinessStorySubmission::class,
                        'related_id' => (string) $submission->id,
                        'payload' => ['title' => $submission->title],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send story submission mail to admin', [
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function canReview(?object $user = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user instanceof User) {
            $roleIds = Role::query()->whereIn('key', ['global_admin', 'industry_director', 'ded'])->pluck('id');

            return $user->roles()->whereIn('roles.id', $roleIds)->exists();
        }

        return false;
    }

    private function mapSubmission(SmeBusinessStorySubmission $item): array
    {
        return [
            'id' => (string) $item->id,
            'user_id' => $item->user_id,
            'title' => $item->title ?? $item->business_name,
            'story' => $item->story ?? $item->company_introduction,
            'short_description' => $item->short_description,
            'cover_image' => $item->cover_image,
            'cover_image_url' => $item->cover_image ? url('/api/v1/files/' . $item->cover_image) : null,
            'attachments' => $item->attachments ?: [],
            'attachment_urls' => collect($item->attachments ?: [])->map(fn ($id) => url('/api/v1/files/' . $id))->all(),
            'status' => $item->status,
            'notes' => $item->notes,
            'rejected_reason' => $item->rejected_reason,
            'submitted_at' => optional($item->submitted_at)->toISOString() ?? optional($item->created_at)->toISOString(),
            'approved_at' => optional($item->approved_at)->toISOString(),
            'approved_by' => $item->approved_by,
            'created_at' => optional($item->created_at)->toISOString(),
            'updated_at' => optional($item->updated_at)->toISOString(),
        ];
    }

    private function paginationMeta($items): array
    {
        return [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
        ];
    }
}
