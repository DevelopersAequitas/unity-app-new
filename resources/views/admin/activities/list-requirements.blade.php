@extends('admin.layouts.app')

@section('title', 'Requirements')

@section('content')
    @php

        $decodeFilter = function ($value): array {
            if (is_array($value)) {
                return $value;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }

            return [];
        };
    @endphp

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('admin.activities.index') }}" class="btn btn-outline-secondary">Back to Activities</a>
    </div>

    <div class="card shadow-sm">
        <div class="border-bottom p-3">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label small text-muted mb-1">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('admin.activities.requirements', $member) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Peer Name</th>
                        <th>Subject</th>
                        <th>Description</th>
                        <th>Region</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Attachment</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $requirement)
                        @php
                            $mediaUrls = \App\Support\MediaFileUrl::all($requirement->media ?? null);
                            $hasAttachment = count(\App\Support\MediaFileUrl::normalize($requirement->media ?? null)) > 0;
                            $regionFilter = $decodeFilter($requirement->region_filter ?? null);
                            $categoryFilter = $decodeFilter($requirement->category_filter ?? null);
                            $regionLabel = $regionFilter['region_label'] ?? $regionFilter['region_name'] ?? $regionFilter['city_name'] ?? null;
                            $category = $categoryFilter['category'] ?? null;
                        @endphp
                        <tr>
                            <td>
                                @if ($requirement->user)
                                    <div class="fw-semibold">{{ $requirement->user->display_name ?? trim($requirement->user->first_name . ' ' . $requirement->user->last_name) }}</div>
                                    <div class="text-muted small">{{ $requirement->user->email ?? '—' }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $requirement->subject ?? '—' }}</td>
                            <td class="text-muted">{{ $requirement->description ?? '—' }}</td>
                            <td>{{ $regionLabel ?: '—' }}</td>
                            <td>{{ $category ?? '—' }}</td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary text-uppercase">{{ $requirement->status ?? 'open' }}</span>
                            </td>
                            <td>
                                @if ($hasAttachment)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#memberRequirementsMediaViewerModal" data-media-modal="memberRequirementsMediaViewerModal" data-media-source="member-requirement-media-json-{{ $requirement->id }}">View</button>
                                    <script type="application/json" id="member-requirement-media-json-{{ $requirement->id }}">{{ e(json_encode($mediaUrls)) }}</script>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ optional($requirement->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No requirements found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $items->links() }}
    </div>

    @include('admin.components.media-viewer-modal', ['modalId' => 'memberRequirementsMediaViewerModal'])
@endsection
