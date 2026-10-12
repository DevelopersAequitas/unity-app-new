@extends('admin.layouts.app')

@section('title', 'Meta WhatsApp Templates - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-whatsapp text-success me-2"></i>Meta WhatsApp Templates
            </h1>
            <p class="text-muted small mb-0">Native Meta WhatsApp Cloud API template synchronization and management.</p>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('admin.whatsapp.templates.sync') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i>Sync from Meta
                </button>
            </form>
            <a href="{{ route('admin.whatsapp.templates.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Create Template
            </a>
            <a href="{{ route('admin.whatsapp.test_message') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-send me-1"></i>Test Message
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Total</div>
                    <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Approved</div>
                    <div class="h4 mb-0 fw-bold text-success">{{ $stats['approved'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">In Review</div>
                    <div class="h4 mb-0 fw-bold text-warning">{{ $stats['in_review'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-danger border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Rejected</div>
                    <div class="h4 mb-0 fw-bold text-danger">{{ $stats['rejected'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-secondary border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Paused / Disabled</div>
                    <div class="h4 mb-0 fw-bold text-secondary">{{ $stats['paused'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('admin.whatsapp.templates') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by template name or Meta ID..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="APPROVED" {{ $status === 'APPROVED' ? 'selected' : '' }}>APPROVED</option>
                        <option value="IN_REVIEW" {{ $status === 'IN_REVIEW' ? 'selected' : '' }}>IN_REVIEW</option>
                        <option value="REJECTED" {{ $status === 'REJECTED' ? 'selected' : '' }}>REJECTED</option>
                        <option value="PAUSED" {{ $status === 'PAUSED' ? 'selected' : '' }}>PAUSED</option>
                        <option value="DISABLED" {{ $status === 'DISABLED' ? 'selected' : '' }}>DISABLED</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="MARKETING" {{ $category === 'MARKETING' ? 'selected' : '' }}>MARKETING</option>
                        <option value="UTILITY" {{ $category === 'UTILITY' ? 'selected' : '' }}>UTILITY</option>
                        <option value="AUTHENTICATION" {{ $category === 'AUTHENTICATION' ? 'selected' : '' }}>AUTHENTICATION</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="language" class="form-select form-select-sm">
                        <option value="">All Languages</option>
                        <option value="en_US" {{ $language === 'en_US' ? 'selected' : '' }}>English (US)</option>
                        <option value="en_IN" {{ $language === 'en_IN' ? 'selected' : '' }}>English (India)</option>
                        <option value="hi" {{ $language === 'hi' ? 'selected' : '' }}>Hindi</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                    @if($search || $status || $category || $language)
                        <a href="{{ route('admin.whatsapp.templates') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Templates Table -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Template Name</th>
                        <th>Category</th>
                        <th>Language</th>
                        <th>Status</th>
                        <th>Quality</th>
                        <th>Meta Template ID</th>
                        <th>Last Synced</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $item)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark">{{ $item->name }}</div>
                                <span class="badge bg-light text-muted border font-monospace small">{{ $item->parameter_format ?? 'positional' }}</span>
                            </td>
                            <td>
                                @if($item->category === 'MARKETING')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">MARKETING</span>
                                @elseif($item->category === 'UTILITY')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">UTILITY</span>
                                @elseif($item->category === 'AUTHENTICATION')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">AUTHENTICATION</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">{{ $item->category }}</span>
                                @endif
                            </td>
                            <td><span class="font-monospace">{{ $item->language }}</span></td>
                            <td>
                                @php
                                    $st = strtoupper((string)$item->status);
                                @endphp
                                @if(in_array($st, ['APPROVED', 'ACTIVE']))
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>APPROVED</span>
                                @elseif($st === 'IN_REVIEW' || $st === 'PENDING')
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>IN REVIEW</span>
                                @elseif($st === 'REJECTED')
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>REJECTED</span>
                                @elseif($st === 'PAUSED' || $st === 'DISABLED')
                                    <span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>{{ $st }}</span>
                                @else
                                    <span class="badge bg-light text-dark border">{{ $st }}</span>
                                @endif
                            </td>
                            <td>
                                @if($item->quality === 'GREEN' || $item->quality === 'HIGH')
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-circle-fill me-1 small"></i>HIGH</span>
                                @elseif($item->quality === 'YELLOW' || $item->quality === 'MEDIUM')
                                    <span class="badge bg-warning-subtle text-warning"><i class="bi bi-circle-fill me-1 small"></i>MEDIUM</span>
                                @elseif($item->quality === 'RED' || $item->quality === 'LOW')
                                    <span class="badge bg-danger-subtle text-danger"><i class="bi bi-circle-fill me-1 small"></i>LOW</span>
                                @else
                                    <span class="text-muted small">N/A</span>
                                @endif
                            </td>
                            <td>
                                <span class="font-monospace small text-muted">{{ $item->meta_template_id ?: '-' }}</span>
                            </td>
                            <td class="small text-muted">
                                {{ $item->last_synced_at ? $item->last_synced_at->diffForHumans() : 'Never' }}
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.whatsapp.templates.show', $item->id) }}" class="btn btn-outline-secondary" title="View Template Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($item->isSendable())
                                        <a href="{{ route('admin.whatsapp.test_message', ['template' => $item->name, 'language' => $item->language]) }}" class="btn btn-outline-primary" title="Test Send Template">
                                            <i class="bi bi-send"></i>
                                        </a>
                                    @endif
                                    <form action="{{ route('admin.whatsapp.templates.delete', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this template from Meta?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete from Meta">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-whatsapp fs-1 d-block mb-2 text-muted"></i>
                                No Meta WhatsApp templates found.
                                <div class="mt-2">
                                    <form action="{{ route('admin.whatsapp.templates.sync') }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-arrow-repeat me-1"></i>Sync Templates from Meta
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.whatsapp.templates.create') }}" class="btn btn-sm btn-outline-success ms-2">
                                        <i class="bi bi-plus-circle me-1"></i>Create Template
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $templates->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
