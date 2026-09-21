<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WebCompanyController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('web_companies')) {
            return view('admin.web.companies.index', [
                'companies' => collect(),
            ]);
        }

        $query = WebCompany::query();

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sq) use ($q): void {
                if (Schema::hasColumn('web_companies', 'name')) {
                    $sq->orWhere('name', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_companies', 'industry')) {
                    $sq->orWhere('industry', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_companies', 'sector')) {
                    $sq->orWhere('sector', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_companies', 'city')) {
                    $sq->orWhere('city', 'ilike', "%{$q}%");
                }
                if (Schema::hasColumn('web_companies', 'headquarters')) {
                    $sq->orWhere('headquarters', 'ilike', "%{$q}%");
                }
            });
        }

        $companies = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

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
            'headquarters' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'website_url' => 'nullable|string|max:255',
            'employee_count' => 'nullable|string|max:100',
            'turnover' => 'nullable|string|max:100',
            'revenue_range' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'bio' => 'nullable|string',
            'is_verified' => 'nullable|boolean',
        ]);

        $data = [];
        foreach ($validated as $k => $v) {
            if ($v !== null && Schema::hasColumn('web_companies', $k)) {
                $data[$k] = $v;
            }
        }

        if (Schema::hasColumn('web_companies', 'is_verified')) {
            $data['is_verified'] = $request->has('is_verified');
        }

        WebCompany::create($data);

        return redirect()->route('admin.web.companies.index')->with('success', 'Company added successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $company = WebCompany::findOrFail($id);
        $company->delete();

        return redirect()->route('admin.web.companies.index')->with('success', 'Company deleted.');
    }
}
