<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebPageMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebPageMediaController extends Controller
{
    public function index(Request $request): View
    {
        $query = WebPageMedia::query();

        if ($request->filled('page_id') && $request->input('page_id') !== 'all') {
            $query->where('page_id', $request->input('page_id'));
        }

        if ($request->filled('source') && $request->input('source') !== 'all') {
            $query->where('media_source', $request->input('source'));
        }

        $items = $query->orderBy('sort_order')->orderBy('created_at', 'desc')->get();

        $pages = [
            ['id' => 'home', 'name' => 'Home Page', 'sections' => ['Hero Background Header', 'Cyber Earth Globe', 'Who We Are Boardroom', 'Leadership Panel']],
            ['id' => 'circles', 'name' => 'Circles Hub', 'sections' => ['Circles Hero Header', 'Roundtable Showcase', 'Operational Hubs Map']],
            ['id' => 'membership', 'name' => 'Membership', 'sections' => ['Membership Hero', 'Tier Benefits', 'Application Form']],
            ['id' => 'leadership', 'name' => 'Leadership', 'sections' => ['Founders Showcase', 'Director Conclave', 'Climbers Panel']],
            ['id' => 'stories', 'name' => 'Stories & Outcomes', 'sections' => ['Stories Hero', 'Video Testimonials', 'Outcomes Grid']],
            ['id' => 'events', 'name' => 'Events & Conclaves', 'sections' => ['Conclave Hero', 'Upcoming Summits', 'Event Gallery']],
            ['id' => 'about', 'name' => 'About Us', 'sections' => ['Origin Story', 'Philosophy Banner', 'Mission 2030']],
        ];

        return view('admin.web.page-media.index', [
            'items' => $items,
            'pages' => $pages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'page_id' => 'required|string',
            'page_title' => 'nullable|string',
            'section_key' => 'nullable|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'media_type' => 'required|string',
            'media_source' => 'required|string',
            'media_url' => 'nullable|string',
            'local_file_name' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        WebPageMedia::create($validated);

        return redirect()->route('admin.web.page-media.index')->with('success', 'Page media assignment saved.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $item = WebPageMedia::findOrFail($id);
        $item->delete();

        return redirect()->route('admin.web.page-media.index')->with('success', 'Page media removed.');
    }
}
