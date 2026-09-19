@extends('admin.layouts.app')

@section('title', 'Website Messages & Inquiries - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-chat-dots me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Website Messages & Inquiries</h1>
            <p class="text-muted small mb-0 mt-0.5">Contact requests, partnership inquiries, and speaker/mentor applications</p>
        </div>
    </div>

    <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                <thead class="table-light text-uppercase tracking-wider text-muted" style="font-size: 0.7rem;">
                    <tr>
                        <th>Sender</th>
                        <th>Subject / Type</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Received</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $msg)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $msg->name }}</div>
                                <small class="text-muted">{{ $msg->email }} {{ $msg->phone ? '• ' . $msg->phone : '' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $msg->subject ?? 'Inquiry' }}</div>
                                <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">{{ $msg->inquiry_type ?? 'General' }}</span>
                            </td>
                            <td>
                                <p class="text-muted small mb-0 line-clamp-2" style="max-width: 360px;">{{ $msg->message }}</p>
                            </td>
                            <td>
                                @if($msg->status === 'unread')
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">Unread</span>
                                @else
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">Read</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $msg->created_at ? $msg->created_at->diffForHumans() : '—' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.web.messages.read', $msg->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light border" title="Mark Read">
                                        <i class="bi bi-check2"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.web.messages.destroy', $msg->id) }}" onsubmit="return confirm('Delete message?');" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No incoming inquiries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
