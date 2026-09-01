@extends('admin.layouts.app')

@section('title', $resource['menu_label'].' Details')

@section('content')
    @php
        $statusBadgeClass = static function (string $status): string {
            return match (strtolower(trim($status))) {
                'approved', 'active', 'completed' => 'bg-success-subtle text-success border border-success-subtle',
                'rejected', 'failed', 'inactive' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'pending', 'in_review' => 'bg-warning-subtle text-warning border border-warning-subtle',
                'new' => 'bg-info-subtle text-info border border-info-subtle',
                default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            };
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0 fw-bold">{{ $resource['menu_label'] }} Details</h1>
        <div class="d-flex gap-2 align-items-center">
            @if (in_array($resource['key'], ['leadership_certification', 'entrepreneur_certification'], true))
                @php
                    $certSubmission = \App\Models\CertificationSubmission::find($item->id);
                @endphp
                @if ($certSubmission && $certSubmission->status === \App\Models\CertificationSubmission::STATUS_NEW)
                    <form method="POST" action="{{ route('admin.certifications.approve', $certSubmission->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-1">
                            <i class="bi bi-check-circle"></i> Approve & Generate Certificate
                        </button>
                    </form>
                    <button type="button" class="btn btn-outline-danger d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#rejectCertificationModal">
                        <i class="bi bi-x-circle"></i> Reject
                    </button>
                @elseif ($certSubmission && $certSubmission->status === \App\Models\CertificationSubmission::STATUS_APPROVED)
                    <a href="{{ route('admin.certifications.certificate', $certSubmission->id) }}" target="_blank" rel="noopener" class="btn btn-primary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-file-earmark-pdf"></i> Open Certificate
                    </a>
                @endif
            @endif
            <a href="{{ route($resource['index_route'], request()->query()) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                @foreach ($resource['columns'] as $column)
                    <div class="col-md-6">
                        <div class="small text-muted mb-1">{{ str_replace('_', ' ', ucfirst($column)) }}</div>
                        @php $value = data_get($item, $column); @endphp
                        @if ($column === 'status')
                            <span class="badge {{ $statusBadgeClass((string) $value) }}">{{ $value ?: '—' }}</span>
                        @elseif (in_array($column, ['notes', 'brief_bio', 'about_your_business', 'partnership_goal', 'why_partner_with_peers_global', 'topics_to_speak_on'], true))
                            <div class="border rounded p-2 bg-light" style="white-space: pre-wrap;">{{ $value ?: '—' }}</div>
                        @else
                            <div>{{ $value ?: '—' }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if (isset($certSubmission) && $certSubmission)
        <div class="modal fade" id="rejectCertificationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.certifications.reject', $certSubmission->id) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Certification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Reject certification for <strong>{{ $certSubmission->full_name }}</strong>?</p>
                        <label class="form-label">Admin Note <span class="text-danger">*</span></label>
                        <textarea name="admin_note" class="form-control" rows="4" required placeholder="Reason for rejection"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
