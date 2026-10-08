import React, { useEffect, useState } from 'react';
import { votingApi, campaignApi, reportApi } from '../../api/services';
import { Campaign } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { Vote, Users, ExternalLink, Copy, Check, Download, AlertCircle, RefreshCw } from 'lucide-react';

interface CandidateVoteTally {
    nomination_id: string;
    candidate_name: string;
    application_number: string;
    applied_role_name?: string;
    vote_count: number;
    result_token?: string;
    token_status?: string;
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

    useEffect(() => {
        loadCampaigns();
    }, []);

    useEffect(() => {
        if (selectedCampaignId) {
            loadTally(selectedCampaignId);
        }
    }, [selectedCampaignId]);

    const handleGenerateResultLink = async (nominationId: string) => {
        setIsGeneratingToken(nominationId);
        try {
            const res = await votingApi.generateResultToken(nominationId, 30);
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
                        Real-time duplicate-resistant public voting tally, verified audit counters, and private candidate scorecard access
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
                        onClick={() => loadTally(selectedCampaignId)}
                        disabled={isLoading}
                        className="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs"
                    >
                        <RefreshCw size={14} className={isLoading ? 'spinner-border spinner-border-sm' : ''} /> Refresh Tally
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
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                <div className="d-flex align-items-center justify-content-between mb-3">
                    <h5 className="fw-bold text-dark mb-0">Live Candidate Scorecard</h5>
                    <span className="text-muted small">Privacy Notice: Voter phone numbers and identities are protected.</span>
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
        </div>
    );
};
