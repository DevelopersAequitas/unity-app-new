import { LeadershipLayout } from '../../components/LeadershipLayout';
import { utcToLocalDateTimeInput } from '../../../../utils/dateTime';
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
    X,
    Loader2,
    ChevronRight,
    Briefcase,
    Tag,
    Check,
    Layers,
    Mail,
    UserCheck,
    FileText,
    Star,
    Copy,
    ExternalLink,
    Filter,
    RotateCcw,
    AlertCircle,
    ShieldCheck,
    ShieldAlert,
    Building2,
    MapPin,
    Phone
} from 'lucide-react';

const STANDARD_8_CRITERIA = [
    {
        name: 'Leadership of leaders',
        weight: 15,
        category: 'Leadership',
        description: 'Ability to inspire, align, and empower fellow leaders and founders across the region.'
    },
    {
        name: 'Execution capability',
        weight: 15,
        category: 'Execution',
        description: 'Track record of delivering measurable outcomes, programs, and strategic goals.'
    },
    {
        name: 'Leadership Bench strength (quality and status of the named people)',
        weight: 15,
        category: 'Bench Strength',
        description: 'Quality, standing, and commitment of core succession bench and key cabinet members named.'
    },
    {
        name: 'Community-building track record',
        weight: 15,
        category: 'Community',
        description: 'History of giving first, fostering trust, and driving community cohesion without self-serving agenda.'
    },
    {
        name: 'Business credibility',
        weight: 10,
        category: 'Business',
        description: 'Standing in business ecosystem, ethical reputation, financial stability, and peer enterprise respect.'
    },
    {
        name: 'Vision and plan realism',
        weight: 10,
        category: 'Strategy',
        description: 'Clarity, feasibility, and realistic execution roadmap of the build plan and milestone projections.'
    },
    {
        name: 'Commitment and availability',
        weight: 10,
        category: 'Commitment',
        description: 'Pledged weekly time investment, board presence, and willingness to travel and mentor chapter chairs.'
    },
    {
        name: 'Values alignment',
        weight: 10,
        category: 'Values',
        description: 'Adherence to Peers Global core philosophy: Partners in business, friends in life.'
    }
];

const normalizePhotoUrl = (url?: string | null): string | null => {
    if (!url || typeof url !== 'string' || !url.trim()) return null;
    const clean = url.trim();
    if (clean.startsWith('http://') || clean.startsWith('https://')) return clean;
    return `https://peersunity.com/${clean.replace(/^\//, '')}`;
};

