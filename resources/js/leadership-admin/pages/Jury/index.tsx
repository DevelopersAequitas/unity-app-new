import React, { useEffect, useState, useMemo } from 'react';
import { juryApi, campaignApi, nominationApi } from '../../api/services';
import { JuryAssignment, EvaluationCriterion, Campaign, Nomination } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { 
    Plus, 
    Users, 
    Award, 
    Trash2, 
    Calendar, 
    Scale, 
    Clock, 
    CheckCircle, 
    CheckCircle2, 
    Eye, 
    Search, 
    ChevronRight, 
    Briefcase, 
    Tag, 
    Check, 
    Layers,
    UserCheck,
    FileText,
    Star,
    Copy,
    ExternalLink
} from 'lucide-react';

export const JuryPage: React.FC = () => {
    const [assignments, setAssignments] = useState<JuryAssignment[]>([]);
    const [criteria, setCriteria] = useState<EvaluationCriterion[]>([]);
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [candidates, setCandidates] = useState<Nomination[]>([]);
    const [members, setMembers] = useState<any[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [activeTab, setActiveTab] = useState<'assignments' | 'criteria'>('assignments');
    const [error, setError] = useState<string | null>(null);

    // Selected Campaign for Structured View (defaults to first campaign or 'all')
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('all');

    // Modal state for Assigning Jury
    const [isAssignModal, setIsAssignModal] = useState<boolean>(false);
    const [jurorSearch, setJurorSearch] = useState<string>('');
    const [selectedJurorIds, setSelectedJurorIds] = useState<string[]>([]);
    const [assignData, setAssignData] = useState({
        campaign_id: '',
        nomination_id: 'all',
        deadline: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 16),
        remarks: '',
    });

    // Modal state for viewing completed jury review
    const [selectedReview, setSelectedReview] = useState<any | null>(null);
    const [copiedJuryLink, setCopiedJuryLink] = useState<string | null>(null);

    const juryPanelUrl = `${window.location.origin}/admin/web/jury/login`;
    const copyJuryPanelLink = (jurorId: string) => {
        navigator.clipboard.writeText(juryPanelUrl);
        setCopiedJuryLink(jurorId);
        setTimeout(() => setCopiedJuryLink(null), 2000);
    };

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
                juryApi.getMembers({ per_page: 300 }).catch(() => ({ data: { data: [] } })),
            ]);
            
            const rawAssignments = assignRes.data.data || [];
            setAssignments(rawAssignments);
            setCriteria(critRes.data.data || []);
            
            const camps = campRes.data.data || [];
            setCampaigns(camps);
            if (camps.length > 0 && selectedCampaignId === 'all') {
                setSelectedCampaignId(camps[0].id);
            }
            
            const cands = nomRes?.data?.data || [];
            setCandidates(cands);

            const userList = memRes?.data?.data?.data || memRes?.data?.data || [];
            setMembers(userList);

            if (camps.length > 0 && !assignData.campaign_id) {
                setAssignData((prev) => ({ 
                    ...prev, 
                    campaign_id: camps[0].id,
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

    // Active Campaign object
    const currentCampaign = useMemo(() => {
        if (selectedCampaignId === 'all') return null;
        return campaigns.find((c) => c.id === selectedCampaignId) || null;
    }, [campaigns, selectedCampaignId]);

    // Assignments filtered by selected campaign
    const campaignAssignments = useMemo(() => {
        if (selectedCampaignId === 'all') return assignments;
        return assignments.filter((asg) => {
            const campId = asg.campaign_id || asg.nomination?.campaign_id;
            return campId === selectedCampaignId;
        });
    }, [assignments, selectedCampaignId]);

    // Distinct Appointed Jurors for selected campaign
    const assignedJurors = useMemo(() => {
        const jurorMap = new Map<string, { juror: any; total: number; completed: number; assignments: JuryAssignment[] }>();
        campaignAssignments.forEach((asg) => {
            const jurorId = asg.juror_user_id;
            if (!jurorId) return;
            if (!jurorMap.has(jurorId)) {
                jurorMap.set(jurorId, {
                    juror: asg.juror || {
                        id: jurorId,
                        display_name: asg.jury_name || 'Juror Member',
                        email: asg.jury_email,
                    },
                    total: 0,
                    completed: 0,
                    assignments: [],
                });
            }
            const record = jurorMap.get(jurorId);
            if (record) {
                record.total += 1;
                if (asg.status === 'completed') record.completed += 1;
                record.assignments.push(asg);
            }
        });
        return Array.from(jurorMap.values());
    }, [campaignAssignments]);

    // Candidates in current campaign
    const campaignCandidates = useMemo(() => {
        const targetCampId = assignData.campaign_id || selectedCampaignId;
        if (!targetCampId || targetCampId === 'all') return candidates;
        return candidates.filter(
            (c) => c.campaign_id === targetCampId && !c.application_number?.endsWith('-F2')
        );
    }, [candidates, assignData.campaign_id, selectedCampaignId]);

    // Filter peers/jurors based on search
    const filteredMembers = useMemo(() => {
        if (!Array.isArray(members)) return [];
        if (!jurorSearch.trim()) return members;
        const q = jurorSearch.toLowerCase();
        return members.filter((m) => {
            if (!m) return false;
            const name = String(m.display_name || `${m.first_name || ''} ${m.last_name || ''}`).toLowerCase();
            const email = String(m.email || '').toLowerCase();
            const phone = String(m.phone || '').toLowerCase();
            const busNameStr = typeof m.business_name === 'object' && m.business_name !== null 
                ? String(m.business_name.name || '') 
                : String(m.business_name || m.company_name || '');
            const busCatStr = typeof m.business_category === 'object' && m.business_category !== null 
                ? String(m.business_category.name || '') 
                : (typeof m.main_business_category === 'object' && m.main_business_category !== null
                    ? String(m.main_business_category.name || '')
                    : String(m.business_category || ''));
            return name.includes(q) || email.includes(q) || phone.includes(q) || busNameStr.toLowerCase().includes(q) || busCatStr.toLowerCase().includes(q);
        });
    }, [members, jurorSearch]);

    const toggleJurorSelection = (userId: string) => {
        setSelectedJurorIds((prev) => 
            prev.includes(userId) ? prev.filter((id) => id !== userId) : [...prev, userId]
        );
    };

    const selectAllFilteredJurors = () => {
        const allIds = filteredMembers.map((m) => m.id);
        setSelectedJurorIds((prev) => Array.from(new Set([...prev, ...allIds])));
    };

    const clearJurorSelection = () => {
        setSelectedJurorIds([]);
    };

    const handleAssignSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (selectedJurorIds.length === 0) {
            alert('Please select at least one juror peer from the peers list.');
            return;
        }

        try {
            await juryApi.assign({
                nomination_id: assignData.nomination_id,
                campaign_id: assignData.campaign_id,
                juror_user_ids: selectedJurorIds,
                deadline: assignData.deadline,
                remarks: assignData.remarks,
            });
            setIsAssignModal(false);
            setSelectedJurorIds([]);
            alert(`✓ Successfully appointed ${selectedJurorIds.length} juror(s)! Official appointment emails with the login portal link have been sent to their registered email addresses.`);
            loadData();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to assign jury members.');
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
                        Manage juror appointments by campaign, evaluation criteria weights, and candidate scoring reviews
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
                                setAssignData(prev => ({ 
                                    ...prev, 
                                    campaign_id: selectedCampaignId !== 'all' ? selectedCampaignId : campaigns[0].id, 
                                    nomination_id: 'all' 
                                }));
                            }
                            setIsAssignModal(true);
                        }}
                        className="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 fw-semibold shadow-xs"
                    >
                        <Plus size={14} /> Assign Jurors to Campaign
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

            {/* Top Navigation Tabs */}
            <div className="d-flex align-items-center justify-content-between mb-4">
                <div className="d-flex align-items-center bg-white p-1.5 rounded-4 border shadow-xs gap-1">
                    <button
                        type="button"
                        onClick={() => setActiveTab('assignments')}
                        className={`btn btn-sm rounded-3 px-3.5 py-2 fw-semibold transition-all d-flex align-items-center gap-2 ${
                            activeTab === 'assignments'
                                ? 'btn-primary shadow-xs text-white'
                                : 'text-secondary border-0 bg-transparent'
                        }`}
                    >
                        <Layers size={15} /> Active Campaigns & Juror Assignments ({assignments.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('criteria')}
                        className={`btn btn-sm rounded-3 px-3.5 py-2 fw-semibold transition-all d-flex align-items-center gap-2 ${
                            activeTab === 'criteria'
                                ? 'btn-primary shadow-xs text-white'
                                : 'text-secondary border-0 bg-transparent'
                        }`}
                    >
                        <Scale size={15} /> Scoring Criteria & Weights ({criteria.length})
                    </button>
                </div>
            </div>

            {activeTab === 'assignments' ? (
                <div>
                    {/* STRUCTURE WISE: STEP 1 - ACTIVE CAMPAIGNS ROSTER */}
                    <div className="card border-0 shadow-xs rounded-4 bg-white p-4 mb-4">
                        <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                            <div>
                                <div className="d-flex align-items-center gap-2 mb-1">
                                    <span className="badge bg-primary text-white font-monospace px-2.5 py-1" style={{ fontSize: '11px' }}>
                                        STEP 1
                                    </span>
                                    <h5 className="fw-bold text-dark mb-0 fs-6">Active Selection Campaigns</h5>
                                </div>
                                <p className="text-muted small mb-0">
                                    Click any campaign below to view appointed juries and candidate evaluations roster
                                </p>
                            </div>
                            <button 
                                className={`btn btn-sm rounded-pill px-3 py-1.5 fw-semibold transition-all ${
                                    selectedCampaignId === 'all' ? 'btn-dark' : 'btn-outline-secondary'
                                }`}
                                onClick={() => setSelectedCampaignId('all')}
                                style={{ fontSize: '12px' }}
                            >
                                View All Campaigns ({assignments.length})
                            </button>
                        </div>

                        <div className="row g-3">
                            {campaigns.map((camp) => {
                                const isSelected = selectedCampaignId === camp.id;
                                const campAsgs = assignments.filter((a) => (a.campaign_id || a.nomination?.campaign_id) === camp.id);
                                const distinctJuries = new Set(campAsgs.map((a) => a.juror_user_id)).size;
                                const completedAsgs = campAsgs.filter((a) => a.status === 'completed').length;
                                const campCandCount = candidates.filter((c) => c.campaign_id === camp.id && !c.application_number?.endsWith('-F2')).length;

                                return (
                                    <div key={camp.id} className="col-12 col-md-6 col-xl-4">
                                        <div 
                                            className={`card h-100 rounded-4 transition-all ${
                                                isSelected 
                                                    ? 'border-2 border-primary shadow-sm' 
                                                    : 'border bg-white hover-shadow'
                                            }`}
                                            onClick={() => setSelectedCampaignId(camp.id)}
                                            style={{ 
                                                cursor: 'pointer', 
                                                transition: 'all 0.2s ease',
                                                padding: '20px',
                                                backgroundColor: isSelected ? '#f8faff' : '#ffffff'
                                            }}
                                        >
                                            <div className="d-flex align-items-start justify-content-between gap-2 mb-2.5">
                                                <h6 className="fw-bold text-dark mb-0" style={{ fontSize: '15px', lineHeight: 1.4 }}>
                                                    {camp.name}
                                                </h6>
                                                <div className="d-flex align-items-center gap-1.5 flex-shrink-0">
                                                    {isSelected && (
                                                        <span className="badge bg-primary text-white rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1 font-monospace" style={{ fontSize: '11px' }}>
                                                            <Check size={11} /> Selected
                                                        </span>
                                                    )}
                                                    <span className="badge bg-success-subtle text-success border border-success-subtle text-uppercase px-2 py-1" style={{ fontSize: '10.5px' }}>
                                                        {camp.status || 'Active'}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="small text-muted mb-3 d-flex align-items-center gap-2" style={{ fontSize: '12.5px' }}>
                                                <span className="badge bg-light text-dark border px-2 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                                                    <Briefcase size={12} className="text-primary" /> {camp.role?.name || 'Executive Role'}
                                                </span>
                                                {camp.scope_value && (
                                                    <span className="text-secondary small">
                                                        • {camp.scope_value}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="d-flex align-items-center justify-content-between pt-3 border-top mt-auto">
                                                <div className="d-flex align-items-center gap-2">
                                                    <span className="badge bg-light text-dark border px-2.5 py-1.5 fw-semibold" style={{ fontSize: '11.5px' }}>
                                                        👥 {campCandCount} Candidates
                                                    </span>
                                                    <span className="badge bg-light text-dark border px-2.5 py-1.5 fw-semibold" style={{ fontSize: '11.5px' }}>
                                                        ⚖️ {distinctJuries} Juries
                                                    </span>
                                                </div>
                                                <div className="small fw-bold text-success d-flex align-items-center gap-1" style={{ fontSize: '12px' }}>
                                                    <CheckCircle2 size={13} /> {completedAsgs} Reviewed
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* STRUCTURE WISE: STEP 2 - APPOINTED JURIES FOR THIS CAMPAIGN */}
                    <div className="card border-0 shadow-xs rounded-4 bg-white p-4 mb-4">
                        <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                            <div className="d-flex align-items-center gap-2.5">
                                <span className="badge text-white font-monospace px-2.5 py-1" style={{ fontSize: '11px', backgroundColor: '#4f46e5' }}>
                                    STEP 2
                                </span>
                                <div>
                                    <h5 className="fw-bold text-dark mb-0 fs-6">
                                        Appointed Juries {currentCampaign ? `for "${currentCampaign.name}"` : '(All Campaigns)'} ({assignedJurors.length})
                                    </h5>
                                    <span className="text-muted small" style={{ fontSize: '12px' }}>
                                        Distinguished peer members appointed to score and evaluate candidate dossiers
                                    </span>
                                </div>
                            </div>

                            <button 
                                onClick={() => {
                                    setAssignData(prev => ({
                                        ...prev,
                                        campaign_id: selectedCampaignId !== 'all' ? selectedCampaignId : (campaigns[0]?.id || ''),
                                        nomination_id: 'all'
                                    }));
                                    setIsAssignModal(true);
                                }}
                                className="btn btn-outline-primary btn-sm rounded-3 px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5"
                            >
                                <Plus size={13} /> Appoint More Juries
                            </button>
                        </div>

                        {assignedJurors.length === 0 ? (
                            <div className="p-4 bg-light rounded-4 text-center border">
                                <p className="text-muted small mb-2">No juries assigned to this campaign yet.</p>
                                <button 
                                    onClick={() => {
                                        setAssignData(prev => ({
                                            ...prev,
                                            campaign_id: selectedCampaignId !== 'all' ? selectedCampaignId : (campaigns[0]?.id || ''),
                                            nomination_id: 'all'
                                        }));
                                        setIsAssignModal(true);
                                    }}
                                    className="btn btn-primary btn-sm rounded-3 px-3 py-1.5 fw-semibold"
                                >
                                    <Plus size={13} className="me-1" /> Appoint First Jury Member
                                </button>
                            </div>
                        ) : (
                            <div className="row g-3">
                                {assignedJurors.map((item, idx) => {
                                    const u = item.juror || {};
                                    const jurorName = (typeof u.display_name === 'string' && u.display_name.trim())
                                        ? u.display_name
                                        : (`${u.first_name || ''} ${u.last_name || ''}`.trim() || 'Jury Member');
                                    const busName = typeof u.business_name === 'object' && u.business_name !== null
                                        ? (u.business_name.name || 'Enterprise Peer')
                                        : (typeof u.company_name === 'string' ? u.company_name : (typeof u.business_name === 'string' ? u.business_name : 'Enterprise Services'));
                                    const busCat = typeof u.business_category === 'object' && u.business_category !== null
                                        ? (u.business_category.name || 'General Business')
                                        : (typeof u.main_business_category === 'object' && u.main_business_category !== null
                                            ? (u.main_business_category.name || 'General Business')
                                            : (typeof u.business_category === 'string' ? u.business_category : 'General Business'));
                                    const pct = item.total > 0 ? Math.round((item.completed / item.total) * 100) : 0;

                                    return (
                                        <div key={u.id || idx} className="col-12 col-md-6 col-xl-4">
                                            <div className="card h-100 rounded-4 border bg-white p-3 shadow-xs">
                                                <div className="d-flex align-items-start gap-2.5 mb-2.5">
                                                    <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style={{ width: '38px', height: '38px', fontSize: '14px' }}>
                                                        {jurorName.charAt(0).toUpperCase()}
                                                    </div>
                                                    <div className="overflow-hidden flex-grow-1">
                                                        <h6 className="fw-bold text-dark mb-0 text-truncate">{jurorName}</h6>
                                                        <div className="small text-muted text-truncate" style={{ fontSize: '11.5px' }}>{u.email || ''}</div>
                                                    </div>
                                                </div>

                                                <div className="p-2 bg-light rounded-3 mb-2.5 border" style={{ fontSize: '11.5px' }}>
                                                    <div className="text-dark fw-semibold text-truncate mb-0.5">
                                                        <Briefcase size={11} className="text-muted me-1 d-inline" /> {busName}
                                                    </div>
                                                    <div className="text-muted text-truncate">
                                                        <Tag size={11} className="text-muted me-1 d-inline" /> {busCat}
                                                    </div>
                                                </div>

                                                {/* Jury Panel Link */}
                                                <div className="d-flex align-items-center gap-1.5 mb-2.5">
                                                    <a
                                                        href={juryPanelUrl}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="btn btn-outline-primary btn-sm rounded-3 py-1 px-2 d-flex align-items-center gap-1 flex-grow-1 justify-content-center"
                                                        style={{ fontSize: '11px' }}
                                                    >
                                                        <ExternalLink size={11} /> Jury Panel
                                                    </a>
                                                    <button
                                                        onClick={() => copyJuryPanelLink(u.id || String(idx))}
                                                        className={`btn btn-sm rounded-3 py-1 px-2 d-flex align-items-center gap-1 ${copiedJuryLink === (u.id || String(idx)) ? 'btn-success text-white' : 'btn-outline-secondary'}`}
                                                        title="Copy jury admin panel link"
                                                        style={{ fontSize: '11px' }}
                                                    >
                                                        {copiedJuryLink === (u.id || String(idx))
                                                            ? <><Check size={11} /> Copied</>
                                                            : <><Copy size={11} /> Copy Link</>
                                                        }
                                                    </button>
                                                </div>

                                                <div className="mt-auto pt-2 border-top d-flex align-items-center justify-content-between">
                                                    <span className="small text-muted" style={{ fontSize: '11.5px' }}>
                                                        {item.completed} of {item.total} Candidates Reviewed
                                                    </span>
                                                    <span className={`badge ${pct === 100 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'}`} style={{ fontSize: '10.5px' }}>
                                                        {pct}% Done
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* STRUCTURE WISE: STEP 3 - CANDIDATES & EVALUATIONS ROSTER */}
                    <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                        <div className="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                            <div className="d-flex align-items-center gap-2.5">
                                <span className="badge bg-primary text-white font-monospace px-2.5 py-1" style={{ fontSize: '11px' }}>
                                    STEP 3
                                </span>
                                <div>
                                    <h5 className="fw-bold text-dark mb-0 fs-6">
                                        Candidate Evaluations Roster {currentCampaign ? `(${currentCampaign.name})` : ''}
                                    </h5>
                                    <span className="text-muted small" style={{ fontSize: '12px' }}>
                                        List of candidates, assigned juries, evaluation deadlines, and submitted reports
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="table-responsive rounded-4 border">
                            <table className="table table-hover align-middle mb-0">
                                <thead className="table-light">
                                    <tr className="text-secondary small">
                                        <th className="ps-3 py-3">Candidate</th>
                                        <th className="py-3">Campaign</th>
                                        <th className="py-3">Appointed Juror</th>
                                        <th className="py-3">Evaluation Deadline</th>
                                        <th className="py-3">Status</th>
                                        <th className="text-end pe-3 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {isLoading ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-4 text-muted">
                                                <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                                Loading assignments...
                                            </td>
                                        </tr>
                                    ) : campaignAssignments.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-4 text-muted">
                                                No jury assignments recorded for this selection.
                                            </td>
                                        </tr>
                                    ) : (
                                        campaignAssignments.map((asg) => {
                                            const isCompleted = asg.status === 'completed';
                                            const jurorBus = typeof asg.juror_business_name === 'string' && asg.juror_business_name.trim()
                                                ? asg.juror_business_name
                                                : (typeof asg.juror?.company_name === 'string' && asg.juror.company_name.trim()
                                                    ? asg.juror.company_name
                                                    : (typeof asg.juror?.business_name === 'string' && asg.juror.business_name.trim()
                                                        ? asg.juror.business_name
                                                        : 'Enterprise Peer'));
                                            const jurorCat = typeof asg.juror_business_category === 'string' && asg.juror_business_category.trim()
                                                ? asg.juror_business_category
                                                : (typeof asg.juror?.business_category === 'string' && asg.juror.business_category.trim()
                                                    ? asg.juror.business_category
                                                    : (typeof asg.juror?.business_category?.name === 'string'
                                                        ? asg.juror.business_category.name
                                                        : (typeof asg.juror?.main_business_category?.name === 'string'
                                                            ? asg.juror.main_business_category.name
                                                            : 'Business Member')));
                                            const candName = typeof asg.candidate_name === 'string' && asg.candidate_name.trim()
                                                ? asg.candidate_name
                                                : (typeof asg.nomination?.candidate_name === 'string'
                                                    ? asg.nomination.candidate_name
                                                    : (typeof asg.nomination_id === 'string' ? asg.nomination_id : 'Candidate'));
                                            const jurorName = typeof asg.jury_name === 'string' && asg.jury_name.trim()
                                                ? asg.jury_name
                                                : (typeof asg.juror?.display_name === 'string' && asg.juror.display_name.trim()
                                                    ? asg.juror.display_name
                                                    : (typeof asg.juror_user_id === 'string' ? asg.juror_user_id : 'Juror'));

                                            return (
                                                <tr key={asg.id}>
                                                    <td className="ps-3 fw-semibold text-dark">
                                                        <div>{candName}</div>
                                                        <div className="small text-muted font-monospace" style={{ fontSize: '11px' }}>
                                                            {asg.nomination?.application_number}
                                                        </div>
                                                    </td>
                                                    <td className="small text-muted">
                                                        {asg.campaign_name || asg.nomination?.campaign?.name || 'Campaign'}
                                                    </td>
                                                    <td>
                                                        <div className="fw-semibold text-dark">
                                                            {jurorName}
                                                        </div>
                                                        <div className="small text-muted" style={{ fontSize: '11px' }}>
                                                            {jurorBus} • {jurorCat}
                                                        </div>
                                                        <div className="small text-muted font-monospace" style={{ fontSize: '10.5px' }}>
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
                                                    <td className="text-end pe-3">
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
                    </div>
                </div>
            ) : (
                <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
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
                </div>
            )}

            {/* Assign Jury Modal (WITH MULTI-PEER SELECTION & BUSINESS DETAILS) */}
            {isAssignModal && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <form onSubmit={handleAssignSubmit}>
                                <div className="modal-header border-bottom px-4 py-3 bg-light">
                                    <div>
                                        <h5 className="modal-title fw-bold text-dark mb-0">Appoint Juries to Campaign</h5>
                                        <span className="text-muted small" style={{ fontSize: '12px' }}>
                                            Select candidate scope and choose one or multiple peer jurors with their business profiles
                                        </span>
                                    </div>
                                    <button type="button" className="btn-close" onClick={() => setIsAssignModal(false)}></button>
                                </div>
                                <div className="modal-body p-4">
                                    {/* Campaign Selector */}
                                    <div className="mb-3">
                                        <label className="form-label small fw-bold text-dark">Selection Campaign *</label>
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
                                            {campaigns.map((c) => {
                                                const yr = c.year || (c as any).campaign_year || '';
                                                const rName = typeof c.role?.name === 'string' ? c.role.name : 'Leadership';
                                                return (
                                                    <option key={c.id} value={c.id}>
                                                        {c.name} {yr ? `(${yr})` : ''} - {rName}
                                                    </option>
                                                );
                                            })}
                                        </select>
                                    </div>

                                    {/* Candidate Selector */}
                                    <div className="mb-3">
                                        <label className="form-label small fw-bold text-dark">Candidate(s) to Evaluate *</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={assignData.nomination_id}
                                            onChange={(e) => setAssignData({ ...assignData, nomination_id: e.target.value })}
                                            required
                                        >
                                            <option value="all">
                                                ⚡ Assign Juries to ALL Candidates in this Campaign ({campaignCandidates.length} Candidates)
                                            </option>
                                            {campaignCandidates.map((cand) => {
                                                const candName = typeof cand.candidate_name === 'string' 
                                                    ? cand.candidate_name 
                                                    : (typeof cand.full_name === 'string' ? cand.full_name : 'Candidate');
                                                const appNum = typeof cand.application_number === 'string' ? cand.application_number : '';
                                                const candRole = typeof cand.applied_role_name === 'string' 
                                                    ? cand.applied_role_name 
                                                    : (typeof cand.designation === 'string' ? cand.designation : 'Candidate');
                                                return (
                                                    <option key={cand.id} value={cand.id}>
                                                        {candName} {appNum ? `(${appNum})` : ''} - {candRole}
                                                    </option>
                                                );
                                            })}
                                        </select>
                                    </div>

                                    {/* MULTI-PEER SELECTOR WITH BUSINESS NAME & CATEGORY */}
                                    <div className="mb-3">
                                        <div className="d-flex align-items-center justify-content-between mb-1.5">
                                            <label className="form-label small fw-bold text-dark mb-0">
                                                Select Juror Peer(s) from Users Table *
                                            </label>
                                            <div className="d-flex align-items-center gap-2">
                                                <button 
                                                    type="button" 
                                                    className="btn btn-link text-decoration-none p-0 small text-primary fw-semibold"
                                                    style={{ fontSize: '11.5px' }}
                                                    onClick={selectAllFilteredJurors}
                                                >
                                                    Select All
                                                </button>
                                                <span className="text-muted small">•</span>
                                                <button 
                                                    type="button" 
                                                    className="btn btn-link text-decoration-none p-0 small text-muted"
                                                    style={{ fontSize: '11.5px' }}
                                                    onClick={clearJurorSelection}
                                                >
                                                    Clear
                                                </button>
                                            </div>
                                        </div>

                                        <div className="input-group input-group-sm mb-2">
                                            <span className="input-group-text bg-light text-muted border-end-0">
                                                <Search size={13} />
                                            </span>
                                            <input
                                                type="text"
                                                className="form-control form-control-sm border-start-0"
                                                placeholder="Search by peer name, business name, category, or email..."
                                                value={jurorSearch}
                                                onChange={(e) => setJurorSearch(e.target.value)}
                                            />
                                        </div>

                                        {/* Multi-peer Checkbox List */}
                                        <div 
                                            className="border rounded-3 p-2 bg-light bg-opacity-50 overflow-auto"
                                            style={{ maxHeight: '220px' }}
                                        >
                                            {filteredMembers.length === 0 ? (
                                                <div className="text-center py-3 text-muted small">
                                                    No peers found matching "{jurorSearch}".
                                                </div>
                                            ) : (
                                                filteredMembers.map((m) => {
                                                    const isChecked = selectedJurorIds.includes(m.id);
                                                    const peerName = (typeof m.display_name === 'string' && m.display_name.trim()) 
                                                        ? m.display_name 
                                                        : (`${m.first_name || ''} ${m.last_name || ''}`.trim() || 'Peer Member');

                                                    const busName = typeof m.business_name === 'object' && m.business_name !== null
                                                        ? (m.business_name.name || 'Enterprise Peer')
                                                        : (typeof m.business_name === 'string' && m.business_name.trim()
                                                            ? m.business_name
                                                            : (typeof m.company_name === 'string' && m.company_name.trim() ? m.company_name : 'Enterprise Peer'));

                                                    const busCat = typeof m.business_category === 'object' && m.business_category !== null
                                                        ? (m.business_category.name || 'Business Member')
                                                        : (typeof m.main_business_category === 'object' && m.main_business_category !== null
                                                            ? (m.main_business_category.name || 'Business Member')
                                                            : (typeof m.business_category === 'string' && m.business_category.trim()
                                                                ? m.business_category
                                                                : 'Business Member'));

                                                    const emailStr = typeof m.email === 'string' ? m.email : '';

                                                    return (
                                                        <div 
                                                            key={m.id}
                                                            className={`p-2 rounded-2 mb-1.5 border d-flex align-items-center justify-content-between gap-2 cursor-pointer transition-all ${
                                                                isChecked 
                                                                    ? 'bg-primary-subtle border-primary text-primary' 
                                                                    : 'bg-white border-light text-dark'
                                                            }`}
                                                            onClick={() => toggleJurorSelection(m.id)}
                                                            style={{ cursor: 'pointer' }}
                                                        >
                                                            <div className="d-flex align-items-center gap-2.5 overflow-hidden">
                                                                <input 
                                                                    type="checkbox" 
                                                                    className="form-check-input mt-0 flex-shrink-0"
                                                                    checked={isChecked}
                                                                    onChange={() => {}} // handled by parent onClick
                                                                />
                                                                <div className="overflow-hidden">
                                                                    <div className="fw-bold text-dark text-truncate" style={{ fontSize: '13px' }}>
                                                                        {peerName}
                                                                    </div>
                                                                    <div className="small text-muted text-truncate" style={{ fontSize: '11.5px' }}>
                                                                        <span className="fw-semibold text-secondary">{busName}</span> • <span className="text-primary">{busCat}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div className="text-end flex-shrink-0 font-monospace text-muted small" style={{ fontSize: '11px' }}>
                                                                {emailStr}
                                                            </div>
                                                        </div>
                                                    );
                                                })
                                            )}
                                        </div>

                                        <div className="d-flex align-items-center justify-content-between mt-2">
                                            <span className="badge bg-indigo-subtle text-indigo px-2.5 py-1 rounded-pill small fw-semibold">
                                                {selectedJurorIds.length} Peer(s) Selected
                                            </span>
                                            <span className="text-muted small" style={{ fontSize: '11.5px' }}>
                                                Each will receive an automated appointment email with Jury Login Portal link.
                                            </span>
                                        </div>
                                    </div>

                                    {/* Evaluation Deadline */}
                                    <div className="mb-3">
                                        <label className="form-label small fw-bold text-dark">Evaluation Deadline *</label>
                                        <input
                                            type="datetime-local"
                                            className="form-control form-control-sm"
                                            value={assignData.deadline}
                                            onChange={(e) => setAssignData({ ...assignData, deadline: e.target.value })}
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="modal-footer border-top px-4 py-2.5 bg-light">
                                    <button type="button" className="btn btn-light" onClick={() => setIsAssignModal(false)}>
                                        Cancel
                                    </button>
                                    <button 
                                        type="submit" 
                                        className="btn btn-primary px-4 fw-semibold shadow-xs"
                                        disabled={selectedJurorIds.length === 0}
                                    >
                                        Appoint {selectedJurorIds.length > 0 ? `(${selectedJurorIds.length}) Jurors` : ''} & Send Login Emails
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* FULL JURY EVALUATION REPORT MODAL (ALL DETAILS, CRITERIA SCORES & REMARKS) */}
            {selectedReview && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <div className="modal-header border-bottom px-4 py-3 bg-light">
                                <div>
                                    <h5 className="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                        <CheckCircle size={18} className="text-success" /> Official Jury Evaluation Report
                                    </h5>
                                    <p className="text-muted small mb-0">
                                        Reviewed by <strong>{selectedReview.jury_name || selectedReview.juror?.display_name}</strong> ({selectedReview.jury_email || selectedReview.juror?.email})
                                    </p>
                                </div>
                                <button type="button" className="btn-close" onClick={() => setSelectedReview(null)}></button>
                            </div>
                            <div className="modal-body p-4">
                                {/* Candidate & Campaign Banner */}
                                <div className="p-3 bg-light rounded-4 mb-4 border">
                                    <div className="row g-3">
                                        <div className="col-md-6">
                                            <span className="text-muted small fw-semibold text-uppercase" style={{ fontSize: '11px' }}>Candidate</span>
                                            <div className="fw-bold text-dark fs-6">{selectedReview.candidate_name}</div>
                                            <div className="text-muted small font-monospace">{selectedReview.nomination?.application_number}</div>
                                        </div>
                                        <div className="col-md-6">
                                            <span className="text-muted small fw-semibold text-uppercase" style={{ fontSize: '11px' }}>Campaign & Role</span>
                                            <div className="fw-bold text-dark fs-6">{selectedReview.campaign_name}</div>
                                            <div className="text-muted small">{selectedReview.nomination?.campaign?.role?.name || 'Executive Leadership'}</div>
                                        </div>
                                    </div>
                                </div>

                                {(() => {
                                    const rData = selectedReview.report_data || (selectedReview.report ? {
                                        overall_recommendation: selectedReview.report.overall_recommendation,
                                        strengths: selectedReview.report.strengths,
                                        concerns: selectedReview.report.concerns,
                                        final_remarks: selectedReview.report.final_remarks,
                                        submitted_at: selectedReview.report.submitted_at,
                                        scores: selectedReview.scores || []
                                    } : null);

                                    const recMap: Record<string, { label: string; class: string; icon: any }> = {
                                        strongly_recommend: { label: 'Strongly Recommend', class: 'bg-success text-white', icon: Star },
                                        recommend: { label: 'Recommend', class: 'bg-primary text-white', icon: Check },
                                        neutral: { label: 'Neutral / Review Further', class: 'bg-warning text-dark', icon: Clock },
                                        do_not_recommend: { label: 'Do Not Recommend', class: 'bg-danger text-white', icon: Trash2 },
                                    };

                                    const recKey = rData?.overall_recommendation?.toLowerCase() || 'recommend';
                                    const recInfo = recMap[recKey] || { label: recKey.toUpperCase(), class: 'bg-success text-white', icon: Check };
                                    const RecIcon = recInfo.icon;

                                    return (
                                        <div>
                                            {/* Overall Recommendation Callout */}
                                            <div className="mb-4 p-3 rounded-4 border d-flex align-items-center justify-content-between" style={{ backgroundColor: '#f8fafc' }}>
                                                <div>
                                                    <span className="text-muted small fw-bold text-uppercase" style={{ fontSize: '11px' }}>
                                                        Jury Overall Recommendation
                                                    </span>
                                                    <div className="mt-1">
                                                        <span className={`badge ${recInfo.class} px-3 py-1.5 rounded-pill text-uppercase font-monospace fw-bold fs-6 d-inline-flex align-items-center gap-1.5`}>
                                                            <RecIcon size={14} /> {recInfo.label}
                                                        </span>
                                                    </div>
                                                </div>
                                                {rData?.submitted_at && (
                                                    <div className="text-end small text-muted">
                                                        Submitted on:<br/>
                                                        <strong>{new Date(rData.submitted_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</strong>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Criteria Scores Breakdown */}
                                            {rData?.scores && rData.scores.length > 0 && (
                                                <div className="mb-4">
                                                    <h6 className="fw-bold text-dark border-bottom pb-2 mb-2 fs-6">
                                                        Criteria Evaluation Scores
                                                    </h6>
                                                    <div className="table-responsive border rounded-3 overflow-hidden">
                                                        <table className="table table-sm table-striped align-middle mb-0">
                                                            <thead className="table-light">
                                                                <tr style={{ fontSize: '11.5px' }}>
                                                                    <th className="ps-3">Criterion Name</th>
                                                                    <th>Category</th>
                                                                    <th>Max Pts</th>
                                                                    <th>Score Awarded</th>
                                                                    <th className="pe-3">Juror Remarks</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {rData.scores.map((sc: any, sIdx: number) => (
                                                                    <tr key={sc.criterion_id || sIdx} style={{ fontSize: '12px' }}>
                                                                        <td className="ps-3 fw-semibold text-dark">
                                                                            {sc.criterion_name || sc.criterion?.name || 'Evaluation Criterion'}
                                                                        </td>
                                                                        <td>
                                                                            <span className="badge bg-light text-muted border" style={{ fontSize: '10.5px' }}>
                                                                                {sc.category || sc.criterion?.category || 'General'}
                                                                            </span>
                                                                        </td>
                                                                        <td className="text-muted">{sc.max_score || sc.criterion?.max_score || 10} pts</td>
                                                                        <td>
                                                                            <span className="badge bg-primary text-white font-monospace px-2 py-1 fw-bold">
                                                                                {sc.score} pts
                                                                            </span>
                                                                        </td>
                                                                        <td className="pe-3 text-muted small">{sc.remarks || '-'}</td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Strengths & Merits */}
                                            <div className="mb-3">
                                                <h6 className="fw-bold text-success mb-1 d-flex align-items-center gap-1.5" style={{ fontSize: '13px' }}>
                                                    <CheckCircle size={14} /> Key Strengths & Leadership Merits
                                                </h6>
                                                <div className="p-3 bg-success-subtle bg-opacity-25 rounded-3 border border-success-subtle text-dark small" style={{ whiteSpace: 'pre-line' }}>
                                                    {rData?.strengths || 'No key strengths recorded.'}
                                                </div>
                                            </div>

                                            {/* Concerns & Limitations */}
                                            <div className="mb-3">
                                                <h6 className="fw-bold text-danger mb-1 d-flex align-items-center gap-1.5" style={{ fontSize: '13px' }}>
                                                    <Clock size={14} /> Key Concerns, Risks or Limitations
                                                </h6>
                                                <div className="p-3 bg-danger-subtle bg-opacity-25 rounded-3 border border-danger-subtle text-dark small" style={{ whiteSpace: 'pre-line' }}>
                                                    {rData?.concerns || 'No key concerns recorded.'}
                                                </div>
                                            </div>

                                            {/* Final Remarks */}
                                            {rData?.final_remarks && (
                                                <div className="mb-3">
                                                    <h6 className="fw-bold text-muted mb-1" style={{ fontSize: '13px' }}>
                                                        Final Operational Remarks & Board Notes
                                                    </h6>
                                                    <div className="p-3 bg-light rounded-3 border text-dark small" style={{ whiteSpace: 'pre-line' }}>
                                                        {rData.final_remarks}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })()}
                            </div>
                            <div className="modal-footer border-top px-4 py-2.5 bg-light">
                                <button type="button" className="btn btn-secondary btn-sm px-4 fw-semibold" onClick={() => setSelectedReview(null)}>
                                    Close Report
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
                                <div className="modal-header border-bottom-0 pb-1">
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
