<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinGuideline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoinGuidelineController extends Controller
{
    public function index(Request $request): View
    {
        $query = CoinGuideline::query();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('activity', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', (bool) $request->input('status'));
        }

        $guidelines = $query->ordered()->paginate(20)->withQueryString();

        $headerInfo = CoinGuideline::query()->first();
        $title = $headerInfo?->title ?? 'The Coin Reward System';
        $description = $headerInfo?->description ?? 'Coins are rewards for being an active community builder. They reflect your engagement and contributions to the network.';
        $icon = $headerInfo?->icon ?? '';

        $stats = [
            'total' => CoinGuideline::count(),
            'active' => CoinGuideline::where('is_active', true)->count(),
            'total_coins' => CoinGuideline::where('is_active', true)->sum('coins'),
        ];

        $filters = [
            'search' => $request->input('q', ''),
            'status' => $request->input('status', ''),
        ];

        return view('admin.coin-guidelines.index', compact('guidelines', 'title', 'description', 'icon', 'stats', 'filters'));
    }

    public function create(): View
    {
        $headerInfo = CoinGuideline::query()->first();
        $maxOrder = (int) CoinGuideline::max('display_order');

        $guideline = new CoinGuideline([
            'title' => $headerInfo?->title ?? 'The Coin Reward System',
            'description' => $headerInfo?->description ?? 'Coins are rewards for being an active community builder. They reflect your engagement and contributions to the network.',
            'icon' => $headerInfo?->icon ?? null,
            'display_order' => $maxOrder + 1,
            'is_active' => true,
            'coins' => 1000,
        ]);

        return view('admin.coin-guidelines.form', [
            'guideline' => $guideline,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->handleIconUpload($request, $data);

        CoinGuideline::query()->create($data);

        return redirect()->route('admin.coin-guidelines.index')
            ->with('success', 'Coin guideline created successfully.');
    }

    public function edit(CoinGuideline $coinGuideline): View
    {
        return view('admin.coin-guidelines.form', [
            'guideline' => $coinGuideline,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, CoinGuideline $coinGuideline): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->handleIconUpload($request, $data, $coinGuideline);

        $coinGuideline->update($data);

        return redirect()->route('admin.coin-guidelines.index')
            ->with('success', 'Coin guideline updated successfully.');
    }

    public function destroy(CoinGuideline $coinGuideline): RedirectResponse
    {
        $coinGuideline->delete();

        return redirect()->route('admin.coin-guidelines.index')
            ->with('success', 'Coin guideline deleted successfully.');
    }

    public function toggleStatus(CoinGuideline $coinGuideline): RedirectResponse
    {
        $coinGuideline->is_active = ! $coinGuideline->is_active;
        $coinGuideline->save();

        $statusLabel = $coinGuideline->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Coin guideline {$statusLabel} successfully.");
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
            $path = $request->file('icon_file')->store('coin-guidelines', 'public');
            $icon = asset('storage/'.$path);
        }

        $updateData = [
            'title' => $validated['title'],
            'description' => $validated['description'],
        ];

        if ($icon !== null) {
            $updateData['icon'] = $icon;
        }

        CoinGuideline::query()->update($updateData);

        return redirect()->back()
            ->with('success', 'Coin Guidelines header information updated successfully.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $orders = $request->input('orders', []);
        if (! is_array($orders)) {
            return response()->json(['success' => false, 'message' => 'Invalid orders payload.'], 400);
        }

        foreach ($orders as $orderItem) {
            if (isset($orderItem['id'], $orderItem['display_order'])) {
                CoinGuideline::where('id', $orderItem['id'])->update([
                    'display_order' => (int) $orderItem['display_order'],
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Display orders updated successfully.']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'activity' => ['required', 'string', 'max:255'],
            'coins' => ['required', 'integer', 'min:0'],
            'display_order' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon_file' => ['nullable', 'image', 'max:5120'],
            'icon_url' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function handleIconUpload(Request $request, array $data, ?CoinGuideline $existing = null): array
    {
        $data['is_active'] = $request->boolean('is_active');
        unset($data['icon_file'], $data['icon_url']);

        if ($request->hasFile('icon_file')) {
            $path = $request->file('icon_file')->store('coin-guidelines', 'public');
            $data['icon'] = asset('storage/'.$path);
        } elseif ($request->filled('icon_url')) {
            $data['icon'] = $request->input('icon_url');
        }

        // Fallback header info from existing records if not provided
        if (empty($data['title'])) {
            $header = CoinGuideline::first();
            $data['title'] = $header?->title ?? 'The Coin Reward System';
            $data['description'] = $header?->description ?? 'Coins are rewards for being an active community builder.';
            if (! isset($data['icon']) && $header?->icon) {
                $data['icon'] = $header->icon;
            }
        }

        return $data;
    }
}
