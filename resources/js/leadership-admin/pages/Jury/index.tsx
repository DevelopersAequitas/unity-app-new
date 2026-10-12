import React, { useEffect, useState } from 'react';
import { juryApi, campaignApi, nominationApi } from '../../api/services';
import { JuryAssignment, EvaluationCriterion, Campaign, Nomination } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { Plus, Users, Award, Trash2, Calendar, Scale, Clock } from 'lucide-react';

export const JuryPage: React.FC = () => {
    const [assignments, setAssignments] = useState<JuryAssignment[]>([]);
    const [criteria, setCriteria] = useState<EvaluationCriterion[]>([]);
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [candidates, setCandidates] = useState<Nomination[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [activeTab, setActiveTab] = useState<'assignments' | 'criteria'>('assignments');
    const [error, setError] = useState<string | null>(null);

    // Modal state for Assigning Jury
    const [isAssignModal, setIsAssignModal] = useState<boolean>(false);
    const [assignData, setAssignData] = useState({
        campaign_id: '',
        nomination_id: '',
        jury_user_id: '',
        deadline: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 16),
    });

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
            const [assignRes, critRes, campRes, nomRes] = await Promise.all([
                juryApi.getAssignments(),
                juryApi.getCriteria(),
                campaignApi.getAll(),
                nominationApi.getAll({ status: 'approved' }),
            ]);
            setAssignments(assignRes.data.data || []);
            setCriteria(critRes.data.data || []);
            setCampaigns(campRes.data.data || []);
            setCandidates(nomRes.data.data || []);
            if (campRes.data.data?.length > 0 && !assignData.campaign_id) {
                setAssignData((prev) => ({ ...prev, campaign_id: campRes.data.data[0].id }));
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

    const handleAssignSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await juryApi.assign(assignData);
            setIsAssignModal(false);
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
                        onClick={() => setIsAssignModal(true)}
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
                                        <th>Juror User ID</th>
                                        <th>Evaluation Deadline</th>
                                        <th>Status</th>
                                        <th className="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {isLoading ? (
                                        <tr>
                                            <td colSpan={5} className="text-center py-4 text-muted">
                                                Loading assignments...
                                            </td>
                                        </tr>
                                    ) : assignments.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-center py-4 text-muted">
                                                No jury assignments recorded. Assign a juror to an approved candidate.
                                            </td>
                                        </tr>
                                    ) : (
                                        assignments.map((asg) => (
                                            <tr key={asg.id}>
                                                <td className="fw-semibold text-dark">{asg.candidate_name || asg.nomination_id}</td>
                                                <td className="font-monospace small text-muted">{asg.jury_user_id}</td>
                                                <td className="small text-muted">
                                                    {asg.deadline ? new Date(asg.deadline).toLocaleDateString() : 'No deadline'}
                                                </td>
                                                <td>
                                                    <StatusBadge status={asg.status} />
                                                </td>
                                                <td className="text-end">
                                                    <button
                                                        onClick={() => handleDeleteAssignment(asg.id)}
                                                        className="btn btn-outline-danger btn-sm rounded-2 py-0.5 px-2"
                                                        title="Revoke assignment"
                                                    >
                                                        <Trash2 size={12} /> Revoke
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
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
                                                        className="btn btn-outline-danger btn-sm rounded-2 py-0.5 px-2"
                                                    >
                                                        <Trash2 size={12} />
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
                    <div className="modal-dialog modal-dialog-centered">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <form onSubmit={handleAssignSubmit}>
                                <div className="modal-header border-bottom-0">
                                    <h5 className="modal-title fw-bold text-dark">Assign Juror to Candidate</h5>
                                    <button type="button" className="btn-close" onClick={() => setIsAssignModal(false)}></button>
                                </div>
                                <div className="modal-body py-3">
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Campaign *</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={assignData.campaign_id}
                                            onChange={(e) => setAssignData({ ...assignData, campaign_id: e.target.value })}
                                            required
                                        >
                                            {campaigns.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Candidate *</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={assignData.nomination_id}
                                            onChange={(e) => setAssignData({ ...assignData, nomination_id: e.target.value })}
                                            required
                                        >
                                            <option value="">Select Candidate...</option>
                                            {candidates.map((cand) => (
                                                <option key={cand.id} value={cand.id}>
                                                    {cand.candidate_name} ({cand.application_number})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Juror User UUID *</label>
                                        <input
                                            type="text"
                                            className="form-control form-control-sm font-monospace"
                                            placeholder="Enter registered user UUID..."
                                            value={assignData.jury_user_id}
                                            onChange={(e) => setAssignData({ ...assignData, jury_user_id: e.target.value })}
                                            required
                                        />
                                    </div>

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
                                        Assign Juror
                                    </button>
                                </div>
                            </form>
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
                                            placeholder="e.g. Strategic Vision & 90-Day Plan"
                                            value={criterionData.name}
                                            onChange={(e) => setCriterionData({ ...criterionData, name: e.target.value })}
                                            required
                                        />
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold">Category</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={criterionData.category}
                                            onChange={(e) => setCriterionData({ ...criterionData, category: e.target.value })}
                                        >
                                            <option value="Business Credibility">Business Credibility</option>
                                            <option value="Leadership Track Record">Leadership Track Record</option>
                                            <option value="Network Strength">Network Strength</option>
                                            <option value="Community Alignment">Community Alignment</option>
                                        </select>
                                    </div>

                                    <div className="row g-2 mb-3">
                                        <div className="col-6">
                                            <label className="form-label small fw-semibold">Max Score</label>
                                            <input
                                                type="number"
                                                className="form-control form-control-sm"
                                                value={criterionData.max_score}
                                                onChange={(e) =>
                                                    setCriterionData({ ...criterionData, max_score: parseInt(e.target.value) || 10 })
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="col-6">
                                            <label className="form-label small fw-semibold">Weightage (%)</label>
                                            <input
                                                type="number"
                                                className="form-control form-control-sm"
                                                value={criterionData.weightage}
                                                onChange={(e) =>
                                                    setCriterionData({ ...criterionData, weightage: parseInt(e.target.value) || 10 })
                                                }
                                                required
                                            />
                                        </div>
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
