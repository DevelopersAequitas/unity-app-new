<!-- Business Deal Detail Modal -->
<div class="modal fade" id="businessDealDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-2xl overflow-hidden">
            <div class="modal-header border-0 bg-gradient-to-r from-amber-900 via-slate-900 to-amber-950 text-white p-4 relative">
                <div class="flex items-center justify-between w-full pr-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-500/30 border border-amber-400/30 flex items-center justify-center text-amber-200 text-lg shadow-sm">
                            <i class="bi bi-briefcase-fill admin-icon" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-bold text-white text-lg m-0">Business Deal Details</h5>
                            <span class="text-xs text-amber-200 font-medium" id="dealModalCreatedAt"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 space-y-4 bg-slate-50/50">
                <!-- Peer Exchange Header Card -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-center">
                        <!-- From Peer -->
                        <div class="sm:col-span-2 bg-slate-50 p-3 rounded-lg border bs flex items-center gap-3">
                            <div id="dealModalFromAvatar" class="w-11 h-11 rounded-full text-white font-bold flex items-center justify-center text-sm shadow shrink-0"></div>
                            <div class="overflow-hidden">
                                <div class="text-[10px] uppercase font-bold text-amber-600 tracking-wider flex items-center gap-1">
                                    <span>GIVEN BY (FROM)</span>
                                </div>
                                <div id="dealModalFromName" class="text-xs font-bold t1 truncate"></div>
                                <div id="dealModalFromMeta" class="text-[11px] t3 truncate"></div>
                            </div>
                        </div>

                        <!-- Arrow Indicator -->
                        <div class="sm:col-span-1 flex justify-center items-center py-1 sm:py-0">
                            <div class="w-8 h-8 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 font-bold text-sm shadow-sm">
                                <i class="bi bi-arrow-right"></i>
                            </div>
                        </div>

                        <!-- To Peer -->
                        <div class="sm:col-span-2 bg-slate-50 p-3 rounded-lg border bs flex items-center gap-3">
                            <div id="dealModalToAvatar" class="w-11 h-11 rounded-full text-white font-bold flex items-center justify-center text-sm shadow shrink-0"></div>
                            <div class="overflow-hidden">
                                <div class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider flex items-center gap-1">
                                    <span>RECEIVED BY (TO)</span>
                                </div>
                                <div id="dealModalToName" class="text-xs font-bold t1 truncate"></div>
                                <div id="dealModalToMeta" class="text-[11px] t3 truncate"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deal Amount & Type Card -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3 bg-amber-50/70 rounded-lg border border-amber-200">
                        <div class="text-[10px] uppercase font-bold text-amber-700">Deal Amount</div>
                        <div id="dealModalAmount" class="text-base font-extrabold text-amber-700 mt-0.5"></div>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-lg border bs">
                        <div class="text-[10px] uppercase font-bold text-slate-400">Business Type</div>
                        <div id="dealModalType" class="text-xs font-bold text-slate-800 mt-0.5"></div>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-lg border bs">
                        <div class="text-[10px] uppercase font-bold text-slate-400">Deal Date</div>
                        <div id="dealModalDate" class="text-xs font-bold text-slate-800 mt-0.5"></div>
                    </div>
                </div>

                <!-- Comments Box -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                        <i class="bi bi-chat-left-text-fill text-amber-500 text-base"></i>
                        <span>Deal Description / Comments</span>
                    </div>
                    <div class="p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/80">
                        <p id="dealModalComment" class="text-xs text-slate-700 leading-relaxed whitespace-pre-line m-0"></p>
                    </div>
                </div>

                <!-- Media Attachment Box -->
                <div id="dealModalMediaBox" class="bg-white p-4 rounded-xl border bs shadow-sm space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                        <i class="bi bi-paperclip text-emerald-500 text-base"></i>
                        <span>Deal Invoice / Attached Media</span>
                    </div>
                    <div id="dealModalMediaContent"></div>
                </div>
            </div>
            <div class="modal-footer border-t bs bg-white p-3">
                <button type="button" class="btn btn-secondary text-xs font-semibold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof window.openBusinessDealDetailModal !== 'function') {
        window.openBusinessDealDetailModal = function(triggerEl, event) {
            if (event && event.target && event.target.closest('a, input, button, select')) {
                return;
            }
            const rawData = triggerEl.getAttribute('data-deal');
            if (!rawData) return;

            try {
                const data = JSON.parse(rawData);

                document.getElementById('dealModalCreatedAt').textContent = 'Logged on: ' + (data.created_at || '—');

                // From Peer
                document.getElementById('dealModalFromName').textContent = data.from_name || '—';
                const fromMeta = [data.from_company, data.from_city].filter(Boolean).join(' • ');
                document.getElementById('dealModalFromMeta').textContent = fromMeta || '—';
                const fromAvatar = document.getElementById('dealModalFromAvatar');
                fromAvatar.style.backgroundColor = data.from_bg || '#d97706';
                fromAvatar.textContent = data.from_initials || 'P';

                // To Peer
                document.getElementById('dealModalToName').textContent = data.to_name || '—';
                const toMeta = [data.to_company, data.to_city].filter(Boolean).join(' • ');
                document.getElementById('dealModalToMeta').textContent = toMeta || '—';
                const toAvatar = document.getElementById('dealModalToAvatar');
                toAvatar.style.backgroundColor = data.to_bg || '#6366f1';
                toAvatar.textContent = data.to_initials || 'P';

                // Deal Details
                document.getElementById('dealModalAmount').textContent = '₹ ' + (data.deal_amount ? Number(data.deal_amount).toLocaleString('en-IN') : '0');
                const bType = (data.business_type || '').toLowerCase();
                let typeBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">' + (data.business_type || '—') + '</span>';
                if (bType.includes('new')) {
                    typeBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>' + (data.business_type || 'New') + '</span>';
                } else if (bType.includes('repeat')) {
                    typeBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>' + (data.business_type || 'Repeat') + '</span>';
                }
                document.getElementById('dealModalType').innerHTML = typeBadge;
                document.getElementById('dealModalDate').textContent = data.deal_date || '—';
                document.getElementById('dealModalComment').textContent = data.comment || 'No description comments provided.';

                // Media
                const mediaContainer = document.getElementById('dealModalMediaContent');
                mediaContainer.innerHTML = '';

                if (data.media_has && data.media_url) {
                    const mediaAnchor = document.createElement('a');
                    mediaAnchor.href = data.media_url;
                    mediaAnchor.target = '_blank';
                    mediaAnchor.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition no-underline';
                    mediaAnchor.innerHTML = '<i class="bi bi-file-earmark-arrow-down-fill text-sm"></i> View Attachment';
                    mediaContainer.appendChild(mediaAnchor);

                    if (/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(data.media_url)) {
                        const img = document.createElement('img');
                        img.src = data.media_url;
                        img.className = 'mt-2 rounded-lg border max-h-60 object-contain block shadow-sm';
                        mediaContainer.appendChild(img);
                    }
                } else {
                    mediaContainer.innerHTML = '<span class="text-xs text-slate-400 italic">No media attached to this business deal.</span>';
                }

                const bsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('businessDealDetailModal'));
                bsModal.show();
            } catch(e) {
                console.error('Error opening business deal detail modal:', e);
            }
        };
    }
</script>
