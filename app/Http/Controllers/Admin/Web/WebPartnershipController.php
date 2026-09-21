<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebPartnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WebPartnershipController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('web_partnerships')) {
            return view('admin.web.partnerships.index', [
                'partnerships' => collect(),
                'sectors' => collect(),
                'statuses' => ['Active', 'Under Review', 'Negotiation', 'Completed', 'Pending Due Diligence'],
            ]);
        }

        $query = WebPartnership::query();

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sq) use ($q): void {
                if (Schema::hasColumn('web_partnerships', 'title')) {
                    $sq->orWhere('title', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_partnerships', 'company_name')) {
                    $sq->orWhere('company_name', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_partnerships', 'company_a')) {
                    $sq->orWhere('company_a', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_partnerships', 'company_b')) {
                    $sq->orWhere('company_b', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_partnerships', 'sector')) {
                    $sq->orWhere('sector', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_partnerships', 'industry')) {
                    $sq->orWhere('industry', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_partnerships', 'code')) {
                    $sq->orWhere('code', 'ilike', "%{$q}%");
                }
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'All') {
            if (Schema::hasColumn('web_partnerships', 'status')) {
                $query->where('status', $request->input('status'));
            }
        }

        $sectorCol = Schema::hasColumn('web_partnerships', 'sector') ? 'sector' : (Schema::hasColumn('web_partnerships', 'industry') ? 'industry' : null);

        if ($request->filled('sector') && $request->input('sector') !== 'All' && $sectorCol) {
            $query->where($sectorCol, $request->input('sector'));
        }

        $partnerships = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $sectors = $sectorCol
            ? WebPartnership::select($sectorCol)->distinct()->whereNotNull($sectorCol)->pluck($sectorCol)
            : collect(['Supply Chain', 'CleanTech', 'AI & SaaS', 'Manufacturing', 'Finance']);

        $statuses = ['Active', 'Under Review', 'Negotiation', 'Completed', 'Pending Due Diligence'];

        return view('admin.web.partnerships.index', [
            'partnerships' => $partnerships,
            'sectors' => $sectors,
            'statuses' => $statuses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'company_a' => 'nullable|string|max:255',
            'company_b' => 'nullable|string|max:255',
            'sector' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'partnership_tier' => 'nullable|string|max:100',
            'route' => 'nullable|string|max:255',
            'value' => 'nullable|string|max:100',
            'numeric_value' => 'nullable|numeric',
            'status' => 'required|string',
            'stage' => 'nullable|string',
            'description' => 'nullable|string',
            'lead_manager' => 'nullable|string|max:255',
        ]);

        $data = [];
        foreach ($validated as $k => $v) {
            if ($v !== null && Schema::hasColumn('web_partnerships', $k)) {
                $data[$k] = $v;
            }
        }

        if (Schema::hasColumn('web_partnerships', 'company_name') && empty($data['company_name'])) {
            $data['company_name'] = $validated['title'] ?? $validated['company_a'] ?? 'Enterprise Partner';
        }

        if (Schema::hasColumn('web_partnerships', 'title') && empty($data['title'])) {
            $data['title'] = $validated['company_name'] ?? 'Strategic Partnership';
        }

        if (Schema::hasColumn('web_partnerships', 'company_a') && empty($data['company_a'])) {
            $data['company_a'] = $data['company_name'] ?? 'Partner A';
        }

        if (Schema::hasColumn('web_partnerships', 'company_b') && empty($data['company_b'])) {
            $data['company_b'] = 'Peers Global Network';
        }

        WebPartnership::create($data);

        return redirect()->route('admin.web.partnerships.index')->with('success', 'Partnership created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $partnership = WebPartnership::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'company_a' => 'nullable|string|max:255',
            'company_b' => 'nullable|string|max:255',
            'sector' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'value' => 'nullable|string|max:100',
            'status' => 'required|string',
            'stage' => 'nullable|string',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string',
            'lead_manager' => 'nullable|string|max:255',
        ]);

        $data = [];
        foreach ($validated as $k => $v) {
            if ($v !== null && Schema::hasColumn('web_partnerships', $k)) {
                $data[$k] = $v;
            }
        }

        $partnership->update($data);

        return redirect()->route('admin.web.partnerships.index')->with('success', 'Partnership updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $partnership = WebPartnership::findOrFail($id);
        $partnership->delete();

        return redirect()->route('admin.web.partnerships.index')->with('success', 'Partnership deleted.');
    }
}
