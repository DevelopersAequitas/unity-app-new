@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Membership Email Settings</h1>
            <p class="text-muted mb-0">Configure uploaded file IDs to attach to membership emails. Upload files through <code>/api/v1/files/upload</code>, then paste the returned IDs below.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-semibold">Membership Email Attachments</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.membership-email-settings.update') }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Uploaded File IDs</label>
                    <textarea name="file_ids" rows="6" class="form-control @error('file_ids') is-invalid @enderror" placeholder="One file UUID per line">{{ old('file_ids', $fileIds) }}</textarea>
                    @error('file_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Add, remove, or replace attachment IDs. Only file IDs are stored; no local paths or public URLs are accepted.</div>
                </div>
                <button class="btn btn-primary">Save Attachments</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Active Attachments</div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>File ID</th><th>Stored Key</th><th>MIME</th><th>Size</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($attachments as $attachment)
                        <tr>
                            <td><code>{{ $attachment->file_id }}</code></td>
                            <td>{{ $attachment->s3_key }}</td>
                            <td>{{ $attachment->mime_type ?: '—' }}</td>
                            <td>{{ $attachment->size_bytes ?: '—' }}</td>
                            <td>{{ $attachment->is_orphaned ? 'Orphaned' : 'Active' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No membership email attachments configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
