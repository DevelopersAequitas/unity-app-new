<!-- Activity Peer Modal -->
<div class="modal fade" id="activityPeerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-2xl overflow-hidden">
            <div class="modal-header border-0 bg-gradient-to-r from-indigo-900 via-slate-900 to-indigo-950 text-white p-4 relative">
                <div class="flex items-center gap-3">
                    <div id="actModalAvatar" class="w-12 h-12 rounded-full text-white font-bold flex items-center justify-center text-lg shadow-md shrink-0 border-2 border-white/20"></div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h5 class="modal-title font-bold text-white text-lg m-0" id="actModalName"></h5>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/30 text-indigo-200 border border-indigo-400/30" id="actModalDesignation"></span>
                        </div>
                        <div class="text-xs text-slate-300 mt-1 flex items-center gap-1.5 flex-wrap">
                            <span id="actModalCompany" class="font-medium"></span>
                            <span id="actModalCity"></span>
                            <span id="actModalCircle" class="text-indigo-300 font-semibold"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 space-y-4 bg-slate-50/50">
                <!-- Contact Info Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-white p-3 rounded-xl border bs flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><i class="bi bi-envelope"></i></div>
                        <div class="overflow-hidden">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Email Address</div>
                            <div id="actModalEmail" class="text-xs font-semibold t1 truncate"></div>
                        </div>
                    </div>
                    <div class="bg-white p-3 rounded-xl border bs flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><i class="bi bi-telephone"></i></div>
                        <div class="overflow-hidden">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Phone Number</div>
                            <div id="actModalPhone" class="text-xs font-semibold t1 truncate"></div>
                        </div>
                    </div>
                </div>

                <!-- Activity Performance Grid -->
                <div>
                    <div class="flex justify-between items-center mb-2 px-1">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Activity Summary Breakdown</span>
                        <span id="actModalScore" class="px-2.5 py-0.5 text-xs font-bold bg-indigo-100 text-indigo-800 rounded-full"></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <a id="actModalLinkTestimonials" href="#" target="_blank" class="p-3 bg-white rounded-xl border bs hover:border-indigo-300 transition no-underline block">
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">💬 Testimonials</div>
                            <div id="actModalValTestimonials" class="text-lg font-bold text-indigo-600 mt-1">0</div>
                        </a>
                        <a id="actModalLinkReferrals" href="#" target="_blank" class="p-3 bg-white rounded-xl border bs hover:border-emerald-300 transition no-underline block">
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">👤 Referrals</div>
                            <div id="actModalValReferrals" class="text-lg font-bold text-emerald-600 mt-1">0</div>
                        </a>
                        <a id="actModalLinkDeals" href="#" target="_blank" class="p-3 bg-white rounded-xl border bs hover:border-amber-300 transition no-underline block">
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">💼 Business Deals</div>
                            <div id="actModalValDeals" class="text-lg font-bold text-amber-600 mt-1">0</div>
                        </a>
                        <a id="actModalLinkP2p" href="#" target="_blank" class="p-3 bg-white rounded-xl border bs hover:border-sky-300 transition no-underline block">
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">🤝 P2P Meetings</div>
                            <div id="actModalValP2p" class="text-lg font-bold text-sky-600 mt-1">0</div>
                        </a>
                        <a id="actModalLinkRequirements" href="#" target="_blank" class="p-3 bg-white rounded-xl border bs hover:border-rose-300 transition no-underline block">
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">📄 Requirements</div>
                            <div id="actModalValRequirements" class="text-lg font-bold text-rose-600 mt-1">0</div>
                        </a>
                        <a id="actModalLinkLeadership" href="#" target="_blank" class="p-3 bg-white rounded-xl border bs hover:border-purple-300 transition no-underline block">
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">🏅 Leadership Req</div>
                            <div id="actModalValLeadership" class="text-lg font-bold text-purple-600 mt-1">0</div>
                        </a>
                        <a id="actModalLinkRecommendations" href="#" target="_blank" class="p-3 bg-white rounded-xl border bs hover:border-violet-300 transition no-underline block">
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">👍 Recommend Peer</div>
                            <div id="actModalValRecommendations" class="text-lg font-bold text-violet-600 mt-1">0</div>
                        </a>
                        <a id="actModalLinkVisitors" href="#" target="_blank" class="p-3 bg-white rounded-xl border bs hover:border-slate-300 transition no-underline block">
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">🪪 Reg Visitor</div>
                            <div id="actModalValVisitors" class="text-lg font-bold text-slate-700 mt-1">0</div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-t bs bg-white p-3">
                <button type="button" class="btn btn-secondary text-xs font-semibold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof window.openActivityPeerModal !== 'function') {
        window.openActivityPeerModal = function(triggerEl, event) {
            if (event && event.target && event.target.closest('a, input, button')) {
                return;
            }
            const rawData = triggerEl.getAttribute('data-peer');
            if (!rawData) return;

            try {
                const peer = JSON.parse(rawData);
                
                document.getElementById('actModalName').textContent = peer.name || 'Peer Details';
                document.getElementById('actModalDesignation').textContent = peer.designation || 'Member';
                document.getElementById('actModalCompany').textContent = peer.company ? peer.company : '';
                document.getElementById('actModalCity').textContent = peer.city ? ' • ' + peer.city : '';
                document.getElementById('actModalCircle').textContent = peer.circle ? ' • ' + peer.circle : '';
                document.getElementById('actModalEmail').textContent = peer.email || '—';
                document.getElementById('actModalPhone').textContent = peer.phone || '—';
                document.getElementById('actModalScore').textContent = 'Total Activity: ' + (peer.score || 0);

                const avatarEl = document.getElementById('actModalAvatar');
                avatarEl.style.backgroundColor = peer.avatarBg || '#6366f1';
                avatarEl.textContent = peer.initials || 'P';

                const setValAndLink = (valId, linkId, val, url) => {
                    const vEl = document.getElementById(valId);
                    const lEl = document.getElementById(linkId);
                    if (vEl) vEl.textContent = val;
                    if (lEl) lEl.href = url || '#';
                };

                setValAndLink('actModalValTestimonials', 'actModalLinkTestimonials', peer.testimonials, peer.testimonialsUrl);
                setValAndLink('actModalValReferrals', 'actModalLinkReferrals', peer.referrals, peer.referralsUrl);
                setValAndLink('actModalValDeals', 'actModalLinkDeals', peer.deals, peer.dealsUrl);
                setValAndLink('actModalValP2p', 'actModalLinkP2p', peer.p2p, peer.p2pUrl);
                setValAndLink('actModalValRequirements', 'actModalLinkRequirements', peer.requirements, peer.requirementsUrl);
                setValAndLink('actModalValLeadership', 'actModalLinkLeadership', peer.leadership, peer.leadershipUrl);
                setValAndLink('actModalValRecommendations', 'actModalLinkRecommendations', peer.recommendations, peer.recommendationsUrl);
                setValAndLink('actModalValVisitors', 'actModalLinkVisitors', peer.visitors, peer.visitorsUrl);

                const bsModal = new bootstrap.Modal(document.getElementById('activityPeerModal'));
                bsModal.show();
            } catch(e) {
                console.error('Error opening peer modal:', e);
            }
        };
    }
</script>
