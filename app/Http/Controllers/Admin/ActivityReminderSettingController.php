<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateActivityReminderSettingRequest;
use App\Models\ActivityReminderSetting;
use App\Services\ActivityReminderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityReminderSettingController extends Controller
{
    public function __construct(
        protected ActivityReminderService $service
    ) {}

    /**
     * Display a listing of activity reminder settings.
     */
    public function index(): View
    {
        $settings = ActivityReminderSetting::query()
            ->orderBy('priority', 'asc')
            ->get();

        $counts = $this->service->calculateEligibleCounts();

        return view('admin.activity-reminders.index', compact('settings', 'counts'));
    }

    /**
     * Update the specified activity reminder setting in storage.
     */
    public function update(UpdateActivityReminderSettingRequest $request, string $id): RedirectResponse
    {
        try {
            $setting = ActivityReminderSetting::query()->findOrFail($id);
            $setting->update($request->validated());

            return redirect()->route('admin.activity-reminders.index')
                ->with('success', "Activity reminder setting for '{$setting->activity_name}' updated successfully.");
        } catch (Throwable $e) {
            Log::error('failed_to_update_activity_reminder_setting', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update activity reminder setting: '.$e->getMessage());
        }
    }

    /**
     * Manually trigger the inactivity reminders workflow.
     */
    public function trigger(Request $request): JsonResponse|RedirectResponse
    {
        $targetUserId = $request->filled('user_id') ? (string) $request->input('user_id') : null;
        $dryRun = $request->boolean('dry_run', false);

        $result = $this->service->processInactivityReminders(
            $targetUserId,
            $dryRun
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }

        return redirect()->route('admin.activity-reminders.index')
            ->with('success', "Dispatched {$result['dispatched_reminders']} inactivity reminder(s). Processed {$result['processed_users']} user(s).");
    }
}
