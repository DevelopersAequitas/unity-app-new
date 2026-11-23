import { LeadershipLayout } from '../../components/LeadershipLayout';
import React, { useEffect, useState } from 'react';
import { decisionApi, campaignApi, nominationApi } from '../../api/services';
import { Campaign, Nomination, FinalDecision } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { ConfirmModal } from '../../components/common/ConfirmModal';
import {
    Trophy,
    Award,
    ShieldCheck,
    CheckCircle2,
    Clock,
    XCircle,
    AlertCircle,
    Users,
    Check,
    Building2,
    MapPin,
    Search,
    ChevronRight,
    ChevronLeft,
    Star,
    Sparkles,
    PlusCircle,
    Globe,
    ThumbsUp,
    AlertTriangle,
    X,
} from 'lucide-react';

export const FinalDecisionsPage: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('');
    const [candidates, setCandidates] = useState<Nomination[]>([]);
    const [candidatePage, setCandidatePage] = useState<number>(1);
    const CANDIDATES_PER_PAGE = 5;
    const [decisions, setDecisions] = useState<FinalDecision[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Decision Modal
    const [selectedCandidate, setSelectedCandidate] = useState<Nomination | null>(null);
    const [candidateEvaluations, setCandidateEvaluations] = useState<any | null>(null);
    const [isLoadingEvaluations, setIsLoadingEvaluations] = useState<boolean>(false);
    const [circles, setCircles] = useState<any[]>([]);
    const [isLoadingCircles, setIsLoadingCircles] = useState<boolean>(false);
    const [circleSearchQuery, setCircleSearchQuery] = useState<string>('');
    const [cities, setCities] = useState<any[]>([]);

    const [decisionForm, setDecisionForm] = useState({
        decision: 'selected' as 'selected' | 'runner_up' | 'standby' | 'rejected' | 'deferred',
        rank: 1,
        remarks: '',
        circle_action: 'none' as 'none' | 'existing' | 'new',
        circle_id: '',
        new_circle_name: '',
        new_circle_description: '',
        new_circle_purpose: '',
        new_circle_city_id: '',
        new_circle_type: 'public' as 'public' | 'private',
        new_circle_meeting_mode: 'hybrid' as 'online' | 'offline' | 'hybrid',
    });
    const [isSavingDecision, setIsSavingDecision] = useState<boolean>(false);

    // Publish Winners Confirmation
    const [isPublishWinnersOpen, setIsPublishWinnersOpen] = useState<boolean>(false);
    const [isPublishing, setIsPublishing] = useState<boolean>(false);

    const extractCirclesList = (cRes: any): any[] => {
        if (!cRes) return [];
        const root = cRes.data !== undefined ? cRes.data : cRes;
        let list: any[] = [];
        if (Array.isArray(root)) list = root;
        else if (Array.isArray(root?.data)) list = root.data;
        else if (Array.isArray(root?.data?.data)) list = root.data.data;
        else if (Array.isArray(root?.items)) list = root.items;
        else if (Array.isArray(root?.circles)) list = root.circles;

        return list.map((c: any) => {
            const cityName = c.city || c.cityRef?.name || c.city_display || (typeof c.city_name === 'string' ? c.city_name : '');
            return {
                id: c.id,
                name: c.name || 'Unnamed Circle',
                slug: c.slug || '',
                city_id: c.city_id || null,
                city: cityName,
                type: c.type || 'public',
                status: c.status || 'active',
                meeting_mode: c.meeting_mode || 'hybrid',
            };
        });
    };

    const loadData = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const [campRes, cRes, cityRes] = await Promise.all([
                campaignApi.getAll(),
                decisionApi.getCircles().catch(() => null),
                campaignApi.getCities().catch(() => null),
            ]);
            const list = campRes.data.data || [];
            setCampaigns(list);
            setSelectedCampaignId('all');

            if (cRes) {
                const fetchedCircles = extractCirclesList(cRes);
                if (fetchedCircles.length > 0) {
                    setCircles(fetchedCircles);
                }
            }

            if (cityRes?.data?.data && Array.isArray(cityRes.data.data)) {
                setCities(cityRes.data.data);
            }

            loadCampaignCandidates('all');
        } catch {
            setError('Failed to load campaigns.');
        } finally {
            setIsLoading(false);
        }
    };

    const loadCampaignCandidates = async (campaignId?: string) => {
        setIsLoading(true);
        try {
            const [nomRes, decRes] = await Promise.all([
                campaignId && campaignId !== 'all' ? nominationApi.getAll({ campaign_id: campaignId }) : nominationApi.getAll(),
                decisionApi.getByCampaign(campaignId || 'all'),
            ]);
            const allCands = (nomRes.data.data || []).filter(
                (c) => c.application_number !== 'PG-STANDING-POOL' && !c.application_number?.endsWith('-F2')
            );
            setCandidates(allCands);
            setCandidatePage(1);
            setDecisions(decRes.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to fetch candidate decisions.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    useEffect(() => {
        if (selectedCampaignId) {
            loadCampaignCandidates(selectedCampaignId);
        }
    }, [selectedCampaignId]);

    const openCandidateDecisionModal = async (cand: Nomination) => {
        const existingDec = decisions.find((d) => d.nomination_id === cand.id);
        const snapshot = (cand.profile_snapshot || {}) as any;
        const hasAssignedCircle = snapshot?.assigned_circle_id;

        setSelectedCandidate(cand);
        setDecisionForm({
            decision: (existingDec?.decision as any) || (cand.status === 'winner' ? 'selected' : 'selected'),
            rank: existingDec?.rank || snapshot?.decision_rank || 1,
            remarks: existingDec?.remarks || snapshot?.decision_remarks || '',
            circle_action: hasAssignedCircle ? 'existing' : 'none',
            circle_id: snapshot?.assigned_circle_id || '',
            new_circle_name: '',
            new_circle_description: '',
            new_circle_purpose: '',
            new_circle_city_id: '',
            new_circle_type: 'public',
            new_circle_meeting_mode: 'hybrid',
        });
        setCircleSearchQuery('');

        // 1. Fetch Jury Evaluations
        setIsLoadingEvaluations(true);
        setCandidateEvaluations(null);
        try {
            const evalRes = await decisionApi.getCandidateEvaluations(cand.id);
            setCandidateEvaluations(evalRes.data?.data || null);
        } catch (e) {
            console.warn('Could not load evaluations', e);
        } finally {
            setIsLoadingEvaluations(false);
        }

        // 2. Fetch Circles if needed
        if (circles.length === 0) {
            setIsLoadingCircles(true);
            try {
                const cRes = await decisionApi.getCircles();
                const fetchedCircles = extractCirclesList(cRes);
                if (fetchedCircles.length > 0) {
                    setCircles(fetchedCircles);
                }
            } catch (e) {
                console.warn('Could not load circles', e);
            } finally {
                setIsLoadingCircles(false);
            }
        }

        // 3. Fetch Cities if needed
        if (cities.length === 0) {
            try {
                const cityRes = await campaignApi.getCities();
                if (cityRes?.data?.data && Array.isArray(cityRes.data.data)) {
                    setCities(cityRes.data.data);
                }
            } catch (e) {
                console.warn('Could not load cities', e);
            }
        }
    };

    const handleSaveDecision = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedCandidate) return;

        if (decisionForm.decision === 'selected' && decisionForm.circle_action === 'existing' && !decisionForm.circle_id) {
            alert('Please select an existing circle to assign, or choose another option.');
            return;
        }

        if (decisionForm.decision === 'selected' && decisionForm.circle_action === 'new' && !decisionForm.new_circle_name.trim()) {
            alert('Please enter a name for the new circle.');
            return;
        }

        setIsSavingDecision(true);
        try {
            await decisionApi.submit({
                campaign_id: selectedCampaignId,
                nomination_id: selectedCandidate.id,
                decision: decisionForm.decision,
                rank: decisionForm.rank,
                remarks: decisionForm.remarks,
                circle_action: decisionForm.decision === 'selected' ? decisionForm.circle_action : 'none',
                circle_id: decisionForm.circle_id || undefined,
                new_circle_name: decisionForm.new_circle_name || undefined,
                new_circle_description: decisionForm.new_circle_description || undefined,
                new_circle_purpose: decisionForm.new_circle_purpose || decisionForm.new_circle_description || undefined,
                new_circle_city_id: decisionForm.new_circle_city_id || undefined,
                new_circle_type: decisionForm.new_circle_type,
                new_circle_meeting_mode: decisionForm.new_circle_meeting_mode,
            });
            setSelectedCandidate(null);
            loadCampaignCandidates(selectedCampaignId);
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to record selection decision.');
        } finally {
            setIsSavingDecision(false);
        }
    };

    const handlePublishWinners = async () => {
        setIsPublishing(true);
        try {
            await decisionApi.publishWinners(selectedCampaignId);
            setIsPublishWinnersOpen(false);
            loadCampaignCandidates(selectedCampaignId);
            alert('Winners published successfully! Campaign transitioned to completed.');
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to publish winners.');
        } finally {
            setIsPublishing(false);
        }
    };

    const selectedCampaign = campaigns.find((c) => c.id === selectedCampaignId);

    const filteredCircles = circles.filter((c) => {
        if (!circleSearchQuery.trim()) return true;
        const q = circleSearchQuery.toLowerCase();
        const cCity = (c.city || '').toLowerCase();
        const cName = (c.name || '').toLowerCase();
        const cType = (c.type || '').toLowerCase();
        return cName.includes(q) || cCity.includes(q) || cType.includes(q);
    });

    const getRecommendationBadge = (rec: string) => {
        switch (rec) {
            case 'strongly_recommend':
                return <span className="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><Star size={12} className="me-1 inline" /> Strongly Recommend</span>;
            case 'recommend':
                return <span className="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><Check size={12} className="me-1 inline" /> Recommend</span>;
            case 'recommend_with_reservations':
                return <span className="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><AlertTriangle size={12} className="me-1 inline" /> Recommend with Reservations</span>;
            case 'do_not_recommend':
                return <span className="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><XCircle size={12} className="me-1 inline" /> Do Not Recommend</span>;
            default:
                return <span className="badge bg-secondary-subtle text-secondary px-2 py-1">{rec || 'Reviewed'}</span>;
        }
    };

    return (
        <LeadershipLayout>
            <div className="container-fluid px-0">
                {/* Header Toolbar */}
            <div className="d-flex justify-content-end align-items-center gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <button
                        onClick={() => setIsPublishWinnersOpen(true)}
                        className="btn btn-success btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 fw-semibold shadow-xs"
                    >
                        <Trophy size={16} /> Officially Publish Winners
                    </button>
            </div>

                {error && (
                    <div className="alert alert-danger rounded-4 border-0 shadow-xs p-3 mb-4 d-flex align-items-center justify-content-between">
                        <div>{error}</div>
                        <button className="btn btn-sm btn-outline-danger" onClick={() => setError(null)}>
                            Dismiss
                        </button>
                    </div>
                )}

                {/* Campaign Selection Bar */}
                <div className="card border-0 shadow-xs rounded-4 bg-white p-3 mb-4">
                    <div className="row g-2 align-items-center">
                        <div className="col-12 col-md-6">
                            <label className="form-label text-muted small fw-semibold text-uppercase tracking-wider mb-1">
                                Campaign:
                            </label>
                            <select
                                className="form-select form-select-sm"
                                value={selectedCampaignId}
                                onChange={(e) => setSelectedCampaignId(e.target.value)}
                            >
                                <option value="all">All Candidate Applications (Global Platform)</option>
                                {campaigns.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name} ({c.year}) - {c.status.toUpperCase()}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Candidates Table */}
                <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                    <div className="table-responsive">
                        <table className="table table-hover align-middle mb-0">
                            <thead className="table-light">
                                <tr>
                                    <th>Candidate</th>
                                    <th>App #</th>
                                    <th>Applied Role</th>
                                    <th>Recorded Decision</th>
                                    <th>Rank</th>
                                    <th>Remarks</th>
                                    <th className="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {isLoading ? (
                                    <tr>
                                        <td colSpan={7} className="text-center py-5 text-muted">
                                            <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                            Loading candidate decision records...
                                        </td>
                                    </tr>
                                ) : candidates.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="text-center py-5 text-muted">
                                            No candidates found for this campaign.
                                        </td>
                                    </tr>
                                ) : (
                                        candidates
                                            .slice((candidatePage - 1) * CANDIDATES_PER_PAGE, candidatePage * CANDIDATES_PER_PAGE)
                                            .map((cand) => {
                                                const dec = decisions.find((d) => d.nomination_id === cand.id);
                                                const snapshot = (cand.profile_snapshot || {}) as any;
                                                const assignedCircleName = snapshot?.assigned_circle_name;
                                                const candPhoto = cand.profile_photo_url || cand.photo_url || cand.avatar_url || (cand as any).image_url || snapshot?.photo_url || snapshot?.profile_photo_url || cand.user?.profile_photo_url || null;

                                                return (
                                                    <tr
                                                        key={cand.id}
                                                        style={{ cursor: 'pointer' }}
                                                        onClick={() => openCandidateDecisionModal(cand)}
                                                        className="transition-all hover:bg-slate-50"
                                                    >
                                                        <td className="fw-semibold text-dark">
                                                            <div className="d-flex align-items-center gap-2.5">
                                                                {candPhoto ? (
                                                                    <img
                                                                        src={candPhoto}
                                                                        alt={cand.candidate_name}
                                                                        className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                                        style={{ width: 36, height: 36 }}
                                                                        onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                    />
                                                                ) : (
                                                                    <div
                                                                        className="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center shadow-xs flex-shrink-0"
                                                                        style={{ width: 36, height: 36, fontSize: 13 }}
                                                                    >
                                                                        {cand.candidate_name?.charAt(0) || 'C'}
                                                                    </div>
                                                                )}
                                                                <div>
                                                                    <div className="fw-bold text-dark">{cand.candidate_name}</div>
                                                                    <div className="text-muted small" style={{ fontSize: 11 }}>
                                                                        {cand.email || ''}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="font-monospace small text-primary fw-semibold">
                                                            {cand.application_number}
                                                        </td>
                                                        <td>
                                                            <span className="badge bg-light text-dark border">
                                                                {cand.applied_role_name || 'Leadership Role'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            {dec ? (
                                                                <div className="d-flex flex-column gap-1">
                                                                    <StatusBadge status={dec.decision} />
                                                                    {assignedCircleName && (
                                                                        <span className="badge bg-info-subtle text-info border border-info-subtle d-inline-flex align-items-center gap-1" style={{ fontSize: 10 }}>
                                                                            <Building2 size={10} /> {assignedCircleName}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <span className="badge bg-warning-subtle text-warning">Pending Decision</span>
                                                            )}
                                                        </td>
                                                        <td className="fw-bold">{dec?.rank ? `#${dec.rank}` : '-'}</td>
                                                        <td className="small text-muted" style={{ maxWidth: 220 }}>
                                                            <div className="text-truncate">{dec?.remarks || snapshot?.decision_remarks || '-'}</div>
                                                        </td>
                                                        <td className="text-end" onClick={(e) => e.stopPropagation()}>
                                                            <button
                                                                onClick={() => openCandidateDecisionModal(cand)}
                                                                className="btn btn-outline-primary btn-sm rounded-2 py-1 px-3 fw-semibold d-inline-flex align-items-center gap-1.5"
                                                            >
                                                                <Award size={14} />
                                                                {dec ? 'Update Decision' : 'Record Decision'}
                                                            </button>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Candidates Table Pagination */}
                    {candidates.length > CANDIDATES_PER_PAGE && (
                        <div className="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 pt-3 border-top mt-3">
                            <span className="text-muted small">
                                Showing {(candidatePage - 1) * CANDIDATES_PER_PAGE + 1} to {Math.min(candidatePage * CANDIDATES_PER_PAGE, candidates.length)} of {candidates.length} candidates
                            </span>
                            <div className="d-flex align-items-center gap-2">
                                <button
                                    type="button"
                                    className="btn btn-outline-secondary btn-sm rounded-3 px-2.5 py-1 d-flex align-items-center gap-1"
                                    disabled={candidatePage <= 1}
                                    onClick={() => setCandidatePage((p) => Math.max(1, p - 1))}
                                >
                                    <ChevronLeft size={14} /> Previous
                                </button>
                                <span className="small text-muted px-2">
                                    Page {candidatePage} of {Math.ceil(candidates.length / CANDIDATES_PER_PAGE)}
                                </span>
                                <button
                                    type="button"
                                    className="btn btn-outline-secondary btn-sm rounded-3 px-2.5 py-1 d-flex align-items-center gap-1"
                                    disabled={candidatePage >= Math.ceil(candidates.length / CANDIDATES_PER_PAGE)}
                                    onClick={() => setCandidatePage((p) => Math.min(Math.ceil(candidates.length / CANDIDATES_PER_PAGE), p + 1))}
                                >
                                    Next <ChevronRight size={14} />
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Comprehensive Decision & Jury Evaluation Modal */}
                {selectedCandidate && (
                    <div
                        className="modal fade show d-block"
                        tabIndex={-1}
                        style={{ backgroundColor: 'rgba(15, 23, 42, 0.65)', backdropFilter: 'blur(4px)', zIndex: 1055, overflowY: 'auto' }}
                    >
                        <div className="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable my-3" style={{ maxWidth: 960 }}>
                            <div className="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                                <form onSubmit={handleSaveDecision}>
                                    {/* Modal Header */}
                                    <div className="modal-header bg-light border-bottom px-4 py-3 align-items-center justify-content-between">
                                        <div className="d-flex align-items-center gap-3">
                                            {(() => {
                                                const mPhoto = selectedCandidate.profile_photo_url || selectedCandidate.photo_url || selectedCandidate.avatar_url || (selectedCandidate as any).image_url || (selectedCandidate.profile_snapshot as any)?.photo_url || selectedCandidate.user?.profile_photo_url || null;
                                                return mPhoto ? (
                                                    <img
                                                        src={mPhoto}
                                                        alt={selectedCandidate.candidate_name}
                                                        className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                        style={{ width: 44, height: 44 }}
                                                        onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                    />
                                                ) : (
                                                    <div
                                                        className="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center shadow-xs flex-shrink-0"
                                                        style={{ width: 44, height: 44, fontSize: 18 }}
                                                    >
                                                        {selectedCandidate.candidate_name?.charAt(0) || 'C'}
                                                    </div>
                                                );
                                            })()}
                                            <div>
                                                <div className="d-flex align-items-center gap-2">
                                                    <h5 className="modal-title fw-bold text-dark mb-0">
                                                        {selectedCandidate.candidate_name}
                                                    </h5>
                                                    <span className="badge bg-primary-subtle text-primary font-monospace px-2 py-0.5" style={{ fontSize: 11 }}>
                                                        {selectedCandidate.application_number}
                                                    </span>
                                                    {selectedCandidate.status === 'winner' && (
                                                        <span className="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style={{ fontSize: 11 }}>
                                                            🏆 Current Winner
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-muted small mt-0.5 d-flex align-items-center gap-2">
                                                    <span>Role: <strong className="text-dark">{selectedCandidate.applied_role_name || 'Leadership Role'}</strong></span>
                                                    <span>•</span>
                                                    <span>Campaign: <strong className="text-dark">{selectedCampaign?.name || ''}</strong></span>
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                width: '36px',
                                                height: '36px',
                                                borderRadius: '50%',
                                                background: '#f1f5f9',
                                                border: '1px solid #cbd5e1',
                                                color: '#475569',
                                                cursor: 'pointer',
                                                flexShrink: 0,
                                            }}
                                            onClick={() => setSelectedCandidate(null)}
                                            title="Close Modal"
                                        >
                                            <X size={20} />
                                        </button>
                                    </div>

                                    {/* Modal Body */}
                                    <div className="modal-body p-4" style={{ maxHeight: 'calc(85vh - 130px)', overflowY: 'auto' }}>
                                        {/* SECTION 1: JURY EVALUATIONS */}
                                        <div className="mb-4">
                                            <div className="d-flex align-items-center justify-content-between mb-3">
                                                <div>
                                                    <h6 className="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                                        <ShieldCheck className="text-primary" size={18} />
                                                        Jury Evaluation & Scorecard
                                                    </h6>
                                                    <p className="text-muted small mb-0">
                                                        Detailed scores, criteria grades, strengths, and recommendations submitted by the appointed jury panel
                                                    </p>
                                                </div>
                                                {candidateEvaluations && (
                                                    <div className="d-flex align-items-center gap-2">
                                                        <div className="px-3 py-1.5 rounded-3 bg-primary-subtle border border-primary-subtle text-primary fw-bold text-center">
                                                            <div style={{ fontSize: 11 }} className="text-uppercase tracking-wider">Avg Score</div>
                                                            <div className="fs-6 d-flex align-items-center justify-content-center gap-1">
                                                                <Star size={14} className="fill-primary" />
                                                                {candidateEvaluations.average_weighted_score || '0.00'} <span className="text-muted fw-normal" style={{ fontSize: 11 }}>/ 10</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>

                                            {isLoadingEvaluations ? (
                                                <div className="text-center py-4 text-muted bg-light rounded-3 border">
                                                    <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                                    Loading jury evaluation scores...
                                                </div>
                                            ) : !candidateEvaluations || !candidateEvaluations.assignments || candidateEvaluations.assignments.length === 0 ? (
                                                <div className="alert alert-light border rounded-3 p-3 text-muted small d-flex align-items-center gap-2">
                                                    <AlertCircle size={18} className="text-secondary" />
                                                    No jury members have submitted evaluations for this candidate yet. You can proceed with recording an administrative decision below.
                                                </div>
                                            ) : (
                                                <div className="d-flex flex-column gap-3">
                                                    {candidateEvaluations.assignments.map((asgn: any, idx: number) => {
                                                        const report = asgn.report || asgn.report_data;
                                                        const scores = asgn.scores || report?.scores || [];
                                                        const jurorName = asgn.jury_name || asgn.juror?.display_name || asgn.juror?.first_name || `Juror #${idx + 1}`;
                                                        const jurorEmail = asgn.jury_email || asgn.juror?.email;

                                                        return (
                                                            <div key={asgn.id || idx} className="card border rounded-3 shadow-xs overflow-hidden">
                                                                <div className="card-header bg-light py-2.5 px-3 d-flex align-items-center justify-content-between">
                                                                    <div className="d-flex align-items-center gap-2">
                                                                        <div className="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center" style={{ width: 28, height: 28, fontSize: 11 }}>
                                                                            {jurorName.charAt(0)}
                                                                        </div>
                                                                        <div>
                                                                            <span className="fw-bold text-dark small">{jurorName}</span>
                                                                            {jurorEmail && <span className="text-muted small ms-1.5" style={{ fontSize: 11 }}>({jurorEmail})</span>}
                                                                        </div>
                                                                    </div>
                                                                    <div className="d-flex align-items-center gap-2">
                                                                        {report?.overall_recommendation && getRecommendationBadge(report.overall_recommendation)}
                                                                        <span className={`badge ${asgn.status === 'completed' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'}`} style={{ fontSize: 10 }}>
                                                                            {asgn.status?.toUpperCase() || 'REVIEWED'}
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                <div className="card-body p-3">
                                                                    {/* Criteria Scores Grid */}
                                                                    {scores && scores.length > 0 ? (
                                                                        <div className="table-responsive mb-3">
                                                                            <table className="table table-sm table-bordered align-middle mb-0" style={{ fontSize: 12 }}>
                                                                                <thead className="table-light">
                                                                                    <tr>
                                                                                        <th>Evaluation Criterion</th>
                                                                                        <th style={{ width: 90 }}>Category</th>
                                                                                        <th style={{ width: 90 }} className="text-center">Score</th>
                                                                                        <th>Remarks / Notes</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    {scores.map((sc: any, scIdx: number) => (
                                                                                        <tr key={sc.criterion_id || scIdx}>
                                                                                            <td className="fw-semibold text-dark">
                                                                                                {sc.criterion_name || sc.criterion?.name || 'Criterion'}
                                                                                            </td>
                                                                                            <td className="text-muted">{sc.category || sc.criterion?.category || 'General'}</td>
                                                                                            <td className="text-center fw-bold text-primary">
                                                                                                {sc.score} <span className="text-muted fw-normal" style={{ fontSize: 10 }}>/ {sc.max_score || 10}</span>
                                                                                            </td>
                                                                                            <td className="text-muted small">{sc.remarks || '-'}</td>
                                                                                        </tr>
                                                                                    ))}
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    ) : (
                                                                        <p className="text-muted small mb-2 fst-italic">Individual criteria scorecards pending or in draft.</p>
                                                                    )}

                                                                    {/* Qualitative Feedback */}
                                                                    {(report?.strengths || report?.concerns || report?.final_remarks) && (
                                                                        <div className="row g-2 pt-1 border-top">
                                                                            {report.strengths && (
                                                                                <div className="col-12 col-md-6">
                                                                                    <div className="p-2.5 rounded-2 bg-success-subtle bg-opacity-30 border border-success-subtle h-100">
                                                                                        <div className="d-flex align-items-center gap-1.5 text-success fw-bold small mb-1">
                                                                                            <ThumbsUp size={13} /> Strengths
                                                                                        </div>
                                                                                        <div className="small text-dark" style={{ fontSize: 12 }}>{report.strengths}</div>
                                                                                    </div>
                                                                                </div>
                                                                            )}
                                                                            {report.concerns && (
                                                                                <div className="col-12 col-md-6">
                                                                                    <div className="p-2.5 rounded-2 bg-warning-subtle bg-opacity-30 border border-warning-subtle h-100">
                                                                                        <div className="d-flex align-items-center gap-1.5 text-warning fw-bold small mb-1">
                                                                                            <AlertCircle size={13} /> Concerns & Observations
                                                                                        </div>
                                                                                        <div className="small text-dark" style={{ fontSize: 12 }}>{report.concerns}</div>
                                                                                    </div>
                                                                                </div>
                                                                            )}
                                                                            {report.final_remarks && (
                                                                                <div className="col-12">
                                                                                    <div className="p-2.5 rounded-2 bg-light border">
                                                                                        <div className="text-muted fw-semibold small mb-0.5" style={{ fontSize: 11 }}>Juror Final Remarks:</div>
                                                                                        <div className="small text-dark" style={{ fontSize: 12 }}>{report.final_remarks}</div>
                                                                                    </div>
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>

                                        <hr className="my-4 text-muted opacity-25" />

                                        {/* SECTION 2: FINAL DECISION FORM */}
                                        <div className="mb-4">
                                            <h6 className="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                                <Award className="text-primary" size={18} />
                                                Selection Committee Decision
                                            </h6>
                                            <p className="text-muted small mb-3">
                                                Select the official selection status, ranking, and notification feedback for this candidate
                                            </p>

                                            <div className="row g-3">
                                                <div className="col-12 col-md-6">
                                                    <label className="form-label small fw-semibold text-dark">Selection Status *</label>
                                                    <select
                                                        className="form-select"
                                                        value={decisionForm.decision}
                                                        onChange={(e) =>
                                                            setDecisionForm({ ...decisionForm, decision: e.target.value as any })
                                                        }
                                                    >
                                                        <option value="selected">🏆 Selected as Winner</option>
                                                        <option value="runner_up">🥈 Runner Up</option>
                                                        <option value="standby">⏳ Standby / Alternate</option>
                                                        <option value="deferred">📅 Deferred for Next Cycle</option>
                                                        <option value="rejected">❌ Not Selected</option>
                                                    </select>
                                                </div>

                                                <div className="col-12 col-md-6">
                                                    <label className="form-label small fw-semibold text-dark">Official Rank / Standing</label>
                                                    <input
                                                        type="number"
                                                        className="form-control"
                                                        min={1}
                                                        placeholder="e.g. 1"
                                                        value={decisionForm.rank}
                                                        onChange={(e) =>
                                                            setDecisionForm({ ...decisionForm, rank: parseInt(e.target.value) || 1 })
                                                        }
                                                    />
                                                </div>

                                                <div className="col-12">
                                                    <label className="form-label small fw-semibold text-dark">Official Remarks / Justification</label>
                                                    <textarea
                                                        className="form-control"
                                                        rows={2}
                                                        placeholder="Provide leadership committee feedback, announcement justification, or internal remarks..."
                                                        value={decisionForm.remarks}
                                                        onChange={(e) => setDecisionForm({ ...decisionForm, remarks: e.target.value })}
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {/* SECTION 3: CIRCLE ASSIGNMENT (Visible when Selected as Winner) */}
                                        {decisionForm.decision === 'selected' && (
                                            <div className="p-3.5 rounded-4 border border-primary-subtle bg-primary-subtle bg-opacity-20 mb-3">
                                                <div className="d-flex align-items-center justify-content-between mb-2">
                                                    <div className="d-flex align-items-center gap-2">
                                                        <Building2 className="text-primary" size={20} />
                                                        <div>
                                                            <h6 className="fw-bold text-dark mb-0">Assign Winner to Peers Circle</h6>
                                                            <div className="text-muted small" style={{ fontSize: 12 }}>
                                                                Link this winner to an active circle or create a brand new circle in the system
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span className="badge bg-primary text-white px-2.5 py-1">Role: Founder / Chair</span>
                                                </div>

                                                {/* Circle Mode Radio Switch */}
                                                <div className="d-flex gap-3 my-3">
                                                    <label className="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            name="circle_action"
                                                            className="form-check-input"
                                                            checked={decisionForm.circle_action === 'existing'}
                                                            onChange={() => setDecisionForm({ ...decisionForm, circle_action: 'existing' })}
                                                        />
                                                        <span className="small fw-semibold text-dark">Assign to Existing Circle</span>
                                                    </label>

                                                    <label className="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            name="circle_action"
                                                            className="form-check-input"
                                                            checked={decisionForm.circle_action === 'new'}
                                                            onChange={() => setDecisionForm({ ...decisionForm, circle_action: 'new' })}
                                                        />
                                                        <span className="small fw-semibold text-dark">Create New Circle</span>
                                                    </label>

                                                    <label className="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            name="circle_action"
                                                            className="form-check-input"
                                                            checked={decisionForm.circle_action === 'none'}
                                                            onChange={() => setDecisionForm({ ...decisionForm, circle_action: 'none' })}
                                                        />
                                                        <span className="small text-muted">Skip / Assign Later</span>
                                                    </label>
                                                </div>

                                                {/* Option A: Existing Circle Selector */}
                                                {decisionForm.circle_action === 'existing' && (
                                                    <div className="bg-white p-3 rounded-3 border">
                                                        <div className="row g-2 mb-2">
                                                            <div className="col-12 col-md-5">
                                                                <div className="input-group input-group-sm">
                                                                    <span className="input-group-text bg-light border-end-0">
                                                                        <Search size={14} className="text-muted" />
                                                                    </span>
                                                                    <input
                                                                        type="text"
                                                                        className="form-control border-start-0"
                                                                        placeholder="Filter circles by name or city..."
                                                                        value={circleSearchQuery}
                                                                        onChange={(e) => setCircleSearchQuery(e.target.value)}
                                                                    />
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-7">
                                                                <select
                                                                    className="form-select form-select-sm"
                                                                    value={decisionForm.circle_id}
                                                                    onChange={(e) => setDecisionForm({ ...decisionForm, circle_id: e.target.value })}
                                                                >
                                                                    <option value="">-- Select Circle from Database ({filteredCircles.length} found) --</option>
                                                                    {filteredCircles.map((cir) => (
                                                                        <option key={cir.id} value={cir.id}>
                                                                            {cir.name} {cir.city ? `(${cir.city})` : ''} - {(cir.meeting_mode || cir.status || 'ACTIVE').toUpperCase()}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                        </div>

                                                        {decisionForm.circle_id && (
                                                            <div className="p-2.5 rounded-2 bg-light border mt-2 small d-flex align-items-center justify-content-between">
                                                                <div>
                                                                    <strong>Selected Circle:</strong>{' '}
                                                                    {circles.find((c) => c.id === decisionForm.circle_id)?.name || decisionForm.circle_id}
                                                                </div>
                                                                <span className="badge bg-success-subtle text-success">Will update leadership & members</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}

                                                {/* Option B: Create New Circle Form */}
                                                {decisionForm.circle_action === 'new' && (
                                                    <div className="bg-white p-3 rounded-3 border">
                                                        <div className="row g-2 mb-2">
                                                            <div className="col-12 col-md-8">
                                                                <label className="form-label small fw-semibold text-dark mb-1">Circle Name *</label>
                                                                <input
                                                                    type="text"
                                                                    className="form-control form-control-sm"
                                                                    placeholder="e.g. Surat Diamond Leaders Circle"
                                                                    value={decisionForm.new_circle_name}
                                                                    onChange={(e) => setDecisionForm({ ...decisionForm, new_circle_name: e.target.value })}
                                                                />
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <label className="form-label small fw-semibold text-dark mb-1">City / District *</label>
                                                                <select
                                                                    className="form-select form-select-sm"
                                                                    value={decisionForm.new_circle_city_id}
                                                                    onChange={(e) => setDecisionForm({ ...decisionForm, new_circle_city_id: e.target.value })}
                                                                >
                                                                    <option value="">-- Select City --</option>
                                                                    {cities.map((city) => (
                                                                        <option key={city.id} value={city.id}>
                                                                            {city.name} {city.state ? `(${city.state})` : ''}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div className="row g-2 mb-2">
                                                            <div className="col-12 col-md-6">
                                                                <label className="form-label small fw-semibold text-dark mb-1">Meeting Mode</label>
                                                                <select
                                                                    className="form-select form-select-sm"
                                                                    value={decisionForm.new_circle_meeting_mode}
                                                                    onChange={(e) => setDecisionForm({ ...decisionForm, new_circle_meeting_mode: e.target.value as any })}
                                                                >
                                                                    <option value="hybrid">Hybrid (Online & In-person)</option>
                                                                    <option value="offline">Offline (In-person)</option>
                                                                    <option value="online">Online (Virtual)</option>
                                                                </select>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <label className="form-label small fw-semibold text-dark mb-1">Circle Visibility</label>
                                                                <select
                                                                    className="form-select form-select-sm"
                                                                    value={decisionForm.new_circle_type}
                                                                    onChange={(e) => setDecisionForm({ ...decisionForm, new_circle_type: e.target.value as any })}
                                                                >
                                                                    <option value="public">Public (Open for Peers to Join)</option>
                                                                    <option value="private">Private (Invitation Only)</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <label className="form-label small fw-semibold text-dark mb-1">Circle Description / Purpose</label>
                                                            <textarea
                                                                className="form-control form-control-sm"
                                                                rows={2}
                                                                placeholder="Core purpose, objective, or industry focus of this new circle..."
                                                                value={decisionForm.new_circle_description}
                                                                onChange={(e) => setDecisionForm({ ...decisionForm, new_circle_description: e.target.value })}
                                                            />
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    {/* Modal Footer */}
                                    <div className="modal-footer bg-light border-top px-4 py-3">
                                        <button
                                            type="button"
                                            className="btn btn-outline-secondary btn-sm px-3 fw-semibold"
                                            onClick={() => setSelectedCandidate(null)}
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            className="btn btn-primary btn-sm px-4 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5"
                                            disabled={isSavingDecision}
                                        >
                                            <Award size={15} />
                                            {isSavingDecision ? 'Saving Decision...' : 'Save & Confirm Decision'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                )}

                {/* Confirm Publish Winners Modal */}
                {isPublishWinnersOpen && (
                    <ConfirmModal
                        isOpen={true}
                        title="Officially Declare & Publish Winners"
                        message="Are you sure you want to officially declare the selected candidates as winners for this campaign? This will publish the results to the public website and conclude this election cycle."
                        variant="success"
                        confirmText="Publish Winners Now"
                        isLoading={isPublishing}
                        onConfirm={handlePublishWinners}
                        onCancel={() => setIsPublishWinnersOpen(false)}
                    />
                )}
            </div>
        </LeadershipLayout>
    );
};
