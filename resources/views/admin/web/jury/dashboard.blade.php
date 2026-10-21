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

<!-- Top Navigation -->
<nav class="navbar navbar-jury">
    <div class="container-xl d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.web.jury.dashboard') }}" class="d-inline-flex align-items-center text-decoration-none">
                <img src="{{ asset('images/peersglobal-logo.png') }}" alt="PeersGlobal" style="height: 44px; width: auto;" class="d-inline-block" />
            </a>
            <div class="border-start border-white border-opacity-25 ps-3 ms-1 d-flex flex-column justify-content-center">
                <span class="text-white fw-bold fs-6 tracking-tight d-block" style="line-height: 1.25;">Leadership Selection</span>
                <span class="text-white-50 small" style="font-size: 11.5px;">Official Jury Evaluation Portal</span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <span class="text-white fw-semibold small d-block">{{ $juror->display_name ?: ($juror->first_name.' '.$juror->last_name) }}</span>
                <span class="text-white-50" style="font-size: 11.5px;">{{ $juror->email }}</span>
            </div>
            <a href="{{ route('admin.web.jury.logout.get') }}" class="btn btn-outline-light btn-sm rounded-pill px-3 py-1.5">
                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Logout
            </a>
        </div>
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
                            <tr id="row-{{ $asg->id }}" data-nomination-id="{{ $nom?->id }}">
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
                                            onclick="openReviewModal('{{ $asg->id }}', '{{ $nom?->id }}', true)"
                                        >
                                            <i class="fa-solid fa-eye me-1"></i> View My Review
                                        </button>
                                    @else
                                        <button 
                                            class="btn btn-review"
                                            onclick="openReviewModal('{{ $asg->id }}', '{{ $nom?->id }}', false)"
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
                            <!-- MAIN POINT 1: Candidate Executive Profile & Business Foundation -->
                            <div class="bg-white p-4 rounded-4 border shadow-xs">
                                <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                    <span class="badge bg-primary text-white font-monospace px-2.5 py-1">MAIN POINT 1</span>
                                    <h6 class="fw-bold text-dark mb-0">Candidate Profile & Enterprise Foundation</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Sub-Point 1.1: Candidate Name</span>
                                        <span class="fw-bold text-dark fs-6" id="f1CandidateName">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Sub-Point 1.2: Application Number</span>
                                        <span class="badge bg-light text-primary border fw-bold px-2.5 py-1" id="f1AppNumber">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Sub-Point 1.3: Applied Role</span>
                                        <span class="fw-bold text-dark" id="f1Role">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Sub-Point 1.4: Circle / Scope</span>
                                        <span class="fw-bold text-dark" id="f1Scope">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Sub-Point 1.5: Company / Venture Name</span>
                                        <span class="fw-bold text-dark" id="f1Company">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Sub-Point 1.6: Executive Designation</span>
                                        <span class="fw-bold text-dark" id="f1Designation">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Sub-Point 1.7: Business Category</span>
                                        <span class="badge bg-indigo-subtle text-indigo fw-semibold px-2.5 py-1" id="f1Category">—</span>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <span class="text-muted small d-block">Sub-Point 1.8: Contact Information</span>
                                        <span class="text-dark small d-block text-truncate" id="f1Email">—</span>
                                        <span class="text-muted small" id="f1Phone">—</span>
                                    </div>
                                </div>
                            </div>

                            <!-- MAIN POINT 2: Strategic Vision for the Leadership Role -->
                            <div class="bg-white p-4 rounded-4 border shadow-xs">
                                <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                    <span class="badge text-white font-monospace px-2.5 py-1" style="background-color: #4f46e5;">MAIN POINT 2</span>
                                    <h6 class="fw-bold text-dark mb-0">Strategic Vision for the Leadership Role</h6>
                                </div>
                                <div class="p-3 rounded-3 border-start border-4 border-primary bg-light">
                                    <span class="text-muted small d-block fw-semibold mb-1">Sub-Point 2.1: Vision Statement for this Term</span>
                                    <p class="text-dark fst-italic mb-0" id="f1Vision" style="font-size: 14px; line-height: 1.6;">
                                        No vision statement recorded.
                                    </p>
                                </div>
                            </div>

                            <!-- MAIN POINT 3: Professional Experience & Track Record -->
                            <div class="bg-white p-4 rounded-4 border shadow-xs">
                                <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                    <span class="badge bg-dark text-white font-monospace px-2.5 py-1">MAIN POINT 3</span>
                                    <h6 class="fw-bold text-dark mb-0">Professional Experience & Track Record</h6>
                                </div>
                                <div class="p-3 bg-light rounded-3">
                                    <span class="text-muted small d-block fw-semibold mb-1">Sub-Point 3.1: Executive Biography & Highlights</span>
                                    <div class="text-dark small" id="f1Bio" style="line-height: 1.6; white-space: pre-line;">
                                        No bio submitted.
                                    </div>
                                </div>
                            </div>

                            <!-- MAIN POINT 4: Form 1 Questionnaire Submissions (Optional) -->
                            <div class="bg-white p-4 rounded-4 border shadow-xs" id="f1DynamicQuestionsCard" style="display: none;">
                                <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                    <span class="badge bg-secondary text-white font-monospace px-2.5 py-1">MAIN POINT 4</span>
                                    <h6 class="fw-bold text-dark mb-0">Form 1 Questionnaire Responses</h6>
                                </div>
                                <div id="f1QuestionsList" class="d-flex flex-column gap-2"></div>
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
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Criteria Evaluation (Scoring)</h6>
                                
                                <div id="criteriaList">
                                    @foreach($criteria as $crit)
                                        <div class="score-slider-box">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div>
                                                    <span class="fw-bold text-dark">{{ $crit->name }}</span>
                                                    <span class="badge bg-light text-muted border ms-2" style="font-size: 11px;">Weight: {{ $crit->weightage }}%</span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold text-primary fs-5" id="score-val-{{ $crit->id }}">8</span>
                                                    <span class="text-muted small">/ {{ $crit->max_score }} pts</span>
                                                </div>
                                            </div>
                                            <input 
                                                type="range" 
                                                class="form-range" 
                                                min="0" 
                                                max="{{ $crit->max_score }}" 
                                                step="0.5" 
                                                value="8" 
                                                data-criterion-id="{{ $crit->id }}"
                                                id="range-{{ $crit->id }}"
                                                oninput="updateScoreDisplay('{{ $crit->id }}', this.value)"
                                            >
                                            <p class="text-muted small mb-0 mt-1" style="font-size: 12px;">{{ $crit->description ?: 'Score candidate competence and demonstrated excellence on this criterion.' }}</p>
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
                                        <div class="recommendation-option" onclick="selectRecommendation('neutral', this)">
                                            <input type="radio" name="overall_recommendation" value="neutral" class="d-none">
                                            <div class="fw-bold text-warning mb-1"><i class="fa-solid fa-circle-question me-1"></i> Neutral</div>
                                            <p class="text-muted mb-0" style="font-size: 11.5px;">Borderline; requires further committee scrutiny.</p>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const reviewModalEl = document.getElementById('reviewModal');
