<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppTutorialPopupsRequest;
use App\Services\AppTutorialPopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppTutorialPopupsController extends Controller
{
    public function __construct(
        private readonly AppTutorialPopupService $tutorialPopupService
    ) {}

    /**
     * Display the App Tutorial Popups management page.
     */
    public function index(Request $request): View
    {
        $masterEnabled = $this->tutorialPopupService->getMasterToggle();
        $steps = $this->tutorialPopupService->getStepsWithMeta();
        $enabledCount = count(array_filter($steps, fn (array $s): bool => $s['is_enabled']));
        $disabledCount = count($steps) - $enabledCount;

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
    public function update(UpdateAppTutorialPopupsRequest $request): RedirectResponse
    {
        $masterEnabled = $request->boolean('master_tutorials_enabled');
        $steps = (array) $request->input('steps', []);

        $this->tutorialPopupService->saveSettings($masterEnabled, $steps);

        return redirect()
            ->route('admin.app-tutorial-popups.index')
            ->with('success', 'App Tutorial Popups configuration updated successfully.');
    }
}

