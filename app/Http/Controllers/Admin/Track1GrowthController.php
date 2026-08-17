<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MilestoneBadge;
use Database\Seeders\Track1GrowthHonoursSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Track1GrowthController extends Controller
{
    public function index(Request $request): View
    {
        $query = MilestoneBadge::query()->where('type', MilestoneBadge::TYPE_MEMBER_INTRODUCTION);

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($tier = $request->input('tier')) {
            match ($tier) {
                'digital' => $query->where('required_count', '<=', 10),
                'circle' => $query->whereBetween('required_count', [20, 50]),
                'city' => $query->whereBetween('required_count', [75, 150]),
                'national' => $query->where('required_count', '>=', 250),
                default => null,
            };
        }

        if ($request->has('status') && $request->input('status') !== '' && $request->input('status') !== null) {
            $query->where('is_active', (bool) $request->input('status'));
        }

        $honours = $query->ordered()->paginate(20)->withQueryString();

        $allHonours = MilestoneBadge::query()->where('type', MilestoneBadge::TYPE_MEMBER_INTRODUCTION)->get();

        $stats = [
            'total' => $allHonours->count(),
            'digital' => $allHonours->where('required_count', '<=', 10)->count(),
            'circle' => $allHonours->whereBetween('required_count', [20, 50])->count(),
            'city' => $allHonours->whereBetween('required_count', [75, 150])->count(),
            'national' => $allHonours->where('required_count', '>=', 250)->count(),
        ];

        $filters = [
            'search' => $request->input('q', ''),
            'tier' => $request->input('tier', ''),
            'status' => $request->input('status', ''),
        ];

        return view('admin.track1_growth.index', compact('honours', 'stats', 'filters'));
    }

    public function create(): View
    {
        return view('admin.track1_growth.form', [
            'badge' => new MilestoneBadge([
                'type' => MilestoneBadge::TYPE_MEMBER_INTRODUCTION,
                'is_active' => true,
                'sort_order' => 0,
            ]),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['type'] = MilestoneBadge::TYPE_MEMBER_INTRODUCTION;
        $data = $this->handleImageUpload($request, $data);

        MilestoneBadge::query()->create($data);

        return redirect()->route('admin.track1-growth.index')
            ->with('success', 'Track 1 Growth Honour created successfully.');
    }

    public function edit(MilestoneBadge $badge): View
    {
        return view('admin.track1_growth.form', [
            'badge' => $badge,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, MilestoneBadge $badge): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['type'] = MilestoneBadge::TYPE_MEMBER_INTRODUCTION;
        $data = $this->handleImageUpload($request, $data, $badge);

        $badge->update($data);

        return redirect()->route('admin.track1-growth.index')
            ->with('success', 'Track 1 Growth Honour updated successfully.');
    }

    public function destroy(MilestoneBadge $badge): RedirectResponse
    {
        $badge->delete();

        return redirect()->route('admin.track1-growth.index')
            ->with('success', 'Track 1 Growth Honour deleted successfully.');
    }

    public function toggleStatus(MilestoneBadge $badge): RedirectResponse
    {
        $badge->is_active = ! $badge->is_active;
        $badge->save();

        $statusLabel = $badge->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Honour {$statusLabel} successfully.");
    }

    public function seed(): RedirectResponse
    {
        $seeder = new Track1GrowthHonoursSeeder;
        $seeder->run();

        return redirect()->route('admin.track1-growth.index')
            ->with('success', 'Track 1 — Growth Honours default definitions have been seeded and updated!');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
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
            $data['badge_image_url'] = 'storage/'.$path;
        }

        return $data;
    }
}
