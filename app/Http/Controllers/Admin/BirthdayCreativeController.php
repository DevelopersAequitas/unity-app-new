<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\FileModel;
use App\Models\BirthdayCreativeConfig;
use App\Services\Media\BirthdayCreativeImageService;
use App\Services\Media\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Log;

class BirthdayCreativeController extends Controller
{
    public function __construct(
        protected BirthdayCreativeImageService $imageService,
        protected FileUploadService $fileUploadService
    ) {}

    public function index(Request $request): View
    {
        $config = BirthdayCreativeConfig::first();
        if (!$config) {
            $config = BirthdayCreativeConfig::create([
                'is_enabled' => true,
                'background_gradient_start' => '#8E2DE2',
                'background_gradient_end' => '#4A00E0',
                'text_color' => '#FFFFFF',
            ]);
        }

        // Fetch users celebrating birthday today
        $todayMmDd = now()->format('m-d');
        $birthdayUsers = User::query()
            ->whereNotNull('dob')
            ->whereRaw("to_char(dob, 'MM-DD') = ?", [$todayMmDd])
            ->whereNull('deleted_at')
            ->get();

        // Sample users for preview dropdown
        $previewUsers = User::query()->whereNull('deleted_at')->limit(30)->get();

        return view('admin.birthday-creative.index', [
            'config' => $config,
            'birthdayUsers' => $birthdayUsers,
            'previewUsers' => $previewUsers,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'is_enabled' => 'required|boolean',
            'background_gradient_start' => 'required|string|max:10',
            'background_gradient_end' => 'required|string|max:10',
            'text_color' => 'required|string|max:10',
            'template_image' => 'nullable|image|max:10240', // Max 10MB
        ]);

        $config = BirthdayCreativeConfig::first();
        if (!$config) {
            $config = new BirthdayCreativeConfig();
        }

        $config->is_enabled = (bool) $request->input('is_enabled');
        $config->background_gradient_start = $request->input('background_gradient_start');
        $config->background_gradient_end = $request->input('background_gradient_end');
        $config->text_color = $request->input('text_color');

        if ($request->hasFile('template_image')) {
            // Delete old template file if it exists
            if ($config->template_file_id) {
                $oldFile = FileModel::find($config->template_file_id);
                if ($oldFile) {
                    $this->fileUploadService->delete($oldFile);
                }
            }

            // Store new template file
            $file = $request->file('template_image');
            $fileModel = $this->fileUploadService->store($file, auth('admin')->user());
            $config->template_file_id = $fileModel->id;
        }

        // Check if user wants to delete current template
        if ($request->boolean('delete_template') && $config->template_file_id) {
            $oldFile = FileModel::find($config->template_file_id);
            if ($oldFile) {
                $this->fileUploadService->delete($oldFile);
            }
            $config->template_file_id = null;
        }

        $config->save();

        return redirect()->back()->with('success', 'Birthday Creative configuration updated successfully.');
    }

    public function preview(string $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Generate the image temporarily using our service
            $fileModel = $this->imageService->generate($user);
            
            // Fetch the physical file content and respond
            $disk = 'public';
            
            if (!$fileModel->s3_key || !Storage::disk($disk)->exists($fileModel->s3_key)) {
                abort(404, "Generated creative image not found in storage.");
            }

            $path = Storage::disk($disk)->path($fileModel->s3_key);
            
            if (!file_exists($path)) {
                abort(404, "Generated creative image file not found on disk.");
            }
            
            $response = response()->file($path, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'no-cache, must-revalidate',
            ])->deleteFileAfterSend(true);

            // Clean up the database record only, since deleteFileAfterSend handles physical file cleanup
            $fileModel->delete();

            return $response;
        } catch (\Throwable $e) {
            Log::error("Failed to generate preview for birthday creative: " . $e->getMessage());
            abort(500, "Error generating preview: " . $e->getMessage());
        }
    }
}
