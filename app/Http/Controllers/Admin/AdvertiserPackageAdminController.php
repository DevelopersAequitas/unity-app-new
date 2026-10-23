<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertiserPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdvertiserPackageAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = AdvertiserPackage::ordered();

        if ($request->filled('status')) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('description', 'ILIKE', "%{$q}%");
            });
        }

        $packages = $query->paginate(20)->appends($request->query());

        $counts = [
            'total' => AdvertiserPackage::count(),
            'active' => AdvertiserPackage::where('is_active', true)->count(),
            'inactive' => AdvertiserPackage::where('is_active', false)->count(),
        ];

        return view('admin.advertiser.packages.index', compact('packages', 'counts'));
    }

    public function create(): View
    {
        return view('admin.advertiser.packages.create', [
            'package' => new AdvertiserPackage([
                'duration_months' => 1,
                'price' => 5000,
                'currency' => 'INR',
                'max_banner_assets' => 5,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'duration_months' => ['required', 'integer', 'in:1,6,12'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'features_text' => ['nullable', 'string'],
            'banner_placement' => ['nullable', 'string', 'max:100'],
            'max_banner_assets' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;
        while (AdvertiserPackage::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-" . $counter;
            $counter++;
        }

        $features = [];
        if (!empty($validated['features_text'])) {
            $features = array_values(array_filter(array_map('trim', explode("\n", $validated['features_text']))));
        }

        AdvertiserPackage::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'duration_months' => (int) $validated['duration_months'],
            'price' => (float) $validated['price'],
            'currency' => strtoupper((string) ($validated['currency'] ?? 'INR')),
            'features' => $features,
            'banner_placement' => $validated['banner_placement'] ?? 'brand_partner_listing',
            'max_banner_assets' => (int) ($validated['max_banner_assets'] ?? 5),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('admin.advertiser.packages.index')->with('success', 'Advertiser package created successfully.');
    }

    public function edit(AdvertiserPackage $package): View
    {
        return view('admin.advertiser.packages.edit', compact('package'));
    }

    public function update(Request $request, AdvertiserPackage $package): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'duration_months' => ['required', 'integer', 'in:1,6,12'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'features_text' => ['nullable', 'string'],
            'banner_placement' => ['nullable', 'string', 'max:100'],
            'max_banner_assets' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $features = [];
        if (!empty($validated['features_text'])) {
            $features = array_values(array_filter(array_map('trim', explode("\n", $validated['features_text']))));
        }

        $package->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_months' => (int) $validated['duration_months'],
            'price' => (float) $validated['price'],
            'currency' => strtoupper((string) ($validated['currency'] ?? 'INR')),
            'features' => $features,
            'banner_placement' => $validated['banner_placement'] ?? 'brand_partner_listing',
            'max_banner_assets' => (int) ($validated['max_banner_assets'] ?? 5),
            'is_active' => $request->boolean('is_active', false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('admin.advertiser.packages.index')->with('success', 'Advertiser package updated successfully.');
    }

    public function toggleStatus(AdvertiserPackage $package): RedirectResponse
    {
        $package->update(['is_active' => !$package->is_active]);

        return redirect()->back()->with('success', 'Package status updated successfully.');
    }
}
