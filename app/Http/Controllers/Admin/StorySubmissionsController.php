<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StoryApprovedMail;
use App\Mail\StoryRejectedMail;
use App\Models\SmeBusinessStorySubmission;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorySubmissionsController extends Controller
{
    public function __construct(
        private readonly NotifyUserService $notifyUserService,
        private readonly EmailLogService $emailLogService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'export' => ['nullable', 'string'],
        ]);

        $query = SmeBusinessStorySubmission::query()->with('user');

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('story', 'ILIKE', "%{$search}%")
                    ->orWhere('full_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('business_name', 'ILIKE', "%{$search}%");
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        // Handle Streamed CSV Export
        if ($request->has('export')) {
            return $this->exportCsv($query);
        }

        $items = $query->latest('created_at')->paginate(15)->withQueryString();

        $statuses = SmeBusinessStorySubmission::query()
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter(fn ($value) => $value !== '')
            ->values();

        return view('admin.stories.index', [
            'items' => $items,
            'filters' => $filters,
            'statuses' => $statuses,
        ]);
    }

    public function approve(Request $request, string $id)
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
        ]);

        $story = SmeBusinessStorySubmission::findOrFail($id);

        if ($story->status === 'approved') {
            return back()->with('success', 'Story is already approved.');
        }

        $adminId = Auth::guard('admin')->id();

        $story->forceFill([
            'status' => 'approved',
            'approved_by' => $adminId,
            'approved_at' => now(),
            'notes' => $data['admin_note'] ?? $story->notes,
            'rejected_reason' => null,
        ])->save();

        // Notify user if linked
        if ($story->user_id) {
            $user = User::find($story->user_id);
            if ($user) {
                $adminUser = User::query()->whereHas('roles', fn ($q) => $q->where('key', 'global_admin'))->first() ?? $user;

                // Push & DB
                $this->notifyUserService->notifyUser(
                    $user,
                    $adminUser,
                    'story_approved',
                    [
                        'title' => 'Story Submission Approved',
                        'body' => "Your story '{$story->title}' has been approved.",
                        'story_submission_id' => (string) $story->id,
                    ],
                    $story
                );

                // Email
                try {
                    if ($user->email) {
                        $mailable = new StoryApprovedMail($story);
                        Mail::to($user->email)->send($mailable);

                        $this->emailLogService->logMailableSent($mailable, [
                            'user_id' => $user->id,
                            'to_email' => $user->email,
                            'to_name' => $user->display_name,
                            'template_key' => 'story_approved',
                            'source_module' => 'StorySubmissions',
                            'related_type' => SmeBusinessStorySubmission::class,
                            'related_id' => (string) $story->id,
                            'payload' => ['title' => $story->title],
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send approval mail to user', ['error' => $e->getMessage()]);
                }
            }
        }

        return back()->with('success', 'Story submission approved successfully.');
    }

    public function reject(Request $request, string $id)
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string'],
        ]);

        $story = SmeBusinessStorySubmission::findOrFail($id);

        $story->forceFill([
            'status' => 'rejected',
            'notes' => $data['admin_note'],
            'rejected_reason' => $data['admin_note'],
            'approved_by' => null,
            'approved_at' => null,
        ])->save();

        // Notify user if linked
        if ($story->user_id) {
            $user = User::find($story->user_id);
            if ($user) {
                $adminUser = User::query()->whereHas('roles', fn ($q) => $q->where('key', 'global_admin'))->first() ?? $user;

                // Push & DB
                $this->notifyUserService->notifyUser(
                    $user,
                    $adminUser,
                    'story_rejected',
                    [
                        'title' => 'Story Submission Rejected',
                        'body' => "Your story '{$story->title}' has been rejected.",
                        'story_submission_id' => (string) $story->id,
                        'reason' => $data['admin_note'],
                    ],
                    $story
                );

                // Email
                try {
                    if ($user->email) {
                        $mailable = new StoryRejectedMail($story);
                        Mail::to($user->email)->send($mailable);

                        $this->emailLogService->logMailableSent($mailable, [
                            'user_id' => $user->id,
                            'to_email' => $user->email,
                            'to_name' => $user->display_name,
                            'template_key' => 'story_rejected',
                            'source_module' => 'StorySubmissions',
                            'related_type' => SmeBusinessStorySubmission::class,
                            'related_id' => (string) $story->id,
                            'payload' => ['title' => $story->title, 'reason' => $data['admin_note']],
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send rejection mail to user', ['error' => $e->getMessage()]);
                }
            }
        }

        return back()->with('success', 'Story submission rejected successfully.');
    }

    private function exportCsv($query): StreamedResponse
    {
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=story_submissions_'.date('Ymd_His').'.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // CSV Header
            fputcsv($handle, [
                'ID',
                'User ID',
                'Author Name',
                'Author Email',
                'Title',
                'Story Content',
                'Status',
                'Submitted At',
                'Approved At',
                'Rejection Reason/Notes',
            ]);

            $query->chunk(100, function ($stories) use ($handle) {
                foreach ($stories as $story) {
                    fputcsv($handle, [
                        $story->id,
                        $story->user_id ?: 'Guest',
                        $story->user ? $story->user->display_name : $story->full_name,
                        $story->user ? $story->user->email : $story->email,
                        $story->title ?: $story->business_name,
                        $story->story ?: $story->company_introduction,
                        $story->status,
                        $story->submitted_at ? $story->submitted_at->toDateTimeString() : $story->created_at->toDateTimeString(),
                        $story->approved_at ? $story->approved_at->toDateTimeString() : '',
                        $story->rejected_reason ?: $story->notes,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
