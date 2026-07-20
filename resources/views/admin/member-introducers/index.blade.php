@extends('admin.layouts.app')

@section('title', 'Member Introducers')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card p-3">
    {{-- Section A: Top 10 Member Introducers --}}
    @if ($topIntroducers->isNotEmpty())
        <div class="card border mb-4 bg-white shadow-none">
            <div class="card-header bg-light py-2">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-warning me-2"></i>Top 10 Member Introducers</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-sm mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="width: 60px; padding-left: 15px;">Rank</th>
                                <th>Peer Name</th>
                                <th>Company Name</th>
                                <th>City</th>
                                <th>Introduced By</th>
                                <th class="text-center">Members Introduced</th>
                                @if ($canEditUsers)
                                    <th class="text-end" style="padding-right: 15px;">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach ($topIntroducers as $index => $introducer)
                                @php
                                    $introducerName = $introducer->name ?? trim((($introducer->first_name ?? '') . ' ' . ($introducer->last_name ?? '')));
                                    $introducerAvatar = $introducer->profile_photo_url ?? ($introducer->profile_photo_file_id ? url('/api/v1/files/' . $introducer->profile_photo_file_id) : null);
                                    $introducerGradientIndex = abs(crc32((string) $introducer->id)) % 5;

                                    // Parse city
                                    $introducerCityModel = $introducer->getRelation('city') ?? $introducer->cityRelation ?? null;
                                    $introducerRawCity = $introducerCityModel->name ?? $introducer->city ?? '';
                                    if (is_string($introducerRawCity)) {
                                        $introducerRawCity = trim($introducerRawCity);
                                        if (str_starts_with($introducerRawCity, '{')) {
                                            $decodedCity = json_decode($introducerRawCity, true);
                                            if (is_array($decodedCity)) {
                                                $introducerCityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $introducerRawCity;
                                            } elseif (preg_match('/name:\s*([^,}]+)/', $introducerRawCity, $matches)) {
                                                $introducerCityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                            } else {
                                                $introducerCityName = $introducerRawCity;
                                            }
                                        } else {
                                            $introducerCityName = $introducerRawCity;
                                        }
                                    } elseif (is_array($introducerRawCity)) {
                                        $introducerCityName = $introducerRawCity['name'] ?? $introducerRawCity['label'] ?? '';
                                    } elseif (is_object($introducerRawCity)) {
                                        $introducerCityName = $introducerRawCity->name ?? $introducerRawCity->label ?? '';
                                    } else {
                                        $introducerCityName = $introducerRawCity;
                                    }
                                    
                                    if (in_array(strtolower(trim((string)$introducerCityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                                        $introducerCityName = null;
                                    }
                                    
                                    // Parse company
                                    $introducerCompany = $introducer->company_name ?? $introducer->company ?? $introducer->business_name ?? '';
                                    if (in_array(strtolower(trim((string)$introducerCompany)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                                        $introducerCompany = null;
                                    }
                                @endphp
                                <tr>
                                    <td style="padding-left: 15px;"><strong>{{ $index + 1 }}</strong></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="peer-avatar-wrapper" style="width: 32px; height: 32px;">
                                                @if ($introducerAvatar)
                                                    <img src="{{ $introducerAvatar }}" alt="{{ $introducerName }}" class="peer-avatar-image" style="width: 32px; height: 32px;">
                                                @else
                                                    <div class="peer-avatar-placeholder bg-gradient-peer-{{ $introducerGradientIndex }}" style="width: 32px; height: 32px; font-size: 0.8rem; line-height: 32px;">
                                                        {{ strtoupper(substr($introducerName !== '' ? $introducerName : 'U', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="d-flex flex-column">
                                                <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.92rem;">{{ $introducerName !== '' ? $introducerName : '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($introducerCompany)
                                            <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                                <i class="bi bi-building text-muted small"></i>{{ $introducerCompany }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($introducerCityName)
                                            <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                                <i class="bi bi-geo-alt text-muted small"></i>{{ $introducerCityName }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($introducer->introducedBy)
                                            @php
                                                $parentIntroducer = $introducer->introducedBy;
                                                $parentIntroducerName = $parentIntroducer->name ?? trim((($parentIntroducer->first_name ?? '') . ' ' . ($parentIntroducer->last_name ?? '')));
                                                $parentIntroducerAvatar = $parentIntroducer->profile_photo_url ?? ($parentIntroducer->profile_photo_file_id ? url('/api/v1/files/' . $parentIntroducer->profile_photo_file_id) : null);
                                                $parentIntroducerGradientIndex = abs(crc32((string) $parentIntroducer->id)) % 5;
                                            @endphp
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="peer-avatar-wrapper" style="width: 28px; height: 28px; min-width: 28px;">
                                                    @if ($parentIntroducerAvatar)
                                                        <img src="{{ $parentIntroducerAvatar }}" alt="{{ $parentIntroducerName }}" class="peer-avatar-image" style="width: 28px; height: 28px;">
                                                    @else
                                                        <div class="peer-avatar-placeholder bg-gradient-peer-{{ $parentIntroducerGradientIndex }}" style="width: 28px; height: 28px; font-size: 0.75rem; line-height: 28px;">
                                                            {{ strtoupper(substr($parentIntroducerName !== '' ? $parentIntroducerName : 'U', 0, 1)) }}
                                                        </div>
                                                    @endif
                                                </div>
                                                @if ($canEditUsers)
                                                    <a href="{{ route('admin.users.edit', $parentIntroducer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none text-nowrap" style="font-size: 0.88rem;">
                                                        {{ $parentIntroducerName }}
                                                    </a>
                                                @else
                                                    <a href="{{ route('admin.users.show', $parentIntroducer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none text-nowrap" style="font-size: 0.88rem;">
                                                        {{ $parentIntroducerName }}
                                                    </a>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $introducedCount = (int) $introducer->introduced_members_count;
                                        @endphp
                                        @if ($introducedCount > 0)
                                            @if ($canEditUsers)
                                                <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none">
                                                    <span class="badge bg-light text-dark border">{{ $introducedCount }}</span>
                                                </a>
                                            @else
                                                <a href="{{ route('admin.users.show', $introducer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none">
                                                    <span class="badge bg-light text-dark border">{{ $introducedCount }}</span>
                                                </a>
                                            @endif
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    @if ($canEditUsers)
                                        <td class="text-end" style="padding-right: 15px;">
                                            <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size: 0.75rem;">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Section B: All Member Introducers --}}
    <div class="card border bg-white shadow-none">
        <div class="card-header bg-light py-2">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-people-fill me-2"></i>All Member Introducers</h6>
        </div>
        <div class="card-body p-3">
            {{-- Filter Form --}}
            <form id="introducersFiltersForm" method="GET" class="border rounded-3 p-3 mb-3 bg-white">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted" for="peerSearch">Search</label>
                        <input type="text" id="peerSearch" name="q" class="form-control form-control-sm" placeholder="Search by name, email, company, city, designation..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted" for="membershipFilter">Membership Status</label>
                        <select id="membershipFilter" name="membership_status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach ($membershipStatuses as $status)
                                <option value="{{ $status }}" @selected(($filters['membership_status'] ?? '') === $status)>{{ $membershipStatusLabels[$status] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted" for="startDateFilter">Introduced From</label>
                        <input id="startDateFilter" type="date" name="start_date" class="form-control form-control-sm" value="{{ $filters['start_date'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted" for="endDateFilter">Introduced To</label>
                        <input id="endDateFilter" type="date" name="end_date" class="form-control form-control-sm" value="{{ $filters['end_date'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-1 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle table-sm mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>
                                <a href="{{ route('admin.member-introducers.index', array_merge(request()->query(), ['sort' => 'display_name', 'dir' => ($filters['sort'] ?? '') === 'display_name' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                    Peer Name
                                    @if (($filters['sort'] ?? '') === 'display_name')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }}-short fs-6"></i>
                                    @endif
                                </a>
                            </th>
                            <th>Company Name</th>
                            <th>City</th>
                            <th>Introduced By</th>
                            <th class="text-center">
                                <a href="{{ route('admin.member-introducers.index', array_merge(request()->query(), ['sort' => 'introduced_members_count', 'dir' => ($filters['sort'] ?? '') === 'introduced_members_count' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                    Members Introduced
                                    @if (($filters['sort'] ?? '') === 'introduced_members_count')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }}-short fs-6"></i>
                                    @endif
                                </a>
                            </th>
                            @if ($canEditUsers)
                                <th class="text-end" style="padding-right: 15px;">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse ($introducers as $introducer)
                            @php
                                $introducerName = $introducer->name ?? trim((($introducer->first_name ?? '') . ' ' . ($introducer->last_name ?? '')));
                                $introducerAvatar = $introducer->profile_photo_url ?? ($introducer->profile_photo_file_id ? url('/api/v1/files/' . $introducer->profile_photo_file_id) : null);
                                $introducerGradientIndex = abs(crc32((string) $introducer->id)) % 5;

                                // Parse city
                                $introducerCityModel = $introducer->getRelation('city') ?? $introducer->cityRelation ?? null;
                                $introducerRawCity = $introducerCityModel->name ?? $introducer->city ?? '';
                                if (is_string($introducerRawCity)) {
                                    $introducerRawCity = trim($introducerRawCity);
                                    if (str_starts_with($introducerRawCity, '{')) {
                                        $decodedCity = json_decode($introducerRawCity, true);
                                        if (is_array($decodedCity)) {
                                            $introducerCityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $introducerRawCity;
                                        } elseif (preg_match('/name:\s*([^,}]+)/', $introducerRawCity, $matches)) {
                                            $introducerCityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                        } else {
                                            $introducerCityName = $introducerRawCity;
                                        }
                                    } else {
                                        $introducerCityName = $introducerRawCity;
                                    }
                                } elseif (is_array($introducerRawCity)) {
                                    $introducerCityName = $introducerRawCity['name'] ?? $introducerRawCity['label'] ?? '';
                                } elseif (is_object($introducerRawCity)) {
                                    $introducerCityName = $introducerRawCity->name ?? $introducerRawCity->label ?? '';
                                } else {
                                    $introducerCityName = $introducerRawCity;
                                }
                                
                                if (in_array(strtolower(trim((string)$introducerCityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                                    $introducerCityName = null;
                                }
                                
                                // Parse company
                                $introducerCompany = $introducer->company_name ?? $introducer->company ?? $introducer->business_name ?? '';
                                if (in_array(strtolower(trim((string)$introducerCompany)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                                    $introducerCompany = null;
                                }
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="peer-avatar-wrapper" style="width: 32px; height: 32px;">
                                            @if ($introducerAvatar)
                                                <img src="{{ $introducerAvatar }}" alt="{{ $introducerName }}" class="peer-avatar-image" style="width: 32px; height: 32px;">
                                            @else
                                                <div class="peer-avatar-placeholder bg-gradient-peer-{{ $introducerGradientIndex }}" style="width: 32px; height: 32px; font-size: 0.8rem; line-height: 32px;">
                                                    {{ strtoupper(substr($introducerName !== '' ? $introducerName : 'U', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="d-flex flex-column">
                                            <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.92rem;">{{ $introducerName !== '' ? $introducerName : '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($introducerCompany)
                                        <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                            <i class="bi bi-building text-muted small"></i>{{ $introducerCompany }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($introducerCityName)
                                        <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                            <i class="bi bi-geo-alt text-muted small"></i>{{ $introducerCityName }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($introducer->introducedBy)
                                        @php
                                            $parentIntroducer = $introducer->introducedBy;
                                            $parentIntroducerName = $parentIntroducer->name ?? trim((($parentIntroducer->first_name ?? '') . ' ' . ($parentIntroducer->last_name ?? '')));
                                            $parentIntroducerAvatar = $parentIntroducer->profile_photo_url ?? ($parentIntroducer->profile_photo_file_id ? url('/api/v1/files/' . $parentIntroducer->profile_photo_file_id) : null);
                                            $parentIntroducerGradientIndex = abs(crc32((string) $parentIntroducer->id)) % 5;
                                        @endphp
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="peer-avatar-wrapper" style="width: 28px; height: 28px; min-width: 28px;">
                                                @if ($parentIntroducerAvatar)
                                                    <img src="{{ $parentIntroducerAvatar }}" alt="{{ $parentIntroducerName }}" class="peer-avatar-image" style="width: 28px; height: 28px;">
                                                @else
                                                    <div class="peer-avatar-placeholder bg-gradient-peer-{{ $parentIntroducerGradientIndex }}" style="width: 28px; height: 28px; font-size: 0.75rem; line-height: 28px;">
                                                        {{ strtoupper(substr($parentIntroducerName !== '' ? $parentIntroducerName : 'U', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($canEditUsers)
                                                <a href="{{ route('admin.users.edit', $parentIntroducer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none text-nowrap" style="font-size: 0.88rem;">
                                                    {{ $parentIntroducerName }}
                                                </a>
                                            @else
                                                <a href="{{ route('admin.users.show', $parentIntroducer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none text-nowrap" style="font-size: 0.88rem;">
                                                    {{ $parentIntroducerName }}
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php
                                        $introducedCount = (int) $introducer->introduced_members_count;
                                    @endphp
                                    @if ($introducedCount > 0)
                                        @if ($canEditUsers)
                                            <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none">
                                                <span class="badge bg-light text-dark border">{{ $introducedCount }}</span>
                                            </a>
                                        @else
                                            <a href="{{ route('admin.users.show', $introducer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none">
                                                <span class="badge bg-light text-dark border">{{ $introducedCount }}</span>
                                            </a>
                                        @endif
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                @if ($canEditUsers)
                                    <td class="text-end" style="padding-right: 15px;">
                                        <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size: 0.75rem;">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No introducers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Controls --}}
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <div>
                    {{ $introducers->links() }}
                </div>
                <div class="small text-muted">
                    @if($introducers->total() > 0)
                        Showing {{ $introducers->firstItem() }}-{{ $introducers->lastItem() }} of {{ $introducers->total() }} records
                    @else
                        No records
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
