<!-- Activity Peer Modal -->
<div class="modal fade" id="activityPeerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow border-0 overflow-hidden" style="border-radius: 1rem;">

            {{-- Header --}}
            <div class="modal-header border-0 p-4 position-relative"
                 style="background: linear-gradient(135deg, #1e1b4b 0%, #1e293b 60%, #1e1b4b 100%);">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div id="actModalAvatar"
                         class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold text-white fs-5"
                         style="width:52px; height:52px; border-radius:50%; background:#6366f1; border:2.5px solid rgba(255,255,255,0.2); overflow:hidden;">
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fw-bold text-white m-0 fs-6">
                                <a id="actModalNameLink" href="#" class="text-white text-decoration-none"></a>
                            </h5>
                            <span id="actModalDesignation"
                                  class="badge fw-semibold text-white"
                                  style="background:rgba(99,102,241,0.35); border:1px solid rgba(99,102,241,0.4); font-size:10px; border-radius:.5rem; padding:2px 8px;">
                            </span>
                        </div>
                        <div class="mt-1 d-flex align-items-center flex-wrap gap-1" style="font-size:11px; color:#94a3b8;">
                            <span id="actModalCompany" class="fw-semibold"></span>
                            <span id="actModalCity"></span>
                            <span id="actModalCircle" style="color:#a5b4fc; font-weight:600;"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body p-4 position-relative" style="background:#f8fafc;">

                {{-- Loading overlay --}}
                <div id="actModalLoadingOverlay"
                     class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center"
                     style="z-index:10; background:rgba(255,255,255,0.8); border-radius:0 0 1rem 1rem;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-2" role="status" style="width:1.4rem;height:1.4rem;"></div>
                        <div class="text-muted" style="font-size:11px;">Loading peer data…</div>
                    </div>
                </div>

                {{-- Error message --}}
                <div id="actModalError" class="d-none alert alert-danger py-2 px-3 mb-0" style="font-size:12px; border-radius:.75rem;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <span id="actModalErrorMsg">Failed to load peer data. Please try again.</span>
                </div>

                {{-- SUMMARY VIEW --}}
                <div id="actModalSummaryView">

                    {{-- Contact Info --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <div class="d-flex align-items-center gap-3 p-3 bg-white rounded-3 border h-100"
                                 style="border-color:#e2e8f0 !important;">
                                <div class="d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:36px;height:36px;border-radius:.5rem;background:#eef2ff;color:#6366f1;">
                                    <i class="bi bi-envelope" style="font-size:15px;"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-uppercase mb-0.5"
                                         style="font-size:10px;color:#94a3b8;letter-spacing:.05em;">Email Address</div>
                                    <div id="actModalEmail" class="fw-semibold text-truncate"
                                         style="font-size:12px;color:#0f172a;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="d-flex align-items-center gap-3 p-3 bg-white rounded-3 border h-100"
                                 style="border-color:#e2e8f0 !important;">
                                <div class="d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:36px;height:36px;border-radius:.5rem;background:#ecfdf5;color:#10b981;">
                                    <i class="bi bi-telephone" style="font-size:15px;"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-uppercase mb-0.5"
                                         style="font-size:10px;color:#94a3b8;letter-spacing:.05em;">Phone Number</div>
                                    <div id="actModalPhone" class="fw-semibold text-truncate"
                                         style="font-size:12px;color:#0f172a;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Activity Summary --}}
                    <div id="actModalActivitySummarySection">
                        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                            <span class="fw-bold text-uppercase" style="font-size:10px;color:#64748b;letter-spacing:.07em;">
                                Activity Summary Breakdown
                            </span>
                            <span id="actModalScore"
                                  class="badge fw-bold"
                                  style="background:#eef2ff;color:#4f46e5;font-size:11px;border-radius:.75rem;padding:3px 10px;">
                            </span>
                        </div>

                        @php
                            $activityCards = [
                                ['type' => 'testimonials',   'id' => 'actModalValTestimonials',   'label' => 'Testimonials',       'icon' => 'bi-chat-quote',       'bg' => '#eef2ff', 'color' => '#6366f1'],
                                ['type' => 'referrals',      'id' => 'actModalValReferrals',      'label' => 'Referrals',          'icon' => 'bi-people',           'bg' => '#ecfdf5', 'color' => '#10b981'],
                                ['type' => 'deals',          'id' => 'actModalValDeals',          'label' => 'Business Deals',     'icon' => 'bi-briefcase',        'bg' => '#fffbeb', 'color' => '#d97706'],
                                ['type' => 'p2p',            'id' => 'actModalValP2p',            'label' => 'P2P Meetings',       'icon' => 'bi-handshake',        'bg' => '#f0f9ff', 'color' => '#0284c7'],
                                ['type' => 'requirements',   'id' => 'actModalValRequirements',   'label' => 'Requirements',       'icon' => 'bi-file-earmark-text','bg' => '#fff1f2', 'color' => '#e11d48'],
                                ['type' => 'leadership',     'id' => 'actModalValLeadership',     'label' => 'Leadership',         'icon' => 'bi-award',            'bg' => '#faf5ff', 'color' => '#9333ea'],
                                ['type' => 'recommendations','id' => 'actModalValRecommendations','label' => 'Recommendations',    'icon' => 'bi-person-check',     'bg' => '#f5f3ff', 'color' => '#7c3aed'],
                                ['type' => 'visitors',       'id' => 'actModalValVisitors',       'label' => 'Reg. Visitors',      'icon' => 'bi-person-badge',     'bg' => '#f8fafc', 'color' => '#475569'],
                            ];
                        @endphp

                        <div class="row g-2">
                            @foreach ($activityCards as $card)
                                <div class="col-6 col-sm-3">
                                    <div role="button" tabindex="0"
                                         onclick="window.loadPeerActivityBreakdown('{{ $card['type'] }}', event)"
                                         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.loadPeerActivityBreakdown('{{ $card['type'] }}', event);}"
                                         aria-label="View {{ $card['label'] }}"
                                         class="p-3 bg-white rounded-3 border h-100 position-relative"
                                         style="cursor:pointer;border-color:#e2e8f0 !important;transition:box-shadow .15s,border-color .15s;"
                                         onmouseenter="this.style.borderColor='{{ $card['color'] }}33 !important';this.style.boxShadow='0 2px 8px rgba(0,0,0,.07)'"
                                         onmouseleave="this.style.borderColor='#e2e8f0 !important';this.style.boxShadow='none'">
                                        <div class="d-flex align-items-center gap-2 mb-1" style="font-size:11px;font-weight:600;color:#64748b;">
                                            <span style="width:22px;height:22px;border-radius:6px;background:{{ $card['bg'] }};display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                <i class="bi {{ $card['icon'] }}" style="font-size:12px;color:{{ $card['color'] }};"></i>
                                            </span>
                                            <span class="text-truncate">{{ $card['label'] }}</span>
                                        </div>
                                        <div id="{{ $card['id'] }}" class="fw-bold" style="font-size:1.1rem;color:{{ $card['color'] }};">0</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- BREAKDOWN LIST VIEW --}}
                <div id="actModalDetailView" class="d-none">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                        <button type="button" id="actModalBackButton" onclick="window.showPeerSummaryView()"
                                class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1"
                                style="font-size:12px;font-weight:600;border-radius:.5rem;">
                            <i class="bi bi-arrow-left"></i> Back to Summary
                        </button>
                        <span id="actModalDetailTitle"
                              class="fw-bold text-uppercase"
                              style="font-size:11px;color:#64748b;letter-spacing:.07em;">Activity Records</span>
                    </div>
                    <div id="actModalDetailList"
                         style="max-height:340px;overflow-y:auto;display:flex;flex-direction:column;gap:.5rem;">
                        {{-- Items dynamically rendered --}}
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="modal-footer border-top bg-white p-3" style="border-color:#e2e8f0 !important;">
                <button type="button" class="btn btn-secondary btn-sm fw-semibold px-4"
                        data-bs-dismiss="modal" style="font-size:12px;border-radius:.5rem;">Close</button>
            </div>

        </div>
    </div>
