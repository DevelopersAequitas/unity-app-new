<!-- P2P Meeting Detail Modal -->
<div class="modal fade" id="p2pMeetingDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-2xl overflow-hidden">
            <div class="modal-header border-0 bg-gradient-to-r from-sky-900 via-slate-900 to-sky-950 text-white p-4 relative">
                <div class="flex items-center justify-between w-full pr-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-sky-500/30 border border-sky-400/30 flex items-center justify-center text-sky-200 text-lg shadow-sm">
                            🤝
                        </div>
                        <div>
                            <h5 class="modal-title font-bold text-white text-lg m-0">P2P Meeting Details</h5>
                            <span class="text-xs text-sky-200 font-medium" id="p2pModalCreatedAt"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 space-y-4 bg-slate-50/50">
                <!-- Peer Exchange Header Card -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-center">
                        <!-- Initiator Peer -->
                        <div class="sm:col-span-2 bg-slate-50 p-3 rounded-lg border bs flex items-center gap-3">
                            <div id="p2pModalFromAvatar" class="w-11 h-11 rounded-full text-white font-bold flex items-center justify-center text-sm shadow shrink-0"></div>
                            <div class="overflow-hidden">
                                <div class="text-[10px] uppercase font-bold text-sky-600 tracking-wider flex items-center gap-1">
                                    <span>INITIATOR (PEER 1)</span>
                                </div>
                                <div id="p2pModalFromName" class="text-xs font-bold t1 truncate"></div>
                                <div id="p2pModalFromMeta" class="text-[11px] t3 truncate"></div>
                            </div>
                        </div>

                        <!-- Connection Indicator -->
                        <div class="sm:col-span-1 flex justify-center items-center py-1 sm:py-0">
                            <div class="w-8 h-8 rounded-full bg-sky-50 border border-sky-200 flex items-center justify-center text-sky-600 font-bold text-sm shadow-sm">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>

                        <!-- Peer User -->
                        <div class="sm:col-span-2 bg-slate-50 p-3 rounded-lg border bs flex items-center gap-3">
                            <div id="p2pModalToAvatar" class="w-11 h-11 rounded-full text-white font-bold flex items-center justify-center text-sm shadow shrink-0"></div>
                            <div class="overflow-hidden">
                                <div class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider flex items-center gap-1">
                                    <span>PEER 2</span>
                                </div>
                                <div id="p2pModalToName" class="text-xs font-bold t1 truncate"></div>
                                <div id="p2pModalToMeta" class="text-[11px] t3 truncate"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Meeting Location & Date Card -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-3 bg-slate-50 rounded-lg border bs">
                        <div class="text-[10px] uppercase font-bold text-slate-400">Meeting Date</div>
                        <div id="p2pModalDate" class="text-xs font-bold text-slate-800 mt-0.5"></div>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-lg border bs">
                        <div class="text-[10px] uppercase font-bold text-slate-400">Meeting Place / Venue</div>
                        <div id="p2pModalPlace" class="text-xs font-bold text-slate-800 mt-0.5"></div>
                    </div>
                </div>

                <!-- Remarks Box -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                        <i class="bi bi-chat-left-text-fill text-sky-500 text-base"></i>
                        <span>Meeting Remarks / Key Takeaways</span>
                    </div>
                    <div class="p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/80">
                        <p id="p2pModalRemarks" class="text-xs text-slate-700 leading-relaxed whitespace-pre-line m-0"></p>
                    </div>
                </div>

                <!-- Media Attachment Box -->
                <div id="p2pModalMediaBox" class="bg-white p-4 rounded-xl border bs shadow-sm space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                        <i class="bi bi-paperclip text-emerald-500 text-base"></i>
                        <span>Meeting Photos / Attached Media</span>
                    </div>
                    <div id="p2pModalMediaContent"></div>
                </div>
            </div>
            <div class="modal-footer border-t bs bg-white p-3">
                <button type="button" class="btn btn-secondary text-xs font-semibold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof window.openP2pMeetingDetailModal !== 'function') {
        window.openP2pMeetingDetailModal = function(triggerEl, event) {
            if (event && event.target && event.target.closest('a, input, button, select')) {
                return;
            }
            const rawData = triggerEl.getAttribute('data-p2p');
            if (!rawData) return;

            try {
                const data = JSON.parse(rawData);

                document.getElementById('p2pModalCreatedAt').textContent = 'Logged on: ' + (data.created_at || '—');

                // Initiator
                document.getElementById('p2pModalFromName').textContent = data.from_name || '—';
                const fromMeta = [data.from_company, data.from_city].filter(Boolean).join(' • ');
                document.getElementById('p2pModalFromMeta').textContent = fromMeta || '—';
                const fromAvatar = document.getElementById('p2pModalFromAvatar');
                fromAvatar.style.backgroundColor = data.from_bg || '#0284c7';
                fromAvatar.textContent = data.from_initials || 'P';

                // Peer 2
                document.getElementById('p2pModalToName').textContent = data.to_name || '—';
                const toMeta = [data.to_company, data.to_city].filter(Boolean).join(' • ');
                document.getElementById('p2pModalToMeta').textContent = toMeta || '—';
                const toAvatar = document.getElementById('p2pModalToAvatar');
                toAvatar.style.backgroundColor = data.to_bg || '#6366f1';
                toAvatar.textContent = data.to_initials || 'P';

                // Meeting Info
                document.getElementById('p2pModalDate').textContent = data.meeting_date || '—';
                document.getElementById('p2pModalPlace').textContent = data.meeting_place || '—';
                document.getElementById('p2pModalRemarks').textContent = data.remarks || 'No remarks provided.';

                // Media
                const mediaContainer = document.getElementById('p2pModalMediaContent');
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
                    mediaContainer.innerHTML = '<span class="text-xs text-slate-400 italic">No media attached to this meeting.</span>';
                }

                const bsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('p2pMeetingDetailModal'));
                bsModal.show();
            } catch(e) {
                console.error('Error opening P2P meeting detail modal:', e);
            }
        };
    }
</script>