const reviewModal = new bootstrap.Modal(reviewModalEl);

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

// Open Review Modal
async function openReviewModal(assignmentId, nominationId, isViewOnly) {
    document.getElementById('currentAssignmentId').value = assignmentId;
    
    if (!nominationId) {
        const row = document.getElementById('row-' + assignmentId);
        if (row) nominationId = row.getAttribute('data-nomination-id');
    }

    // Switch to Form 1 tab by default
    const tab1Btn = document.getElementById('tab-form1-btn');
    if (tab1Btn) {
        bootstrap.Tab.getOrCreateInstance(tab1Btn).show();
    }

    // Set submit button state
    const submitBtn = document.getElementById('btnSubmitEvaluation');
    if (isViewOnly) {
        submitBtn.style.display = 'none';
    } else {
        submitBtn.style.display = 'inline-block';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-check me-1.5"></i> Submit Final Review';
    }

    reviewModal.show();

    // Reset Form 2 container with loading
    const form2Container = document.getElementById('form2Container');
    if (form2Container) {
        form2Container.innerHTML = `
            <div class="text-center py-5 text-muted bg-white rounded-4 border">
                <span class="spinner-border spinner-border-sm me-2"></span> Loading Form 2 operational dossier...
            </div>
        `;
    }

    // Fetch candidate details
    try {
        const res = await fetch(`/admin/web/jury/nomination/${nominationId}/details?assignment_id=${assignmentId}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            populateCandidateDetails(data.data, isViewOnly);
        }
    } catch (err) {
        console.error('Failed to load candidate details', err);
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
                const qText = ans.question?.question_text || `Question ${idx + 1}`;
                const valText = ans.answer_text || (Array.isArray(ans.selected_options) ? ans.selected_options.join(', ') : '—');
                qHtml += `
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small d-block fw-semibold mb-1">Sub-Point 4.${idx + 1}: ${qText}</span>
                        <p class="fw-semibold text-dark mb-0 small">${valText}</p>
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

    const benchList = Array.isArray(secD?.bench_members)
        ? secD.bench_members
        : (Array.isArray(secD?.bench) ? secD.bench : []);

    let html = '';

    // MAIN POINT 1: Section A - Business Credibility & Enterprise Metrics
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <span class="badge bg-primary text-white font-monospace px-2.5 py-1">MAIN POINT 1</span>
                <h6 class="fw-bold text-dark mb-0">Section A: Business Credibility & Enterprise Metrics</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <span class="text-muted small d-block">Sub-Point 1.1: Business Structure</span>
                    <span class="fw-bold text-dark">${secA?.business_type || '—'}</span>
                </div>
                <div class="col-md-6 col-lg-3">
                    <span class="text-muted small d-block">Sub-Point 1.2: Year Founded</span>
                    <span class="fw-bold text-dark">${secA?.year_founded || '—'}</span>
                </div>
                <div class="col-md-6 col-lg-3">
                    <span class="text-muted small d-block">Sub-Point 1.3: Annual Turnover Band</span>
                    <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1">
                        ${secA?.annual_turnover_band || '—'}
                    </span>
                </div>
                <div class="col-md-6 col-lg-3">
                    <span class="text-muted small d-block">Sub-Point 1.4: Enterprise Team Size</span>
                    <span class="fw-bold text-dark">${secA?.team_size || '—'}</span>
                </div>
                <div class="col-12 col-md-8">
                    <span class="text-muted small d-block">Sub-Point 1.5: Top Business Achievements</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${secA?.achievements || secA?.top_3_achievements || 'No specific achievements noted.'}
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <span class="text-muted small d-block">Sub-Point 1.6: Business Registration Proof</span>
                    ${secA?.registration_doc_url || rawF2?.business_proof_url ? `
                        <a href="${secA?.registration_doc_url || rawF2?.business_proof_url}" target="_blank" class="btn btn-outline-primary btn-sm mt-2 rounded-3 px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-file-invoice"></i> View Registration Certificate
                        </a>
                    ` : '<span class="text-muted small mt-2 d-inline-block">No document uploaded</span>'}
                </div>
            </div>
        </div>
    `;

    // MAIN POINT 2: Section B - Leadership Track Record & Crisis Governance
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <span class="badge bg-success text-white font-monospace px-2.5 py-1">MAIN POINT 2</span>
                <h6 class="fw-bold text-dark mb-0">Section B: Leadership Scale & Crisis Governance</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted small d-block">Sub-Point 2.1: Largest Community / Workforce Led</span>
                    <span class="fw-bold text-dark">${secB?.largest_community || '—'}</span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Sub-Point 2.2: Measurable Impact Metric</span>
                    <span class="fw-bold text-dark">${secB?.peak_impact_metric || '—'}</span>
                </div>
                <div class="col-12">
                    <span class="text-muted small d-block">Sub-Point 2.3: Real Crisis Handled & Resolution Outcome</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${secB?.crisis_handled || 'No crisis case reported.'}
                    </div>
                </div>
            </div>
        </div>
    `;

    // MAIN POINT 3: Section C - Ecosystem Influence & Industry Networks
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <span class="badge bg-info text-white font-monospace px-2.5 py-1">MAIN POINT 3</span>
                <h6 class="fw-bold text-dark mb-0">Section C: Ecosystem Influence & Strategic Networks</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted small d-block">Sub-Point 3.1: Industry Associations & Board Memberships</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${secC?.industry_associations || '—'}
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Sub-Point 3.2: CXO, Investor & Institutional Network</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${secC?.cxo_network || '—'}
                    </div>
                </div>
            </div>
        </div>
    `;

    // MAIN POINT 4: Section D - Leadership Bench & Successor Pool
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <span class="badge bg-warning text-dark font-monospace px-2.5 py-1">MAIN POINT 4</span>
                <h6 class="fw-bold text-dark mb-0">Section D: Leadership Bench & Successor Pool</h6>
            </div>
            ${benchList.length > 0 ? `
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Sub-Point 4.1: Nominated Leader</th>
                                <th>Current Designation / Venture</th>
                                <th>Proposed Leadership Role</th>
                                <th>Readiness / Endorsement Note</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            ${benchList.map(b => `
                                <tr>
                                    <td class="fw-bold text-dark">${b.name || '—'}</td>
                                    <td>${b.current_designation || b.designation || '—'}</td>
                                    <td><span class="badge bg-indigo-subtle text-indigo px-2 py-1">${b.proposed_role || b.role || '—'}</span></td>
                                    <td class="text-muted">${b.endorsement || b.notes || '—'}</td>
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

    // MAIN POINT 5: Section E - 12-Month Strategic Execution Roadmap
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <span class="badge text-white font-monospace px-2.5 py-1" style="background-color: #7c3aed;">MAIN POINT 5</span>
                <h6 class="fw-bold text-dark mb-0">Section E: 12-Month Strategic Execution Roadmap</h6>
            </div>
            <div class="p-3 rounded-3 bg-light border small text-dark" style="white-space: pre-line; line-height: 1.6;">
                ${secE?.twelve_month_plan || secE?.plan || 'No 12-month execution roadmap provided.'}
            </div>
        </div>
    `;

    // MAIN POINT 6: Section F & G - Commitment, Compliance & Professional References
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <span class="badge bg-secondary text-white font-monospace px-2.5 py-1">MAIN POINT 6</span>
                <h6 class="fw-bold text-dark mb-0">Section F & G: Commitment, Ethics & References</h6>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted small d-block">Sub-Point 6.1: Weekly Dedicated Hours</span>
                    <span class="fw-bold text-dark">${secF?.hours_per_week || secF?.commitment_hours || '10+'} hours/week</span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Sub-Point 6.2: Conflict of Interest Disclosure</span>
                    <span class="fw-bold text-dark">${secF?.conflict_disclosure || 'None declared'}</span>
                </div>
                <div class="col-12">
                    <span class="text-muted small d-block">Sub-Point 6.3: Professional Referees</span>
                    <div class="p-3 rounded-3 bg-light border small text-dark mt-1" style="white-space: pre-line;">
                        ${secG?.references || 'No external references attached.'}
                    </div>
                </div>
            </div>
        </div>
    `;

    // MAIN POINT 7: Section H - Verified Supporting Documents & Pitch Decks
    html += `
        <div class="bg-white p-4 rounded-4 border shadow-xs">
            <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                <span class="badge bg-dark text-white font-monospace px-2.5 py-1">MAIN POINT 7</span>
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
        </div>
    `;

    container.innerHTML = html;
}

function applyReviewFormState(existing, isViewOnly) {
    // Recommendation Radios
    const targetRec = existing?.overall_recommendation || 'strongly_recommend';
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

    // Scores
    if (existing?.scores && Array.isArray(existing.scores)) {
        existing.scores.forEach(s => {
            const range = document.getElementById('range-' + s.criterion_id);
            if (range) {
                range.value = s.score;
                updateScoreDisplay(s.criterion_id, s.score);
            }
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

// Handle Review Submission
async function handleReviewSubmit(e) {
    e.preventDefault();

    const assignmentId = document.getElementById('currentAssignmentId').value;
    if (!assignmentId) return;

    const strengths = document.getElementById('evalStrengths').value.trim();
    const concerns = document.getElementById('evalConcerns').value.trim();
    const remarks = document.getElementById('evalRemarks').value.trim();
    const recInput = document.querySelector('input[name="overall_recommendation"]:checked');
    const recommendation = recInput ? recInput.value : 'recommend';

    // Collect scores
    const scoreInputs = document.querySelectorAll('input[type="range"][data-criterion-id]');
    const scores = [];
    scoreInputs.forEach(input => {
        scores.push({
            criterion_id: input.getAttribute('data-criterion-id'),
            score: parseFloat(input.value)
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
                    <button class="btn btn-view-review" onclick="openReviewModal('${assignmentId}', '', true)">
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

            reviewModal.hide();
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
