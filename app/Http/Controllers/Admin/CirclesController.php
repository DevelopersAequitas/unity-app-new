<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\City;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CirclesController extends Controller
{
    public function index(Request $request): View
    {
        $query = Circle::query()->with(['founder', 'city']);

        $search = trim((string) $request->input('search'));
        $status = $request->input('status');
        $cityId = $request->input('city_id');

        if ($search !== '') {
            $query->where('name', 'ILIKE', "%{$search}%");
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($cityId)) {
            $query->where('city_id', $cityId);
        }

        $circles = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $statuses = Circle::query()
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->filter()
            ->sort()
            ->values();

        $cities = City::query()->orderBy('name')->get();

        return view('admin.circles.index', [
            'circles' => $circles,
            'statuses' => $statuses,
            'cities' => $cities,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'city_id' => $cityId,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $selectedCountry = (string) ($request->query('country') ?: 'India');

        $allUsers = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'display_name',
                'email',
                'company_name',
                'business_name',
                'city',
            ])
            ->orderByRaw("COALESCE(NULLIF(display_name, ''), NULLIF(first_name, ''), email) asc")
            ->get();

        $cities = City::query()
            ->when($selectedCountry !== '', function ($query) use ($selectedCountry) {
                $query->where('country', $selectedCountry);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'state', 'country']);

        return view('admin.circles.create', [
            'defaultFounder' => Auth::user(),
            'allUsers' => $allUsers,
            'types' => ['public', 'private'],
            'statuses' => ['active', 'inactive', 'draft', 'archived'],
            'meetingModes' => ['online', 'offline', 'hybrid'],
            'meetingFrequencies' => ['weekly', 'monthly', 'quarterly'],
            'circleStages' => ['Forming', 'Growing', 'Active', 'Mature'],
            'countries' => ['India', 'United Arab Emirates', 'United Kingdom', 'United States', 'Canada', 'Australia', 'Singapore'],
            'selectedCountry' => $selectedCountry,
            'cities' => $cities,
        ]);
    }
}