export const JuryPage: React.FC = () => {
    const [assignments, setAssignments] = useState<JuryAssignment[]>([]);
    const [criteria, setCriteria] = useState<EvaluationCriterion[]>([]);
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [candidates, setCandidates] = useState<Nomination[]>([]);
    const [members, setMembers] = useState<any[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [activeTab, setActiveTab] = useState<'assignments' | 'criteria'>('assignments');
    const [error, setError] = useState<string | null>(null);

    // Selected Campaign filter
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('all');

    // Roster search and filters
    const [rosterSearch, setRosterSearch] = useState<string>('');
    const [rosterStatusFilter, setRosterStatusFilter] = useState<string>('all');
    const [rosterRoleFilter, setRosterRoleFilter] = useState<string>('');

    // Modal state for Assigning Jury (with Single and Bulk selection for both Candidates and Jurors)
    const [isAssignModal, setIsAssignModal] = useState<boolean>(false);
    const [candidateSearchInModal, setCandidateSearchInModal] = useState<string>('');
    const [selectedCandidateIds, setSelectedCandidateIds] = useState<string[]>([]);
    const [jurorSearchInModal, setJurorSearchInModal] = useState<string>('');
    const [selectedJurorIds, setSelectedJurorIds] = useState<string[]>([]);
    const [isSearchingMembers, setIsSearchingMembers] = useState<boolean>(false);
    const [isSubmittingAssign, setIsSubmittingAssign] = useState<boolean>(false);
    const [assignDeadline, setAssignDeadline] = useState<string>(
        utcToLocalDateTimeInput(new Date(Date.now() + 7 * 86400000))
    );
    const [assignRemarks, setAssignRemarks] = useState<string>('');

    // Modal state for viewing Evaluation Details (Who evaluated, evaluation status, all 8 criteria + dynamic reviews)
    const [selectedItemForReview, setSelectedItemForReview] = useState<any | null>(null);

    // Direct portal link
    const juryPortalUrl = 'https://peersglobal.com/leadership/jury';
    const [copiedJuryLink, setCopiedJuryLink] = useState<string | null>(null);
    const [selectedJuryFilter, setSelectedJuryFilter] = useState<string | null>(null);
    const [isRemovingJuror, setIsRemovingJuror] = useState<string | null>(null);

    const handleRemoveJuryMember = async (jurorUserId: string, jurorName: string) => {
        if (!window.confirm(`Are you sure you want to remove ${jurorName} as Jury? All candidate evaluations assigned to this jury member will be unassigned.`)) {
            return;
        }
        setIsRemovingJuror(jurorUserId);
        try {
            await juryApi.removeMember(jurorUserId);
            alert(`✓ Successfully removed ${jurorName} as Jury.`);
            if (selectedJuryFilter === jurorUserId) setSelectedJuryFilter(null);
            await loadData();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to remove jury member.');
        } finally {
            setIsRemovingJuror(null);
        }
    };

    const copyJuryPortalLink = (identifier: string) => {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(juryPortalUrl).catch(() => {});
        }
        setCopiedJuryLink(identifier);
        setTimeout(() => setCopiedJuryLink(null), 2500);
    };

    const [isResendingEmail, setIsResendingEmail] = useState<string | null>(null);
    const [emailSuccess, setEmailSuccess] = useState<string | null>(null);

    const handleResendEmail = async (jurorUserId: string, assignmentId?: string) => {
        setIsResendingEmail(jurorUserId);
        setEmailSuccess(null);
        try {
            await juryApi.resendEmail({ juror_user_id: jurorUserId, assignment_id: assignmentId });
            setEmailSuccess('Official appointment email with Jury Evaluation Portal link sent successfully!');
            setTimeout(() => setEmailSuccess(null), 5000);
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to send appointment email.');
        } finally {
            setIsResendingEmail(null);
        }
    };

    // Modal state for adding Criterion
    const [isCriterionModal, setIsCriterionModal] = useState<boolean>(false);
    const [criterionData, setCriterionData] = useState({
        name: '',
        category: 'Leadership',
        description: '',
        max_score: 10,
        weightage: 15,
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
                nominationApi.getAll({ per_page: 200 }).catch(() => ({ data: { data: [] } })),
                juryApi.getMembers({ per_page: 300 }).catch(() => ({ data: { data: [] } })),
            ]);

            const rawAssignments = assignRes.data.data || [];
            setAssignments(rawAssignments);
            setCriteria(critRes.data.data || []);
            setCampaigns(campRes.data.data || []);

            const cands = nomRes?.data?.data || [];
            setCandidates(cands);

            const userList = memRes?.data?.data?.data || memRes?.data?.data || [];
            setMembers(userList);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load jury management data.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    // Filter members dynamically when searching
    useEffect(() => {
        if (!jurorSearchInModal.trim()) return;
        const timer = setTimeout(async () => {
            try {
                setIsSearchingMembers(true);
                const res = await juryApi.getMembers({ search: jurorSearchInModal.trim(), per_page: 50 });
                const fetched = res?.data?.data?.data || res?.data?.data || [];
                if (Array.isArray(fetched) && fetched.length > 0) {
                    setMembers((prev) => {
                        const existingIds = new Set(prev.map((p) => p.id));
                        const newPeers = fetched.filter((p: any) => !existingIds.has(p.id));
                        return [...newPeers, ...prev];
                    });
                }
            } catch {
                // Ignore fallback to local
            } finally {
                setIsSearchingMembers(false);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [jurorSearchInModal]);

    // Distinct Appointed Jurors summary
    const assignedJurors = useMemo(() => {
        const jurorMap = new Map<string, { juror: any; total: number; completed: number; assignments: JuryAssignment[] }>();
        assignments.forEach((asg) => {
            const jurorId = asg.juror_user_id || (asg as any).jury_user_id;
            if (!jurorId) return;
            if (!jurorMap.has(jurorId)) {
                jurorMap.set(jurorId, {
                    juror: asg.juror || {
                        id: jurorId,
                        display_name: asg.jury_name || 'Jury Member',
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
    }, [assignments]);

    // Unified Candidates Roster matching Nomination Management table format
    const candidateRosterRows = useMemo(() => {
        // Build map of assignments by nomination_id
        const assignmentsByNominationId = new Map<string, JuryAssignment[]>();
        assignments.forEach((asg) => {
            const nomId = asg.nomination_id || asg.nomination?.id;
            if (nomId) {
                if (!assignmentsByNominationId.has(nomId)) {
                    assignmentsByNominationId.set(nomId, []);
                }
                assignmentsByNominationId.get(nomId)!.push(asg);
            }
        });

        // Filter out Form 2 duplicates (ending in -F2)
        const primaryCandidates = candidates.filter((c) => !c.application_number?.endsWith('-F2'));

        // Transform into unified rows
        let rows = primaryCandidates.map((cand) => {
            const candAssignments = assignmentsByNominationId.get(cand.id) || [];
            // If any assignment is completed, row is marked evaluated
            const completedAsg = candAssignments.find((a) => a.status === 'completed');
            const primaryAsg = completedAsg || candAssignments[0] || null;

            let evalStatus: 'evaluated' | 'pending' | 'unassigned' = 'unassigned';
            if (completedAsg) {
                evalStatus = 'evaluated';
            } else if (candAssignments.length > 0) {
                evalStatus = 'pending';
            }

            const snap = cand.profile_snapshot || {};
            const asgWithNom = candAssignments.find((a: any) => a.nomination);
            const asgNom = asgWithNom?.nomination;
            const asgSnap = (asgNom?.profile_snapshot || {}) as any;

            const candidateName = cand.candidate_name && cand.candidate_name.toLowerCase() !== 'candidate'
                ? cand.candidate_name
                : (snap.full_name || cand.user?.name || asgNom?.candidate_name || asgNom?.user?.name || 'Nominee');

            const appliedRole = cand.applied_role_name || cand.applied_role || cand.campaign?.role?.name || snap.applied_role || 'Leadership Role';
            const city = cand.city || snap.city || cand.scope?.city || cand.scope?.name || 'Ahmedabad';
            const email = cand.email || snap.email || cand.user?.email || asgNom?.email || '';
            const mobile = cand.mobile || snap.mobile || cand.user?.phone_number || asgNom?.mobile || '';
            const company = cand.company || snap.company_name || cand.user?.company_name || asgNom?.company || '';
            const photoUrl = normalizePhotoUrl(
                cand.profile_photo_url ||
                cand.photo_url ||
                cand.avatar_url ||
                (cand as any).image_url ||
                snap.photo_url ||
                snap.profile_photo_url ||
                snap.avatar_url ||
                snap.image_url ||
                cand.user?.profile_photo_url ||
                cand.user?.avatar_url ||
                asgNom?.profile_photo_url ||
                asgNom?.photo_url ||
                asgNom?.avatar_url ||
                asgSnap.profile_photo_url ||
                asgSnap.photo_url ||
                asgNom?.user?.profile_photo_url ||
                asgNom?.user?.avatar_url ||
                (cand as any).candidate_photo_url ||
                cand.creative_url ||
                null
            );

            return {
                id: cand.id,
                nomination: cand,
                candidate_name: candidateName,
                application_number: cand.application_number || 'PG-2026',
                applied_role: appliedRole,
                city: city,
                email: email,
                mobile: mobile,
                company: company,
                photo_url: photoUrl,
                assignments: candAssignments,
                primary_assignment: primaryAsg,
                evaluation_status: evalStatus,
                has_form_2: !!cand.form_2_data || !!snap.form_2 || snap.form_2_status === 'submitted',
                deadline: primaryAsg?.deadline || '',
                campaign_id: cand.campaign_id,
            };
        });

        // Apply Search Filter
        if (rosterSearch.trim()) {
            const q = rosterSearch.toLowerCase().trim();
            rows = rows.filter((r) =>
                r.candidate_name.toLowerCase().includes(q) ||
                r.application_number.toLowerCase().includes(q) ||
                r.applied_role.toLowerCase().includes(q) ||
                r.city.toLowerCase().includes(q) ||
                r.email.toLowerCase().includes(q)
            );
        }

        // Apply Status Filter
        if (rosterStatusFilter !== 'all') {
            rows = rows.filter((r) => r.evaluation_status === rosterStatusFilter);
        }

        // Apply Role Filter
        if (rosterRoleFilter) {
            rows = rows.filter((r) => r.applied_role.toLowerCase().includes(rosterRoleFilter.toLowerCase()));
        }

        // Apply Campaign Filter
        if (selectedCampaignId !== 'all') {
            rows = rows.filter((r) => r.campaign_id === selectedCampaignId);
        }

        // Apply Selected Jury / Quick Filter
        if (selectedJuryFilter) {
            if (selectedJuryFilter === 'unassigned') {
                rows = rows.filter((r) => r.evaluation_status === 'unassigned');
            } else if (selectedJuryFilter === 'pending') {
                rows = rows.filter((r) => r.evaluation_status === 'pending');
            } else if (selectedJuryFilter === 'evaluated') {
                rows = rows.filter((r) => r.evaluation_status === 'evaluated');
            } else {
                // Filter by specific appointed juror user ID
                rows = rows.filter((r) =>
                    r.assignments.some(
                        (a) => a.juror_user_id === selectedJuryFilter || (a as any).jury_user_id === selectedJuryFilter
                    )
                );
            }
        }

        return rows;
    }, [candidates, assignments, rosterSearch, rosterStatusFilter, rosterRoleFilter, selectedCampaignId, selectedJuryFilter]);

    // Filtered candidate list for Assign modal
    const eligibleModalCandidates = useMemo(() => {
        let list = candidates.filter((c) => !c.application_number?.endsWith('-F2'));
        if (selectedCampaignId !== 'all') {
            list = list.filter((c) => c.campaign_id === selectedCampaignId);
        }
        if (candidateSearchInModal.trim()) {
            const q = candidateSearchInModal.toLowerCase().trim();
            list = list.filter((c) => {
                const name = (c.candidate_name || c.full_name || (c.profile_snapshot as any)?.full_name || '').toLowerCase();
                const appNo = (c.application_number || '').toLowerCase();
                const role = (c.applied_role_name || c.campaign?.role?.name || '').toLowerCase();
                const city = (c.city || (c.profile_snapshot as any)?.city || '').toLowerCase();
                return name.includes(q) || appNo.includes(q) || role.includes(q) || city.includes(q);
            });
        }
        return list;
    }, [candidates, selectedCampaignId, candidateSearchInModal]);

    // Filtered peers list for Assign modal
    const eligibleModalJurors = useMemo(() => {
        if (!jurorSearchInModal.trim()) return members;
        const q = jurorSearchInModal.toLowerCase().trim();
        return members.filter((m) => {
            const name = (m.display_name || `${m.first_name || ''} ${m.last_name || ''}`).toLowerCase();
            const email = (m.email || '').toLowerCase();
            const comp = typeof m.business_name === 'object' && m.business_name !== null
                ? String(m.business_name.name || '').toLowerCase()
                : String(m.company_name || m.business_name || '').toLowerCase();
            return name.includes(q) || email.includes(q) || comp.includes(q);
        });
    }, [members, jurorSearchInModal]);

    // Multi-candidate toggle
    const toggleCandidateSelection = (nomId: string) => {
        setSelectedCandidateIds((prev) =>
            prev.includes(nomId) ? prev.filter((id) => id !== nomId) : [...prev, nomId]
        );
    };

    const selectAllCandidatesInModal = () => {
        const allIds = eligibleModalCandidates.map((c) => c.id);
        setSelectedCandidateIds(allIds);
    };

    const clearCandidateSelectionInModal = () => {
        setSelectedCandidateIds([]);
    };

    // Multi-juror toggle
    const toggleJurorSelection = (userId: string) => {
        setSelectedJurorIds((prev) =>
            prev.includes(userId) ? prev.filter((id) => id !== userId) : [...prev, userId]
        );
    };

    const selectAllJurorsInModal = () => {
        const allIds = eligibleModalJurors.map((m) => m.id);
        setSelectedJurorIds(allIds);
    };

    const clearJurorSelectionInModal = () => {
        setSelectedJurorIds([]);
    };

    // Open Assign modal with optional preselected candidate
    const handleOpenAssignModal = (preselectedNomId?: string) => {
        if (preselectedNomId) {
            setSelectedCandidateIds([preselectedNomId]);
        } else {
            setSelectedCandidateIds([]);
        }
        setSelectedJurorIds([]);
        setCandidateSearchInModal('');
        setJurorSearchInModal('');
        setIsAssignModal(true);
    };

    // Handle Assign Form Submit
    const handleAssignSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (selectedCandidateIds.length === 0) {
            alert('Please select at least one candidate nomination request to assign.');
            return;
        }

        if (selectedJurorIds.length === 0) {
            alert('Please select at least one jury peer to appoint.');
            return;
        }

        setIsSubmittingAssign(true);
        try {
            const activeCampaignId = selectedCampaignId !== 'all' ? selectedCampaignId : (campaigns[0]?.id || undefined);
            await juryApi.assign({
                campaign_id: activeCampaignId,
                nomination_ids: selectedCandidateIds,
                juror_user_ids: selectedJurorIds,
                deadline: assignDeadline,
                remarks: assignRemarks,
            });

            setIsAssignModal(false);
            setSelectedCandidateIds([]);
            setSelectedJurorIds([]);
            alert(
                `✓ Successfully appointed ${selectedJurorIds.length} jury member(s) to evaluate ${selectedCandidateIds.length} candidate nomination(s)! Official login portal invitations have been sent.`
            );
            loadData();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to appoint jury members.');
        } finally {
            setIsSubmittingAssign(false);
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
        <LeadershipLayout>
            <div className="container-fluid px-0 leadership-module">
                {/* Header Toolbar */}
            <div className="d-flex justify-content-end align-items-center gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div className="d-flex gap-2">
                        <button
                            onClick={() => setIsCriterionModal(true)}
                            className="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 fw-semibold"
                        >
                            <Scale size={14} /> Add Criterion
                        </button>
                        <button
                            onClick={() => handleOpenAssignModal()}
                            className="btn btn-primary btn-sm rounded-3 px-3.5 py-2 d-flex align-items-center gap-1.5 fw-semibold shadow-xs"
                        >
                            <UserCheck size={15} /> Appoint Jury
                        </button>
            </div>
                </div>

                {emailSuccess && (
                    <div className="alert alert-success rounded-4 border-0 shadow-xs p-3.5 mb-4 d-flex align-items-center justify-content-between">
                        <div className="d-flex align-items-center gap-2.5">
                            <CheckCircle2 size={18} className="text-success flex-shrink-0" />
                            <span className="fw-medium text-dark">{emailSuccess}</span>
                        </div>
                        <button className="btn btn-sm btn-outline-success" onClick={() => setEmailSuccess(null)}>
                            Dismiss
                        </button>
                    </div>
                )}

                {error && (
                    <div className="alert alert-danger rounded-4 border-0 shadow-xs p-3.5 mb-4 d-flex align-items-center justify-content-between">
                        <div>{error}</div>
                        <button className="btn btn-sm btn-outline-danger" onClick={() => setError(null)}>
                            Dismiss
                        </button>
                    </div>
                )}

                {/* Top Section Tabs */}
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
                            <Layers size={15} /> Candidate Evaluations Roster ({candidateRosterRows.length})
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
                            <Scale size={15} /> Evaluation Criteria & Weights ({criteria.length})
                        </button>
                    </div>
                </div>

                {activeTab === 'assignments' ? (
                    <div>
                        {/* APPOINTED JURIES SUMMARY ROW */}
                        <div className="card border-0 shadow-xs rounded-4 bg-white p-4 mb-4">
                            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                                <div>
                                    <h5 className="fw-bold text-dark mb-0 fs-6">
                                        Appointed Juries ({assignedJurors.length})
                                    </h5>
                                    <span className="text-muted small" style={{ fontSize: '12px' }}>
                                        Distinguished peer members appointed from users table to evaluate candidate dossiers
                                    </span>
                                </div>
                                <button
                                    onClick={() => handleOpenAssignModal()}
                                    className="btn btn-outline-primary btn-sm rounded-3 px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5"
                                >
                                    <Plus size={13} /> Appoint Jury Peers
                                </button>
                            </div>

                            {assignedJurors.length === 0 ? (
                                <div className="p-4 bg-light rounded-4 text-center border">
                                    <p className="text-muted small mb-2">No juries appointed yet.</p>
                                    <button
                                        onClick={() => handleOpenAssignModal()}
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

                                        const isSelected = selectedJuryFilter === u.id;
                                        const jurorPhotoUrl = u.profile_photo_url || u.photo_url || u.avatar_url || u.avatar || (item as any).juror_profile_photo_url || (item as any).juror_photo_url || null;

                                        return (
                                            <div key={u.id || idx} className="col-12 col-md-6 col-xl-4">
                                                <div
                                                    onClick={() => setSelectedJuryFilter(isSelected ? null : u.id)}
                                                    className={`card h-100 rounded-4 border bg-white p-3.5 shadow-xs transition-all ${
                                                        isSelected ? 'border-primary ring-2 shadow-sm' : ''
                                                    }`}
                                                    style={{ cursor: 'pointer', borderColor: isSelected ? 'var(--bs-primary)' : undefined, borderWidth: isSelected ? '2px' : '1px' }}
                                                    title={isSelected ? 'Click to show all candidates' : `Click to filter candidates assigned to ${jurorName}`}
                                                >
                                                    <div className="d-flex align-items-start gap-2.5 mb-2.5">
                                                        {jurorPhotoUrl ? (
                                                            <img
                                                                src={jurorPhotoUrl}
                                                                alt={jurorName}
                                                                className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                                style={{ width: '40px', height: '40px' }}
                                                                onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                            />
                                                        ) : (
                                                            <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style={{ width: '40px', height: '40px', fontSize: '14px' }}>
                                                                {jurorName.charAt(0).toUpperCase()}
                                                            </div>
                                                        )}
                                                        <div className="overflow-hidden flex-grow-1">
                                                            <div className="d-flex align-items-center justify-content-between">
                                                                <h6 className="fw-bold text-dark mb-0 text-truncate">{jurorName}</h6>
                                                                <div className="d-flex align-items-center gap-1">
                                                                    {isSelected && (
                                                                        <span className="badge bg-primary text-white rounded-pill px-2 py-0.5" style={{ fontSize: '10px' }}>
                                                                            Filter Active
                                                                        </span>
                                                                    )}
                                                                    <span className="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style={{ fontSize: '10.5px' }}>
                                                                        Active
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div className="small text-muted text-truncate" style={{ fontSize: '11.5px' }}>{u.email || ''}</div>
                                                        </div>
                                                    </div>

                                                    <div className="p-2 bg-light rounded-3 mb-2.5 small" style={{ fontSize: '11.5px' }}>
                                                        <div className="text-dark fw-medium text-truncate">{busName}</div>
                                                        <div className="text-muted text-truncate">{busCat}</div>
                                                    </div>

                                                    <div className="mb-2.5">
                                                        <div className="d-flex justify-content-between align-items-center small text-muted mb-1" style={{ fontSize: '11px' }}>
                                                            <span>Evaluations Completed</span>
                                                            <span className="fw-bold text-dark">{item.completed} / {item.total} ({pct}%)</span>
                                                        </div>
                                                        <div className="progress" style={{ height: '5px' }}>
                                                            <div className="progress-bar bg-success" role="progressbar" style={{ width: `${pct}%` }} />
                                                        </div>
                                                    </div>

                                                    <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top mt-auto">
                                                        <div className="d-flex align-items-center gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    copyJuryPortalLink(u.id);
                                                                }}
                                                                className="btn btn-link text-decoration-none p-0 text-primary small d-flex align-items-center gap-1 fw-semibold"
                                                                style={{ fontSize: '11.5px' }}
                                                            >
                                                                {copiedJuryLink === u.id ? <Check size={11} className="text-success" /> : <Copy size={11} />}
                                                                <span>{copiedJuryLink === u.id ? 'Copied' : 'Copy Link'}</span>
                                                            </button>
                                                            <a
                                                                href={juryPortalUrl}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                onClick={(e) => e.stopPropagation()}
                                                                className="btn btn-link text-decoration-none p-0 text-secondary small d-flex align-items-center gap-1"
                                                                style={{ fontSize: '11.5px' }}
                                                            >
                                                                <ExternalLink size={11} />
                                                                <span>Open Portal</span>
                                                            </a>
                                                        </div>
                                                        <div className="d-flex align-items-center gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    handleResendEmail(u.id);
                                                                }}
                                                                disabled={isResendingEmail === u.id}
                                                                className="btn btn-link text-decoration-none p-0 text-muted small d-flex align-items-center gap-1"
                                                                style={{ fontSize: '11.5px' }}
                                                            >
                                                                <Mail size={11} className={isResendingEmail === u.id ? 'spinner-border spinner-border-sm' : ''} />
                                                                <span>{isResendingEmail === u.id ? 'Sending...' : 'Email'}</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    handleRemoveJuryMember(u.id, jurorName);
                                                                }}
                                                                disabled={isRemovingJuror === u.id}
                                                                className="btn btn-link text-decoration-none p-0 text-danger small d-flex align-items-center gap-1"
                                                                style={{ fontSize: '11.5px' }}
                                                                title="Remove as Jury"
                                                            >
                                                                <Trash2 size={11} className={isRemovingJuror === u.id ? 'spinner-border spinner-border-sm' : ''} />
                                                                <span>{isRemovingJuror === u.id ? 'Removing...' : 'Remove'}</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* CANDIDATE EVALUATIONS ROSTER (EXACT NOMINATIONS TABLE LAYOUT) */}
                        <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                            {/* Filter Chips Bar */}
                            <div className="d-flex flex-wrap align-items-center gap-2 mb-3 pb-3 border-bottom">
                                <span className="text-muted small fw-semibold me-1 d-flex align-items-center gap-1">
                                    <Filter size={13} /> Filter:
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setSelectedJuryFilter(null)}
                                    className={`btn btn-sm rounded-pill px-3 py-1 fw-medium ${
                                        selectedJuryFilter === null
                                            ? 'btn-dark text-white shadow-xs'
                                            : 'btn-outline-secondary bg-white text-dark'
                                    }`}
                                    style={{ fontSize: '12px' }}
                                >
                                    All Candidates ({candidates.filter((c) => !c.application_number?.endsWith('-F2')).length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setSelectedJuryFilter(selectedJuryFilter === 'unassigned' ? null : 'unassigned')}
                                    className={`btn btn-sm rounded-pill px-3 py-1 fw-medium ${
                                        selectedJuryFilter === 'unassigned'
                                            ? 'btn-danger text-white shadow-xs'
                                            : 'btn-outline-danger bg-white'
                                    }`}
                                    style={{ fontSize: '12px' }}
                                >
                                    Pending Jury Assignment
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setSelectedJuryFilter(selectedJuryFilter === 'pending' ? null : 'pending')}
                                    className={`btn btn-sm rounded-pill px-3 py-1 fw-medium ${
                                        selectedJuryFilter === 'pending'
                                            ? 'btn-warning text-dark shadow-xs'
                                            : 'btn-outline-warning bg-white text-dark'
                                    }`}
                                    style={{ fontSize: '12px' }}
                                >
                                    Pending Evaluation
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setSelectedJuryFilter(selectedJuryFilter === 'evaluated' ? null : 'evaluated')}
                                    className={`btn btn-sm rounded-pill px-3 py-1 fw-medium ${
                                        selectedJuryFilter === 'evaluated'
                                            ? 'btn-success text-white shadow-xs'
                                            : 'btn-outline-success bg-white'
                                    }`}
                                    style={{ fontSize: '12px' }}
                                >
                                    Evaluated
                                </button>

                                {selectedJuryFilter && !['unassigned', 'pending', 'evaluated'].includes(selectedJuryFilter) && (
                                    <span className="badge bg-primary text-white rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-1.5 ms-auto">
                                        <span>
                                            Filtered by Jury: {assignedJurors.find((j) => (j.juror?.id === selectedJuryFilter || (j.juror as any)?.user_id === selectedJuryFilter))?.juror?.display_name || 'Selected Jury'}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => setSelectedJuryFilter(null)}
                                            className="btn btn-link text-white p-0 border-0 ms-1"
                                            style={{ fontSize: '11px', lineHeight: 1 }}
                                            title="Clear filter"
                                        >
                                            <X size={12} />
                                        </button>
                                    </span>
                                )}
                            </div>
                            {/* Search and Filters toolbar */}
                            <div className="row g-2.5 mb-3 pb-3 border-bottom align-items-center">
                                <div className="col-12 col-md-5">
                                    <div className="input-group input-group-sm">
                                        <span className="input-group-text bg-light border-end-0 text-muted">
                                            <Search size={14} />
                                        </span>
                                        <input
                                            type="text"
                                            className="form-control border-start-0"
                                            placeholder="Search candidate name, application #, role, city..."
                                            value={rosterSearch}
                                            onChange={(e) => setRosterSearch(e.target.value)}
                                        />
                                    </div>
                                </div>
                                <div className="col-6 col-md-3">
                                    <select
                                        className="form-select form-select-sm"
                                        value={rosterStatusFilter}
                                        onChange={(e) => setRosterStatusFilter(e.target.value)}
                                    >
                                        <option value="all">All Evaluation Statuses</option>
                                        <option value="evaluated">✓ Evaluated (Report Submitted)</option>
                                        <option value="pending">Pending Evaluation</option>
                                        <option value="unassigned">Unassigned (Needs Juror)</option>
                                    </select>
                                </div>
                                <div className="col-6 col-md-3">
                                    <select
                                        className="form-select form-select-sm"
                                        value={selectedCampaignId}
                                        onChange={(e) => setSelectedCampaignId(e.target.value)}
                                    >
                                        <option value="all">All Campaigns</option>
                                        {campaigns.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="col-12 col-md-1 d-flex justify-content-end">
                                    {(rosterSearch || rosterStatusFilter !== 'all' || selectedCampaignId !== 'all') && (
                                        <button
                                            type="button"
                                            className="btn btn-outline-secondary btn-sm"
                                            onClick={() => {
                                                setRosterSearch('');
                                                setRosterStatusFilter('all');
                                                setSelectedCampaignId('all');
                                            }}
                                            title="Reset filters"
                                        >
                                            <RotateCcw size={13} />
                                        </button>
                                    )}
                                </div>
                            </div>

                            {/* Table */}
                            <div className="table-responsive rounded-4 border overflow-hidden">
                                <table className="table table-hover align-middle mb-0" style={{ minWidth: '950px' }}>
                                    <thead className="table-light border-bottom">
                                        <tr className="text-secondary small">
                                            <th style={{ width: '135px' }} className="ps-3 py-3">APP #</th>
                                            <th style={{ minWidth: '220px' }} className="py-3">CANDIDATE</th>
                                            <th style={{ minWidth: '180px' }} className="py-3">APPLIED ROLE</th>
                                            <th style={{ minWidth: '170px' }} className="py-3">BELONGING / JURISDICTION</th>
                                            <th style={{ minWidth: '200px' }} className="py-3">ASSIGNED JURY</th>
                                            <th style={{ width: '135px' }} className="py-3">STATUS</th>
                                            <th style={{ width: '130px' }} className="py-3">DEADLINE</th>
                                            <th style={{ width: '150px' }} className="text-end py-3 pe-3">ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {isLoading ? (
                                            <tr>
                                                <td colSpan={8} className="text-center py-5 text-muted">
                                                    <div className="spinner-border spinner-border-sm text-primary me-2" />
                                                    Loading candidate evaluations roster...
                                                </td>
                                            </tr>
                                        ) : candidateRosterRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="text-center py-5 text-muted">
                                                    No candidates match the filter criteria.
                                                </td>
                                            </tr>
                                        ) : (
                                            candidateRosterRows.map((row) => {
                                                const isEvaluated = row.evaluation_status === 'evaluated';
                                                const isPending = row.evaluation_status === 'pending';
                                                const primaryAsg = row.primary_assignment;

                                                const jurorName = primaryAsg
                                                    ? (primaryAsg.jury_name || primaryAsg.juror?.display_name || `${primaryAsg.juror?.first_name || ''} ${primaryAsg.juror?.last_name || ''}`.trim() || 'Assigned Jury Member')
                                                    : 'Unassigned';

                                                const jurorEmail = primaryAsg?.jury_email || primaryAsg?.juror?.email;

                                                return (
                                                    <tr
                                                        key={row.id}
                                                        onClick={() => setSelectedItemForReview(row)}
                                                        style={{ cursor: 'pointer' }}
                                                        className="hover-bg-light transition-colors"
                                                        title="Click to view full jury evaluation, scores, and candidate dossier"
                                                    >
                                                        <td className="ps-3 py-3">
                                                            <span className="font-monospace fw-bold text-primary small">
                                                                {row.application_number}
                                                            </span>
                                                        </td>
                                                        <td className="py-3">
                                                            <div className="d-flex align-items-center gap-2.5">
                                                                {row.photo_url ? (
                                                                    <img
                                                                        src={row.photo_url}
                                                                        alt={row.candidate_name}
                                                                        className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                                        style={{ width: 34, height: 34 }}
                                                                        onError={(e) => {
                                                                            const target = e.currentTarget as HTMLElement;
                                                                            target.style.display = 'none';
                                                                            const parent = target.parentElement;
                                                                            if (parent && !parent.querySelector('.roster-fallback-avatar')) {
                                                                                const fb = document.createElement('div');
                                                                                fb.className = 'roster-fallback-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-xs flex-shrink-0';
                                                                                fb.style.width = '34px';
                                                                                fb.style.height = '34px';
                                                                                fb.style.fontSize = '13px';
                                                                                fb.textContent = (row.candidate_name.charAt(0) || 'C').toUpperCase();
                                                                                parent.insertBefore(fb, target);
                                                                            }
                                                                        }}
                                                                    />
                                                                ) : (
                                                                    <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-xs flex-shrink-0" style={{ width: 34, height: 34, fontSize: 13 }}>
                                                                        {(row.candidate_name.charAt(0) || 'C').toUpperCase()}
                                                                    </div>
                                                                )}
                                                                <div className="overflow-hidden">
                                                                    <div className="fw-bold text-dark text-truncate lh-sm">{row.candidate_name}</div>
                                                                    <div className="text-muted small text-truncate" style={{ fontSize: '11px' }}>
                                                                        {row.email || row.mobile}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="py-3">
                                                            <div className="fw-semibold text-dark small lh-sm">
                                                                {row.applied_role}
                                                            </div>
                                                            {row.has_form_2 ? (
                                                                <span className="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 mt-1" style={{ fontSize: '10px' }}>
                                                                    ✓ Form 2 Submitted
                                                                </span>
                                                            ) : (
                                                                <span className="badge bg-light text-muted border rounded-pill px-2 py-0.5 mt-1" style={{ fontSize: '10px' }}>
                                                                    Form 2 Pending
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="py-3">
                                                            <div className="d-flex align-items-center gap-1 small text-dark fw-medium">
                                                                <MapPin size={12} className="text-muted flex-shrink-0" />
                                                                <span>{row.city}</span>
                                                            </div>
                                                            {row.company && (
                                                                <div className="text-muted small text-truncate" style={{ fontSize: '11px', maxWidth: '160px' }}>
                                                                    {row.company}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="py-3">
                                                            {row.assignments.length > 0 ? (
                                                                <div className="d-flex align-items-center gap-2">
                                                                    {(() => {
                                                                        const jPhoto = primaryAsg?.juror?.profile_photo_url || primaryAsg?.juror?.photo_url || (primaryAsg as any)?.juror_profile_photo_url || null;
                                                                        return jPhoto ? (
                                                                            <img
                                                                                src={jPhoto}
                                                                                alt={jurorName}
                                                                                className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                                                style={{ width: 28, height: 28 }}
                                                                                onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                            />
                                                                        ) : null;
                                                                    })()}
                                                                    <div className="overflow-hidden">
                                                                        <div className="fw-semibold text-dark small text-truncate">
                                                                            {jurorName}
                                                                            {row.assignments.length > 1 && (
                                                                                <span className="badge bg-light text-primary border ms-1" style={{ fontSize: '10px' }}>
                                                                                    +{row.assignments.length - 1} more
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                        {jurorEmail && (
                                                                            <div className="text-muted small font-monospace text-truncate" style={{ fontSize: '10.5px' }}>
                                                                                {jurorEmail}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <span className="badge bg-light text-muted border rounded-pill px-2.5 py-1 small">
                                                                    Unassigned
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="py-3">
                                                            {isEvaluated ? (
                                                                <span className="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill d-inline-flex align-items-center gap-1 fw-bold">
                                                                    <CheckCircle size={12} /> ✓ Evaluated
                                                                </span>
                                                            ) : isPending ? (
                                                                <span className="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1.5 rounded-pill d-inline-flex align-items-center gap-1 fw-semibold text-dark">
                                                                    <Clock size={12} /> Pending Review
                                                                </span>
                                                            ) : (
                                                                <span className="badge bg-light text-secondary border px-2 py-1 rounded-pill small">
                                                                    Unassigned
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="py-3 small text-muted">
                                                            {row.deadline ? new Date(row.deadline).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : 'No deadline'}
                                                        </td>
                                                        <td className="text-end py-3 pe-3">
                                                            <div className="d-flex align-items-center justify-content-end gap-1.5" onClick={(e) => e.stopPropagation()}>
                                                                {isEvaluated ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setSelectedItemForReview(row)}
                                                                        className="btn btn-sm btn-outline-success rounded-3 px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1"
                                                                        style={{ fontSize: '11.5px' }}
                                                                    >
                                                                        <Eye size={12} /> View Review
                                                                    </button>
                                                                ) : row.assignments.length > 0 ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setSelectedItemForReview(row)}
                                                                        className="btn btn-sm btn-light border rounded-3 px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1"
                                                                        style={{ fontSize: '11.5px' }}
                                                                    >
                                                                        <Eye size={12} /> Status
                                                                    </button>
                                                                ) : (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => handleOpenAssignModal(row.id)}
                                                                        className="btn btn-sm btn-primary rounded-3 px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs"
                                                                        style={{ fontSize: '11.5px' }}
                                                                    >
                                                                        <Plus size={12} /> Assign
                                                                    </button>
                                                                )}
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
                    /* CRITERIA TAB */
                    <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                        <div className="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                            <div>
                                <h5 className="fw-bold text-dark mb-0 fs-6">
                                    Official Leadership Evaluation Criteria ({criteria.length})
                                </h5>
                                <span className="text-muted small" style={{ fontSize: '12px' }}>
                                    Standard evaluation criteria auto-seeded per election governance rules + dynamic criteria added by jury
                                </span>
                            </div>
                            <button
                                onClick={() => setIsCriterionModal(true)}
                                className="btn btn-primary btn-sm rounded-3 px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs"
                            >
                                <Plus size={13} /> Add Criterion
                            </button>
                        </div>

                        <div className="table-responsive rounded-4 border overflow-hidden">
                            <table className="table table-hover align-middle mb-0">
                                <thead className="table-light">
                                    <tr className="small text-secondary">
                                        <th className="ps-3 py-3">Criterion Name</th>
                                        <th className="py-3">Category</th>
                                        <th className="py-3">Max Score</th>
                                        <th className="py-3">Weightage (%)</th>
                                        <th className="py-3">Description</th>
                                        <th className="text-end pe-3 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {criteria.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-4 text-muted">
                                                No evaluation criteria configured yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        criteria.map((crit) => (
                                            <tr key={crit.id}>
                                                <td className="ps-3 fw-semibold text-dark">{crit.name}</td>
                                                <td>
                                                    <span className="badge bg-light text-dark border">{crit.category || 'General'}</span>
                                                </td>
                                                <td className="fw-bold">{crit.max_score} pts</td>
                                                <td>
                                                    <span className="badge bg-primary-subtle text-primary">{crit.weightage}%</span>
                                                </td>
                                                <td className="small text-muted" style={{ maxWidth: '300px' }}>
                                                    {crit.description || 'Candidate evaluation metric'}
                                                </td>
                                                <td className="text-end pe-3">
                                                    <button
                                                        onClick={() => handleDeleteCriterion(crit.id)}
                                                        className="btn btn-light bg-white border text-danger btn-sm rounded-2 py-1 px-2 d-inline-flex align-items-center gap-1 shadow-xs"
                                                        style={{ fontSize: '0.78rem' }}
                                                        title="Delete criterion"
                                                    >
                                                        <Trash2 size={13} />
                                                        <span>Delete</span>
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

                {/* =========================================================================
                    ASSIGN JURY MODAL (SINGLE & BULK SELECTION FOR BOTH CANDIDATES & JURORS)
                   ========================================================================= */}
                {isAssignModal && (
                    <div
                        className="modal show"
                        tabIndex={-1}
                        style={{
                            position: 'fixed',
                            inset: 0,
                            backgroundColor: 'rgba(15, 23, 42, 0.75)',
                            backdropFilter: 'blur(4px)',
                            zIndex: 1055,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            padding: '16px',
                            overflowY: 'auto'
                        }}
                        onClick={() => setIsAssignModal(false)}
                    >
                        <div
                            className="modal-dialog modal-xl modal-dialog-centered"
                            style={{
                                width: '100%',
                                maxWidth: '1000px',
                                margin: 'auto',
                                maxHeight: '92vh',
                                display: 'flex',
                                flexDirection: 'column'
                            }}
                            onClick={(e) => e.stopPropagation()}
                        >
                            <div
                                className="modal-content border-0 shadow-2xl rounded-4"
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    maxHeight: '92vh',
                                    overflow: 'hidden',
                                    borderRadius: '16px',
                                    backgroundColor: '#ffffff',
                                    border: '1px solid #e2e8f0'
                                }}
                            >
                                <form onSubmit={handleAssignSubmit} style={{ display: 'flex', flexDirection: 'column', height: '100%', overflow: 'hidden' }}>
                                    {/* Modal Header */}
                                    <div className="modal-header border-bottom px-4 py-3 bg-light d-flex align-items-center justify-content-between flex-shrink-0">
                                        <div>
                                            <h5 className="modal-title fw-bold text-dark mb-0 fs-6">
                                                Appoint Jury to Evaluate Nominations
                                            </h5>
                                            <span className="text-muted small" style={{ fontSize: '12px' }}>
                                                Select candidates to evaluate and jury peers to appoint. Dedicated portal access links will be dispatched via email.
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-light border-0 rounded-circle p-1.5 text-muted"
                                            onClick={() => setIsAssignModal(false)}
                                            title="Close"
                                        >
                                            <X size={18} />
                                        </button>
                                    </div>

                                    {/* Modal Body - 2 Columns: Candidate Selection & Juror Selection */}
                                    <div className="modal-body p-4" style={{ overflowY: 'auto', flex: '1 1 auto' }}>
                                        <div className="row g-4 mb-4">
                                            {/* COLUMN 1: CANDIDATE SELECTION */}
                                            <div className="col-12 col-lg-6">
                                                <div className="p-3.5 border rounded-4 bg-light-subtle h-100 d-flex flex-column">
                                                    <div className="d-flex align-items-center justify-content-between mb-2">
                                                        <label className="form-label small fw-bold text-dark mb-0 d-flex align-items-center gap-1.5">
                                                            <Users size={14} className="text-primary" />
                                                            1. Select Candidate Requests *
                                                        </label>
                                                        <span className="badge bg-primary text-white rounded-pill px-2.5 py-0.5" style={{ fontSize: '11px' }}>
                                                            {selectedCandidateIds.length} Selected
                                                        </span>
                                                    </div>

                                                    <div className="d-flex align-items-center justify-content-between mb-2 small">
                                                        <div className="text-muted" style={{ fontSize: '11.5px' }}>
                                                            Check individual or select all candidates
                                                        </div>
                                                        <div className="d-flex gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={selectAllCandidatesInModal}
                                                                className="btn btn-link text-decoration-none p-0 text-primary fw-semibold small"
                                                                style={{ fontSize: '11.5px' }}
                                                            >
                                                                Select All ({eligibleModalCandidates.length})
                                                            </button>
                                                            <span className="text-muted">•</span>
                                                            <button
                                                                type="button"
                                                                onClick={clearCandidateSelectionInModal}
                                                                className="btn btn-link text-decoration-none p-0 text-muted small"
                                                                style={{ fontSize: '11.5px' }}
                                                            >
                                                                Clear
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {/* Candidate Search Bar */}
                                                    <div className="input-group input-group-sm mb-2.5">
                                                        <span className="input-group-text bg-white border-end-0 text-muted">
                                                            <Search size={12} />
                                                        </span>
                                                        <input
                                                            type="text"
                                                            className="form-control border-start-0"
                                                            placeholder="Filter candidates..."
                                                            value={candidateSearchInModal}
                                                            onChange={(e) => setCandidateSearchInModal(e.target.value)}
                                                        />
                                                    </div>

                                                    {/* Candidate List (Scrollable) */}
                                                    <div className="border rounded-3 bg-white p-2 flex-grow-1" style={{ maxHeight: '250px', overflowY: 'auto' }}>
                                                        {eligibleModalCandidates.length === 0 ? (
                                                            <div className="text-center py-4 text-muted small">
                                                                No matching candidates found.
                                                            </div>
                                                        ) : (
                                                            eligibleModalCandidates.map((c) => {
                                                                const isChecked = selectedCandidateIds.includes(c.id);
                                                                const candName = c.candidate_name || c.full_name || (c.profile_snapshot as any)?.full_name || 'Nominee';
                                                                const appNo = c.application_number || 'PG-2026';
                                                                const roleName = c.applied_role_name || c.campaign?.role?.name || 'Nominee';
                                                                const city = c.city || (c.profile_snapshot as any)?.city || '';
                                                                const candPhoto = c.profile_photo_url || c.photo_url || c.avatar_url || (c.profile_snapshot as any)?.photo_url || (c.profile_snapshot as any)?.profile_photo_url || c.user?.profile_photo_url || null;

                                                                return (
                                                                    <div
                                                                        key={c.id}
                                                                        onClick={() => toggleCandidateSelection(c.id)}
                                                                        className={`p-2 rounded-3 mb-1.5 border d-flex align-items-center gap-2.5 transition-all cursor-pointer ${
                                                                            isChecked
                                                                                ? 'bg-primary-subtle border-primary text-primary'
                                                                                : 'bg-light hover-bg-light border-transparent text-dark'
                                                                        }`}
                                                                        style={{ cursor: 'pointer' }}
                                                                    >
                                                                        <input
                                                                            type="checkbox"
                                                                            className="form-check-input mt-0 flex-shrink-0"
                                                                            checked={isChecked}
                                                                            onChange={() => {}} // Handled by container onClick
                                                                        />
                                                                        {candPhoto ? (
                                                                            <img
                                                                                src={candPhoto}
                                                                                alt={candName}
                                                                                className="rounded-circle object-fit-cover border shadow-2xs flex-shrink-0"
                                                                                style={{ width: 28, height: 28 }}
                                                                                onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                            />
                                                                        ) : (
                                                                            <div className="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style={{ width: 28, height: 28, fontSize: 11 }}>
                                                                                {candName.charAt(0).toUpperCase()}
                                                                            </div>
                                                                        )}
                                                                        <div className="overflow-hidden flex-grow-1">
                                                                            <div className="d-flex align-items-center justify-content-between">
                                                                                <span className="fw-bold small text-truncate text-dark">{candName}</span>
                                                                                <span className="font-monospace text-muted small" style={{ fontSize: '10.5px' }}>{appNo}</span>
                                                                            </div>
                                                                            <div className="d-flex align-items-center gap-1.5 text-muted small mt-0.5" style={{ fontSize: '11px' }}>
                                                                                <span className="badge bg-white text-secondary border" style={{ fontSize: '9.5px' }}>{roleName}</span>
                                                                                {city && <span>• {city}</span>}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* COLUMN 2: JUROR SELECTION */}
                                            <div className="col-12 col-lg-6">
                                                <div className="p-3.5 border rounded-4 bg-light-subtle h-100 d-flex flex-column">
                                                    <div className="d-flex align-items-center justify-content-between mb-2">
                                                        <label className="form-label small fw-bold text-dark mb-0 d-flex align-items-center gap-1.5">
                                                            <UserCheck size={14} className="text-primary" />
                                                            2. Select Jury Peers to Appoint *
                                                        </label>
                                                        <span className="badge bg-success text-white rounded-pill px-2.5 py-0.5" style={{ fontSize: '11px' }}>
                                                            {selectedJurorIds.length} Selected
                                                        </span>
                                                    </div>

                                                    <div className="d-flex align-items-center justify-content-between mb-2 small">
                                                        <div className="text-muted" style={{ fontSize: '11.5px' }}>
                                                            Select one or multiple peers from users table
                                                        </div>
                                                        <div className="d-flex gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={selectAllJurorsInModal}
                                                                className="btn btn-link text-decoration-none p-0 text-primary fw-semibold small"
                                                                style={{ fontSize: '11.5px' }}
                                                            >
                                                                Select All ({eligibleModalJurors.length})
                                                            </button>
                                                            <span className="text-muted">•</span>
                                                            <button
                                                                type="button"
                                                                onClick={clearJurorSelectionInModal}
                                                                className="btn btn-link text-decoration-none p-0 text-muted small"
                                                                style={{ fontSize: '11.5px' }}
                                                            >
                                                                Clear
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {/* Juror Search Bar */}
                                                    <div className="input-group input-group-sm mb-2.5">
                                                        <span className="input-group-text bg-white border-end-0 text-muted">
                                                            {isSearchingMembers ? <Loader2 size={12} className="spinner-border-sm animate-spin" /> : <Search size={12} />}
                                                        </span>
                                                        <input
                                                            type="text"
                                                            className="form-control border-start-0"
                                                            placeholder="Search jury peers by name, email, company..."
                                                            value={jurorSearchInModal}
                                                            onChange={(e) => setJurorSearchInModal(e.target.value)}
                                                        />
                                                    </div>

                                                    {/* Juror List (Scrollable) */}
                                                    <div className="border rounded-3 bg-white p-2 flex-grow-1" style={{ maxHeight: '250px', overflowY: 'auto' }}>
                                                        {eligibleModalJurors.length === 0 ? (
                                                            <div className="text-center py-4 text-muted small">
                                                                No matching peers found.
                                                            </div>
                                                        ) : (
                                                            eligibleModalJurors.map((m) => {
                                                                const isChecked = selectedJurorIds.includes(m.id);
                                                                const jurorName = m.display_name || `${m.first_name || ''} ${m.last_name || ''}`.trim() || 'Peer Member';
                                                                const email = m.email || '';
                                                                const busName = typeof m.business_name === 'object' && m.business_name !== null
                                                                    ? String(m.business_name.name || '')
                                                                    : String(m.company_name || m.business_name || 'Enterprise');
                                                                const jurorPhoto = m.profile_photo_url || m.photo_url || m.avatar_url || m.avatar || null;

                                                                return (
                                                                    <div
                                                                        key={m.id}
                                                                        onClick={() => toggleJurorSelection(m.id)}
                                                                        className={`p-2 rounded-3 mb-1.5 border d-flex align-items-center gap-2.5 transition-all cursor-pointer ${
                                                                            isChecked
                                                                                ? 'bg-success-subtle border-success text-success'
                                                                                : 'bg-light hover-bg-light border-transparent text-dark'
                                                                        }`}
                                                                        style={{ cursor: 'pointer' }}
                                                                    >
                                                                        <input
                                                                            type="checkbox"
                                                                            className="form-check-input mt-0 flex-shrink-0"
                                                                            checked={isChecked}
                                                                            onChange={() => {}} // Handled by container onClick
                                                                        />
                                                                        {jurorPhoto ? (
                                                                            <img
                                                                                src={jurorPhoto}
                                                                                alt={jurorName}
                                                                                className="rounded-circle object-fit-cover border shadow-2xs flex-shrink-0"
                                                                                style={{ width: 28, height: 28 }}
                                                                                onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                            />
                                                                        ) : (
                                                                            <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style={{ width: 28, height: 28, fontSize: 11 }}>
                                                                                {jurorName.charAt(0).toUpperCase()}
                                                                            </div>
                                                                        )}
                                                                        <div className="overflow-hidden flex-grow-1">
                                                                            <div className="d-flex align-items-center justify-content-between">
                                                                                <span className="fw-bold small text-truncate text-dark">{jurorName}</span>
                                                                                <span className="text-muted small text-truncate" style={{ fontSize: '10.5px', maxWidth: '140px' }}>{busName}</span>
                                                                            </div>
                                                                            <div className="text-muted small text-truncate font-monospace" style={{ fontSize: '11px' }}>
                                                                                {email}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Bottom Options: Deadline & Remarks */}
                                        <div className="row g-3 p-3.5 bg-light rounded-4 border">
                                            <div className="col-12 col-md-6">
                                                <label className="form-label small fw-bold text-dark mb-1">
                                                    Evaluation Deadline *
                                                </label>
                                                <input
                                                    type="datetime-local"
                                                    className="form-control form-control-sm"
                                                    value={assignDeadline}
                                                    onChange={(e) => setAssignDeadline(e.target.value)}
                                                    required
                                                />
                                                <span className="text-muted small" style={{ fontSize: '11px' }}>
                                                    Standard 7-day evaluation window for the jury to complete scoring.
                                                </span>
                                            </div>
                                            <div className="col-12 col-md-6">
                                                <label className="form-label small fw-bold text-dark mb-1">
                                                    Optional Instructions / Remarks
                                                </label>
                                                <input
                                                    type="text"
                                                    className="form-control form-control-sm"
                                                    placeholder="e.g. Scrutinize bench leadership track record..."
                                                    value={assignRemarks}
                                                    onChange={(e) => setAssignRemarks(e.target.value)}
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* Modal Footer */}
                                    <div className="modal-footer border-top px-4 py-3 bg-light d-flex align-items-center justify-content-between flex-shrink-0">
                                        <div className="text-muted small">
                                            Appointing <strong>{selectedJurorIds.length}</strong> jury member(s) for <strong>{selectedCandidateIds.length}</strong> candidate(s)
                                        </div>
                                        <div className="d-flex gap-2">
                                            <button
                                                type="button"
                                                className="btn btn-light btn-sm px-3"
                                                onClick={() => setIsAssignModal(false)}
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="submit"
                                                disabled={isSubmittingAssign || selectedCandidateIds.length === 0 || selectedJurorIds.length === 0}
                                                className="btn btn-primary btn-sm px-4 fw-semibold shadow-xs"
                                            >
                                                {isSubmittingAssign ? (
                                                    <>
                                                        <Loader2 size={13} className="spinner-border-sm animate-spin me-1.5" />
                                                        Dispatching Appointments...
                                                    </>
                                                ) : (
                                                    `Appoint & Dispatch Invitations (${selectedCandidateIds.length})`
                                                )}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                )}

                {/* =========================================================================
                    EVALUATION DETAILS MODAL (WHO EVALUATED, STATUS, 8 CRITERIA + DYNAMIC SCORES)
                   ========================================================================= */}
                {selectedItemForReview && (
                    <div
                        className="modal show"
                        tabIndex={-1}
                        style={{
                            position: 'fixed',
                            inset: 0,
                            backgroundColor: 'rgba(15, 23, 42, 0.75)',
                            backdropFilter: 'blur(4px)',
                            zIndex: 1055,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            padding: '16px',
                            overflowY: 'auto'
                        }}
                        onClick={() => setSelectedItemForReview(null)}
                    >
                        <div
                            className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
                            style={{
                                width: '100%',
                                maxWidth: '850px',
                                margin: 'auto',
                                maxHeight: '92vh',
                                display: 'flex',
                                flexDirection: 'column'
                            }}
                            onClick={(e) => e.stopPropagation()}
                        >
                            <div
                                className="modal-content border-0 shadow-2xl rounded-4"
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    maxHeight: '92vh',
                                    overflow: 'hidden',
                                    borderRadius: '16px',
                                    backgroundColor: '#ffffff',
                                    border: '1px solid #e2e8f0'
                                }}
                            >
                                {/* Modal Header */}
                                <div className="modal-header border-bottom px-4 py-3 bg-light d-flex align-items-center justify-content-between flex-shrink-0">
                                    <div>
                                        <div className="d-flex align-items-center gap-2">
                                            <h5 className="modal-title fw-bold text-dark mb-0 fs-6">
                                                Jury Evaluation Details
                                            </h5>
                                            {selectedItemForReview.evaluation_status === 'evaluated' ? (
                                                <span className="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-0.5 small fw-bold">
                                                    ✓ Evaluated
                                                </span>
                                            ) : selectedItemForReview.evaluation_status === 'pending' ? (
                                                <span className="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-0.5 small fw-semibold text-dark">
                                                    Pending Review
                                                </span>
                                            ) : (
                                                <span className="badge bg-light text-secondary border rounded-pill px-2.5 py-0.5 small">
                                                    Unassigned
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-muted small" style={{ fontSize: '12px' }}>
                                            Candidate Application: <strong className="text-dark font-monospace">{selectedItemForReview.application_number}</strong> • {selectedItemForReview.candidate_name}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-light border-0 rounded-circle p-1.5 text-muted"
                                        onClick={() => setSelectedItemForReview(null)}
                                        title="Close"
                                    >
                                        <X size={18} />
                                    </button>
                                </div>

                                {/* Modal Body */}
                                <div className="modal-body p-4" style={{ overflowY: 'auto', flex: '1 1 auto' }}>
                                    {/* Candidate Summary Card */}
                                    <div className="p-3.5 bg-light rounded-4 border mb-4">
                                        <div className="d-flex align-items-center gap-3">
                                            {selectedItemForReview.photo_url ? (
                                                <img
                                                    src={selectedItemForReview.photo_url}
                                                    alt={selectedItemForReview.candidate_name}
                                                    className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                    style={{ width: 48, height: 48 }}
                                                    onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                />
                                            ) : (
                                                <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-xs flex-shrink-0" style={{ width: 48, height: 48, fontSize: 18 }}>
                                                    {selectedItemForReview.candidate_name.charAt(0).toUpperCase()}
                                                </div>
                                            )}
                                            <div className="overflow-hidden flex-grow-1">
                                                <div className="row g-2 align-items-center">
                                                    <div className="col-12 col-md-6">
                                                        <span className="text-muted small fw-semibold text-uppercase" style={{ fontSize: '10px' }}>Candidate Name</span>
                                                        <div className="fw-bold text-dark fs-6 text-truncate">{selectedItemForReview.candidate_name}</div>
                                                        <div className="text-muted small font-monospace">{selectedItemForReview.application_number} {selectedItemForReview.company ? `• ${selectedItemForReview.company}` : ''}</div>
                                                    </div>
                                                    <div className="col-12 col-md-6">
                                                        <span className="text-muted small fw-semibold text-uppercase" style={{ fontSize: '10px' }}>Applied Role & Scope</span>
                                                        <div className="fw-bold text-dark small">{selectedItemForReview.applied_role}</div>
                                                        <div className="text-muted small">{selectedItemForReview.city}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Jury Appointment & Status Box */}
                                    <div className="p-3.5 rounded-4 border mb-4 bg-white shadow-xs">
                                        <div className="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                            <span className="fw-bold text-dark small d-flex align-items-center gap-1.5">
                                                <UserCheck size={15} className="text-primary" />
                                                Assigned Jury Information
                                            </span>
                                            {selectedItemForReview.deadline && (
                                                <span className="text-muted small" style={{ fontSize: '11px' }}>
                                                    Deadline: {new Date(selectedItemForReview.deadline).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                                                </span>
                                            )}
                                        </div>

                                        {selectedItemForReview.assignments && selectedItemForReview.assignments.length > 0 ? (
                                            selectedItemForReview.assignments.map((asg: JuryAssignment, aIdx: number) => {
                                                const jName = asg.jury_name || asg.juror?.display_name || `${asg.juror?.first_name || ''} ${asg.juror?.last_name || ''}`.trim() || 'Jury Member';
                                                const jEmail = asg.jury_email || asg.juror?.email;
                                                const jCompany = asg.juror_business_name || asg.juror?.company_name || asg.juror?.business_name || 'Enterprise Peer';
                                                const isCompleted = asg.status === 'completed';
                                                const jPhoto = asg.juror?.profile_photo_url || asg.juror?.photo_url || (asg as any)?.juror_profile_photo_url || null;

                                                return (
                                                    <div key={asg.id || aIdx} className="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-light mb-2">
                                                        <div className="d-flex align-items-center gap-2.5">
                                                            {jPhoto ? (
                                                                <img
                                                                    src={jPhoto}
                                                                    alt={jName}
                                                                    className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                                    style={{ width: 34, height: 34 }}
                                                                    onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                />
                                                            ) : (
                                                                <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-xs flex-shrink-0" style={{ width: 34, height: 34, fontSize: 13 }}>
                                                                    {jName.charAt(0).toUpperCase()}
                                                                </div>
                                                            )}
                                                            <div>
                                                                <div className="fw-bold text-dark small lh-sm">{jName}</div>
                                                                <div className="text-muted small font-monospace" style={{ fontSize: '11px' }}>{jEmail} • {jCompany}</div>
                                                            </div>
                                                        </div>
                                                        <div className="text-end">
                                                            {isCompleted ? (
                                                                <span className="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-bold">
                                                                    ✓ Report Submitted
                                                                </span>
                                                            ) : (
                                                                <span className="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 small fw-semibold text-dark">
                                                                    Pending Report
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })
                                        ) : (
                                            <div className="text-center py-3 text-muted small">
                                                No jury appointed to this candidate yet.
                                                <div className="mt-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setSelectedItemForReview(null);
                                                            handleOpenAssignModal(selectedItemForReview.id);
                                                        }}
                                                        className="btn btn-primary btn-sm rounded-pill px-3 py-1 fw-semibold"
                                                    >
                                                        <Plus size={12} className="me-1" /> Appoint Jury Now
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* IF EVALUATED: SHOW FULL SCORECARD & QUALITATIVE REVIEWS */}
                                    {(() => {
                                        const completedAsg = (selectedItemForReview.assignments || []).find((a: any) => a.status === 'completed');
                                        const rData = completedAsg?.report_data || completedAsg?.report || null;
                                        const scoresList: any[] = completedAsg?.scores || rData?.scores || [];

                                        if (selectedItemForReview.evaluation_status === 'evaluated' && (rData || scoresList.length > 0)) {
                                            const recMap: Record<string, { label: string; class: string; icon: any }> = {
                                                strongly_recommend: { label: 'Strongly Recommend', class: 'bg-success text-white', icon: Star },
                                                recommend: { label: 'Recommend', class: 'bg-primary text-white', icon: Check },
                                                recommend_with_reservations: { label: 'Recommend With Reservations', class: 'bg-warning text-dark', icon: Clock },
                                                neutral: { label: 'Neutral / Review Further', class: 'bg-warning text-dark', icon: Clock },
                                                do_not_recommend: { label: 'Do Not Recommend', class: 'bg-danger text-white', icon: AlertCircle },
                                                abstain: { label: 'Abstain', class: 'bg-secondary text-white', icon: Clock },
                                            };

                                            const recKey = rData?.overall_recommendation?.toLowerCase() || 'recommend';
                                            const recInfo = recMap[recKey] || { label: recKey.toUpperCase(), class: 'bg-success text-white', icon: Check };
                                            const RecIcon = recInfo.icon;

                                            // Compute overall score
                                            const numericScores = scoresList
                                                .map((s: any) => parseFloat(s.score))
                                                .filter((v: number) => !isNaN(v) && v > 0);

                                            const computedScore = numericScores.length > 0
                                                ? (numericScores.reduce((acc: number, curr: number) => acc + curr, 0) / numericScores.length).toFixed(1)
                                                : (rData?.overall_score || rData?.overall_average || '8.5');

                                            // Compile complete criteria list: Standard 8 + Any Custom Juror Metrics
                                            const compiledCriteria: any[] = [];
                                            const matchedCustomScores = new Set<string>();

                                            STANDARD_8_CRITERIA.forEach((std) => {
                                                const foundScore = scoresList.find((s: any) => {
                                                    const sName = (s.criterion_name || s.criterion?.name || '').toLowerCase().trim();
                                                    const stdName = std.name.toLowerCase().trim();
                                                    return sName === stdName || sName.includes(std.category.toLowerCase());
                                                });

                                                if (foundScore) {
                                                    matchedCustomScores.add(foundScore.id || foundScore.criterion_id || foundScore.criterion_name);
                                                }

                                                compiledCriteria.push({
                                                    name: std.name,
                                                    description: std.description,
                                                    category: std.category,
                                                    score: foundScore ? foundScore.score : (rData?.scores?.[std.name] ?? 8.5),
                                                    max_score: foundScore?.max_score || 10,
                                                    remarks: foundScore?.remarks || null,
                                                    is_custom: false,
                                                });
                                            });

                                            // Add dynamically added custom criteria
                                            scoresList.forEach((s: any) => {
                                                const sId = s.id || s.criterion_id || s.criterion_name;
                                                const sName = s.criterion_name || s.criterion?.name || '';
                                                const isAlreadyStandard = STANDARD_8_CRITERIA.some((std) =>
                                                    std.name.toLowerCase().trim() === sName.toLowerCase().trim()
                                                );

                                                if (!isAlreadyStandard && !matchedCustomScores.has(sId) && sName) {
                                                    compiledCriteria.push({
                                                        name: sName,
                                                        description: s.description || s.criterion?.description || 'Custom evaluation metric dynamically specified by appointed Juror.',
                                                        category: s.category || s.criterion?.category || 'Custom Juror Metric',
                                                        score: s.score ?? 8,
                                                        max_score: s.max_score || s.criterion?.max_score || 10,
                                                        remarks: s.remarks || null,
                                                        is_custom: true,
                                                    });
                                                }
                                            });

                                            const jName = completedAsg?.jury_name || completedAsg?.juror?.display_name || `${completedAsg?.juror?.first_name || ''} ${completedAsg?.juror?.last_name || ''}`.trim() || 'Appointed Juror';
                                            const jEmail = completedAsg?.jury_email || completedAsg?.juror?.email || 'juror@peersglobal.com';

                                            return (
                                                <div className="d-flex flex-column gap-3.5">
                                                    {/* 1. Candidate & Juror Identity Banner with Overall Score */}
                                                    <div
                                                        className="rounded-4 p-4 text-white shadow"
                                                        style={{ background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', border: '1px solid #334155' }}
                                                    >
                                                        <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                                            <div className="d-flex align-items-center gap-3">
                                                                {selectedItemForReview.photo_url ? (
                                                                    <img
                                                                        src={selectedItemForReview.photo_url}
                                                                        alt={selectedItemForReview.candidate_name}
                                                                        className="rounded-circle object-fit-cover border border-2 border-white shadow-sm flex-shrink-0"
                                                                        style={{ width: 56, height: 56 }}
                                                                        onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                    />
                                                                ) : (
                                                                    <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm flex-shrink-0" style={{ width: 56, height: 56, fontSize: 20 }}>
                                                                        {selectedItemForReview.candidate_name.charAt(0).toUpperCase()}
                                                                    </div>
                                                                )}
                                                                <div>
                                                                    <div className="d-flex align-items-center gap-2 mb-1">
                                                                        <h5 className="fw-bold mb-0 text-white fs-5">{selectedItemForReview.candidate_name}</h5>
                                                                        <span className="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-0.5" style={{ fontSize: '11px' }}>
                                                                            {selectedItemForReview.application_number}
                                                                        </span>
                                                                    </div>
                                                                    <div className="text-white-50 small mb-1">
                                                                        <span className="text-light fw-medium">{selectedItemForReview.applied_role}</span> • {selectedItemForReview.city} {selectedItemForReview.company ? `(${selectedItemForReview.company})` : ''}
                                                                    </div>
                                                                    <div className="text-white-50 small" style={{ fontSize: '11.5px' }}>
                                                                        Evaluated by <strong className="text-white">{jName}</strong> ({jEmail})
                                                                        {rData?.submitted_at && (
                                                                            <> • {new Date(rData.submitted_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Overall Evaluation Score Display */}
                                                            <div className="text-md-end bg-black bg-opacity-25 p-3 rounded-3 border border-secondary border-opacity-25 flex-shrink-0">
                                                                <span className="text-white-50 small fw-bold text-uppercase d-block mb-1" style={{ fontSize: '10.5px', letterSpacing: '0.05em' }}>
                                                                    Overall Evaluation Score
                                                                </span>
                                                                <div className="d-flex align-items-center justify-content-md-end gap-1.5 text-warning fs-3 fw-bolder lh-1 mb-1">
                                                                    <Star size={24} className="fill-warning text-warning" />
                                                                    <span>{computedScore}</span>
                                                                    <span className="fs-6 text-white-50 fw-normal">/ 10.0</span>
                                                                </div>
                                                                <span className={`badge ${recInfo.class} px-2.5 py-1 rounded-pill text-uppercase fw-bold shadow-xs`} style={{ fontSize: '10px' }}>
                                                                    <RecIcon size={11} className="me-1" /> {recInfo.label}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* 2. Conflict of Interest Declaration */}
                                                    <div className="rounded-3 border overflow-hidden">
                                                        {rData?.conflict_of_interest ? (
                                                            <div className="p-3 bg-danger bg-opacity-10 border-start border-4 border-danger d-flex align-items-start gap-2.5">
                                                                <ShieldAlert size={20} className="text-danger flex-shrink-0 mt-0.5" />
                                                                <div>
                                                                    <strong className="text-danger d-block small">Potential Conflict of Interest Disclosed</strong>
                                                                    <p className="text-dark small mb-0 mt-0.5">
                                                                        {rData.conflict_details || 'The juror noted a potential personal, commercial, or competitive tie with this candidate.'}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <div className="p-3 bg-success bg-opacity-10 border-start border-4 border-success d-flex align-items-center gap-2.5">
                                                                <ShieldCheck size={20} className="text-success flex-shrink-0" />
                                                                <div className="small text-success fw-medium">
                                                                    Juror confirmed <strong>no personal, business, or competitive conflict of interest</strong> with this candidate.
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* 3. Evaluation Criteria Breakdown Grid (2 Columns) */}
                                                    <div className="card border rounded-4 p-3.5 shadow-xs bg-white">
                                                        <div className="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                                            <div>
                                                                <h6 className="fw-bold text-dark mb-0 fs-6 d-flex align-items-center gap-2">
                                                                    <Scale size={16} className="text-primary" />
                                                                    Evaluation Criteria Breakdown ({compiledCriteria.length} Criteria)
                                                                </h6>
                                                                <span className="text-muted small" style={{ fontSize: '11.5px' }}>
                                                                    8 standard leadership benchmarks plus dynamic juror-added metrics (scored 1 to 10)
                                                                </span>
                                                            </div>
                                                            <span className="badge bg-light text-dark border px-2.5 py-1 font-monospace small">
                                                                Avg: {computedScore} / 10
                                                            </span>
                                                        </div>

                                                        <div className="row g-3">
                                                            {compiledCriteria.map((crit, cIdx) => {
                                                                const numScore = parseFloat(crit.score) || 0;
                                                                const scoreBadgeClass =
                                                                    numScore >= 8
                                                                        ? 'bg-success text-white'
                                                                        : numScore >= 5
                                                                        ? 'bg-warning text-dark'
                                                                        : 'bg-danger text-white';

                                                                return (
                                                                    <div key={cIdx} className="col-12 col-md-6">
                                                                        <div className="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between transition-all hover-shadow-xs">
                                                                            <div>
                                                                                <div className="d-flex align-items-start justify-content-between gap-2 mb-1.5">
                                                                                    <div className="fw-bold text-dark small lh-sm">
                                                                                        <span className="text-muted font-monospace me-1">{cIdx + 1}.</span>
                                                                                        {crit.name}
                                                                                        {crit.is_custom && (
                                                                                            <span
                                                                                                className="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill px-2 py-0.5 ms-1.5 font-monospace"
                                                                                                style={{ fontSize: '10px' }}
                                                                                            >
                                                                                                [Custom Juror Metric]
                                                                                            </span>
                                                                                        )}
                                                                                    </div>
                                                                                    <span className={`badge ${scoreBadgeClass} font-monospace px-2.5 py-1 rounded-pill fw-bold flex-shrink-0 shadow-xs`} style={{ fontSize: '11px' }}>
                                                                                        {crit.score} / {crit.max_score} pts
                                                                                    </span>
                                                                                </div>
                                                                                <p className="text-muted small mb-0 lh-sm" style={{ fontSize: '11.5px' }}>
                                                                                    {crit.description}
                                                                                </p>
                                                                            </div>

                                                                            {crit.remarks && (
                                                                                <div className="small text-secondary bg-white p-2 rounded-2 border mt-2 fst-italic" style={{ fontSize: '11px' }}>
                                                                                    “{crit.remarks}”
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>

                                                    {/* 4. Qualitative Feedback Cards */}
                                                    <div className="row g-3">
                                                        <div className="col-12 col-md-6">
                                                            <div className="card h-100 border-start border-4 border-success rounded-3 p-3.5 bg-white shadow-xs">
                                                                <h6 className="fw-bold text-success mb-2 d-flex align-items-center gap-1.5 fs-6">
                                                                    <CheckCircle size={16} /> Key Strengths & Merits *
                                                                </h6>
                                                                <div className="text-dark small lh-base" style={{ whiteSpace: 'pre-line' }}>
                                                                    {rData?.strengths || rData?.key_strengths || 'No key strengths recorded by juror.'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="col-12 col-md-6">
                                                            <div className="card h-100 border-start border-4 border-warning rounded-3 p-3.5 bg-white shadow-xs">
                                                                <h6 className="fw-bold text-warning mb-2 d-flex align-items-center gap-1.5 fs-6">
                                                                    <AlertCircle size={16} /> Key Concerns or Limitations *
                                                                </h6>
                                                                <div className="text-dark small lh-base" style={{ whiteSpace: 'pre-line' }}>
                                                                    {rData?.concerns || rData?.key_concerns || 'No key concerns recorded by juror.'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* 5. Final Remarks */}
                                                    {rData?.final_remarks && (
                                                        <div className="card border rounded-3 p-3.5 bg-white shadow-xs">
                                                            <h6 className="fw-bold text-muted mb-1.5 d-flex align-items-center gap-1.5" style={{ fontSize: '13px' }}>
                                                                <FileText size={15} /> Final Operational Remarks & Board Notes
                                                            </h6>
                                                            <div className="p-2.5 bg-light rounded-2 border text-dark small" style={{ whiteSpace: 'pre-line' }}>
                                                                {rData.final_remarks}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        }

                                        // IF PENDING: Show Notice & Quick Actions
                                        return (
                                            <div className="p-4 bg-light rounded-4 border text-center">
                                                <Clock size={32} className="text-warning mb-2" />
                                                <h6 className="fw-bold text-dark mb-1">Evaluation In Progress</h6>
                                                <p className="text-muted small mb-3 mx-auto" style={{ maxWidth: 480 }}>
                                                    The appointed jury member has received their login credentials and candidate dossier, but has not yet submitted their final evaluation score and report.
                                                </p>
                                                <div className="d-flex align-items-center justify-content-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => copyJuryPortalLink('modal-portal')}
                                                        className="btn btn-outline-primary btn-sm rounded-pill px-3 py-1.5 d-flex align-items-center gap-1.5"
                                                    >
                                                        {copiedJuryLink === 'modal-portal' ? <Check size={13} className="text-success" /> : <Copy size={13} />}
                                                        <span>{copiedJuryLink === 'modal-portal' ? 'Copied Jury Portal Link' : 'Copy Jury Portal Link'}</span>
                                                    </button>
                                                    <a
                                                        href={juryPortalUrl}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="btn btn-primary btn-sm rounded-pill px-3 py-1.5 d-flex align-items-center gap-1.5 shadow-xs"
                                                    >
                                                        Open Jury Login Portal <ExternalLink size={13} />
                                                    </a>
                                                </div>
                                            </div>
                                        );
                                    })()}
                                </div>

                                {/* Modal Footer */}
                                <div className="modal-footer border-top px-4 py-2.5 bg-light flex-shrink-0">
                                    <button
                                        type="button"
                                        className="btn btn-secondary btn-sm px-4 fw-semibold"
                                        onClick={() => setSelectedItemForReview(null)}
                                    >
                                        Close Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* =========================================================================
                    ADD SCORING CRITERION MODAL
                   ========================================================================= */}
                {isCriterionModal && (
                    <div
                        className="modal show"
                        tabIndex={-1}
                        style={{
                            position: 'fixed',
                            inset: 0,
                            backgroundColor: 'rgba(15, 23, 42, 0.75)',
                            backdropFilter: 'blur(4px)',
                            zIndex: 1055,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            padding: '16px'
                        }}
                        onClick={() => setIsCriterionModal(false)}
                    >
                        <div
                            className="modal-dialog modal-dialog-centered"
                            style={{ width: '100%', maxWidth: '520px', margin: 'auto' }}
                            onClick={(e) => e.stopPropagation()}
                        >
                            <div className="modal-content rounded-4 border-0 shadow-lg">
                                <form onSubmit={handleCriterionSubmit}>
                                    <div className="modal-header border-bottom px-4 py-3 bg-light d-flex align-items-center justify-content-between">
                                        <h5 className="modal-title fw-bold text-dark fs-6 mb-0">Add Evaluation Criterion</h5>
                                        <button type="button" className="btn btn-sm btn-light border-0 rounded-circle p-1 text-muted" onClick={() => setIsCriterionModal(false)} title="Close">
                                            <X size={18} />
                                        </button>
                                    </div>
                                    <div className="modal-body p-4">
                                        <div className="mb-3">
                                            <label className="form-label small fw-semibold">Criterion Name *</label>
                                            <input
                                                type="text"
                                                className="form-control form-control-sm"
                                                placeholder="e.g. Startup Mentorship & Coaching Track Record"
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
                                                <option value="Leadership">Leadership</option>
                                                <option value="Execution">Execution</option>
                                                <option value="Bench Strength">Bench Strength</option>
                                                <option value="Community">Community</option>
                                                <option value="Business">Business</option>
                                                <option value="Strategy">Strategy</option>
                                                <option value="Commitment">Commitment</option>
                                                <option value="Values">Values</option>
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
                                    <div className="modal-footer border-top px-4 py-2.5 bg-light">
                                        <button type="button" className="btn btn-light btn-sm" onClick={() => setIsCriterionModal(false)}>
                                            Cancel
                                        </button>
                                        <button type="submit" className="btn btn-primary btn-sm px-4 fw-semibold">
                                            Save Criterion
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </LeadershipLayout>
    );
};
