<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tutorial;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TutorialController extends Controller
{
    /**
     * Display the listing of tutorials.
     */
    public function index(Request $request): View
    {
        $tutorials = Tutorial::orderBy('created_at', 'desc')->get();

        return view('admin.tutorials.index', [
            'tutorials' => $tutorials,
        ]);
    }

    /**
     * Store a newly created tutorial.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'youtube_url' => ['required', 'string', 'url'],
        ]);

        $url = $request->input('youtube_url');
        $videoId = $this->extractVideoId($url);

        if (! $videoId) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['youtube_url' => 'Could not extract a valid YouTube video ID from the provided URL.']);
        }

        if (Tutorial::where('video_id', $videoId)->exists()) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['youtube_url' => 'This YouTube video has already been added.']);
        }

        try {
            Tutorial::create([
                'video_id' => $videoId,
                'youtube_url' => $url,
            ]);

            return redirect()->route('admin.tutorials.index')
                ->with('success', 'Tutorial video added successfully.');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['youtube_url' => 'Failed to save tutorial: '.$e->getMessage()]);
        }
    }

    /**
     * Remove the specified tutorial.
     */
    public function destroy(string $id): RedirectResponse
    {
        try {
            $tutorial = Tutorial::findOrFail($id);
            $tutorial->delete();

            return redirect()->route('admin.tutorials.index')
                ->with('success', 'Tutorial video removed successfully.');
        } catch (Exception $e) {
            return redirect()->route('admin.tutorials.index')
                ->withErrors(['error' => 'Failed to remove tutorial: '.$e->getMessage()]);
        }
    }

    /**
     * Extract YouTube video ID from URL.
     */
    private function extractVideoId(string $url): ?string
    {
        // 1. Matches youtu.be/ID
        if (preg_match('/youtu\.be\/([^#?&]+)/', $url, $matches)) {
            return $matches[1];
        }

        // 2. Matches youtube.com/watch?v=ID
        if (preg_match('/[?&]v=([^#?&]+)/', $url, $matches)) {
            return $matches[1];
        }

        // 3. Matches youtube.com/shorts/ID or embed/ID or v/ID
        if (preg_match('/youtube\.com\/(?:shorts|embed|v)\/([^#?&]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
