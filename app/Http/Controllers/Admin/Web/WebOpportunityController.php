<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebOpportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WebOpportunityController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('web_opportunities')) {
            return view('admin.web.opportunities.index', [
                'opportunities' => collect(),
            ]);
        }

        $query = WebOpportunity::query();

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sq) use ($q): void {
                if (Schema::hasColumn('web_opportunities', 'title')) {
                    $sq->orWhere('title', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_opportunities', 'sector')) {
                    $sq->orWhere('sector', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_opportunities', 'category')) {
                    $sq->orWhere('category', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_opportunities', 'proposer_company')) {
                    $sq->orWhere('proposer_company', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_opportunities', 'code')) {
                    $sq->orWhere('code', 'ilike', "%{$q}%");
                }
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'All') {
            if (Schema::hasColumn('web_opportunities', 'status')) {
                $query->where('status', $request->input('status'));
            }
        }

        $opportunities = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return view('admin.web.opportunities.index', [
            'opportunities' => $opportunities,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'value' => 'nullable|string|max:100',
            'deal_size' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'status' => 'required|string',
            'description' => 'nullable|string',
            'summary' => 'nullable|string',
            'proposer_name' => 'nullable|string|max:255',
            'proposer_company' => 'nullable|string|max:255',
            'deadline' => 'nullable|date',
        ]);

        $data = [];
        foreach ($validated as $k => $v) {
            if ($v !== null && Schema::hasColumn('web_opportunities', $k)) {
                $data[$k] = $v;
            }
        }

        if (Schema::hasColumn('web_opportunities', 'slug') && empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($validated['title']).'-'.random_int(100, 999);
        }

        WebOpportunity::create($data);

        return redirect()->route('admin.web.opportunities.index')->with('success', 'Opportunity created successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $opportunity = WebOpportunity::findOrFail($id);
        $opportunity->delete();

        return redirect()->route('admin.web.opportunities.index')->with('success', 'Opportunity deleted.');
    }
}
