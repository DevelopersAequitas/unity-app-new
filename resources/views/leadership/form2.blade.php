<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Form 2: Jury Evaluation Data Form | Peers Global Leadership Selection</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --pg-primary: #1e3a8a;
            --pg-accent: #2563eb;
            --pg-dark: #0f172a;
            --pg-light: #f8fafc;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            min-height: 100vh;
        }
        .header-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            color: #ffffff;
            padding: 3rem 1rem 2.5rem;
            position: relative;
        }
        .header-card {
            max-width: 920px;
            margin: -2.5rem auto 2rem;
            position: relative;
            z-index: 10;
        }
        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        .section-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .section-badge {
            background-color: #eff6ff;
            color: #2563eb;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            border: 1px solid #dbeafe;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #1e293b;
            margin-bottom: 0.35rem;
        }
        .form-control, .form-select {
            border-radius: 0.6rem;
            border: 1px solid #cbd5e1;
            padding: 0.65rem 0.9rem;
            font-size: 0.925rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .bench-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .btn-pg-primary {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem 1.75rem;
            border-radius: 0.6rem;
            border: none;
            transition: all 0.2s;
        }
        .btn-pg-primary:hover {
            background-color: #1d4ed8;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .status-pill-committed {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <!-- Main Header -->
    <header class="header-bg text-center">
        <div class="container">
            <div class="d-inline-flex align-items-center gap-2 mb-2 px-3 py-1 bg-white bg-opacity-10 rounded-pill text-white small">
                <i class="bi bi-shield-check text-warning"></i>
                <span>Peers Global Community of Collaboration</span>
            </div>
            <h1 class="h2 fw-bold text-white mb-2">Form 2: Jury Evaluation Data Form</h1>
            <p class="text-white-50 mb-4 mx-auto" style="max-width: 650px;">
                Detailed Operational, Track Record & Leadership Bench Evidence for Shortlisted Nominees
            </p>
        </div>
    </header>

    <div class="container pb-5">
        <!-- Candidate Bar -->
        <div class="card card-custom header-card p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 52px; height: 52px;">
                        {{ strtoupper(substr($candidateName, 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h2 class="h5 fw-bold text-dark mb-0">{{ $candidateName }}</h2>
                            <span class="badge bg-primary text-white">{{ $roleName }}</span>
                        </div>
                        <p class="text-muted small mb-0 mt-0.5">
                            Application #: <strong class="text-dark font-monospace">{{ $nomination->application_number }}</strong> &bull; Campaign: {{ $campaignName }}
                        </p>
                    </div>
                </div>
                <div>
                    @if($isSubmitted)
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                            <i class="bi bi-check-circle-fill me-1"></i> Form 2 Submitted
                        </span>
                    @else
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fs-6">
                            <i class="bi bi-clock-history me-1"></i> Form 2 Pending
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if(session('success') || request()->has('submitted') || $isSubmitted)
            <!-- Submission Success Screen -->
            <div class="card card-custom p-4 p-md-5 text-center mx-auto" style="max-width: 800px;">
                <div class="mx-auto rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-check-lg fs-1"></i>
                </div>
                <h3 class="h4 fw-bold text-dark mb-2">Form 2 Successfully Submitted!</h3>
                <p class="text-muted mb-4 mx-auto" style="max-width: 550px;">
                    Thank you, <strong>{{ $candidateName }}</strong>. Your Jury Evaluation Data and Leadership Bench records have been securely stored in <strong>Jury Forms (2)</strong> and forwarded to the Scrutiny Committee and Jury Board.
                </p>
                <div class="bg-light p-3 rounded-3 border mb-4 text-start small font-monospace">
                    <div><strong>Reference ID:</strong> {{ $nomination->application_number }}-F2</div>
                    <div><strong>Role Evaluated:</strong> {{ $roleName }}</div>
                    <div><strong>Status:</strong> Under Jury Review</div>
                </div>
                <div>
                    <a href="/" class="btn btn-outline-primary px-4 py-2">Return to Peers Global</a>
                </div>
            </div>
        @else

            <!-- Step 1: Security Verification Gate -->
            <div id="verificationGate" class="card card-custom p-4 p-md-5 mx-auto" style="max-width: 600px;">
                <div class="text-center mb-4">
                    <div class="mx-auto rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-shield-lock-fill fs-2"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-1">Identity & Email Verification</h3>
                    <p class="text-muted small mb-0">
                        To prevent application mismatching, please enter the registered email address used during your initial nomination for <strong>{{ $candidateName }}</strong>.
                    </p>
                </div>

                <div id="emailVerifyAlert" class="alert alert-danger d-none py-2 px-3 small rounded-3" role="alert"></div>

                <div class="mb-3">
                    <label class="form-label" for="verifyEmailInput">Registered Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" id="verifyEmailInput" class="form-control" placeholder="name@example.com" required>
                    </div>
                    <div class="form-text small">Must match the email on your nomination application.</div>
                </div>

                <button type="button" id="btnVerifyEmail" class="btn btn-pg-primary w-100 py-2.5">
                    <span id="verifyBtnSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                    <i class="bi bi-arrow-right-circle me-1" id="verifyBtnIcon"></i> Verify & Unlock Form 2
                </button>
            </div>

            <!-- Step 2: The Main Form 2 (Hidden until email verified) -->
            <form id="mainForm2" method="POST" action="{{ route('leadership.form2.submit', ['id' => $nomination->id]) }}" enctype="multipart/form-data" class="d-none">
                @csrf
                <input type="hidden" name="email" id="verifiedEmailHidden" value="">

                <!-- Section A: Business Credibility -->
                <div class="card card-custom p-4 p-md-5 mb-4">
                    <div class="section-header">
                        <span class="section-badge">Section A</span>
                        <h3 class="h5 fw-bold text-dark mb-0">Business Credibility</h3>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Business Type <span class="text-danger">*</span></label>
                            <input type="text" name="business_type" class="form-control" placeholder="e.g. Pvt Ltd, LLP, Partnership, Prop" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year Founded <span class="text-danger">*</span></label>
                            <input type="number" name="year_founded" class="form-control" placeholder="e.g. 2018" min="1950" max="2026" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Annual Turnover Band <span class="text-danger">*</span></label>
                            <select name="turnover_band" class="form-select" required>
                                <option value="" disabled selected>Select Turnover Band (dropdown range)</option>
                                <option value="Under ₹50 Lakhs">Under ₹50 Lakhs</option>
                                <option value="₹50 Lakhs - ₹1 Crore">₹50 Lakhs - ₹1 Crore</option>
                                <option value="₹1 Crore - ₹5 Crores">₹1 Crore - ₹5 Crores</option>
                                <option value="₹5 Crores - ₹25 Crores">₹5 Crores - ₹25 Crores</option>
                                <option value="₹25 Crores+">₹25 Crores+</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Team Size <span class="text-danger">*</span></label>
                            <input type="text" name="team_size" class="form-control" placeholder="e.g. 15 full-time employees" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Top 3 Business Achievements <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="text" name="achievement_1" class="form-control" placeholder="1. Key milestone or achievement..." required>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="achievement_2" class="form-control" placeholder="2. Expansion, market penetration, or client milestone..." required>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="achievement_3" class="form-control" placeholder="3. Industry award, patent, or recognition..." required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Business Registration Proof (GST / Incorporation) <span class="text-danger">*</span></label>
                            <input type="file" name="business_proof_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            <div class="form-text small">Accepted formats: PDF, JPG, PNG (Max 10MB)</div>
                        </div>
                    </div>
                </div>

                <!-- Section B: Leadership Track Record -->
                <div class="card card-custom p-4 p-md-5 mb-4">
                    <div class="section-header">
                        <span class="section-badge">Section B</span>
                        <h3 class="h5 fw-bold text-dark mb-0">Leadership Track Record</h3>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Leadership Roles Held <span class="text-danger">*</span></label>
                            <textarea name="leadership_roles_held" rows="3" class="form-control" placeholder="List organisation, role title, duration/years, and size of team led..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">The largest community or group you have built or grown <span class="text-danger">*</span></label>
                            <textarea name="largest_community" rows="3" class="form-control" placeholder="What it was, its size, and your exact leadership role..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Leaders You Have Developed (Name 2 people who grew because of you) <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" name="developed_leader_1" class="form-control" placeholder="Leader 1: Name, initial role & growth achieved" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="developed_leader_2" class="form-control" placeholder="Leader 2: Name, initial role & growth achieved" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">A Failure as a Leader, and what you learned <span class="text-danger">*</span></label>
                            <textarea name="leadership_failure_lesson" rows="3" class="form-control" placeholder="Describe a difficult challenge or failure, and the leadership insights you gained..." required></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section C: Network Strength -->
                <div class="card card-custom p-4 p-md-5 mb-4">
                    <div class="section-header">
                        <span class="section-badge">Section C</span>
                        <h3 class="h5 fw-bold text-dark mb-0">Network Strength</h3>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Business Associations / Chambers you belong to <span class="text-danger">*</span></label>
                            <input type="text" name="associations" class="form-control" placeholder="e.g. CII, FICCI, BNI, Rotary, TiE, Merchants Chamber..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Institutions You Can Access <span class="text-danger">*</span></label>
                            <input type="text" name="institutions_access" class="form-control" placeholder="Universities/colleges, banks, government bodies, media outlets..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Potential Sponsors or Strategic Partners you can bring <span class="text-danger">*</span></label>
                            <textarea name="potential_sponsors" rows="2" class="form-control" placeholder="Name organizations and partnership types..." required></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section D: Leadership Bench, "Who will you bring?" -->
                <div class="card card-custom p-4 p-md-5 mb-4">
                    <div class="section-header">
                        <span class="section-badge">Section D</span>
                        <h3 class="h5 fw-bold text-dark mb-0">Leadership Bench &mdash; "Who will you bring?"</h3>
                    </div>

                    <div class="alert alert-primary rounded-3 border-0 p-3 mb-4">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-info-circle-fill fs-5 text-primary"></i>
                            <h4 class="h6 fw-bold mb-0 text-dark">Target Role Bench Requirement: {{ $benchRequirement['target_role'] }}</h4>
                        </div>
                        <p class="small text-muted mb-0">
                            {{ $benchRequirement['description'] }} Jurors heavily weight <strong>Committed</strong> names who can be contacted and verified.
                        </p>
                    </div>

                    <div id="benchContainer">
                        <!-- Default Bench Item 1 -->
                        <div class="bench-card">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold small text-dark bench-index-label">#1 Proposed Leader</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="bench[0][name]" class="form-control form-control-sm" placeholder="Leader Full Name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Mobile Number <span class="text-danger">*</span></label>
                                    <input type="tel" name="bench[0][mobile]" class="form-control form-control-sm" placeholder="+91 9876543210" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">City <span class="text-danger">*</span></label>
                                    <input type="text" name="bench[0][city]" class="form-control form-control-sm" placeholder="City / District" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Industry / Business</label>
                                    <input type="text" name="bench[0][industry]" class="form-control form-control-sm" placeholder="e.g. IT, Manufacturing">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Relationship <span class="text-danger">*</span></label>
                                    <input type="text" name="bench[0][relationship]" class="form-control form-control-sm" placeholder="Client, friend, association colleague" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Your Status with Them <span class="text-danger">*</span></label>
                                    <select name="bench[0][status]" class="form-select form-select-sm" required>
                                        <option value="Committed">Committed (Confirmed)</option>
                                        <option value="Spoken, interested">Spoken, interested</option>
                                        <option value="Not yet approached">Not yet approached</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Proposed Role or Circle</label>
                                    <input type="text" name="bench[0][proposed_role]" class="form-control form-control-sm" placeholder="e.g. Industry Director / Circle Founder">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Expected Onboarding Timeline <span class="text-danger">*</span></label>
                                    <select name="bench[0][timeline]" class="form-select form-select-sm" required>
                                        <option value="30 days">Within 30 Days</option>
                                        <option value="90 days">Within 90 Days</option>
                                        <option value="180 days">Within 180 Days</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="btnAddBenchLeader" class="btn btn-outline-primary btn-sm rounded-pill mb-4">
                        <i class="bi bi-plus-circle me-1"></i> Add Another Leader to Bench
                    </button>

                    <div class="form-check p-3 bg-light rounded-3 border">
                        <input class="form-check-input" type="checkbox" name="bench_declaration" id="benchDeclaration" required>
                        <label class="form-check-label fw-semibold small text-dark" for="benchDeclaration">
                            I understand Peers Global may contact the people named here to verify interest and commitment. <span class="text-danger">*</span>
                        </label>
                    </div>
                </div>

                <!-- Section E: 12-Month Plan -->
                <div class="card card-custom p-4 p-md-5 mb-4">
                    <div class="section-header">
                        <span class="section-badge">Section E</span>
                        <h3 class="h5 fw-bold text-dark mb-0">12-Month Strategic Plan</h3>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Targets at 6 Months <span class="text-danger">*</span></label>
                            <input type="text" name="targets_6_months" class="form-control" placeholder="Target number of Circles, IDs, or Peers..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Targets at 12 Months <span class="text-danger">*</span></label>
                            <input type="text" name="targets_12_months" class="form-control" placeholder="12-month expansion milestone..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">First 90 Days: Three Concrete Actions <span class="text-danger">*</span></label>
                            <input type="text" name="plan_action_1" class="form-control mb-2" placeholder="Action 1: Immediate first 30 days milestone..." required>
                            <input type="text" name="plan_action_2" class="form-control mb-2" placeholder="Action 2: Bench mobilization and circle launches..." required>
                            <input type="text" name="plan_action_3" class="form-control" placeholder="Action 3: Day 60-90 consolidation and scale..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">How you will fund your time and operations in Year 1 <span class="text-danger">*</span></label>
                            <textarea name="funding_operations" rows="2" class="form-control" placeholder="Explain your operational sustainability..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Biggest risk to your plan, and how you will handle it <span class="text-danger">*</span></label>
                            <textarea name="biggest_risk_mitigation" rows="2" class="form-control" placeholder="Identify potential bottlenecks and risk mitigation strategy..." required></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section F: Commitment & Fit -->
                <div class="card card-custom p-4 p-md-5 mb-4">
                    <div class="section-header">
                        <span class="section-badge">Section F</span>
                        <h3 class="h5 fw-bold text-dark mb-0">Commitment & Culture Fit</h3>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Hours per week available <span class="text-danger">*</span></label>
                            <input type="text" name="weekly_hours" class="form-control" placeholder="e.g. 10 - 15 hours/week" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Typical week availability schedule</label>
                            <input type="text" name="typical_availability" class="form-control" placeholder="e.g. Weekday mornings, Saturday afternoons">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Other commitments that compete for time</label>
                            <input type="text" name="competing_commitments" class="form-control" placeholder="Existing business duties, board seats, etc.">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Why Peers Global and not another network? <span class="text-danger">*</span></label>
                            <textarea name="why_peers_global" rows="2" class="form-control" placeholder="Alignment with Community of Collaboration values..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Understanding of the role: "In your words, what does a {{ $roleName }} do?" <span class="text-danger">*</span></label>
                            <textarea name="role_understanding" rows="2" class="form-control" placeholder="Your interpretation of responsibilities..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Acceptance that the role is performance-based, with no fixed tenure <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="performance_acceptance" id="acceptanceYes" value="yes" required>
                                    <label class="form-check-label fw-semibold" for="acceptanceYes">Yes, I accept</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="performance_acceptance" id="acceptanceNo" value="no">
                                    <label class="form-check-label" for="acceptanceNo">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section G: References -->
                <div class="card card-custom p-4 p-md-5 mb-4">
                    <div class="section-header">
                        <span class="section-badge">Section G</span>
                        <h3 class="h5 fw-bold text-dark mb-0">Professional References</h3>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <h4 class="h6 fw-bold text-primary mb-2">Reference 1: Business Reference (Mandatory)</h4>
                                <input type="text" name="ref1_name" class="form-control form-control-sm mb-2" placeholder="Full Name" required>
                                <input type="text" name="ref1_relationship" class="form-control form-control-sm mb-2" placeholder="Business Relationship (e.g. Client, Board Member)" required>
                                <input type="tel" name="ref1_mobile" class="form-control form-control-sm mb-2" placeholder="Mobile Number" required>
                                <input type="text" name="ref1_years" class="form-control form-control-sm" placeholder="Years Known (e.g. 5 years)" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <h4 class="h6 fw-bold text-primary mb-2">Reference 2: Professional / Association Reference</h4>
                                <input type="text" name="ref2_name" class="form-control form-control-sm mb-2" placeholder="Full Name" required>
                                <input type="text" name="ref2_relationship" class="form-control form-control-sm mb-2" placeholder="Relationship" required>
                                <input type="tel" name="ref2_mobile" class="form-control form-control-sm mb-2" placeholder="Mobile Number" required>
                                <input type="text" name="ref2_years" class="form-control form-control-sm" placeholder="Years Known" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section H: Documents -->
                <div class="card card-custom p-4 p-md-5 mb-4">
                    <div class="section-header">
                        <span class="section-badge">Section H</span>
                        <h3 class="h5 fw-bold text-dark mb-0">Confidential Documents (Admin & Jury View Only)</h3>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Identity Proof (Masked Aadhaar / Passport / DL) <span class="text-danger">*</span></label>
                            <input type="file" name="identity_proof_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Profile or Pitch Deck (Optional)</label>
                            <input type="file" name="deck_file" class="form-control" accept=".pdf,.ppt,.pptx">
                        </div>
                    </div>
                </div>

                <!-- Submit Button Bar -->
                <div class="card card-custom p-4 text-center">
                    <p class="text-muted small mb-3">
                        By submitting Form 2, you affirm that all business numbers, track record data, and leadership bench references are accurate and true.
                    </p>
                    <div>
                        <button type="submit" class="btn btn-pg-primary px-5 py-3 fs-6">
                            <i class="bi bi-send-check-fill me-2"></i> Submit Form 2: Jury Evaluation Data
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const verifyBtn = document.getElementById('btnVerifyEmail');
            const verifyInput = document.getElementById('verifyEmailInput');
            const verifyAlert = document.getElementById('emailVerifyAlert');
            const verifyGate = document.getElementById('verificationGate');
            const mainForm = document.getElementById('mainForm2');
            const hiddenEmail = document.getElementById('verifiedEmailHidden');
            const spinner = document.getElementById('verifyBtnSpinner');
            const btnIcon = document.getElementById('verifyBtnIcon');

            if (verifyBtn) {
                verifyBtn.addEventListener('click', async function () {
                    const email = verifyInput.value.trim();
                    if (!email) {
                        verifyAlert.textContent = 'Please enter your registered email address.';
                        verifyAlert.classList.remove('d-none');
                        return;
                    }

                    spinner.classList.remove('d-none');
                    btnIcon.classList.add('d-none');
                    verifyAlert.classList.add('d-none');

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const response = await fetch("{{ route('leadership.form2.verify-email', ['id' => $nomination->id]) }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({ email: email })
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            hiddenEmail.value = email;
                            verifyGate.classList.add('d-none');
                            mainForm.classList.remove('d-none');
                            window.scrollTo({ top: 350, behavior: 'smooth' });
                        } else {
                            verifyAlert.textContent = data.message || 'Email does not match this nomination application.';
                            verifyAlert.classList.remove('d-none');
                        }
                    } catch (err) {
                        verifyAlert.textContent = 'Verification server error. Please try again.';
                        verifyAlert.classList.remove('d-none');
                    } finally {
                        spinner.classList.add('d-none');
                        btnIcon.classList.remove('d-none');
                    }
                });
            }

            // Bench Leader Repeater
            const benchContainer = document.getElementById('benchContainer');
            const addBenchBtn = document.getElementById('btnAddBenchLeader');
            let benchCount = 1;

            if (addBenchBtn && benchContainer) {
                addBenchBtn.addEventListener('click', function () {
                    benchCount++;
                    const card = document.createElement('div');
                    card.className = 'bench-card';
                    card.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold small text-dark bench-index-label">#${benchCount} Proposed Leader</span>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 remove-bench-btn" title="Remove">&times;</button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="bench[${benchCount - 1}][name]" class="form-control form-control-sm" placeholder="Leader Full Name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Mobile Number <span class="text-danger">*</span></label>
                                <input type="tel" name="bench[${benchCount - 1}][mobile]" class="form-control form-control-sm" placeholder="+91 9876543210" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">City <span class="text-danger">*</span></label>
                                <input type="text" name="bench[${benchCount - 1}][city]" class="form-control form-control-sm" placeholder="City / District" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Industry / Business</label>
                                <input type="text" name="bench[${benchCount - 1}][industry]" class="form-control form-control-sm" placeholder="e.g. IT, Manufacturing">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Relationship <span class="text-danger">*</span></label>
                                <input type="text" name="bench[${benchCount - 1}][relationship]" class="form-control form-control-sm" placeholder="Client, friend, association colleague" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Your Status with Them <span class="text-danger">*</span></label>
                                <select name="bench[${benchCount - 1}][status]" class="form-select form-select-sm" required>
                                    <option value="Committed">Committed (Confirmed)</option>
                                    <option value="Spoken, interested">Spoken, interested</option>
                                    <option value="Not yet approached">Not yet approached</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Proposed Role or Circle</label>
                                <input type="text" name="bench[${benchCount - 1}][proposed_role]" class="form-control form-control-sm" placeholder="e.g. Industry Director / Circle Founder">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Expected Onboarding Timeline <span class="text-danger">*</span></label>
                                <select name="bench[${benchCount - 1}][timeline]" class="form-select form-select-sm" required>
                                    <option value="30 days">Within 30 Days</option>
                                    <option value="90 days">Within 90 Days</option>
                                    <option value="180 days">Within 180 Days</option>
                                </select>
                            </div>
                        </div>
                    `;
                    benchContainer.appendChild(card);

                    card.querySelector('.remove-bench-btn').addEventListener('click', function () {
                        card.remove();
                    });
                });
            }
        });
    </script>
</body>
</html>
