import React, { useEffect, useState } from 'react';
import { creativeApi, campaignApi, decisionApi } from '../../api/services';
import { Campaign, FinalDecision, WinnerCreative } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { Image, Sparkles, Download, RefreshCw, Plus, CheckCircle2 } from 'lucide-react';

export const CreativesPage: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('');
    const [creatives, setCreatives] = useState<WinnerCreative[]>([]);
    const [winners, setWinners] = useState<FinalDecision[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Generation Modal
    const [isGenerateModal, setIsGenerateModal] = useState<boolean>(false);
    const [genData, setGenData] = useState({
        decision_id: '',
        template_name: 'official_announcement_gold',
        format: 'png',
    });
    const [isGenerating, setIsGenerating] = useState<boolean>(false);

    const loadCampaigns = async () => {
        try {
            const res = await campaignApi.getAll();
            const list = res.data.data || [];
            setCampaigns(list);
            if (list.length > 0 && !selectedCampaignId) {
                setSelectedCampaignId(list[0].id);
            }
        } catch {
            setError('Failed to fetch campaigns.');
        }
    };

    const loadCreatives = async (campaignId: string) => {
        if (!campaignId) return;
        setIsLoading(true);
        setError(null);
        try {
            const [creatRes, decRes] = await Promise.all([
                creativeApi.getByCampaign(campaignId),
                decisionApi.getByCampaign(campaignId),
            ]);
            setCreatives(creatRes.data.data || []);
            setWinners((decRes.data.data || []).filter((d) => d.decision === 'selected'));
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load creatives.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadCampaigns();
    }, []);

    useEffect(() => {
        if (selectedCampaignId) {
            loadCreatives(selectedCampaignId);
        }
    }, [selectedCampaignId]);

    const handleGenerateSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsGenerating(true);
        try {
            await creativeApi.generate({
                campaign_id: selectedCampaignId,
                decision_id: genData.decision_id,
                template_name: genData.template_name,
            });
            setIsGenerateModal(false);
            loadCreatives(selectedCampaignId);
            alert('Winner announcement creative generated successfully!');
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to generate creative.');
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Creative Management</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Generate official winner announcement posters, social media banners, and digital certificates
                    </p>
                </div>
                <button
                    onClick={() => setIsGenerateModal(true)}
                    className="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 fw-semibold shadow-xs"
                >
                    <Sparkles size={16} /> Generate Winner Creative
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

            {/* Creatives Grid */}
            <div className="row g-4 mb-4">
                {isLoading ? (
                    <div className="col-12 text-center py-5 text-muted">
                        <div className="spinner-border spinner-border-sm text-primary me-2"></div> Loading creative assets...
                    </div>
                ) : creatives.length === 0 ? (
                    <div className="col-12 text-center py-5 text-muted">
                        No announcement creatives generated for this campaign yet. Click "Generate Winner Creative" to create one.
                    </div>
                ) : (
                    creatives.map((cr) => (
                        <div key={cr.id} className="col-12 col-md-6 col-lg-4">
                            <div className="card border-0 shadow-xs rounded-4 bg-white overflow-hidden h-100">
                                <div
                                    className="p-4 d-flex align-items-center justify-content-center bg-light border-bottom position-relative"
                                    style={{ height: '220px' }}
                                >
                                    {cr.image_url ? (
                                        <img
                                            src={cr.image_url}
                                            alt={cr.template_name || 'Creative'}
                                            className="img-fluid rounded shadow-sm"
                                            style={{ maxHeight: '180px', objectFit: 'contain' }}
                                        />
                                    ) : (
                                        <div className="text-center text-muted">
                                            <Image size={48} className="opacity-25 mb-2" />
                                            <div className="small">Banner Preview</div>
                                        </div>
                                    )}
                                </div>
                                <div className="p-3">
                                    <div className="d-flex align-items-center justify-content-between mb-2">
                                        <h6 className="fw-bold text-dark mb-0">{cr.candidate_name || 'Winner Creative'}</h6>
                                        <StatusBadge status={cr.status} />
                                    </div>
                                    <div className="text-muted small mb-3 font-monospace">{cr.template_name || 'Standard Gold Theme'}</div>
                                    <div className="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <span className="text-muted small">{new Date(cr.created_at).toLocaleDateString()}</span>
                                        {cr.image_url && (
                                            <a
                                                href={cr.image_url}
                                                download
                                                target="_blank"
                                                rel="noreferrer"
                                                className="btn btn-outline-primary btn-sm rounded-3 py-1 px-3 d-flex align-items-center gap-1.5 fw-semibold"
                                            >
                                                <Download size={14} /> Download
                                            </a>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* Generate Modal */}
            {isGenerateModal && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-dialog-centered">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <form onSubmit={handleGenerateSubmit}>
                                <div className="modal-header border-bottom-0 pb-0">
                                    <h5 className="modal-title fw-bold text-dark">Generate Winner Creative</h5>
                                    <button type="button" className="btn-close" onClick={() => setIsGenerateModal(false)}></button>
                                </div>
                                <div className="modal-body py-3">
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">Select Winner *</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={genData.decision_id}
                                            onChange={(e) => setGenData({ ...genData, decision_id: e.target.value })}
                                            required
                                        >
                                            <option value="">Choose Winner...</option>
                                            {winners.map((w) => (
                                                <option key={w.id} value={w.id}>
                                                    {w.candidate_name || 'Selected Leader'} (Rank #{w.rank || 1})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">Creative Template Layout</label>
                                        <select
                                            className="form-select form-select-sm"
                                            value={genData.template_name}
                                            onChange={(e) => setGenData({ ...genData, template_name: e.target.value })}
                                        >
                                            <option value="official_announcement_gold">Peers Global Leadership — Gold Prestige</option>
                                            <option value="official_announcement_indigo">Peers Global Leadership — Modern Indigo</option>
                                            <option value="social_media_square_1080">Social Media Square (1080x1080)</option>
                                            <option value="website_hero_landscape">Website Hero Landscape (1920x1080)</option>
                                        </select>
                                    </div>
                                </div>
                                <div className="modal-footer border-top-0 pt-0">
                                    <button type="button" className="btn btn-light" onClick={() => setIsGenerateModal(false)}>
                                        Cancel
                                    </button>
                                    <button type="submit" className="btn btn-primary fw-semibold px-4" disabled={isGenerating}>
                                        {isGenerating ? 'Rendering Creative...' : 'Generate Creative'}
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
