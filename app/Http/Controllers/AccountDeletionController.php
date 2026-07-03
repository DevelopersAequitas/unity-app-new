<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    /**
     * Show the account deletion request form.
     */
    public function show(): View
    {
        $user = Auth::guard('web')->user();

        return view('account-deletion.request', compact('user'));
    }

    /**
     * Submit the account deletion request.
     */
    public function submit(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        $rules = [
            'reason' => 'required|string|min:10|max:1000',
        ];

        // If user is not authenticated on web guard, require email to identify them
        if (! $user) {
            $rules['email'] = 'required|email|exists:users,email';
        }

        $request->validate($rules, [
            'email.exists' => 'We could not find an account with that email address.',
            'reason.min' => 'Please provide a more detailed reason (minimum 10 characters).',
        ]);

        if (! $user) {
            $user = User::where('email', $request->email)->firstOrFail();
        }

        // Check if there is already a pending deletion request for this user
        $exists = AccountDeletionRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'A deletion request is already pending for this account.');
        }

        AccountDeletionRequest::create([
            'user_id' => $user->id,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\AccountDeletionRequestedMail($user));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send account deletion requested email: ' . $e->getMessage());
        }

        return back()->with('success', 'Your request has been submitted. Our compliance team will review and process your deletion request.');
    }

    /**
     * Retrieve the account deletion request status for the authenticated user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $deletionRequest = AccountDeletionRequest::where('user_id', $user->id)->first();

        if (! $deletionRequest) {
            return response()->json(['message' => 'Account deletion request not found.'], 404);
        }

        return response()->json($deletionRequest);
    }
}
