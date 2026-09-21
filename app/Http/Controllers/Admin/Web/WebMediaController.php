<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebMediaController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->query('category');
        $type = $request->query('type');
        $search = $request->query('search');

        if (! \Illuminate\Support\Facades\Schema::hasTable('web_media')) {
            $emptyPaginator = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 18);

            return view('admin.web.media.index', [
                'mediaAssets' => $emptyPaginator,
                'selectedCategory' => $category ?? 'all',
                'selectedType' => $type ?? 'all',
            ]);
        }

        $query = WebMedia::query();

        if (! empty($category) && $category !== 'all') {
            $query->where('category', $category);
        }

        if (! empty($type) && $type !== 'all') {
            $query->where('file_type', $type);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $mediaAssets = $query->orderBy('created_at', 'desc')->paginate(18);

        return view('admin.web.media.index', [
            'mediaAssets' => $mediaAssets,
            'selectedCategory' => $category ?? 'all',
            'selectedType' => $type ?? 'all',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'file_type' => 'required|string',
            'media_file' => 'nullable|file|max:102400',
            'media_url' => 'nullable|string',
        ]);

        $filePath = $request->input('media_url') ?: '';
        $fileSize = 0;
        $fileName = 'external-link';

        if ($request->hasFile('media_file')) {
            $file = $request->file('media_file');
            if ($file && $file->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension());
                $fileName = Str::uuid().'.'.$ext;
                $destinationPath = public_path('uploads/web-media');

                if (! File::isDirectory($destinationPath)) {
                    File::makeDirectory($destinationPath, 0755, true, true);
                }

                $file->move($destinationPath, $fileName);
                $filePath = '/uploads/web-media/'.$fileName;
                $fileSize = filesize($destinationPath.DIRECTORY_SEPARATOR.$fileName);
            }
        }

        WebMedia::create([
            'title' => $request->input('title'),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $request->input('file_type'),
            'file_size' => $fileSize,
            'category' => $request->input('category', 'general'),
            'alt_text' => $request->input('title'),
        ]);

        return redirect()->route('admin.web.media.index')->with('success', 'Media asset uploaded successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $media = WebMedia::find($id);
        if ($media) {
            // Delete file if local
            if (Str::startsWith($media->file_path, '/uploads/web-media/')) {
                $localPath = public_path(ltrim($media->file_path, '/'));
                if (File::exists($localPath)) {
                    File::delete($localPath);
                }
            }
            $media->delete();
        }

        return redirect()->route('admin.web.media.index')->with('success', 'Media asset deleted successfully.');
    }
}
