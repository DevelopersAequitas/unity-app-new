<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ads\StoreAdRequest;
use App\Http\Requests\Admin\Ads\UpdateAdRequest;
use App\Models\Ad;
use App\Services\Ads\AdAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdController extends Controller
{
    private const PLACEMENTS = ['timeline', 'dashboard', 'home', 'banner', 'popup', 'sidebar'];

    public function __construct(
        private readonly AdAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $placement = trim((string) $request->query('placement', ''));
        $status = trim((string) $request->query('status', ''));

        $isPgSql = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPgSql ? 'ILIKE' : 'LIKE';

        $ads = Ad::query()
            ->withCount(['views', 'clicks'])
            ->when($search !== '', function ($query) use ($search, $likeOp) {
                $query->where(function ($sub) use ($search, $likeOp) {
                    $sub->where('title', $likeOp, '%'.$search.'%')
                        ->orWhere('subtitle', $likeOp, '%'.$search.'%')
                        ->orWhere('description', $likeOp, '%'.$search.'%');
                });
            })
            ->when($placement !== '' && \Illuminate\Support\Facades\Schema::hasColumn('ads', 'placement'), function ($query) use ($placement) {
                $query->where('placement', $placement);
            })
            ->when($status !== '' && \Illuminate\Support\Facades\Schema::hasColumn('ads', 'is_active'), function ($query) use ($status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.ads.index', compact('ads', 'search', 'placement', 'status'));
    }

    public function show(Ad $ad): View
    {
        $analytics = $this->analyticsService->getAdAnalytics($ad->id);

        return view('admin.ads.show', compact('ad', 'analytics'));
    }

    public function create(): View
    {
        return view('admin.ads.create', [
            'ad' => new Ad(['is_active' => true]),
            'placements' => self::PLACEMENTS,
        ]);
    }

    public function store(StoreAdRequest $request): RedirectResponse
    {
        $data = $this->payload($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }

        $adminUser = Auth::guard('admin')->user();
        $data['created_by'] = $adminUser?->id;

        Ad::query()->create($data);

        return redirect()->route('admin.ads.index')->with('success', 'Ad created successfully.');
    }

    public function edit(Ad $ad): View
    {
        return view('admin.ads.edit', [
            'ad' => $ad,
            'placements' => self::PLACEMENTS,
        ]);
    }

    public function update(UpdateAdRequest $request, Ad $ad): RedirectResponse
    {
        $data = $this->payload($request);

        if ($request->hasFile('image')) {
            $oldImagePath = $ad->normalizedImagePath();
            $data['image_path'] = $this->storeImage($request);

            if ($oldImagePath && ! str_starts_with($oldImagePath, 'http')) {
                Storage::disk('public')->delete($oldImagePath);
            }
        }

        $ad->update($data);

        return redirect()->route('admin.ads.index')->with('success', 'Ad updated successfully.');
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        $imagePath = $ad->normalizedImagePath();

        if ($imagePath && ! str_starts_with($imagePath, 'http')) {
            Storage::disk('public')->delete($imagePath);
        }

        $ad->delete();

        return redirect()->route('admin.ads.index')->with('success', 'Ad deleted successfully.');
    }

    public function toggleStatus(Ad $ad): RedirectResponse
    {
        $ad->update(['is_active' => ! $ad->is_active]);

        return redirect()->route('admin.ads.index')->with('success', 'Ad status updated successfully.');
    }

    private function payload(StoreAdRequest|UpdateAdRequest $request): array
    {
        $data = $request->validated();

        unset($data['image']);

        $data['is_active'] = $request->boolean('is_active');

        if (! empty($data['starts_at'])) {
            $data['starts_at'] = Carbon::parse($data['starts_at'], config('app.timezone', 'UTC'))->utc();
        } else {
            $data['starts_at'] = null;
        }

        if (! empty($data['ends_at'])) {
            $data['ends_at'] = Carbon::parse($data['ends_at'], config('app.timezone', 'UTC'))->utc();
        } else {
            $data['ends_at'] = null;
        }

        // Handle nullable integer fields
        $data['timeline_position'] = isset($data['timeline_position']) && $data['timeline_position'] !== '' ? (int) $data['timeline_position'] : null;
        $data['sort_order'] = isset($data['sort_order']) && $data['sort_order'] !== '' ? (int) $data['sort_order'] : 0;

        return $data;
    }

    private function storeImage(StoreAdRequest|UpdateAdRequest $request): string
    {
        return (string) $request->file('image')->store('ads', 'public');
    }
}
