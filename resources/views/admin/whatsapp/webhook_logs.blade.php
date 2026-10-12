@extends('admin.layouts.app')

@section('title', 'Meta WhatsApp Webhook Logs - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-arrow-left-right text-info me-2"></i>Meta WhatsApp Webhook Events
            </h1>
            <p class="text-muted small mb-0">Raw inbound audit trail received from Meta Cloud Webhook endpoint.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chat-dots me-1"></i>Message Logs
            </a>
            <a href="{{ route('admin.whatsapp.settings') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-gear me-1"></i>WhatsApp Settings
            </a>
        </div>
    </div>

    <!-- Webhook Table -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Received At</th>
                        <th>Event Type</th>
                        <th>WABA ID</th>
                        <th>Meta Message ID</th>
                        <th>Meta Template ID</th>
                        <th>Processing Status</th>
                        <th class="text-end pe-3">Raw Payload</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $ev)
                        <tr>
                            <td class="ps-3 small text-muted font-monospace">
                                {{ $ev->created_at->format('d M Y, H:i:s') }}
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                    {{ $ev->event_type }}
                                </span>
                            </td>
                            <td class="small font-monospace">{{ $ev->waba_id ?: '-' }}</td>
                            <td class="small font-monospace text-primary">{{ $ev->meta_message_id ?: '-' }}</td>
                            <td class="small font-monospace">{{ $ev->meta_template_id ?: '-' }}</td>
                            <td>
                                @if($ev->processing_status === 'processed')
                                    <span class="badge bg-success"><i class="bi bi-check me-1"></i>PROCESSED</span>
                                @elseif($ev->processing_status === 'failed')
                                    <span class="badge bg-danger"><i class="bi bi-x me-1"></i>FAILED</span>
                                @else
                                    <span class="badge bg-secondary">{{ $ev->processing_status }}</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#payloadModal{{ $ev->id }}">
                                    <i class="bi bi-code-slash me-1"></i>View JSON
                                </button>

                                <!-- Payload Modal -->
                                <div class="modal fade" id="payloadModal{{ $ev->id }}" tabindex="-1" aria-labelledby="payloadModalLabel{{ $ev->id }}" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered text-start">
                                        <div class="modal-content shadow border-0">
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold" id="payloadModalLabel{{ $ev->id }}">
                                                    Webhook Event Payload ({{ $ev->event_type }})
                                                </h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-0">
                                                <pre class="bg-dark text-light p-3 mb-0 small font-monospace" style="max-height: 480px; overflow-y: auto;">{{ json_encode($ev->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                            <div class="modal-footer bg-light py-2">
                                                <span class="small text-muted me-auto font-monospace">Hash: {{ substr((string)$ev->payload_hash, 0, 16) }}...</span>
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-arrow-left-right fs-1 d-block mb-2 text-muted"></i>
                                No webhook events received yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($events->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $events->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
