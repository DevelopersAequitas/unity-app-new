import { LeadershipLayout } from '../../components/LeadershipLayout';
import { utcToLocalDateTimeInput } from '../../../../utils/dateTime';
import React, { useEffect, useState, useMemo } from 'react';
import { juryApi } from '../../api/services';
import { StatusBadge } from '../../components/common/Badge';
import {
    Scale,
    Users,
    Award,
    FileText,
    CheckCircle2,
    AlertCircle,
    RefreshCw,
    ChevronLeft,
    ChevronRight,
    UserCheck,
    Plus,
    Search,
    Mail,
    Copy,
    ExternalLink,
    X,
    Loader2,
    Check,
    Calendar,
    Briefcase,
    Layers
} from 'lucide-react';

export const JuryEvaluationsPage: React.FC = () => {
    const [reportData, setReportData] = useState<any[]>([]);
    const [evalPage, setEvalPage] = useState<number>(1);
    const EVALS_PER_PAGE = 5;
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Standing / Appointed Jurors State
    const [standingJurors, setStandingJurors] = useState<any[]>([]);
    const [isLoadingJurors, setIsLoadingJurors] = useState<boolean>(false);
    const [copiedJuryLink, setCopiedJuryLink] = useState<string | null>(null);
    const [isResendingEmail, setIsResendingEmail] = useState<string | null>(null);
    const [emailSuccess, setEmailSuccess] = useState<string | null>(null);

    // Modal state for Assigning Jury
    const [isAssignModal, setIsAssignModal] = useState<boolean>(false);
    const [members, setMembers] = useState<any[]>([]);
    const [jurorSearch, setJurorSearch] = useState<string>('');
    const [selectedJurorIds, setSelectedJurorIds] = useState<string[]>([]);
    const [visibleJurorsCount, setVisibleJurorsCount] = useState<number>(10);
    const [isSearchingMembers, setIsSearchingMembers] = useState<boolean>(false);
    const [assignDeadline, setAssignDeadline] = useState<string>(
        utcToLocalDateTimeInput(new Date(Date.now() + 7 * 86400000))
    );
    const [assignRemarks, setAssignRemarks] = useState<string>('');
    const [isSubmittingAssign, setIsSubmittingAssign] = useState<boolean>(false);
    const [assignSuccess, setAssignSuccess] = useState<string | null>(null);

    const juryPanelUrl = 'https://peersglobal.com/leadership/jury';

    const copyJuryPanelLink = (jurorId: string) => {
        navigator.clipboard.writeText(juryPanelUrl);
        setCopiedJuryLink(jurorId);
        setTimeout(() => setCopiedJuryLink(null), 2500);
    };

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

    const loadReport = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await juryApi.getReport();
            const data = res.data.data;
            const evaluationsList = Array.isArray(data)
                ? data
                : (Array.isArray(data?.assignments)
                    ? data.assignments
                    : (Array.isArray(data?.evaluations) ? data.evaluations : []));
            setReportData(evaluationsList);
            setEvalPage(1);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load jury evaluation reports.');
            setReportData([]);
        } finally {
            setIsLoading(false);
        }
    };

    const loadStandingJurors = async () => {
        setIsLoadingJurors(true);
        try {
            const res = await juryApi.getAssignments();
            const assignments = Array.isArray(res.data.data) ? res.data.data : [];
            // Group and deduplicate by juror_user_id
            const jurorMap = new Map<string, any>();
            assignments.forEach((asg: any) => {
                const uid = asg.juror_user_id || asg.juror?.id;
                if (uid && (!jurorMap.has(uid) || asg.status === 'completed')) {
                    jurorMap.set(uid, asg);
                }
            });
            setStandingJurors(Array.from(jurorMap.values()));
        } catch (err) {
            console.error('Failed to load standing jurors:', err);
        } finally {
            setIsLoadingJurors(false);
        }
    };

    const loadMembers = async (search?: string) => {
        setIsSearchingMembers(true);
        try {
            const res = await juryApi.getMembers({ search, per_page: 50 });
            const list = Array.isArray(res.data.data)
                ? res.data.data
                : (Array.isArray((res.data.data as any)?.data) ? (res.data.data as any).data : []);
            setMembers(list);
        } catch (err) {
            console.error('Failed to load eligible peers for jury:', err);
        } finally {
            setIsSearchingMembers(false);
        }
    };

    useEffect(() => {
        loadReport();
        loadStandingJurors();
    }, []);

    const filteredMembers = useMemo(() => {
        if (!jurorSearch.trim()) return members;
        const q = jurorSearch.toLowerCase();
        return members.filter((m) => {
            const name = (m.display_name || `${m.first_name || ''} ${m.last_name || ''}`).toLowerCase();
            const email = (m.email || '').toLowerCase();
            const comp = (m.company_name || m.business_name || '').toLowerCase();
            return name.includes(q) || email.includes(q) || comp.includes(q);
        });
    }, [members, jurorSearch]);

    const toggleJurorSelection = (id: string) => {
        setSelectedJurorIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
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
            alert('Please select at least one juror peer from the peers directory.');
            return;
        }

        setIsSubmittingAssign(true);
        try {
            const res = await juryApi.assign({
                nomination_id: 'all',
                juror_user_ids: selectedJurorIds,
                deadline: assignDeadline,
                remarks: assignRemarks,
            });

            setIsAssignModal(false);
            setSelectedJurorIds([]);
            setAssignSuccess(
                res?.data?.message ||
                `✓ Successfully appointed ${selectedJurorIds.length} juror(s)! Official appointment emails with the evaluation portal link have been dispatched.`
            );
            setTimeout(() => setAssignSuccess(null), 6000);
            loadReport();
            loadStandingJurors();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to assign jury members.');
        } finally {
            setIsSubmittingAssign(false);
        }
    };

    return (
        <LeadershipLayout>
            <div className="container-fluid px-0">
                {/* Header Toolbar */}
            <div className="d-flex justify-content-end align-items-center gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <button
                        onClick={() => loadReport()}
                        disabled={isLoading}
                        className="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs"
                    >
                        <RefreshCw size={14} className={isLoading ? 'spinner-border spinner-border-sm' : ''} /> Refresh Report
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

                {/* Appointed Jury Evaluators Panel */}
                <div className="card border-0 shadow-xs rounded-4 bg-white p-4 mb-4">
                    <div className="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 pb-3 border-bottom mb-3">
                        <div className="d-flex align-items-center gap-2">
                            <div className="bg-primary-subtle text-primary p-2 rounded-3">
                                <Users size={18} />
                            </div>
                            <div>
                                <h6 className="fw-bold text-dark mb-0">Appointed Jury Panel</h6>
                                <p className="text-muted small mb-0">
                                    Standing jurors who receive and score all candidate nomination requests
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                setIsAssignModal(true);
                                loadMembers();
                            }}
                            className="btn btn-outline-primary btn-sm rounded-3 px-3 py-1.5 d-flex align-items-center gap-1.5"
                        >
                            <Plus size={14} /> Add Juror
                        </button>
                    </div>

                    {isLoadingJurors ? (
                        <div className="text-center py-4 text-muted">
                            <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                            Loading appointed jury panel...
                        </div>
                    ) : standingJurors.length === 0 ? (
                        <div className="bg-light rounded-4 p-4 text-center border border-dashed">
                            <UserCheck size={32} className="text-muted mb-2 opacity-50" />
                            <h6 className="fw-bold text-dark mb-1">No Jury Members Appointed Yet</h6>
                            <p className="text-muted small mb-3 mx-auto" style={{ maxWidth: 460 }}>
                                Appoint standing jury members from the peers directory. They will receive their dedicated evaluation portal link via email and evaluate all current and incoming candidate applications.
                            </p>
                            <button
                                type="button"
                                onClick={() => {
                                    setIsAssignModal(true);
                                    loadMembers();
                                }}
                                className="btn btn-primary btn-sm rounded-3 px-3 py-1.5 d-inline-flex align-items-center gap-1.5"
                            >
                                <Plus size={14} /> Appoint First Juror Now
                            </button>
                        </div>
                    ) : (
                        <div className="row g-3">
                            {standingJurors.map((jurorAsg, idx) => {
                                const juror = jurorAsg.juror;
                                const jurorId = jurorAsg.juror_user_id || juror?.id || `juror-${idx}`;
                                const name = juror?.display_name || `${juror?.first_name || ''} ${juror?.last_name || ''}`.trim() || jurorAsg.jury_name || 'Appointed Juror';
                                const email = juror?.email || jurorAsg.jury_email || '';
                                const comp = juror?.business_name || juror?.company_name || juror?.designation || 'Peers Global Member';

                                                const jurorPhoto = jurorAsg.juror?.profile_photo_url || jurorAsg.juror?.photo_url || (jurorAsg as any).juror_profile_photo_url || null;

                                                return (
                                                    <div key={jurorId} className="col-12 col-md-6 col-xl-4">
                                                        <div className="border rounded-4 p-3.5 bg-light-subtle h-100 d-flex flex-column justify-content-between">
                                                            <div>
                                                                <div className="d-flex align-items-start justify-content-between gap-2 mb-2">
                                                                    <div className="d-flex align-items-center gap-2.5">
                                                                        {jurorPhoto ? (
                                                                            <img
                                                                                src={jurorPhoto}
                                                                                alt={name}
                                                                                className="rounded-circle object-fit-cover border shadow-xs flex-shrink-0"
                                                                                style={{ width: 38, height: 38 }}
                                                                                onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                            />
                                                                        ) : (
                                                                            <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-xs" style={{ width: 38, height: 38, fontSize: 13 }}>
                                                                                {name.substring(0, 2).toUpperCase()}
                                                                            </div>
                                                                        )}
                                                                        <div>
                                                                            <div className="fw-bold text-dark lh-sm">{name}</div>
                                                                            <div className="text-muted small text-truncate" style={{ maxWidth: 170 }}>{comp}</div>
                                                                        </div>
                                                                    </div>
                                                    <span className="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 small fw-semibold">
                                                        Standing
                                                    </span>
                                                </div>

                                                <div className="small text-muted mb-2.5">
                                                    <div className="d-flex align-items-center gap-1.5 text-truncate">
                                                        <Mail size={12} className="text-secondary flex-shrink-0" />
                                                        <span className="text-truncate">{email || 'No email registered'}</span>
                                                    </div>
                                                </div>

                                                <div className="p-2 bg-white rounded-3 border mb-3">
                                                    <div className="text-muted small fw-semibold mb-1" style={{ fontSize: 11 }}>
                                                        JUROR EVALUATION PORTAL LINK:
                                                    </div>
                                                    <div className="d-flex align-items-center justify-content-between gap-2">
                                                        <span className="font-monospace text-primary text-truncate small" style={{ fontSize: 11.5 }}>
                                                            peersglobal.com/.../jury
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() => copyJuryPanelLink(jurorId)}
                                                            className={`btn btn-xs rounded-2 px-2 py-0.5 d-flex align-items-center gap-1 ${copiedJuryLink === jurorId
                                                                ? 'btn-success text-white'
                                                                : 'btn-outline-primary'
                                                                }`}
                                                            style={{ fontSize: 11 }}
                                                            title="Copy login link"
                                                        >
                                                            {copiedJuryLink === jurorId ? <Check size={11} /> : <Copy size={11} />}
                                                            {copiedJuryLink === jurorId ? 'Copied' : 'Copy'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="d-flex align-items-center justify-content-between pt-2 border-top">
                                                <button
                                                    type="button"
                                                    onClick={() => handleResendEmail(jurorId, jurorAsg.id)}
                                                    disabled={isResendingEmail === jurorId || !email}
                                                    className="btn btn-outline-secondary btn-sm rounded-3 px-2.5 py-1 d-flex align-items-center gap-1 text-muted"
                                                    style={{ fontSize: 11.5 }}
                                                    title="Resend official appointment email with portal link"
                                                >
                                                    <Mail size={12} className={isResendingEmail === jurorId ? 'spinner-border spinner-border-sm' : ''} />
                                                    {isResendingEmail === jurorId ? 'Sending...' : 'Resend Email'}
                                                </button>
                                                <a
                                                    href={juryPanelUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="btn btn-link btn-sm text-decoration-none text-primary p-0 d-flex align-items-center gap-1"
                                                    style={{ fontSize: 11.5 }}
                                                >
                                                    Open Portal <ExternalLink size={11} />
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Evaluations Report Table */}
                <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                    <div className="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 className="fw-bold text-dark mb-0">Candidate Evaluation Scorecards</h6>
                            <p className="text-muted small mb-0">Completed reviews from appointed jurors</p>
                        </div>
                    </div>

                    <div className="table-responsive">
                        <table className="table table-hover align-middle mb-0">
                            <thead className="table-light">
                                <tr>
                                    <th>Candidate</th>
                                    <th>Juror</th>
                                    <th>Overall Score</th>
                                    <th>Completion</th>
                                    <th>Juror Recommendation</th>
                                    <th>Submitted At</th>
                                </tr>
                            </thead>
                            <tbody>
                                {isLoading ? (
                                    <tr>
                                        <td colSpan={6} className="text-center py-5 text-muted">
                                            <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                            Fetching evaluation scorecards...
                                        </td>
                                    </tr>
                                ) : !Array.isArray(reportData) || reportData.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="text-center py-5">
                                            <div className="py-4">
                                                <Scale size={42} className="text-muted mb-2 opacity-50" />
                                                <h6 className="fw-bold text-dark">No Submitted Evaluations Yet</h6>
                                                <p className="text-muted small mx-auto mb-3" style={{ maxWidth: 460 }}>
                                                    Appoint jury members to review incoming leadership nomination requests. Jurors will receive their evaluation portal access link via email and can score candidates directly.
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setIsAssignModal(true);
                                                        loadMembers();
                                                    }}
                                                    className="btn btn-primary btn-sm rounded-3 px-3.5 py-2 d-inline-flex align-items-center gap-1.5 shadow-xs"
                                                >
                                                    <UserCheck size={15} /> Appoint Jury Members Now
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    reportData
                                        .slice((evalPage - 1) * EVALS_PER_PAGE, evalPage * EVALS_PER_PAGE)
                                        .map((ev, idx) => {
                                            const candName = typeof ev.candidate_name === 'string'
                                                ? ev.candidate_name
                                                : (typeof ev.nomination?.candidate_name === 'string'
                                                    ? ev.nomination.candidate_name
                                                    : (typeof ev.nomination?.full_name === 'string' ? ev.nomination.full_name : 'Candidate'));
                                            const jurorName = typeof ev.jury_name === 'string'
                                                ? ev.jury_name
                                                : (typeof ev.juror?.display_name === 'string'
                                                    ? ev.juror.display_name
                                                    : (typeof ev.jury_user_id === 'string' ? ev.jury_user_id : 'Juror'));
                                            const totalScore = ev.report?.total_score ?? ev.report_data?.total_score ?? ev.total_score ?? 'N/A';
                                            const maxScore = ev.max_score ?? 100;
                                            const rawRec = ev.report?.overall_recommendation ?? ev.report_data?.overall_recommendation ?? ev.recommendation ?? 'Completed';
                                            const recText = typeof rawRec === 'string' ? rawRec.replace(/_/g, ' ') : 'Completed';
                                            const dateStr = ev.completed_at || ev.submitted_at;

                                            const candPhoto = ev.nomination?.profile_photo_url || ev.nomination?.photo_url || ev.candidate_photo_url || (ev as any).photo_url || null;
                                            const jurorPhoto = ev.juror?.profile_photo_url || ev.juror?.photo_url || (ev as any).juror_profile_photo_url || null;

                                            return (
                                                <tr key={ev.id || idx}>
                                                    <td className="fw-semibold text-dark">
                                                        <div className="d-flex align-items-center gap-2">
                                                            {candPhoto ? (
                                                                <img
                                                                    src={candPhoto}
                                                                    alt={candName}
                                                                    className="rounded-circle object-fit-cover border shadow-2xs flex-shrink-0"
                                                                    style={{ width: 28, height: 28 }}
                                                                    onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                />
                                                            ) : (
                                                                <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style={{ width: 28, height: 28, fontSize: 11 }}>
                                                                    {candName.charAt(0).toUpperCase()}
                                                                </div>
                                                            )}
                                                            <span>{candName}</span>
                                                        </div>
                                                    </td>
                                                    <td className="text-muted small">
                                                        <div className="d-flex align-items-center gap-2">
                                                            {jurorPhoto ? (
                                                                <img
                                                                    src={jurorPhoto}
                                                                    alt={jurorName}
                                                                    className="rounded-circle object-fit-cover border shadow-2xs flex-shrink-0"
                                                                    style={{ width: 24, height: 24 }}
                                                                    onError={(e) => { (e.currentTarget as HTMLElement).style.display = 'none'; }}
                                                                />
                                                            ) : null}
                                                            <span>{jurorName}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span className="h6 fw-bold text-primary mb-0">{totalScore}</span>
                                                        {totalScore !== 'N/A' && <span className="text-muted small"> / {maxScore} pts</span>}
                                                    </td>
                                                    <td>
                                                        <StatusBadge status={ev.status || 'completed'} />
                                                    </td>
                                                    <td>
                                                        <span className="badge bg-light text-dark border text-capitalize">
                                                            {recText}
                                                        </span>
                                                    </td>
                                                    <td className="small text-muted">
                                                        {dateStr ? new Date(dateStr).toLocaleDateString() : 'Pending'}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Evaluations Pagination */}
                    {reportData.length > EVALS_PER_PAGE && (
                        <div className="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 pt-3 border-top mt-3">
                            <span className="text-muted small">
                                Showing {(evalPage - 1) * EVALS_PER_PAGE + 1} to {Math.min(evalPage * EVALS_PER_PAGE, reportData.length)} of {reportData.length} evaluations
                            </span>
                            <div className="d-flex align-items-center gap-2">
                                <button
                                    type="button"
                                    className="btn btn-outline-secondary btn-sm rounded-3 px-2.5 py-1 d-flex align-items-center gap-1"
                                    disabled={evalPage <= 1}
                                    onClick={() => setEvalPage((p) => Math.max(1, p - 1))}
                                >
                                    <ChevronLeft size={14} /> Previous
                                </button>
                                <span className="small text-muted px-2">
                                    Page {evalPage} of {Math.ceil(reportData.length / EVALS_PER_PAGE)}
                                </span>
                                <button
                                    type="button"
                                    className="btn btn-outline-secondary btn-sm rounded-3 px-2.5 py-1 d-flex align-items-center gap-1"
                                    disabled={evalPage >= Math.ceil(reportData.length / EVALS_PER_PAGE)}
                                    onClick={() => setEvalPage((p) => Math.min(Math.ceil(reportData.length / EVALS_PER_PAGE), p + 1))}
                                >
                                    Next <ChevronRight size={14} />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* ASSIGN JURY MODAL */}
            {isAssignModal && (
                <div
                    className="modal show d-block"
                    tabIndex={-1}
                    style={{
                        position: 'fixed',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        backgroundColor: 'rgba(15, 23, 42, 0.75)',
                        backdropFilter: 'blur(4px)',
                        zIndex: 1055,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: '16px'
                    }}
                    onClick={() => setIsAssignModal(false)}
                >
                    <div
                        className="modal-dialog modal-lg"
                        style={{
                            width: '100%',
                            maxWidth: '720px',
                            margin: 0,
                            pointerEvents: 'auto',
                            maxHeight: '92vh',
                            display: 'flex',
                            flexDirection: 'column'
                        }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            className="modal-content border-0 shadow-lg rounded-4"
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
                            <form
                                onSubmit={handleAssignSubmit}
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    maxHeight: '92vh',
                                    height: '100%',
                                    overflow: 'hidden',
                                    margin: 0
                                }}
                            >
                                <div
                                    className="modal-header bg-light border-bottom p-4"
                                    style={{
                                        flexShrink: 0,
                                        padding: '18px 24px',
                                        backgroundColor: '#f8fafc',
                                        borderBottom: '1px solid #e2e8f0',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between'
                                    }}
                                >
                                    <div className="d-flex align-items-center gap-2.5">
                                        <div className="bg-primary text-white p-2.5 rounded-3 shadow-xs">
                                            <UserCheck size={20} />
                                        </div>
                                        <div>
                                            <h5 className="modal-title fw-bold text-dark mb-0">Appoint Jury Members</h5>
                                            <p className="text-muted small mb-0 mt-0.5">
                                                Select peers to evaluate candidate applications. They receive portal access & emails automatically.
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        className="btn-close"
                                        onClick={() => setIsAssignModal(false)}
                                    ></button>
                                </div>

                                <div
                                    className="modal-body p-4"
                                    style={{
                                        padding: '24px',
                                        overflowY: 'auto',
                                        flex: '1 1 auto',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: '16px'
                                    }}
                                >
                                    {/* Notice Box */}
                                    <div className="p-3 bg-primary-subtle border border-primary-subtle rounded-3 d-flex align-items-start gap-2.5">
                                        <CheckCircle2 size={18} className="text-primary mt-0.5 flex-shrink-0" />
                                        <div className="small text-dark">
                                            <strong>Standing Jury Assignment:</strong> Appointed jurors will evaluate all current candidate applications and will automatically be assigned to any future candidate nominations submitted from the website.
                                        </div>
                                    </div>

                                    {/* Juror Search & Selection */}
                                    <div>
                                        <div className="d-flex align-items-center justify-content-between mb-2">
                                            <label className="form-label fw-bold small text-dark mb-0">
                                                Select Peers as Jurors <span className="text-danger">*</span>
                                            </label>
                                            <div className="d-flex gap-2">
                                                <button
                                                    type="button"
                                                    onClick={selectAllFilteredJurors}
                                                    className="btn btn-link btn-sm text-decoration-none p-0 text-primary small fw-semibold"
                                                >
                                                    Select All
                                                </button>
                                                <span className="text-muted">|</span>
                                                <button
                                                    type="button"
                                                    onClick={clearJurorSelection}
                                                    className="btn btn-link btn-sm text-decoration-none p-0 text-muted small"
                                                >
                                                    Clear
                                                </button>
                                            </div>
                                        </div>

                                        <div className="input-group mb-2.5">
                                            <span className="input-group-text bg-white border-end-0">
                                                <Search size={15} className="text-muted" />
                                            </span>
                                            <input
                                                type="text"
                                                className="form-control border-start-0 ps-0"
                                                placeholder="Search peers by name, email, company, or category..."
                                                value={jurorSearch}
                                                onChange={(e) => setJurorSearch(e.target.value)}
                                            />
                                            {jurorSearch && (
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-secondary border-start-0"
                                                    onClick={() => setJurorSearch('')}
                                                >
                                                    <X size={14} />
                                                </button>
                                            )}
                                        </div>

                                        {/* Peers List */}
                                        <div
                                            style={{
                                                maxHeight: '220px',
                                                minHeight: '100px',
                                                overflowY: 'auto',
                                                overflowX: 'hidden',
                                                backgroundColor: '#f8fafc',
                                                border: '1px solid #e2e8f0',
                                                borderRadius: '8px',
                                                padding: '8px'
                                            }}
                                        >
                                            {isSearchingMembers ? (
                                                <div className="text-center py-4 text-muted small">
                                                    <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                                    Loading peers directory...
                                                </div>
                                            ) : filteredMembers.length === 0 ? (
                                                <div className="text-center py-4 text-muted small">
                                                    No peers found matching &ldquo;{jurorSearch}&rdquo;.
                                                </div>
                                            ) : (
                                                filteredMembers.slice(0, visibleJurorsCount).map((peer) => {
                                                    const isChecked = selectedJurorIds.includes(peer.id);
                                                    const displayName =
                                                        peer.display_name ||
                                                        `${peer.first_name || ''} ${peer.last_name || ''}`.trim() ||
                                                        'Peer Member';
                                                    const company =
                                                        peer.business_name ||
                                                        peer.company_name ||
                                                        peer.designation ||
                                                        peer.business_category ||
                                                        'Enterprise Member';

                                                    return (
                                                        <div
                                                            key={peer.id}
                                                            onClick={() => toggleJurorSelection(peer.id)}
                                                            style={{
                                                                display: 'flex',
                                                                alignItems: 'center',
                                                                justifyContent: 'space-between',
                                                                padding: '10px 12px',
                                                                borderRadius: '8px',
                                                                marginBottom: '6px',
                                                                cursor: 'pointer',
                                                                backgroundColor: isChecked ? '#eff6ff' : '#ffffff',
                                                                border: isChecked ? '1px solid #3b82f6' : '1px solid #e2e8f0',
                                                                transition: 'all 0.15s ease'
                                                            }}
                                                        >
                                                            <div className="d-flex align-items-center gap-2.5">
                                                                <input
                                                                    type="checkbox"
                                                                    className="form-check-input mt-0"
                                                                    checked={isChecked}
                                                                    onChange={() => { }}
                                                                />
                                                                <div
                                                                    className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold text-xs"
                                                                    style={{ width: 32, height: 32, fontSize: 11, flexShrink: 0 }}
                                                                >
                                                                    {displayName.substring(0, 2).toUpperCase()}
                                                                </div>
                                                                <div>
                                                                    <div className="fw-semibold text-dark small lh-sm">
                                                                        {displayName}
                                                                    </div>
                                                                    <div className="text-muted" style={{ fontSize: 11 }}>
                                                                        {company} {peer.email && `• ${peer.email}`}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            {isChecked && (
                                                                <span className="badge bg-primary text-white rounded-pill px-2 py-0.5 text-xs">
                                                                    Selected
                                                                </span>
                                                            )}
                                                        </div>
                                                    );
                                                })
                                            )}

                                            {filteredMembers.length > visibleJurorsCount && (
                                                <button
                                                    type="button"
                                                    onClick={() => setVisibleJurorsCount((prev) => prev + 15)}
                                                    className="btn btn-link btn-sm w-100 text-decoration-none text-primary small py-1"
                                                >
                                                    Show more peers ({filteredMembers.length - visibleJurorsCount} remaining)
                                                </button>
                                            )}
                                        </div>

                                        <div className="d-flex align-items-center justify-content-between mt-2 text-muted small">
                                            <span>
                                                Selected: <strong className="text-primary">{selectedJurorIds.length}</strong> juror(s)
                                            </span>
                                            <span>Total in directory: {filteredMembers.length}</span>
                                        </div>
                                    </div>

                                    {/* Review Deadline */}
                                    <div>
                                        <label className="form-label fw-bold small text-dark mb-1">
                                            Evaluation Review Deadline
                                        </label>
                                        <div className="input-group">
                                            <span className="input-group-text bg-white">
                                                <Calendar size={14} className="text-muted" />
                                            </span>
                                            <input
                                                type="datetime-local"
                                                className="form-control"
                                                value={assignDeadline}
                                                onChange={(e) => setAssignDeadline(e.target.value)}
                                            />
                                        </div>
                                        <div className="form-text small text-muted">
                                            Jurors are notified of this deadline in their appointment email.
                                        </div>
                                    </div>

                                    {/* Remarks / Instructions */}
                                    <div>
                                        <label className="form-label fw-bold small text-dark mb-1">
                                            Instructions & Remarks for Jurors
                                        </label>
                                        <textarea
                                            className="form-control"
                                            rows={2}
                                            placeholder="Provide any instructions or scoring focus areas for the appointed jurors..."
                                            value={assignRemarks}
                                            onChange={(e) => setAssignRemarks(e.target.value)}
                                        />
                                    </div>

                                    {/* Juror Portal Link Preview */}
                                    <div className="p-3 bg-light rounded-3 border">
                                        <div className="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div className="fw-semibold text-dark small">Juror Evaluation Portal URL:</div>
                                                <div className="font-monospace text-primary small mt-0.5">
                                                    {juryPanelUrl}
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => copyJuryPanelLink('modal')}
                                                className="btn btn-outline-primary btn-sm rounded-3 px-2.5 py-1 d-flex align-items-center gap-1"
                                            >
                                                {copiedJuryLink === 'modal' ? <Check size={13} /> : <Copy size={13} />}
                                                {copiedJuryLink === 'modal' ? 'Copied' : 'Copy'}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    className="modal-footer bg-light border-top p-3.5"
                                    style={{
                                        flexShrink: 0,
                                        padding: '14px 24px',
                                        backgroundColor: '#f8fafc',
                                        borderTop: '1px solid #e2e8f0',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'flex-end',
                                        gap: '10px'
                                    }}
                                >
                                    <button
                                        type="button"
                                        className="btn btn-light border rounded-3 px-3.5 py-2"
                                        onClick={() => setIsAssignModal(false)}
                                        disabled={isSubmittingAssign}
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        className="btn btn-primary rounded-3 px-4 py-2 d-flex align-items-center gap-2 fw-semibold shadow-xs"
                                        disabled={isSubmittingAssign || selectedJurorIds.length === 0}
                                    >
                                        {isSubmittingAssign ? (
                                            <>
                                                <Loader2 size={16} className="spinner-border spinner-border-sm" />
                                                Appointing Jurors...
                                            </>
                                        ) : (
                                            <>
                                                <UserCheck size={16} />
                                                Confirm & Appoint {selectedJurorIds.length > 0 ? `(${selectedJurorIds.length}) Jurors` : 'Jury'}
                                            </>
                                        )}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </LeadershipLayout>
    );
};
