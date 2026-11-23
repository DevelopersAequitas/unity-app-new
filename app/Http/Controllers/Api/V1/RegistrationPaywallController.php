<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RegistrationPaywallController extends Controller
{
    /**
     * Check if user should be presented with the first-registration paywall.
     */
    public function getStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $hasSeen = false;
        if (Schema::hasColumn('users', 'has_seen_first_registration_paywall')) {
            $hasSeen = (bool) $user->has_seen_first_registration_paywall;
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => [
                'user_id' => (string) $user->id,
                'has_seen_first_registration_paywall' => $hasSeen,
                'has_presented_paywall' => $hasSeen,
                'should_show_paywall' => ! $hasSeen,
                'should_present_paywall' => ! $hasSeen,
                'is_first_registration' => ! $hasSeen,
            ],
        ], 200);
    }

    /**
     * Permanently record that user has been presented with the first-registration paywall.
     */
    public function markPresented(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (Schema::hasColumn('users', 'has_seen_first_registration_paywall')) {
            $user->has_seen_first_registration_paywall = true;
        }
        if (Schema::hasColumn('users', 'first_registration_paywall_shown_at')) {
            $user->first_registration_paywall_shown_at = now();
        }

        $user->save();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'First-registration paywall presentation status recorded.',
            'data' => [
                'has_seen_first_registration_paywall' => true,
                'has_presented_paywall' => true,
                'should_show_paywall' => false,
                'shown_at' => now()->toIso8601String(),
            ],
        ], 200);
    }
}
