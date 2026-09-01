@extends('admin.layouts.app')

@section('title', 'Industry Director Dashboard')

@section('content')
<style>
    .kpi-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 12px;
        transition: all 0.25s ease-in-out;
        border: 1px solid rgba(0,0,0,0.06);
        cursor: pointer;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
        border-color: #0d6efd;
    }
    .text-primary-gradient {
        background: linear-gradient(45deg, #0d6efd, #0dcaf0);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
</style>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="mb-2">
                <span class="badge px-3 py-2 rounded-pill fw-bold" style="background: rgba(13, 110, 253, 0.08); color: #0d6efd; border: 1px solid rgba(13, 110, 253, 0.15); font-size: 0.85rem; letter-spacing: 0.3px;">
                    Welcome, {{ auth('admin')->user()?->name }}
                </span>
            </div>
            <h3 class="mb-0 fw-bold text-primary-gradient">{{ $industry?->name ?? 'Assigned Industry' }} Dashboard</h3>
            <div class="text-muted small mt-2">
                Includes selected industry{{ $industryCount > 1 ? ' and '.($industryCount - 1).' child industries/categories' : '' }}.
            </div>
        </div>
        @if (($assignedIndustries ?? collect())->count() > 1)
            <form method="POST" action="{{ route('admin.industry-director.switch-industry') }}" class="d-flex align-items-end gap-2">
                @csrf
                <div>
                    <label for="selected-industry-id" class="form-label small text-muted mb-1 fw-bold">Selected industry</label>
                    <select id="selected-industry-id" name="selected_industry_id" class="form-select" onchange="this.form.submit()">
                        @foreach ($assignedIndustries as $assignedIndustry)
                            <option value="{{ $assignedIndustry->id }}" @selected((string) $selectedIndustryId === (string) $assignedIndustry->id)>
                                {{ $assignedIndustry->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <noscript>
                    <button type="submit" class="btn btn-primary">Switch</button>
                </noscript>
            </form>
        @endif
    </div>

    <div class="row g-3">
        @foreach ([
            ['label' => 'Total Industry Members', 'value' => $metrics['total_industry_members'], 'color' => 'text-dark', 'route' => 'admin.users.index'],
            ['label' => 'Active Members', 'value' => $metrics['active_members'], 'color' => 'text-success', 'route' => 'admin.users.index'],
            ['label' => 'New Registrations', 'value' => $metrics['new_registrations'], 'color' => 'text-info', 'route' => 'admin.users.index'],
            ['label' => 'Total Activities', 'value' => $metrics['total_activities'], 'color' => 'text-primary', 'route' => 'admin.activities.index'],
            ['label' => 'Total Posts', 'value' => $metrics['total_posts'], 'color' => 'text-dark', 'route' => '#'],
            ['label' => 'Pending Requests Count', 'value' => $metrics['pending_requests_count'], 'color' => 'text-danger', 'route' => 'admin.circle-joining-requests.index'],
            ['label' => 'Total Circles', 'value' => $metrics['total_circles'], 'color' => 'text-dark', 'route' => 'admin.circles.index'],
            ['label' => 'Total Coins Earned', 'value' => $metrics['total_coins_earned'], 'color' => 'text-warning', 'route' => 'admin.coins.index'],
            ['label' => 'Life Impact', 'value' => $metrics['life_impact'], 'color' => 'text-info', 'route' => 'admin.life-impact.index'],
        ] as $card)
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ $card['route'] === '#' ? '#' : route($card['route']) }}" class="kpi-card p-3 d-block text-decoration-none text-reset shadow-sm h-100">
                    <p class="text-muted mb-1 small fw-bold">{{ $card['label'] }}</p>
                    <h2 class="mb-0 fw-bold {{ $card['color'] ?? 'text-dark' }}">
                        {{ number_format((float) $card['value']) }}
                    </h2>
                </a>
            </div>
        @endforeach
    </div>
@endsection
