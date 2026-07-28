<!-- Referral Detail Modal -->
<div class="modal fade" id="referralDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-2xl overflow-hidden">
            <div class="modal-header border-0 bg-gradient-to-r from-emerald-900 via-slate-900 to-emerald-950 text-white p-4 relative">
                <div class="flex items-center justify-between w-full pr-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-500/30 border border-emerald-400/30 flex items-center justify-center text-emerald-200 text-lg shadow-sm">
                            👤
                        </div>
                        <div>
                            <h5 class="modal-title font-bold text-white text-lg m-0">Referral Details</h5>
                            <span class="text-xs text-emerald-200 font-medium" id="refModalCreatedAt"></span>
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
                            <div id="refModalFromAvatar" class="w-11 h-11 rounded-full text-white font-bold flex items-center justify-center text-sm shadow shrink-0"></div>
                            <div class="overflow-hidden">
                                <div class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider flex items-center gap-1">
                                    <span>GIVEN BY (FROM)</span>
                                </div>
                                <div id="refModalFromName" class="text-xs font-bold t1 truncate"></div>
                                <div id="refModalFromMeta" class="text-[11px] t3 truncate"></div>
                            </div>
                        </div>

                        <!-- Arrow Indicator -->
                        <div class="sm:col-span-1 flex justify-center items-center py-1 sm:py-0">
                            <div class="w-8 h-8 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 font-bold text-sm shadow-sm">
                                <i class="bi bi-arrow-right"></i>
                            </div>
                        </div>

                        <!-- To Peer -->
                        <div class="sm:col-span-2 bg-slate-50 p-3 rounded-lg border bs flex items-center gap-3">
                            <div id="refModalToAvatar" class="w-11 h-11 rounded-full text-white font-bold flex items-center justify-center text-sm shadow shrink-0"></div>
                            <div class="overflow-hidden">
                                <div class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider flex items-center gap-1">
                                    <span>GIVEN TO (TO)</span>
                                </div>
                                <div id="refModalToName" class="text-xs font-bold t1 truncate"></div>
                                <div id="refModalToMeta" class="text-[11px] t3 truncate"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Referral Contact / Info Card -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Referral Contact Details</div>
                        <span id="refModalType" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 bg-slate-50 rounded-lg border bs">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Referral Name / Subject</div>
                            <div id="refModalReferralOf" class="text-xs font-bold text-slate-800 mt-0.5"></div>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-lg border bs">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Referral Date</div>
                            <div id="refModalDate" class="text-xs font-bold text-slate-800 mt-0.5"></div>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-lg border bs">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Phone Number</div>
                            <div id="refModalPhone" class="text-xs font-semibold text-slate-700 mt-0.5"></div>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-lg border bs">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Email Address</div>
                            <div id="refModalEmail" class="text-xs font-semibold text-slate-700 mt-0.5"></div>
                        </div>
                    </div>
                </div>

                <!-- Address & Remarks Box -->
                <div class="bg-white p-4 rounded-xl border bs shadow-sm space-y-3">
                    <div>
                        <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Address</div>
                        <div id="refModalAddress" class="text-xs text-slate-700 font-medium"></div>
                    </div>
                    <div class="pt-2 border-t bs">
                        <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Remarks / Discussion Notes</div>
                        <div class="p-3 bg-slate-50/70 rounded-xl border border-slate-200/80">
                            <p id="refModalRemarks" class="text-xs text-slate-700 leading-relaxed whitespace-pre-line m-0"></p>
                        </div>
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
    if (typeof window.openReferralDetailModal !== 'function') {
        window.openReferralDetailModal = function(triggerEl, event) {
            if (event && event.target && event.target.closest('a, input, button, select')) {
                return;
            }
            const rawData = triggerEl.getAttribute('data-referral');
            if (!rawData) return;

            try {
                const data = JSON.parse(rawData);

                document.getElementById('refModalCreatedAt').textContent = 'Created on: ' + (data.created_at || '—');

                // From Peer
                document.getElementById('refModalFromName').textContent = data.from_name || '—';
                const fromMeta = [data.from_company, data.from_city].filter(Boolean).join(' • ');
                document.getElementById('refModalFromMeta').textContent = fromMeta || '—';
                const fromAvatar = document.getElementById('refModalFromAvatar');
                fromAvatar.style.backgroundColor = data.from_bg || '#10b981';
                fromAvatar.textContent = data.from_initials || 'P';

                // To Peer
                document.getElementById('refModalToName').textContent = data.to_name || '—';
                const toMeta = [data.to_company, data.to_city].filter(Boolean).join(' • ');
                document.getElementById('refModalToMeta').textContent = toMeta || '—';
                const toAvatar = document.getElementById('refModalToAvatar');
                toAvatar.style.backgroundColor = data.to_bg || '#6366f1';
                toAvatar.textContent = data.to_initials || 'P';

                // Referral Details
                document.getElementById('refModalType').textContent = (data.referral_type || 'General').toUpperCase();
                document.getElementById('refModalReferralOf').textContent = data.referral_of || '—';
                document.getElementById('refModalDate').textContent = data.referral_date || '—';
                document.getElementById('refModalPhone').textContent = data.phone || '—';
                document.getElementById('refModalEmail').textContent = data.email || '—';

                // Address & Remarks
                document.getElementById('refModalAddress').textContent = data.address || '—';
                document.getElementById('refModalRemarks').textContent = data.remarks || 'No remarks provided.';

                const bsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('referralDetailModal'));
                bsModal.show();
            } catch(e) {
                console.error('Error opening referral detail modal:', e);
            }
        };
    }
</script>
