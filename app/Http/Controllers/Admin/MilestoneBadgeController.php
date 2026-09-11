<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MilestoneBadge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MilestoneBadgeController extends Controller
{
    public function index(Request $request): View
    {
        $query = MilestoneBadge::query();

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->input('status') !== '' && $request->input('status') !== null) {
            $query->where('is_active', (bool) $request->input('status'));
        }

        $badges = $query->ordered()->paginate(20)->withQueryString();

        $filters = [
            'type' => $request->input('type', ''),
            'search' => $request->input('q', ''),
            'status' => $request->input('status', ''),
        ];

        return view('admin.milestone_badges.index', compact('badges', 'filters'));
    }

    public function create(): View
    {
        return view('admin.milestone_badges.form', [
            'badge' => new MilestoneBadge(['is_active' => true, 'sort_order' => 0]),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->handleImageUpload($request, $data);

        $badge = MilestoneBadge::query()->create($data);

        return redirect()->route('admin.milestone-badges.index')
            ->with('success', 'Milestone badge created successfully.');
    }

    public function edit(MilestoneBadge $badge): View
    {
        return view('admin.milestone_badges.form', [
            'badge' => $badge,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, MilestoneBadge $badge): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->handleImageUpload($request, $data, $badge);

        $badge->update($data);

        return redirect()->route('admin.milestone-badges.index')
            ->with('success', 'Milestone badge updated successfully.');
    }

    public function destroy(MilestoneBadge $badge): RedirectResponse
    {
        $badge->delete();

        return redirect()->route('admin.milestone-badges.index')
            ->with('success', 'Milestone badge deleted successfully.');
    }

    public function toggleStatus(MilestoneBadge $badge): RedirectResponse
    {
        $badge->is_active = ! $badge->is_active;
        $badge->save();

        $statusLabel = $badge->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Milestone badge {$statusLabel} successfully.");
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(MilestoneBadge::ALLOWED_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'required_count' => ['required', 'integer', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'badge_image' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function handleImageUpload(Request $request, array $data, ?MilestoneBadge $existingBadge = null): array
    {
        $data['is_active'] = $request->boolean('is_active');
        unset($data['badge_image']);

        if ($request->hasFile('badge_image')) {
            $path = $request->file('badge_image')->store('milestone-badges', 'public');
            $data['badge_image_url'] = 'https://peersunity.com/api/v1/files/'.$path;
        }

        return $data;
    }
}
