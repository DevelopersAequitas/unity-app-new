<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityVideoController extends Controller
{
    /**
     * Display a listing of all activities with their video configuration.
     */
    public function index(Request $request): View
    {
        $activities = ActivityVideo::ACTIVITIES;
        $configured = Schema::hasTable('activity_videos')
            ? ActivityVideo::all()->keyBy('activity_key')
            : collect();

        // Calculate summary stats
        $totalActivities = count($activities);
        $configuredCount = $configured->count();
        $activeCount = $configured->where('is_active', true)->count();
        $youtubeCount = $configured->where('video_type', 'youtube')->count();
        $fileCount = $configured->where('video_type', 'file')->count();
        $missingCount = $totalActivities - $configuredCount;

        $stats = [
            'total_activities' => $totalActivities,
            'configured_count' => $configuredCount,
            'active_count' => $activeCount,
            'youtube_count' => $youtubeCount,
            'file_count' => $fileCount,
            'missing_count' => $missingCount,
        ];

        return view('admin.activities.videos.index', [
            'activities' => $activities,
            'configured' => $configured,
            'stats' => $stats,
        ]);
    }

    /**
     * Store or update activity video configuration.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('activity_videos')) {
            return back()->with('error', 'The activity_videos table is not yet created. Please run the database query first.');
        }

        $validKeys = array_keys(ActivityVideo::ACTIVITIES);

        $validated = $request->validate([
            'activity_key' => ['required', 'string', Rule::in($validKeys)],
            'video_type' => ['required', 'string', Rule::in(['youtube', 'file'])],
            'youtube_url' => ['nullable', 'url', 'max:500'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,mkv,3gp,m4v', 'max:51200'], // 50MB
            'is_active' => ['nullable', 'boolean'],
        ]);

        $activityKey = $validated['activity_key'];
        $activityName = ActivityVideo::ACTIVITIES[$activityKey] ?? ucwords(str_replace('_', ' ', $activityKey));
        $videoType = $validated['video_type'];
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;

        $videoUrl = null;

        if ($videoType === 'youtube') {
            if (empty($validated['youtube_url'])) {
                return back()->withInput()->with('error', 'Please enter a valid YouTube URL.');
            }

            // Verify YouTube URL pattern
            $ytUrl = trim((string) $validated['youtube_url']);
            if (! preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i', $ytUrl)) {
                return back()->withInput()->with('error', 'Please provide a valid YouTube video link (youtube.com or youtu.be).');
            }

            $videoUrl = $ytUrl;
        } elseif ($videoType === 'file') {
            if (! $request->hasFile('video_file')) {
                // Check if updating existing record that already has a file URL
                $existing = ActivityVideo::where('activity_key', $activityKey)->first();
                if ($existing && $existing->video_type === 'file' && ! empty($existing->video_url)) {
                    $videoUrl = $existing->video_url;
                } else {
                    return back()->withInput()->with('error', 'Please select a video file to upload.');
                }
            } else {
                $path = $request->file('video_file')->store('activity_videos', 'public');
                $videoUrl = asset('storage/'.$path);
            }
        }

        ActivityVideo::updateOrCreate(
            ['activity_key' => $activityKey],
            [
                'activity_name' => $activityName,
                'video_type' => $videoType,
                'video_url' => $videoUrl,
                'is_active' => $isActive,
            ]
        );

        return redirect()->route('admin.activities.videos.index')
            ->with('success', "Video for '{$activityName}' configured successfully.");
    }

    /**
     * Toggle active status.
     */
    public function toggle(string $id): RedirectResponse
    {
        $video = ActivityVideo::findOrFail($id);
        $video->update(['is_active' => ! $video->is_active]);

        $status = $video->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Video for '{$video->activity_name}' has been {$status}.");
    }

    /**
     * Delete video configuration.
     */
    public function destroy(string $id): RedirectResponse
    {
        $video = ActivityVideo::findOrFail($id);
        $name = $video->activity_name;
        $video->delete();

        return redirect()->route('admin.activities.videos.index')
            ->with('success', "Video for '{$name}' has been removed.");
    }
}
