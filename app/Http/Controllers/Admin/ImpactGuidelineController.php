<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImpactGuideline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImpactGuidelineController extends Controller
{
    public function index(Request $request): View
    {
        $query = ImpactGuideline::query();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', (bool) $request->input('status'));
        }

        $guidelines = $query->ordered()->paginate(20)->withQueryString();

        $headerInfo = ImpactGuideline::query()->first();
        $title = $headerInfo?->title ?? 'Your Life Impact Score';
        $description = $headerInfo?->description ?? 'Study this. Know it. Start counting from today. Every action below earns you impact — tracked in the Unity App.';
        $icon = $headerInfo?->icon ?? '';

        $categories = ImpactGuideline::query()->distinct()->pluck('category')->filter()->values()->all();
        if (empty($categories)) {
            $categories = ['Business & Growth', 'Trust & Visibility', 'Community & Network'];
        }

        $stats = [
            'total' => ImpactGuideline::count(),
            'active' => ImpactGuideline::where('is_active', true)->count(),
            'categories_count' => count($categories),
        ];

        $filters = [
            'search' => $request->input('q', ''),
            'category' => $request->input('category', ''),
            'status' => $request->input('status', ''),
        ];

        return view('admin.impact-guidelines.index', compact('guidelines', 'title', 'description', 'icon', 'categories', 'stats', 'filters'));
    }

    public function create(): View
    {
        $headerInfo = ImpactGuideline::query()->first();
        $maxOrder = (int) ImpactGuideline::max('display_order');

        $categories = ImpactGuideline::query()->distinct()->pluck('category')->filter()->values()->all();
        if (empty($categories)) {
            $categories = ['Business & Growth', 'Trust & Visibility', 'Community & Network'];
        }

        $guideline = new ImpactGuideline([
            'title' => $headerInfo?->title ?? 'Your Life Impact Score',
            'description' => $headerInfo?->description ?? 'Study this. Know it. Start counting from today. Every action below earns you impact — tracked in the Unity App.',
            'icon' => $headerInfo?->icon ?? null,
            'category' => 'Business & Growth',
            'impact_value' => 1,
            'impact_unit' => 'Lives',
            'display_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return view('admin.impact-guidelines.form', [
            'guideline' => $guideline,
            'categories' => $categories,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->handleIconUpload($request, $data);

        ImpactGuideline::query()->create($data);

        return redirect()->route('admin.impact-guidelines.index')
            ->with('success', 'Impact guideline created successfully.');
    }

    public function edit(ImpactGuideline $impactGuideline): View
    {
        $categories = ImpactGuideline::query()->distinct()->pluck('category')->filter()->values()->all();
        if (empty($categories)) {
            $categories = ['Business & Growth', 'Trust & Visibility', 'Community & Network'];
        }

        return view('admin.impact-guidelines.form', [
            'guideline' => $impactGuideline,
            'categories' => $categories,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, ImpactGuideline $impactGuideline): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->handleIconUpload($request, $data, $impactGuideline);

        $impactGuideline->update($data);

        return redirect()->route('admin.impact-guidelines.index')
            ->with('success', 'Impact guideline updated successfully.');
    }

    public function destroy(ImpactGuideline $impactGuideline): RedirectResponse
    {
        $impactGuideline->delete();

        return redirect()->route('admin.impact-guidelines.index')
            ->with('success', 'Impact guideline deleted successfully.');
    }

    public function toggleStatus(ImpactGuideline $impactGuideline): RedirectResponse
    {
        $impactGuideline->is_active = ! $impactGuideline->is_active;
        $impactGuideline->save();

        $statusLabel = $impactGuideline->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Impact guideline {$statusLabel} successfully.");
    }

    public function updateHeader(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'icon_file' => ['nullable', 'image', 'max:5120'],
            'icon_url' => ['nullable', 'string', 'max:500'],
        ]);

        $icon = $validated['icon_url'] ?? null;
        if ($request->hasFile('icon_file')) {
            $path = $request->file('icon_file')->store('impact-guidelines', 'public');
            $icon = asset('storage/'.$path);
        }

        $updateData = [
            'title' => $validated['title'],
            'description' => $validated['description'],
        ];

        if ($icon !== null) {
            $updateData['icon'] = $icon;
        }

        ImpactGuideline::query()->update($updateData);

        return redirect()->back()
            ->with('success', 'Impact Guidelines header information updated successfully.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $orders = $request->input('orders', []);
        if (! is_array($orders)) {
            return response()->json(['success' => false, 'message' => 'Invalid orders payload.'], 400);
        }

        foreach ($orders as $orderItem) {
            if (isset($orderItem['id'], $orderItem['display_order'])) {
                ImpactGuideline::where('id', $orderItem['id'])->update([
                    'display_order' => (int) $orderItem['display_order'],
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Display orders updated successfully.']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'action' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'impact_value' => ['required', 'integer', 'min:0'],
            'impact_unit' => ['required', 'string', 'max:50'],
            'display_order' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon_file' => ['nullable', 'image', 'max:5120'],
            'icon_url' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function handleIconUpload(Request $request, array $data, ?ImpactGuideline $existing = null): array
    {
        $data['is_active'] = $request->boolean('is_active');
        unset($data['icon_file'], $data['icon_url']);

        if ($request->hasFile('icon_file')) {
            $path = $request->file('icon_file')->store('impact-guidelines', 'public');
            $data['icon'] = asset('storage/'.$path);
        } elseif ($request->filled('icon_url')) {
            $data['icon'] = $request->input('icon_url');
        }

        if (empty($data['title'])) {
            $header = ImpactGuideline::first();
            $data['title'] = $header?->title ?? 'Your Life Impact Score';
            $data['description'] = $header?->description ?? 'Study this. Know it. Start counting from today.';
            if (! isset($data['icon']) && $header?->icon) {
                $data['icon'] = $header->icon;
            }
        }

        return $data;
    }
}
