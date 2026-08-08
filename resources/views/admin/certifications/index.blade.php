@extends('admin.layouts.app')

@section('title', 'Certification Submissions')

@include('admin.partials.grid-head')

@section('content')
    @php
        $statusBadgeClass = static function (?string $status): string {
            return match (strtolower((string) $status)) {
                'approved' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200',
                'rejected' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200',
                'new' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-sky-50 text-sky-700 border-sky-200',
                default => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200',
            };
        };

        $formatLabel = static fn (string $value): string => str($value)->replace('_', ' ')->title()->toString();
        $formatDate = static fn ($value): string => $value ? $value->format('d M Y, h:i A') : '—';
    @endphp

    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            <div class="font-semibold mb-1">Please fix the following:</div>
            <ul class="mb-0 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Certification Submissions</h2>
                <p class="text-xs t3 m-0 mt-0.5">Review Leadership and Entrepreneur certification requests.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total: {{ number_format($items->total()) }}</span>
        </div>

        <!-- Filter Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <form method="GET" action="{{ route('admin.certifications.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Statuses</option>
                        @foreach (['new' => 'New', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Type</label>
                    <select name="type" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Types</option>
                        @foreach (['leadership' => 'Leadership', 'entrepreneur' => 'Entrepreneur'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $filters['search'] ?? '' }}" placeholder="Name, business, email, or contact">
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('admin.certifications.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline w-full">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px] align-middle">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs whitespace-nowrap">
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Type</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Business Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Contact No</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-center whitespace-nowrap">Score</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-center whitespace-nowrap">%</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Level</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Submitted Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-center whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $item)
                            @php
                                $downloadUrl = $item->status === \App\Models\CertificationSubmission::STATUS_APPROVED
                                    ? ((is_string($item->certificate_download_url) && str_contains($item->certificate_download_url, '/admin/certificates/') && str_contains($item->certificate_download_url, '/view')) ? $item->certificate_download_url : url('/admin/certificates/' . $item->id . '/view'))
                                    : null;
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs whitespace-nowrap">
                                <td class="px-3 py-2.5 text-xs font-semibold t1 whitespace-nowrap">{{ $formatLabel($item->certification_type) }}</td>
                                <td class="px-3 py-2.5 text-xs font-medium t1 whitespace-nowrap">
                                    @if(!empty($item->user_id ?? $item->user?->id))
                                        <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $item->user_id ?? $item->user?->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline whitespace-nowrap">
                                            {{ $item->full_name }}
                                        </a>
                                    @else
                                        {{ $item->full_name }}
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $item->business_name ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $item->email }}</td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap font-mono">{{ $item->contact_no ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1 whitespace-nowrap">{{ $item->total_score }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1 whitespace-nowrap">{{ $item->percentage }}%</td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $item->certification_level ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @if(strtolower((string)$item->status) === 'approved')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Approved
                                        </span>
                                    @elseif(strtolower((string)$item->status) === 'rejected')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Rejected
                                        </span>
                                    @elseif(strtolower((string)$item->status) === 'new')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-sky-50 text-sky-700 border border-sky-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>New
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ $formatLabel($item->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap font-mono">{{ $formatDate($item->created_at) }}</td>
                                <td class="px-3 py-2.5 text-xs text-center whitespace-nowrap">
                                    <div class="flex justify-center gap-1.5 items-center whitespace-nowrap">
                                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition whitespace-nowrap" data-bs-toggle="modal" data-bs-target="#viewCertification{{ $item->id }}">View</button>
                                        @if ($item->status === \App\Models\CertificationSubmission::STATUS_NEW)
                                            <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition whitespace-nowrap" data-bs-toggle="modal" data-bs-target="#approveCertification{{ $item->id }}">Approve</button>
                                            <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition whitespace-nowrap" data-bs-toggle="modal" data-bs-target="#rejectCertification{{ $item->id }}">Reject</button>
                                        @endif
                                        @if ($item->status === \App\Models\CertificationSubmission::STATUS_APPROVED && $downloadUrl)
                                            <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition no-underline whitespace-nowrap">Open Certificate</a>
                                        @elseif ($item->status === \App\Models\CertificationSubmission::STATUS_APPROVED)
                                            <form method="POST" action="{{ route('admin.certifications.approve', $item->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition whitespace-nowrap">Refresh Link</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-8 text-xs t3 whitespace-nowrap">No certification submissions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $items->links() }}
            </div>
        </div>
    </div>


    @foreach ($items as $item)
        @php
            $downloadUrl = $item->status === \App\Models\CertificationSubmission::STATUS_APPROVED
                ? ((is_string($item->certificate_download_url) && str_contains($item->certificate_download_url, '/admin/certificates/') && str_contains($item->certificate_download_url, '/view')) ? $item->certificate_download_url : url('/admin/certificates/' . $item->id . '/view'))
                : null;
        @endphp
        <div class="modal fade" id="viewCertification{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Certification Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="small text-muted">Type</div><div class="fw-semibold">{{ $formatLabel($item->certification_type) }}</div></div>
                            <div class="col-md-4"><div class="small text-muted">Name</div><div class="fw-semibold">{{ $item->full_name }}</div></div>
                            <div class="col-md-4"><div class="small text-muted">Business Name</div><div>{{ $item->business_name ?: '—' }}</div></div>
                            <div class="col-md-4"><div class="small text-muted">Email</div><div>{{ $item->email }}</div></div>
                            <div class="col-md-4"><div class="small text-muted">Contact No</div><div>{{ $item->contact_no ?: '—' }}</div></div>
                            <div class="col-md-4"><div class="small text-muted">Submitted Date</div><div>{{ $formatDate($item->created_at) }}</div></div>
                            <div class="col-md-3"><div class="small text-muted">Score</div><div>{{ $item->total_score }}</div></div>
                            <div class="col-md-3"><div class="small text-muted">Percentage</div><div>{{ $item->percentage }}%</div></div>
                            <div class="col-md-3"><div class="small text-muted">Level</div><div>{{ $item->certification_level ?: '—' }}</div></div>
                            <div class="col-md-3"><div class="small text-muted">Status</div><span class="badge {{ $statusBadgeClass($item->status) }}">{{ $formatLabel($item->status) }}</span></div>
                            <div class="col-12"><div class="small text-muted">Admin Note</div><div class="border rounded p-2 bg-light" style="white-space: pre-wrap;">{{ $item->admin_note ?: '—' }}</div></div>
                        </div>

                        @if ($item->status === \App\Models\CertificationSubmission::STATUS_APPROVED)
                            <div class="border rounded p-3 bg-light mb-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <h6 class="mb-2">Certificate Details</h6>
                                        <div class="small text-muted">Certificate Number</div>
                                        <div class="fw-semibold">{{ $item->certificate_number ?: '—' }}</div>
                                        <div class="small text-muted mt-2">Issued Date</div>
                                        <div>{{ $formatDate($item->issued_at) }}</div>
                                    </div>
                                    @if ($downloadUrl)
                                        <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="btn btn-primary">
                                            Open Certificate
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <h6 class="mb-2">Answers</h6>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    @forelse (($item->answers ?? []) as $question => $answer)
                                        <tr>
                                            <th class="bg-light" style="width: 34%;">{{ $formatLabel($question) }}</th>
                                            <td>{{ is_array($answer) ? json_encode($answer) : ($answer ?: '—') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="text-muted">No answers stored.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="approveCertification{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.certifications.approve', $item->id) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Approve Certification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Approve the {{ $formatLabel($item->certification_type) }} certification submission for <strong>{{ $item->full_name }}</strong>?</p>
                        <label class="form-label">Admin Note <span class="text-muted">(optional)</span></label>
                        <textarea name="admin_note" class="form-control" rows="4" placeholder="Certification approved after review.">{{ old('admin_note') }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="rejectCertification{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.certifications.reject', $item->id) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Certification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Reject the {{ $formatLabel($item->certification_type) }} certification submission for <strong>{{ $item->full_name }}</strong>?</p>
                        <label class="form-label">Admin Note <span class="text-danger">*</span></label>
                        <textarea name="admin_note" class="form-control" rows="4" required placeholder="Reason for rejection">{{ old('admin_note') }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
