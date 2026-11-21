<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jury Evaluation Dashboard | Peers Global Leadership</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #4f46e5;
            --brand-primary-hover: #4338ca;
            --brand-dark: #0f172a;
            --brand-surface: #f8fafc;
            --brand-card: #ffffff;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
        }
        .navbar-jury {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            box-shadow: 0 4px 20px -5px rgba(0,0,0,0.25);
            padding: 16px 0;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 22px 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        .candidate-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .candidate-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }
        .badge-reviewed {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 12.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-pending {
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 12.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-review {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 9px 18px;
            font-weight: 700;
            font-size: 13.5px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);
        }
        .btn-review:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            color: #ffffff;
            transform: translateY(-1px);
        }
        .btn-view-review {
            background: #ffffff;
            color: #059669;
            border: 1.5px solid #059669;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .btn-view-review:hover {
            background: #ecfdf5;
            color: #047857;
        }
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .nav-tabs .nav-link {
            font-weight: 600;
            color: #64748b;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 12px 20px;
        }
        .nav-tabs .nav-link.active {
            color: #4f46e5;
            border-bottom: 2px solid #4f46e5;
            background: transparent;
        }
        .score-slider-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
        }
        .recommendation-option {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .recommendation-option:hover {
            border-color: #6366f1;
            background: #f8fafc;
        }
        .recommendation-option.selected {
            border-color: #4f46e5;
            background: #eef2ff;
        }
    </style>
</head>
<body>

<!-- Top Navigation (Only Peers Global Logo) -->
<nav class="navbar navbar-jury py-3">
    <div class="container-xl d-flex align-items-center justify-content-center">
        <a href="{{ route('admin.web.jury.dashboard') }}" class="d-inline-flex align-items-center text-decoration-none">
            <img src="{{ asset('images/peersglobal-logo.png') }}" alt="PeersGlobal" style="height: 48px; width: auto;" class="d-inline-block" />
        </a>
    </div>
</nav>

<!-- Main Container -->
<div class="container-xl py-4">

    <!-- KPI Metrics -->
    @php
        $totalAssigned = $assignments->count();
        $completedCount = $assignments->where('status', 'completed')->count();
        $pendingCount = $totalAssigned - $completedCount;
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Total Nominations</span>
                    <h3 class="fw-bold mb-0 text-dark mt-1" id="kpiTotal">{{ $totalAssigned }}</h3>
                </div>
                <div class="bg-indigo-subtle text-indigo rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="fa-solid fa-users fs-5 text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Pending Evaluation</span>
                    <h3 class="fw-bold mb-0 text-amber mt-1 text-warning" id="kpiPending">{{ $pendingCount }}</h3>
                </div>
                <div class="bg-warning-subtle text-warning rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="fa-solid fa-clock fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Completed Evaluations</span>
                    <h3 class="fw-bold mb-0 text-success mt-1" id="kpiCompleted">{{ $completedCount }}</h3>
                </div>
                <div class="bg-success-subtle text-success rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="fa-solid fa-circle-check fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Candidate Evaluations Section -->
    <div class="candidate-card">
        <div class="p-4 border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h4 class="fw-bold text-dark mb-1 fs-5">Assigned Candidates for Review</h4>
                <p class="text-muted small mb-0">
                    Review candidate Form 1 credentials & Form 2 operational dossiers, assign criterion scores, and provide final recommendation.
                </p>
            </div>
            <div>
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
                    <i class="fa-solid fa-building-columns me-1 text-primary"></i> Assigned Campaigns ({{ $assignments->pluck('nomination.campaign_name')->filter()->unique()->count() }})
                </span>
            </div>
        </div>

        @if($assignments->isEmpty())
            <div class="text-center py-5">
                <i class="fa-solid fa-folder-open text-muted fs-1 mb-3"></i>
                <h5 class="fw-bold text-dark">No Candidates Assigned Yet</h5>
                <p class="text-muted small">You will be notified via email when candidates are assigned to you for evaluation.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase text-secondary" style="font-size: 12px; letter-spacing: 0.05em;">
                            <th class="ps-4">Candidate</th>
                            <th>Applied Role</th>
                            <th>Campaign</th>
                            <th>Evaluation Deadline</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assignments as $asg)
                            @php
                                $nom = $asg->nomination;
                                $candName = $nom ? ($nom->candidate_name ?? $nom->full_name) : 'Candidate';
                                $initial = strtoupper(substr($candName, 0, 1));
                                $appNo = $nom ? $nom->application_number : 'N/A';
                                $roleName = $nom?->campaign?->role?->name ?? 'Leadership Role';
                                $campName = $asg->campaign_name ?? $nom?->campaign?->name ?? 'Leadership Campaign';
                                $isCompleted = $asg->status === 'completed';
                            @endphp
                            <tr 
                                id="row-{{ $asg->id }}" 
                                data-nomination-id="{{ $nom?->id }}"
                                onclick="openReviewModal('{{ $asg->id }}', '{{ $nom?->id }}', {{ $isCompleted ? 'true' : 'false' }})"
                                style="cursor: pointer;"
                                title="Click to view candidate dossier and submit evaluation"
                                class="hover-bg-light"
                            >
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="candidate-avatar">
                                            {{ $initial }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark candidate-name">{{ $candName }}</div>
                                            <div class="text-muted small font-monospace" style="font-size: 11.5px;">{{ $appNo }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-3 fw-semibold small">
                                        {{ $roleName }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark small fw-medium">{{ $campName }}</span>
                                </td>
                                <td class="small text-muted">
                                    @if($asg->due_at)
                                        <i class="fa-regular fa-calendar me-1"></i> {{ $asg->due_at->format('d M Y, h:i A') }}
                                    @else
                                        <span class="text-muted">No deadline</span>
                                    @endif
                                </td>
                                <td id="status-col-{{ $asg->id }}">
                                    @if($isCompleted)
                                        <span class="badge-reviewed">
                                            <i class="fa-solid fa-circle-check"></i> ✓ Reviewed
                                        </span>
                                    @else
                                        <span class="badge-pending">
                                            <i class="fa-solid fa-clock"></i> Pending Review
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end pe-4" id="action-col-{{ $asg->id }}">
                                    @if($isCompleted)
                                        <button 
                                            class="btn btn-view-review"
                                            onclick="event.stopPropagation(); openReviewModal('{{ $asg->id }}', '{{ $nom?->id }}', true)"
                                        >
                                            <i class="fa-solid fa-eye me-1"></i> View My Review
                                        </button>
                                    @else
                                        <button 
                                            class="btn btn-review"
                                            onclick="event.stopPropagation(); openReviewModal('{{ $asg->id }}', '{{ $nom?->id }}', false)"
                                        >
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Review Candidate
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Middle Bottom Jury Account Bar -->
    <div class="d-flex flex-column align-items-center justify-content-center text-center mt-5 mb-4">
        <div class="bg-white border rounded-4 shadow-xs px-4 py-3 d-inline-flex flex-column flex-sm-row align-items-center gap-3">
            <div class="text-center text-sm-start">
                <span class="text-dark fw-bold fs-6 d-block">{{ $juror->display_name ?: ($juror->first_name.' '.$juror->last_name) }}</span>
                <span class="text-muted small" style="font-size: 12px;">{{ $juror->email }} • Official Jury Member</span>
            </div>
            <a href="{{ route('admin.web.jury.logout.get') }}" class="btn btn-outline-danger btn-sm rounded-pill px-3.5 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </div>
        <p class="text-muted small mt-2 mb-0" style="font-size: 11.5px;">Peers Global Leadership Selection 2026 • Dedicated Jury Evaluation Portal</p>
    </div>

</div>

<!-- Candidate Review & Scoring Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div>
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalCandidateTitle">
                        Candidate Evaluation
                    </h5>
                    <span class="text-muted small" id="modalCandidateSubtitle"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Nav Tabs -->
            <div class="border-bottom px-4 pt-2 bg-white">
                <ul class="nav nav-tabs" id="evaluationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-form1-btn" data-bs-toggle="tab" data-bs-target="#tab-form1" type="button" role="tab">
                            <i class="fa-solid fa-user me-1.5"></i> Form 1 Profile
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-form2-btn" data-bs-toggle="tab" data-bs-target="#tab-form2" type="button" role="tab">
                            <i class="fa-solid fa-file-lines me-1.5"></i> Form 2 Jury Dossier
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-scoring-btn" data-bs-toggle="tab" data-bs-target="#tab-scoring" type="button" role="tab">
                            <i class="fa-solid fa-star me-1.5 text-warning"></i> Scoring & Recommendation
                        </button>
                    </li>
                </ul>
            </div>

            <div class="modal-body p-4" style="background-color: #f8fafc;">
                <div class="tab-content" id="evaluationTabsContent">
                    
                    <!-- TAB 1: FORM 1 PROFILE -->
                    <div class="tab-pane fade show active" id="tab-form1" role="tabpanel">
                        <div class="d-flex flex-column gap-3">
                            <!-- Candidate Profile & Enterprise Foundation -->
                            <div class="bg-white p-4 rounded-4 border shadow-xs">
                                <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                    <h6 class="fw-bold text-dark mb-0">Candidate Profile & Enterprise Foundation</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Candidate Name</span>
                                        <span class="fw-bold text-dark fs-6" id="f1CandidateName">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Application Number</span>
                                        <span class="badge bg-light text-primary border fw-bold px-2.5 py-1" id="f1AppNumber">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Applied Role</span>
                                        <span class="fw-bold text-dark" id="f1Role">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Circle / Scope</span>
                                        <span class="fw-bold text-dark" id="f1Scope">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Company / Venture Name</span>
                                        <span class="fw-bold text-dark" id="f1Company">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Executive Designation</span>
                                        <span class="fw-bold text-dark" id="f1Designation">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Business Category</span>
                                        <span class="badge bg-indigo-subtle text-indigo fw-semibold px-2.5 py-1" id="f1Category">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Contact Information</span>
                                        <span class="text-dark small d-block text-truncate" id="f1Email">—</span>
                                        <span class="text-muted small" id="f1Phone">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: FORM 2 JURY DOSSIER -->
                    <div class="tab-pane fade" id="tab-form2" role="tabpanel">
                        <div id="form2Container" class="d-flex flex-column gap-3">
                            <div class="text-center py-5 text-muted bg-white rounded-4 border">
                                <span class="spinner-border spinner-border-sm me-2"></span> Loading Form 2 dossier...
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: SCORING & RECOMMENDATION -->
                    <div class="tab-pane fade" id="tab-scoring" role="tabpanel">
                        <form id="scoringForm" onsubmit="handleReviewSubmit(event)">
                            <input type="hidden" id="currentAssignmentId">
                            
                            <div class="bg-white p-4 rounded-4 border shadow-xs mb-4">
                                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                                    <h6 class="fw-bold text-dark mb-0">Criteria Evaluation (Scoring)</h6>
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 fw-semibold" onclick="showAddCustomCriterionPrompt()">
                                        <i class="fa-solid fa-plus me-1"></i> Add Custom Criterion
                                    </button>
                                </div>
                                
                                <div id="criteriaList">
                                    @foreach($criteria as $crit)
                                        <div class="p-3 bg-light rounded-4 border mb-3" id="crit-box-{{ $crit->id }}">
                                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2">
                                                <div>
                                                    <span class="fw-bold text-dark fs-6">{{ $crit->name }}</span>
                                                    <span class="badge bg-white text-muted border ms-2" style="font-size: 11px;">Weight: {{ $crit->weightage }}%</span>
                                                    <p class="text-muted small mb-0 mt-1" style="font-size: 12px;">{{ $crit->description ?: 'Score candidate competence and demonstrated excellence on this criterion.' }}</p>
                                                </div>
                                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                    <label class="small text-muted fw-semibold me-1">Score:</label>
                                                    <div class="input-group input-group-sm" style="width: 140px;">
                                                        <input 
                                                            type="number" 
                                                            class="form-control text-center fw-bold fs-6 text-primary score-number-input" 
                                                            min="0" 
                                                            max="{{ $crit->max_score }}" 
                                                            step="0.5" 
                                                            value="8" 
                                                            data-criterion-id="{{ $crit->id }}"
                                                            data-criterion-name="{{ $crit->name }}"
                                                            id="input-score-{{ $crit->id }}"
                                                            required
                                                        >
                                                        <span class="input-group-text bg-white text-muted fw-semibold" style="font-size: 12px;">/ 10 pts</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <input type="text" class="form-control form-control-sm bg-white" placeholder="Optional notes/remarks on this criterion..." id="remarks-{{ $crit->id }}" data-criterion-id="{{ $crit->id }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="bg-white p-4 rounded-4 border shadow-xs mb-4">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Overall Recommendation *</h6>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-md-3">
                                        <div class="recommendation-option selected" onclick="selectRecommendation('strongly_recommend', this)">
                                            <input type="radio" name="overall_recommendation" value="strongly_recommend" checked class="d-none">
                                            <div class="fw-bold text-success mb-1"><i class="fa-solid fa-star me-1"></i> Strongly Recommend</div>
                                            <p class="text-muted mb-0" style="font-size: 11.5px;">Outstanding fit with proven leadership track record.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="recommendation-option" onclick="selectRecommendation('recommend', this)">
                                            <input type="radio" name="overall_recommendation" value="recommend" class="d-none">
                                            <div class="fw-bold text-primary mb-1"><i class="fa-solid fa-check me-1"></i> Recommend</div>
                                            <p class="text-muted mb-0" style="font-size: 11.5px;">Strong candidate meeting all core benchmarks.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="recommendation-option" onclick="selectRecommendation('recommend_with_reservations', this)">
                                            <input type="radio" name="overall_recommendation" value="recommend_with_reservations" class="d-none">
                                            <div class="fw-bold text-warning mb-1"><i class="fa-solid fa-circle-question me-1"></i> Reservations</div>
                                            <p class="text-muted mb-0" style="font-size: 11.5px;">Borderline; requires further committee scrutiny or conditions.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="recommendation-option" onclick="selectRecommendation('do_not_recommend', this)">
                                            <input type="radio" name="overall_recommendation" value="do_not_recommend" class="d-none">
                                            <div class="fw-bold text-danger mb-1"><i class="fa-solid fa-xmark me-1"></i> Do Not Recommend</div>
                                            <p class="text-muted mb-0" style="font-size: 11.5px;">Does not meet minimum prerequisites or bench capacity.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Key Strengths & Merits *</label>
                                        <textarea id="evalStrengths" class="form-control" rows="3" placeholder="Highlight primary strengths, leadership attributes, and strategic bench value..." required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Key Concerns or Limitations *</label>
                                        <textarea id="evalConcerns" class="form-control" rows="3" placeholder="Note potential operational bottlenecks, bandwidth risks, or reservations..." required></textarea>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 rounded-3 border bg-light-subtle">
                                            <label class="form-label small fw-bold text-dark d-flex align-items-center gap-1.5 mb-2">
                                                <i class="fa-solid fa-shield-halved text-primary"></i>
                                                Conflict of Interest Check
                                            </label>
                                            <div class="d-flex flex-column gap-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="juror_conflict_check" id="conflictNone" value="none" checked onchange="toggleConflictDetails(false)">
                                                    <label class="form-check-label small text-dark fw-medium" for="conflictNone">
                                                        I confirm that I have <strong>no personal, business, or competitive conflict of interest</strong> with this candidate.
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="juror_conflict_check" id="conflictExists" value="exists" onchange="toggleConflictDetails(true)">
                                                    <label class="form-check-label small text-danger fw-medium" for="conflictExists">
                                                        I disclose a potential conflict of interest or close commercial/personal tie with this candidate.
                                                    </label>
                                                </div>
                                            </div>
                                            <div id="conflictDetailsBox" class="mt-2" style="display: none;">
                                                <textarea id="evalConflictDetails" class="form-control form-control-sm" rows="2" placeholder="Describe nature of the conflict of interest..."></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-muted">Final Remarks & Recommendation Summary</label>
                                        <textarea id="evalRemarks" class="form-control" rows="2" placeholder="Optional notes for the Final Decision Board..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-end gap-2" id="modalFooterActions">
                                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Close</button>
                                <button type="submit" id="btnSubmitEvaluation" class="btn btn-review px-5 py-2.5">
                                    <i class="fa-solid fa-check me-1.5"></i> Submit Final Review
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script id="assignmentsDataJson" type="application/json">
    {!! json_encode($assignmentsData ?? []) !!}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

// Parse preloaded assignments data for instant (0ms) rendering
let preloadedAssignments = {};
try {
    const rawDataEl = document.getElementById('assignmentsDataJson');
    if (rawDataEl && rawDataEl.textContent) {
        preloadedAssignments = JSON.parse(rawDataEl.textContent);
    }
} catch (e) {
    console.warn('Could not parse preloaded assignments data', e);
}

// Resilient Modal Controller (Works with Bootstrap or Vanilla fallback)
function showModalSafely(modalId) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return;
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            return;
        }
    } catch (e) {
        console.warn('Bootstrap modal show failed, using fallback', e);
    }

    // Vanilla fallback
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    if (!document.getElementById('custom-modal-backdrop')) {
        const bd = document.createElement('div');
        bd.id = 'custom-modal-backdrop';
        bd.className = 'modal-backdrop fade show';
        document.body.appendChild(bd);
    }
}

