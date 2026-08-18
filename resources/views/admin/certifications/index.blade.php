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
                                    ? ($item->certificate_download_url ?: url('/admin/certificates/' . $item->id . '/view'))
                                    : null;

                                $certRowData = [
                                    'id' => $item->id,
                                    'type' => $formatLabel($item->certification_type),
                                    'fullName' => $item->full_name,
                                    'businessName' => $item->business_name ?: '—',
                                    'email' => $item->email,
                                    'contactNo' => $item->contact_no ?: '—',
                                    'totalScore' => $item->total_score,
                                    'percentage' => $item->percentage,
                                    'level' => $item->certification_level ?: '—',
                                    'status' => $item->status,
                                    'statusLabel' => $formatLabel($item->status),
                                    'submittedDate' => $formatDate($item->created_at),
                                    'adminNote' => $item->admin_note ?: '',
                                    'certificateNumber' => $item->certificate_number ?: '',
                                    'issuedDate' => $formatDate($item->issued_at),
                                    'downloadUrl' => $downloadUrl,
                                    'isNew' => $item->status === \App\Models\CertificationSubmission::STATUS_NEW,
                                ];
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs whitespace-nowrap cursor-pointer" onclick="openCertRowModal({{ json_encode($certRowData) }})" title="Click row to view full certification details">
                                <td class="px-3 py-2.5 text-xs font-semibold t1 whitespace-nowrap">{{ $formatLabel($item->certification_type) }}</td>
                                <td class="px-3 py-2.5 text-xs font-medium t1 whitespace-nowrap">
                                    <span class="text-indigo-600 font-semibold no-underline whitespace-nowrap">
                                        {{ $item->full_name }}
                                    </span>
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
                                <td class="px-3 py-2.5 text-xs text-center whitespace-nowrap" onclick="event.stopPropagation()">
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

    <!-- Certification Row Details Popup Modal -->
    <div id="certRowDetailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 relative border border-gray-200 space-y-4 max-h-[90vh] overflow-y-auto">
            <button type="button" onclick="closeCertRowModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 transition cursor-pointer">&times;</button>
            
            <div class="border-b bs pb-3">
                <h3 id="modalCertFullName" class="font-bold text-base text-gray-900 m-0">Certification Submission</h3>
                <p id="modalCertType" class="text-xs text-indigo-600 font-semibold m-0 mt-0.5">Leadership Certification</p>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="p-3 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Business Name</span>
                    <span id="modalCertBusiness" class="font-semibold text-gray-900">—</span>
                </div>
                <div class="p-3 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Contact No</span>
                    <span id="modalCertContact" class="font-semibold text-gray-900 font-mono">—</span>
                </div>
                <div class="p-3 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Email</span>
                    <span id="modalCertEmail" class="font-semibold text-gray-900 break-all">—</span>
                </div>
                <div class="p-3 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Submitted Date</span>
                    <span id="modalCertDate" class="font-semibold text-gray-900 font-mono">—</span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2.5 text-xs">
                <div class="p-2.5 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Score</span>
                    <span id="modalCertScore" class="font-bold text-sm text-gray-900">—</span>
                </div>
                <div class="p-2.5 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Percentage</span>
                    <span id="modalCertPercentage" class="font-bold text-sm text-gray-900">—</span>
                </div>
                <div class="p-2.5 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Status</span>
                    <span id="modalCertStatus" class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-sky-50 text-sky-700 border border-sky-200">New</span>
                </div>
            </div>

            <div class="p-3 rounded-lg border bs bg-gray-50/70 space-y-1">
                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500">Level</span>
                <p id="modalCertLevel" class="text-xs text-gray-800 font-semibold m-0">—</p>
            </div>

            <div id="modalCertNoteContainer" class="p-3 rounded-lg border bs bg-gray-50/70 space-y-1 hidden">
                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500">Admin Note</span>
                <p id="modalCertNote" class="text-xs text-gray-800 leading-relaxed whitespace-pre-wrap break-words m-0">—</p>
            </div>

            <div class="pt-3 border-t bs flex justify-between items-center gap-2 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" id="modalCertViewDetailsBtn" class="px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition no-underline shadow-sm flex items-center gap-1.5 cursor-pointer">
                        Open Full Page
                    </button>

                    <a id="modalCertDownloadBtn" href="#" target="_blank" rel="noopener" class="hidden px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition no-underline shadow-sm flex items-center gap-1.5">
                        Open Certificate
                    </a>

                    <!-- Modal Approve Button -->
                    <button type="button" id="modalCertApproveBtn" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm cursor-pointer flex items-center gap-1.5">
                        Approve
                    </button>

                    <!-- Modal Reject Button -->
                    <button type="button" id="modalCertRejectBtn" class="px-4 py-2 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition shadow-sm cursor-pointer flex items-center gap-1.5">
                        Reject
                    </button>
                </div>

                <button type="button" onclick="closeCertRowModal()" class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentCertModalId = null;

        function openCertRowModal(data) {
            currentCertModalId = data.id;
            document.getElementById('modalCertFullName').textContent = data.fullName || 'Certification Submission';
            document.getElementById('modalCertType').textContent = (data.type || '') + ' Certification';
            document.getElementById('modalCertBusiness').textContent = data.businessName || '—';
            document.getElementById('modalCertContact').textContent = data.contactNo || '—';
            document.getElementById('modalCertEmail').textContent = data.email || '—';
            document.getElementById('modalCertDate').textContent = data.submittedDate || '—';
            document.getElementById('modalCertScore').textContent = data.totalScore ?? '—';
            document.getElementById('modalCertPercentage').textContent = (data.percentage ?? '—') + '%';
            document.getElementById('modalCertLevel').textContent = data.level || '—';

            // Admin Note
            const noteContainer = document.getElementById('modalCertNoteContainer');
            const noteEl = document.getElementById('modalCertNote');
            if (data.adminNote && data.adminNote.trim()) {
                noteEl.textContent = data.adminNote;
                noteContainer.classList.remove('hidden');
            } else {
                noteContainer.classList.add('hidden');
            }

            // Status Badge
            const statusEl = document.getElementById('modalCertStatus');
            statusEl.textContent = data.statusLabel || data.status;
            const st = (data.status || '').toLowerCase();
            if (st === 'approved') {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200';
            } else if (st === 'rejected') {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200';
            } else {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-sky-50 text-sky-700 border border-sky-200';
            }

            // View Details Button triggers bootstrap modal
            const viewBtn = document.getElementById('modalCertViewDetailsBtn');
            viewBtn.onclick = function() {
                closeCertRowModal();
                const bsModal = new bootstrap.Modal(document.getElementById('viewCertification' + data.id));
                bsModal.show();
            };

            // Certificate Download Link
            const downloadBtn = document.getElementById('modalCertDownloadBtn');
            if (data.downloadUrl) {
                downloadBtn.href = data.downloadUrl;
                downloadBtn.classList.remove('hidden');
            } else {
                downloadBtn.classList.add('hidden');
            }

            // Approve & Reject Buttons
            const approveBtn = document.getElementById('modalCertApproveBtn');
            const rejectBtn = document.getElementById('modalCertRejectBtn');

            approveBtn.onclick = function() {
                closeCertRowModal();
                const approveModal = new bootstrap.Modal(document.getElementById('approveCertification' + data.id));
                approveModal.show();
            };

            rejectBtn.onclick = function() {
                closeCertRowModal();
                const rejectModal = new bootstrap.Modal(document.getElementById('rejectCertification' + data.id));
                rejectModal.show();
            };

            document.getElementById('certRowDetailModal').classList.remove('hidden');
        }

        function closeCertRowModal() {
            document.getElementById('certRowDetailModal').classList.add('hidden');
        }
    </script>

    @foreach ($items as $item)
        @php
            $downloadUrl = $item->status === \App\Models\CertificationSubmission::STATUS_APPROVED
                ? ($item->certificate_download_url ?: url('/admin/certificates/' . $item->id . '/view'))
                : null;
            $st = strtolower((string)$item->status);
        @endphp
        <div class="modal fade" id="viewCertification{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content rounded-2xl shadow-2xl border-0 overflow-hidden">
                    <div class="modal-header border-b border-gray-200/80 bg-gray-50/50 px-6 py-4">
                        <div>
                            <h5 class="modal-title font-bold text-base text-gray-900 m-0">Certification Submission Details</h5>
                            <p class="text-xs text-indigo-600 font-semibold m-0 mt-0.5">{{ $formatLabel($item->certification_type) }} • {{ $item->full_name }}</p>
                        </div>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-6 space-y-5">
                        <!-- Overview Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            <div class="p-3.5 rounded-xl border bs bg-gray-50/70 space-y-1">
                                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500">Applicant Name</span>
                                <div class="font-bold text-sm text-gray-900">{{ $item->full_name }}</div>
                                <div class="text-xs text-indigo-600 font-medium">{{ $formatLabel($item->certification_type) }}</div>
                            </div>
                            <div class="p-3.5 rounded-xl border bs bg-gray-50/70 space-y-1">
                                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500">Business Name</span>
                                <div class="font-semibold text-sm text-gray-900">{{ $item->business_name ?: '—' }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $item->contact_no ?: '—' }}</div>
                            </div>
                            <div class="p-3.5 rounded-xl border bs bg-gray-50/70 space-y-1">
                                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500">Email Address</span>
                                <div class="font-semibold text-xs text-gray-900 break-all">{{ $item->email }}</div>
                                <div class="text-[11px] text-gray-500 font-mono">Submitted: {{ $formatDate($item->created_at) }}</div>
                            </div>
                        </div>

                        <!-- Score & Status Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 rounded-xl border bs bg-gray-50/70">
                                <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Total Score</span>
                                <span class="font-bold text-sm text-gray-900">{{ $item->total_score }}</span>
                            </div>
                            <div class="p-3 rounded-xl border bs bg-gray-50/70">
                                <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Percentage</span>
                                <span class="font-bold text-sm text-gray-900">{{ $item->percentage }}%</span>
                            </div>
                            <div class="p-3 rounded-xl border bs bg-gray-50/70">
                                <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Level</span>
                                <span class="font-semibold text-xs text-gray-900">{{ $item->certification_level ?: '—' }}</span>
                            </div>
                            <div class="p-3 rounded-xl border bs bg-gray-50/70">
                                <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Status</span>
                                @if($st === 'approved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Approved
                                    </span>
                                @elseif($st === 'rejected')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Rejected
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md bg-sky-50 text-sky-700 border border-sky-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>New
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Admin Note -->
                        @if($item->admin_note)
                            <div class="p-3.5 rounded-xl border bs bg-amber-50/40 border-amber-200/80 space-y-1">
                                <span class="block text-[11px] uppercase tracking-wider font-semibold text-amber-800">Admin Note</span>
                                <p class="text-xs text-gray-800 leading-relaxed whitespace-pre-wrap break-words m-0">{{ $item->admin_note }}</p>
                            </div>
                        @endif

                        <!-- Certificate Details (if approved) -->
                        @if ($item->status === \App\Models\CertificationSubmission::STATUS_APPROVED)
                            <div class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/40">
                                <div class="flex flex-wrap justify-between items-center gap-3">
                                    <div class="space-y-1">
                                        <h6 class="font-semibold text-xs text-emerald-800 uppercase tracking-wider m-0">Certificate Issued</h6>
                                        <div class="text-xs text-gray-700">Certificate No: <strong class="font-mono">{{ $item->certificate_number ?: '—' }}</strong></div>
                                        <div class="text-[11px] text-gray-500">Issued Date: {{ $formatDate($item->issued_at) }}</div>
                                    </div>
                                    @if ($downloadUrl)
                                        <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition no-underline shadow-sm">
                                            Open Certificate
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Answers List -->
                        <div class="space-y-2">
                            <h6 class="font-semibold text-xs text-gray-700 uppercase tracking-wider m-0">Answers Breakdown</h6>
                            <div class="rounded-xl border bs overflow-hidden">
                                <table class="w-full text-xs border-collapse">
                                    <tbody class="divide-y divide-gray-200/60">
                                        @forelse (($item->answers ?? []) as $question => $answer)
                                            <tr class="hover:bg-gray-50/60 transition">
                                                <th class="p-3 font-semibold text-gray-700 bg-gray-50/70 text-left align-top border-r bs" style="width: 38%;">{{ $formatLabel($question) }}</th>
                                                <td class="p-3 text-gray-800 leading-relaxed align-top">{{ is_array($answer) ? json_encode($answer) : ($answer ?: '—') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="p-4 text-center text-gray-400">No answers stored.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-t border-gray-200/80 bg-gray-50/50 px-6 py-3.5 flex justify-between items-center gap-2 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if ($item->status === \App\Models\CertificationSubmission::STATUS_NEW)
                                <button type="button" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm cursor-pointer flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#approveCertification{{ $item->id }}">
                                    Approve
                                </button>
                                <button type="button" class="px-4 py-2 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition shadow-sm cursor-pointer flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#rejectCertification{{ $item->id }}">
                                    Reject
                                </button>
                            @endif
                            @if ($item->status === \App\Models\CertificationSubmission::STATUS_APPROVED && $downloadUrl)
                                <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition no-underline shadow-sm flex items-center gap-1.5">
                                    Open Certificate
                                </a>
                            @endif
                        </div>

                        <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition cursor-pointer" data-bs-dismiss="modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="approveCertification{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.certifications.approve', $item->id) }}" class="modal-content rounded-2xl shadow-2xl border-0 overflow-hidden">
                    @csrf
                    <div class="modal-header border-b border-gray-200/80 bg-gray-50/50 px-6 py-4">
                        <h5 class="modal-title font-bold text-base text-gray-900 m-0">Approve Certification</h5>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-6 space-y-3">
                        <p class="text-xs text-gray-700 leading-relaxed m-0">Approve the <strong class="text-indigo-600">{{ $formatLabel($item->certification_type) }}</strong> certification submission for <strong>{{ $item->full_name }}</strong>?</p>
                        <div>
                            <label class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Admin Note <span class="text-gray-400 font-normal">(optional)</span></label>
                            <textarea name="admin_note" class="w-full px-3 py-2 text-xs rounded-lg border border-gray-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none" rows="4" placeholder="Certification approved after review.">{{ old('admin_note') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-t border-gray-200/80 bg-gray-50/50 px-6 py-3.5 flex justify-end items-center gap-2">
                        <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition cursor-pointer" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition shadow-sm cursor-pointer">Approve</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="rejectCertification{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.certifications.reject', $item->id) }}" class="modal-content rounded-2xl shadow-2xl border-0 overflow-hidden">
                    @csrf
                    <div class="modal-header border-b border-gray-200/80 bg-gray-50/50 px-6 py-4">
                        <h5 class="modal-title font-bold text-base text-gray-900 m-0">Reject Certification</h5>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-6 space-y-3">
                        <p class="text-xs text-gray-700 leading-relaxed m-0">Reject the <strong class="text-indigo-600">{{ $formatLabel($item->certification_type) }}</strong> certification submission for <strong>{{ $item->full_name }}</strong>?</p>
                        <div>
                            <label class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Admin Note <span class="text-rose-500 font-bold">*</span></label>
                            <textarea name="admin_note" class="w-full px-3 py-2 text-xs rounded-lg border border-gray-300 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none" rows="4" required placeholder="Reason for rejection">{{ old('admin_note') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-t border-gray-200/80 bg-gray-50/50 px-6 py-3.5 flex justify-end items-center gap-2">
                        <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition cursor-pointer" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition shadow-sm cursor-pointer">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
