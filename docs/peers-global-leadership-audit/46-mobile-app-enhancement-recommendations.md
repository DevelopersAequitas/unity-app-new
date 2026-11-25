# 46 — Mobile App Enhancement Recommendations (`LeaderApp-Fornt-end-`)

> **Audit Group:** 08 — Enhancement Planning & Roadmap  
> **Target Repository:** `D:\office project\LeaderApp-Fornt-end-` (Flutter 3.50.0 · Dart `^3.12.0` · MVP + BLoC Architecture)  
> **Hard Constraints:** **ENHANCE, NEVER DUPLICATE** · Preserve existing MVP + BLoC folder conventions · Adhere strictly to `README.md` §9 Critical Vocabulary Rule (zero prohibited networking/member terms in user-facing UI strings; preserve backend JSON/API keys)  
> **Related Deliverables:** [02-existing-leadership-app-audit.md](./02-existing-leadership-app-audit.md) · [04-existing-feature-and-screen-inventory.md](./04-existing-feature-and-screen-inventory.md) · [45-existing-vs-required-feature-gap-analysis.md](./45-existing-vs-required-feature-gap-analysis.md) · [47-api-enhancement-recommendations.md](./47-api-enhancement-recommendations.md) · [50-final-implementation-roadmap.md](./50-final-implementation-roadmap.md)

---

## 1. Architectural Preservation & Completed Baseline Rounds

**FACT:** Prior audit stabilization rounds (documented in `LEADER_APP_IMPLEMENTATION_PROGRESS.md`, **260 passing Flutter tests / 0 failures**) already resolved:
- Responsive `PrimaryButton` wrapping (`lib/core/widgets/primary_button.dart`) and `ReportSubmitSection` header wrapping (`report_submit_section.dart`).
- Fritsch–Butland monotone cubic spline rendering (`lib/core/utils/monotone_spline.dart`) eliminating negative baseline dips in `FinanceSplineChart` and `ReportsSplineChart`.
-Incomplete All-Circles roster submission guard (`ReportsBloc.rosterSubmitBlocker`).
- Removal of `DashboardHeroCard` fallback from `overallRevenue` to `deals` (now showing `"Unavailable"` when null).
- International phone validation (`phone_numbers_parser` + `InternationalPhoneField`), DST-aware `EventTimeZones` (`event_time_display.dart`), typed `NotificationNavigator` cold-start deep-link routing, and `test/content/brand_vocabulary_test.dart` enforcing `README.md` §9 across `lib/`.

**RECOMMENDATION:** Keep the existing 24-feature `bloc / model / presenter / view` architecture, `ApiClient` singleton, `SessionManager`, and 5-slot bottom navigation intact. Execute the **14 targeted, non-duplicative mobile enhancements** below — each documented with all **10 mandatory specification fields** (Prompt §2).

---

## 2. Screen-by-Screen & Core-Layer Enhancement Specifications (All 10 Mandatory Fields)

