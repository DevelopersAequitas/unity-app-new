import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { nominationApi, campaignApi } from '../../api/services';
import { Nomination, Campaign } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { ConfirmModal } from '../../components/common/ConfirmModal';
import {
    Search,
    UserCheck,
    XCircle,
    RotateCcw,
    Award,
    FileText,
    History,
    ShieldAlert,
    ExternalLink,
    Check,
    X,
    Clock,
    Link2,
    Mail,
    CheckCircle2,
    Copy,
    Filter,
    FolderKanban,
} from 'lucide-react';

export const NominationsPage: React.FC = () => {
    const location = useLocation();
    const navigate = useNavigate();

    // Check whether current view is Main form 1 or Sub form 2
    const isSubForm2 =
        location.pathname.includes('sub-form-2') ||
        location.pathname.includes('/sub') ||
        new URLSearchParams(location.search).get('form') === 'sub_form_2';
    const activeForm: 'main_form_1' | 'sub_form_2' = isSubForm2 ? 'sub_form_2' : 'main_form_1';

    const [nominations, setNominations] = useState<Nomination[]>([]);
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [roles, setRoles] = useState<{ id: string; key: string; name: string }[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [search, setSearch] = useState<string>('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [campaignFilter, setCampaignFilter] = useState<string>('');
    const [roleFilter, setRoleFilter] = useState<string>('');
    const [error, setError] = useState<string | null>(null);

    // Shareable Voting Link & Manual Email States
    const [copiedId, setCopiedId] = useState<string | null>(null);
    const [copiedForm2Id, setCopiedForm2Id] = useState<string | null>(null);
    const [sendingEmailId, setSendingEmailId] = useState<string | null>(null);
    const [emailSuccessMsg, setEmailSuccessMsg] = useState<string | null>(null);

    // Multi-selection for batch actions
    const [selectedNominationIds, setSelectedNominationIds] = useState<string[]>([]);

    // Proceed to Jury Modal State
    const [proceedJuryModal, setProceedJuryModal] = useState<{
        open: boolean;
        nominations: Nomination[];
        remarks: string;
        isLoading: boolean;
    }>({
        open: false,
        nominations: [],
        remarks: '',
        isLoading: false,
    });

    const getNominationVotingLink = (nom: Nomination) => {
        return nom.voting_link || `https://peersglobal.com/leadership/campaigns/${nom.campaign_id}/vote?candidate=${nom.id}`;
    };

    const getNominationForm2Link = (nom: Nomination) => {
        return nom.form_2_link || `${window.location.origin}/leadership/form-2/${nom.id}`;
    };

    const handleCopyVotingLink = (nom: Nomination) => {
        const link = getNominationVotingLink(nom);
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).catch(() => fallbackCopyText(link));
        } else {
            fallbackCopyText(link);
        }
        setCopiedId(nom.id);
        setTimeout(() => setCopiedId(null), 2500);
    };

    const handleCopyForm2Link = (nom: Nomination) => {
        const link = getNominationForm2Link(nom);
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).catch(() => fallbackCopyText(link));
        } else {
            fallbackCopyText(link);
        }
        setCopiedForm2Id(nom.id);
        setTimeout(() => setCopiedForm2Id(null), 2500);
    };

    const toggleSelectNomination = (id: string) => {
        setSelectedNominationIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
        );
    };

    const toggleSelectAll = () => {
        if (selectedNominationIds.length === nominations.length && nominations.length > 0) {
            setSelectedNominationIds([]);
        } else {
            setSelectedNominationIds(nominations.map((n) => n.id));
        }
    };

    const handleOpenProceedJuryModal = (nomsToProceed: Nomination[]) => {
        setProceedJuryModal({
            open: true,
            nominations: nomsToProceed,
            remarks: '',
            isLoading: false,
        });
    };

    const handleConfirmProceedJury = async () => {
        setProceedJuryModal((prev) => ({ ...prev, isLoading: true }));
        setError(null);
        try {
            const ids = proceedJuryModal.nominations.map((n) => n.id);
            if (ids.length === 1) {
                await nominationApi.proceedToJury(ids[0], proceedJuryModal.remarks);
            } else {
                await nominationApi.batchProceedToJury(ids, proceedJuryModal.remarks);
            }

            setEmailSuccessMsg(
                `Successfully proceeded ${ids.length} candidate(s) to Jury! Form 2 evaluation links dispatched via Email and WhatsApp.`
            );
            setTimeout(() => setEmailSuccessMsg(null), 6000);
            setProceedJuryModal({ open: false, nominations: [], remarks: '', isLoading: false });
            setSelectedNominationIds([]);
            loadNominations();
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to proceed nominees to Jury.');
            setProceedJuryModal((prev) => ({ ...prev, isLoading: false }));
        }
    };

    const fallbackCopyText = (text: string) => {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
        } catch (e) {
            console.error('Fallback copy failed', e);
        }
        document.body.removeChild(textArea);
    };

    const handleSendManualEmail = async (nom: Nomination) => {
        setSendingEmailId(nom.id);
        setError(null);
        try {
            const res = await nominationApi.sendApprovalEmail(nom.id);
            const target = nom.email || nom.candidate_name || 'candidate';
            setEmailSuccessMsg(res.data?.message || `Approval email with voting link sent successfully to ${target}!`);
            setTimeout(() => setEmailSuccessMsg(null), 6000);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to send approval email.');
        } finally {
            setSendingEmailId(null);
        }
    };

    // Detail Modal State (6 Tabs + Form 2 Tab)
    const [selectedNomination, setSelectedNomination] = useState<Nomination | null>(null);
    const [activeTab, setActiveTab] = useState<'profile' | 'answers' | 'diff' | 'documents' | 'declarations' | 'history' | 'form_2'>('profile');
    const [isLoadingDetail, setIsLoadingDetail] = useState<boolean>(false);

    // Consequential Action Modals
    const [actionModal, setActionModal] = useState<{
        type: 'approve' | 'reject' | 'correction' | 'shortlist';
        nomination: Nomination;
        reason?: string;
    } | null>(null);
    const [isActionLoading, setIsActionLoading] = useState<boolean>(false);

    // Initial load of campaigns & roles
    useEffect(() => {
        const fetchInitialData = async () => {
            try {
                const [campRes, roleRes] = await Promise.all([
                    campaignApi.getAll(),
                    campaignApi.getRoles(),
                ]);
                setCampaigns(campRes.data.data || []);
                const fetchedRoles = roleRes.data?.data || [];
                setRoles(
                    fetchedRoles.filter(
                        (r: any) =>
                            r.key !== 'global_admin' &&
                            !r.name?.toLowerCase().includes('global admin')
                    )
                );
            } catch (err: any) {
                console.error('Failed to load campaigns or roles:', err);
            }
        };
        fetchInitialData();
    }, []);

    const loadNominations = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const params: any = {};
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            if (campaignFilter) params.campaign_id = campaignFilter;
            if (roleFilter) params.role = roleFilter;
            params.form_type = activeForm;

            const nomRes = await nominationApi.getAll(params);
            setNominations(nomRes.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load nominations from server.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadNominations();
    }, [statusFilter, campaignFilter, roleFilter, activeForm]);

    const openDetailModal = async (nom: Nomination) => {
        setIsLoadingDetail(true);
        const isF2 = nom.form_type === 'sub_form_2' || !!nom.form_2_data || activeForm === 'sub_form_2';
        setActiveTab(isF2 ? 'form_2' : 'profile');
        try {
            const res = await nominationApi.getById(nom.id);
            setSelectedNomination(res.data.data);
        } catch {
            setSelectedNomination(nom);
        } finally {
            setIsLoadingDetail(false);
        }
    };

    const handleActionSubmit = async () => {
        if (!actionModal) return;
        setIsActionLoading(true);
        try {
            if (actionModal.type === 'approve') {
                await nominationApi.approve(actionModal.nomination.id, actionModal.reason);
            } else if (actionModal.type === 'reject') {
                if (!actionModal.reason?.trim()) {
                    alert('Rejection reason is mandatory.');
                    setIsActionLoading(false);
                    return;
                }
                await nominationApi.reject(actionModal.nomination.id, actionModal.reason);
            } else if (actionModal.type === 'correction') {
                if (!actionModal.reason?.trim()) {
                    alert('Correction notes are mandatory.');
                    setIsActionLoading(false);
                    return;
                }
                await nominationApi.requestCorrection(actionModal.nomination.id, actionModal.reason);
            } else if (actionModal.type === 'shortlist') {
                await nominationApi.shortlist(actionModal.nomination.id);
            }

            setActionModal(null);
            if (selectedNomination) {
                const refreshed = await nominationApi.getById(selectedNomination.id);
                setSelectedNomination(refreshed.data.data);
            }
            loadNominations();
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Action failed.');
        } finally {
            setIsActionLoading(false);
        }
    };

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <div className="d-flex align-items-center gap-2 mb-1">
                        <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Nomination Management</h1>
                        <span
                            className={`badge rounded-pill px-3 py-1.5 fw-semibold ${
                                activeForm === 'main_form_1'
                                    ? 'bg-primary-subtle text-primary border border-primary-subtle'
                                    : 'bg-info-subtle text-info border border-info-subtle'
                            }`}
                        >
                            {activeForm === 'main_form_1' ? 'Main form 1' : 'Sub form 2'}
                        </span>
                    </div>
                    <p className="text-muted small mb-0">
                        {activeForm === 'main_form_1'
                            ? 'All primary candidate nominations submitted directly from the website portal.'
                            : 'Secondary nomination applications and supplemental forms.'}
                    </p>
                </div>

                {/* Submenu form switcher tabs */}
                <div className="d-flex align-items-center bg-light p-1 rounded-3 border">
                    <button
                        type="button"
                        onClick={() => navigate('/nominations/main-form-1')}
                        className={`btn btn-sm rounded-3 px-3 py-1.5 fw-semibold transition-all ${
                            activeForm === 'main_form_1'
                                ? 'bg-white text-primary shadow-xs'
                                : 'text-muted border-0 bg-transparent'
                        }`}
                    >
                        <FileText size={14} className="me-1.5 d-inline" />
                        Main form 1
                    </button>
                    <button
                        type="button"
                        onClick={() => navigate('/nominations/sub-form-2')}
                        className={`btn btn-sm rounded-3 px-3 py-1.5 fw-semibold transition-all ${
                            activeForm === 'sub_form_2'
                                ? 'bg-white text-info shadow-xs'
                                : 'text-muted border-0 bg-transparent'
                        }`}
                    >
                        <FolderKanban size={14} className="me-1.5 d-inline" />
                        Sub form 2
                    </button>
                </div>
            </div>

            {emailSuccessMsg && (
                <div className="alert alert-success rounded-4 border-0 shadow-xs p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div className="d-flex align-items-center gap-2">
                        <CheckCircle2 size={18} className="text-success flex-shrink-0" />
                        <span>{emailSuccessMsg}</span>
                    </div>
                    <button className="btn btn-sm btn-outline-success" onClick={() => setEmailSuccessMsg(null)}>
                        Dismiss
                    </button>
                </div>
            )}

            {error && (
                <div className="alert alert-danger rounded-4 border-0 shadow-xs p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>{error}</div>
                    <button className="btn btn-sm btn-outline-danger" onClick={() => setError(null)}>
                        Dismiss
                    </button>
                </div>
            )}

            {/* Filter Bar */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-3 mb-4">
                <div className="row g-2 align-items-center">
                    <div className="col-12 col-md-3">
                        <div className="input-group input-group-sm">
                            <span className="input-group-text bg-white border-end-0">
                                <Search size={14} className="text-muted" />
                            </span>
                            <input
                                type="text"
                                className="form-control border-start-0"
                                placeholder="Search candidate name or app #..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && loadNominations()}
                            />
                        </div>
                    </div>

                    <div className="col-12 col-md-3">
                        <select
                            className="form-select form-select-sm"
                            value={campaignFilter}
                            onChange={(e) => setCampaignFilter(e.target.value)}
                        >
                            <option value="">All Campaigns</option>
                            {campaigns.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="col-12 col-md-2">
                        <select
                            className="form-select form-select-sm"
                            value={roleFilter}
                            onChange={(e) => setRoleFilter(e.target.value)}
                        >
                            <option value="">All Roles</option>
                            {roles.map((r) => (
                                <option key={r.id} value={r.name}>
                                    {r.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="col-12 col-md-2">
                        <select
                            className="form-select form-select-sm"
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                        >
                            <option value="">All Statuses</option>
                            <option value="submitted">Submitted</option>
                            <option value="under_review">Under Review</option>
                            <option value="correction_requested">Correction Requested</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="shortlisted">Shortlisted</option>
                        </select>
                    </div>

                    <div className="col-12 col-md-2 d-flex gap-2">
                        <button onClick={loadNominations} className="btn btn-primary btn-sm flex-grow-1 fw-semibold shadow-xs">
                            Filter
                        </button>
                        {(search || statusFilter || campaignFilter || roleFilter) && (
                            <button
                                onClick={() => {
                                    setSearch('');
                                    setStatusFilter('');
                                    setCampaignFilter('');
                                    setRoleFilter('');
                                }}
                                className="btn btn-outline-secondary btn-sm"
                                title="Reset all filters"
                            >
                                Reset
                            </button>
                        )}
                    </div>
                </div>

                {/* Role Quick Filter Pills (Matches Red Box in Screenshot 1) */}
                <div className="d-flex align-items-center gap-1.5 flex-wrap pt-3 mt-3 border-top">
                    <span className="text-muted small fw-semibold d-flex align-items-center gap-1 me-2">
                        <Filter size={13} className="text-primary" />
                        Role Filter:
                    </span>
                    <button
                        type="button"
                        onClick={() => setRoleFilter('')}
                        className={`btn btn-sm rounded-pill px-3 py-1 fs-12 fw-medium transition-all ${
                            roleFilter === ''
                                ? 'btn-dark text-white shadow-xs'
                                : 'btn-outline-secondary bg-white text-dark'
                        }`}
                    >
                        All Roles
                    </button>
                    {roles.map((r) => {
                        const isSelected =
                            roleFilter === r.id ||
                            roleFilter === r.key ||
                            roleFilter === r.name;
                        return (
                            <button
                                key={r.id}
                                type="button"
                                onClick={() => setRoleFilter(isSelected ? '' : r.name)}
                                className={`btn btn-sm rounded-pill px-3 py-1 fs-12 fw-medium transition-all ${
                                    isSelected
                                        ? 'btn-primary text-white shadow-xs'
                                        : 'btn-outline-secondary bg-white text-dark'
                                }`}
                            >
                                {r.name}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Multi-Selection Action Banner */}
            {selectedNominationIds.length > 0 && (
                <div className="alert alert-primary rounded-4 border-0 shadow-xs p-3 mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div className="d-flex align-items-center gap-2">
                        <span className="badge bg-primary text-white fs-6 px-3 py-1.5 rounded-pill">
                            {selectedNominationIds.length} Nominee(s) Selected
                        </span>
                        <span className="fw-semibold text-dark">
                            Ready to advance shortlisted candidates directly to the Jury Evaluation phase
                        </span>
                    </div>
                    <div className="d-flex align-items-center gap-2">
                        <button
                            onClick={() => {
                                const selectedNoms = nominations.filter((n) => selectedNominationIds.includes(n.id));
                                handleOpenProceedJuryModal(selectedNoms);
                            }}
                            className="btn btn-primary btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                        >
                            <Award size={15} /> Proceed to Jury ({selectedNominationIds.length})
                        </button>
                        <button
                            onClick={() => setSelectedNominationIds([])}
                            className="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1.5"
                        >
                            Deselect All
                        </button>
                    </div>
                </div>
            )}

            {/* Nominations Table */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th style={{ width: '40px' }} className="text-center">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={nominations.length > 0 && selectedNominationIds.length === nominations.length}
                                        onChange={toggleSelectAll}
                                        title="Select all nominees"
                                    />
                                </th>
                                <th>App #</th>
                                <th>Candidate</th>
                                <th>Applied Role</th>
                                <th>Campaign</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th className="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Loading nominations...
                                    </td>
                                </tr>
                            ) : nominations.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-5 text-muted">
                                        No nominations found matching the criteria.
                                    </td>
                                </tr>
                            ) : (
                                nominations.map((nom) => (
                                    <tr key={nom.id}>
                                        <td className="text-center">
                                            <input
                                                type="checkbox"
                                                className="form-check-input"
                                                checked={selectedNominationIds.includes(nom.id)}
                                                onChange={() => toggleSelectNomination(nom.id)}
                                            />
                                        </td>
                                        <td className="font-monospace small text-primary fw-semibold">{nom.application_number}</td>
                                        <td>
                                            <div className="d-flex align-items-center gap-2">
                                                <div
                                                    className="rounded-circle bg-light border d-flex align-items-center justify-content-center text-secondary fw-bold"
                                                    style={{ width: '36px', height: '36px', fontSize: '0.85rem' }}
                                                >
                                                    {(nom.candidate_name && nom.candidate_name.toLowerCase() !== 'candidate' ? nom.candidate_name : 'Hardik Chauhan').charAt(0).toUpperCase()}
                                                </div>
                                                <div>
                                                    <div className="fw-semibold text-dark">{nom.candidate_name && nom.candidate_name.toLowerCase() !== 'candidate' ? nom.candidate_name : (nom.user?.name || 'Hardik Chauhan')}</div>
                                                    <div className="text-muted small">{nom.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span className="badge bg-light text-dark border">{nom.applied_role_name || 'Candidate Role'}</span>
                                        </td>
                                        <td className="small text-muted">{nom.campaign_name || 'Campaign'}</td>
                                        <td className="small text-muted">
                                            {nom.submitted_at ? new Date(nom.submitted_at).toLocaleDateString() : 'Draft'}
                                        </td>
                                        <td>
                                            <div className="d-flex align-items-center gap-1 flex-wrap">
                                                <StatusBadge status={nom.status} />
                                                {nom.form_2_status === 'submitted' && (
                                                    <span className="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-1.5 fs-11" title="Form 2 Submitted">
                                                        F2 Done
                                                    </span>
                                                )}
                                                {nom.form_2_status === 'pending_submission' && (
                                                    <span className="badge bg-warning-subtle text-warning border border-warning-subtle py-0.5 px-1.5 fs-11" title="Form 2 Link Sent">
                                                        F2 Sent
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-end">
                                            <div className="d-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                                {activeForm === 'sub_form_2' ? (
                                                    <button
                                                        onClick={() => openDetailModal(nom)}
                                                        className="btn btn-primary btn-sm rounded-2 py-1 px-3 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs"
                                                    >
                                                        <FileText size={14} /> Review Form 2
                                                    </button>
                                                ) : (
                                                    <>
                                                        {['submitted', 'resubmitted', 'under_review'].includes(nom.status) && (
                                                            <>
                                                                <button
                                                                    onClick={() => setActionModal({ type: 'approve', nomination: nom, reason: '' })}
                                                                    className="btn btn-success btn-sm rounded-2 py-1 px-2.5 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs"
                                                                    title="Approve Nomination Request"
                                                                >
                                                                    <Check size={14} /> Approve
                                                                </button>
                                                                <button
                                                                    onClick={() => setActionModal({ type: 'reject', nomination: nom, reason: '' })}
                                                                    className="btn btn-outline-danger btn-sm rounded-2 py-1 px-2.5 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs"
                                                                    title="Reject Nomination Request"
                                                                >
                                                                    <X size={14} /> Reject
                                                                </button>
                                                            </>
                                                        )}
                                                        {['approved', 'shortlisted'].includes(nom.status) && (
                                                            <>
                                                                <button
                                                                    onClick={() => handleCopyVotingLink(nom)}
                                                                    className={`btn btn-sm rounded-2 py-1 px-2.5 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs ${
                                                                        copiedId === nom.id ? 'btn-success text-white' : 'btn-outline-success'
                                                                    }`}
                                                                    title="Copy Shareable Voting Link"
                                                                >
                                                                    {copiedId === nom.id ? (
                                                                        <>
                                                                            <Check size={14} /> Copied!
                                                                        </>
                                                                    ) : (
                                                                        <>
                                                                            <Link2 size={14} /> Copy Link
                                                                        </>
                                                                    )}
                                                                </button>
                                                                <button
                                                                    onClick={() => handleSendManualEmail(nom)}
                                                                    disabled={sendingEmailId === nom.id}
                                                                    className="btn btn-outline-secondary btn-sm rounded-2 py-1 px-2.5 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs"
                                                                    title="Send Official Approval Email to Candidate"
                                                                >
                                                                    {sendingEmailId === nom.id ? (
                                                                        <>
                                                                            <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                                            Sending...
                                                                        </>
                                                                    ) : (
                                                                        <>
                                                                            <Mail size={14} /> Send Mail
                                                                        </>
                                                                    )}
                                                                </button>
                                                                <button
                                                                    onClick={() => handleOpenProceedJuryModal([nom])}
                                                                    className="btn btn-outline-primary btn-sm rounded-2 py-1 px-2.5 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs"
                                                                    title="Proceed Candidate to Jury & Send Form 2 Registration Link"
                                                                >
                                                                    <Award size={14} className="text-primary" /> Proceed to Jury
                                                                </button>
                                                            </>
                                                        )}
                                                        <button
                                                            onClick={() => openDetailModal(nom)}
                                                            className="btn btn-outline-primary btn-sm rounded-2 py-1 px-2.5 fw-semibold"
                                                        >
                                                            Review
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* 6-Tab Detailed Review Modal */}
            {selectedNomination && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.6)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <div className="modal-header border-bottom pb-3">
                                <div>
                                    <div className="d-flex align-items-center gap-2 mb-1">
                                        <span className="badge bg-light text-primary border font-monospace">
                                            {selectedNomination.application_number}
                                        </span>
                                        <StatusBadge status={selectedNomination.status} />
                                    </div>
                                    <h4 className="fw-bold text-dark mb-0">{selectedNomination.candidate_name}</h4>
                                </div>
                                <button type="button" className="btn-close" onClick={() => setSelectedNomination(null)}></button>
                            </div>

                            {/* 6 Navigation Tabs */}
                            <div className="bg-light px-4 pt-2 border-bottom">
                                <ul className="nav nav-tabs border-bottom-0">
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link border-0 fw-semibold ${activeTab === 'profile' ? 'active text-primary' : 'text-muted'}`}
                                            onClick={() => setActiveTab('profile')}
                                        >
                                            Tab 1: Profile
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link border-0 fw-semibold ${activeTab === 'answers' ? 'active text-primary' : 'text-muted'}`}
                                            onClick={() => setActiveTab('answers')}
                                        >
                                            Tab 2: Form Answers
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link border-0 fw-semibold ${activeTab === 'diff' ? 'active text-primary' : 'text-muted'}`}
                                            onClick={() => setActiveTab('diff')}
                                        >
                                            Tab 3: Profile Diffs
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link border-0 fw-semibold ${activeTab === 'documents' ? 'active text-primary' : 'text-muted'}`}
                                            onClick={() => setActiveTab('documents')}
                                        >
                                            Tab 4: Documents
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link border-0 fw-semibold ${activeTab === 'declarations' ? 'active text-primary' : 'text-muted'}`}
                                            onClick={() => setActiveTab('declarations')}
                                        >
                                            Tab 5: Declarations
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link border-0 fw-semibold ${activeTab === 'history' ? 'active text-primary' : 'text-muted'}`}
                                            onClick={() => setActiveTab('history')}
                                        >
                                            Tab 6: Activity History
                                        </button>
                                    </li>
                                    {(selectedNomination.form_type === 'sub_form_2' || selectedNomination.form_2_data || activeForm === 'sub_form_2') && (
                                        <li className="nav-item">
                                            <button
                                                className={`nav-link border-0 fw-semibold ${activeTab === 'form_2' ? 'active text-primary border-bottom border-primary border-2' : 'text-muted'}`}
                                                onClick={() => setActiveTab('form_2')}
                                            >
                                                <span className="badge bg-primary-subtle text-primary me-1">Form 2</span> Jury Data
                                            </button>
                                        </li>
                                    )}
                                </ul>
                            </div>

                            <div className="modal-body py-4">
                                {isLoadingDetail ? (
                                    <div className="text-center py-5">
                                        <div className="spinner-border text-primary"></div>
                                    </div>
                                ) : (
                                    <>
                                        {/* Tab 1: Profile */}
                                        {activeTab === 'profile' && (
                                            <div className="row g-3">
                                                {['approved', 'shortlisted'].includes(selectedNomination.status) && (
                                                    <div className="col-12">
                                                        <div className="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                                                            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                                                <div className="flex-grow-1">
                                                                    <div className="d-flex align-items-center gap-2 text-success fw-bold">
                                                                        <CheckCircle2 size={18} />
                                                                        <span>Nomination Approved - Shareable Voting Link Ready</span>
                                                                    </div>
                                                                    <p className="text-secondary small mb-2 mt-1">
                                                                        Direct voting link for voters to cast verified OTP votes for this candidate:
                                                                    </p>
                                                                    <div className="input-group input-group-sm" style={{ maxWidth: '640px' }}>
                                                                        <span className="input-group-text bg-white text-muted">
                                                                            <Link2 size={14} />
                                                                        </span>
                                                                        <input
                                                                            type="text"
                                                                            readOnly
                                                                            className="form-control font-monospace bg-white"
                                                                            value={getNominationVotingLink(selectedNomination)}
                                                                        />
                                                                        <button
                                                                            className={`btn ${copiedId === selectedNomination.id ? 'btn-success' : 'btn-primary'} fw-semibold`}
                                                                            onClick={() => handleCopyVotingLink(selectedNomination)}
                                                                        >
                                                                            {copiedId === selectedNomination.id ? 'Copied!' : 'Copy Link'}
                                                                        </button>
                                                                        <a
                                                                            href={getNominationVotingLink(selectedNomination)}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="btn btn-outline-secondary"
                                                                        >
                                                                            <ExternalLink size={14} /> Open
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                                <div className="d-flex flex-column align-items-md-end gap-1 flex-shrink-0">
                                                                    <span className="text-muted small">Need to notify candidate?</span>
                                                                    <button
                                                                        onClick={() => handleSendManualEmail(selectedNomination)}
                                                                        disabled={sendingEmailId === selectedNomination.id}
                                                                        className="btn btn-success btn-sm fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs"
                                                                    >
                                                                        {sendingEmailId === selectedNomination.id ? (
                                                                            <>
                                                                                <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                                                Sending...
                                                                            </>
                                                                        ) : (
                                                                            <>
                                                                                <Mail size={14} /> Send Approval Email
                                                                            </>
                                                                        )}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                                <div className="col-12 col-md-6">
                                                    <div className="p-3 rounded-3 bg-light border">
                                                        <span className="text-muted small">Full Name</span>
                                                        <div className="fw-bold text-dark">{selectedNomination.candidate_name}</div>
                                                    </div>
                                                </div>
                                                <div className="col-12 col-md-6">
                                                    <div className="p-3 rounded-3 bg-light border">
                                                        <span className="text-muted small">Email Address</span>
                                                        <div className="fw-bold text-dark">{selectedNomination.email}</div>
                                                    </div>
                                                </div>
                                                <div className="col-12 col-md-6">
                                                    <div className="p-3 rounded-3 bg-light border">
                                                        <span className="text-muted small">Mobile Number</span>
                                                        <div className="fw-bold text-dark">{selectedNomination.mobile}</div>
                                                    </div>
                                                </div>
                                                <div className="col-12 col-md-6">
                                                    <div className="p-3 rounded-3 bg-light border">
                                                        <span className="text-muted small">Campaign & Role</span>
                                                        <div className="fw-bold text-dark">
                                                            {selectedNomination.applied_role_name || 'Role'} - {selectedNomination.campaign_name}
                                                        </div>
                                                    </div>
                                                </div>
                                                {selectedNomination.rejection_reason && (
                                                    <div className="col-12">
                                                        <div className="alert alert-danger mb-0">
                                                            <strong>Rejection Reason:</strong> {selectedNomination.rejection_reason}
                                                        </div>
                                                    </div>
                                                )}
                                                {selectedNomination.correction_notes && (
                                                    <div className="col-12">
                                                        <div className="alert alert-warning mb-0">
                                                            <strong>Requested Corrections:</strong> {selectedNomination.correction_notes}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* Tab 2: Answers */}
                                        {activeTab === 'answers' && (
                                            <div>
                                                <h6 className="fw-bold text-dark mb-3">Submitted Dynamic Answers</h6>
                                                {selectedNomination.answers && selectedNomination.answers.length > 0 ? (
                                                    <div className="list-group">
                                                        {selectedNomination.answers.map((ans) => (
                                                            <div key={ans.id} className="list-group-item p-3 mb-2 rounded-3 border">
                                                                <div className="text-muted small fw-semibold">
                                                                    {ans.question_label || 'Question'}
                                                                </div>
                                                                <div className="fw-bold text-dark mt-1">
                                                                    {ans.answer_text || JSON.stringify(ans.answer_json) || 'No answer provided'}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <div className="text-muted py-4 text-center">No questionnaire answers recorded.</div>
                                                )}
                                            </div>
                                        )}

                                        {/* Tab 3: Diffs */}
                                        {activeTab === 'diff' && (
                                            <div>
                                                <h6 className="fw-bold text-dark mb-3">Unity Profile vs Submitted Diffs</h6>
                                                {selectedNomination.profile_diff &&
                                                Object.keys(selectedNomination.profile_diff).length > 0 ? (
                                                    <table className="table table-bordered align-middle">
                                                        <thead className="table-light">
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Original Unity Value</th>
                                                                <th>Candidate Submitted Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {Object.entries(selectedNomination.profile_diff).map(([key, diff]) => (
                                                                <tr key={key}>
                                                                    <td className="fw-semibold text-capitalize">{key.replace(/_/g, ' ')}</td>
                                                                    <td className="text-muted">{String(diff.original ?? 'Empty')}</td>
                                                                    <td className="text-success fw-bold">{String(diff.submitted ?? 'Empty')}</td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                ) : (
                                                    <div className="text-muted py-4 text-center">
                                                        No differences detected between Unity directory profile and nomination entry.
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* Tab 4: Documents */}
                                        {activeTab === 'documents' && (
                                            <div>
                                                <h6 className="fw-bold text-dark mb-3">Supporting Uploaded Documents</h6>
                                                {selectedNomination.documents && selectedNomination.documents.length > 0 ? (
                                                    <div className="row g-3">
                                                        {selectedNomination.documents.map((doc) => (
                                                            <div key={doc.id} className="col-12 col-md-6">
                                                                <div className="p-3 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                                                                    <div>
                                                                        <div className="fw-bold text-dark">{doc.document_type}</div>
                                                                        <div className="text-muted small">{doc.file_name}</div>
                                                                    </div>
                                                                    <div className="d-flex align-items-center gap-2">
                                                                        <StatusBadge status={doc.status} />
                                                                        {doc.file_url && (
                                                                            <a
                                                                                href={doc.file_url}
                                                                                target="_blank"
                                                                                rel="noreferrer"
                                                                                className="btn btn-outline-secondary btn-sm"
                                                                            >
                                                                                <ExternalLink size={14} />
                                                                            </a>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <div className="text-muted py-4 text-center">No documents uploaded.</div>
                                                )}
                                            </div>
                                        )}

                                        {/* Tab 5: Declarations */}
                                        {activeTab === 'declarations' && (
                                            <div className="p-3 bg-light rounded-3 border">
                                                <h6 className="fw-bold text-dark mb-2">Consent & Integrity Declaration</h6>
                                                <p className="text-muted small mb-3">
                                                    Candidate verified truthfulness of all submitted leadership credentials and agreed to organizational guidelines upon OTP verification.
                                                </p>
                                                <div className="d-flex align-items-center gap-2 text-success fw-semibold">
                                                    <Check size={18} /> Verified & Timestamped on Submission ({selectedNomination.submitted_at || 'Recorded'})
                                                </div>
                                            </div>
                                        )}

                                        {/* Tab 6: History */}
                                        {activeTab === 'history' && (
                                            <div>
                                                <h6 className="fw-bold text-dark mb-3">Audit Lifecycle History</h6>
                                                {selectedNomination.history && selectedNomination.history.length > 0 ? (
                                                    <div className="timeline ps-3 border-start">
                                                        {selectedNomination.history.map((hist) => (
                                                            <div key={hist.id} className="mb-3 ps-3 position-relative">
                                                                <div className="fw-semibold text-dark">{hist.action}</div>
                                                                <div className="text-muted small">
                                                                    {hist.remarks || 'Status update'} •{' '}
                                                                    {new Date(hist.created_at).toLocaleString()}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <div className="text-muted py-4 text-center">No lifecycle changes logged yet.</div>
                                                )}
                                            </div>
                                        )}

                                        {/* Form 2: Jury Evaluation Data Form (Sections A to H) */}
                                        {activeTab === 'form_2' && (() => {
                                            const rawF2 =
                                                selectedNomination.form_2_data ||
                                                (selectedNomination.profile_snapshot as any)?.form_2 ||
                                                (selectedNomination.form_type === 'sub_form_2' ? (selectedNomination.profile_snapshot as any) : null);

                                            const secA = rawF2?.section_a_business_credibility || rawF2;
                                            const secB = rawF2?.section_b_leadership_track_record || rawF2;
                                            const secC = rawF2?.section_c_network_strength || rawF2;
                                            const secD = rawF2?.section_d_leadership_bench || rawF2;
                                            const secE = rawF2?.section_e_12_month_plan || rawF2;
                                            const secF = rawF2?.section_f_commitment_fit || rawF2;
                                            const secG = rawF2?.section_g_references || rawF2;
                                            const secH = rawF2?.section_h_documents || rawF2;

                                            const hasData = rawF2 && (secA?.business_type || secB?.largest_community || secD?.bench_members?.length || secA?.annual_turnover_band);

                                            if (!hasData) {
                                                return (
                                                    <div className="p-4 text-center">
                                                        <div className="p-4 rounded-4 bg-light border max-w-lg mx-auto" style={{ maxWidth: '580px' }}>
                                                            <div className="text-warning mb-3">
                                                                <Clock size={40} className="mx-auto" />
                                                            </div>
                                                            <h5 className="fw-bold text-dark">Form 2 Submission Pending</h5>
                                                            <p className="text-secondary small mb-3">
                                                                This nominee has been shortlisted for the Jury Evaluation phase, but has not yet submitted their Form 2 answers.
                                                            </p>
                                                            <div className="d-flex justify-content-center gap-2 flex-wrap">
                                                                <button
                                                                    onClick={() => handleCopyForm2Link(selectedNomination)}
                                                                    className="btn btn-primary btn-sm fw-semibold d-inline-flex align-items-center gap-1.5"
                                                                >
                                                                    {copiedForm2Id === selectedNomination.id ? <Check size={14} /> : <Copy size={14} />}
                                                                    {copiedForm2Id === selectedNomination.id ? 'Copied Link!' : 'Copy Form 2 Link'}
                                                                </button>
                                                                <a
                                                                    href={getNominationForm2Link(selectedNomination)}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="btn btn-outline-secondary btn-sm fw-semibold d-inline-flex align-items-center gap-1"
                                                                >
                                                                    <ExternalLink size={14} /> Preview Form 2 Portal
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            }

                                            const benchList: any[] = Array.isArray(secD?.bench_members)
                                                ? secD.bench_members
                                                : Array.isArray(secD?.bench)
                                                ? secD.bench
                                                : [];

                                            return (
                                                <div className="d-flex flex-column gap-3">
                                                    {/* Header Banner */}
                                                    <div className="p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                                        <div>
                                                            <div className="d-flex align-items-center gap-2 mb-1">
                                                                <span className="badge bg-primary text-white">FORM 2: JURY EVALUATION</span>
                                                                <span className="fw-bold text-dark">{selectedNomination.candidate_name}</span>
                                                            </div>
                                                            <span className="text-muted small">
                                                                Applied Role: <strong>{selectedNomination.applied_role_name || selectedNomination.campaign?.role?.name || 'Leadership Role'}</strong>
                                                            </span>
                                                        </div>
                                                        <div className="d-flex align-items-center gap-2">
                                                            <span className="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1">
                                                                <CheckCircle2 size={12} /> Email Verified
                                                            </span>
                                                            <button
                                                                onClick={() => handleCopyForm2Link(selectedNomination)}
                                                                className="btn btn-outline-primary btn-sm py-1 px-2.5 fw-semibold d-inline-flex align-items-center gap-1"
                                                                title="Copy direct Form 2 link"
                                                            >
                                                                {copiedForm2Id === selectedNomination.id ? <Check size={13} /> : <Copy size={13} />}
                                                                {copiedForm2Id === selectedNomination.id ? 'Copied' : 'Form 2 Link'}
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {/* Section A: Business Credibility */}
                                                    <div className="border rounded-3 p-3 bg-white">
                                                        <div className="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                                            <span className="badge bg-dark text-white font-monospace">Section A</span>
                                                            <h6 className="fw-bold text-dark mb-0">Business Credibility</h6>
                                                        </div>
                                                        <div className="row g-3">
                                                            <div className="col-12 col-md-3">
                                                                <span className="text-muted small d-block">Business Type</span>
                                                                <span className="fw-bold text-dark">{secA?.business_type || '—'}</span>
                                                            </div>
                                                            <div className="col-12 col-md-3">
                                                                <span className="text-muted small d-block">Year Founded</span>
                                                                <span className="fw-bold text-dark">{secA?.year_founded || '—'}</span>
                                                            </div>
                                                            <div className="col-12 col-md-3">
                                                                <span className="text-muted small d-block">Annual Turnover Band</span>
                                                                <span className="badge bg-primary-subtle text-primary fw-bold fs-12 px-2.5 py-1">
                                                                    {secA?.annual_turnover_band || '—'}
                                                                </span>
                                                            </div>
                                                            <div className="col-12 col-md-3">
                                                                <span className="text-muted small d-block">Team Size</span>
                                                                <span className="fw-bold text-dark">{secA?.team_size || '—'}</span>
                                                            </div>
                                                            <div className="col-12 col-md-8">
                                                                <span className="text-muted small d-block">Top 3 Business Achievements</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secA?.achievements || secA?.top_3_achievements || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-muted small d-block">Registration Proof (GST/Incorporation)</span>
                                                                {secA?.registration_doc_url ? (
                                                                    <a
                                                                        href={secA.registration_doc_url}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="btn btn-outline-primary btn-sm mt-1 d-inline-flex align-items-center gap-1.5"
                                                                    >
                                                                        <ExternalLink size={13} /> View Registration Proof
                                                                    </a>
                                                                ) : (
                                                                    <span className="text-muted small fst-italic">Not uploaded</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Section B: Leadership Track Record */}
                                                    <div className="border rounded-3 p-3 bg-white">
                                                        <div className="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                                            <span className="badge bg-dark text-white font-monospace">Section B</span>
                                                            <h6 className="fw-bold text-dark mb-0">Leadership Track Record</h6>
                                                        </div>
                                                        <div className="row g-3">
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">Leadership Roles Held</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secB?.leadership_roles || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">Largest Community / Group Built</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secB?.largest_community || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">Leaders Developed</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secB?.developed_leaders || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">Failure as a Leader & Lessons Learned</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1 border-start border-warning border-3" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secB?.leadership_failure_learning || '—'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Section C: Network Strength */}
                                                    <div className="border rounded-3 p-3 bg-white">
                                                        <div className="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                                            <span className="badge bg-dark text-white font-monospace">Section C</span>
                                                            <h6 className="fw-bold text-dark mb-0">Network Strength</h6>
                                                        </div>
                                                        <div className="row g-3">
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-muted small d-block">Business Associations / Chambers</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secC?.associations || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-muted small d-block">Institutions You Can Access</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secC?.institutions_access || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-muted small d-block">Potential Sponsors or Partners</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secC?.sponsors_partners || '—'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Section D: Leadership Bench */}
                                                    <div className="border rounded-3 p-3 bg-white">
                                                        <div className="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-2 mb-3 gap-2">
                                                            <div className="d-flex align-items-center gap-2">
                                                                <span className="badge bg-dark text-white font-monospace">Section D</span>
                                                                <h6 className="fw-bold text-dark mb-0">Leadership Bench ("Who will you bring?")</h6>
                                                            </div>
                                                            <div className="d-flex align-items-center gap-2">
                                                                <span className="badge bg-secondary-subtle text-secondary small">
                                                                    {benchList.length} Leaders Named
                                                                </span>
                                                                {secD?.declaration_confirmed && (
                                                                    <span className="badge bg-success-subtle text-success border border-success-subtle small d-inline-flex align-items-center gap-1">
                                                                        <Check size={12} /> Consent Declared
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>

                                                        {benchList.length > 0 ? (
                                                            <div className="table-responsive">
                                                                <table className="table table-bordered table-sm align-middle fs-13 mb-0">
                                                                    <thead className="table-light">
                                                                        <tr>
                                                                            <th>#</th>
                                                                            <th>Leader Name & Mobile</th>
                                                                            <th>City / Industry</th>
                                                                            <th>Relationship</th>
                                                                            <th>Candidate Status</th>
                                                                            <th>Proposed Role / Circle</th>
                                                                            <th>Timeline</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {benchList.map((bm: any, bIdx: number) => (
                                                                            <tr key={bIdx}>
                                                                                <td className="fw-bold text-muted">{bIdx + 1}</td>
                                                                                <td>
                                                                                    <div className="fw-bold text-dark">{bm.name || '—'}</div>
                                                                                    <div className="text-muted small">{bm.mobile || '—'}</div>
                                                                                </td>
                                                                                <td>
                                                                                    <div className="text-dark">{bm.city || '—'}</div>
                                                                                    <div className="text-muted small">{bm.industry || '—'}</div>
                                                                                </td>
                                                                                <td>
                                                                                    <span className="text-secondary">{bm.relationship || '—'}</span>
                                                                                </td>
                                                                                <td>
                                                                                    <span
                                                                                        className={`badge ${
                                                                                            bm.status === 'Committed'
                                                                                                ? 'bg-success text-white'
                                                                                                : bm.status === 'Spoken, interested'
                                                                                                ? 'bg-warning-subtle text-warning border border-warning-subtle'
                                                                                                : 'bg-light text-muted border'
                                                                                        }`}
                                                                                    >
                                                                                        {bm.status || '—'}
                                                                                    </span>
                                                                                </td>
                                                                                <td>
                                                                                    <span className="fw-semibold text-primary">{bm.proposed_role || '—'}</span>
                                                                                </td>
                                                                                <td>
                                                                                    <span className="badge bg-info-subtle text-info">
                                                                                        {bm.timeline ? `${bm.timeline} days` : '—'}
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                        ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        ) : (
                                                            <div className="text-muted text-center py-3 bg-light rounded">No bench members entered.</div>
                                                        )}
                                                    </div>

                                                    {/* Section E: 12-Month Plan */}
                                                    <div className="border rounded-3 p-3 bg-white">
                                                        <div className="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                                            <span className="badge bg-dark text-white font-monospace">Section E</span>
                                                            <h6 className="fw-bold text-dark mb-0">12-Month Plan (Role-Specific)</h6>
                                                        </div>
                                                        <div className="row g-3">
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">Targets (6 & 12 months)</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secE?.targets_6_12_months || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">First 90 Days Actions</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secE?.first_90_days || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">How you will fund Year 1</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secE?.funding_plan || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">Biggest Risk & Handling Strategy</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1 border-start border-danger border-3" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secE?.biggest_risk || '—'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Section F: Commitment and Fit */}
                                                    <div className="border rounded-3 p-3 bg-white">
                                                        <div className="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                                            <span className="badge bg-dark text-white font-monospace">Section F</span>
                                                            <h6 className="fw-bold text-dark mb-0">Commitment and Fit</h6>
                                                        </div>
                                                        <div className="row g-3">
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-muted small d-block">Hours per week</span>
                                                                <span className="fw-bold text-dark">{secF?.hours_per_week || '—'}</span>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-muted small d-block">Competing commitments</span>
                                                                <span className="fw-bold text-dark">{secF?.competing_commitments || '—'}</span>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-muted small d-block">Performance-Based Tenure</span>
                                                                <span className={`badge ${secF?.performance_tenure_accepted ? 'bg-success' : 'bg-secondary'} px-2.5 py-1`}>
                                                                    {secF?.performance_tenure_accepted ? 'Yes, Accepted' : 'No'}
                                                                </span>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">Why Peers Global?</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secF?.why_peers_global || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-muted small d-block">Role Understanding</span>
                                                                <div className="p-2.5 rounded bg-light border small text-dark mt-1" style={{ whiteSpace: 'pre-line' }}>
                                                                    {secF?.role_understanding || '—'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Section G: References */}
                                                    <div className="border rounded-3 p-3 bg-white">
                                                        <div className="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                                            <span className="badge bg-dark text-white font-monospace">Section G</span>
                                                            <h6 className="fw-bold text-dark mb-0">References</h6>
                                                        </div>
                                                        <div className="row g-3">
                                                            <div className="col-12 col-md-6">
                                                                <div className="p-3 rounded-2 bg-light border">
                                                                    <span className="badge bg-primary-subtle text-primary mb-2">Reference 1 (Business Reference)</span>
                                                                    <div className="fw-bold text-dark fs-14">{secG?.ref_1_name || '—'}</div>
                                                                    <div className="text-muted small mt-1">Relationship: <strong className="text-dark">{secG?.ref_1_relationship || '—'}</strong></div>
                                                                    <div className="text-muted small">Mobile: <strong className="text-dark">{secG?.ref_1_mobile || '—'}</strong></div>
                                                                    <div className="text-muted small">Years Known: <strong className="text-dark">{secG?.ref_1_years || '—'}</strong></div>
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <div className="p-3 rounded-2 bg-light border">
                                                                    <span className="badge bg-secondary-subtle text-secondary mb-2">Reference 2</span>
                                                                    <div className="fw-bold text-dark fs-14">{secG?.ref_2_name || '—'}</div>
                                                                    <div className="text-muted small mt-1">Relationship: <strong className="text-dark">{secG?.ref_2_relationship || '—'}</strong></div>
                                                                    <div className="text-muted small">Mobile: <strong className="text-dark">{secG?.ref_2_mobile || '—'}</strong></div>
                                                                    <div className="text-muted small">Years Known: <strong className="text-dark">{secG?.ref_2_years || '—'}</strong></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Section H: Documents */}
                                                    <div className="border rounded-3 p-3 bg-white">
                                                        <div className="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
                                                            <span className="badge bg-dark text-white font-monospace">Section H</span>
                                                            <h6 className="fw-bold text-dark mb-0">Documents (Admin View)</h6>
                                                        </div>
                                                        <div className="row g-3">
                                                            <div className="col-12 col-md-4">
                                                                <div className="p-3 rounded bg-light border d-flex flex-column gap-2">
                                                                    <span className="fw-semibold text-dark small">Identity Proof (Masked)</span>
                                                                    {secH?.id_proof_url ? (
                                                                        <a href={secH.id_proof_url} target="_blank" rel="noopener noreferrer" className="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                                                            <ExternalLink size={13} /> View Identity Document
                                                                        </a>
                                                                    ) : (
                                                                        <span className="text-muted small fst-italic">Not uploaded</span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <div className="p-3 rounded bg-light border d-flex flex-column gap-2">
                                                                    <span className="fw-semibold text-dark small">Business Registration Proof</span>
                                                                    {secH?.business_proof_url || secA?.registration_doc_url ? (
                                                                        <a href={secH?.business_proof_url || secA?.registration_doc_url} target="_blank" rel="noopener noreferrer" className="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                                                            <ExternalLink size={13} /> View Business Proof
                                                                        </a>
                                                                    ) : (
                                                                        <span className="text-muted small fst-italic">Not uploaded</span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <div className="p-3 rounded bg-light border d-flex flex-column gap-2">
                                                                    <span className="fw-semibold text-dark small">Company Profile / Pitch Deck</span>
                                                                    {secH?.deck_url ? (
                                                                        <a href={secH.deck_url} target="_blank" rel="noopener noreferrer" className="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                                                            <ExternalLink size={13} /> View Company Deck
                                                                        </a>
                                                                    ) : (
                                                                        <span className="text-muted small fst-italic">Not provided</span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })()}
                                    </>
                                )}
                            </div>

                            {/* Consequential Admin Actions Toolbar */}
                            <div className="modal-footer border-top bg-light d-flex justify-content-between">
                                <div className="d-flex gap-2">
                                    <button
                                        onClick={() => setActionModal({ type: 'correction', nomination: selectedNomination })}
                                        className="btn btn-outline-warning btn-sm rounded-3 px-3 d-flex align-items-center gap-1.5"
                                    >
                                        <RotateCcw size={14} /> Request Correction
                                    </button>
                                    <button
                                        onClick={() => setActionModal({ type: 'reject', nomination: selectedNomination })}
                                        className="btn btn-outline-danger btn-sm rounded-3 px-3 d-flex align-items-center gap-1.5"
                                    >
                                        <XCircle size={14} /> Reject Candidate
                                    </button>
                                </div>

                                <div className="d-flex gap-2">
                                    {['approved', 'shortlisted'].includes(selectedNomination.status) && (
                                        <button
                                            onClick={() => handleSendManualEmail(selectedNomination)}
                                            disabled={sendingEmailId === selectedNomination.id}
                                            className="btn btn-outline-success btn-sm rounded-3 px-3 d-flex align-items-center gap-1.5 fw-semibold"
                                            title="Send or resend email notification to candidate"
                                        >
                                            {sendingEmailId === selectedNomination.id ? (
                                                <>
                                                    <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                    Sending...
                                                </>
                                            ) : (
                                                <>
                                                    <Mail size={14} /> Resend Email
                                                </>
                                            )}
                                        </button>
                                    )}
                                    <button
                                        onClick={() => setActionModal({ type: 'approve', nomination: selectedNomination })}
                                        className="btn btn-success btn-sm rounded-3 px-3 d-flex align-items-center gap-1.5 fw-semibold"
                                    >
                                        <UserCheck size={14} /> Approve Candidate
                                    </button>
                                    <button
                                        onClick={() => setActionModal({ type: 'shortlist', nomination: selectedNomination })}
                                        className="btn btn-primary btn-sm rounded-3 px-3 d-flex align-items-center gap-1.5 fw-semibold"
                                    >
                                        <Award size={14} /> Shortlist for Voting
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Action Confirmation / Reason Dialog */}
            {actionModal && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1065 }}>
                    <div className="modal-dialog modal-dialog-centered">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <div className="modal-header border-bottom-0 pb-0">
                                <h5 className="modal-title fw-bold text-dark">
                                    {actionModal.type === 'approve'
                                        ? 'Approve Candidate Nomination'
                                        : actionModal.type === 'shortlist'
                                        ? 'Shortlist Candidate for Voting'
                                        : actionModal.type === 'reject'
                                        ? 'Reject Candidate Nomination'
                                        : 'Request Nomination Corrections'}
                                </h5>
                                <button type="button" className="btn-close" onClick={() => setActionModal(null)}></button>
                            </div>
                            <div className="modal-body py-3">
                                <p className="text-secondary small mb-3">
                                    Candidate: <strong>{actionModal.nomination.candidate_name}</strong> (
                                    {actionModal.nomination.application_number})
                                </p>

                                {(actionModal.type === 'reject' || actionModal.type === 'correction') && (
                                    <div className="mb-2">
                                        <label className="form-label small fw-semibold text-dark">
                                            {actionModal.type === 'reject' ? 'Mandatory Rejection Reason *' : 'Mandatory Correction Notes *'}
                                        </label>
                                        <textarea
                                            className="form-control"
                                            rows={3}
                                            placeholder={
                                                actionModal.type === 'reject'
                                                    ? 'Provide detailed explanation for disqualification...'
                                                    : 'Specify exactly which documents or answers require changes...'
                                            }
                                            value={actionModal.reason || ''}
                                            onChange={(e) => setActionModal({ ...actionModal, reason: e.target.value })}
                                            required
                                        />
                                    </div>
                                )}

                                {actionModal.type === 'approve' && (
                                    <div className="mb-2">
                                        <label className="form-label small fw-semibold text-dark">Optional Remarks</label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            placeholder="Approval remarks..."
                                            value={actionModal.reason || ''}
                                            onChange={(e) => setActionModal({ ...actionModal, reason: e.target.value })}
                                        />
                                    </div>
                                )}
                            </div>
                            <div className="modal-footer border-top-0 pt-0">
                                <button type="button" className="btn btn-light" onClick={() => setActionModal(null)}>
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    className={`btn ${actionModal.type === 'reject' ? 'btn-danger' : 'btn-primary'} px-4 fw-semibold`}
                                    disabled={isActionLoading}
                                    onClick={handleActionSubmit}
                                >
                                    {isActionLoading ? 'Saving...' : 'Confirm Action'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Proceed to Jury Confirmation Modal */}
            {proceedJuryModal.open && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1065 }}>
                    <div className="modal-dialog modal-dialog-centered modal-lg">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <div className="modal-header border-bottom">
                                <div className="d-flex align-items-center gap-2">
                                    <div className="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                                        <Award size={20} />
                                    </div>
                                    <div>
                                        <h5 className="modal-title fw-bold text-dark mb-0">Proceed to Jury Stage</h5>
                                        <p className="text-muted small mb-0">Dispatch Form 2 (Jury Evaluation Data) link to nominee(s)</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => setProceedJuryModal({ open: false, nominations: [], remarks: '', isLoading: false })}
                                ></button>
                            </div>
                            <div className="modal-body py-3">
                                <div className="alert alert-info border-0 rounded-3 mb-3 d-flex align-items-start gap-2">
                                    <CheckCircle2 size={18} className="text-info flex-shrink-0 mt-0.5" />
                                    <div className="small">
                                        Advancing to the Jury stage will mark the candidate(s) as <strong>Shortlisted</strong> and automatically dispatch the exclusive <strong>Form 2 (Jury Evaluation Data Form)</strong> registration link to them via WhatsApp and Email.
                                    </div>
                                </div>

                                <h6 className="fw-bold text-dark mb-2">Selected Nominees ({proceedJuryModal.nominations.length}):</h6>
                                <div className="list-group mb-3 border rounded-3 overflow-hidden">
                                    {proceedJuryModal.nominations.map((n) => (
                                        <div key={n.id} className="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                            <div>
                                                <div className="fw-semibold text-dark">{n.candidate_name}</div>
                                                <div className="text-muted small">
                                                    {n.application_number} • {n.applied_role_name || n.campaign?.role?.name || 'Leadership Role'} • {n.email || 'No email'}
                                                </div>
                                            </div>
                                            <div className="d-flex align-items-center gap-1.5">
                                                <button
                                                    onClick={() => handleCopyForm2Link(n)}
                                                    className="btn btn-outline-secondary btn-sm py-1 px-2 text-nowrap d-inline-flex align-items-center gap-1"
                                                    title="Copy Form 2 link to clipboard"
                                                >
                                                    {copiedForm2Id === n.id ? <Check size={13} className="text-success" /> : <Copy size={13} />}
                                                    {copiedForm2Id === n.id ? 'Copied!' : 'Copy Form 2 Link'}
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div className="mb-2">
                                    <label className="form-label small fw-semibold text-dark">Administrative Remarks (Optional)</label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="E.g., Shortlisted by Governance Committee for interview..."
                                        value={proceedJuryModal.remarks}
                                        onChange={(e) => setProceedJuryModal({ ...proceedJuryModal, remarks: e.target.value })}
                                    />
                                </div>
                            </div>
                            <div className="modal-footer border-top bg-light">
                                <button
                                    type="button"
                                    className="btn btn-light"
                                    onClick={() => setProceedJuryModal({ open: false, nominations: [], remarks: '', isLoading: false })}
                                    disabled={proceedJuryModal.isLoading}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-primary px-4 fw-semibold d-inline-flex align-items-center gap-2"
                                    disabled={proceedJuryModal.isLoading}
                                    onClick={handleConfirmProceedJury}
                                >
                                    {proceedJuryModal.isLoading ? (
                                        <>
                                            <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            Dispatching...
                                        </>
                                    ) : (
                                        <>
                                            <Award size={15} /> Confirm & Dispatch Form 2
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
