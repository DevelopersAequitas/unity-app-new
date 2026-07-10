@extends('admin.layouts.app')

@section('title', 'Post Report Details')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        $postOwner = $report->post?->user;
        $postOwnerName = $postOwner?->display_name ?: trim(($postOwner?->first_name ?? '') . ' ' . ($postOwner?->last_name ?? ''));
        $reporterName = $report->reporter?->display_name ?: trim(($report->reporter?->first_name ?? '') . ' ' . ($report->reporter?->last_name ?? ''));
        $isPostActive = $report->post ? ! $report->post->is_deleted && ! $report->post->deleted_at : false;
        $mediaUrl = $report->post ? data_get($report->post->media, '0.url') : null;
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1 fw-bold">Post Report Details</h2>
            <div class="text-muted small">Report ID: {{ $report->id }}</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('admin.post-reports.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Report Info</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Reported At</dt>
                        <dd class="col-sm-8">{{ $report->created_at?->format('Y-m-d H:i') }}</dd>
                        <dt class="col-sm-4">Reason</dt>
                        <dd class="col-sm-8">{{ $report->reasonOption?->title ?? $report->reason ?? '—' }}</dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">{{ ucfirst($report->status) }}</dd>
                        <dt class="col-sm-4">Reporter</dt>
                        <dd class="col-sm-8">{{ $reporterName !== '' ? $reporterName : 'Unknown' }}</dd>
                        <dt class="col-sm-4">Note</dt>
                        <dd class="col-sm-8">{{ $report->note ?: '—' }}</dd>
                        <dt class="col-sm-4">Reviewed By</dt>
                        <dd class="col-sm-8">{{ $report->reviewer?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">Reviewed At</dt>
                        <dd class="col-sm-8">{{ $report->reviewed_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        <dt class="col-sm-4">Admin Note</dt>
                        <dd class="col-sm-8">{{ $report->admin_note ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Post Preview</div>
                <div class="card-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-4">Post ID</dt>
                        <dd class="col-sm-8">{{ $report->post_id }}</dd>
                        <dt class="col-sm-4">Post Owner</dt>
                        <dd class="col-sm-8">{{ $postOwnerName !== '' ? $postOwnerName : 'Unknown' }}</dd>
                        <dt class="col-sm-4">Active?</dt>
                        <dd class="col-sm-8">{{ $isPostActive ? 'Yes' : 'No' }}</dd>
                    </dl>
                    <div class="mb-3">
                        <div class="text-muted small">Content</div>
                        <div class="border rounded p-2 bg-light">{{ $report->post?->content_text ?: '—' }}</div>
                    </div>
                    @if ($mediaUrl)
                        <div>
                            <div class="text-muted small">Media Preview</div>
                            <img src="{{ $mediaUrl }}" alt="Post media" class="img-fluid rounded border">
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">Moderation Actions</div>
        <div class="card-body d-flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.post-reports.mark-reviewed', $report) }}" class="d-flex align-items-center gap-2">
                @csrf
                <button type="submit" class="btn btn-outline-primary">Mark Reviewed</button>
            </form>
            <form method="POST" action="{{ route('admin.post-reports.dismiss', $report) }}" class="d-flex align-items-center gap-2">
                @csrf
                <input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Dismiss note (optional)">
                <button type="submit" class="btn btn-outline-secondary">Dismiss</button>
            </form>
            <form method="POST" action="{{ route('admin.post-reports.resolve', $report) }}" class="d-flex align-items-center gap-2">
                @csrf
                <input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Resolve note (optional)">
                <button type="submit" class="btn btn-outline-success">Resolve</button>
            </form>
            @if ($report->post)
                @if ($isPostActive)
                    <form method="POST" action="{{ route('admin.posts.deactivate', $report->post) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Deactivate Post</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.posts.restore', $report->post) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-success">Restore / Activate Again</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">All Reports for This Post</div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle text-nowrap">
                <thead class="table-light">
                    <tr>
                        <th>Reported At</th>
                        <th>Reporter</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($postReports as $postReport)
                        @php
                            $postReporterName = $postReport->reporter?->display_name ?: trim(($postReport->reporter?->first_name ?? '') . ' ' . ($postReport->reporter?->last_name ?? ''));
                        @endphp
                        <tr>
                            <td>{{ $postReport->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $postReporterName !== '' ? $postReporterName : 'Unknown' }}</td>
                            <td>{{ ucfirst($postReport->reason) }}</td>
                            <td>{{ ucfirst($postReport->status) }}</td>
                            <td>{{ $postReport->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No reports for this post.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