### MOB-ENH-01: Canonical `UserRole` Taxonomy Alignment & Strict `isSuperAdmin` Resolution
1. **Existing Implementation:** `UserRole` enum defines 10 values (`chairBusinessGrowth`, `chairMembership`, `chairEvents`, `circleChair`, `circleFounder`, `circleDirector`, `industryDirector`, `districtExecDirector`, `countryDirector`, `superAdmin`) parsed by fuzzy substring matching in `UserRole.fromLabel()`; `SessionManager.isSuperAdmin` checks `customRoleLabel?.toLowerCase().contains('admin')`.
2. **Existing File Path:** [user_role.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/enums/user_role.dart#L2-L74) & [session_manager.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/helpers/session_manager.dart#L59-L63) (`L241-L245`, `L307-L434`)
3. **Existing Class/Function:** `UserRole`, `UserRole.fromLabel()`, `UserSession.fromJson()`, `SessionManager.isSuperAdmin`, `SessionManager._resolvePredefinedPermissions()`
4. **Existing API Endpoint:** `POST /api/v1/leader/auth/verify-otp` & `GET /api/v1/leader/profile` (`/leader/auth/me`)
5. **Existing Database Table:** `users`, `roles`, `admin_user_roles`, `circles`, `circle_members`, `leader_role_capabilities`
6. **Existing Permission Mechanism:** `SessionManager().permissions` (`LeaderPermissions`) — returns `LeaderPermissions.allGranted` whenever `isSuperAdmin` is `true`.
7. **Actual Limitation:** **FACT:** (a) `viceChair`, `secretary`, and `committeeLeader` are missing from `UserRole`, silently collapsing to `UserRole.circleChair` (`session_manager.dart:62`) and granting `canAccessTeamsTab: true` in offline/fallback mode; (b) `chairEvents` label says `'Chair - Events & Programs'` while backend canonical key is `chairEventsPrograms` (*Chair - Events & Impacts Committee*); (c) any custom role label containing `'admin'` (e.g., `"Circle Operations Admin"`) triggers `isSuperAdmin == true` (`L245`) and unlocks all 22 Super Admin UI controls; (d) substring `clean.contains('industry')` or `clean.contains('district')` misclassifies custom titles.
8. **Proposed Enhancement:** Add `viceChair('Vice Chair')`, `secretary('Secretary')`, and `committeeLeader('Committee Leader')` to `UserRole`; update `chairEvents` display label to `'Chair - Events & Impacts'` (keeping exact backend canonical key mapping `chairEventsPrograms` in a primary exact-key `switch` map `UserRole.fromRoleKey(String? key)` **before** falling back to `fromLabel()`); restrict `SessionManager.isSuperAdmin` strictly to `_currentSession.role == UserRole.superAdmin` (removing `customRoleLabel.contains('admin')`).
9. **Dependencies:** Backend `UserRoleResolverService` alignment for `committee_leader` & `global_founder` (`API-ENH-02` in [47-api-enhancement-recommendations.md](./47-api-enhancement-recommendations.md)).
10. **Risk of Regression:** **Low** — Exhaustive `switch (role)` blocks in `session_manager.dart:316` are compile-time checked by Dart analyzer; existing unit tests in `test/features/login/` and `test/core/` verify role resolution.

---

### MOB-ENH-02: Network Security Hardening — Bearer Token Log Redaction, Logout API Call & 401 Session-Expiry Handler
1. **Existing Implementation:** `ApiClient` Dio `InterceptorsWrapper` injects `Authorization: Bearer $token`, logs full `options.headers` and response bodies in `kDebugMode`, and translates HTTP `401` into an uncaught `UnauthenticatedException`; `ProfileSignOutBottomSheet` / `ProfileBloc` clears local storage without calling server logout; `OtpBloc._isNotLeaderException` treats every `403` as `"Not a Leader"`.
2. **Existing File Path:** [api_client.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/network/api_client.dart#L36-L105) (`L267-L279`), [api_endpoints.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/constants/api_endpoints.dart#L45-L50), [profile_bloc.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/profile/bloc/profile_bloc.dart), [otp_bloc.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/otp/bloc/otp_bloc.dart)
3. **Existing Class/Function:** `ApiClient._printRequestLog()`, `ApiClient._handleDioError()`, `SessionManager.clearSession()`, `OtpBloc._isNotLeaderException()`
4. **Existing API Endpoint:** `POST /api/v1/leader/auth/logout` (already routed in `routes/leader.php:57`!), `POST /api/v1/leader/auth/verify-otp`
5. **Existing Database Table:** `personal_access_tokens`, `user_push_tokens`
6. **Existing Permission Mechanism:** `auth:sanctum` + `leader.user` middleware.
7. **Actual Limitation:** **FACT:** (a) Live Sanctum tokens are printed in cleartext to `debugPrint` (`api_client.dart:71`); (b) voluntary sign-out leaves the Bearer token active forever in PostgreSQL `personal_access_tokens` and keeps FCM push tokens active; (c) mid-session token revocation (`401`) leaves the user stranded on broken screens without redirecting to `/login`; (d) `OtpBloc` misinterprets any `403` (e.g., suspended account) as `"Not a Leader"`.
8. **Proposed Enhancement:**
   - Redact `Authorization` (`Bearer ***REDACTED***`) and `otp`/`token`/`refresh_token` keys in `ApiClient._printRequestLog()`.
   - Add `static String get logout => '$baseUrl/leader/auth/logout';` in `ApiEndpoints` and call it best-effort (with 5s timeout, unregistering Leader FCM token) inside `AuthRepository.logout()` before `SessionManager().clearSession()`.
   - Add a single-flight `QueuedInterceptor` in `ApiClient`: when `/leader/auth/refresh` (`API-ENH-01`) is deployed, attempt one token refresh on `401` (excluding `/auth/send-otp`, `/auth/verify-otp`, `/auth/logout`), else clear session, call `AppNotificationNavigator.instance.reset()`, and route cleanly via `navigatorKey` to `AppRoutes.login` with `"Session expired. Please sign in again."`.
   - Narrow `OtpBloc._isNotLeaderException` to check `errorCode == 'NOT_A_LEADER'` (falling back to message check only if `errorCode` is null).
9. **Dependencies:** `POST /api/v1/leader/auth/logout` already exists today; optional token refresh depends on `API-ENH-01`.
10. **Risk of Regression:** **Medium** — Must guard the `401` redirect so non-auth background calls or login-screen `401`s never trigger duplicate navigation loops; cover with mock `DioAdapter` unit tests.

---

### MOB-ENH-03: Eliminate 7-Call `DashboardRemoteDataSource` Fan-Out & `/admin/*` Endpoint Coupling
1. **Existing Implementation:** After fetching `GET /leader/dashboard/metrics`, `DashboardRemoteDataSource.getMetrics()` executes a 7-endpoint `Future.wait` (`leader/asks/summary`, `admin/leads/counts`, `admin/pending-requests/summary`, `admin/posts?per_page=50`, `leader/peers?per_page=500`, `circulars`, `admin/pending-requests?per_page=100`) for `superAdmin`/`countryDirector` (and a 2nd call to `leader/pending-requests/summary` for scoped roles) to patch missing/broken counts in `LeaderDashboardService`.
2. **Existing File Path:** [dashboard_remote_datasource.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/data/datasources/remote/dashboard_remote_datasource.dart#L33-L450) & [api_endpoints.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/constants/api_endpoints.dart#L58-L82)
3. **Existing Class/Function:** `DashboardRemoteDataSource.getMetrics()`
4. **Existing API Endpoint:** `GET /api/v1/leader/dashboard/metrics` (alias `/dashboard/summary`), plus `/api/v1/admin/{leads/counts, pending-requests, posts}`
5. **Existing Database Table:** `users`, `business_deals`, `p2p_meetings`, `referrals`, `testimonials`, `impacts`, `asks`, `posts`, `circulars`, `certification_submissions`, `partner_with_us_submissions`, `become_speaker_submissions`, `become_mentor_submissions`, `sme_business_story_submissions`
6. **Existing Permission Mechanism:** Client-side `isGlobalRole` check (`dashboard_remote_datasource.dart:76`); backend `/api/v1/admin/*` currently lacks admin guard (`RBAC-SEC-04`).
7. **Actual Limitation:** **FACT:** (a) Once `/api/v1/admin/*` is properly locked down in Phase 0 (`RBAC-SEC-04`), those 4 `/admin/*` calls from a mobile `User` token will fail or require cross-guard hacks; (b) downloading 500 Peer records (`L156`) and 100 Pending Request objects (`L192`) on **every** Dashboard refresh wastes bandwidth, battery, and latency; (c) `creativesCount` (`L340-L358`) only counts creatives inside the first 50 posts!
8. **Proposed Enhancement:** Fix `LeaderDashboardService::getSummary()` on the backend (`API-ENH-04`) to return authoritative, scoped counts for all 4 Dome Tabs in a single JSON payload, then simplify `DashboardRemoteDataSource.getMetrics()` to a **single HTTP request** (`GET /leader/dashboard/metrics`) and remove unused `ApiEndpoints.admin*` constants from the mobile app.
9. **Dependencies:** Backend `LeaderDashboardService::getSummary()` fix (`API-ENH-04`) must deploy **first** so KPI tiles remain populated.
10. **Risk of Regression:** **Low** (once `API-ENH-04` is live) — Dramatically improves Dashboard load time from 8 HTTP round-trips to 1 and decouples Leader App from `/api/v1/admin/*`.

---

### MOB-ENH-04: Fix Silent Failure on `EditPeerBottomSheet` & Wire to Scoped Peer Update API
1. **Existing Implementation:** `EditPeerBottomSheet._handleSave()` calls `await ApiClient().put(ApiEndpoints.peerDetails(widget.peer.id), body: updated.toJson())` inside `try { ... } catch (_) {}`, then unconditionally calls `widget.onUpdated(updated)` and `Navigator.pop()`.
2. **Existing File Path:** [edit_peer_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/widgets/edit_peer_bottom_sheet.dart#L77-L137) & [peer_profile_view.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/peer_profile_view.dart)
3. **Existing Class/Function:** `_EditPeerBottomSheetState._handleSave()`
4. **Existing API Endpoint:** `PUT /api/v1/leader/peers/{id}` (`ApiEndpoints.peerDetails(id)`)
5. **Existing Database Table:** `users`
6. **Existing Permission Mechanism:** UI edit icon gated by `SessionManager().permissions.canAddEditPeer`; backend `LeaderPeersController::update` is unrouted (`405 Method Not Allowed`).
7. **Actual Limitation:** **FACT:** Because `PUT /api/v1/leader/peers/{id}` is unrouted on Laravel (`subagent_1_leader_apis.md` §Executive Highlights #4) and `_handleSave()` swallows all exceptions with `catch (_) {}` (`L130`), Leaders think their edits succeeded, see the mutated state locally until refresh, and **lose 100% of changes**! Furthermore, `updated.toJson()` sends display-only fields rather than the clean `LeaderUpdatePeerRequest` payload.
8. **Proposed Enhancement:** Route `PUT /api/v1/leader/peers/{id}` on backend (`API-ENH-05`); move the call into `PeersRepository.updatePeer(String peerId, UpdatePeerDto dto)` sending only validated fields (`name`, `company`, `designation`, `phone`, `email`, `intro_video_url`); remove `catch (_) {}` in `EditPeerBottomSheet` so `ValidationException` (`422`) and `UnauthorizedException` (`403`) display a `SnackBar` in `AppColors.danger` without closing the sheet or mutating state on failure.
9. **Dependencies:** Backend routing & scoping of `LeaderPeersController::update` (`API-ENH-05`).
10. **Risk of Regression:** **Low** — Replaces silent data loss with honest server-confirmed persistence.

---

### MOB-ENH-05: Wire Role-Targeted Circular Publishing (`PublishCircularBottomSheet` & `CircularsBloc`)
1. **Existing Implementation:** `PublishCircularBottomSheet` collects `title`, `content`, `priority` (`General | Important | Urgent`), and `targetRoles`, builds a `CircularModel` with hardcoded `authorName: 'National Directorate'`, `authorRole: 'Super Admin'`, and dispatches to `CircularsRemoteDataSource.publishCircular()` (`POST /api/v1/circulars/publish`).
2. **Existing File Path:** [publish_circular_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/circulars/view/widgets/publish_circular_bottom_sheet.dart#L53-L70), [circulars_remote_datasource.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/data/datasources/remote/circulars_remote_datasource.dart#L12-L31), [api_endpoints.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/constants/api_endpoints.dart#L202-L204)
3. **Existing Class/Function:** `_PublishCircularBottomSheetState._handlePublish()`, `CircularsRemoteDataSource.publishCircular()`
4. **Existing API Endpoint:** `GET /api/v1/leader/circulars` & `POST /api/v1/leader/circulars` (currently `ApiEndpoints.circularPublish` points to unrouted `/circulars/publish`)
5. **Existing Database Table:** `circulars`
6. **Existing Permission Mechanism:** UI FAB gated to `superAdmin` / `system_configs` (`CircularsView`).
7. **Actual Limitation:** **FACT:** (a) `POST /api/v1/circulars/publish` returns `404` because `LeaderCircularsController::publish` is unrouted; (b) `ApiEndpoints.circulars` calls `/api/v1/circulars` instead of `/api/v1/leader/circulars`; (c) `PublishCircularBottomSheet` hardcodes `'National Directorate'` / `'Super Admin'` (`L65-L66`) and omits `viceChair`, `secretary`, and the 3 Committee Chair keys in `_roleNames` (`L36-L44`).
8. **Proposed Enhancement:** Point `ApiEndpoints.circulars` to `'$baseUrl/leader/circulars'` and `ApiEndpoints.circularPublish` to `'$baseUrl/leader/circulars'`; populate author metadata from the server response (`SessionManager().currentSession` as fallback); add `viceChair`, `secretary`, and Committee Chairs to `_roleNames`; keep sheet open with loading state until `POST` succeeds (`201`).
9. **Dependencies:** Backend `POST /api/v1/leader/circulars` persisted to `circulars` table (`API-ENH-06`).
10. **Risk of Regression:** **Low** — Isolated to `features/circulars/`.

---

### MOB-ENH-06: Remove Fabricated Model Fallbacks & Hardcoded Dummy Filters Across Finance, Session, Circle Details & Reports
1. **Existing Implementation:** Multiple production models inject invented numbers, dates, designations, and hardcoded UUID filters when JSON keys are omitted:
   - `FinanceMetricsModel.fromJson`: `activeTenures ?? 42`, `totalRevenue ?? projected_annual_revenue ?? '₹0.0'`, `circleRevenue ?? total_collections ?? '₹0.0'`
   - `UserSession.fromJson`: `memberSince ?? 'Aug 2026'`, `capabilitiesCount ?? 14`
   - `CircleLeadershipCard`: places `Circle Founder` & `Circle Director` under `"Regional Leaders"` with invented `'Founder & CEO'` / `'Managing Director'` titles, `'Ahmedabad'` city fallback, and `'90%'` attendance fallback!
   - `ReportModel.isDummyPeer` / `isDummyReport`: hardcodes names `'siddharth verma'`, `'pooja sharma'` and two UUIDs in client production code.
2. **Existing File Path:** [finance_model.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/finance/model/finance_model.dart#L246-L260), [session_manager.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/helpers/session_manager.dart#L103-L105), [circle_leadership_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/circle_details/view/widgets/circle_leadership_card.dart#L34-L66) (`L199, L213-L216`), [report_model.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/model/report_model.dart#L245-L281)
3. **Existing Class/Function:** `FinanceMetricsModel.fromJson`, `UserSession.fromJson`, `CircleLeadershipCard.build` / `_buildLeaderPeerCard`, `ReportModel.isDummyPeer` / `isDummyReport`
4. **Existing API Endpoint:** `/leader/finance/metrics`, `/leader/profile`, `/leader/teams/circles/{id}`, `/leader/reports`
5. **Existing Database Table:** `finance_leader_tenures`, `payments`, `users`, `circles`, `circle_members`, `leader_reports`
6. **Existing Permission Mechanism:** Read-only model deserialization across role-gated views.
7. **Actual Limitation:** **FACT:** Violates the Authoritative Finance & Data Trust Rules: (a) missing `total_revenue` silently displays `projected_annual_revenue`; (b) missing `active_tenures` shows `42`; (c) Circle Founders and Circle Directors are **Circle-level** leaders (`Stage 003` / Founding), NOT `"Regional Leaders"` (`DED`, `Industry Director`, `Country Director`), and inventing `'Founder & CEO'`, `'Ahmedabad'`, and `'90%'` attendance misrepresents real Peers; (d) `isDummyPeer` could hide real production Peers named `"Pooja Sharma"` or `"Siddharth Verma"`!
8. **Proposed Enhancement:**
   - In `FinanceMetricsModel`: make `totalRevenue`, `circleRevenue`, `commissionDue`, and `activeTenures` nullable (`null` renders `"Unavailable"` / `"—"`, never `projected_annual_revenue` or `42`).
   - In `UserSession`: default missing `memberSince` to `''` (hide chip when empty) and derive `capabilitiesCount` from `enabled_capabilities.length` (or `null` -> hide).
   - In `CircleLeadershipCard`: bucket `Circle Founder`, `Circle Director`, `Chairs`, `Vice Chair`, `Secretary`, and `Committee Leaders` under **`"Circle Leadership"`**, and place `Industry Director` / `DED` / `Country Director` (when provided by API) under **`"Regional Leadership"`**; remove invented `'Founder & CEO'`, `'Managing Director'`, `'Ahmedabad'`, and `'90%'` fallbacks (`''` / `'—'` when absent).
   - Remove `ReportModel.isDummyPeer` name matching from production builds (clean seed data in dev DB instead).
9. **Dependencies:** Update `test/features/reports/reports_model_test.dart` if any assertion tests `isDummyPeer`.
10. **Risk of Regression:** **Low** — Pure data-honesty cleanup; widget tests verify nullable `"Unavailable"` / `"—"` states.

---

### MOB-ENH-07: Resolve Orphaned Activity Creation Bottom Sheets (`CreateBusinessDeal`, `CreateImpact`, `CreateP2pMeeting`, `PeerProfileBottomActions`)
1. **Existing Implementation:** Five bottom-sheet widgets exist in `lib/features/` that are never mounted or invoked from any active screen:
   - `CreateBusinessDealBottomSheet` (asks user to type raw Peer UUID!)
   - `CreateImpactBottomSheet`
   - `CreateP2pMeetingBottomSheet`
   - `PeerProfileBottomActions` (`LogP2pBottomSheet` + `SendReferralBottomSheet` — commented out of `PeerProfileView` since initial commit `652cea9`)
2. **Existing File Path:** [create_business_deal_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/business_deals/view/widgets/create_business_deal_bottom_sheet.dart#L1-L172), [create_impact_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/impacts/view/widgets/create_impact_bottom_sheet.dart), [create_p2p_meeting_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/p2p_meetings/view/widgets/create_p2p_meeting_bottom_sheet.dart), [peer_profile_bottom_actions.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/widgets/peer_profile_bottom_actions.dart#L1-L64), [log_p2p_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/widgets/log_p2p_bottom_sheet.dart), [send_referral_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/widgets/send_referral_bottom_sheet.dart)
3. **Existing Class/Function:** `CreateBusinessDealBottomSheet`, `CreateImpactBottomSheet`, `CreateP2pMeetingBottomSheet`, `PeerProfileBottomActions`, `LogP2pBottomSheet`, `SendReferralBottomSheet`
4. **Existing API Endpoint:** `POST /api/v1/leader/p2p-meetings` (`/activities/p2p-meetings`), `POST /api/v1/leader/referrals` (`/activities/referrals`)
5. **Existing Database Table:** `p2p_meetings`, `referrals`, `business_deals`, `impacts`
6. **Existing Permission Mechanism:** `canSendWishes` / `request_actions` capability (`LeaderPermissions`).
7. **Actual Limitation:** **FACT:** Dead widget files bloat maintenance, and `CreateBusinessDealBottomSheet` (`L118-L123`) requires typing a raw 36-char UUID (`'Enter peer UUID'`). Meanwhile, Leaders viewing a Peer's 360° profile (`PeerProfileView`) have backend support for `POST /activities/p2p-meetings` (1-on-1 coaching check-ins) and `POST /activities/referrals`, where `peer.id` is already known!
8. **Proposed Enhancement:**
   - **Re-mount** `PeerProfileBottomActions` (`LogP2pBottomSheet` + `SendReferralBottomSheet`) on `PeerProfileView` when `peer.id != SessionManager().currentSession.id` and `permissions.canReviewPendingPeers || permissions.canSendWishes` (`request_actions`), enabling 1-on-1 leadership coaching logs and Peer introductions directly from a Peer's profile.
   - **Keep unmounted / deprecate** `CreateBusinessDealBottomSheet` and `CreateImpactBottomSheet` in the Leadership App — Peer-to-Peer commercial deals and personal impact declarations belong in the Member App (`unity_app`) where bilateral/moderation workflows run.
9. **Dependencies:** **BUSINESS DECISION REQUIRED** (`BD-06` in [51-open-business-decisions.md](./51-open-business-decisions.md)) confirming re-mounting `PeerProfileBottomActions` and deprecating raw-UUID `CreateBusinessDealBottomSheet`.
10. **Risk of Regression:** **Low** — `PeerProfileBottomActions` responsive layout is already verified by 37 widget tests in `test/features/paired_buttons_responsive_test.dart`.

---

### MOB-ENH-08: Scoped Circle Leadership Assignment Sheet (`canAssignCircleChair`)
1. **Existing Implementation:** `LeaderPermissions.canAssignCircleChair` (`leader_permissions.dart:14`) and `PeerRoleManagementSection` (`lib/features/peers/view/widgets/peer_role_management_section.dart`) exist as permission/display concepts, but no UI flow allows an authorized `Circle Director`, `Circle Founder`, `DED`, `Country Director`, or `Super Admin` to assign or update a Circle's `Chair` (`Business Growth`, `Membership Growth`, `Events & Impacts`), `Vice Chair`, `Secretary`, or `Committee Leader`.
2. **Existing File Path:** [circle_leadership_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/circle_details/view/widgets/circle_leadership_card.dart#L71-L96), [peer_role_management_section.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peers/view/widgets/peer_role_management_section.dart), [teams_remote_datasource.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/data/datasources/remote/teams_remote_datasource.dart)
3. **Existing Class/Function:** `CircleLeadershipCard`, `TeamsRepository`, `CircleDetailsBloc`
4. **Existing API Endpoint:** Genuinely missing in `app/Leader/` -> proposed `POST /api/v1/leader/teams/circles/{id}/assignments` & `DELETE .../assignments/{assignment_id}` (`API-ENH-07`)
5. **Existing Database Table:** `user_organizational_assignments`, `circle_members`, `circles` (`calendar` JSONB + FK columns)
6. **Existing Permission Mechanism:** `SessionManager().permissions.canAssignCircleChair` (`access_teams` + role in `CF, CD, DED, EED, SuperAdmin`).
7. **Actual Limitation:** **FACT:** Leaders with `can_assign_circle_chair = true` have zero mobile UI or `/leader/*` endpoint to appoint Circle Chairs, Vice Chairs, Secretaries, or configurable Committee Leaders.
8. **Proposed Enhancement:** Add a `"Manage Circle Leadership"` action button in `CircleLeadershipCard` (visible **strictly** when `SessionManager().permissions.canAssignCircleChair` is `true` and circle is in scope) opening an `AssignCircleLeaderBottomSheet` that selects an approved Peer from `circlePeers`, picks from backend-supplied configurable seat roles (`chair_business_growth_committee`, `chair_membership_growth_committee`, `chair_events_impacts_committee`, `vice_chair`, `secretary`, `committee_leader`), sets optional term dates, and calls `POST /api/v1/leader/teams/circles/{id}/assignments`. Never hardcode fixed committee seat counts!
9. **Dependencies:** Backend `POST /api/v1/leader/teams/circles/{id}/assignments` (`API-ENH-07`).
10. **Risk of Regression:** **Low** — Additive bottom sheet hidden for unauthorized roles.

---

### MOB-ENH-09: Action Center Enhancement — Category Filters, Rejection Reason Modal & Role-Gated Moderation
1. **Existing Implementation:** `DashboardPendingPeersCard` (`dashboard_pending_peers_card.dart`) opens `LeaderActivitiesView(activityType: 'pending-requests')`, which renders `LeaderActivityItemCard` with inline `Approve` and `Reject` buttons calling `POST /leader/pending-requests/{category}/{id}/approve|reject` with no rejection reason prompt and no category filter chips across the 10 pending queues.
2. **Existing File Path:** [leader_activities_view.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/leader_activities/view/leader_activities_view.dart#L546-L561), [leader_activities_bloc.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/leader_activities/bloc/leader_activities_bloc.dart), [leader_activity_item_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/leader_activities/view/widgets/leader_activity_item_card.dart), [dashboard_pending_peers_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/dashboard/view/widgets/dashboard_pending_peers_card.dart#L8-L78)
3. **Existing Class/Function:** `LeaderActivitiesView`, `LeaderActivitiesBloc`, `ApproveLeaderActivityItem`, `RejectLeaderActivityItem`
4. **Existing API Endpoint:** `GET /api/v1/leader/pending-requests`, `GET /api/v1/leader/pending-requests/summary`, `POST /api/v1/leader/pending-requests/{category}/{id}/approve`, `POST .../reject`
5. **Existing Database Table:** `circle_join_requests`, `visitor_registrations`, `coin_claim_requests`, `certification_submissions`, `impacts`, `ad_bookings`, `event_registration_requests`, `peer_recommendations`, `introduction_requests`
6. **Existing Permission Mechanism:** `SessionManager().isSuperAdmin || SessionManager().currentRole.canModeratePendingRequests || SessionManager().permissions.canReviewPendingPeers` (`leader_activities_view.dart:547-549`).
7. **Actual Limitation:** **FACT:** (a) Tapping `Reject` fires immediately without confirmation or `rejection_reason` / `remarks` input (causing backend validation errors on endpoints requiring remarks or leaving Peers with zero feedback); (b) `LeaderPermissions` defaults `canReviewPendingPeers = true` (`leader_permissions.dart:30`), inadvertently showing Approve/Reject buttons to `secretary` or `viceChair` if fallback permissions apply; (c) all 10 pending categories are mixed in one flat list without `HorizontalSelectionChips` (`Circle Joins`, `Impacts`, `Visitors`, `Coin Claims`, `Certifications`, `Events`, `Referrals`), nor SLA age badges (`"Waiting 4d"`).
8. **Proposed Enhancement:** Reuse `LeaderActivitiesView(activityType: 'pending-requests')` as the **Unified Action Center** (no duplicate screen!):
   - Add `HorizontalSelectionChips` bound to `summary.breakdown` counts per category + status filter (`Pending | Approved | Rejected`).
   - Add a `ConfirmModerationBottomSheet` on `Approve` (confirm stage transition, e.g. `"Approve as Circle Director -> forwards to Industry Director"`) and `Reject` (required `reason` text field passed as `{'reason': reason, 'rejection_reason': reason}`).
   - Display age / SLA urgency pill (`> 48h` amber, `> 5d` danger) from `created_at` / `due_at`.
   - Default `LeaderPermissions.canReviewPendingPeers` to `false` unless explicitly granted by backend `can_review_pending_peers`.
9. **Dependencies:** Backend `PendingRequestAdminController` capability check (`API-ENH-03`).
10. **Risk of Regression:** **Low** — Enhances existing `LeaderActivitiesView` using shared `HorizontalSelectionChips` and `StatusPill` widgets.

---

### MOB-ENH-10: Multi-Assignment `ProfileAssignedCirclesCard` & Header Scope Switcher Upgrade
1. **Existing Implementation:** `SessionManager` parses `managed_circles` into a `List<String>` of circle names only (`session_manager.dart:64-73`), and `ProfileAssignedCirclesCard` (`profile_assigned_circles_card.dart:115`) renders the hardcoded subtitle `'Active Leadership Assignment'` for every row (and `'National Scope – All circles accessible.'` whenever `circles.isEmpty`, even for an unassigned Chair!).
2. **Existing File Path:** [profile_assigned_circles_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/profile/view/widgets/profile_assigned_circles_card.dart#L68-L129), [profile_model.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/profile/model/profile_model.dart), [dashboard_circle_switcher.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/dashboard/view/widgets/dashboard_circle_switcher.dart)
3. **Existing Class/Function:** `ProfileAssignedCirclesCard`, `UserProfileModel`, `DashboardCircleSwitcher`
4. **Existing API Endpoint:** `GET /api/v1/leader/profile` (`/leader/auth/me`)
5. **Existing Database Table:** `user_organizational_assignments`, `circles`, `circle_members`, `admin_ded_districts`, `industry_director_assignments`
6. **Existing Permission Mechanism:** Authenticated session (`leader.user`).
7. **Actual Limitation:** **FACT:** (a) A Leader who is `Circle Founder` in Circle A and `Chair - Business Growth` in Circle B sees identical `'Active Leadership Assignment'` labels with no role, territory, or tenure dates; (b) if a Circle Leader has `0` resolved circles due to a DB mismatch, `circles.isEmpty` falsely claims `'National Scope – All circles accessible.'` (`L72`)!
8. **Proposed Enhancement:** Parse structured `assignments[]` (with backward-compatible fallback to `managed_circles`) into `LeadershipAssignmentModel { id, roleKey, roleLabel, scopeType, scopeId, scopeName, startsAt, endsAt }`; render actual `roleLabel · scopeType` per row in `ProfileAssignedCirclesCard`; show `'Global Scope'` / `'Country Scope'` **only** when `role == superAdmin || role == countryDirector` (otherwise show `'No active Circle assignments found'`).
9. **Dependencies:** Additive `assignments[]` array on `GET /api/v1/leader/auth/me` (`API-ENH-02`).
10. **Risk of Regression:** **Low** — Preserves `managed_circles` fallback if `assignments[]` is absent.

---

### MOB-ENH-11: Deduplicated All-Circles Report Roster & Conditional `ReportsTrendCard` Re-Mounting
1. **Existing Implementation:** `ReportsBloc` blocks submission when `circleId` is empty (`All Circles`) + roster breakdown is enabled because no multi-circle deduplicated roster endpoint was wired; `ReportsTrendCard` (`reports_trend_card.dart`) is unmounted while `/leader/reports/attendance-trend` returns `[]`.
2. **Existing File Path:** [reports_bloc.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/bloc/reports_bloc.dart), [report_submit_section.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/view/widgets/report_submit_section.dart), [report_history_section.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/view/widgets/report_history_section.dart), [reports_trend_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/view/widgets/reports_trend_card.dart)
3. **Existing Class/Function:** `ReportsBloc._onLoadCirclePeersForReport()`, `ReportSubmitSection`, `ReportHistorySection`, `ReportsTrendCard`
4. **Existing API Endpoint:** `GET /api/v1/leader/reports/roster`, `POST /api/v1/leader/reports/submit`, `GET /api/v1/leader/reports/attendance-trend`
5. **Existing Database Table:** `leader_reports`, `circle_members`, `attendance_records`, `circle_meetings`
6. **Existing Permission Mechanism:** `canAccessReportsTab` & `canSubmitReports`.
7. **Actual Limitation:** **FACT:** Multi-circle leaders (`DED`, `Industry Director`, multi-circle `CF`/`CD`) cannot attach a deduplicated `"N Unique Peers · M Circle Participations"` roster on All-Circles reports, and attendance trends over time are hidden.
8. **Proposed Enhancement:** Once `GET /api/v1/leader/reports/roster?circle_id=all|<uuid>` (`API-ENH-09`) returns deduplicated eligible Peers (`unique_peers_count`, `total_participations_count`, `complete: true`, `peers[]` with `circles[]`), wire `ReportsBloc` to display `"63 Unique Peers · 71 Circle Participations"` (preserving `rosterSubmitBlocker` whenever `complete == false`); re-mount `ReportsTrendCard` inside `ReportHistorySection` strictly when `attendanceTrend.length >= 2`.
9. **Dependencies:** Backend `LeaderReportsService` roster deduplication & real `attendance_records` trend aggregation (`API-ENH-09`).
10. **Risk of Regression:** **Low** — Existing `report_roster_rules_test.dart` and `reports_spline_chart_test.dart` protect both invariants.

---

### MOB-ENH-12: Single-Source Notification Unread Badge Synchronization
1. **Existing Implementation:** Bell badge (`NotificationCountService.unreadCount`), header `"N new"` chip (`NotificationsState.unreadCount`), and feed list (`NotificationsBloc`) compute unread counts differently: `PushNotificationService` increments locally by `+1` on foreground pushes (`L307`), while `NotificationsBloc` filters out non-leader items via `isLeaderNotification` (`notifications_bloc.dart:68`).
2. **Existing File Path:** [notifications_bloc.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/notifications/bloc/notifications_bloc.dart#L65-L77), [notification_count_service.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/services/notification_count_service.dart), [push_notification_service.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/services/push_notification_service.dart#L307)
3. **Existing Class/Function:** `NotificationsBloc._onLoadNotifications()`, `NotificationCountService.fetchUnreadCount()`, `PushNotificationService`
4. **Existing API Endpoint:** `GET /api/v1/leader/notifications`, `GET /api/v1/leader/notifications/unread-count`, `POST /api/v1/leader/notifications/mark-read`
5. **Existing Database Table:** `app_notifications`, `notifications`
6. **Existing Permission Mechanism:** Self-scoped (`auth:sanctum`, `leader.user`).
7. **Actual Limitation:** **FACT:** Observed on device (`LEADER_APP_ARCHITECTURE_PROPOSALS.md` §1.8): bell badge shows `1–2` unread while opening `NotificationsView` displays an empty list, and foreground `+1` drifts from server state; also tapping fake `ldr-welcome-1` items fails UUID mark-read calls.
8. **Proposed Enhancement:** Align backend `unreadCount` & `index` filter (`DOC-49`); on mobile, re-sync `NotificationCountService.instance.fetchUnreadCount()` debounced (500ms) after foreground FCM arrival instead of blind `+1`, and ignore synthetic non-UUID IDs if offline cached.
9. **Dependencies:** Backend notification filter alignment ([49-notification-enhancement-recommendations.md](./49-notification-enhancement-recommendations.md)).
10. **Risk of Regression:** **Low** — Eliminates phantom unread badge counts.

---

### MOB-ENH-13: Peer 360° "I Can Help / Looking For" Capabilities & Privacy-Aware Contact Actions
1. **Existing Implementation:** `PeerProfileOverviewSection` (`peer_profile_overview_section.dart`) displays bio, company, designation, and contact fields, while `PeerCard` (`peer_card.dart`) renders phone/email from list payloads.
2. **Existing File Path:** [peer_profile_overview_section.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/widgets/peer_profile_overview_section.dart), [peer_profile_model.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/model/peer_profile_model.dart), [peer_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peers/view/widgets/peer_card.dart)
3. **Existing Class/Function:** `PeerProfileOverviewSection`, `PeerProfileModel.fromJson`, `PeerCard`
4. **Existing API Endpoint:** `GET /api/v1/leader/members/{member_id}` (`/overview`, `/profile`), `GET /api/v1/leader/peers`
5. **Existing Database Table:** `users` (`i_can_help_with`, `i_am_looking_for`, `superpower`, `hide_phone`, `hide_email`)
6. **Existing Permission Mechanism:** `canViewPeerProfile` & `canViewPeerContactInfo`.
7. **Actual Limitation:** **FACT:** (a) Activity #4 (`i_can_help_with`, `i_am_looking_for`, `superpower`) is stored on `users` and returned on profile APIs, but Leaders cannot see these tags when brokering P2P introductions; (b) when backend masks `phone`/`email` (`hide_phone = true`), `PeerCard` dial/WhatsApp icons must cleanly hide or show `"Hidden by Peer"` without launching broken `tel:`/`https://wa.me/` intents; (c) image parsing must prioritize canonical `profile_photo_image` (`DEVELOPER_RULES.md` §9).
8. **Proposed Enhancement:** Add `"Superpower"`, `"Can Help Peers With"`, and `"Looking For"` chip groups to `PeerProfileOverviewSection` when non-empty; map `json['profile_photo_image']` as primary avatar source across `PeerModel`, `PeerProfileModel`, `ImpacterModel`, and `ReportPeerRosterItem` (keeping legacy fallback keys read-only); disable `tel:`/`wa.me` buttons cleanly when `hidePhone == true` or phone is masked.
9. **Dependencies:** Backend contact-masking fix (`DATA-SEC-04`) and canonical `profile_photo_image` mapping (`API-ENH-10`).
10. **Risk of Regression:** **Low** — Additive UI chips + safer null/masked URL launcher guards.

---

### MOB-ENH-14: Encrypted Offline Hive Cache with TTL & Visible Stale-Data Indicator
1. **Existing Implementation:** `HiveCacheService` caches JSON responses in plaintext Hive boxes on disk; repositories (`PeersRepositoryImpl`, `DashboardRepositoryImpl`, `FinanceRepositoryImpl`) silently serve cached JSON on network errors without timestamping or encryption.
2. **Existing File Path:** [hive_cache_service.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/storage/hive_cache_service.dart), [secure_storage_service.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/storage/secure_storage_service.dart), [dashboard_repository.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/data/repositories/dashboard_repository.dart#L24-L60), [finance_repository.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/data/repositories/finance_repository.dart#L40)
3. **Existing Class/Function:** `HiveCacheService`, `DashboardRepositoryImpl.getMetrics`, `FinanceRepositoryImpl.getMetrics`
4. **Existing API Endpoint:** Offline cache fallback for `/leader/dashboard/metrics`, `/leader/finance/metrics`, `/leader/peers`
5. **Existing Database Table:** Local device Hive box (`leader_app_cache`)
6. **Existing Permission Mechanism:** Local file sandbox only (cleared on manual `SessionManager.clearSession()`).
7. **Actual Limitation:** **FACT:** Unencrypted PII and financial revenue snapshots remain on flash storage indefinitely without TTL (`DATA-SEC-12`), and Leaders viewing stale cached metrics on poor mobile connectivity have no visual cue (`R9` in `LEADER_APP_MASTER_AUDIT.md`).
8. **Proposed Enhancement:** Generate/load a 32-byte `HiveAesCipher` key inside `SecureStorageService` (`FlutterSecureStorage`); wrap cached entries in `{cached_at_utc, role_key, payload}` enforcing a 24-hour TTL and invalidating on role change; pass `isFromCache` + `cachedAt` into `ApiResponse` so `DashboardView`, `FinanceView`, and `PeersView` display a subtle `"Offline snapshot · Updated 2h ago"` banner (`README.md` §9 compliant copy: `"Connection unavailable — showing saved snapshot"` instead of `"network"`!).
9. **Dependencies:** None (`hive` already supports `HiveAesCipher` out of the box with zero new pub packages!).
10. **Risk of Regression:** **Low** — Wrap box migration in `try/catch` (if legacy unencrypted box fails cipher open on first upgrade, delete legacy cache box once and recreate encrypted).
