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
        $deletionRequest->update(['status' => 'approved']);

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
        $deletionRequest->update(['status' => $request->status]);

        return back()->with('success', 'Account deletion request status updated to '.ucfirst($request->status).'.');
    }
}
