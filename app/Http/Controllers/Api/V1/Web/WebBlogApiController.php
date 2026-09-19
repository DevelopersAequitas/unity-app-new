<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebBlog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebBlogApiController extends Controller
{
    /**
     * Get published blogs for website.
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $tag = $request->query('tag');
        $limit = (int) $request->query('limit', 20);

        $query = WebBlog::query()->where('is_published', true);

        if (! empty($category)) {
            $query->where('category', $category);
        }

        if (! empty($tag)) {
            $query->whereJsonContains('tags', $tag);
        }

        $blogs = $query->orderBy('published_at', 'desc')->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $blogs->items(),
            'meta' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'total' => $blogs->total(),
            ],
        ]);
    }

    /**
     * Get single blog by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $blog = WebBlog::where('slug', $slug)->first();

        if (! $blog) {
            return response()->json(['success' => false, 'message' => 'Article not found'], 404);
        }

        $blog->increment('views_count');

        return response()->json([
            'success' => true,
            'data' => $blog,
        ]);
    }

    /**
     * Create or update blog from API/Admin.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string',
            'content' => 'required|string',
            'cover_image' => 'nullable|string',
            'category' => 'nullable|string',
            'tags' => 'nullable|array',
            'author_name' => 'nullable|string',
            'read_time' => 'nullable|string',
            'is_published' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        if (! empty($validated['is_published']) && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $blog = null;
        if (! empty($validated['id'])) {
            $blog = WebBlog::find($validated['id']);
        }

        if ($blog) {
            $blog->update($validated);
        } else {
            $blog = WebBlog::create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Article saved successfully',
            'data' => $blog,
        ]);
    }
}
