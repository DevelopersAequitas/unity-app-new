<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppTutorialPopupsRequest;
use App\Services\AppTutorialPopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppTutorialPopupsController extends Controller
{
    public function __construct(
        private readonly AppTutorialPopupService $tutorialPopupService
    ) {}

    /**
     * Display the App Tutorial Popups management page or return JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        $masterEnabled = $this->tutorialPopupService->getMasterToggle();
        $steps = $this->tutorialPopupService->getStepsWithMeta();
        $enabledCount = count(array_filter($steps, fn (array $s): bool => (bool) ($s['is_enabled'] ?? false)));
        $disabledCount = count($steps) - $enabledCount;

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'master_tutorials_enabled' => $masterEnabled,
                    'steps' => $steps,
                    'enabled_count' => $enabledCount,
                    'disabled_count' => $disabledCount,
                    'total_count' => count($steps),
                ],
            ]);
        }

        return view('admin.app-tutorial-popups.index', [
            'masterEnabled' => $masterEnabled,
            'steps' => $steps,
            'enabledCount' => $enabledCount,
            'disabledCount' => $disabledCount,
            'totalCount' => count($steps),
        ]);
    }

    /**
     * Update master toggle and individual step toggles.
     */
    public function update(UpdateAppTutorialPopupsRequest $request): RedirectResponse|JsonResponse
    {
        $masterEnabled = $request->boolean('master_tutorials_enabled');
        $steps = (array) $request->input('steps', []);

        $this->tutorialPopupService->saveSettings($masterEnabled, $steps);

        if ($request->wantsJson() || $request->is('api/*')) {
            $refreshedSteps = $this->tutorialPopupService->getStepsWithMeta();
            $enabledCount = count(array_filter($refreshedSteps, fn (array $s): bool => (bool) ($s['is_enabled'] ?? false)));

            return response()->json([
                'success' => true,
                'message' => 'App Tutorial Popups configuration updated successfully.',
                'data' => [
                    'master_tutorials_enabled' => $masterEnabled,
                    'steps' => $refreshedSteps,
                    'enabled_count' => $enabledCount,
                    'disabled_count' => count($refreshedSteps) - $enabledCount,
                    'total_count' => count($refreshedSteps),
                ],
            ]);
        }

        return redirect()
            ->route('admin.app-tutorial-popups.index')
            ->with('success', 'App Tutorial Popups configuration updated successfully.');
    }
}
