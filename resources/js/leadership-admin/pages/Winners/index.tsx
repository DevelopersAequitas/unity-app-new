import React, { useEffect, useState } from 'react';
import { decisionApi, campaignApi, creativeApi } from '../../api/services';
import { Campaign, FinalDecision, WinnerCreative } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { Trophy, Award, Image, Download, ExternalLink, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';

export const WinnersPage: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('');
    const [winners, setWinners] = useState<FinalDecision[]>([]);
    const [creatives, setCreatives] = useState<WinnerCreative[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

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

    const loadWinners = async (campaignId: string) => {
        if (!campaignId) return;
        setIsLoading(true);
        setError(null);
        try {
            const [decRes, creatRes] = await Promise.all([
                decisionApi.getByCampaign(campaignId),
                creativeApi.getByCampaign(campaignId),
            ]);
            const selectedList = (decRes.data.data || []).filter((d) => d.decision === 'selected');
            setWinners(selectedList);
            setCreatives(creatRes.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to fetch winners.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadCampaigns();
    }, []);

    useEffect(() => {
        if (selectedCampaignId) {
            loadWinners(selectedCampaignId);
        }
    }, [selectedCampaignId]);

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Declared Winners</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Official elected leaders, declared leadership statements, and announcement creatives
                    </p>
                </div>
                <Link to="/creatives" className="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 fw-semibold">
                    <Sparkles size={14} /> Manage Creative Banners
                </Link>
            </div>

            {error && (
                <div className="alert alert-danger rounded-4 border-0 shadow-xs p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>{error}</div>
                    <button className="btn btn-sm btn-outline-danger" onClick={() => setError(null)}>
                        Dismiss
                    </button>
                </div>
            )}

            {/* Campaign Filter */}
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
                                    {c.name} ({c.year})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* Winners Cards Grid */}
            <div className="row g-4 mb-4">
                {isLoading ? (
                    <div className="col-12 text-center py-5 text-muted">
                        <div className="spinner-border spinner-border-sm text-primary me-2"></div> Loading declared winners...
                    </div>
                ) : winners.length === 0 ? (
                    <div className="col-12 text-center py-5 text-muted">
                        No winners officially declared for this campaign yet.
                    </div>
                ) : (
                    winners.map((w) => {
                        const creative = creatives.find((c) => c.decision_id === w.id);
                        return (
                            <div key={w.id} className="col-12 col-md-6 col-lg-4">
                                <div className="card border-0 shadow-xs rounded-4 p-4 bg-white position-relative overflow-hidden h-100 text-center">
                                    <div className="d-flex justify-content-center mb-3">
                                        <div
                                            className="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm"
                                            style={{
                                                width: '68px',
                                                height: '68px',
                                                background: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                                            }}
                                        >
                                            <Trophy size={32} />
                                        </div>
                                    </div>

                                    <h5 className="fw-bold text-dark mb-1">{w.candidate_name || 'Elected Leader'}</h5>
                                    <span className="badge bg-indigo-subtle text-indigo rounded-pill px-3 py-1 mb-2">
                                        Rank #{w.rank || 1} Selected Winner
                                    </span>

                                    {w.remarks && <p className="text-muted small mt-2 px-2 italic">"{w.remarks}"</p>}

                                    <div className="pt-3 mt-3 border-top d-flex justify-content-between align-items-center">
                                        <StatusBadge status={w.is_published ? 'published' : 'draft'} label={w.is_published ? 'Published' : 'Unpublished'} />
                                        {creative ? (
                                            <a
                                                href={creative.image_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="btn btn-outline-primary btn-sm rounded-2 d-flex align-items-center gap-1"
                                            >
                                                <Image size={14} /> Creative
                                            </a>
                                        ) : (
                                            <span className="text-muted small">No banner</span>
                                        )}
                                    </div>
                                    <div className="position-absolute top-0 start-0 end-0" style={{ height: '4px', background: 'linear-gradient(90deg, #f59e0b, #10b981)' }}></div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
};
