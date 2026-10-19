import React, { useEffect, useState } from 'react';
import { votingApi, campaignApi, reportApi } from '../../api/services';
import { Campaign } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import {
    Vote,
    Users,
    ExternalLink,
    Copy,
    Check,
    Download,
    AlertCircle,
    RefreshCw,
    Search,
    Filter,
    Mail,
    Phone,
    MapPin,
    Building,
    Calendar,
    ShieldCheck,
    Globe,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface CandidateVoteTally {
    nomination_id: string;
    candidate_name: string;
    application_number: string;
    applied_role_name?: string;
    vote_count: number;
    result_token?: string;
    token_status?: string;
}

interface DetailedVote {
    id: string;
    vote_reference: string;
    campaign_id?: string;
    nomination_id?: string;
    candidate_name: string;
    candidate_email?: string;
    voter_name: string;
    voter_email: string;
    voter_phone?: string;
    voter_company?: string;
    voter_designation?: string;
    voter_city?: string;
    support_reason?: string;
    created_at_human?: string;
    channel: 'Website' | 'App';
}

export const VotingPage: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('');
    const [tallies, setTallies] = useState<CandidateVoteTally[]>([]);
    const [totalVotes, setTotalVotes] = useState<number>(0);
    const [uniqueVoters, setUniqueVoters] = useState<number>(0);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const [copiedToken, setCopiedToken] = useState<string | null>(null);
    const [isGeneratingToken, setIsGeneratingToken] = useState<string | null>(null);

    // Detailed Voter Audit State ("Who Voted Whom")
    const [votes, setVotes] = useState<DetailedVote[]>([]);
    const [votesTotal, setVotesTotal] = useState<number>(0);
    const [votesPage, setVotesPage] = useState<number>(1);
    const [votesLastPage, setVotesLastPage] = useState<number>(1);
    const [isVotesLoading, setIsVotesLoading] = useState<boolean>(false);
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [selectedNomineeFilter, setSelectedNomineeFilter] = useState<string>('');

    const loadCampaigns = async () => {
        try {
            const res = await campaignApi.getAll();
            const list = res.data.data || [];
            setCampaigns(list);
            if (list.length > 0 && !selectedCampaignId) {
                setSelectedCampaignId(list[0].id);
            }
        } catch {
            setError('Failed to load campaigns.');
        }
    };

    const loadTally = async (campaignId: string) => {
        if (!campaignId) return;
        setIsLoading(true);
        setError(null);
        try {
            const res = await votingApi.getTally(campaignId);
            const data = res.data.data || {};
            setTallies(data.candidates || data.tallies || []);
            setTotalVotes(data.total_votes || 0);
            setUniqueVoters(data.unique_voters || 0);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load voting tally from backend.');
        } finally {
            setIsLoading(false);
        }
    };

    const loadDetailedVotes = async (campaignId: string, page = 1, search = '', nominationId = '') => {
        if (!campaignId) return;
        setIsVotesLoading(true);
        try {
            const res = await votingApi.getDetailedVotes(campaignId, {
                page,
                per_page: 15,
                search: search.trim() || undefined,
                nomination_id: nominationId || undefined,
            });
            const data = res.data.data || {};
            setVotes(data.data || []);
            setVotesTotal(data.total || 0);
            setVotesPage(data.current_page || 1);
            setVotesLastPage(data.last_page || 1);
        } catch {
            // Keep existing votes if error
        } finally {
            setIsVotesLoading(false);
        }
    };

    useEffect(() => {
        loadCampaigns();
    }, []);

    useEffect(() => {
        if (selectedCampaignId) {
            loadTally(selectedCampaignId);
            loadDetailedVotes(selectedCampaignId, 1, searchQuery, selectedNomineeFilter);
        }
    }, [selectedCampaignId]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        loadDetailedVotes(selectedCampaignId, 1, searchQuery, selectedNomineeFilter);
    };

    const handleNomineeFilterChange = (nomId: string) => {
        setSelectedNomineeFilter(nomId);
        loadDetailedVotes(selectedCampaignId, 1, searchQuery, nomId);
    };

    const handleRefreshAll = () => {
        if (selectedCampaignId) {
            loadTally(selectedCampaignId);
            loadDetailedVotes(selectedCampaignId, votesPage, searchQuery, selectedNomineeFilter);
        }
    };

    const handleGenerateResultLink = async (nominationId: string) => {
        setIsGeneratingToken(nominationId);
        try {
            await votingApi.generateResultToken(nominationId, 30);
            await loadTally(selectedCampaignId);
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to generate candidate result link.');
        } finally {
            setIsGeneratingToken(null);
        }
    };

    const copyToClipboard = (token: string) => {
        const fullUrl = `${window.location.origin}/candidate/results/${token}`;
        navigator.clipboard.writeText(fullUrl);
        setCopiedToken(token);
        setTimeout(() => setCopiedToken(null), 3000);
    };

    const selectedCampaign = campaigns.find((c) => c.id === selectedCampaignId);

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Voting Management</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Real-time duplicate-resistant public voting tally, voter verification records, and candidate scorecard access
                    </p>
                </div>
                <div className="d-flex align-items-center gap-2">
                    {selectedCampaignId && (
                        <a
                            href={reportApi.exportVotesUrl(selectedCampaignId)}
                            className="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5"
                            download
                        >
                            <Download size={14} /> Export Vote Audit CSV
                        </a>
                    )}
                    <button
                        onClick={handleRefreshAll}
                        disabled={isLoading || isVotesLoading}
                        className="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs"
                    >
                        <RefreshCw size={14} className={isLoading || isVotesLoading ? 'spinner-border spinner-border-sm' : ''} /> Refresh Data
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

            {/* Campaign Selection Bar */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-3 mb-4">
                <div className="row g-3 align-items-center">
                    <div className="col-12 col-md-6">
                        <label className="form-label text-muted small fw-semibold text-uppercase tracking-wider mb-1">
                            Select Campaign to Monitor:
                        </label>
                        <select
                            className="form-select form-select-sm rounded-3"
                            value={selectedCampaignId}
                            onChange={(e) => setSelectedCampaignId(e.target.value)}
                        >
                            {campaigns.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name} ({c.year}) - {c.status.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </div>

                    {selectedCampaign && (
                        <div className="col-12 col-md-6 d-flex gap-3 justify-content-md-end align-items-center pt-2">
                            <div className="text-center px-3 py-1 bg-light rounded-3 border">
                                <span className="text-muted small">Total Verified Votes</span>
                                <div className="h4 fw-bold text-dark mb-0">{totalVotes}</div>
                            </div>
                            <div className="text-center px-3 py-1 bg-light rounded-3 border">
                                <span className="text-muted small">Unique Voters</span>
                                <div className="h4 fw-bold text-primary mb-0">{uniqueVoters}</div>
                            </div>
                            <div className="text-center px-3 py-1 bg-light rounded-3 border">
                                <span className="text-muted small">Voting Status</span>
                                <div>
                                    <StatusBadge status={selectedCampaign.status} />
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Candidate Vote Tally Table */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4 mb-4">
                <div className="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 className="fw-bold text-dark mb-0">Live Candidate Scorecard</h5>
                        <p className="text-muted small mb-0">Summary tally per nominee with percentage share and private links</p>
                    </div>
                    <span className="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill small">
                        {tallies.length} Nominees Active
                    </span>
                </div>

                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>Candidate</th>
                                <th>Application #</th>
                                <th>Total Verified Votes</th>
                                <th>Vote Share (%)</th>
                                <th>Private Result Link</th>
                                <th className="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Fetching voting counts...
                                    </td>
                                </tr>
                            ) : tallies.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-5 text-muted">
                                        No votes or shortlisted candidates recorded for this campaign yet.
                                    </td>
                                </tr>
                            ) : (
                                tallies.map((item) => {
                                    const percent = totalVotes > 0 ? Math.round((item.vote_count / totalVotes) * 100) : 0;
                                    return (
                                        <tr key={item.nomination_id}>
                                            <td className="fw-semibold text-dark">{item.candidate_name}</td>
                                            <td className="font-monospace small text-muted">{item.application_number}</td>
                                            <td>
                                                <div className="d-flex align-items-center gap-2">
                                                    <span className="h6 fw-bold text-primary mb-0">{item.vote_count}</span>
                                                    <span className="text-muted small">votes</span>
                                                </div>
                                            </td>
                                            <td style={{ width: '200px' }}>
                                                <div className="d-flex align-items-center gap-2">
                                                    <div className="progress flex-grow-1" style={{ height: '6px' }}>
                                                        <div
                                                            className="progress-bar bg-primary"
                                                            role="progressbar"
                                                            style={{ width: `${percent}%` }}
                                                        ></div>
                                                    </div>
                                                    <span className="small fw-semibold text-muted">{percent}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                {item.result_token ? (
                                                    <div className="d-flex align-items-center gap-2">
                                                        <span className="badge bg-success-subtle text-success">Link Active</span>
                                                        <button
                                                            onClick={() => copyToClipboard(item.result_token!)}
                                                            className="btn btn-outline-secondary btn-sm py-0.5 px-2 d-flex align-items-center gap-1"
                                                            title="Copy private URL"
                                                        >
                                                            {copiedToken === item.result_token ? (
                                                                <>
                                                                    <Check size={12} className="text-success" /> Copied
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Copy size={12} /> Copy URL
                                                                </>
                                                            )}
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted small">Not generated</span>
                                                )}
                                            </td>
                                            <td className="text-end">
                                                <button
                                                    onClick={() => handleGenerateResultLink(item.nomination_id)}
                                                    disabled={isGeneratingToken === item.nomination_id}
                                                    className="btn btn-outline-primary btn-sm rounded-2 py-1 px-2.5 fw-semibold"
                                                >
                                                    {isGeneratingToken === item.nomination_id
                                                        ? 'Generating...'
                                                        : item.result_token
                                                        ? 'Regenerate Link'
                                                        : 'Generate Link'}
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Detailed Voter Audit Table ("Who Voted Whom") */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
                    <div>
                        <div className="d-flex align-items-center gap-2">
                            <h5 className="fw-bold text-dark mb-0">Voter Audit Log (Who Voted Whom)</h5>
                            <span className="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill small">
                                {votesTotal} Recorded Votes
                            </span>
                        </div>
                        <p className="text-muted small mb-0 mt-0.5">
                            Individual verified vote transactions including voter identity, nominee selected, reason, and reference ID
                        </p>
                    </div>

                    {/* Filter & Search Controls */}
                    <div className="d-flex flex-wrap align-items-center gap-2">
                        {/* Nominee Filter Dropdown */}
                        <div className="d-flex align-items-center gap-1">
                            <Filter size={14} className="text-muted" />
                            <select
                                className="form-select form-select-sm rounded-3"
                                style={{ minWidth: '160px' }}
                                value={selectedNomineeFilter}
                                onChange={(e) => handleNomineeFilterChange(e.target.value)}
                            >
                                <option value="">All Nominees</option>
                                {tallies.map((t) => (
                                    <option key={t.nomination_id} value={t.nomination_id}>
                                        {t.candidate_name} ({t.vote_count} votes)
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Search Input */}
                        <form onSubmit={handleSearchSubmit} className="d-flex align-items-center gap-1">
                            <div className="input-group input-group-sm">
                                <span className="input-group-text bg-light border-end-0 rounded-start-3">
                                    <Search size={14} className="text-muted" />
                                </span>
                                <input
                                    type="text"
                                    className="form-control border-start-0 ps-0"
                                    placeholder="Search voter, email, city..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                />
                            </div>
                            <button type="submit" className="btn btn-sm btn-primary rounded-3 px-3">
                                Search
                            </button>
                        </form>
                    </div>
                </div>

                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>Voter Information</th>
                                <th>Organization / City</th>
                                <th>Nominee Voted For</th>
                                <th>Endorsement Reason</th>
                                <th>Reference Code</th>
                                <th>Channel</th>
                                <th>Voted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isVotesLoading ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Loading voter audit entries...
                                    </td>
                                </tr>
                            ) : votes.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-5 text-muted">
                                        <div className="mb-2">
                                            <Vote size={32} className="text-muted opacity-50" />
                                        </div>
                                        No voter records found matching the current filters.
                                    </td>
                                </tr>
                            ) : (
                                votes.map((v) => (
                                    <tr key={v.id || v.vote_reference}>
                                        <td>
                                            <div className="fw-semibold text-dark">{v.voter_name}</div>
                                            <div className="d-flex flex-column gap-0.5 mt-0.5">
                                                {v.voter_email && (
                                                    <span className="small text-muted d-flex align-items-center gap-1">
                                                        <Mail size={12} className="text-muted" /> {v.voter_email}
                                                    </span>
                                                )}
                                                {v.voter_phone && (
                                                    <span className="small text-muted d-flex align-items-center gap-1">
                                                        <Phone size={12} className="text-muted" /> {v.voter_phone}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td>
                                            {v.voter_company || v.voter_designation ? (
                                                <div className="d-flex align-items-center gap-1.5 text-dark small fw-medium">
                                                    <Building size={13} className="text-muted flex-shrink-0" />
                                                    <span>
                                                        {v.voter_designation ? `${v.voter_designation}, ` : ''}
                                                        {v.voter_company}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-muted small">-</span>
                                            )}
                                            {v.voter_city && (
                                                <div className="mt-1">
                                                    <span className="badge bg-light text-secondary border small px-2 py-0.5 rounded-pill d-inline-flex align-items-center gap-1">
                                                        <MapPin size={10} /> {v.voter_city}
                                                    </span>
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            <div className="d-inline-flex flex-column">
                                                <span className="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-semibold text-start">
                                                    {v.candidate_name}
                                                </span>
                                                {v.candidate_email && (
                                                    <span className="small text-muted mt-1" style={{ fontSize: '11px' }}>
                                                        {v.candidate_email}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td style={{ maxWidth: '240px' }}>
                                            {v.support_reason ? (
                                                <p className="small text-dark mb-0 fst-italic text-truncate" title={v.support_reason}>
                                                    "{v.support_reason}"
                                                </p>
                                            ) : (
                                                <span className="text-muted small">-</span>
                                            )}
                                        </td>
                                        <td>
                                            <span className="font-monospace small bg-light border px-2 py-1 rounded-2 text-dark">
                                                {v.vote_reference}
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                className={`badge ${
                                                    v.channel === 'Website'
                                                        ? 'bg-info-subtle text-info border border-info-subtle'
                                                        : 'bg-secondary-subtle text-secondary border'
                                                } rounded-pill px-2 py-0.5 small d-inline-flex align-items-center gap-1`}
                                            >
                                                {v.channel === 'Website' ? <Globe size={11} /> : <Users size={11} />}
                                                {v.channel}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="small text-muted d-flex align-items-center gap-1">
                                                <Calendar size={12} /> {v.created_at_human || '-'}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination Controls */}
                {votesLastPage > 1 && (
                    <div className="d-flex align-items-center justify-content-between pt-3 border-top mt-3">
                        <span className="text-muted small">
                            Showing {(votesPage - 1) * 15 + 1} to {Math.min(votesPage * 15, votesTotal)} of {votesTotal} records
                        </span>
                        <div className="d-flex align-items-center gap-2">
                            <button
                                className="btn btn-outline-secondary btn-sm rounded-3 px-2.5 py-1 d-flex align-items-center gap-1"
                                disabled={votesPage <= 1 || isVotesLoading}
                                onClick={() => loadDetailedVotes(selectedCampaignId, votesPage - 1, searchQuery, selectedNomineeFilter)}
                            >
                                <ChevronLeft size={14} /> Previous
                            </button>
                            <span className="small text-muted px-2">
                                Page {votesPage} of {votesLastPage}
                            </span>
                            <button
                                className="btn btn-outline-secondary btn-sm rounded-3 px-2.5 py-1 d-flex align-items-center gap-1"
                                disabled={votesPage >= votesLastPage || isVotesLoading}
                                onClick={() => loadDetailedVotes(selectedCampaignId, votesPage + 1, searchQuery, selectedNomineeFilter)}
                            >
                                Next <ChevronRight size={14} />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};
