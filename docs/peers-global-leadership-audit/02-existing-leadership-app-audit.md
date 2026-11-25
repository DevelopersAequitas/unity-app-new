# 02 — Consolidated Audit of the Existing Flutter Leadership App

> **Repository Audited:** `D:\office project\LeaderApp-Fornt-end-` (Branch: `api-integration` — preserved untouched with all uncommitted Round 1–6 enhancements and `documents/audit/` artifacts intact).  
> **Prior Audit Artifacts Verified:** 8 Markdown audit documents + 44 redacted device screenshots in `D:\office project\LeaderApp-Fornt-end-\documents\audit\`.  
> **Related Deliverables:** [01-existing-system-architecture.md](./01-existing-system-architecture.md) · [04-existing-feature-and-screen-inventory.md](./04-existing-feature-and-screen-inventory.md) · [30-existing-notification-system-audit.md](./30-existing-notification-system-audit.md) · [46-mobile-app-enhancement-recommendations.md](./46-mobile-app-enhancement-recommendations.md)

---

## 1. Reuse & Verification of Previous Audit Work (`documents/audit/`)

**FACT:** Prior to this cross-ecosystem audit, an extensive mobile-side audit and six remediation rounds were executed on the `api-integration` branch of `LeaderApp-Fornt-end-`, stored in `D:\office project\LeaderApp-Fornt-end-\documents\audit\`:

| # | Prior Audit Document | Scope Covered in Prior Audit | Verification Against Current Codebase (`FACT`) |
|---:|---|---|---|
| 1 | [LEADER_APP_MASTER_AUDIT.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_MASTER_AUDIT.md) | 29 device-walked screens, 44 redacted screenshots, UX/data/layout defects | **Verified & Extended:** Frontend findings remain accurate except items fixed in Rounds 1–6 (detailed in §2). Backend root causes were previously hypothesised; now verified in PHP source. |
| 2 | [LEADER_APP_ROLE_AND_SCOPE_MATRIX.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_ROLE_AND_SCOPE_MATRIX.md) | 10 Flutter `UserRole` values, 21 permission flags, role scope rules | **Partially Outdated vs Backend:** Correctly documented the 10 Flutter enum values, but missed `viceChair` (rank 30), `secretary` (rank 20), `committee_leader`, `global_founder`, and `ambessader` in backend PHP/SQL. |
| 3 | [LEADER_APP_SCREEN_BY_SCREEN_AUDIT.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_SCREEN_BY_SCREEN_AUDIT.md) | Per-screen UI, state, widget, and API call breakdown across 29 screens | **Verified & Expanded:** Expanded here to cover all **24 feature modules (423 Dart files)** including orphaned bottom sheets and `peer_profile` sub-screens. |
| 4 | [LEADER_APP_GLOBAL_PRODUCT_REQUIREMENTS.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_GLOBAL_PRODUCT_REQUIREMENTS.md) | Deduplicated All-Circles rule, authoritative revenue (`—`), international phone/timezone rules, brand copy rules | **Still Authoritative:** All global business rules remain binding and are enforced in this consolidated audit. |
| 5 | [LEADER_APP_IMPLEMENTATION_PROGRESS.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_IMPLEMENTATION_PROGRESS.md) | Engineering changelog for Rounds 1–6 applied in uncommitted working tree | **Verified in `lib/` & `test/`:** Confirmed present across ~58 uncommitted files and 25 unit/widget tests. |
| 6 | [LEADER_APP_ARCHITECTURE_PROPOSALS.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_ARCHITECTURE_PROPOSALS.md) | Proposed token refresh, unified error handling, server-driven scope | **Validated:** Proposals align with actual backend gaps uncovered in `unity-app-new`. |
| 7 | [LEADER_APP_IMPLEMENTATION_ROADMAP.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_IMPLEMENTATION_ROADMAP.md) | Frontend-focused remediation sequence | **Superseded by Consolidated Ecosystem Roadmap:** Integrated with backend security/API fixes in [50-final-implementation-roadmap.md](./50-final-implementation-roadmap.md). |
| 8 | [LEADER_APP_DESIGN_SYSTEM.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_DESIGN_SYSTEM.md) | `LibreFranklin` typography, `AppColors` / `AppColor`, spacing, motion tokens | **Verified Accurate:** Matches [app.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/app.dart#L31-L84) and `lib/core/theme/*`. |

---

## 2. What Changed Since the Original Screen Audit (Verified Rounds 1–6 in Working Tree)

**FACT:** Inspection of the current `api-integration` working tree confirms the following six improvements are **already implemented in Dart** (uncommitted on `api-integration` and strictly preserved):

| Round | Capability Improved | Verified Dart Files & Tests | Current Classification |
|---:|---|---|---|
| 1 | **Report Submit Header & Paired Button Responsiveness**: Eliminated `RenderFlex` overflow on narrow/scaled viewports; `PrimaryButton` scales height dynamically with text scale. | [report_submit_section.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/view/widgets/report_submit_section.dart), [primary_button.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/widgets/primary_button.dart), [primary_button_responsive_test.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/test/core/widgets/primary_button_responsive_test.dart) | `Fully Implemented` |
| 2 | **Monotone Cubic Spline Charts**: Replaced jagged/overshooting Bézier curves in Finance & Reports trend charts with Fritsch–Carlson monotone cubic interpolation. | [monotone_spline.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/utils/monotone_spline.dart), [finance_spline_chart.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/finance/view/widgets/finance_spline_chart.dart), [reports_spline_chart.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/view/widgets/reports_spline_chart.dart) | `Fully Implemented` |
| 3 | **All-Circles Incomplete Roster Submit Blocker**: Blocks report submission when "All Circles" is selected or roster is incomplete (`rosterSubmitBlocker`). | [reports_bloc.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/bloc/reports_bloc.dart), [report_roster_rules_test.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/test/features/reports/report_roster_rules_test.dart) | `Partially Implemented` (Client guard works; backend `LeaderReportsService::submitReport` still falls back to random first circle if `circle_id` omitted!) |
| 4 | **Dashboard Hero Authoritative Revenue Display**: Removed client-side fallback that substituted `overallDealsClosed` into the Revenue hero card; displays `"Unavailable"` when `overallRevenue` is empty/zero. | [dashboard_hero_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/dashboard/view/widgets/dashboard_hero_card.dart), [dashboard_hero_card_test.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/test/features/dashboard/dashboard_hero_card_test.dart) | `Implemented but Incorrect` (Frontend fixed, **BUT** backend [LeaderDashboardService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderDashboardService.php#L733) still sends synthetic `$dealsSum * 0.05` as `overall_revenue`!) |
| 5 | **International Phone, Currency & Timezone Formatting**: Added `intl`, `timezone`, and `phone_numbers_parser` for E.164 validation and IANA event timezone rendering. | [app_formatters.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/formatting/app_formatters.dart), [phone_number_helper.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/formatting/phone_number_helper.dart), [event_time_display.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/formatting/event_time_display.dart) | `Fully Implemented` |
| 6 | **Typed Notification Deep-Link Navigator & Brand Vocabulary Guard**: Cold-start/background/feed tap queue with 15-min TTL, 2-sec dedupe, permission gate, and Circle/Report entity pre-resolution; automated test banning user-facing `member(s)` / `network(ing)` strings. | [notification_navigator.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/notifications/notification_navigator.dart), [brand_vocabulary_test.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/test/content/brand_vocabulary_test.dart) | `Fully Implemented` (Client navigator solid; backend notification payloads & unread counts still need alignment) |

---

## 3. Newly Uncovered Cross-Stack Defects (Frontend ↔ Backend Verification)

By tracing every Flutter screen through its BLoC, Repository, RemoteDataSource, Dio call, Laravel Route, Controller, Service, and PostgreSQL query, this audit uncovered **12 major cross-stack issues** that could not be confirmed from the Flutter repository alone:

### 3.1 Silent Failure & Unrouted Endpoint in Edit Peer (`Implemented but Not Integrated` / `Incorrect`)
- **Frontend Evidence:** [edit_peer_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/widgets/edit_peer_bottom_sheet.dart#L123-L136) calls `await ApiClient().put(ApiEndpoints.peerDetails(widget.peer.id), body: updated.toJson())` inside `try { ... } catch (_) {}` and then **unconditionally calls `widget.onUpdated(updated)` and closes the sheet**!
- **Backend Evidence:** [LeaderPeersController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Controllers/LeaderPeersController.php#L183-L261) (`update()`) and [LeaderUpdatePeerRequest.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Requests/LeaderUpdatePeerRequest.php) exist in `app/Leader/`, **but no `PUT` or `PATCH` route for `/peers/{id}` or `/leader/peers/{id}` is registered in [routes/leader.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/routes/leader.php#L79-L86)**!
- **Actual Runtime Result:** Every Peer edit in the Leadership App returns HTTP `405 Method Not Allowed`, silently swallows the exception, fakes success in local UI state, and loses all changes on refresh!

### 3.2 Unrouted Circular Publishing & Hardcoded Author (`Implemented but Not Integrated`)
- **Frontend Evidence:** [publish_circular_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/circulars/view/widgets/publish_circular_bottom_sheet.dart#L58-L68) hardcodes `authorName: 'National Directorate', authorRole: 'Super Admin'` and posts via [circulars_remote_datasource.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/data/datasources/remote/circulars_remote_datasource.dart).
- **Backend Evidence:** [LeaderCircularsController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Controllers/LeaderCircularsController.php#L26-L175) (`index`, `publish`) is **100% unrouted**; [routes/leader.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/routes/leader.php#L236-L237) routes `GET /leader/circulars` to consumer `CircularController@index` (reading SQL `circulars`) with **zero `POST` route**.

### 3.3 Missing Chair Assignment Backend Endpoint (`Missing` in `app/Leader/`)
- **Frontend Evidence:** [peer_role_management_section.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peers/view/widgets/peer_role_management_section.dart#L62-L86) renders `"Change Chair (per circle)"`, `"Change Circle Director"`, `"Change Industry Director"`, and `"Change District Exec"` buttons, **all of which merely navigate to `AppRoutes.roleManagement` (`RoleManagementView`)**, which edits *role-level capability checkboxes*, NOT *user-to-circle chair assignments*!
- **Backend Evidence:** Despite `can_assign_circle_chair` in [LeaderPermissionService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L490), **no chair-assignment endpoint exists in `app/Leader/`** (only Admin API `PATCH /api/v1/admin/circles/{id}/assign-leadership-team` and DynamicAccess `POST /api/v1/admin/access/organizational-assignments` exist).

### 3.4 Three Orphaned Creation Bottom Sheets Requiring Raw UUIDs (`Not Required` / `Implemented but Not Integrated`)
- **FACT:** Three bottom-sheet widgets exist in `lib/features/*/view/widgets/` that require typing raw Peer UUIDs (`'Enter peer UUID'`) and are **never imported or invoked by their parent views**:
  1. [create_business_deal_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/business_deals/view/widgets/create_business_deal_bottom_sheet.dart) (not called in [business_deals_view.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/business_deals/view/business_deals_view.dart))
  2. [create_p2p_meeting_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/p2p_meetings/view/widgets/create_p2p_meeting_bottom_sheet.dart) (not called in [p2p_meetings_view.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/p2p_meetings/view/p2p_meetings_view.dart); whereas Peer Profile uses wired [log_p2p_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/widgets/log_p2p_bottom_sheet.dart))
  3. [create_impact_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/impacts/view/widgets/create_impact_bottom_sheet.dart) (not called in [impacts_view.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/impacts/view/impacts_view.dart)).

### 3.5 Client-Side 7-Call Dashboard Fan-Out & Admin Endpoint Fallbacks (`Implemented but Incorrect`)
- **FACT:** In [dashboard_remote_datasource.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/data/datasources/remote/dashboard_remote_datasource.dart) and [activities_remote_datasource.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/data/datasources/remote/activities_remote_datasource.dart), when the user is `superAdmin` or `countryDirector`, Flutter fires **7 additional parallel REST calls** (`/leader/asks/summary`, `/admin/leads/counts`, `/admin/pending-requests/summary`, `/admin/posts`, `/leader/peers?per_page=500`, `/circulars`, `/admin/pending-requests`) and overwrites summary KPIs using `max(...)`. Meanwhile, scoped roles (`DED`, `Industry Director`, `Circle Founder`, `Circle Director`, `Chair`) do NOT run those fallbacks and receive `[]` or synthetic splits (`60%/25%/15%` asks, `70%/30%` certifications, hardcoded `circulars = 5`) from `LeaderDashboardService`!

### 3.6 Role Enum Truncation in Flutter (`Implemented but Incorrect`)
- **FACT:** [user_role.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/enums/user_role.dart) defines only **10 enum values** (`superAdmin`, `countryDirector`, `districtExecDirector`, `industryDirector`, `circleFounder`, `circleDirector`, `circleChair`, `chairBusinessGrowth`, `chairMembership`, `chairEvents`), **omitting `viceChair`, `secretary`, and `committeeLeader`**, and naming `chairEvents` instead of backend `chairEventsPrograms`. When a Vice Chair or Secretary signs in, `UserRole.fromString()` falls back to a default Chair/Founder enum!

### 3.7 Remaining Fabricated Defaults & Placeholders in Dart & PHP (`Implemented but Incorrect`)
- **Flutter Defaults Still Present:**
  - `FinanceMetricsModel.fromJson` ([finance_model.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/finance/model/finance_model.dart)): defaults `activeTenures ?? 42`.
  - `UserSession` / Profile: defaults `memberSince ?? 'Aug 2026'` and `capabilitiesCount ?? 14` (backend actually has 13 capabilities).
  - [circle_leadership_card.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/circle_details/view/widgets/circle_leadership_card.dart#L46-L65): groups Circle Founders & Circle Directors under `"Regional Leaders"` (they are Circle-level leaders, not regional!) and invents fallback designations `'Founder & CEO'`, `'Managing Director'`, `'Ahmedabad'`, `'90%'` attendance!
  - [record_payment_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/finance/view/widgets/record_payment_bottom_sheet.dart#L74-L78): sends literal `'peer_manual'` and `'circle_current'` when inputs are blank!
  - [report_model.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/reports/model/report_model.dart): contains hardcoded `isDummyPeer` name/UUID blocklist.
- **Backend Fake Fallbacks Still Present:**
  - `LeaderDashboardService.php`: `overall_revenue = $dealsSum * 0.05` (`L733`), `60/25/15%` ask splits (`L784-786`), `70/30%` certification splits (`L789-790`), `circulars = 5` (`L798`), 5 fictional `top_impacters` (`"Siddharth Verma"`, `"Ananya Roy"`, etc., `L945-970`).
  - `LeaderReportsService.php`: `attendance_percentage ?? 94.5`, `deals_closed_value ?? '₹18.5L'`, `total_revenue ?? '₹24.0L'` (`L157-179`).
  - `LeaderNotificationsController.php`: returns fake `ldr-welcome-1` sample notifications when a leader's inbox is empty (`L235+`).

### 3.8 Dual Color/Theme Files & Network Token Handling (`Requires Enhancement`)
- **Theme Duplication:** Both `lib/core/theme/app_colors.dart` (`AppColors`) and `lib/core/theme/app_color.dart` (`AppColor`) coexist across views (`DashboardView` uses `AppColors`, `NotificationsView` & `LeaderActivitiesView` use `AppColor`). Recommend consolidating via alias exports without rewriting working screens.
- **Session & Security in `ApiClient`:** [api_client.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/network/api_client.dart) logs raw `Authorization: Bearer <token>` headers under `kDebugMode`, has no global `401 UnauthenticatedException` listener to clear `SessionManager` and redirect to `/login`, and stores an unused `refresh_token` generated as a non-persisted random string in `LeaderAuthService.php:180`.

---

## 4. Verification Status Summary (What Remains Unverified Without Live Production DB Access)

| Area | Verification Level | Explanation |
|---|---|---|
| Flutter Leadership App (`423` Dart files, 25 tests) | **100% Source-Verified (`FACT`)** | Every route, view, bottom sheet, BLoC, datasource, model, and test inspected directly. |
| Laravel 12 Backend (`app/Leader/`, `routes/*`, RBAC, 35 Activities) | **100% Source-Verified (`FACT`)** | All controllers, services, requests, middleware, models, migrations, and cron schedules inspected directly. |
| Live PostgreSQL Pre-Migration Tables (`web_leadership_*`, `leader_interest_submissions` columns, `circle_member_status_enum` values) | **Schema-Inferred (`INFERENCE`)** | Baseline SQL dump predates `database/migrations/`; code uses `Schema::hasColumn` / `Schema::hasTable` guards in several places. Live `information_schema` check recommended before running additive migrations (see [48-database-enhancement-recommendations.md](./48-database-enhancement-recommendations.md)). |
