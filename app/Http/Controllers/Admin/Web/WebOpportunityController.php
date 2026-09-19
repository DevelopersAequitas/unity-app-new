<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebOpportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebOpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $query = WebOpportunity::query();

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sq) use ($q): void {
                $sq->where('title', 'ilike', "%{$q}%")
                    ->orWhere('sector', 'ilike', "%{$q}%")
                    ->orWhere('proposer_company', 'ilike', "%{$q}%")
                    ->orWhere('code', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'All') {
            $query->where('status', $request->input('status'));
        }

        $opportunities = $query->latest()->paginate(15)->withQueryString();

        return view('admin.web.opportunities.index', [
            'opportunities' => $opportunities,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
            'value' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'status' => 'required|string',
            'description' => 'nullable|string',
            'proposer_name' => 'nullable|string|max:255',
            'proposer_company' => 'nullable|string|max:255',
            'deadline' => 'nullable|date',
        ]);

        WebOpportunity::create($validated);

        return redirect()->route('admin.web.opportunities.index')->with('success', 'Opportunity created successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $opportunity = WebOpportunity::findOrFail($id);
        $opportunity->delete();

        return redirect()->route('admin.web.opportunities.index')->with('success', 'Opportunity deleted.');
    }
}
