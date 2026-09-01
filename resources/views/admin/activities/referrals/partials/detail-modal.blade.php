<!-- Referral Detail Modal -->
<div class="modal fade" id="referralDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-2xl overflow-hidden">
            <div class="modal-header border-0 bg-gradient-to-r from-emerald-900 via-slate-900 to-emerald-950 text-white p-4 relative">
                <div class="flex items-center justify-between w-full pr-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-500/30 border border-emerald-400/30 flex items-center justify-center text-emerald-200 text-lg shadow-sm">
                            <i class="bi bi-person-plus-fill admin-icon" aria-hidden="true"></i>
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
                        <div class="flex items-center gap-2">
                            <span id="refModalType" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-indigo-50 text-indigo-700 border-indigo-200"></span>
                            <span id="refModalHot" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border" style="display: none;"></span>
                        </div>
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

                // Referral Details - Type & Hot
                const typeMap = {
                    'customer_referral': { label: 'Customer', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
                    'customer': { label: 'Customer', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
                    'b2b_referral': { label: 'B2B Referral', badge: 'bg-indigo-50 text-indigo-700 border-indigo-200', dot: 'bg-indigo-500' },
                    'b2b': { label: 'B2B Referral', badge: 'bg-indigo-50 text-indigo-700 border-indigo-200', dot: 'bg-indigo-500' },
                    'b2g_referral': { label: 'B2G Referral', badge: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-500' },
                    'b2g': { label: 'B2G Referral', badge: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-500' },
                    'collaborative_projects': { label: 'Collaboration', badge: 'bg-cyan-50 text-cyan-700 border-cyan-200', dot: 'bg-cyan-500' },
                    'collaborative': { label: 'Collaboration', badge: 'bg-cyan-50 text-cyan-700 border-cyan-200', dot: 'bg-cyan-500' },
                    'referral_partnerships': { label: 'Partnership', badge: 'bg-sky-50 text-sky-700 border-sky-200', dot: 'bg-sky-500' },
                    'partnerships': { label: 'Partnership', badge: 'bg-sky-50 text-sky-700 border-sky-200', dot: 'bg-sky-500' },
                    'vendor_referrals': { label: 'Vendor', badge: 'bg-teal-50 text-teal-700 border-teal-200', dot: 'bg-teal-500' },
                    'vendor': { label: 'Vendor', badge: 'bg-teal-50 text-teal-700 border-teal-200', dot: 'bg-teal-500' },
                    'business': { label: 'Business', badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
                    'service': { label: 'Service', badge: 'bg-violet-50 text-violet-700 border-violet-200', dot: 'bg-violet-500' },
                    'others': { label: 'Other', badge: 'bg-slate-100 text-slate-700 border-slate-200', dot: 'bg-slate-400' },
                    'other': { label: 'Other', badge: 'bg-slate-100 text-slate-700 border-slate-200', dot: 'bg-slate-400' }
                };

                const rawType = (data.referral_type || '').toLowerCase();
                const typeConfig = typeMap[rawType] || {
                    label: data.referral_type ? data.referral_type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'General',
                    badge: 'bg-slate-100 text-slate-700 border-slate-200',
                    dot: 'bg-slate-500'
                };
                const refModalType = document.getElementById('refModalType');
                refModalType.className = `inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border ${typeConfig.badge}`;
                refModalType.innerHTML = `<span class="w-1.5 h-1.5 rounded-full ${typeConfig.dot}"></span><span>${typeConfig.label}</span>`;

                const hotVal = parseInt(data.hot_value, 10);
                const refModalHot = document.getElementById('refModalHot');
                if (!isNaN(hotVal) && hotVal > 0) {
                    refModalHot.style.display = 'inline-flex';
                    let hotBadgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
                    let hotIconClass = 'text-rose-600 animate-pulse';
                    if (hotVal === 4) {
                        hotBadgeClass = 'bg-orange-50 text-orange-700 border-orange-200';
                        hotIconClass = 'text-orange-500';
                    } else if (hotVal === 3) {
                        hotBadgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
                        hotIconClass = 'text-amber-500';
                    } else if (hotVal === 2) {
                        hotBadgeClass = 'bg-yellow-50 text-yellow-800 border-yellow-200';
                        hotIconClass = 'text-yellow-600';
                    } else if (hotVal === 1) {
                        hotBadgeClass = 'bg-slate-100 text-slate-700 border-slate-200';
                        hotIconClass = 'text-slate-400';
                    }
                    refModalHot.className = `inline-flex items-center justify-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border ${hotBadgeClass}`;
                    refModalHot.innerHTML = `<i class="bi bi-fire text-[11px] ${hotIconClass}"></i><span>Hotness: ${hotVal}/5</span>`;
                } else {
                    refModalHot.style.display = 'none';
                }

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
