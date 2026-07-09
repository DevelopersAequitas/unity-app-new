<?php

namespace App\Http\Controllers\Api;

use App\Models\ContactPost;
use App\Models\UserContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserContactsController extends BaseApiController
{
    public function permission(Request $request): JsonResponse
    {
        $user = $request->user();

        // A user has synced contacts if there are records under their user_id in contact_posts or user_contacts
        $hasSynced = ContactPost::where('user_id', $user->id)->exists();

        // Prevent query failure on PostgreSQL due to type mismatch (UUID string vs bigint)
        if (is_numeric($user->id)) {
            $hasSynced = $hasSynced || UserContact::where('user_id', (int) $user->id)->exists();
        }

        // Return allowed as true only if they have not synced yet
        $allowed = !$hasSynced;

        return response()->json([
            'success' => true,
            'message' => 'User contacts permission fetched successfully.',
            'data' => [
                'user_id' => $user->id,
                'contacts_allowed' => $allowed,
                'android_contacts_permission' => $allowed ? 'yes' : 'no',
                'ios_contacts_permission' => $allowed ? 'yes' : 'no',
            ],
        ]);
    }
}

