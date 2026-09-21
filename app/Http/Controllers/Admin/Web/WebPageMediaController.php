<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebMedia;
use App\Models\Web\WebPageMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebPageMediaController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('web_page_medias')) {
            return view('admin.web.page-media.index', [
                'items' => collect(),
                'pages' => $this->getPagesList(),
            ]);
        }

        $query = WebPageMedia::query();

        if ($request->filled('page_id') && $request->input('page_id') !== 'all') {
            $query->where('page_id', $request->input('page_id'));
        }

        if ($request->filled('source') && $request->input('source') !== 'all') {
            if (Schema::hasColumn('web_page_medias', 'media_source')) {
                $query->where('media_source', $request->input('source'));
            }
        }

        if (Schema::hasColumn('web_page_medias', 'sort_order')) {
            $query->orderBy('sort_order');
        } elseif (Schema::hasColumn('web_page_medias', 'display_order')) {
            $query->orderBy('display_order');
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        return view('admin.web.page-media.index', [
            'items' => $items,
            'pages' => $this->getPagesList(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string',
            'page_id' => 'required|string',
            'page_title' => 'nullable|string',
            'page_slug' => 'nullable|string',
            'section_key' => 'nullable|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'media_type' => 'required|string',
            'media_source' => 'required|string',
            'media_url' => 'nullable|string',
            'media_file' => 'nullable|file|max:102400', // up to 100MB video/image
            'is_active' => 'nullable',
        ]);

        $mediaUrl = $validated['media_url'] ?? '';

        // Handle uploaded file if provided
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
                $mediaUrl = '/uploads/web-media/'.$fileName;

                if (Schema::hasTable('web_media')) {
                    WebMedia::create([
                        'title' => $validated['title'],
                        'file_name' => $fileName,
                        'file_path' => $mediaUrl,
                        'file_type' => $validated['media_type'],
                        'file_size' => filesize($destinationPath.DIRECTORY_SEPARATOR.$fileName),
                        'category' => $validated['page_id'],
                    ]);
                }
            }
        }

        $pageNames = [
            'home' => 'Home Page',
            'circles' => 'Circles Hub',
            'membership' => 'Membership',
            'leadership' => 'Leadership',
            'stories' => 'Stories & Outcomes',
            'events' => 'Events & Conclaves',
            'about' => 'About Us',
        ];

        $pageSlug = '/'.($validated['page_id'] === 'home' ? '' : $validated['page_id']);

        $data = [
            'page_id' => $validated['page_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'media_type' => $validated['media_type'],
            'media_url' => $mediaUrl,
            'is_active' => $request->has('is_active') ? true : false,
        ];

        if (Schema::hasColumn('web_page_medias', 'page_title')) {
            $data['page_title'] = $pageNames[$validated['page_id']] ?? ucfirst($validated['page_id']);
        }
        if (Schema::hasColumn('web_page_medias', 'page_slug')) {
            $data['page_slug'] = $pageSlug;
        }
        if (Schema::hasColumn('web_page_medias', 'section_key')) {
            $data['section_key'] = $validated['section_key'] ?? 'Main Section';
        }
        if (Schema::hasColumn('web_page_medias', 'section_name')) {
            $data['section_name'] = $validated['section_key'] ?? 'Main Section';
        }
        if (Schema::hasColumn('web_page_medias', 'slot_key')) {
            $data['slot_key'] = Str::slug($validated['section_key'] ?? 'main-section');
        }
        if (Schema::hasColumn('web_page_medias', 'media_source')) {
            $data['media_source'] = $validated['media_source'];
        }

        if (! empty($validated['id'])) {
            $item = WebPageMedia::find($validated['id']);
            if ($item) {
                $item->update($data);

                return redirect()->route('admin.web.page-media.index')->with('success', 'Page media slot updated successfully.');
            }
        }

        WebPageMedia::create($data);

        return redirect()->route('admin.web.page-media.index')->with('success', 'Page media assignment saved successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $item = WebPageMedia::find($id);
        if ($item) {
            $item->delete();
        }

        return redirect()->route('admin.web.page-media.index')->with('success', 'Page media removed successfully.');
    }

    protected function getPagesList(): array
    {
        return [
            ['id' => 'home', 'name' => 'Home Page', 'sections' => ['Hero Background Header', 'Cyber Earth Globe', 'Who We Are Boardroom', 'Leadership Panel']],
            ['id' => 'circles', 'name' => 'Circles Hub', 'sections' => ['Circles Hero Header', 'Roundtable Showcase', 'Operational Hubs Map']],
            ['id' => 'membership', 'name' => 'Membership', 'sections' => ['Membership Hero', 'Tier Benefits', 'Application Form']],
            ['id' => 'leadership', 'name' => 'Leadership', 'sections' => ['Founders Showcase', 'Director Conclave', 'Climbers Panel']],
            ['id' => 'stories', 'name' => 'Stories & Outcomes', 'sections' => ['Stories Hero', 'Video Testimonials', 'Outcomes Grid']],
            ['id' => 'events', 'name' => 'Events & Conclaves', 'sections' => ['Conclave Hero', 'Upcoming Summits', 'Event Gallery']],
            ['id' => 'about', 'name' => 'About Us', 'sections' => ['Origin Story', 'Philosophy Banner', 'Mission 2030']],
        ];
    }
}
