<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnniversaryTemplate;
use App\Models\User;
use App\Services\Creative\AnniversaryImageGenerator;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AnniversaryTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeGlobalAdmin($request);

        $templates = AnniversaryTemplate::query()
            ->latest('created_at')
            ->get();

        // Fetch users celebrating wedding anniversary today (for display panel)
        $today = now(config('app.timezone', 'UTC'));
        $anniversaryUsers = User::query()
            ->where('status', 'active')
            ->whereNotNull('anniversary_date')
            ->whereMonth('anniversary_date', $today->month)
            ->whereDay('anniversary_date', $today->day)
            ->get();

        // Users for dynamic preview dropdown (exclude system account)
        $previewUsers = User::query()
            ->whereNull('deleted_at')
            ->where('email', '!=', 'info@peersglobal.com')
            ->orderBy('first_name')
            ->take(50)
            ->get();

        return view('admin.anniversary-creatives.index', [
            'templates' => $templates,
            'anniversaryUsers' => $anniversaryUsers,
            'previewUsers' => $previewUsers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeGlobalAdmin($request);

        $request->validate([
            'image' => ['required', 'image', 'max:10240'], // Max 10MB
            'message' => ['required', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $disk = config('filesystems.default', 'public');
        $file = $request->file('image');
        $path = $file->store('uploads/anniversary/templates', $disk);

        $isActive = $request->boolean('is_active', false);

        if ($isActive) {
            // Deactivate all other templates
            AnniversaryTemplate::query()->update(['is_active' => false]);
        }

        AnniversaryTemplate::create([
            'image_path' => $path,
            'message' => $request->input('message'),
            'is_active' => $isActive,
        ]);

        return redirect()
            ->route('admin.anniversary-creatives.index')
            ->with('success', 'Anniversary template uploaded successfully.');
    }

    public function toggleActive(Request $request, AnniversaryTemplate $template): RedirectResponse
    {
        $this->authorizeGlobalAdmin($request);

        $newStatus = ! $template->is_active;

        if ($newStatus) {
            // Deactivate all other templates first
            AnniversaryTemplate::query()->update(['is_active' => false]);
        }

        $template->update(['is_active' => $newStatus]);

        $statusMessage = $newStatus ? 'enabled' : 'disabled';

        return redirect()
            ->route('admin.anniversary-creatives.index')
            ->with('success', "Anniversary template {$statusMessage} successfully.");
    }

    public function destroy(Request $request, AnniversaryTemplate $template): RedirectResponse
    {
        $this->authorizeGlobalAdmin($request);

        $disk = config('filesystems.default', 'public');
        if (Storage::disk($disk)->exists($template->image_path)) {
            Storage::disk($disk)->delete($template->image_path);
        }

        $template->delete();

        return redirect()
            ->route('admin.anniversary-creatives.index')
            ->with('success', 'Anniversary template deleted successfully.');
    }

    /**
     * Generate dynamic live preview.
     */
    public function preview(string $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $activeTemplate = AnniversaryTemplate::where('is_active', true)->first();

            $imageGenerator = app(AnniversaryImageGenerator::class);
            $fileModel = $imageGenerator->generate($user, $activeTemplate);

            $disk = 'public';
            if (! $fileModel->s3_key || ! Storage::disk($disk)->exists($fileModel->s3_key)) {
                abort(404, 'Generated creative image not found in storage.');
            }

            $path = Storage::disk($disk)->path($fileModel->s3_key);

            if (! file_exists($path)) {
                abort(404, 'Generated creative image file not found on disk.');
            }

            $response = response()->file($path, [
                'Content-Type' => 'image/webp',
                'Cache-Control' => 'no-cache, must-revalidate',
            ])->deleteFileAfterSend(true);

            // Clean up the database record only
            $fileModel->delete();

            return $response;
        } catch (\Throwable $e) {
            Log::error('Failed to generate preview for anniversary creative: '.$e->getMessage());
            abort(500, 'Error generating preview: '.$e->getMessage());
        }
    }

    private function authorizeGlobalAdmin(Request $request): void
    {
        if (! AdminAccess::isGlobalAdmin($request->user('admin'))) {
            abort(403);
        }
    }
}
