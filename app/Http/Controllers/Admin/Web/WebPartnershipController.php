<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebPartnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebPartnershipController extends Controller
{
    public function index(Request $request): View
    {
        $query = WebPartnership::query();

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sq) use ($q): void {
                $sq->where('title', 'ilike', "%{$q}%")
                    ->orWhere('company_a', 'ilike', "%{$q}%")
                    ->orWhere('company_b', 'ilike', "%{$q}%")
                    ->orWhere('sector', 'ilike', "%{$q}%")
                    ->orWhere('code', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'All') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('sector') && $request->input('sector') !== 'All') {
            $query->where('sector', $request->input('sector'));
        }

        $partnerships = $query->latest()->paginate(15)->withQueryString();

        $sectors = WebPartnership::select('sector')->distinct()->whereNotNull('sector')->pluck('sector');
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
            'title' => 'required|string|max:255',
            'company_a' => 'required|string|max:255',
            'company_b' => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'value' => 'nullable|string|max:100',
            'numeric_value' => 'nullable|numeric',
            'status' => 'required|string',
            'stage' => 'required|string',
            'description' => 'nullable|string',
            'lead_manager' => 'nullable|string|max:255',
        ]);

        WebPartnership::create($validated);

        return redirect()->route('admin.web.partnerships.index')->with('success', 'Partnership created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $partnership = WebPartnership::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'company_a' => 'required|string|max:255',
            'company_b' => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'value' => 'nullable|string|max:100',
            'status' => 'required|string',
            'stage' => 'required|string',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string',
            'lead_manager' => 'nullable|string|max:255',
        ]);

        $partnership->update($validated);

        return redirect()->route('admin.web.partnerships.index')->with('success', 'Partnership updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $partnership = WebPartnership::findOrFail($id);
        $partnership->delete();

        return redirect()->route('admin.web.partnerships.index')->with('success', 'Partnership deleted.');
    }
}
