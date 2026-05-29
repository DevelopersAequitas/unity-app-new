@extends('admin.layouts.app')

@section('title', 'Testimonials')

@section('content')
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
                    <a href="{{ route('admin.activities.testimonials', $member) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>To Peer</th>
                        <th>Content</th>
                        <th>Attachment</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $testimonial)
                        @php
                            $mediaUrls = \App\Support\MediaFileUrl::all($testimonial->media ?? null);
                            $hasAttachment = count(\App\Support\MediaFileUrl::normalize($testimonial->media ?? null)) > 0;
                        @endphp
                        <tr>
                            <td>
                                <div>{{ $testimonial->toUser->display_name ?? trim(($testimonial->toUser->first_name ?? '') . ' ' . ($testimonial->toUser->last_name ?? '')) ?: '—' }}</div>
                                <div class="text-muted small">{{ $testimonial->toUser->email ?? '—' }}</div>
                            </td>
                            <td class="text-muted">{{ $testimonial->content ?? '—' }}</td>
                            <td>
                                @if ($hasAttachment)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#memberTestimonialsMediaViewerModal" data-media-modal="memberTestimonialsMediaViewerModal" data-media-source="member-testimonial-media-json-{{ $testimonial->id }}">View</button>
                                    <script type="application/json" id="member-testimonial-media-json-{{ $testimonial->id }}">{{ e(json_encode($mediaUrls)) }}</script>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ optional($testimonial->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No testimonials found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $items->links() }}
    </div>

    @include('admin.components.media-viewer-modal', ['modalId' => 'memberTestimonialsMediaViewerModal'])
@endsection
