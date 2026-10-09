import React, { useEffect, useState } from 'react';
import { decisionApi, campaignApi, nominationApi } from '../../api/services';
import { Campaign, Nomination, FinalDecision } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { ConfirmModal } from '../../components/common/ConfirmModal';
import { Trophy, Award, ShieldCheck, CheckCircle2, Clock, XCircle, AlertCircle } from 'lucide-react';

export const FinalDecisionsPage: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('');
    const [candidates, setCandidates] = useState<Nomination[]>([]);
    const [decisions, setDecisions] = useState<FinalDecision[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Decision Modal
    const [selectedCandidate, setSelectedCandidate] = useState<Nomination | null>(null);
    const [decisionForm, setDecisionForm] = useState({
        decision: 'selected' as 'selected' | 'runner_up' | 'standby' | 'rejected' | 'deferred',
        rank: 1,
        remarks: '',
    });
    const [isSavingDecision, setIsSavingDecision] = useState<boolean>(false);

    // Publish Winners Confirmation
    const [isPublishWinnersOpen, setIsPublishWinnersOpen] = useState<boolean>(false);
    const [isPublishing, setIsPublishing] = useState<boolean>(false);

    const loadData = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const campRes = await campaignApi.getAll();
            const list = campRes.data.data || [];
            setCampaigns(list);
            if (list.length > 0 && !selectedCampaignId) {
                setSelectedCampaignId(list[0].id);
            }
        } catch {
            setError('Failed to load campaigns.');
        } finally {
            setIsLoading(false);
        }
    };

    const loadCampaignCandidates = async (campaignId: string) => {
        if (!campaignId) return;
        setIsLoading(true);
        try {
            const [nomRes, decRes] = await Promise.all([
                nominationApi.getAll({ campaign_id: campaignId, status: 'shortlisted' }),
                decisionApi.getByCampaign(campaignId),
            ]);
            setCandidates(nomRes.data.data || []);
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

    const handleSaveDecision = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedCandidate) return;
        setIsSavingDecision(true);
        try {
            await decisionApi.submit({
                campaign_id: selectedCampaignId,
                nomination_id: selectedCandidate.id,
                decision: decisionForm.decision,
                rank: decisionForm.rank,
                remarks: decisionForm.remarks,
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

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Final Decision Management</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Consolidated evaluation scoring, leadership selection declaration, and official winner publishing
                    </p>
                </div>
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
                                        No shortlisted candidates found for this campaign.
                                    </td>
                                </tr>
                            ) : (
                                candidates.map((cand) => {
                                    const dec = decisions.find((d) => d.nomination_id === cand.id);
                                    return (
                                        <tr key={cand.id}>
                                            <td className="fw-semibold text-dark">{cand.candidate_name}</td>
                                            <td className="font-monospace small text-muted">{cand.application_number}</td>
                                            <td>{cand.applied_role_name || 'Leadership Role'}</td>
                                            <td>
                                                {dec ? (
                                                    <StatusBadge status={dec.decision} />
                                                ) : (
                                                    <span className="badge bg-warning-subtle text-warning">Pending Decision</span>
                                                )}
                                            </td>
                                            <td className="fw-bold">{dec?.rank ? `#${dec.rank}` : '-'}</td>
                                            <td className="small text-muted">{dec?.remarks || '-'}</td>
                                            <td className="text-end">
                                                <button
                                                    onClick={() => {
                                                        setSelectedCandidate(cand);
                                                        setDecisionForm({
                                                            decision: dec?.decision || 'selected',
                                                            rank: dec?.rank || 1,
                                                            remarks: dec?.remarks || '',
                                                        });
                                                    }}
                                                    className="btn btn-outline-primary btn-sm rounded-2 py-1 px-2.5 fw-semibold"
                                                >
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
            </div>

            {/* Record Decision Modal */}
            {selectedCandidate && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-dialog-centered">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <form onSubmit={handleSaveDecision}>
                                <div className="modal-header border-bottom-0 pb-0">
                                    <h5 className="modal-title fw-bold text-dark">Record Final Selection Decision</h5>
                                    <button type="button" className="btn-close" onClick={() => setSelectedCandidate(null)}></button>
                                </div>
                                <div className="modal-body py-3">
                                    <p className="text-muted small mb-3">
                                        Candidate: <strong>{selectedCandidate.candidate_name}</strong> (
                                        {selectedCandidate.application_number})
                                    </p>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">Selection Decision *</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={decisionForm.decision}
                                            onChange={(e) =>
                                                setDecisionForm({ ...decisionForm, decision: e.target.value as any })
                                            }
                                        >
                                            <option value="selected">Selected as Winner</option>
                                            <option value="runner_up">Runner Up</option>
                                            <option value="standby">Standby / Alternate</option>
                                            <option value="deferred">Deferred for Next Cycle</option>
                                            <option value="rejected">Not Selected</option>
                                        </select>
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">Rank / Position</label>
                                        <input
                                            type="number"
                                            className="form-control form-control-sm"
                                            value={decisionForm.rank}
                                            onChange={(e) =>
                                                setDecisionForm({ ...decisionForm, rank: parseInt(e.target.value) || 1 })
                                            }
                                        />
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">Official Remarks / Justification</label>
                                        <textarea
                                            className="form-control form-control-sm"
                                            rows={3}
                                            placeholder="Provide leadership committee remarks..."
                                            value={decisionForm.remarks}
                                            onChange={(e) => setDecisionForm({ ...decisionForm, remarks: e.target.value })}
                                        />
                                    </div>
                                </div>
                                <div className="modal-footer border-top-0 pt-0">
                                    <button type="button" className="btn btn-light" onClick={() => setSelectedCandidate(null)}>
                                        Cancel
                                    </button>
                                    <button type="submit" className="btn btn-primary fw-semibold px-4" disabled={isSavingDecision}>
                                        {isSavingDecision ? 'Saving...' : 'Save Decision'}
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
    );
};
