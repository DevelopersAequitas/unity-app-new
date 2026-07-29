<!-- Testimonial Detail Modal -->
<div class="modal fade" id="testimonialDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-2xl overflow-hidden">
            <div class="modal-header border-0 bg-gradient-to-r from-indigo-900 via-slate-900 to-indigo-950 text-white p-4 relative">
                <div class="flex items-center justify-between w-full pr-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-500/30 border border-indigo-400/30 flex items-center justify-center text-indigo-200 text-lg shadow-sm">
                            💬
                        </div>
                        <div>
                            <h5 class="modal-title font-bold text-white text-lg m-0">Testimonial Details</h5>
                            <span class="text-xs text-slate-300 font-medium" id="tmModalCreatedAt"></span>
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
                            <div id="tmModalFromAvatar" class="w-11 h-11 rounded-full text-white font-bold flex items-center justify-center text-sm shadow shrink-0"></div>
                            <div class="overflow-hidden">
                                <div class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider flex items-center gap-1">
                                    <span>GIVEN BY (FROM)</span>
                                </div>
                                <div id="tmModalFromName" class="text-xs font-bold t1 truncate"></div>
                                <div id="tmModalFromMeta" class="text-[11px] t3 truncate"></div>
                            </div>
                        </div>

                        <!-- Arrow Indicator -->
                        <div class="sm:col-span-1 flex justify-center items-center py-1 sm:py-0">
                            <div class="w-8 h-8 rounded-full bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 font-bold text-sm shadow-sm">
                                <i class="bi bi-arrow-right"></i>
                            </div>
                        </div>

                        <!-- To Peer -->
                        <div class="sm:col-span-2 bg-slate-50 p-3 rounded-lg border bs flex items-center gap-3">
                            <div id="tmModalToAvatar" class="w-11 h-11 rounded-full text-white font-bold flex items-center justify-center text-sm shadow shrink-0"></div>
                            <div class="overflow-hidden">
                                <div class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider flex items-center gap-1">
                                    <span>GIVEN TO (TO)</span>
                                </div>
                                <div id="tmModalToName" class="text-xs font-bold t1 truncate"></div>
                                <div id="tmModalToMeta" class="text-[11px] t3 truncate"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Testimonial Content Box -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                        <i class="bi bi-chat-quote-fill text-indigo-500 text-base"></i>
                        <span>Testimonial Message</span>
                    </div>
                    <div class="p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/80 relative">
                        <p id="tmModalContent" class="text-xs text-slate-700 leading-relaxed font-normal whitespace-pre-line m-0"></p>
                    </div>
                </div>

                <!-- Media Attachment Box -->
                <div id="tmModalMediaBox" class="bg-white p-4 rounded-xl border bs shadow-sm space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                        <i class="bi bi-paperclip text-emerald-500 text-base"></i>
                        <span>Attached Media</span>
                    </div>
                    <div id="tmModalMediaContent"></div>
                </div>
            </div>
            <div class="modal-footer border-t bs bg-white p-3">
                <button type="button" class="btn btn-secondary text-xs font-semibold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof window.openTestimonialDetailModal !== 'function') {
        window.openTestimonialDetailModal = function(triggerEl, event) {
            if (event && event.target && event.target.closest('a, input, button, select')) {
                return;
            }
            const rawData = triggerEl.getAttribute('data-testimonial');
            if (!rawData) return;

            try {
                const data = JSON.parse(rawData);

                document.getElementById('tmModalCreatedAt').textContent = 'Submitted on: ' + (data.created_at || '—');

                // From Peer
                document.getElementById('tmModalFromName').textContent = data.from_name || '—';
                const fromMeta = [data.from_company, data.from_city].filter(Boolean).join(' • ');
                document.getElementById('tmModalFromMeta').textContent = fromMeta || '—';
                const fromAvatar = document.getElementById('tmModalFromAvatar');
                fromAvatar.style.backgroundColor = data.from_bg || '#6366f1';
                fromAvatar.textContent = data.from_initials || 'P';

                // To Peer
                document.getElementById('tmModalToName').textContent = data.to_name || '—';
                const toMeta = [data.to_company, data.to_city].filter(Boolean).join(' • ');
                document.getElementById('tmModalToMeta').textContent = toMeta || '—';
                const toAvatar = document.getElementById('tmModalToAvatar');
                toAvatar.style.backgroundColor = data.to_bg || '#10b981';
                toAvatar.textContent = data.to_initials || 'P';

                // Content
                document.getElementById('tmModalContent').textContent = data.content || 'No text content provided.';

                // Media
                const mediaContainer = document.getElementById('tmModalMediaContent');
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
                    mediaContainer.innerHTML = '<span class="text-xs text-slate-400 italic">No media attached to this testimonial.</span>';
                }

                const bsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('testimonialDetailModal'));
                bsModal.show();
            } catch(e) {
                console.error('Error opening testimonial detail modal:', e);
            }
        };
    }
</script>
