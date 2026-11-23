import { LeadershipLayout } from '../../components/LeadershipLayout';
import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { ROUTES } from '../../../../constants/routes';
import { nominationApi, campaignApi, votingApi, juryApi } from '../../api/services';
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
    User,
    Paperclip,
    MapPin,
    Video,
    Briefcase,
    Building2,
    Target,
    Eye,
    Trash2,
    Globe,
    Sparkles,
    AlertTriangle,
    ShieldCheck,
    Quote,
    Compass,
    ThumbsUp,
    Star,
    RefreshCw,
} from 'lucide-react';

const AUTHORIZED_ROLES = [
    { key: 'all', label: 'All Roles', value: '' },
    { key: 'ded', label: 'DED', value: 'ded', fullName: 'District Executive Director' },
    { key: 'eed', label: 'EED', value: 'eed', fullName: 'Ecosystem Executive Director' },
    { key: 'industry_director', label: 'Industry Director', value: 'industry_director', fullName: 'Industry Director' },
    { key: 'circle_director', label: 'Circle Director', value: 'circle_director', fullName: 'Circle Director' },
    { key: 'chair', label: 'Circle Chair', value: 'chair', fullName: 'Circle Chair' },
    { key: 'vice_chair', label: 'Vice Chair', value: 'vice_chair', fullName: 'Circle Vice Chair' },
    { key: 'circle_leader', label: 'Circle Leader', value: 'circle_leader', fullName: 'Circle Leader' },
    { key: 'circle_founder', label: 'Circle Founder', value: 'circle_founder', fullName: 'Circle Founder' },
    { key: 'secretary', label: 'Secretary', value: 'secretary', fullName: 'Circle Secretary' },
    { key: 'committee_leader', label: 'Committee Leader', value: 'committee_leader', fullName: 'Committee Leader' },
];

const getAppliedRole = (nom: Nomination): string => {
    return (
        nom.profile_snapshot?.applied_role_name ||
        nom.applied_role_name ||
        nom.profile_snapshot?.applied_role ||
        nom.campaign?.role?.name ||
        'Leadership Role'
    );
};

export const toText = (val: any, fallback = ''): string => {
    if (val === null || val === undefined) return fallback;
    if (typeof val === 'string') return val;
    if (typeof val === 'number' || typeof val === 'boolean') return String(val);
    if (typeof val === 'object') {
        if ('value' in val) return toText((val as any).value, fallback);
        if ('name' in val) return toText((val as any).name, fallback);
        if ('title' in val) return toText((val as any).title, fallback);
        if ('label' in val) return toText((val as any).label, fallback);
        if (Array.isArray(val)) {
            const joined = val.map((x) => toText(x)).filter(Boolean).join(', ');
            return joined || fallback;
        }
        try {
            return JSON.stringify(val);
        } catch {
            return fallback;
        }
    }
    return String(val);
};

const getBelonging = (nom: Nomination): { type: string; name: string } => {
    const snap = nom.profile_snapshot || {};
    const rawName = snap.belonging_name || snap.city || nom.scope_name || (nom as any).scope?.name || 'National';
    const rawType = snap.belonging_type || (snap.city ? 'City' : 'Jurisdiction');
    const name = toText(rawName, 'National');
    const typeStr = toText(rawType, 'Jurisdiction');
    const type = typeStr.charAt(0).toUpperCase() + typeStr.slice(1);
    return { type, name };
};

const getExperience = (nom: Nomination): string => {
    const snap = nom.profile_snapshot || {};
    const rawSince = toText(snap.since || (nom as any).answers?.since);
    if (rawSince) {
        const sinceYear = parseInt(rawSince.trim(), 10);
        if (!isNaN(sinceYear) && sinceYear > 1990 && sinceYear <= new Date().getFullYear()) {
            const tenure = Math.max(1, new Date().getFullYear() - sinceYear);
            return `Joined ${sinceYear} · ${tenure} yr${tenure > 1 ? 's' : ''}`;
        }
        if (!isNaN(sinceYear) && sinceYear > 0 && sinceYear < 100) {
            return `${sinceYear} Yrs Exp`;
        }
        return `${rawSince} Exp`;
    }
    return 'New Peer';
};

const slugify = (text: string): string => {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'nominee';
};

const isRoleMatch = (nom: Nomination, filter: string): boolean => {
    if (!filter) return true;
    const f = filter.toLowerCase().trim();
    const snap = nom.profile_snapshot || {};
    const appliedKey = String(snap.applied_role || '').toLowerCase();
    const appliedName = String(snap.applied_role_name || '').toLowerCase();
    const nomRole = String(nom.applied_role_name || '').toLowerCase();
    const campRole = String(nom.campaign?.role?.name || '').toLowerCase();
    const campKey = String(nom.campaign?.role?.key || '').toLowerCase();

    return (
        appliedKey === f ||
        appliedKey.includes(f) ||
        appliedName.includes(f) ||
        nomRole.includes(f) ||
        campRole.includes(f) ||
        campKey === f
    );
};

