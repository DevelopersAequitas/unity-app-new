<!-- Requirement Detail Modal -->
<div class="modal fade" id="requirementDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-2xl overflow-hidden">
            <div class="modal-header border-0 bg-gradient-to-r from-rose-900 via-slate-900 to-rose-950 text-white p-4 relative">
                <div class="flex items-center justify-between w-full pr-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-rose-500/30 border border-rose-400/30 flex items-center justify-center text-rose-200 text-lg shadow-sm">
                            <i class="bi bi-file-earmark-text-fill admin-icon" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-bold text-white text-lg m-0">Requirement Details</h5>
                            <span class="text-xs text-rose-200 font-medium" id="reqModalCreatedAt"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 space-y-4 bg-slate-50/50">
                <!-- Peer & Status Grid -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div id="reqModalAvatar" class="w-12 h-12 rounded-full text-white font-bold flex items-center justify-center text-base shadow shrink-0"></div>
                            <div>
                                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Posted By</div>
                                <div id="reqModalName" class="text-sm font-bold t1"></div>
                                <div id="reqModalMeta" class="text-xs t3"></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Status</div>
                            <span id="reqModalStatus" class="px-3 py-1 rounded-full text-xs font-bold capitalize"></span>
                        </div>
                    </div>
                </div>

                <!-- Subject & Tags Box -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm space-y-3">
                    <div>
                        <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Subject</div>
                        <div id="reqModalSubject" class="text-sm font-bold text-slate-800"></div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap pt-1 border-t bs">
                        <span id="reqModalRegion" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700"></span>
                        <span id="reqModalCategory" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700"></span>
                    </div>
                </div>

                <!-- Description Box -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                        <i class="bi bi-text-left text-rose-500 text-base"></i>
                        <span>Requirement Description</span>
                    </div>
                    <div class="p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/80">
                        <p id="reqModalDescription" class="text-xs text-slate-700 leading-relaxed whitespace-pre-line m-0"></p>
                    </div>
                </div>

                <!-- Media Attachment Box -->
                <div id="reqModalMediaBox" class="bg-white p-4 rounded-xl border bs shadow-sm space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                        <i class="bi bi-paperclip text-emerald-500 text-base"></i>
                        <span>Attached Media</span>
                    </div>
                    <div id="reqModalMediaContent"></div>
                </div>
            </div>
            <div class="modal-footer border-t bs bg-white p-3">
                <button type="button" class="btn btn-secondary text-xs font-semibold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof window.openRequirementDetailModal !== 'function') {
        window.openRequirementDetailModal = function(triggerEl, event) {
            if (event && event.target && event.target.closest('a, input, button, select')) {
                return;
            }
            const rawData = triggerEl.getAttribute('data-requirement');
            if (!rawData) return;

            try {
                const data = JSON.parse(rawData);

                document.getElementById('reqModalCreatedAt').textContent = 'Posted on: ' + (data.created_at || '—');

                // Peer info
                document.getElementById('reqModalName').textContent = data.from_name || '—';
                const meta = [data.from_company, data.from_city].filter(Boolean).join(' • ');
                document.getElementById('reqModalMeta').textContent = meta || '—';
                const avatar = document.getElementById('reqModalAvatar');
                avatar.style.backgroundColor = data.from_bg || '#f43f5e';
                avatar.textContent = data.from_initials || 'P';

                // Status
                const statusEl = document.getElementById('reqModalStatus');
                statusEl.textContent = data.status || 'open';
                if ((data.status || '').toLowerCase() === 'closed') {
                    statusEl.className = 'px-3 py-1 rounded-full text-xs font-bold capitalize bg-slate-100 text-slate-700 border border-slate-200';
                } else {
                    statusEl.className = 'px-3 py-1 rounded-full text-xs font-bold capitalize bg-rose-100 text-rose-700 border border-rose-200';
                }

                // Subject & Filters
                document.getElementById('reqModalSubject').textContent = data.subject || '—';
                document.getElementById('reqModalRegion').innerHTML = '<i class="bi bi-geo-alt-fill text-slate-500"></i> Region: ' + (data.region || 'Any');
                document.getElementById('reqModalCategory').innerHTML = '<i class="bi bi-tag-fill text-indigo-500"></i> Category: ' + (data.category || 'Any');

                // Description
                document.getElementById('reqModalDescription').textContent = data.description || 'No detailed description provided.';

                // Media
                const mediaContainer = document.getElementById('reqModalMediaContent');
                mediaContainer.innerHTML = '';

                if (data.media_has && data.media_url) {
                    const mediaAnchor = document.createElement('a');
                    mediaAnchor.href = data.media_url;
                    mediaAnchor.target = '_blank';
                    mediaAnchor.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition no-underline';
                    mediaAnchor.innerHTML = '<i class="bi bi-file-earmark-arrow-down-fill text-sm"></i> View Attachment (' + data.media_count + ' file)';
                    mediaContainer.appendChild(mediaAnchor);

                    if (/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(data.media_url)) {
                        const img = document.createElement('img');
                        img.src = data.media_url;
                        img.className = 'mt-2 rounded-lg border max-h-60 object-contain block shadow-sm';
                        mediaContainer.appendChild(img);
                    }
                } else {
                    mediaContainer.innerHTML = '<span class="text-xs text-slate-400 italic">No media attached to this requirement.</span>';
                }

                const bsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('requirementDetailModal'));
                bsModal.show();
            } catch(e) {
                console.error('Error opening requirement detail modal:', e);
            }
        };
    }
</script>
