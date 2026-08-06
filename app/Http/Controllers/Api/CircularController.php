<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Circular;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CircularController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Circular::query()->whereNull('deleted_at');

        $status = (string) $request->query('status', '');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        } elseif ($status === '') {
            $query->where('status', '!=', 'archived');
        }

        $now = now();
        $query->where(function ($q) use ($now): void {
            $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>', $now);
        });

        if ($request->filled('category')) {
            $query->where('category', (string) $request->query('category'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', (string) $request->query('priority'));
        }

        if ($request->filled('audience_type')) {
            $query->where('audience_type', (string) $request->query('audience_type'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', (string) $request->query('city_id'));
        }

        if ($request->filled('circle_id')) {
            $query->where('circle_id', (string) $request->query('circle_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->query('search')).'%';
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', $search)
                    ->orWhere('summary', 'like', $search);
            });
        }

        $items = $query
            ->orderByDesc('is_pinned')
            ->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 3
                    WHEN 'important' THEN 2
                    ELSE 1
                END DESC
            ")
            ->orderByDesc('publish_date')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Circulars fetched successfully.',
            'data' => [
                'items' => $items->items(),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $circular = Circular::query()
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Circular detail fetched successfully.',
            'data' => $circular,
        ]);
    }
}