const isCityMatch = (nom: Nomination, filter: string): boolean => {
    if (!filter) return true;
    const f = filter.toLowerCase().trim();
    const snap = nom.profile_snapshot || {};
    const city = String(snap.city || '').toLowerCase();
    const belonging = String(snap.belonging_name || '').toLowerCase();
    const scope = String(nom.scope_name || (nom as any).scope?.name || '').toLowerCase();

    return city.includes(f) || belonging.includes(f) || scope.includes(f);
};

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
    const [cityFilter, setCityFilter] = useState<string>('');
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

    // Delete Modal State (Single, Selected, or All)
    const [deleteModal, setDeleteModal] = useState<{
        open: boolean;
        type: 'single' | 'selected' | 'all';
        nomination?: Nomination;
        count?: number;
        isLoading: boolean;
    }>({ open: false, type: 'selected', isLoading: false });

    const getNominationVotingLink = (nom?: Nomination | null) => {
        if (!nom) return '';
        if (nom.voting_link && nom.voting_link.includes('/nomination/')) {
            return nom.voting_link;
        }
        const candidateName = nom.candidate_name && nom.candidate_name.toLowerCase() !== 'candidate'
            ? nom.candidate_name
            : (nom.profile_snapshot?.full_name || nom.user?.name || (nom as any).full_name || 'nominee');
        const slug = slugify(candidateName);
        return `https://peersglobal.com/leadership/nomination/${slug}`;
    };

    const getNominationForm2Link = (nom?: Nomination | null) => {
        if (!nom) return '';
        if (nom.form_2_link) {
            return nom.form_2_link.replace(/https?:\/\/peersunity\.com/g, 'https://peersglobal.com');
        }
        return `https://peersglobal.com/leadership/form-2/${nom.id}`;
    };

    const handleCopyVotingLink = (nom?: Nomination | null) => {
        if (!nom) return;
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
        if (selectedNominationIds.length === displayedNominations.length && displayedNominations.length > 0) {
            setSelectedNominationIds([]);
        } else {
            setSelectedNominationIds(displayedNominations.map((n) => n.id));
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

    const handleOpenDeleteSingle = (nom: Nomination) => {
        setDeleteModal({
            open: true,
            type: 'single',
            nomination: nom,
            count: 1,
            isLoading: false,
        });
    };

    const handleOpenDeleteSelected = () => {
        if (selectedNominationIds.length === 0) return;
        setDeleteModal({
            open: true,
            type: 'selected',
            count: selectedNominationIds.length,
            isLoading: false,
        });
    };

    const handleOpenDeleteAll = () => {
        setDeleteModal({
            open: true,
            type: 'all',
            count: nominations.length,
            isLoading: false,
        });
    };

    const handleConfirmDelete = async () => {
        setDeleteModal((prev) => ({ ...prev, isLoading: true }));
        setError(null);
        try {
            if (deleteModal.type === 'single') {
                const targetId = deleteModal.nomination?.id;
                if (!targetId) return;
                await nominationApi.delete(targetId);
                setNominations((prev) => prev.filter((n) => n.id !== targetId));
                setSelectedNominationIds((prev) => prev.filter((id) => id !== targetId));
                setEmailSuccessMsg(`Successfully deleted nomination ${deleteModal.nomination?.application_number || ''}.`);
            } else if (deleteModal.type === 'selected') {
                const idsToDelete = [...selectedNominationIds];
                const res = await nominationApi.bulkDelete({ ids: idsToDelete });
                setNominations((prev) => prev.filter((n) => !idsToDelete.includes(n.id)));
                setSelectedNominationIds([]);
                setEmailSuccessMsg(res.data?.message || `Successfully deleted ${idsToDelete.length} nomination request(s).`);
            } else if (deleteModal.type === 'all') {
                const res = await nominationApi.bulkDelete({
                    delete_all: true,
                    form_type: activeForm,
                });
                setNominations([]);
                setSelectedNominationIds([]);
                setEmailSuccessMsg(res.data?.message || 'Successfully deleted all nomination requests.');
            }

            setTimeout(() => setEmailSuccessMsg(null), 6000);
            setDeleteModal({ open: false, type: 'selected', isLoading: false });
            loadNominations();
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to delete nomination(s).');
            setDeleteModal((prev) => ({ ...prev, isLoading: false }));
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

    const handleSendManualEmail = async (nom?: Nomination | null) => {
        if (!nom) return;
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

    // Detail Modal State (Tabs + Votes & Jury Review)
    const [selectedNomination, setSelectedNomination] = useState<Nomination | null>(null);
    const [activeTab, setActiveTab] = useState<'profile' | 'votes' | 'jury' | 'answers' | 'diff' | 'documents' | 'declarations' | 'history' | 'form_2'>('profile');
    const [isLoadingDetail, setIsLoadingDetail] = useState<boolean>(false);
    const [candidateVotes, setCandidateVotes] = useState<{ data: any[]; total: number } | null>(null);
    const [isLoadingVotes, setIsLoadingVotes] = useState<boolean>(false);

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
            if (cityFilter) params.city = cityFilter;
            params.form_type = activeForm;

            const nomRes = await nominationApi.getAll(params);
            const payloadData = nomRes.data?.data;
            const list = Array.isArray(payloadData)
                ? payloadData
                : Array.isArray((payloadData as any)?.data)
                    ? (payloadData as any).data
                    : Array.isArray(nomRes.data)
                        ? nomRes.data
                        : [];
            setNominations(list);
        } catch (err: any) {
            console.error('Failed to load nominations:', err);
            setError(err?.response?.data?.message || err?.message || 'Failed to load nominations from server.');
            setNominations([]);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadNominations();
    }, [statusFilter, campaignFilter, roleFilter, cityFilter, activeForm]);

    // Client-side instant filtering across roles & cities / belongings
    const displayedNominations = React.useMemo(() => {
        return nominations.filter((nom) => {
            if (roleFilter && !isRoleMatch(nom, roleFilter)) return false;
            if (cityFilter && !isCityMatch(nom, cityFilter)) return false;
            return true;
        });
    }, [nominations, roleFilter, cityFilter]);

    const openDetailModal = async (nom: Nomination) => {
        setIsLoadingDetail(true);
        setIsLoadingVotes(true);
        setCandidateVotes(null);
        const isF2 = nom.form_type === 'sub_form_2' || !!nom.form_2_data || activeForm === 'sub_form_2';
        setActiveTab(isF2 ? 'form_2' : 'profile');
        try {
            const res = await nominationApi.getById(nom.id);
            const detailedNom = res.data.data;
            setSelectedNomination(detailedNom);
            if (detailedNom?.detailed_votes && detailedNom.detailed_votes.length > 0) {
                setCandidateVotes({
                    data: detailedNom.detailed_votes,
                    total: detailedNom.votes_count ?? detailedNom.detailed_votes.length,
                });
            }
        } catch {
            setSelectedNomination(nom);
        } finally {
            setIsLoadingDetail(false);
        }

        // Fetch live detailed votes ledger
        try {
            const voteRes = await votingApi.getDetailedVotes(undefined, { nomination_id: nom.id, per_page: 100 });
            const vData = voteRes.data?.data;
            if (vData) {
                const list = Array.isArray(vData) ? vData : (vData as any).data || [];
                const total = (voteRes.data as any).meta?.total ?? (vData as any).total ?? list.length;
                setCandidateVotes({ data: list, total });
            }
        } catch (e) {
            console.error('Failed to load candidate votes', e);
        } finally {
            setIsLoadingVotes(false);
        }
    };

    const refreshCandidateVotes = async (nomId?: string) => {
        const targetId = nomId || selectedNomination?.id;
        if (!targetId) return;
        setIsLoadingVotes(true);
        try {
            const voteRes = await votingApi.getDetailedVotes(undefined, { nomination_id: targetId, per_page: 100 });
            const vData = voteRes.data?.data;
            if (vData) {
                const list = Array.isArray(vData) ? vData : (vData as any).data || [];
                const total = (voteRes.data as any).meta?.total ?? (vData as any).total ?? list.length;
                setCandidateVotes({ data: list, total });
            }
        } catch (e) {
            console.error('Failed to refresh candidate votes', e);
        } finally {
            setIsLoadingVotes(false);
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
        <LeadershipLayout>
            <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div className="d-flex align-items-center gap-2">
                    <button
                        type="button"
                        onClick={loadNominations}
                        disabled={isLoading}
                        className="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold"
                        title="Refresh nominations from server"
                    >
                        <RefreshCw size={14} className={isLoading ? 'spinner-border spinner-border-sm' : ''} />
                        <span>{isLoading ? 'Refreshing...' : 'Refresh Nominations'}</span>
                    </button>
                    <span className="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill small fw-semibold">
                        {displayedNominations.length} Active {activeForm === 'sub_form_2' ? 'Sub Form 2' : 'Nominations'}
                    </span>
                </div>

                {/* Submenu form switcher tabs */}
                <div className="d-flex align-items-center bg-light p-1 rounded-3 border">
                    <button
                        type="button"
                        onClick={() => navigate(`${ROUTES.WEB_LEADERSHIP_NOMINATIONS}?form=main_form_1`)}
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
                        onClick={() => navigate(`${ROUTES.WEB_LEADERSHIP_NOMINATIONS}?form=sub_form_2`)}
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
                    <div className="col-12 col-md-4">
                        <div className="input-group input-group-sm">
                            <span className="input-group-text bg-white border-end-0">
                                <Search size={14} className="text-muted" />
                            </span>
                            <input
                                type="text"
                                className="form-control border-start-0"
                                placeholder="Search candidate, email, mobile, or app #..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && loadNominations()}
                            />
                        </div>
                    </div>

                    <div className="col-12 col-md-3">
                        <select
                            className="form-select form-select-sm"
                            value={roleFilter}
                            onChange={(e) => setRoleFilter(e.target.value)}
                        >
                            {AUTHORIZED_ROLES.map((r) => (
                                <option key={r.key} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="col-12 col-md-2">
                        <div className="input-group input-group-sm">
                            <span className="input-group-text bg-white border-end-0">
                                <MapPin size={13} className="text-muted" />
                            </span>
                            <input
                                type="text"
                                className="form-control border-start-0"
                                placeholder="Filter by City / District..."
                                value={cityFilter}
                                onChange={(e) => setCityFilter(e.target.value)}
                            />
                        </div>
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

                    <div className="col-12 col-md-1 d-flex gap-1">
                        <button onClick={loadNominations} className="btn btn-primary btn-sm flex-grow-1 fw-semibold shadow-xs" title="Filter / Search">
                            <Search size={13} />
                        </button>
                        <button onClick={loadNominations} disabled={isLoading} className="btn btn-light border btn-sm shadow-2xs" title="Refresh list">
                            <RefreshCw size={13} className={isLoading ? 'spinner-border spinner-border-sm' : ''} />
                        </button>
                        {(search || statusFilter || campaignFilter || roleFilter || cityFilter) && (
                            <button
                                onClick={() => {
                                    setSearch('');
                                    setStatusFilter('');
                                    setCampaignFilter('');
                                    setRoleFilter('');
                                    setCityFilter('');
                                }}
                                className="btn btn-outline-secondary btn-sm"
                                title="Reset all filters"
                            >
                                <RotateCcw size={13} />
                            </button>
                        )}
                    </div>
                </div>

                {/* Role Quick Filter Pills (The 10 Authorized Roles) */}
                <div className="d-flex align-items-center gap-1.5 flex-wrap pt-3 mt-3 border-top">
                    <span className="text-muted small fw-semibold d-flex align-items-center gap-1 me-2">
                        <Filter size={13} className="text-primary" />
                        Role Filter:
                    </span>
                    {AUTHORIZED_ROLES.map((r) => {
                        const isSelected = r.value === '' 
                            ? roleFilter === '' 
                            : roleFilter.toLowerCase() === r.value.toLowerCase() || 
                              roleFilter.toLowerCase() === r.label.toLowerCase() ||
                              roleFilter.toLowerCase() === (r.fullName || '').toLowerCase();
                        return (
                            <button
                                key={r.key}
                                type="button"
                                onClick={() => setRoleFilter(isSelected && r.value !== '' ? '' : r.value)}
                                className={`btn btn-sm rounded-pill px-3 py-1 fs-12 fw-medium transition-all ${
                                    isSelected
                                        ? 'btn-primary text-white shadow-xs'
                                        : 'btn-outline-secondary bg-white text-dark'
                                }`}
                            >
                                {r.label}
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
                            onClick={handleOpenDeleteSelected}
                            className="btn btn-danger btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                            title="Permanently delete selected nominations"
                        >
                            <Trash2 size={14} /> Delete Selected ({selectedNominationIds.length})
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
            <div className="card border-0 shadow-xs rounded-4 bg-white overflow-hidden">
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0" style={{ minWidth: '940px' }}>
                        <thead className="table-light border-bottom">
                            <tr className="text-secondary small">
                                <th style={{ width: '40px' }} className="text-center py-3">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={displayedNominations.length > 0 && selectedNominationIds.length === displayedNominations.length}
                                        onChange={toggleSelectAll}
                                        title="Select all nominees"
                                    />
                                </th>
                                <th style={{ width: '130px' }} className="py-3">APP #</th>
                                <th style={{ minWidth: '200px' }} className="py-3">CANDIDATE</th>
                                <th style={{ minWidth: '160px' }} className="py-3">APPLIED ROLE</th>
                                <th style={{ minWidth: '180px' }} className="py-3">BELONGING / JURISDICTION</th>
                                <th style={{ width: '130px' }} className="py-3">EXPERIENCE</th>
                                <th style={{ width: '115px' }} className="py-3">SUBMITTED AT</th>
                                <th style={{ width: '130px' }} className="py-3">STATUS</th>
                                <th style={{ width: '220px' }} className="text-end py-3 pe-3">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                <tr>
                                    <td colSpan={9} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Loading nominations...
                                    </td>
                                </tr>
                            ) : displayedNominations.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-center py-5 text-muted">
                                        No nominations found matching the criteria.
                                    </td>
                                </tr>
                            ) : (
                                displayedNominations.map((nom) => {
                                    const snap = nom.profile_snapshot || {};
                                    const appliedRole = getAppliedRole(nom);
                                    const belonging = getBelonging(nom);
                                    const experience = getExperience(nom);
                                    const candidateName =
                                        nom.candidate_name && nom.candidate_name.toLowerCase() !== 'candidate'
                                            ? nom.candidate_name
                                            : (snap.full_name || nom.user?.name || 'Nominee');
                                    const photoUrl =
                                        nom.profile_photo_url ||
                                        nom.photo_url ||
                                        nom.avatar_url ||
                                        (nom as any).image_url ||
                                        snap.photo_url ||
                                        snap.profile_photo_url ||
                                        snap.avatar_url ||
                                        snap.image_url ||
                                        nom.user?.profile_photo_url ||
                                        nom.user?.avatar_url ||
                                        nom.creative_url ||
                                        null;
                                    const email = nom.email || snap.email;
                                    const mobile = nom.mobile || snap.mobile;

                                    return (
                                        <tr
                                            key={nom.id}
                                            onClick={(e) => {
                                                const target = e.target as HTMLElement;
                                                if (target.closest('input[type="checkbox"]') || target.closest('button') || target.closest('a')) {
                                                    return;
                                                }
                                                openDetailModal(nom);
                                            }}
                                            style={{ cursor: 'pointer' }}
                                            className="hover-bg-light transition-colors"
                                            title="Click to view full dossier, votes ledger, and jury evaluation"
                                        >
                                            <td className="text-center py-3">
                                                <input
                                                    type="checkbox"
                                                    className="form-check-input"
                                                    checked={selectedNominationIds.includes(nom.id)}
                                                    onChange={() => toggleSelectNomination(nom.id)}
                                                />
                                            </td>
                                            <td className="py-3">
                                                <span className="font-monospace small text-primary fw-semibold px-2 py-1 rounded bg-primary-subtle text-nowrap">
                                                    {nom.application_number}
                                                </span>
                                            </td>
                                            <td className="py-3">
                                                <div className="d-flex align-items-center gap-2">
                                                    {photoUrl ? (
                                                        <img
                                                            src={photoUrl}
                                                            alt={candidateName}
                                                            className="rounded-circle object-fit-cover border flex-shrink-0"
                                                            style={{ width: '34px', height: '34px' }}
                                                        />
                                                    ) : (
                                                        <div
                                                            className="rounded-circle bg-primary-subtle text-primary border border-primary-subtle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                                            style={{ width: '34px', height: '34px', fontSize: '0.8rem' }}
                                                        >
                                                            {candidateName.charAt(0).toUpperCase()}
                                                        </div>
                                                    )}
                                                    <div style={{ maxWidth: '180px' }}>
                                                        <div className="fw-semibold text-dark text-truncate" title={candidateName}>
                                                            {candidateName}
                                                        </div>
                                                        <div className="text-muted small text-truncate" title={email}>
                                                            {email}
                                                        </div>
                                                        {mobile && (
                                                            <div className="text-muted text-truncate" style={{ fontSize: '11px' }} title={mobile}>
                                                                {mobile}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <span
                                                    className="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 fw-semibold text-truncate d-inline-block"
                                                    style={{ maxWidth: '160px', fontSize: '0.75rem', borderRadius: '6px' }}
                                                    title={appliedRole}
                                                >
                                                    {appliedRole}
                                                </span>
                                            </td>
                                            <td className="py-3">
                                                <div style={{ maxWidth: '180px' }}>
                                                    <div className="fw-semibold text-dark text-truncate" title={belonging.name}>
                                                        {belonging.name}
                                                    </div>
                                                    <div className="small text-muted d-flex align-items-center gap-1 text-truncate">
                                                        <span className="badge bg-light text-secondary border px-1.5 py-0" style={{ fontSize: '9.5px' }}>
                                                            {belonging.type}
                                                        </span>
                                                        {snap.company_name && (
                                                            <span className="text-truncate text-secondary" title={snap.company_name}>
                                                                • {snap.company_name}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <span className="small fw-medium text-dark px-2 py-1 rounded bg-light border text-nowrap">
                                                    {experience}
                                                </span>
                                            </td>
                                            <td className="py-3 small text-muted text-nowrap">
                                                {nom.submitted_at ? new Date(nom.submitted_at).toLocaleDateString() : 'Draft'}
                                            </td>
                                            <td className="py-3">
                                                <div className="d-flex flex-column align-items-start gap-1">
                                                    <StatusBadge status={nom.status} />
                                                    {nom.jury_evaluation && nom.jury_evaluation.status === 'completed' && (
                                                        <span 
                                                            className="badge rounded-pill bg-success text-white px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-xs" 
                                                            style={{ fontSize: '0.68rem', fontWeight: 700 }} 
                                                            title={`Jury Evaluation Completed by ${nom.jury_evaluation.juror_name} (${nom.jury_evaluation.juror_email || ''})`}
                                                        >
                                                            <Check size={10} /> Jury Reviewed: {nom.jury_evaluation.juror_name}
                                                        </span>
                                                    )}
                                                    {nom.form_2_status === 'submitted' && (
                                                        <span className="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-0.5 d-inline-flex align-items-center gap-1" style={{ fontSize: '0.68rem', fontWeight: 600 }} title="Form 2 Submitted">
                                                            <Check size={10} /> F2 Done
                                                        </span>
                                                    )}
                                                    {nom.form_2_status === 'pending_submission' && (
                                                        <span className="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-0.5 d-inline-flex align-items-center gap-1" style={{ fontSize: '0.68rem', fontWeight: 600 }} title="Form 2 Link Sent">
                                                            <Clock size={10} /> F2 Sent
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                        <td className="text-end py-3 pe-3" onClick={(e) => e.stopPropagation()}>
                                            <div className="d-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                                {activeForm === 'sub_form_2' ? (
                                                    <div className="d-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                                        {nom.form_2_status === 'pending_submission' && (
                                                            <button
                                                                onClick={() => handleCopyForm2Link(nom)}
                                                                className="btn btn-light bg-white border text-secondary btn-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center shadow-xs"
                                                                title="Copy Form 2 Link to Clipboard"
                                                            >
                                                                {copiedForm2Id === nom.id ? <Check size={13} className="text-success" /> : <Copy size={13} />}
                                                            </button>
                                                        )}
                                                        <button
                                                            onClick={() => openDetailModal(nom)}
                                                            className="btn btn-primary btn-sm rounded-pill py-1 px-3 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                                                            title="Review full Form 2 submission"
                                                        >
                                                            <FileText size={13} />
                                                            <span className="fs-12">Review Form 2</span>
                                                        </button>
                                                        <button
                                                            onClick={() => handleOpenDeleteSingle(nom)}
                                                            className="btn btn-outline-danger btn-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center shadow-xs"
                                                            title="Delete Form 2 submission"
                                                        >
                                                            <Trash2 size={13} />
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <div className="d-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                                        {['submitted', 'resubmitted', 'under_review'].includes(nom.status) && (
                                                            <>
                                                                <button
                                                                    onClick={() => setActionModal({ type: 'approve', nomination: nom, reason: '' })}
                                                                    className="btn btn-success btn-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center shadow-xs"
                                                                    title="Approve Nomination Request"
                                                                >
                                                                    <Check size={13} />
                                                                </button>
                                                                <button
                                                                    onClick={() => setActionModal({ type: 'reject', nomination: nom, reason: '' })}
                                                                    className="btn btn-outline-danger btn-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center shadow-xs"
                                                                    title="Reject Nomination Request"
                                                                >
                                                                    <X size={13} />
                                                                </button>
                                                            </>
                                                        )}

                                                        {/* Primary View Details Button */}
                                                        <button
                                                            onClick={() => openDetailModal(nom)}
                                                            className="btn btn-primary btn-sm rounded-pill py-1 px-3 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                                                            title="Open Candidate Request (Profile, Votes Ledger, Jury Scorecard, Answers)"
                                                        >
                                                            <Eye size={13} />
                                                            <span className="fs-12">View Details</span>
                                                        </button>

                                                        {/* Quick Actions (Copy Voting Link, Mail, Proceed to Jury) */}
                                                        {['approved', 'shortlisted'].includes(nom.status) && (
                                                            <>
                                                                <button
                                                                    onClick={() => handleCopyVotingLink(nom)}
                                                                    className="btn btn-light bg-white border text-secondary btn-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center shadow-xs"
                                                                    title="Copy Candidate Voting Link"
                                                                >
                                                                    {copiedId === nom.id ? <Check size={13} className="text-success" /> : <Link2 size={13} />}
                                                                </button>
                                                                <button
                                                                    onClick={() => handleSendManualEmail(nom)}
                                                                    disabled={sendingEmailId === nom.id}
                                                                    className="btn btn-light bg-white border text-secondary btn-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center shadow-xs"
                                                                    title="Send Official Approval Email"
                                                                >
                                                                    {sendingEmailId === nom.id ? (
                                                                        <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" style={{ width: '12px', height: '12px' }}></span>
                                                                    ) : (
                                                                        <Mail size={13} className="text-muted" />
                                                                    )}
                                                                </button>
                                                                <button
                                                                    onClick={() => handleOpenProceedJuryModal([nom])}
                                                                    className="btn btn-light bg-white border text-primary btn-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center shadow-xs"
                                                                    title="Proceed Candidate to Jury Stage"
                                                                >
                                                                    <Award size={13} className="text-primary" />
                                                                </button>
                                                            </>
                                                        )}

                                                        <button
                                                            onClick={() => handleOpenDeleteSingle(nom)}
                                                            className="btn btn-outline-danger btn-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center shadow-xs"
                                                            title="Delete nomination request"
                                                        >
                                                            <Trash2 size={13} />
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            }))}
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
                                        {selectedNomination.jury_evaluation && (
                                            <span className="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style={{ fontSize: '0.72rem' }}>
                                                <Check size={11} /> Jury Reviewed: {selectedNomination.jury_evaluation.juror_name}
                                            </span>
                                        )}
                                        {((candidateVotes?.total ?? selectedNomination.votes_count ?? (selectedNomination.detailed_votes?.length || 0)) > 0) && (
                                            <span className="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5" style={{ fontSize: '0.72rem' }}>
                                                <ThumbsUp size={11} /> {candidateVotes?.total ?? selectedNomination.votes_count ?? (selectedNomination.detailed_votes?.length || 0)} Votes
                                            </span>
                                        )}
                                    </div>
                                    <div className="d-flex align-items-baseline gap-2">
                                        <h4 className="fw-bold text-dark mb-0">{selectedNomination.candidate_name}</h4>
                                        <span className="text-muted small">&bull; {toText(getAppliedRole(selectedNomination), 'Leader')}</span>
                                    </div>
                                </div>
                                <div className="d-flex align-items-center gap-2">
                                    {['approved', 'shortlisted'].includes(selectedNomination.status) && (
                                        <>
                                            <button
                                                type="button"
                                                onClick={() => handleCopyVotingLink(selectedNomination)}
                                                className={`btn btn-sm ${copiedId === selectedNomination.id ? 'btn-success' : 'btn-outline-primary'} fw-semibold d-inline-flex align-items-center gap-1.5 rounded-pill px-3 shadow-xs`}
                                                title="Copy candidate short voting link"
                                            >
                                                {copiedId === selectedNomination.id ? <Check size={13} /> : <Copy size={13} />}
                                                <span>{copiedId === selectedNomination.id ? 'Copied' : 'Copy Voting Link'}</span>
                                            </button>
                                            <a
                                                href={getNominationVotingLink(selectedNomination)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 rounded-pill px-2.5 shadow-xs"
                                                title="Open Public Voting Booth"
                                            >
                                                <ExternalLink size={13} />
                                                <span>Open Booth</span>
                                            </a>
                                        </>
                                    )}
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-light border-0 rounded-circle p-1.5 d-flex align-items-center justify-content-center text-muted ms-2"
                                        style={{
                                            background: '#f1f5f9',
                                            border: '1px solid #e2e8f0',
                                            cursor: 'pointer',
                                            padding: '6px',
                                            borderRadius: '50%',
                                            color: '#64748b',
                                        }}
                                        onClick={() => setSelectedNomination(null)}
                                        title="Close"
                                    >
                                        <X size={18} />
                                    </button>
                                </div>
                            </div>

                            {/* Modern Form Navigation Tabs */}
                            {(() => {
                                const nom = selectedNomination;
                                if (!nom) return null;
                                const isF2 = nom.form_type === 'sub_form_2' || !!nom.form_2_data || activeForm === 'sub_form_2';
                                const totalVotes = candidateVotes?.total ?? nom.votes_count ?? (nom.detailed_votes?.length || 0);
                                const hasJury = Boolean(nom.jury_evaluation || (nom.jury_assignments && nom.jury_assignments.length > 0));

                                const tabs = isF2
                                    ? [
                                        { key: 'form_2', label: 'Form 2 (Jury Data)', icon: Award, count: null },
                                        { key: 'profile', label: 'Candidate Profile', icon: User, count: null },
                                        { key: 'votes', label: 'Votes', icon: ThumbsUp, count: totalVotes > 0 ? totalVotes : null },
                                        { key: 'jury', label: 'Jury Evaluation', icon: Award, count: hasJury ? '✓' : null },
                                        { key: 'answers', label: 'Form Answers', icon: FileText, count: null },
                                        { key: 'documents', label: 'Documents', icon: Paperclip, count: nom.documents?.length || 0 },
                                        { key: 'diff', label: 'Profile Diffs', icon: ShieldAlert, count: null },
                                        { key: 'history', label: 'Activity History', icon: History, count: null },
                                    ]
                                    : [
                                        { key: 'profile', label: 'Candidate Profile', icon: User, count: null },
                                        { key: 'votes', label: 'Votes', icon: ThumbsUp, count: totalVotes > 0 ? totalVotes : null },
                                        { key: 'jury', label: 'Jury Evaluation', icon: Award, count: hasJury ? '✓' : null },
                                        { key: 'answers', label: 'Form Answers', icon: FileText, count: null },
                                        { key: 'diff', label: 'Profile Diffs', icon: ShieldAlert, count: null },
                                        { key: 'documents', label: 'Documents', icon: Paperclip, count: nom.documents?.length || 0 },
                                        { key: 'declarations', label: 'Declarations', icon: CheckCircle2, count: null },
                                        { key: 'history', label: 'Activity History', icon: History, count: null },
                                    ];

                                return (
                                    <div style={{ background: '#f8fafc', borderBottom: '1px solid #e2e8f0', padding: '10px 20px', display: 'flex', alignItems: 'center', gap: '8px', overflowX: 'auto' }}>
                                        {tabs.map((tab) => {
                                            const isActive = activeTab === tab.key;
                                            const Icon = tab.icon;
                                            return (
                                                <button
                                                    key={tab.key}
                                                    type="button"
                                                    onClick={() => setActiveTab(tab.key as any)}
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: '8px',
                                                        padding: '8px 16px',
                                                        borderRadius: '8px',
                                                        fontSize: '13px',
                                                        fontWeight: isActive ? 600 : 500,
                                                        color: isActive ? '#1d4ed8' : '#475569',
                                                        background: isActive ? '#eff6ff' : '#ffffff',
                                                        border: isActive ? '1px solid #bfdbfe' : '1px solid #e2e8f0',
                                                        boxShadow: isActive ? '0 1px 3px rgba(37, 99, 235, 0.12)' : 'none',
                                                        cursor: 'pointer',
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    <Icon size={15} style={{ color: isActive ? '#2563eb' : '#64748b' }} />
                                                    <span>{tab.label}</span>
                                                    {tab.count !== null && (
                                                        <span
                                                            style={{
                                                                display: 'inline-flex',
                                                                alignItems: 'center',
                                                                justifyContent: 'center',
                                                                padding: '1px 7px',
                                                                borderRadius: '9999px',
                                                                fontSize: '11px',
                                                                fontWeight: 700,
                                                                background: isActive ? '#dbeafe' : '#e2e8f0',
                                                                color: isActive ? '#1e40af' : '#475569',
                                                            }}
                                                        >
                                                            {tab.count}
                                                        </span>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                );
                            })()}

                            <div className="modal-body py-4">
                                {isLoadingDetail ? (
                                    <div className="text-center py-5">
                                        <div className="spinner-border text-primary"></div>
                                    </div>
                                ) : (
                                    <>
                                        {/* Tab 1: Profile (Become a Leader Candidate Dossier) */}
                                        {activeTab === 'profile' && selectedNomination && (() => {
                                            const snap = selectedNomination.profile_snapshot || {};
                                            const appliedRole = toText(getAppliedRole(selectedNomination), 'Leader');
                                            const belonging = getBelonging(selectedNomination);
                                            const experience = getExperience(selectedNomination);
                                            const candidateName = toText(
                                                selectedNomination.candidate_name && selectedNomination.candidate_name.toLowerCase() !== 'candidate'
                                                    ? selectedNomination.candidate_name
                                                    : (snap.full_name || selectedNomination.user?.name || 'Nominee'),
                                                'Nominee'
                                            );
                                            const photoUrl =
                                                selectedNomination.profile_photo_url ||
                                                selectedNomination.photo_url ||
                                                selectedNomination.avatar_url ||
                                                (selectedNomination as any).image_url ||
                                                snap.photo_url ||
                                                snap.profile_photo_url ||
                                                snap.avatar_url ||
                                                snap.image_url ||
                                                selectedNomination.user?.profile_photo_url ||
                                                selectedNomination.user?.avatar_url ||
                                                selectedNomination.creative_url ||
                                                null;
                                            const goals: string[] = (() => {
                                                const getAnswerValue = (keys: string[]) => {
                                                    if (!selectedNomination.answers) return undefined;
                                                    for (const key of keys) {
                                                        const found = selectedNomination.answers.find((a: any) => {
                                                            const qk = (a.question_key || a.question?.question_key || '').toLowerCase();
                                                            return qk === key.toLowerCase() || qk === `q_${key.toLowerCase()}` || qk.includes(key.toLowerCase());
                                                        });
                                                        if (found) {
                                                            const raw = found.answer?.value ?? found.answer ?? found.answer_text ?? found.answer_json;
                                                            if (raw !== undefined && raw !== null && raw !== '') {
                                                                return raw;
                                                            }
                                                        }
                                                    }
                                                    return undefined;
                                                };

                                                const parseList = (val: any): any[] => {
                                                    if (!val) return [];
                                                    while (val && typeof val === 'object' && !Array.isArray(val) && 'value' in val) {
                                                        val = (val as any).value;
                                                    }
                                                    if (!val) return [];
                                                    if (Array.isArray(val)) {
                                                        return val.map((x) => (x && typeof x === 'object' && 'value' in x ? (x as any).value : x));
                                                    }
                                                    if (typeof val === 'string') {
                                                        const trimmed = val.trim();
                                                        if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
                                                            try {
                                                                const parsed = JSON.parse(trimmed);
                                                                if (Array.isArray(parsed)) return parsed.map((x) => (x && typeof x === 'object' && 'value' in x ? (x as any).value : x));
                                                            } catch {
                                                                // ignore JSON error
                                                            }
                                                        }
                                                        if (trimmed.includes('\n')) return trimmed.split('\n').map((s) => s.trim()).filter(Boolean);
                                                        if (trimmed.includes(';')) return trimmed.split(';').map((s) => s.trim()).filter(Boolean);
                                                        if (trimmed.length > 0) return [trimmed];
                                                    }
                                                    if (typeof val === 'object') return Object.values(val);
                                                    return [];
                                                };

                                                let rawGoals = snap.goals ?? snap.top_3_goals ?? snap.plan_goals ?? getAnswerValue(['goals', 'top_3_goals', 'top_goals', 'plan_for_the_year']);
                                                let parsed = parseList(rawGoals);
                                                if (parsed.length === 0) {
                                                    const g1 = snap.goal_1 || snap.goal1 || getAnswerValue(['goal_1', 'goal1']);
                                                    const g2 = snap.goal_2 || snap.goal2 || getAnswerValue(['goal_2', 'goal2']);
                                                    const g3 = snap.goal_3 || snap.goal3 || getAnswerValue(['goal_3', 'goal3']);
                                                    if (g1 || g2 || g3) {
                                                        parsed = [g1 || '', g2 || '', g3 || ''].filter(Boolean);
                                                    }
                                                }
                                                return parsed.map((g: any) => toText(typeof g === 'object' && g !== null && 'value' in g ? (g as any).value : (g?.title || g?.goal || g?.text || g)));
                                            })();

                                            const pastRoles: any[] = (() => {
                                                const getAnswerValue = (keys: string[]) => {
                                                    if (!selectedNomination.answers) return undefined;
                                                    for (const key of keys) {
                                                        const found = selectedNomination.answers.find((a: any) => {
                                                            const qk = (a.question_key || a.question?.question_key || '').toLowerCase();
                                                            return qk === key.toLowerCase() || qk === `q_${key.toLowerCase()}` || qk.includes(key.toLowerCase());
                                                        });
                                                        if (found) {
                                                            const raw = found.answer?.value ?? found.answer ?? found.answer_text ?? found.answer_json;
                                                            if (raw !== undefined && raw !== null && raw !== '') {
                                                                return raw;
                                                            }
                                                        }
                                                    }
                                                    return undefined;
                                                };

                                                const parseList = (val: any): any[] => {
                                                    if (!val) return [];
                                                    while (val && typeof val === 'object' && !Array.isArray(val) && 'value' in val) {
                                                        val = (val as any).value;
                                                    }
                                                    if (!val) return [];
                                                    if (Array.isArray(val)) {
                                                        return val.map((x) => (x && typeof x === 'object' && 'value' in x ? (x as any).value : x));
                                                    }
                                                    if (typeof val === 'string') {
                                                        const trimmed = val.trim();
                                                        if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
                                                            try {
                                                                const parsed = JSON.parse(trimmed);
                                                                if (Array.isArray(parsed)) return parsed.map((x) => (x && typeof x === 'object' && 'value' in x ? (x as any).value : x));
                                                            } catch {
                                                                // ignore JSON error
                                                            }
                                                        }
                                                        if (trimmed.includes('\n')) return trimmed.split('\n').map((s) => s.trim()).filter(Boolean);
                                                        if (trimmed.includes(';')) return trimmed.split(';').map((s) => s.trim()).filter(Boolean);
                                                        if (trimmed.length > 0) return [trimmed];
                                                    }
                                                    if (typeof val === 'object') return Object.values(val);
                                                    return [];
                                                };

                                                let rawRoles = snap.leadership_roles ?? snap.past_roles ?? snap.experience_roles ?? getAnswerValue(['leadership_roles', 'past_leadership_experience', 'past_roles', 'experience']);
                                                let parsed = parseList(rawRoles);
                                                return parsed.map((r: any) => {
                                                    if (typeof r === 'string') {
                                                        return { role: r, org: '—', years: '—' };
                                                    }
                                                    return {
                                                        role: toText(r?.role ?? r?.title ?? r, '—'),
                                                        org: toText(r?.org ?? r?.organisation ?? r?.company, '—'),
                                                        years: toText(r?.years ?? r?.duration, '—'),
                                                    };
                                                });
                                            })();

                                            const timeCommitment = (() => {
                                                const rawCommitment = snap.time_commitment ?? snap.commitment ?? (() => {
                                                    if (!selectedNomination.answers) return undefined;
                                                    const found = selectedNomination.answers.find((a: any) => {
                                                        const qk = (a.question_key || a.question?.question_key || '').toLowerCase();
                                                        return qk.includes('time') || qk.includes('commitment');
                                                    });
                                                    return found?.answer?.value ?? found?.answer ?? found?.answer_text;
                                                })();
                                                const cleanCommitment = toText(rawCommitment);
                                                return cleanCommitment
                                                    ? (cleanCommitment.toLowerCase().includes('hr') || cleanCommitment.toLowerCase().includes('hour')
                                                        ? cleanCommitment
                                                        : `${cleanCommitment} hrs / month`)
                                                    : '10+ hrs / month';
                                            })();

                                            const videoUrl = (() => {
                                                const rawVideo = snap.video_url ?? snap.intro_video ?? snap.video ?? (() => {
                                                    if (!selectedNomination.answers) return undefined;
                                                    const found = selectedNomination.answers.find((a: any) => {
                                                        const qk = (a.question_key || a.question?.question_key || '').toLowerCase();
                                                        return qk.includes('video');
                                                    });
                                                    return found?.answer?.value ?? found?.answer ?? found?.answer_text;
                                                })();
                                                const cleanVideo = toText(rawVideo);
                                                return cleanVideo && cleanVideo.startsWith('http') ? cleanVideo : undefined;
                                            })();

                                            // Documents resolution across both selectedNomination.documents and profile snapshot
                                            const allNomDocs = selectedNomination.documents || [];
                                            const findDocByType = (types: string[]) => {
                                                return allNomDocs.find((d: any) => {
                                                    const dt = (d.document_type || d.type || '').toLowerCase();
                                                    return types.some((t) => dt.includes(t.toLowerCase()));
                                                });
                                            };

                                            const idDoc = findDocByType(['id', 'aadhaar', 'passport', 'identity']);
                                            const letterDoc = findDocByType(['letter', 'recommendation']);
                                            const deckDoc = findDocByType(['deck', 'company', 'profile', 'presentation']);
                                            const manifestoDoc = findDocByType(['manifesto']);

                                            const idFile = toText(idDoc?.file_name || (idDoc as any)?.original_filename || snap.id_file);
                                            const idUrl = idDoc?.file_url || (typeof snap.id_file === 'string' && snap.id_file.startsWith('http') ? snap.id_file : undefined);

                                            const letterFile = toText(letterDoc?.file_name || (letterDoc as any)?.original_filename || snap.letter_file);
                                            const letterUrl = letterDoc?.file_url || (typeof snap.letter_file === 'string' && snap.letter_file.startsWith('http') ? snap.letter_file : undefined);

                                            const deckFile = toText(deckDoc?.file_name || (deckDoc as any)?.original_filename || snap.deck_file);
                                            const deckUrl = deckDoc?.file_url || (typeof snap.deck_file === 'string' && snap.deck_file.startsWith('http') ? snap.deck_file : undefined);

                                            const manifestoFile = toText(manifestoDoc?.file_name || (manifestoDoc as any)?.original_filename || snap.manifesto_file);
                                            const manifestoUrl = manifestoDoc?.file_url || (typeof snap.manifesto_file === 'string' && snap.manifesto_file.startsWith('http') ? snap.manifesto_file : undefined);

                                            // New Schema Fields (Website Portal Ingestion)
                                            const linkedinUrl = snap.linkedin || (selectedNomination as any).linkedin;
                                            const websiteUrl = snap.website || snap.company_website || (selectedNomination as any).website;
                                            const companyName = toText(snap.company_name || (selectedNomination as any).company, '—');
                                            const designation = toText(snap.designation || (selectedNomination as any).designation, 'Leader');
                                            const industry = toText(snap.industry, '—');
                                            const cityName = toText(snap.city_name || snap.city || belonging.name, 'National');
                                            const leadershipStatement = snap.leadership_statement || snap.headline;
                                            const whyRole = snap.why_role || snap.motivation;
                                            const vision12Months = snap.vision_12_months || snap.twelve_month_plan;
                                            const hoursPerWeek = snap.hours_per_week || snap.time_commitment || timeCommitment;
                                            const hasOtherNetwork = Boolean(snap.other_network_involvement);
                                            const otherNetworkDetails = snap.other_network_details;

                                            // Signed Declarations
                                            const decs = snap.declarations || {};
                                            const decTruthful = Boolean(decs.dec_truthful_information ?? true);
                                            const decConduct = Boolean(decs.dec_code_of_conduct_no_incentives ?? true);
                                            const decPublicProfile = Boolean(decs.dec_public_profile_consent ?? true);
                                            const decJuryContact = Boolean(decs.dec_jury_contact_consent ?? true);

                                            // Profile Changes Audit Flag
                                            const profileChanges: string[] = (selectedNomination as any).profile_changes || snap.profile_changes || [];
                                            const hasProfileChanges = Array.isArray(profileChanges) && profileChanges.length > 0;

                                            return (
                                                <div className="d-flex flex-column gap-3">
                                                    {/* Profile Changes Audit Banner if candidate edited master profile */}
                                                    {hasProfileChanges && (
                                                        <div className="p-3 rounded-3 bg-warning-subtle border border-warning-subtle d-flex align-items-center justify-content-between gap-3 shadow-2xs">
                                                            <div className="d-flex align-items-center gap-2.5">
                                                                <AlertTriangle size={20} className="text-warning flex-shrink-0" />
                                                                <div>
                                                                    <div className="fw-bold text-dark small">Profile Modified from Master Directory</div>
                                                                    <div className="text-muted small mt-0.5">
                                                                        Candidate edited fields during nomination:
                                                                        {profileChanges.map((f, i) => (
                                                                            <span key={i} className="badge bg-warning text-dark font-monospace ms-1.5 px-2 py-0.5">
                                                                                {f}
                                                                            </span>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                className="btn btn-xs btn-outline-warning py-1 px-2.5 text-nowrap fw-semibold"
                                                                onClick={() => setActiveTab('diff')}
                                                            >
                                                                View Diffs
                                                            </button>
                                                        </div>
                                                    )}

                                                    {/* Jury Evaluation Banner if completed */}
                                                    {selectedNomination.jury_evaluation && selectedNomination.jury_evaluation.status === 'completed' && (
                                                        <div className="p-3.5 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 shadow-xs">
                                                            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
                                                                <div className="d-flex align-items-center gap-2 text-success fw-bold">
                                                                    <CheckCircle2 size={18} />
                                                                    <span>Jury Evaluation Completed</span>
                                                                </div>
                                                                <span className="badge bg-success text-white text-uppercase font-monospace px-2.5 py-1">
                                                                    {selectedNomination.jury_evaluation.overall_recommendation}
                                                                </span>
                                                            </div>
                                                            <div className="small text-dark mb-1">
                                                                <strong>Appointed Juror:</strong> {selectedNomination.jury_evaluation.juror_name} ({selectedNomination.jury_evaluation.juror_email || 'Juror'})
                                                            </div>
                                                            {selectedNomination.jury_evaluation.strengths && (
                                                                <div className="small text-muted mb-1">
                                                                    <strong className="text-dark">Key Strengths:</strong> {selectedNomination.jury_evaluation.strengths}
                                                                </div>
                                                            )}
                                                            {selectedNomination.jury_evaluation.concerns && (
                                                                <div className="small text-muted mb-1">
                                                                    <strong className="text-dark">Key Concerns:</strong> {selectedNomination.jury_evaluation.concerns}
                                                                </div>
                                                            )}
                                                            {selectedNomination.jury_evaluation.remarks && (
                                                                <div className="small text-muted">
                                                                    <strong className="text-dark">Final Remarks:</strong> {selectedNomination.jury_evaluation.remarks}
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}

                                                    {/* Voting Link Banner if Approved / Shortlisted */}
                                                    {['approved', 'shortlisted'].includes(selectedNomination.status) && (
                                                        <div style={{ background: '#ecfdf5', border: '1px solid #a7f3d0', borderRadius: '12px', padding: '16px' }}>
                                                            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                                                <div className="flex-grow-1">
                                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#065f46', fontWeight: 700, fontSize: '15px' }}>
                                                                        <CheckCircle2 size={18} />
                                                                        <span>Nomination Approved - Shareable Voting Link Ready</span>
                                                                    </div>
                                                                    <p style={{ color: '#047857', fontSize: '13px', margin: '4px 0 10px 0' }}>
                                                                        Direct voting link for voters to cast verified OTP votes for this candidate:
                                                                    </p>
                                                                    <div className="d-flex flex-wrap align-items-center gap-2">
                                                                        <div className="input-group input-group-sm" style={{ maxWidth: '490px' }}>
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
                                                                                {copiedId === selectedNomination.id ? 'Copied!' : 'Copy Voting Link'}
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

                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleOpenProceedJuryModal([selectedNomination])}
                                                                            className="btn btn-sm btn-outline-primary fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs"
                                                                            style={{ height: '31px', padding: '0 12px', borderRadius: '6px' }}
                                                                            title="Proceed this candidate to Jury Evaluation round"
                                                                        >
                                                                            <Award size={14} className="text-primary" />
                                                                            <span>Proceed to Jury</span>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div className="d-flex flex-column align-items-md-end gap-1 flex-shrink-0">
                                                                    <span style={{ color: '#065f46', fontSize: '12px', fontWeight: 600 }}>Need to notify candidate?</span>
                                                                    <button
                                                                        onClick={() => handleSendManualEmail(selectedNomination)}
                                                                        disabled={sendingEmailId === selectedNomination.id}
                                                                        style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', padding: '7px 14px', borderRadius: '8px', fontSize: '13px', fontWeight: 600, background: '#059669', color: '#ffffff', border: 'none', boxShadow: '0 1px 2px rgba(5, 150, 105, 0.25)', cursor: 'pointer', whiteSpace: 'nowrap' }}
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
                                                    )}

                                                    {/* 1. Candidate Profile Card */}
                                                    <div className="p-3.5 rounded-3 bg-white border shadow-2xs mb-1">
                                                        <div className="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                                                            <div className="d-flex align-items-center gap-3">
                                                                {photoUrl ? (
                                                                    <img
                                                                        src={photoUrl}
                                                                        alt={candidateName}
                                                                        className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                                        style={{ width: '64px', height: '64px' }}
                                                                    />
                                                                ) : (
                                                                    <div
                                                                        className="rounded-circle bg-primary-subtle text-primary border border-primary-subtle d-flex align-items-center justify-content-center fw-bold fs-4 flex-shrink-0"
                                                                        style={{ width: '64px', height: '64px' }}
                                                                    >
                                                                        {candidateName.charAt(0).toUpperCase()}
                                                                    </div>
                                                                )}
                                                                <div>
                                                                    <div className="d-flex align-items-center gap-2 flex-wrap">
                                                                        <h5 className="fw-bold text-dark mb-0">{candidateName}</h5>
                                                                        <span className="badge bg-primary text-white px-2.5 py-1 fw-semibold" style={{ fontSize: '0.75rem', borderRadius: '6px' }}>
                                                                            {appliedRole}
                                                                        </span>
                                                                    </div>
                                                                    <div className="text-secondary small mt-1 d-flex align-items-center gap-2 flex-wrap">
                                                                        <span className="fw-semibold text-dark">{designation}</span>
                                                                        {companyName !== '—' && <span>at <strong className="text-dark">{companyName}</strong></span>}
                                                                        <span>• <MapPin size={12} className="text-muted me-0.5" />{cityName}</span>
                                                                    </div>
                                                                    {/* Social / Web Links */}
                                                                    {(linkedinUrl || websiteUrl) && (
                                                                        <div className="d-flex align-items-center gap-2 mt-2">
                                                                            {linkedinUrl && (
                                                                                <a
                                                                                    href={linkedinUrl.startsWith('http') ? linkedinUrl : `https://${linkedinUrl}`}
                                                                                    target="_blank"
                                                                                    rel="noopener noreferrer"
                                                                                    className="btn btn-xs btn-outline-primary py-0.5 px-2 d-inline-flex align-items-center gap-1 rounded-2"
                                                                                    style={{ fontSize: '11px' }}
                                                                                >
                                                                                    <ExternalLink size={11} /> LinkedIn
                                                                                </a>
                                                                            )}
                                                                            {websiteUrl && (
                                                                                <a
                                                                                    href={websiteUrl.startsWith('http') ? websiteUrl : `https://${websiteUrl}`}
                                                                                    target="_blank"
                                                                                    rel="noopener noreferrer"
                                                                                    className="btn btn-xs btn-outline-secondary py-0.5 px-2 d-inline-flex align-items-center gap-1 rounded-2"
                                                                                    style={{ fontSize: '11px' }}
                                                                                >
                                                                                    <Globe size={11} /> Website
                                                                                </a>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            <div className="d-flex flex-row flex-md-column align-items-center align-items-md-end justify-content-between gap-1.5 flex-shrink-0 w-100 w-md-auto pt-2 pt-md-0 border-top border-top-md-0">
                                                                <span className="badge bg-light text-secondary border px-2.5 py-1 text-nowrap fw-semibold" style={{ fontSize: '11px' }}>
                                                                    {experience}
                                                                </span>
                                                                <span className="text-muted text-nowrap" style={{ fontSize: '11.5px' }}>
                                                                    Submitted: {selectedNomination.submitted_at ? new Date(selectedNomination.submitted_at).toLocaleDateString() : 'Draft'}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* 2. Professional Profile Grid */}
                                                    <div className="row g-2.5">
                                                        <div className="col-12 col-md-3">
                                                            <div className="p-3 rounded-3 bg-light border h-100">
                                                                <span className="text-muted small fw-bold text-uppercase d-block" style={{ fontSize: '11px', letterSpacing: '0.04em' }}>Belonging / Scope</span>
                                                                <div className="fw-bold text-dark mt-1">{cityName}</div>
                                                                <span className="badge bg-white text-secondary border mt-1" style={{ fontSize: '10px' }}>
                                                                    {toText(belonging.type, 'Jurisdiction')}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div className="col-12 col-md-3">
                                                            <div className="p-3 rounded-3 bg-light border h-100">
                                                                <span className="text-muted small fw-bold text-uppercase d-block" style={{ fontSize: '11px', letterSpacing: '0.04em' }}>Industry & Enterprise</span>
                                                                <div className="fw-bold text-dark mt-1 text-truncate" title={industry}>{industry}</div>
                                                                <span className="small text-muted mt-1 d-block">{companyName}</span>
                                                            </div>
                                                        </div>
                                                        <div className="col-12 col-md-3">
                                                            <div className="p-3 rounded-3 bg-light border h-100">
                                                                <span className="text-muted small fw-bold text-uppercase d-block" style={{ fontSize: '11px', letterSpacing: '0.04em' }}>Verified Email</span>
                                                                <div className="fw-bold text-dark mt-1 text-truncate" title={toText(selectedNomination.email)}>{toText(selectedNomination.email)}</div>
                                                                <span className="badge bg-success-subtle text-success border border-success-subtle mt-1" style={{ fontSize: '10px' }}>
                                                                    <Check size={10} className="me-0.5" /> OTP Verified
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div className="col-12 col-md-3">
                                                            <div className="p-3 rounded-3 bg-light border h-100">
                                                                <span className="text-muted small fw-bold text-uppercase d-block" style={{ fontSize: '11px', letterSpacing: '0.04em' }}>Verified Mobile</span>
                                                                <div className="fw-bold text-dark mt-1">{toText(selectedNomination.mobile, '—')}</div>
                                                                <span className="badge bg-success-subtle text-success border border-success-subtle mt-1" style={{ fontSize: '10px' }}>
                                                                    <Check size={10} className="me-0.5" /> Phone Verified
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* 3. Leadership & Vision Section (NEW & HIGHLIGHTED) */}
                                                    <div className="d-flex flex-column gap-2.5">
                                                        {/* One-Line Leadership Statement */}
                                                        {leadershipStatement && (
                                                            <div className="p-3.5 rounded-3 border shadow-2xs" style={{ background: 'linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%)', borderColor: '#c7d2fe' }}>
                                                                <div className="text-uppercase small fw-bold text-primary mb-1 d-flex align-items-center gap-1.5" style={{ fontSize: '11px', letterSpacing: '0.05em' }}>
                                                                    <Sparkles size={14} className="text-primary" /> One-Line Leadership Statement
                                                                </div>
                                                                <div className="fw-bold text-dark fs-6 fst-italic">
                                                                    &ldquo;{leadershipStatement}&rdquo;
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Motivation ("Why this role?") & 12-Month Plan */}
                                                        <div className="row g-2.5">
                                                            <div className="col-12 col-md-6">
                                                                <div className="p-3.5 rounded-3 bg-white border shadow-2xs h-100" style={{ minHeight: '110px' }}>
                                                                    <h6 className="fw-bold text-dark mb-2 d-flex align-items-center gap-2" style={{ fontSize: '13px' }}>
                                                                        <Target size={16} className="text-primary" />
                                                                        Why this role? (Motivation)
                                                                    </h6>
                                                                    <div className="small text-secondary" style={{ lineHeight: 1.6, wordBreak: 'break-word' }}>
                                                                        {whyRole || <span className="text-muted fst-italic">No motivation statement provided.</span>}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <div className="p-3.5 rounded-3 bg-white border shadow-2xs h-100" style={{ minHeight: '110px' }}>
                                                                    <h6 className="fw-bold text-dark mb-2 d-flex align-items-center gap-2" style={{ fontSize: '13px' }}>
                                                                        <Compass size={16} className="text-primary" />
                                                                        12-Month Build Plan & Milestones
                                                                    </h6>
                                                                    <div className="small text-secondary" style={{ lineHeight: 1.6, wordBreak: 'break-word' }}>
                                                                        {vision12Months || (goals.length > 0 ? goals.join(' • ') : <span className="text-muted fst-italic">No 12-month roadmap entered.</span>)}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {/* Commitment & Other Network Involvement */}
                                                        <div className="row g-2.5">
                                                            <div className="col-12 col-md-4">
                                                                <div className="p-3.5 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between" style={{ minHeight: '90px' }}>
                                                                    <div>
                                                                        <span className="text-muted small fw-bold text-uppercase d-flex align-items-center gap-1" style={{ fontSize: '11px', letterSpacing: '0.04em' }}>
                                                                            <Clock size={13} className="text-primary" /> Time Commitment
                                                                        </span>
                                                                        <div className="fw-bold text-primary mt-1.5 fs-6">{toText(hoursPerWeek)}</div>
                                                                    </div>
                                                                    <div className="small text-muted mt-2" style={{ fontSize: '11.5px', lineHeight: 1.3 }}>
                                                                        Weekly allocation for chapter governance
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-8">
                                                                <div className="p-3.5 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between" style={{ minHeight: '90px' }}>
                                                                    <div>
                                                                        <div className="d-flex align-items-center justify-content-between mb-1">
                                                                            <span className="text-muted small fw-bold text-uppercase" style={{ fontSize: '11px', letterSpacing: '0.04em' }}>
                                                                                Other Business Network Roles
                                                                            </span>
                                                                            {hasOtherNetwork ? (
                                                                                <span className="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5" style={{ fontSize: '10.5px' }}>
                                                                                    Active in Other Networks
                                                                                </span>
                                                                            ) : (
                                                                                <span className="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style={{ fontSize: '10.5px' }}>
                                                                                    <Check size={11} className="me-0.5" /> None (Exclusive)
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                        <div className="small text-dark mt-1" style={{ wordBreak: 'break-word' }}>
                                                                            {otherNetworkDetails || (hasOtherNetwork ? 'Holds leadership positions in external business associations.' : 'Candidate confirmed exclusive commitment to Peers Global.')}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {/* Intro Video URL Preview */}
                                                        {videoUrl && (
                                                            <div className="p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 d-flex align-items-center justify-content-between">
                                                                <div className="d-flex align-items-center gap-2.5">
                                                                    <Video size={20} className="text-primary flex-shrink-0" />
                                                                    <div>
                                                                        <div className="fw-bold text-dark small">Candidate 60-Second Video Pitch</div>
                                                                        <div className="text-muted small font-monospace text-truncate" style={{ maxWidth: '420px' }}>
                                                                            {videoUrl}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <a
                                                                    href={videoUrl}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="btn btn-sm btn-primary rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1.5 shadow-xs"
                                                                >
                                                                    <ExternalLink size={13} /> Watch Video
                                                                </a>
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Plan for the Year (Top 3 Goals) */}
                                                    {goals.length > 0 && (
                                                        <div className="p-3.5 rounded-3 bg-white border shadow-2xs">
                                                            <div className="d-flex align-items-center justify-content-between mb-2.5">
                                                                <h6 className="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                                                    <Target size={16} className="text-primary" />
                                                                    Plan for the Year • Top 3 Goals
                                                                </h6>
                                                            </div>
                                                            <div className="row g-2">
                                                                {[0, 1, 2].map((idx) => {
                                                                    const rawGoal = goals[idx] || (snap as any)[`goal_${idx + 1}`] || '';
                                                                    const goalText = toText(rawGoal);
                                                                    return (
                                                                        <div key={idx} className="col-12 col-md-4">
                                                                            <div className="p-2.5 rounded-3 bg-light border h-100">
                                                                                <div className="d-flex align-items-center gap-1.5 mb-1 text-primary fw-bold small">
                                                                                    <span className="badge bg-primary text-white rounded-circle p-1" style={{ width: '18px', height: '18px', fontSize: '10px', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
                                                                                        {idx + 1}
                                                                                    </span>
                                                                                    <span>Goal {idx + 1}</span>
                                                                                </div>
                                                                                <div className="small text-dark" style={{ minHeight: '44px' }}>
                                                                                    {goalText || <span className="text-muted fst-italic">No specific goal entered</span>}
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>
                                                    )}



                                                    {/* 5. Signed Legal Declarations */}
                                                    <div className="p-3.5 rounded-3 bg-white border shadow-2xs">
                                                        <div className="d-flex align-items-center justify-content-between mb-2.5">
                                                            <h6 className="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                                                <ShieldCheck size={16} className="text-primary" />
                                                                Signed Declarations & Legal Consents
                                                            </h6>
                                                            <span className="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 small">
                                                                <Check size={11} className="me-1" /> 4/4 Consents Signed
                                                            </span>
                                                        </div>
                                                        <div className="row g-2">
                                                            <div className="col-12 col-md-6">
                                                                <div className="p-2.5 rounded-3 bg-light border d-flex align-items-start gap-2">
                                                                    <CheckCircle2 size={16} className="text-success mt-0.5 flex-shrink-0" />
                                                                    <div className="small">
                                                                        <div className="fw-bold text-dark">Truthful Information</div>
                                                                        <div className="text-muted" style={{ fontSize: '11.5px' }}>Affirmed all business turnover & credentials are true.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <div className="p-2.5 rounded-3 bg-light border d-flex align-items-start gap-2">
                                                                    <CheckCircle2 size={16} className="text-success mt-0.5 flex-shrink-0" />
                                                                    <div className="small">
                                                                        <div className="fw-bold text-dark">Code of Conduct & Zero Incentives</div>
                                                                        <div className="text-muted" style={{ fontSize: '11.5px' }}>Agreed not to offer any voting inducements or bribes.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <div className="p-2.5 rounded-3 bg-light border d-flex align-items-start gap-2">
                                                                    <CheckCircle2 size={16} className="text-success mt-0.5 flex-shrink-0" />
                                                                    <div className="small">
                                                                        <div className="fw-bold text-dark">Public Candidate Profile Consent</div>
                                                                        <div className="text-muted" style={{ fontSize: '11.5px' }}>Approved publishing leadership profile for peer voting.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <div className="p-2.5 rounded-3 bg-light border d-flex align-items-start gap-2">
                                                                    <CheckCircle2 size={16} className="text-success mt-0.5 flex-shrink-0" />
                                                                    <div className="small">
                                                                        <div className="fw-bold text-dark">Jury Evaluation Contact Consent</div>
                                                                        <div className="text-muted" style={{ fontSize: '11.5px' }}>Consented to interview & scrutiny by appointed jurors.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Rejection / Correction Alerts */}
                                                    {selectedNomination.rejection_reason && (
                                                        <div className="alert alert-danger mb-0 rounded-3">
                                                            <strong>Rejection Reason:</strong> {selectedNomination.rejection_reason}
                                                        </div>
                                                    )}
                                                    {selectedNomination.correction_notes && (
                                                        <div className="alert alert-warning mb-0 rounded-3">
                                                            <strong>Requested Corrections:</strong> {selectedNomination.correction_notes}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })()}

                                        {/* Tab: Votes */}
                                        {activeTab === 'votes' && (
                                            <div className="d-flex flex-column gap-3">
                                                {/* Top Summary: Total Votes & Refresh Button Only */}
                                                <div className="p-3.5 bg-white rounded-3 border shadow-2xs d-flex align-items-center justify-content-between flex-wrap gap-3">
                                                    <div className="d-flex align-items-center gap-3">
                                                        <div className="p-2.5 bg-primary bg-opacity-10 text-primary rounded-3">
                                                            <ThumbsUp size={24} />
                                                        </div>
                                                        <div>
                                                            <div className="text-muted small fw-semibold text-uppercase mb-0.5">Total Votes</div>
                                                            <div className="d-flex align-items-baseline gap-2">
                                                                <span className="fs-2 fw-bold text-primary">
                                                                    {candidateVotes?.total ?? selectedNomination.votes_count ?? (selectedNomination.detailed_votes?.length || 0)}
                                                                </span>
                                                                <span className="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style={{ fontSize: '0.72rem' }}>
                                                                    100% OTP Verified
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => refreshCandidateVotes(selectedNomination.id)}
                                                        disabled={isLoadingVotes}
                                                        className="btn btn-outline-primary btn-sm fw-semibold d-inline-flex align-items-center gap-2 px-3 py-2 shadow-xs"
                                                        title="Refresh latest votes"
                                                    >
                                                        <RefreshCw size={14} className={isLoadingVotes ? 'spinner-border spinner-border-sm border-0' : ''} style={isLoadingVotes ? { animation: 'spin 1s linear infinite' } : {}} />
                                                        <span>{isLoadingVotes ? 'Refreshing...' : 'Refresh Votes'}</span>
                                                    </button>
                                                </div>

                                                {/* Voters Ledger Table */}
                                                <div className="bg-white rounded-3 border shadow-2xs overflow-hidden">
                                                    <div className="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
                                                        <div>
                                                            <h6 className="fw-bold text-dark mb-0">Who Voted For This Nominee (Voters Audit Ledger)</h6>
                                                            <small className="text-muted">Complete breakdown of verified voters who voted for this candidate</small>
                                                        </div>
                                                        <div className="d-flex align-items-center gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={() => refreshCandidateVotes(selectedNomination.id)}
                                                                disabled={isLoadingVotes}
                                                                className="btn btn-sm btn-light border text-secondary d-inline-flex align-items-center gap-1.5 px-2.5 py-1"
                                                                title="Refresh list"
                                                            >
                                                                <RefreshCw size={12} className={isLoadingVotes ? 'spinner-border spinner-border-sm border-0' : ''} style={isLoadingVotes ? { animation: 'spin 1s linear infinite' } : {}} />
                                                                <span style={{ fontSize: '0.78rem' }}>Refresh</span>
                                                            </button>
                                                            <span className="badge bg-white text-primary border font-monospace px-2.5 py-1">
                                                                {candidateVotes?.data?.length ?? selectedNomination.detailed_votes?.length ?? 0} Recorded
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {isLoadingVotes ? (
                                                        <div className="text-center py-5">
                                                            <div className="spinner-border text-primary spinner-border-sm mb-2" role="status"></div>
                                                            <div className="text-muted small">Loading live voter audit records...</div>
                                                        </div>
                                                    ) : (!candidateVotes?.data || candidateVotes.data.length === 0) && (!selectedNomination.detailed_votes || selectedNomination.detailed_votes.length === 0) ? (
                                                        <div className="text-center py-5 px-3">
                                                            <div className="rounded-circle bg-light d-inline-flex p-3 text-muted mb-2">
                                                                <ThumbsUp size={24} />
                                                            </div>
                                                            <h6 className="text-dark fw-bold mb-1">No Public Votes Recorded Yet</h6>
                                                            <p className="text-muted small mb-3" style={{ maxWidth: '440px', margin: '0 auto' }}>
                                                                This nominee has not received any votes yet.
                                                            </p>
                                                            <button
                                                                type="button"
                                                                onClick={() => refreshCandidateVotes(selectedNomination.id)}
                                                                disabled={isLoadingVotes}
                                                                className="btn btn-primary btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                                                            >
                                                                <RefreshCw size={13} className={isLoadingVotes ? 'spinner-border spinner-border-sm border-0' : ''} style={isLoadingVotes ? { animation: 'spin 1s linear infinite' } : {}} /> Refresh Votes
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <div className="table-responsive">
                                                            <table className="table table-hover align-middle mb-0">
                                                                <thead className="table-light">
                                                                    <tr style={{ fontSize: '0.78rem' }}>
                                                                        <th className="ps-3 py-2 text-muted fw-semibold">#</th>
                                                                        <th className="py-2 text-muted fw-semibold">VOTER NAME</th>
                                                                        <th className="py-2 text-muted fw-semibold">ORGANIZATION & ROLE</th>
                                                                        <th className="py-2 text-muted fw-semibold">CITY</th>
                                                                        <th className="py-2 text-muted fw-semibold">VERIFIED CONTACT</th>
                                                                        <th className="py-2 text-muted fw-semibold">VOTE REF CODE</th>
                                                                        <th className="py-2 text-muted fw-semibold">VOTED AT</th>
                                                                        <th className="pe-3 py-2 text-muted fw-semibold">SUPPORT STATEMENT</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody style={{ fontSize: '0.84rem' }}>
                                                                    {((candidateVotes?.data && candidateVotes.data.length > 0) ? candidateVotes.data : (selectedNomination.detailed_votes || [])).map((voter: any, idx: number) => (
                                                                        <tr key={voter.id || voter.vote_reference || idx}>
                                                                            <td className="ps-3 py-2.5 text-muted">{idx + 1}</td>
                                                                            <td className="py-2.5">
                                                                                <div className="fw-semibold text-dark">{voter.voter_name || voter.name || 'Verified Voter'}</div>
                                                                            </td>
                                                                            <td className="py-2.5">
                                                                                <div className="text-dark fw-medium">{voter.voter_company || voter.company || '—'}</div>
                                                                                <div className="small text-muted">{voter.voter_designation || voter.designation || ''}</div>
                                                                            </td>
                                                                            <td className="py-2.5">
                                                                                <span className="badge bg-light text-secondary border">
                                                                                    {voter.voter_city || voter.city || '—'}
                                                                                </span>
                                                                            </td>
                                                                            <td className="py-2.5">
                                                                                <div className="small text-dark text-truncate" style={{ maxWidth: '160px' }}>{voter.voter_email || voter.email || '—'}</div>
                                                                                {voter.voter_phone && (
                                                                                    <div className="small text-muted font-monospace">{voter.voter_phone}</div>
                                                                                )}
                                                                            </td>
                                                                            <td className="py-2.5">
                                                                                <span className="badge bg-light text-primary border font-monospace" style={{ fontSize: '11px' }}>
                                                                                    {voter.vote_reference || voter.reference_code || `VR-${idx + 1}`}
                                                                                </span>
                                                                            </td>
                                                                            <td className="py-2.5 text-nowrap text-muted small">
                                                                                {voter.created_at_human || (voter.created_at ? new Date(voter.created_at).toLocaleString() : '—')}
                                                                            </td>
                                                                            <td className="pe-3 py-2.5">
                                                                                <div className="small text-secondary fst-italic" style={{ maxWidth: '240px' }}>
                                                                                    {voter.support_reason || 'Verified Community Support'}
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {/* Tab: Jury Evaluation */}
                                        {activeTab === 'jury' && (() => {
                                            const juryEval = selectedNomination.jury_evaluation;
                                            const assignments = selectedNomination.jury_assignments || [];
                                            const hasAssignments = assignments.length > 0;

                                            return (
                                                <div className="d-flex flex-column gap-3">
                                                    {juryEval ? (
                                                        <div className="p-3.5 bg-white rounded-3 border shadow-2xs">
                                                            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 border-bottom pb-3 mb-3">
                                                                <div className="d-flex align-items-center gap-2.5">
                                                                    <div className="p-2 bg-success bg-opacity-10 text-success rounded-3">
                                                                        <Award size={24} />
                                                                    </div>
                                                                    <div>
                                                                        <h6 className="fw-bold text-dark mb-0">Official Standing Jury Evaluation Completed</h6>
                                                                        <p className="text-muted small mb-0">Formally reviewed and evaluated by appointed committee juror</p>
                                                                    </div>
                                                                </div>
                                                                <div className="d-flex align-items-center gap-2">
                                                                    <span className="text-muted small">Verdict:</span>
                                                                    <span className={`badge text-uppercase px-3 py-1.5 fw-bold font-monospace ${
                                                                        (juryEval.overall_recommendation || '').toLowerCase().includes('recommend') && !(juryEval.overall_recommendation || '').toLowerCase().includes('not')
                                                                            ? 'bg-success text-white'
                                                                            : (juryEval.overall_recommendation || '').toLowerCase().includes('do_not') || (juryEval.overall_recommendation || '').toLowerCase().includes('reject')
                                                                                ? 'bg-danger text-white'
                                                                                : 'bg-warning text-dark'
                                                                    }`}>
                                                                        {juryEval.overall_recommendation || 'Evaluated'}
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div className="row g-3 mb-3">
                                                                <div className="col-12 col-md-4">
                                                                    <div className="p-2.5 bg-light rounded-2 border">
                                                                        <div className="text-muted small">Appointed Juror</div>
                                                                        <div className="fw-bold text-dark">{juryEval.juror_name || 'Standing Juror'}</div>
                                                                        {juryEval.juror_email && <div className="text-muted small">{juryEval.juror_email}</div>}
                                                                    </div>
                                                                </div>
                                                                <div className="col-12 col-md-4">
                                                                    <div className="p-2.5 bg-light rounded-2 border">
                                                                        <div className="text-muted small">Evaluation Completed At</div>
                                                                        <div className="fw-bold text-dark">
                                                                            {juryEval.completed_at ? new Date(juryEval.completed_at).toLocaleString() : 'Recently'}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div className="col-12 col-md-4">
                                                                    <div className="p-2.5 bg-light rounded-2 border">
                                                                        <div className="text-muted small">Evaluation Stage</div>
                                                                        <div className="fw-bold text-primary">Stage 2: Standing Jury Committee</div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Strengths */}
                                                            {juryEval.strengths && (
                                                                <div className="mb-3 p-3 rounded-2 bg-success bg-opacity-10 border border-success border-opacity-25">
                                                                    <div className="fw-bold text-success mb-1 d-flex align-items-center gap-1.5">
                                                                        <Sparkles size={16} /> Key Strengths
                                                                    </div>
                                                                    <div className="text-dark small">{juryEval.strengths}</div>
                                                                </div>
                                                            )}

                                                            {/* Concerns */}
                                                            {juryEval.concerns && (
                                                                <div className="mb-3 p-3 rounded-2 bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                                                    <div className="fw-bold text-warning-emphasis mb-1 d-flex align-items-center gap-1.5">
                                                                        <AlertTriangle size={16} /> Key Concerns / Flags
                                                                    </div>
                                                                    <div className="text-dark small">{juryEval.concerns}</div>
                                                                </div>
                                                            )}

                                                            {/* Remarks */}
                                                            {(juryEval.final_remarks || juryEval.remarks) && (
                                                                <div className="p-3 rounded-2 bg-light border">
                                                                    <div className="fw-bold text-dark mb-1">Juror Final Remarks & Advice</div>
                                                                    <div className="text-secondary small">{juryEval.final_remarks || juryEval.remarks}</div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    ) : hasAssignments ? (
                                                        <div className="p-3.5 bg-white rounded-3 border shadow-2xs">
                                                            <div className="d-flex align-items-center gap-2 mb-2 text-warning-emphasis fw-bold">
                                                                <Clock size={18} />
                                                                <span>Jury Assignment Pending Evaluation</span>
                                                            </div>
                                                            <p className="text-muted small mb-3">
                                                                This candidate has been assigned to the following juror(s) and is currently undergoing dossier evaluation:
                                                            </p>
                                                            <div className="list-group">
                                                                {assignments.map((as: any, i: number) => (
                                                                    <div key={as.id || i} className="list-group-item d-flex align-items-center justify-content-between p-3">
                                                                        <div>
                                                                            <div className="fw-bold text-dark">{as.juror?.display_name || as.juror_name || 'Appointed Juror'}</div>
                                                                            <div className="text-muted small">{as.juror?.email || as.juror_email}</div>
                                                                        </div>
                                                                        <span className={`badge ${as.status === 'completed' ? 'bg-success' : 'bg-warning text-dark'}`}>
                                                                            {as.status === 'completed' ? 'Evaluation Submitted' : 'Awaiting Juror Evaluation'}
                                                                        </span>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="text-center py-5 px-3 bg-white rounded-3 border shadow-2xs">
                                                            <div className="rounded-circle bg-light d-inline-flex p-3 text-muted mb-2">
                                                                <Award size={24} />
                                                            </div>
                                                            <h6 className="text-dark fw-bold mb-1">Candidate Not Yet in Jury Stage</h6>
                                                            <p className="text-muted small mb-3" style={{ maxWidth: '440px', margin: '0 auto' }}>
                                                                This candidate has not been proceeded to the Jury Evaluation pool yet. You can advance this candidate to receive their Form 2 dossier and assign standing committee jurors.
                                                            </p>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleOpenProceedJuryModal([selectedNomination])}
                                                                className="btn btn-primary btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                                                            >
                                                                <Award size={13} /> Proceed Candidate to Jury Stage
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })()}

                                        {/* Tab 2: Answers */}
                                        {activeTab === 'answers' && (
                                            <div>
                                                <h6 className="fw-bold text-dark mb-3">Submitted Dynamic Answers & Form 2 Responses</h6>
                                                {selectedNomination.answers && selectedNomination.answers.length > 0 ? (
                                                    <div className="list-group">
                                                        {selectedNomination.answers.map((ans, idx) => {
                                                            const questionLabel =
                                                                ans.question_label ||
                                                                ans.question?.label ||
                                                                (() => {
                                                                    const rawKey = ans.question_key || ans.question?.question_key;
                                                                    if (!rawKey) return `Question ${idx + 1}`;
                                                                    const cleanKey = rawKey.replace(/^q_/, '').replace(/_/g, ' ');
                                                                    return cleanKey.charAt(0).toUpperCase() + cleanKey.slice(1);
                                                                })();

                                                            let rawVal =
                                                                ans.answer_text ??
                                                                ans.answer_json ??
                                                                (ans.answer && typeof ans.answer === 'object' && 'value' in ans.answer ? (ans.answer as any).value : ans.answer);

                                                            let displayContent: React.ReactNode = null;
                                                            if (rawVal === true || rawVal === 'true') {
                                                                displayContent = (
                                                                    <span className="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                                                        <Check size={13} className="me-1" /> Affirmed (Yes)
                                                                    </span>
                                                                );
                                                            } else if (rawVal === false || rawVal === 'false') {
                                                                displayContent = (
                                                                    <span className="badge bg-light text-muted border px-2.5 py-1 rounded-pill">
                                                                        No
                                                                    </span>
                                                                );
                                                            } else if (typeof rawVal === 'string' && rawVal.trim().length > 0) {
                                                                displayContent = <div className="text-dark fw-medium mt-1">{rawVal}</div>;
                                                            } else if (rawVal && typeof rawVal === 'object') {
                                                                displayContent = (
                                                                    <pre className="bg-light p-2 rounded small text-dark mt-1 mb-0" style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                                                                        {JSON.stringify(rawVal, null, 2)}
                                                                    </pre>
                                                                );
                                                            } else {
                                                                displayContent = <span className="text-muted fst-italic">No answer provided</span>;
                                                            }

                                                            return (
                                                                <div key={ans.id || idx} className="list-group-item p-3 mb-2 rounded-3 border bg-white shadow-2xs">
                                                                    <div className="d-flex align-items-center justify-content-between gap-2">
                                                                        <div className="text-primary small fw-bold text-uppercase" style={{ letterSpacing: '0.04em', fontSize: '0.75rem' }}>
                                                                            {questionLabel}
                                                                        </div>
                                                                        {ans.question_key && (
                                                                            <span className="badge bg-light text-muted border font-monospace" style={{ fontSize: '10px' }}>
                                                                                {ans.question_key}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                    <div className="mt-1">
                                                                        {displayContent}
                                                                    </div>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                ) : (selectedNomination.form_2_data || (selectedNomination.profile_snapshot as any)?.form_2) ? (
                                                    (() => {
                                                        const f2 = selectedNomination.form_2_data || (selectedNomination.profile_snapshot as any)?.form_2;
                                                        const secA = f2?.section_a_business_credibility || f2;
                                                        const secB = f2?.section_b_leadership_track_record || f2;
                                                        const secE = f2?.section_e_12_month_plan || f2;
                                                        return (
                                                            <div className="d-flex flex-column gap-3">
                                                                <div className="p-3 rounded-3 border bg-light">
                                                                    <span className="badge bg-primary mb-2">Section A: Business Credibility</span>
                                                                    <div className="row g-2 small">
                                                                        <div className="col-md-4"><span className="text-muted">Business Type:</span> <strong className="text-dark d-block">{secA?.business_type || '—'}</strong></div>
                                                                        <div className="col-md-4"><span className="text-muted">Turnover Band:</span> <strong className="text-dark d-block">{secA?.annual_turnover_band || '—'}</strong></div>
                                                                        <div className="col-md-4"><span className="text-muted">Team Size:</span> <strong className="text-dark d-block">{secA?.team_size || '—'}</strong></div>
                                                                        <div className="col-12 mt-2"><span className="text-muted">Achievements:</span> <div className="p-2 rounded bg-white border mt-1">{secA?.achievements || secA?.top_3_achievements || '—'}</div></div>
                                                                    </div>
                                                                </div>
                                                                <div className="p-3 rounded-3 border bg-light">
                                                                    <span className="badge bg-dark mb-2">Section B: Leadership Track Record</span>
                                                                    <div className="row g-2 small">
                                                                        <div className="col-md-6"><span className="text-muted">Leadership Roles:</span> <div className="p-2 rounded bg-white border mt-1">{secB?.leadership_roles || '—'}</div></div>
                                                                        <div className="col-md-6"><span className="text-muted">Largest Community:</span> <div className="p-2 rounded bg-white border mt-1">{secB?.largest_community || '—'}</div></div>
                                                                    </div>
                                                                </div>
                                                                {secE?.twelve_month_plan && (
                                                                    <div className="p-3 rounded-3 border bg-light">
                                                                        <span className="badge bg-secondary mb-2">Section E: 12-Month Plan</span>
                                                                        <div className="p-2 rounded bg-white border small">{secE.twelve_month_plan}</div>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        );
                                                    })()
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
                                                    <div className="p-4 text-center rounded-3 bg-light border">
                                                        <CheckCircle2 size={32} className="text-success mx-auto mb-2" />
                                                        <h6 className="fw-bold text-dark mb-1">Directory Profile Verified</h6>
                                                        <p className="text-muted small mb-0">
                                                            No differences detected between candidate's directory profile and nomination entry.
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* Tab 4: Documents */}
                                        {activeTab === 'documents' && (() => {
                                            const snap = selectedNomination.profile_snapshot || {};
                                            const allDocs = selectedNomination.documents || [];
                                            const existingUrlsOrNames = new Set(
                                                allDocs.map((d: any) => (d.file_url || d.file_name || d.original_filename || '').toLowerCase())
                                            );

                                            const rawSnapDocs = [
                                                snap.id_file ? { type: 'ID Proof (Aadhaar / Passport)', name: snap.id_file, url: snap.id_file.startsWith('http') ? snap.id_file : undefined, status: 'verified', confidential: true } : null,
                                                snap.letter_file ? { type: 'Recommendation Letter', name: snap.letter_file, url: snap.letter_file.startsWith('http') ? snap.letter_file : undefined, status: 'submitted' } : null,
                                                snap.deck_file ? { type: 'Company Profile / Deck', name: snap.deck_file, url: snap.deck_file.startsWith('http') ? snap.deck_file : undefined, status: 'submitted' } : null,
                                                snap.manifesto_file ? { type: 'Candidate Manifesto', name: snap.manifesto_file, url: snap.manifesto_file.startsWith('http') ? snap.manifesto_file : undefined, status: 'submitted' } : null,
                                            ].filter(Boolean) as any[];

                                            // Filter out snapDocs already represented in allDocs
                                            const snapDocs = rawSnapDocs.filter((sd) => !existingUrlsOrNames.has((sd.url || sd.name).toLowerCase()));
                                            const hasAny = allDocs.length > 0 || snapDocs.length > 0;

                                            return (
                                                <div>
                                                    <h6 className="fw-bold text-dark mb-3">Supporting Uploaded Documents (Form 1 & Form 2)</h6>
                                                    {hasAny ? (
                                                        <div className="row g-3">
                                                            {snapDocs.map((doc: any, idx: number) => (
                                                                <div key={`snap-${idx}`} className="col-12 col-md-6">
                                                                    <div className="p-3 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                                                                        <div className="text-truncate me-2">
                                                                            <div className="fw-bold text-dark d-flex align-items-center gap-1.5">
                                                                                <span>{doc.type}</span>
                                                                                {doc.confidential && (
                                                                                    <span className="badge bg-danger-subtle text-danger border border-danger-subtle" style={{ fontSize: '9px' }}>
                                                                                        Confidential
                                                                                    </span>
                                                                                )}
                                                                            </div>
                                                                            <div className="text-muted small text-truncate" title={doc.name}>{doc.name}</div>
                                                                        </div>
                                                                        <div className="d-flex align-items-center gap-2 flex-shrink-0">
                                                                            {doc.url && (
                                                                                <a
                                                                                    href={doc.url}
                                                                                    target="_blank"
                                                                                    rel="noreferrer"
                                                                                    className="btn btn-outline-secondary btn-sm"
                                                                                    title="View Document"
                                                                                >
                                                                                    <ExternalLink size={14} />
                                                                                </a>
                                                                            )}
                                                                            <span className="badge bg-success-subtle text-success border border-success-subtle">
                                                                                Attached
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                            {allDocs.map((doc) => (
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
                                            );
                                        })()}

                                        {/* Tab 5: Declarations */}
                                        {activeTab === 'declarations' && (
                                            <div className="d-flex flex-column gap-3">
                                                <div className="p-3.5 bg-white rounded-3 border shadow-2xs">
                                                    <div className="d-flex align-items-center justify-content-between mb-3">
                                                        <h6 className="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                                            <ShieldCheck size={18} className="text-primary" />
                                                            Candidate Legal Declarations & Consents
                                                        </h6>
                                                        <span className="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1">
                                                            <Check size={12} className="me-1" /> Verified & Sealed
                                                        </span>
                                                    </div>
                                                    <p className="text-muted small mb-3">
                                                        Candidate verified truthfulness of all submitted leadership credentials and agreed to organizational guidelines upon OTP verification on {selectedNomination.submitted_at ? new Date(selectedNomination.submitted_at).toLocaleString() : 'Submission'}.
                                                    </p>
                                                    <div className="row g-2.5">
                                                        <div className="col-12 col-md-6">
                                                            <div className="p-3 rounded-3 bg-light border d-flex align-items-start gap-2.5">
                                                                <CheckCircle2 size={18} className="text-success mt-0.5 flex-shrink-0" />
                                                                <div>
                                                                    <div className="fw-bold text-dark small">1. Truthful Information</div>
                                                                    <div className="text-muted small mt-0.5">I declare that all personal and professional information provided in this application is accurate and verifiable.</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="col-12 col-md-6">
                                                            <div className="p-3 rounded-3 bg-light border d-flex align-items-start gap-2.5">
                                                                <CheckCircle2 size={18} className="text-success mt-0.5 flex-shrink-0" />
                                                                <div>
                                                                    <div className="fw-bold text-dark small">2. Code of Conduct & Zero Incentives</div>
                                                                    <div className="text-muted small mt-0.5">I pledge not to offer financial inducements, gifts, or incentives to voters or election stewards.</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="col-12 col-md-6">
                                                            <div className="p-3 rounded-3 bg-light border d-flex align-items-start gap-2.5">
                                                                <CheckCircle2 size={18} className="text-success mt-0.5 flex-shrink-0" />
                                                                <div>
                                                                    <div className="fw-bold text-dark small">3. Public Candidate Profile Consent</div>
                                                                    <div className="text-muted small mt-0.5">I consent to Peers Global publishing my leadership profile, vision, and credentials for peer member voting.</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="col-12 col-md-6">
                                                            <div className="p-3 rounded-3 bg-light border d-flex align-items-start gap-2.5">
                                                                <CheckCircle2 size={18} className="text-success mt-0.5 flex-shrink-0" />
                                                                <div>
                                                                    <div className="fw-bold text-dark small">4. Jury & Governance Contact Consent</div>
                                                                    <div className="text-muted small mt-0.5">I consent to being interviewed and audited by appointed standing jurors and the Election Committee.</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
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

                                            const hasData = Boolean(
                                                rawF2 && (
                                                    selectedNomination.form_2_status === 'submitted' ||
                                                    selectedNomination.form_type === 'sub_form_2' ||
                                                    Object.keys(rawF2).length > 2 ||
                                                    secA?.business_type ||
                                                    secB?.largest_community ||
                                                    secD?.bench_members?.length ||
                                                    secA?.annual_turnover_band ||
                                                    secE?.twelve_month_plan ||
                                                    rawF2?.submitted_at
                                                )
                                            );

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
                                                            <div className="col-12">
                                                                <span className="text-dark fw-bold small d-block mb-1">Top 3 Business Achievements</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secA?.achievements || secA?.top_3_achievements || '—'}
                                                                </div>
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
                                                                <span className="text-dark fw-bold small d-block mb-1">Leadership Roles Held</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secB?.leadership_roles || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-dark fw-bold small d-block mb-1">Largest Community / Group Built</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secB?.largest_community || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-dark fw-bold small d-block mb-1">Leaders Developed</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secB?.developed_leaders || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-dark fw-bold small d-block mb-1">Failure as a Leader & Lessons Learned</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 border-start border-warning border-4 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
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
                                                                <span className="text-dark fw-bold small d-block mb-1">Business Associations / Chambers</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secC?.associations || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-dark fw-bold small d-block mb-1">Institutions You Can Access</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secC?.institutions_access || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-4">
                                                                <span className="text-dark fw-bold small d-block mb-1">Potential Sponsors or Partners</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
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
                                                                <span className="text-dark fw-bold small d-block mb-1">Targets (6 & 12 months)</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secE?.targets_6_12_months || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-dark fw-bold small d-block mb-1">First 90 Days Actions</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secE?.first_90_days || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-dark fw-bold small d-block mb-1">How you will fund Year 1</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secE?.funding_plan || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-dark fw-bold small d-block mb-1">Biggest Risk & Handling Strategy</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 border-start border-danger border-4 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
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
                                                                <span className="text-dark fw-bold small d-block mb-1">Why Peers Global?</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
                                                                    {secF?.why_peers_global || '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-12 col-md-6">
                                                                <span className="text-dark fw-bold small d-block mb-1">Role Understanding</span>
                                                                <div className="p-3 rounded-2 bg-white border border-secondary-subtle text-dark fw-medium mt-1 shadow-xs" style={{ whiteSpace: 'pre-line', fontSize: '0.92rem', lineHeight: '1.6' }}>
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
                                    {isSubForm2 ? (
                                        <>
                                            {selectedNomination.profile_snapshot?.form_2_link && (
                                                <button
                                                    onClick={() => handleCopyForm2Link(selectedNomination)}
                                                    className="btn btn-outline-secondary btn-sm rounded-3 px-3 d-flex align-items-center gap-1.5"
                                                >
                                                    {copiedForm2Id === selectedNomination.id ? (
                                                        <>
                                                            <Check size={14} className="text-success" /> Copied Form 2 Link
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Copy size={14} /> Copy Form 2 Link
                                                        </>
                                                    )}
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                className="btn btn-secondary btn-sm rounded-3 px-3"
                                                onClick={() => setSelectedNomination(null)}
                                            >
                                                Close
                                            </button>
                                        </>
                                    ) : (
                                        <>
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
                                            {['submitted', 'resubmitted', 'under_review'].includes(selectedNomination.status) && (
                                                <button
                                                    onClick={() => setActionModal({ type: 'approve', nomination: selectedNomination })}
                                                    className="btn btn-success btn-sm rounded-3 px-3 d-flex align-items-center gap-1.5 fw-semibold"
                                                >
                                                    <UserCheck size={14} /> Approve Candidate
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                className="btn btn-secondary btn-sm rounded-3 px-3"
                                                onClick={() => setSelectedNomination(null)}
                                            >
                                                Close
                                            </button>
                                        </>
                                    )}
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
                                <button
                                    type="button"
                                    className="btn btn-sm btn-light border-0 rounded-circle p-1.5 d-flex align-items-center justify-content-center text-muted"
                                    style={{
                                        background: '#f1f5f9',
                                        border: '1px solid #e2e8f0',
                                        cursor: 'pointer',
                                        padding: '6px',
                                        borderRadius: '50%',
                                        color: '#64748b',
                                    }}
                                    onClick={() => setActionModal(null)}
                                    title="Close"
                                >
                                    <X size={18} />
                                </button>
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
                                    className="btn btn-sm btn-light border-0 rounded-circle p-1.5 d-flex align-items-center justify-content-center text-muted"
                                    style={{
                                        background: '#f1f5f9',
                                        border: '1px solid #e2e8f0',
                                        cursor: 'pointer',
                                        padding: '6px',
                                        borderRadius: '50%',
                                        color: '#64748b',
                                    }}
                                    onClick={() => setProceedJuryModal({ open: false, nominations: [], remarks: '', isLoading: false })}
                                    title="Close"
                                >
                                    <X size={18} />
                                </button>
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
                                                <div className="fw-semibold text-dark">{n.candidate_name && n.candidate_name.toLowerCase() !== 'candidate' ? n.candidate_name : (n.user?.name || 'Candidate')}</div>
                                                <div className="text-muted small">
                                                    {n.email || 'No email'} • {n.applied_role_name || n.campaign?.role?.name || 'Leadership Role'}
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

            {/* Delete Confirmation Modal */}
            <ConfirmModal
                isOpen={deleteModal.open}
                title={
                    deleteModal.type === 'all'
                        ? 'Delete All Nominations'
                        : deleteModal.type === 'selected'
                        ? `Delete ${deleteModal.count || selectedNominationIds.length} Selected Nominations`
                        : `Delete Nomination ${deleteModal.nomination?.application_number}`
                }
                message={
                    deleteModal.type === 'all'
                        ? `Are you sure you want to permanently delete ALL nomination requests (${nominations.length} total) in this view? All candidate applications, form answers, and documents will be permanently erased from the database and table. This action CANNOT be undone.`
                        : deleteModal.type === 'selected'
                        ? `Are you sure you want to permanently delete ${deleteModal.count || selectedNominationIds.length} selected nomination request(s)? They will be permanently removed from the database and table.`
                        : `Are you sure you want to permanently delete application ${deleteModal.nomination?.application_number} (${deleteModal.nomination?.candidate_name || (deleteModal.nomination as any)?.full_name || 'Candidate'})? This will permanently remove it from the database and table.`
                }
                confirmText={deleteModal.isLoading ? 'Deleting...' : 'Delete Permanently'}
                cancelText="Cancel"
                variant="danger"
                isLoading={deleteModal.isLoading}
                onConfirm={handleConfirmDelete}
                onCancel={() => setDeleteModal({ open: false, type: 'selected', isLoading: false })}
            />
        </div>
        </LeadershipLayout>
    );
};
