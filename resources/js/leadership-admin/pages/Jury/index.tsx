import React, { useEffect, useState, useMemo } from 'react';
import { juryApi, campaignApi, nominationApi } from '../../api/services';
import { JuryAssignment, EvaluationCriterion, Campaign, Nomination } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { Plus, Users, Award, Trash2, Calendar, Scale, Clock, CheckCircle, Eye, Search } from 'lucide-react';

export const JuryPage: React.FC = () => {
    const [assignments, setAssignments] = useState<JuryAssignment[]>([]);
    const [criteria, setCriteria] = useState<EvaluationCriterion[]>([]);
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [candidates, setCandidates] = useState<Nomination[]>([]);
    const [members, setMembers] = useState<any[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [activeTab, setActiveTab] = useState<'assignments' | 'criteria'>('assignments');
    const [error, setError] = useState<string | null>(null);

    // Modal state for Assigning Jury
    const [isAssignModal, setIsAssignModal] = useState<boolean>(false);
    const [jurorSearch, setJurorSearch] = useState<string>('');
    const [assignData, setAssignData] = useState({
        campaign_id: '',
        nomination_id: 'all',
        jury_user_id: '',
        deadline: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 16),
        remarks: '',
    });

    // Modal state for viewing completed jury review
    const [selectedReview, setSelectedReview] = useState<any | null>(null);

    // Modal state for Criteria
    const [isCriterionModal, setIsCriterionModal] = useState<boolean>(false);
    const [criterionData, setCriterionData] = useState({
        name: '',
        category: 'Business Credibility',
        description: '',
        max_score: 10,
        weightage: 20,
        sort_order: 1,
    });

    const loadData = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const [assignRes, critRes, campRes, nomRes, memRes] = await Promise.all([
                juryApi.getAssignments(),
                juryApi.getCriteria(),
                campaignApi.getAll(),
                nominationApi.getAll({ status: 'shortlisted' }).catch(() => nominationApi.getAll()),
                juryApi.getMembers({ per_page: 250 }).catch(() => ({ data: { data: [] } })),
            ]);
            setAssignments(assignRes.data.data || []);
            setCriteria(critRes.data.data || []);
            setCampaigns(campRes.data.data || []);
            
            // If shortlisted filter returned empty, get all
            const cands = nomRes?.data?.data || [];
            setCandidates(cands);

            const userList = memRes?.data?.data?.data || memRes?.data?.data || [];
            setMembers(userList);

            if (campRes.data.data?.length > 0 && !assignData.campaign_id) {
                setAssignData((prev) => ({ 
                    ...prev, 
                    campaign_id: campRes.data.data[0].id,
                    nomination_id: 'all'
                }));
            }
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load jury management data.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    // Filter candidates for selected campaign
    const campaignCandidates = useMemo(() => {
        if (!assignData.campaign_id) return candidates;
        return candidates.filter(
            (c) => c.campaign_id === assignData.campaign_id && !c.application_number?.endsWith('-F2')
        );
    }, [candidates, assignData.campaign_id]);

    // Filter peers/jurors based on search
    const filteredMembers = useMemo(() => {
        if (!jurorSearch.trim()) return members;
        const q = jurorSearch.toLowerCase();
        return members.filter((m) => {
            const name = (m.display_name || `${m.first_name || ''} ${m.last_name || ''}`).toLowerCase();
            const email = (m.email || '').toLowerCase();
            const phone = (m.phone || '').toLowerCase();
            return name.includes(q) || email.includes(q) || phone.includes(q);
        });
    }, [members, jurorSearch]);

    const handleAssignSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!assignData.jury_user_id) {
            alert('Please select a juror peer from the users dropdown.');
            return;
        }

        try {
            await juryApi.assign(assignData);
            setIsAssignModal(false);
            alert('✓ Juror assigned successfully! An official appointment email with the login portal link has been sent to their registered email.');
            loadData();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to assign jury member.');
        }
    };

    const handleCriterionSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await juryApi.createCriterion(criterionData);
            setIsCriterionModal(false);
            loadData();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to add criterion.');
        }
    };

    const handleDeleteAssignment = async (id: string) => {
        if (!window.confirm('Are you sure you want to revoke this jury assignment?')) return;
        try {
            await juryApi.deleteAssignment(id);
            loadData();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to delete assignment.');
        }
    };

    const handleDeleteCriterion = async (id: string) => {
        if (!window.confirm('Are you sure you want to delete this criterion?')) return;
        try {
            await juryApi.deleteCriterion(id);
            loadData();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to delete criterion.');
        }
    };

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Jury Management</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Manage juror assignments, deadlines, evaluation criteria weights, and scoring rules
                    </p>
                </div>
                <div className="d-flex gap-2">
                    <button
                        onClick={() => setIsCriterionModal(true)}
                        className="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5"
                    >
                        <Scale size={14} /> Add Criterion
                    </button>
                    <button
                        onClick={() => {
                            if (campaigns.length > 0 && !assignData.campaign_id) {
                                setAssignData(prev => ({ ...prev, campaign_id: campaigns[0].id, nomination_id: 'all' }));
                            }
                            setIsAssignModal(true);
                        }}
                        className="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 fw-semibold"
                    >
                        <Plus size={14} /> Assign Juror to Candidate
                    </button>
                </div>
            </div>

            {error && (
                <div className="alert alert-danger rounded-4 border-0 shadow-xs p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>{error}</div>
                    <button className="btn btn-sm btn-outline-danger" onClick={() => setError(null)}>
                        Dismiss
                    </button>
                </div>
            )}

            {/* Sub-Tabs: Assignments vs Criteria */}
            <div className="card border-0 shadow-xs rounded-4 bg-white mb-4">
                <div className="border-bottom px-4 pt-3">
                    <ul className="nav nav-pills gap-2 mb-3">
                        <li className="nav-item">
                            <button
                                className={`btn btn-sm rounded-3 px-3 py-1.5 fw-semibold ${
                                    activeTab === 'assignments' ? 'btn-primary' : 'btn-light'
                                }`}
                                onClick={() => setActiveTab('assignments')}
                            >
                                <Users size={14} className="me-1.5 d-inline" /> Active Juror Assignments ({assignments.length})
                            </button>
                        </li>
                        <li className="nav-item">
                            <button
                                className={`btn btn-sm rounded-3 px-3 py-1.5 fw-semibold ${
                                    activeTab === 'criteria' ? 'btn-primary' : 'btn-light'
                                }`}
                                onClick={() => setActiveTab('criteria')}
                            >
                                <Scale size={14} className="me-1.5 d-inline" /> Scoring Criteria & Weights ({criteria.length})
                            </button>
                        </li>
                    </ul>
                </div>

                <div className="p-4">
                    {activeTab === 'assignments' ? (
                        <div className="table-responsive">
                            <table className="table table-hover align-middle mb-0">
                                <thead className="table-light">
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Campaign</th>
                                        <th>Appointed Juror</th>
                                        <th>Evaluation Deadline</th>
                                        <th>Status</th>
                                        <th className="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {isLoading ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-4 text-muted">
                                                Loading assignments...
                                            </td>
                                        </tr>
                                    ) : assignments.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-4 text-muted">
                                                No jury assignments recorded. Assign a juror to an approved candidate.
                                            </td>
                                        </tr>
                                    ) : (
                                        assignments.map((asg) => {
                                            const isCompleted = asg.status === 'completed';
                                            return (
                                                <tr key={asg.id}>
                                                    <td className="fw-semibold text-dark">
                                                        <div>{asg.candidate_name || asg.nomination_id}</div>
                                                        <div className="small text-muted font-monospace" style={{ fontSize: '11px' }}>
                                                            {asg.nomination?.application_number}
                                                        </div>
                                                    </td>
                                                    <td className="small text-muted">
                                                        {asg.campaign_name || asg.nomination?.campaign?.name || 'Campaign'}
                                                    </td>
                                                    <td>
                                                        <div className="fw-semibold text-dark">
                                                            {asg.jury_name || asg.juror?.display_name || asg.juror_user_id}
                                                        </div>
                                                        <div className="small text-muted" style={{ fontSize: '11.5px' }}>
                                                            {asg.jury_email || asg.juror?.email}
                                                        </div>
                                                    </td>
                                                    <td className="small text-muted">
                                                        {asg.deadline ? new Date(asg.deadline).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'No deadline'}
                                                    </td>
                                                    <td>
                                                        {isCompleted ? (
                                                            <span className="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill d-inline-flex align-items-center gap-1 fw-bold">
                                                                <CheckCircle size={12} /> ✓ Reviewed
                                                            </span>
                                                        ) : (
                                                            <StatusBadge status={asg.status} />
                                                        )}
                                                    </td>
                                                    <td className="text-end">
                                                        <div className="d-flex align-items-center justify-content-end gap-1.5">
                                                            {isCompleted ? (
                                                                <button
                                                                    onClick={() => setSelectedReview(asg)}
                                                                    className="btn btn-sm rounded-2 py-1 px-2.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs border"
                                                                    style={{
                                                                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                                                                        borderColor: 'rgba(16, 185, 129, 0.3)',
                                                                        color: '#047857',
                                                                        fontSize: '0.78rem',
                                                                    }}
                                                                    title="View completed juror review and evaluation report"
                                                                >
                                                                    <Eye size={13} />
                                                                    <span>View Review</span>
                                                                </button>
                                                            ) : (
                                                                <span
                                                                    className="text-muted small px-2 py-1 d-inline-flex align-items-center gap-1 me-1"
                                                                    style={{ fontSize: '0.75rem' }}
                                                                >
                                                                    <Clock size={12} className="text-warning" /> Awaiting Review
                                                                </span>
                                                            )}
                                                            <button
                                                                onClick={() => handleDeleteAssignment(asg.id)}
                                                                className="btn btn-light bg-white border text-secondary btn-sm rounded-2 py-1 px-2 d-inline-flex align-items-center gap-1 shadow-xs"
                                                                style={{ fontSize: '0.78rem' }}
                                                                title="Revoke assignment"
                                                            >
                                                                <Trash2 size={13} className="text-danger" />
                                                                <span className="fs-12 text-muted">Revoke</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="table-responsive">
                            <table className="table table-hover align-middle mb-0">
                                <thead className="table-light">
                                    <tr>
                                        <th>Criterion Name</th>
                                        <th>Category</th>
                                        <th>Max Score</th>
                                        <th>Weightage (%)</th>
                                        <th className="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {criteria.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-center py-4 text-muted">
                                                No evaluation criteria configured yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        criteria.map((crit) => (
                                            <tr key={crit.id}>
                                                <td className="fw-semibold text-dark">{crit.name}</td>
                                                <td>
                                                    <span className="badge bg-light text-dark border">{crit.category || 'General'}</span>
                                                </td>
                                                <td className="fw-bold">{crit.max_score} pts</td>
                                                <td>
                                                    <span className="badge bg-indigo-subtle text-indigo">{crit.weightage}%</span>
                                                </td>
                                                <td className="text-end">
                                                    <button
                                                        onClick={() => handleDeleteCriterion(crit.id)}
                                                        className="btn btn-light bg-white border text-secondary btn-sm rounded-2 py-1 px-2 d-inline-flex align-items-center gap-1 shadow-xs"
                                                        style={{ fontSize: '0.78rem' }}
                                                        title="Delete criterion"
                                                    >
                                                        <Trash2 size={13} className="text-danger" />
                                                        <span className="fs-12 text-muted">Delete</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Assign Jury Modal */}
            {isAssignModal && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-dialog-centered modal-lg">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <form onSubmit={handleAssignSubmit}>
                                <div className="modal-header border-bottom-0 pb-1">
                                    <h5 className="modal-title fw-bold text-dark">Assign Juror to Candidate</h5>
                                    <button type="button" className="btn-close" onClick={() => setIsAssignModal(false)}></button>
                                </div>
                                <div className="modal-body py-3">
                                    {/* Campaign Selector */}
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Campaign *</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={assignData.campaign_id}
                                            onChange={(e) => {
                                                const campId = e.target.value;
                                                setAssignData({ 
                                                    ...assignData, 
                                                    campaign_id: campId,
                                                    nomination_id: 'all'
                                                });
                                            }}
                                            required
                                        >
                                            {campaigns.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Candidate Selector */}
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Candidate(s) to Evaluate *</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={assignData.nomination_id}
                                            onChange={(e) => setAssignData({ ...assignData, nomination_id: e.target.value })}
                                            required
                                        >
                                            <option value="all">
                                                ⚡ Assign Juror to ALL Candidates in this Campaign ({campaignCandidates.length} Candidates)
                                            </option>
                                            {campaignCandidates.map((cand) => (
                                                <option key={cand.id} value={cand.id}>
                                                    {cand.candidate_name || cand.full_name} ({cand.application_number}) - {cand.applied_role_name || cand.designation || 'Candidate'}
                                                </option>
                                            ))}
                                        </select>
                                        {campaignCandidates.length === 0 && (
                                            <div className="text-warning small mt-1" style={{ fontSize: '12px' }}>
                                                Notice: No candidate records found currently linked to this campaign.
                                            </div>
                                        )}
                                    </div>

                                    {/* Juror Selector from users Table */}
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">
                                            Select Juror from Registered Peers / Users Table *
                                        </label>
                                        <div className="input-group input-group-sm mb-2">
                                            <span className="input-group-text bg-light text-muted border-end-0">
                                                <Search size={13} />
                                            </span>
                                            <input
                                                type="text"
                                                className="form-control form-control-sm border-start-0"
                                                placeholder="Quick search peer by name, email, or phone..."
                                                value={jurorSearch}
                                                onChange={(e) => setJurorSearch(e.target.value)}
                                            />
                                        </div>
                                        <select
                                            className="form-select form-select-sm"
                                            value={assignData.jury_user_id}
                                            onChange={(e) => setAssignData({ ...assignData, jury_user_id: e.target.value })}
                                            required
                                            size={6}
                                            style={{ maxHeight: '160px' }}
                                        >
                                            <option value="" disabled>-- Select a Peer ({filteredMembers.length} available) --</option>
                                            {filteredMembers.map((m) => {
                                                const name = m.display_name || `${m.first_name || ''} ${m.last_name || ''}`.trim() || 'Peer';
                                                return (
                                                    <option key={m.id} value={m.id}>
                                                        {name} • {m.email} {m.phone ? `(${m.phone})` : ''}
                                                    </option>
                                                );
                                            })}
                                        </select>
                                        <div className="form-text small text-muted mt-1.5" style={{ fontSize: '11.5px' }}>
                                            The selected user will be appointed as Jury Member and will receive an official email containing their Jury Login Portal link (<strong>https://peersunity.com/admin/web/jury/login</strong>).
                                        </div>
                                    </div>

                                    {/* Evaluation Deadline */}
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Evaluation Deadline *</label>
                                        <input
                                            type="datetime-local"
                                            className="form-control form-control-sm"
                                            value={assignData.deadline}
                                            onChange={(e) => setAssignData({ ...assignData, deadline: e.target.value })}
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="modal-footer border-top-0 pt-0">
                                    <button type="button" className="btn btn-light" onClick={() => setIsAssignModal(false)}>
                                        Cancel
                                    </button>
                                    <button type="submit" className="btn btn-primary px-4 fw-semibold">
                                        Assign Juror & Dispatch Notification Email
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* View Completed Review Modal */}
            {selectedReview && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-dialog-centered modal-lg">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <div className="modal-header border-bottom">
                                <div>
                                    <h5 className="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                        <CheckCircle size={18} className="text-success" /> Jury Evaluation Report
                                    </h5>
                                    <p className="text-muted small mb-0">
                                        Reviewed by <strong>{selectedReview.jury_name || selectedReview.juror?.display_name}</strong> ({selectedReview.jury_email || selectedReview.juror?.email})
                                    </p>
                                </div>
                                <button type="button" className="btn-close" onClick={() => setSelectedReview(null)}></button>
                            </div>
                            <div className="modal-body p-4">
                                <div className="p-3 bg-light rounded-3 mb-3 border">
                                    <div className="row g-2">
                                        <div className="col-md-6">
                                            <span className="text-muted small">Candidate:</span>
                                            <div className="fw-bold text-dark">{selectedReview.candidate_name}</div>
                                        </div>
                                        <div className="col-md-6">
                                            <span className="text-muted small">Campaign:</span>
                                            <div className="fw-bold text-dark">{selectedReview.campaign_name}</div>
                                        </div>
                                    </div>
                                </div>

                                {selectedReview.report_data ? (
                                    <div>
                                        <div className="mb-3">
                                            <span className="text-muted small fw-semibold">Overall Recommendation:</span>
                                            <div className="mt-1">
                                                <span className="badge bg-success text-white px-3 py-1.5 rounded-pill text-uppercase font-monospace">
                                                    {selectedReview.report_data.overall_recommendation}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mb-3">
                                            <span className="text-muted small fw-semibold">Key Strengths & Merits:</span>
                                            <div className="p-3 bg-light rounded-3 text-dark small mt-1">
                                                {selectedReview.report_data.strengths || 'No strengths notes recorded.'}
                                            </div>
                                        </div>

                                        <div className="mb-3">
                                            <span className="text-muted small fw-semibold">Key Concerns:</span>
                                            <div className="p-3 bg-light rounded-3 text-dark small mt-1">
                                                {selectedReview.report_data.concerns || 'No concerns recorded.'}
                                            </div>
                                        </div>

                                        {selectedReview.report_data.final_remarks && (
                                            <div className="mb-3">
                                                <span className="text-muted small fw-semibold">Final Remarks:</span>
                                                <div className="p-3 bg-light rounded-3 text-dark small mt-1">
                                                    {selectedReview.report_data.final_remarks}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="alert alert-light border small text-muted">
                                        Evaluation completed. (Report details: {selectedReview.status})
                                    </div>
                                )}
                            </div>
                            <div className="modal-footer border-top-0 pt-0">
                                <button type="button" className="btn btn-light" onClick={() => setSelectedReview(null)}>
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Add Criterion Modal */}
            {isCriterionModal && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-dialog-centered">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <form onSubmit={handleCriterionSubmit}>
                                <div className="modal-header border-bottom-0">
                                    <h5 className="modal-title fw-bold text-dark">Add Scoring Criterion</h5>
                                    <button type="button" className="btn-close" onClick={() => setIsCriterionModal(false)}></button>
                                </div>
                                <div className="modal-body py-3">
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Criterion Name *</label>
                                        <input
                                            type="text"
                                            className="form-control form-control-sm"
                                            placeholder="e.g. Industry Track Record"
                                            value={criterionData.name}
                                            onChange={(e) => setCriterionData({ ...criterionData, name: e.target.value })}
                                            required
                                        />
                                    </div>
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Category *</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={criterionData.category}
                                            onChange={(e) => setCriterionData({ ...criterionData, category: e.target.value })}
                                            required
                                        >
                                            <option value="Business Credibility">Business Credibility</option>
                                            <option value="Financial & Operational Standing">Financial & Operational Standing</option>
                                            <option value="Team & Bench Strength">Team & Bench Strength</option>
                                            <option value="Vision & Strategic Plan">Vision & Strategic Plan</option>
                                            <option value="General Leadership">General Leadership</option>
                                        </select>
                                    </div>
                                    <div className="row g-2 mb-3">
                                        <div className="col-6">
                                            <label className="form-label small fw-semibold">Max Score *</label>
                                            <input
                                                type="number"
                                                className="form-control form-control-sm"
                                                min={1}
                                                max={100}
                                                value={criterionData.max_score}
                                                onChange={(e) => setCriterionData({ ...criterionData, max_score: Number(e.target.value) })}
                                                required
                                            />
                                        </div>
                                        <div className="col-6">
                                            <label className="form-label small fw-semibold">Weightage (%) *</label>
                                            <input
                                                type="number"
                                                className="form-control form-control-sm"
                                                min={0}
                                                max={100}
                                                value={criterionData.weightage}
                                                onChange={(e) => setCriterionData({ ...criterionData, weightage: Number(e.target.value) })}
                                                required
                                            />
                                        </div>
                                    </div>
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Description</label>
                                        <textarea
                                            className="form-control form-control-sm"
                                            rows={2}
                                            placeholder="Evaluation guidelines for the jury..."
                                            value={criterionData.description}
                                            onChange={(e) => setCriterionData({ ...criterionData, description: e.target.value })}
                                        />
                                    </div>
                                </div>
                                <div className="modal-footer border-top-0 pt-0">
                                    <button type="button" className="btn btn-light" onClick={() => setIsCriterionModal(false)}>
                                        Cancel
                                    </button>
                                    <button type="submit" className="btn btn-primary px-4 fw-semibold">
                                        Save Criterion
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
