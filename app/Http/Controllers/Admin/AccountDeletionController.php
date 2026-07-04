<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    /**
     * Display a listing of account deletion requests.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');

        $query = AccountDeletionRequest::with('user')
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->paginate(15);

        return view('admin.account-deletion.index', compact('requests', 'status'));
    }

    /**
     * Approve the deletion request.
     */
    public function approve(string $id): RedirectResponse
    {
        $deletionRequest = AccountDeletionRequest::findOrFail($id);
        $oldStatus = $deletionRequest->status;
        $deletionRequest->update(['status' => 'approved']);

        if (! in_array($oldStatus, ['completed', 'approved'], true)) {
            if ($deletionRequest->user) {
                $user = $deletionRequest->user;
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\AccountDeletedMail($user));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send account deleted email in approve: '.$e->getMessage());
                }
                $user->delete();
            }
        }

        return back()->with('success', 'Account deletion request approved.');
    }

    /**
     * Reject the deletion request.
     */
    public function reject(string $id): RedirectResponse
    {
        $deletionRequest = AccountDeletionRequest::findOrFail($id);
        $deletionRequest->update(['status' => 'rejected']);

        return back()->with('success', 'Account deletion request rejected.');
    }

    /**
     * Update the status of the deletion request.
     */
    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,ongoing,completed,approved,rejected',
        ]);

        $deletionRequest = AccountDeletionRequest::findOrFail($id);
        $oldStatus = $deletionRequest->status;
        $deletionRequest->update(['status' => $request->status]);

        if (in_array($request->status, ['completed', 'approved'], true) && ! in_array($oldStatus, ['completed', 'approved'], true)) {
            if ($deletionRequest->user) {
                $user = $deletionRequest->user;
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\AccountDeletedMail($user));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send account deleted email in updateStatus: '.$e->getMessage());
                }
                $user->delete();
            }
        }

        return back()->with('success', 'Account deletion request status updated to '.ucfirst($request->status).'.');
    }

    /**
     * Display the email management dashboard.
     */
    public function emails(Request $request): View
    {
        $requests = AccountDeletionRequest::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.account-deletion.emails-dashboard', compact('requests'));
    }

    /**
     * Preview the email template.
     */
    public function preview(string $template, Request $request)
    {
        $user = null;
        $requestId = $request->query('request_id');

        if ($requestId) {
            $deletionRequest = AccountDeletionRequest::with('user')->find($requestId);
            if ($deletionRequest && $deletionRequest->user) {
                $user = $deletionRequest->user;
            }
        }

        if (! $user) {
            // Mock dummy user for preview if no request is chosen
            $user = new \App\Models\User;
            $user->display_name = 'John Doe';
            $user->first_name = 'John';
            $user->last_name = 'Doe';
            $user->email = 'johndoe@example.com';
            $user->id = 'dummy-id-1234';
        }

        if ($template === 'requested') {
            return new \App\Mail\AccountDeletionRequestedMail($user);
        } elseif ($template === 'deleted') {
            return new \App\Mail\AccountDeletedMail($user);
        }

        abort(404, 'Template not found');
    }

    /**
     * Manually send the email template.
     */
    public function send(string $template, Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid|exists:account_deletion_requests,id',
        ]);

        $deletionRequest = AccountDeletionRequest::with('user')->findOrFail($request->request_id);
        $user = $deletionRequest->user;

        if (! $user) {
            $user = \App\Models\User::withTrashed()->find($deletionRequest->user_id);
        }

        if (! $user || ! $user->email) {
            return back()->with('error', 'Unable to send email: Associated user or email address not found.');
        }

        try {
            if ($template === 'requested') {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\AccountDeletionRequestedMail($user));
            } elseif ($template === 'deleted') {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\AccountDeletedMail($user));
            } else {
                return back()->with('error', 'Invalid email template requested.');
            }

            // Save log in session
            $log = session()->get('manual_email_logs', []);
            $log[] = [
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'template' => $template === 'requested' ? 'Request Submitted' : 'Account Deleted',
                'recipient' => $user->email,
                'status' => 'success',
            ];
            session()->put('manual_email_logs', $log);

            return back()->with('success', 'Email ('.($template === 'requested' ? 'Request Submitted' : 'Account Deleted').') successfully sent to '.$user->email);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Manual email send failed: '.$e->getMessage());

            $log = session()->get('manual_email_logs', []);
            $log[] = [
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'template' => $template === 'requested' ? 'Request Submitted' : 'Account Deleted',
                'recipient' => $user->email ?? 'unknown',
                'status' => 'failed ('.$e->getMessage().')',
            ];
            session()->put('manual_email_logs', $log);

            return back()->with('error', 'Failed to send email: '.$e->getMessage());
        }
    }

    /**
     * Clear manual email logs from session.
     */
    public function clearLogs(): RedirectResponse
    {
        session()->forget('manual_email_logs');

        return back()->with('success', 'Manual trigger logs cleared.');
    }
}
