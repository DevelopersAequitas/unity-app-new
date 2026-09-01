<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextSwitcherController extends Controller
{
    public function switchContext(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'circle_id' => ['required', 'string'],
        ]);

        $circleId = $validated['circle_id'];
        $admin = auth('admin')->user();

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Validate that this circle_id is in their allowed window (or "All")
        if ($circleId !== 'All') {
            $allowed = AdminAccess::allowedCircleIds($admin);
            if (! in_array($circleId, $allowed, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized circle context.',
                ], 403);
            }
        }

        session(['activeScopeId' => $circleId]);
        AdminCircleScope::resetCache();

        return response()->json([
            'success' => true,
            'message' => 'Context switched to '.$circleId,
        ]);
    }
}
