<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $query = WebCompany::query();

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sq) use ($q): void {
                $sq->where('name', 'ilike', "%{$q}%")
                    ->orWhere('industry', 'ilike', "%{$q}%")
                    ->orWhere('sector', 'ilike', "%{$q}%")
                    ->orWhere('city', 'ilike', "%{$q}%");
            });
        }

        $companies = $query->latest()->paginate(15)->withQueryString();

        return view('admin.web.companies.index', [
            'companies' => $companies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'sector' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'employee_count' => 'nullable|string|max:100',
            'turnover' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_verified' => 'nullable|boolean',
        ]);

        $validated['is_verified'] = $request->has('is_verified');

        WebCompany::create($validated);

        return redirect()->route('admin.web.companies.index')->with('success', 'Company added successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $company = WebCompany::findOrFail($id);
        $company->delete();

        return redirect()->route('admin.web.companies.index')->with('success', 'Company deleted.');
    }
}