</div>

<script>
    (function() {
        if (window._activityPeerModalInitialized) return;
        window._activityPeerModalInitialized = true;

        let currentPeerId = null;

        function setLoadingState(loading) {
            const overlay = document.getElementById('actModalLoadingOverlay');
            const errorEl = document.getElementById('actModalError');
            if (overlay) {
                if (loading) {
                    overlay.classList.remove('d-none');
                    overlay.classList.add('d-flex');
                } else {
                    overlay.classList.add('d-none');
                    overlay.classList.remove('d-flex');
                }
            }
            if (errorEl) errorEl.classList.add('d-none');
        }

        function showError(msg) {
            const overlay = document.getElementById('actModalLoadingOverlay');
            const errorEl  = document.getElementById('actModalError');
            const errorMsg = document.getElementById('actModalErrorMsg');
            if (overlay) { overlay.classList.add('d-none'); overlay.classList.remove('d-flex'); }
            if (errorEl) errorEl.classList.remove('d-none');
            if (errorMsg) errorMsg.textContent = msg || 'Failed to load peer data. Please try again.';
        }

        window.showPeerSummaryView = function() {
            const summarySection = document.getElementById('actModalActivitySummarySection');
            const d = document.getElementById('actModalDetailView');
            const backBtn = document.getElementById('actModalBackButton');
            if (summarySection) summarySection.classList.remove('d-none');
            if (d) d.classList.add('d-none');
            if (backBtn) backBtn.classList.remove('d-none');
        };

        window.renderPeerModalData = function(peer) {
            setLoadingState(false);
            window.showPeerSummaryView();

            if (peer.id) currentPeerId = String(peer.id);

            const nameLinkEl = document.getElementById('actModalNameLink');
            if (nameLinkEl) {
                nameLinkEl.textContent = peer.name || 'Peer Details';
                nameLinkEl.href = '#';
                nameLinkEl.onclick = function(e) { e.preventDefault(); };
            }

            const desigEl = document.getElementById('actModalDesignation');
            if (desigEl) desigEl.textContent = peer.designation || 'Member';

            const compEl = document.getElementById('actModalCompany');
            if (compEl) compEl.textContent = peer.company || '';

            const cityEl = document.getElementById('actModalCity');
            if (cityEl) cityEl.textContent = peer.city ? ' • ' + peer.city : '';

            const circleEl = document.getElementById('actModalCircle');
            if (circleEl) circleEl.textContent = peer.circle ? ' • ' + peer.circle : '';

            const emailEl = document.getElementById('actModalEmail');
            if (emailEl) emailEl.textContent = peer.email || '—';

            const phoneEl = document.getElementById('actModalPhone');
            if (phoneEl) phoneEl.textContent = peer.phone || '—';

            const scoreEl = document.getElementById('actModalScore');
            if (scoreEl) scoreEl.textContent = 'Total Activity: ' + (peer.score !== undefined ? peer.score : 0);

            // Avatar
            const avatarEl = document.getElementById('actModalAvatar');
            if (avatarEl) {
                avatarEl.style.backgroundColor = peer.avatarBg || '#6366f1';
                avatarEl.innerHTML = '';
                if (peer.photo) {
                    const img = document.createElement('img');
                    img.src = peer.photo;
                    img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
                    img.onerror = function() {
                        avatarEl.innerHTML = '';
                        avatarEl.textContent = peer.initials || (peer.name ? peer.name.trim()[0].toUpperCase() : 'P');
                    };
                    avatarEl.appendChild(img);
                } else {
                    avatarEl.textContent = peer.initials || (peer.name ? peer.name.trim()[0].toUpperCase() : 'P');
                }
            }

            // Activity values
            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = (val !== undefined && val !== null) ? val : '0';
            };
            setVal('actModalValTestimonials',   peer.testimonials);
            setVal('actModalValReferrals',       peer.referrals);
            setVal('actModalValDeals',           peer.deals);
            setVal('actModalValP2p',             peer.p2p);
            setVal('actModalValRequirements',    peer.requirements);
            setVal('actModalValLeadership',      peer.leadership);
            setVal('actModalValRecommendations', peer.recommendations);
            setVal('actModalValVisitors',        peer.visitors);
            setVal('actModalValSupportTickets',  peer.supportTickets);
        };

        window.loadPeerActivityBreakdown = function(type, event) {
            if (event) { if (event.stopPropagation) event.stopPropagation(); if (event.preventDefault) event.preventDefault(); }
            if (!currentPeerId) return;

            const summaryView  = document.getElementById('actModalSummaryView');
            const detailView   = document.getElementById('actModalDetailView');
            const detailTitle  = document.getElementById('actModalDetailTitle');
            const detailList   = document.getElementById('actModalDetailList');

            const titles = {
                testimonials:   '<i class="bi bi-chat-quote-fill admin-icon me-1.5" aria-hidden="true"></i>Testimonials',
                referrals:      '<i class="bi bi-person-plus-fill admin-icon me-1.5" aria-hidden="true"></i>Referrals',
                deals:          '<i class="bi bi-briefcase-fill admin-icon me-1.5" aria-hidden="true"></i>Business Deals',
                p2p:            '<i class="bi bi-people-fill admin-icon me-1.5" aria-hidden="true"></i>P2P Meetings',
                requirements:   '<i class="bi bi-file-earmark-text-fill admin-icon me-1.5" aria-hidden="true"></i>Requirements',
                leadership:     '<i class="bi bi-award-fill admin-icon me-1.5" aria-hidden="true"></i>Leadership Requests',
                recommendations:'<i class="bi bi-hand-thumbs-up-fill admin-icon me-1.5" aria-hidden="true"></i>Recommended Peers',
                visitors:       '<i class="bi bi-person-badge-fill admin-icon me-1.5" aria-hidden="true"></i>Registered Visitors',
                support_tickets:'<i class="bi bi-headset admin-icon me-1.5" aria-hidden="true"></i>Support Tickets'
            };

            if (detailTitle) detailTitle.innerHTML = titles[type] || 'Activity Records';
            if (detailList) detailList.innerHTML = '<div class="text-center py-5 text-muted" style="font-size:12px;"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><br>Loading records…</div>';

            const summarySection = document.getElementById('actModalActivitySummarySection');
            const backBtn = document.getElementById('actModalBackButton');

            if (type === 'support_tickets') {
                if (summarySection) summarySection.classList.add('d-none');
                if (backBtn) backBtn.classList.add('d-none');
            } else {
                if (summarySection) summarySection.classList.add('d-none');
                if (backBtn) backBtn.classList.remove('d-none');
            }

            if (detailView) detailView.classList.remove('d-none');

            fetch('/admin/activities/peer-details/' + encodeURIComponent(currentPeerId) + '/' + encodeURIComponent(type), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function(res) {
                if (!res.ok) {
                    return res.json().then(function(e) { throw new Error(e.error || 'Server error ' + res.status); }).catch(function() { throw new Error('Server error ' + res.status); });
                }
                return res.json();
            })
            .then(function(data) {
                if (!detailList) return;
                if (!data.items || data.items.length === 0) {
                    detailList.innerHTML = '<div class="text-center py-5 rounded-3 border bg-white" style="font-size:12px;color:#94a3b8;"><i class="bi bi-inbox d-block mb-2" style="font-size:1.8rem;"></i>No records found.</div>';
                    return;
                }
                detailList.innerHTML = '';
                data.items.forEach(function(item) {
                    const row = document.createElement('div');
                    row.style.cssText = 'display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;padding:.75rem;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;transition:background .15s;';
                    row.onmouseenter = function() { this.style.background = '#f8fafc'; };
                    row.onmouseleave = function() { this.style.background = '#fff'; };
                    const titleHtml = item.url
                        ? `<a href="${item.url}" style="color:#4f46e5;text-decoration:none;font-weight:600;" onmouseenter="this.style.textDecoration='underline'" onmouseleave="this.style.textDecoration='none'">${item.title || 'Record'} <i class="bi bi-box-arrow-up-right ms-1" style="font-size:10px;"></i></a>`
                        : (item.title || 'Record');
                    row.innerHTML = `
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:12px;font-weight:600;color:#0f172a;">${titleHtml}</div>
                            <div style="font-size:11px;color:#64748b;margin-top:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${item.details || '—'}</div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
                            <span style="padding:2px 8px;font-size:10px;font-weight:600;border-radius:999px;border:1px solid;" class="${item.badgeClass || 'bg-light text-secondary border-secondary'}">${item.badge || ''}</span>
                            <span style="font-size:10px;color:#94a3b8;font-family:monospace;">${item.date || ''}</span>
                        </div>
                    `;
                    detailList.appendChild(row);
                });
            })
            .catch(function(err) {
                if (detailList) {
                    detailList.innerHTML = '<div class="text-center py-4 rounded-3" style="background:#fff1f2;border:1px solid #fecdd3;font-size:12px;color:#e11d48;"><i class="bi bi-exclamation-circle me-1"></i>' + (err.message || 'Failed to load details.') + '</div>';
                }
            });
        };

        window.openActivityPeerModal = function(trigger, event, initialType) {
            if (event) { if (event.stopPropagation) event.stopPropagation(); if (event.preventDefault) event.preventDefault(); }

            let peerId  = null;
            let rawData = null;

            if (typeof trigger === 'string' || typeof trigger === 'number') {
                peerId = String(trigger).trim();
            } else if (trigger && trigger.nodeType === 1) {
                if (event && event.target && event.target.closest('input, button') && !event.target.closest('.peer-link, [data-peer-id]')) return;
                rawData = trigger.getAttribute('data-peer');
                peerId  = trigger.getAttribute('data-peer-id') || trigger.getAttribute('data-user-id');
                if (peerId) peerId = String(peerId).trim();
            } else if (typeof trigger === 'object' && trigger !== null) {
                if (trigger.id) {
                    window.renderPeerModalData(trigger);
                    const modalEl = document.getElementById('activityPeerModal');
                    if (modalEl) { let m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl); m.show(); }
                    if (initialType) window.loadPeerActivityBreakdown(initialType);
                    return;
                }
            }

            if (rawData) {
                try {
                    const peer = JSON.parse(rawData);
                    window.renderPeerModalData(peer);
                    const modalEl = document.getElementById('activityPeerModal');
                    if (modalEl) { let m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl); m.show(); }
                    if (initialType) window.loadPeerActivityBreakdown(initialType);
                    return;
                } catch(e) { console.warn('[PeerModal] Failed to parse inline data:', e); }
            }

            if (peerId && peerId !== '' && peerId !== 'null' && peerId !== 'undefined') {
                currentPeerId = peerId;
                window.showPeerSummaryView();

                // Reset fields
                const fields = {
                    actModalNameLink:  { prop: 'textContent', val: 'Loading…' },
                    actModalDesignation: { prop: 'textContent', val: '' },
                    actModalCompany:   { prop: 'textContent', val: '' },
                    actModalCity:      { prop: 'textContent', val: '' },
                    actModalCircle:    { prop: 'textContent', val: '' },
                    actModalEmail:     { prop: 'textContent', val: '—' },
                    actModalPhone:     { prop: 'textContent', val: '—' },
                    actModalScore:     { prop: 'textContent', val: 'Loading…' },
                };
                Object.entries(fields).forEach(([id, cfg]) => {
                    const el = document.getElementById(id);
                    if (el) el[cfg.prop] = cfg.val;
                });
                const avatarEl = document.getElementById('actModalAvatar');
                if (avatarEl) { avatarEl.textContent = '…'; avatarEl.style.backgroundColor = '#6366f1'; }
                ['actModalValTestimonials','actModalValReferrals','actModalValDeals','actModalValP2p',
                 'actModalValRequirements','actModalValLeadership','actModalValRecommendations','actModalValVisitors','actModalValSupportTickets']
                    .forEach(id => { const el = document.getElementById(id); if (el) el.textContent = '—'; });

                setLoadingState(true);

                const modalEl = document.getElementById('activityPeerModal');
                if (modalEl) { let m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl); m.show(); }

                fetch('/admin/activities/peer-summary/' + encodeURIComponent(peerId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function(res) {
                    if (!res.ok) {
                        return res.json().then(function(e) { throw new Error(e.error || 'Server error ' + res.status); }).catch(function() { throw new Error('Server error ' + res.status); });
                    }
                    return res.json();
                })
                .then(function(data) {
                    if (data && !data.error) {
                        window.renderPeerModalData(data);
                        if (initialType) {
                            window.loadPeerActivityBreakdown(initialType);
                        }
                    } else {
                        showError(data && data.error ? data.error : 'Peer not found.');
                    }
                })
                .catch(function(err) {
                    showError('Could not load peer details: ' + err.message);
                });
            } else {
                console.warn('[PeerModal] openActivityPeerModal called with invalid ID:', trigger);
            }
        };
    })();
</script>
