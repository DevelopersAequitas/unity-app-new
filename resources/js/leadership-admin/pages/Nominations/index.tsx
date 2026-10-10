import React, { useEffect, useState } from 'react';
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
} from 'lucide-react';

export const NominationsPage: React.FC = () => {
    const [nominations, setNominations] = useState<Nomination[]>([]);
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [search, setSearch] = useState<string>('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [campaignFilter, setCampaignFilter] = useState<string>('');
    const [error, setError] = useState<string | null>(null);

    // Shareable Voting Link & Manual Email States
    const [copiedId, setCopiedId] = useState<string | null>(null);
    const [sendingEmailId, setSendingEmailId] = useState<string | null>(null);
    const [emailSuccessMsg, setEmailSuccessMsg] = useState<string | null>(null);

    const getNominationVotingLink = (nom: Nomination) => {
        return nom.voting_link || `https://peersglobal.com/leadership/campaigns/${nom.campaign_id}/vote?candidate=${nom.id}`;
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

    // Detail Modal State (6 Tabs)
    const [selectedNomination, setSelectedNomination] = useState<Nomination | null>(null);
    const [activeTab, setActiveTab] = useState<'profile' | 'answers' | 'diff' | 'documents' | 'declarations' | 'history'>('profile');
    const [isLoadingDetail, setIsLoadingDetail] = useState<boolean>(false);

    // Consequential Action Modals
    const [actionModal, setActionModal] = useState<{
        type: 'approve' | 'reject' | 'correction' | 'shortlist';
        nomination: Nomination;
        reason?: string;
    } | null>(null);
    const [isActionLoading, setIsActionLoading] = useState<boolean>(false);

    const loadNominations = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const params: any = {};
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            if (campaignFilter) params.campaign_id = campaignFilter;

            const [nomRes, campRes] = await Promise.all([
                nominationApi.getAll(params),
                campaignApi.getAll(),
            ]);
            setNominations(nomRes.data.data || []);
            setCampaigns(campRes.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load nominations from server.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadNominations();
    }, [statusFilter, campaignFilter]);

    const openDetailModal = async (nom: Nomination) => {
        setIsLoadingDetail(true);
        setActiveTab('profile');
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
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Nomination Management</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Review candidate profiles, dynamic form answers, document verifications, and shortlisting
                    </p>
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
                    <div className="col-12 col-md-4">
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

                    <div className="col-12 col-md-3">
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
                        <button onClick={loadNominations} className="btn btn-light border btn-sm flex-grow-1 fw-semibold">
                            Filter
                        </button>
                    </div>
                </div>
            </div>

            {/* Nominations Table */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
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
                                    <td colSpan={7} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Loading nominations...
                                    </td>
                                </tr>
                            ) : nominations.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-5 text-muted">
                                        No nominations found matching the criteria.
                                    </td>
                                </tr>
                            ) : (
                                nominations.map((nom) => (
                                    <tr key={nom.id}>
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
                                            <StatusBadge status={nom.status} />
                                        </td>
                                        <td className="text-end">
                                            <div className="d-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
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
                                                    </>
                                                )}
                                                <button
                                                    onClick={() => openDetailModal(nom)}
                                                    className="btn btn-outline-primary btn-sm rounded-2 py-1 px-2.5 fw-semibold"
                                                >
                                                    Review
                                                </button>
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
        </div>
    );
};