function hideModalSafely(modalId) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return;
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
                return;
            }
        }
    } catch (e) {
        console.warn('Bootstrap modal hide failed, using fallback', e);
    }

    // Vanilla fallback
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    const bd = document.getElementById('custom-modal-backdrop');
    if (bd) bd.remove();
}

// Wire modal close buttons for guaranteed instant close
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#reviewModal [data-bs-dismiss="modal"], #reviewModal .btn-close').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            hideModalSafely('reviewModal');
        });
    });

    // Wire evaluation tabs for instant switching even if bootstrap tab plugin hasn't initialized
    document.querySelectorAll('#evaluationTabs button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#evaluationTabs .nav-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('#evaluationTabsContent .tab-pane').forEach(p => p.classList.remove('show', 'active'));
            this.classList.add('active');
            const targetSelector = this.getAttribute('data-bs-target');
            const target = targetSelector ? document.querySelector(targetSelector) : null;
            if (target) {
                target.classList.add('show', 'active');
            }
        });
    });
});

function updateScoreDisplay(id, val) {
    const el = document.getElementById('score-val-' + id);
    if (el) el.textContent = val;
}

function selectRecommendation(val, element) {
    document.querySelectorAll('.recommendation-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    const radio = element.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
}

// Open Review Modal - Synchronous 0ms open with instant preloaded data
function openReviewModal(assignmentId, nominationId, isViewOnly) {
    try {
        const inputAsg = document.getElementById('currentAssignmentId');
        if (inputAsg) inputAsg.value = assignmentId;
        
        if (!nominationId) {
            const row = document.getElementById('row-' + assignmentId);
            if (row) nominationId = row.getAttribute('data-nomination-id');
        }

        // Set submit button state
        const submitBtn = document.getElementById('btnSubmitEvaluation');
        if (submitBtn) {
            if (isViewOnly) {
                submitBtn.style.display = 'none';
            } else {
                submitBtn.style.display = 'inline-block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-check me-1.5"></i> Submit Final Review';
            }
        }

        // Check preloaded memory cache first for INSTANT 0ms population
        const cached = preloadedAssignments && preloadedAssignments[assignmentId];
        if (cached) {
            populateCandidateDetails(cached, isViewOnly);
        } else {
            // Reset Form 2 container with loading only if not cached
            const form2Container = document.getElementById('form2Container');
            if (form2Container) {
                form2Container.innerHTML = `
                    <div class="text-center py-5 text-muted bg-white rounded-4 border">
                        <span class="spinner-border spinner-border-sm me-2"></span> Loading Form 2 operational dossier...
                    </div>
                `;
            }
        }

        // Show Modal immediately (0ms latency)
        showModalSafely('reviewModal');

        // Switch to Form 1 tab by default
        const tab1Btn = document.getElementById('tab-form1-btn');
        if (tab1Btn) {
            document.querySelectorAll('#evaluationTabs .nav-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('#evaluationTabsContent .tab-pane').forEach(p => p.classList.remove('show', 'active'));
            tab1Btn.classList.add('active');
            const targetPane = document.getElementById('tab-form1');
            if (targetPane) targetPane.classList.add('show', 'active');
        }

        // Fetch background refresh if needed (updates seamlessly without blocking modal)
        if (nominationId) {
            fetch(`/admin/web/jury/nomination/${nominationId}/details?assignment_id=${assignmentId}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.data) {
                    preloadedAssignments[assignmentId] = data.data;
                    populateCandidateDetails(data.data, isViewOnly);
                }
            })
            .catch(err => {
                console.warn('Background sync note:', err);
            });
        }
    } catch (err) {
        console.error('Failed to open candidate evaluation modal:', err);
    }
}

function populateCandidateDetails(data, isViewOnly) {
    const nom = data.nomination || {};
    const form2 = data.form_2;
    const docs = data.documents || [];
    const existing = data.existing_review;

    // Header Details
    const candTitle = nom.candidate_name || nom.full_name || 'Candidate';
    const appNo = nom.application_number || 'N/A';
    const roleName = nom.campaign?.role?.name || nom.role_name || 'Leadership Office';
    const campName = nom.campaign?.name || nom.campaign_name || 'Leadership Campaign';
    const scopeName = nom.scope?.name || nom.scope_name || 'National Circle';

    document.getElementById('modalCandidateTitle').innerHTML = `
        <span>${candTitle}</span>
        <span class="badge bg-primary-subtle text-primary border" style="font-size: 12px;">${appNo}</span>
    `;
    document.getElementById('modalCandidateSubtitle').textContent = `${roleName} • ${campName}`;

    // TAB 1: FORM 1 PROFILE (Main Points & Sub-Points)
    const elCandName = document.getElementById('f1CandidateName');
    if (elCandName) elCandName.textContent = candTitle;

    const elAppNo = document.getElementById('f1AppNumber');
    if (elAppNo) elAppNo.textContent = appNo;

    const elRole = document.getElementById('f1Role');
    if (elRole) elRole.textContent = roleName;

    const elScope = document.getElementById('f1Scope');
    if (elScope) elScope.textContent = scopeName;

    const elCompany = document.getElementById('f1Company');
    if (elCompany) elCompany.textContent = nom.company || nom.company_name || 'Not Specified';

    const elDesignation = document.getElementById('f1Designation');
    if (elDesignation) elDesignation.textContent = nom.designation || 'Leader';

    const elCategory = document.getElementById('f1Category');
    if (elCategory) elCategory.textContent = nom.business_category || 'General Business';

    const elEmail = document.getElementById('f1Email');
    if (elEmail) elEmail.textContent = nom.email || 'Not Disclosed';

    const elPhone = document.getElementById('f1Phone');
    if (elPhone) elPhone.textContent = nom.phone || 'Not Disclosed';

    const elVision = document.getElementById('f1Vision');
    if (elVision) elVision.textContent = nom.vision_statement || 'No vision statement recorded.';

    const elBio = document.getElementById('f1Bio');
    if (elBio) elBio.textContent = nom.bio || 'No professional bio recorded.';

    // Dynamic questionnaire answers in Form 1
    const qCard = document.getElementById('f1DynamicQuestionsCard');
    const qList = document.getElementById('f1QuestionsList');
    if (qCard && qList) {
        if (nom.answers && Array.isArray(nom.answers) && nom.answers.length > 0) {
            let qHtml = '';
            nom.answers.forEach((ans, idx) => {
                const qText = ans.question_label || ans.question?.label || ans.question?.question_text || (() => {
                    const k = ans.question_key || '';
                    if (!k) return `Question ${idx + 1}`;
                    const clean = k.replace(/^q_/, '').replace(/_/g, ' ');
                    return clean.charAt(0).toUpperCase() + clean.slice(1);
                })();

                let raw = ans.sanitized_value ?? ans.answer_text ?? ans.answer_json ?? ans.answer;
                if (typeof raw === 'string' && (raw.startsWith('{') || raw.startsWith('['))) {
                    try { raw = JSON.parse(raw); } catch (e) {}
                }

                let val = raw;
                if (val && typeof val === 'object') {
                    if ('value' in val) {
                        val = val.value;
                    } else if ('text' in val) {
                        val = val.text;
                    }
                }

                let valHtml = '';
                if (val === true || val === 'true') {
                    valHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill"><i class="fa-solid fa-check me-1"></i> Affirmed (Yes)</span>';
                } else if (val === false || val === 'false') {
                    valHtml = '<span class="badge bg-light text-muted border px-2 py-0.5 rounded-pill">No</span>';
                } else if (Array.isArray(val)) {
                    const filtered = val.filter(item => item !== null && item !== undefined && item !== '');
                    if (filtered.length === 0) {
                        valHtml = '<span class="text-muted fst-italic small">No data submitted</span>';
                    } else {
                        valHtml = `<p class="fw-semibold text-dark mb-0 small">${filtered.join(', ')}</p>`;
                    }
                } else if (val === null || val === undefined || val === '' || val === 'null' || val === '[]' || val === '{}') {
                    valHtml = '<span class="text-muted fst-italic small">No data submitted</span>';
                } else if (typeof val === 'string' && val.trim().length > 0) {
                    valHtml = `<p class="fw-semibold text-dark mb-0 small" style="white-space: pre-line;">${val.trim()}</p>`;
                } else if (typeof val === 'number') {
                    valHtml = `<p class="fw-semibold text-dark mb-0 small">${val}</p>`;
                } else if (val && typeof val === 'object') {
                    const entries = Object.entries(val).filter(([k, v]) => v !== null && v !== '' && v !== undefined);
                    if (entries.length === 0) {
                        valHtml = '<span class="text-muted fst-italic small">No data submitted</span>';
                    } else {
                        valHtml = entries.map(([k, v]) => `<div class="small"><strong>${k}:</strong> ${v}</div>`).join('');
                    }
                } else {
                    valHtml = '<span class="text-muted fst-italic small">No data submitted</span>';
                }

                qHtml += `
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small d-block fw-semibold mb-1">${qText}</span>
                        ${valHtml}
                    </div>
                `;
            });
            qList.innerHTML = qHtml;
            qCard.style.display = 'block';
        } else {
            qCard.style.display = 'none';
        }
    }

    // TAB 2: FORM 2 JURY DOSSIER (Main Points & Sub-Points)
    renderForm2Dossier(form2, docs);

    // TAB 3: SCORING & RECOMMENDATION (Pre-fill existing review or reset)
    applyReviewFormState(existing, isViewOnly);
}

function renderForm2Dossier(rawF2, docs) {
    const container = document.getElementById('form2Container');
    if (!container) return;

    if (!rawF2 || (typeof rawF2 === 'object' && Object.keys(rawF2).length === 0)) {
        container.innerHTML = `
            <div class="p-4 rounded-4 bg-white border text-center">
                <div class="bg-warning-subtle text-warning rounded-circle p-3 d-inline-flex mb-3">
                    <i class="fa-solid fa-clock fs-3"></i>
                </div>
                <h6 class="fw-bold text-dark">Form 2 Operational Dossier Pending</h6>
                <p class="text-muted small mb-0" style="max-width: 500px; margin: 0 auto;">
                    This candidate has been shortlisted for Jury Evaluation, but has not yet finalized their Form 2 operational responses. You may evaluate based on Form 1 credentials and interview interactions.
                </p>
            </div>
        `;
        return;
    }

    const secA = rawF2.section_a_business_credibility || rawF2;
    const secB = rawF2.section_b_leadership_track_record || rawF2;
    const secC = rawF2.section_c_network_strength || rawF2;
    const secD = rawF2.section_d_leadership_bench || rawF2;
    const secE = rawF2.section_e_12_month_plan || rawF2;
    const secF = rawF2.section_f_commitment_fit || rawF2;
    const secG = rawF2.section_g_references || rawF2;

    const getVal = (...keys) => {
        for (const k of keys) {
            if (rawF2 && rawF2[k] !== undefined && rawF2[k] !== null && rawF2[k] !== '') return rawF2[k];
            if (secA && secA[k] !== undefined && secA[k] !== null && secA[k] !== '') return secA[k];
            if (secB && secB[k] !== undefined && secB[k] !== null && secB[k] !== '') return secB[k];
            if (secC && secC[k] !== undefined && secC[k] !== null && secC[k] !== '') return secC[k];
            if (secD && secD[k] !== undefined && secD[k] !== null && secD[k] !== '') return secD[k];
            if (secE && secE[k] !== undefined && secE[k] !== null && secE[k] !== '') return secE[k];
            if (secF && secF[k] !== undefined && secF[k] !== null && secF[k] !== '') return secF[k];
            if (secG && secG[k] !== undefined && secG[k] !== null && secG[k] !== '') return secG[k];
        }
        return null;
    };

    // Section A
    const businessType = getVal('business_type') || '—';
    const yearFounded = getVal('year_founded') || '—';
    const turnoverBand = getVal('turnover_band', 'annual_turnover_band') || '—';
    const teamSize = getVal('team_size') || '—';
    const achs = [getVal('achievement_1'), getVal('achievement_2'), getVal('achievement_3')].filter(Boolean);
    const achievementsText = achs.length > 0 ? achs.map((a, i) => `${i + 1}. ${a}`).join('\n') : (getVal('achievements', 'top_3_achievements') || 'No specific achievements noted.');
    const regProofUrl = getVal('business_proof_url', 'registration_doc_url');

    // Section B
    const rolesHeld = getVal('leadership_roles_held') || '—';
    const largestCommunity = getVal('largest_community') || '—';
    const devs = [getVal('developed_leader_1'), getVal('developed_leader_2')].filter(Boolean);
    const developedText = devs.length > 0 ? devs.map((d, i) => `Leader ${i + 1}: ${d}`).join('\n') : (getVal('developed_leaders') || '—');
    const crisisHandled = getVal('leadership_failure_lesson', 'crisis_handled') || 'No crisis case reported.';

    // Section C
    const associations = getVal('associations', 'industry_associations') || '—';
    const institutionsAccess = getVal('institutions_access', 'cxo_network') || '—';
    const potentialSponsors = getVal('potential_sponsors') || '—';

    // Section D (Bench)
    let rawBench = getVal('bench', 'bench_members') || [];
    if (typeof rawBench === 'object' && !Array.isArray(rawBench)) {
        rawBench = Object.values(rawBench);
    }
    const benchList = Array.isArray(rawBench) ? rawBench : [];

    // Section E
    const target6 = getVal('targets_6_months') || '—';
    const target12 = getVal('targets_12_months') || '—';
    const first90 = getVal('first_90_days') || '—';
    const bigRisk = getVal('biggest_risk') || '—';

    // Section F
    const hoursPerWeek = getVal('hours_per_week', 'commitment_hours') || '—';
    const travelAvailability = getVal('travel_availability') || '—';
    const valuesAlignment = getVal('peers_values_alignment', 'values_alignment') || '—';

    // Section G
    let refList = [];
    if (getVal('ref_1_name')) {
        refList.push({ name: getVal('ref_1_name'), phone: getVal('ref_1_phone') || '', relationship: getVal('ref_1_relationship') || '' });
    }
    if (getVal('ref_2_name')) {
        refList.push({ name: getVal('ref_2_name'), phone: getVal('ref_2_phone') || '', relationship: getVal('ref_2_relationship') || '' });
    }
    if (refList.length === 0 && Array.isArray(getVal('references'))) {
        refList = getVal('references');
    }

    let html = '';

    // Section A: Business Credibility & Enterprise Metrics
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">Business Credibility & Enterprise Metrics</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <span class="text-muted small d-block">Business Structure</span>
                    <span class="fw-bold text-dark">${businessType}</span>
                </div>
                <div class="col-md-6 col-lg-3">
                    <span class="text-muted small d-block">Year Founded</span>
                    <span class="fw-bold text-dark">${yearFounded}</span>
                </div>
                <div class="col-md-6 col-lg-3">
                    <span class="text-muted small d-block">Annual Turnover Band</span>
                    <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1">
                        ${turnoverBand}
                    </span>
                </div>
                <div class="col-md-6 col-lg-3">
                    <span class="text-muted small d-block">Enterprise Team Size</span>
                    <span class="fw-bold text-dark">${teamSize}</span>
                </div>
                <div class="col-12 col-md-8">
                    <span class="text-muted small d-block">Top Business Achievements</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${achievementsText}
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <span class="text-muted small d-block">Business Registration Proof</span>
                    ${regProofUrl ? `
                        <a href="${regProofUrl}" target="_blank" class="btn btn-outline-primary btn-sm mt-2 rounded-3 px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-file-invoice"></i> View Registration Certificate
                        </a>
                    ` : '<span class="text-muted small mt-2 d-inline-block">No document uploaded</span>'}
                </div>
            </div>
        </div>
    `;

    // Section B: Leadership Scale & Track Record
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">Leadership Scale & Track Record</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted small d-block">Leadership Roles Previously Held</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${rolesHeld}
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Largest Community / Workforce Led</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${largestCommunity}
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Leaders Developed & Mentored</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${developedText}
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Difficult Leadership Failure & Lesson Learned</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${crisisHandled}
                    </div>
                </div>
            </div>
        </div>
    `;

    // Section C: Ecosystem Influence & Strategic Networks
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">Ecosystem Influence & Strategic Networks</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <span class="text-muted small d-block">Industry Associations & Chambers</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${associations}
                    </div>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Institutions Access (Banks, Media, Universities)</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${institutionsAccess}
                    </div>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Potential Enterprise Sponsors</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${potentialSponsors}
                    </div>
                </div>
            </div>
        </div>
    `;

    // Section D: Leadership Bench Strength & Succession Pool
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">Leadership Bench Strength & Succession Pool</h6>
            </div>
            ${benchList.length > 0 ? `
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>#</th>
                                <th>Leader Name & Mobile</th>
                                <th>Location & Industry</th>
                                <th>Relationship</th>
                                <th>Proposed Role</th>
                                <th>Status & Timeline</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            ${benchList.map((b, bIdx) => `
                                <tr>
                                    <td class="text-muted">${bIdx + 1}</td>
                                    <td>
                                        <div class="fw-bold text-dark">${b.name || '—'}</div>
                                        <div class="text-muted small">${b.mobile || ''}</div>
                                    </td>
                                    <td>
                                        <div>${b.city || '—'}</div>
                                        <span class="badge bg-light text-muted border">${b.industry || '—'}</span>
                                    </td>
                                    <td>${b.relationship || '—'}</td>
                                    <td><span class="badge bg-indigo-subtle text-indigo px-2 py-1">${b.proposed_role || b.role || '—'}</span></td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border">${b.status || 'Committed'}</span>
                                        <span class="text-muted ms-1 small">(${b.timeline || 'Immediate'})</span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            ` : `
                <div class="p-3 bg-light rounded-3 border text-muted small">
                    No explicit bench successors listed in Form 2 submission.
                </div>
            `}
        </div>
    `;

    // Section E: 12-Month Expansion Plan & Milestones
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">12-Month Expansion Plan & Milestones</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted small d-block">6-Month Expansion Target</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1">
                        ${target6}
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">12-Month Expansion Target</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1">
                        ${target12}
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">First 90 Days Priority Actions</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${first90}
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Anticipated Risks & Mitigation Strategy</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${bigRisk}
                    </div>
                </div>
            </div>
        </div>
    `;

    // Section F & G: Time Commitment, Culture & References
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">Time Commitment, Culture & References</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted small d-block">Weekly Dedicated Hours</span>
                    <span class="fw-bold text-dark">${hoursPerWeek}</span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Travel & Regional Availability</span>
                    <span class="fw-bold text-dark">${travelAvailability}</span>
                </div>
                <div class="col-12">
                    <span class="text-muted small d-block">Peers Global Values & Culture Alignment</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${valuesAlignment}
                    </div>
                </div>
                <div class="col-12">
                    <span class="text-muted small d-block">Professional Referees</span>
                    <div class="row g-2 mt-1">
                        ${refList.length > 0 ? refList.map((r, rIdx) => `
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="fw-bold text-dark small">Reference #${rIdx + 1}: ${r.name || '—'}</div>
                                    <div class="text-muted small">${r.phone || ''} • ${r.relationship || 'Peer'}</div>
                                </div>
                            </div>
                        `).join('') : '<div class="col-12 text-muted small">No external references listed.</div>'}
                    </div>
                </div>
            </div>
        </div>
    `;

    // Verified Supporting Documents & Presentations
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">Verified Supporting Documents & Presentations</h6>
            </div>
            <div class="row g-2">
                ${docs.length > 0 ? docs.map(d => `
                    <div class="col-md-6">
                        <div class="p-2.5 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                <i class="fa-solid fa-file-pdf text-danger fs-5"></i>
                                <span class="small fw-semibold text-dark text-truncate">${d.document_type || 'Document'}</span>
                            </div>
                            <a href="${d.file_path || '#'}" target="_blank" class="btn btn-outline-primary btn-sm rounded-2 py-0.5 px-2.5">
                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View
                            </a>
                        </div>
                    </div>
                `).join('') : `
                    <div class="col-12 text-muted small p-2">
                        No supplementary PDF proofs or presentation decks uploaded.
                    </div>
                `}
            </div>
                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View
                            </a>
                        </div>
                    </div>
                `).join('') : `
                    <div class="col-12 text-muted small p-2">
                        No supplementary PDF proofs or presentation decks uploaded.
                    </div>
                `}
            </div>
        </div>
    `;

    container.innerHTML = html;
}

function applyReviewFormState(existing, isViewOnly) {
    // Recommendation Radios
    let targetRec = existing?.overall_recommendation || 'strongly_recommend';
    if (targetRec === 'neutral') targetRec = 'recommend_with_reservations';
    const matchingOption = document.querySelector(`.recommendation-option input[value="${targetRec}"]`);
    if (matchingOption) {
        selectRecommendation(targetRec, matchingOption.closest('.recommendation-option'));
    }

    // Strengths, Concerns, Remarks
    const elStrengths = document.getElementById('evalStrengths');
    const elConcerns = document.getElementById('evalConcerns');
    const elRemarks = document.getElementById('evalRemarks');

    if (elStrengths) elStrengths.value = existing?.strengths || '';
    if (elConcerns) elConcerns.value = existing?.concerns || '';
    if (elRemarks) elRemarks.value = existing?.final_remarks || '';

    // Conflict of Interest
    if (existing?.conflict_of_interest) {
        const confRadio = document.getElementById('conflictExists');
        if (confRadio) confRadio.checked = true;
        toggleConflictDetails(true);
        const confBox = document.getElementById('evalConflictDetails');
        if (confBox) confBox.value = existing.conflict_details || '';
    } else {
        const confRadio = document.getElementById('conflictNone');
        if (confRadio) confRadio.checked = true;
        toggleConflictDetails(false);
    }

    // Scores
    if (existing?.scores && Array.isArray(existing.scores)) {
        existing.scores.forEach(s => {
            const numInput = document.getElementById('input-score-' + s.criterion_id);
            if (numInput) numInput.value = s.score;
            const remInput = document.getElementById('remarks-' + s.criterion_id);
            if (remInput) remInput.value = s.remarks || '';
        });
    }

    // View-Only Toggle
    const allInputs = document.querySelectorAll('#scoringForm input, #scoringForm textarea');
    allInputs.forEach(inp => {
        inp.disabled = isViewOnly;
    });

    const options = document.querySelectorAll('.recommendation-option');
    options.forEach(opt => {
        if (isViewOnly) {
            opt.style.pointerEvents = 'none';
        } else {
            opt.style.pointerEvents = 'auto';
        }
    });

    // Read-only indicator banner
    let banner = document.getElementById('evalReadOnlyBanner');
    if (isViewOnly && existing) {
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'evalReadOnlyBanner';
            banner.className = 'alert alert-success d-flex align-items-center gap-2 py-2.5 px-3 mb-3 small rounded-3 border';
            const scoringPane = document.getElementById('tab-scoring');
            scoringPane.insertBefore(banner, scoringPane.firstChild);
        }
        banner.innerHTML = `
            <i class="fa-solid fa-circle-check fs-5"></i>
            <div>
                <strong>Completed Evaluation:</strong> You have submitted this candidate review. This form is displayed in read-only mode.
            </div>
        `;
        banner.style.display = 'flex';
    } else if (banner) {
        banner.style.display = 'none';
    }
}

function toggleConflictDetails(show) {
    const box = document.getElementById('conflictDetailsBox');
    if (box) box.style.display = show ? 'block' : 'none';
}

function showAddCustomCriterionPrompt() {
    const name = prompt('Enter the name of your custom evaluation criterion (e.g. "Startup Mentorship Track Record"):');
    if (!name || !name.trim()) return;

    const customId = 'custom_' + Date.now();
    const criteriaList = document.getElementById('criteriaList');
    if (!criteriaList) return;

    const newBox = document.createElement('div');
    newBox.className = 'p-3 bg-light rounded-4 border border-primary-subtle bg-primary-subtle bg-opacity-10 mb-3';
    newBox.id = 'crit-box-' + customId;
    newBox.innerHTML = `
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2">
            <div>
                <span class="fw-bold text-dark fs-6">${name.trim()}</span>
                <span class="badge bg-primary text-white border ms-2" style="font-size: 11px;">Custom Jury Metric</span>
                <p class="text-muted small mb-0 mt-1" style="font-size: 12px;">Dynamic criterion added by evaluation jury.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <label class="small text-muted fw-semibold me-1">Score:</label>
                <div class="input-group input-group-sm" style="width: 140px;">
                    <input 
                        type="number" 
                        class="form-control text-center fw-bold fs-6 text-primary score-number-input" 
                        min="0" 
                        max="10" 
                        step="0.5" 
                        value="8" 
                        data-criterion-id="${customId}"
                        data-criterion-name="${name.trim()}"
                        id="input-score-${customId}"
                        required
                    >
                    <span class="input-group-text bg-white text-muted fw-semibold" style="font-size: 12px;">/ 10 pts</span>
                </div>
            </div>
        </div>
        <div class="mt-2">
            <input type="text" class="form-control form-control-sm bg-white" placeholder="Optional notes/remarks on this criterion..." id="remarks-${customId}" data-criterion-id="${customId}">
        </div>
    `;
    criteriaList.appendChild(newBox);
}

// Handle Review Submission
async function handleReviewSubmit(e) {
    e.preventDefault();

    const assignmentId = document.getElementById('currentAssignmentId').value;
    if (!assignmentId) return;

    const strengths = document.getElementById('evalStrengths').value.trim();
    const concerns = document.getElementById('evalConcerns').value.trim();
    const remarks = document.getElementById('evalRemarks').value.trim();
    const recInput = document.querySelector('input[name="overall_recommendation"]:checked');
    let recommendation = recInput ? recInput.value : 'recommend';
    if (recommendation === 'neutral' || recommendation === 'reservations') {
        recommendation = 'recommend_with_reservations';
    }

    if (!strengths) {
        alert('Strengths are mandatory. Please provide candidate strengths.');
        document.getElementById('evalStrengths').focus();
        return;
    }

    if (!concerns) {
        alert('Concerns are mandatory. Please provide candidate concerns or limitations.');
        document.getElementById('evalConcerns').focus();
        return;
    }

    const conflictRadio = document.querySelector('input[name="juror_conflict_check"]:checked');
    const conflictExists = conflictRadio && conflictRadio.value === 'exists';
    const conflictDetails = conflictExists ? (document.getElementById('evalConflictDetails')?.value.trim() || 'Conflict disclosed') : null;

    // Collect scores (standard and dynamic custom criteria)
    const scoreInputs = document.querySelectorAll('.score-number-input[data-criterion-id]');
    const scores = [];
    scoreInputs.forEach(input => {
        const critId = input.getAttribute('data-criterion-id');
        const remEl = document.getElementById('remarks-' + critId);
        scores.push({
            criterion_id: critId,
            criterion_name: input.getAttribute('data-criterion-name') || '',
            score: parseFloat(input.value) || 0,
            remarks: remEl ? remEl.value.trim() : null
        });
    });

    const submitBtn = document.getElementById('btnSubmitEvaluation');
    const origText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

    try {
        const res = await fetch(`/admin/web/jury/review/${assignmentId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                overall_recommendation: recommendation,
                strengths: strengths,
                concerns: concerns,
                conflict_of_interest: conflictExists,
                conflict_details: conflictDetails,
                final_remarks: remarks,
                scores: scores
            })
        });

        const data = await res.json();

        if (res.ok && data.success) {
            // Update Table Row Dynamically
            const statusCol = document.getElementById('status-col-' + assignmentId);
            if (statusCol) {
                statusCol.innerHTML = `
                    <span class="badge-reviewed">
                        <i class="fa-solid fa-circle-check"></i> ✓ Reviewed
                    </span>
                `;
            }

            const actionCol = document.getElementById('action-col-' + assignmentId);
            if (actionCol) {
                actionCol.innerHTML = `
                    <button class="btn btn-view-review" onclick="event.stopPropagation(); openReviewModal('${assignmentId}', '', true)">
                        <i class="fa-solid fa-eye me-1"></i> View My Review
                    </button>
                `;
            }

            // Update KPI count
            const pendingEl = document.getElementById('kpiPending');
            const completedEl = document.getElementById('kpiCompleted');
            if (pendingEl && completedEl) {
                let p = parseInt(pendingEl.textContent) || 0;
                let c = parseInt(completedEl.textContent) || 0;
                if (p > 0) pendingEl.textContent = p - 1;
                completedEl.textContent = c + 1;
            }

            hideModalSafely('reviewModal');
            alert('✓ Candidate evaluation and scores submitted successfully!');
        } else {
            alert(data.message || 'Failed to submit review.');
        }
    } catch (err) {
        alert('Network error while submitting evaluation. Please try again.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
    }
}
</script>
</body>
</html>
