<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebBlog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebBlogController extends Controller
{
    public function index(Request $request): View
    {
        $query = WebBlog::query();

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sq) use ($q): void {
                $sq->where('title', 'ilike', "%{$q}%")
                    ->orWhere('author_name', 'ilike', "%{$q}%")
                    ->orWhere('category', 'ilike', "%{$q}%");
            });
        }

        $blogs = $query->latest()->paginate(15)->withQueryString();

        return view('admin.web.blogs.index', [
            'blogs' => $blogs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'author_name' => 'nullable|string|max:255',
            'author_role' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'read_time' => 'nullable|string|max:50',
            'is_published' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['title']).'-'.random_int(100, 999);
        $validated['is_published'] = $request->has('is_published');
        $validated['published_at'] = $validated['is_published'] ? now() : null;

        WebBlog::create($validated);

        return redirect()->route('admin.web.blogs.index')->with('success', 'Publication article created successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $blog = WebBlog::findOrFail($id);
        $blog->delete();

        return redirect()->route('admin.web.blogs.index')->with('success', 'Article deleted.');
    }
}
