# 07 — Role-Wise Responsibilities & Governance Specification

> **Audit Scope:** Complete 17-attribute operational and technical specification for all 17 discovered roles across the Peers Global Unity Platform  
> **Overall Status:** `Partially Implemented` (Core executive roles exist; `leader.can` backend capability enforcement is unrouted, and lower-tier officer/committee roles have resolver/UI gaps)  
> **Related Deliverables:** [06-complete-role-hierarchy.md](./06-complete-role-hierarchy.md) · [08-master-role-permission-matrix.md](./08-master-role-permission-matrix.md) · [09-organizational-data-scope-matrix.md](./09-organizational-data-scope-matrix.md) · [10-role-wise-screen-access.md](./10-role-wise-screen-access.md) · [11-approval-and-delegation-authority.md](./11-approval-and-delegation-authority.md)

---

## 1. Governance Principles & Mandatory 17-Attribute Framework

Every role documented below is evaluated against **all 17 mandatory governance attributes**, strictly separating **verified source-code facts (`FACT`)** from **workbook specs (`WORKBOOK REQUIREMENT`)**, **recommended enhancements (`RECOMMENDATION`)**, and **open founder choices (`BUSINESS DECISION REQUIRED`)**.

> [!IMPORTANT]
> **Cross-Role Governance Guardrails:**
> - **Scope Comes From Assignment:** No role (including Country Director, DED, or Industry Director) receives territory or sector data from its role title alone; visibility is strictly bound to active assignment rows (`admin_ded_districts`, `industry_director_assignments`, `user_organizational_assignments`, `circles`, `circle_members`).
> - **Super Admin Privacy Boundary:** Even `superAdmin` / `global_founder` must **not** browse personal `peer_blocks`, private `"Who Viewed My Profile"` (`profile_views`) logs, private unshared drafts, or raw OTPs/passwords without explicit governance justification and immutable audit logging (`activity_logs`).
> - **Configurable Committees:** Circle Powerhouse committees (`circles.calendar`, `roles`, `user_organizational_assignments`) are dynamically configurable and never locked to hardcoded seat counts.

---

## 2. Role 1 — Super Admin / Global Founder

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Central platform steward responsible for global RBAC governance, system configuration, multi-country executive oversight, election campaign administration, financial commission policy, and final exception handling. |
| 2 | **Existing role identifier(s)** | **FACT:** `superAdmin` (Rank `100`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L17)); `roles.key`: `global_admin` (`SA`, L1) & dynamic `global_founder` ([Role.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Models/Role.php#L182)); `AdminAccess::SUPER_ROLE_KEYS` (`super_admin`, `global_admin`, `global_founder`, plus `eed` on Web Admin [AdminAccess.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Support/AdminAccess.php#L20-L23)); Flutter `UserRole.superAdmin` ([user_role.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/enums/user_role.dart#L12)). |
| 3 | **Organizational level** | Level 1 — Global Ecosystem Administration (`admin` / `leadership`). |
| 4 | **Reporting hierarchy** | Apex governance authority; reports to Peers Global Board / Founders. |
| 5 | **Assigned geographic scope** | `Global Scope` (`isUnrestricted: true` across all countries, states, districts, cities). |
| 6 | **Industry scope** | Unrestricted across all Level 1–4 `circle_categories`. |
| 7 | **Circle scope** | All non-deleted `circles` globally (`resolveScopedCircleIds()` returns `null` = unrestricted). |
| 8 | **Direct reports** | Country Directors (`countryDirector` / `eed`), District Executive Directors (`ded`), Industry Directors (`industryDirector`), and Functional Admin Teams. |
| 9 | **Data visibility** | **FACT:** Global visibility across all Circles, deduplicated Peers, Activities, Asks, Finance Ledgers, Pending Requests, Leadership Campaigns, and Audit Logs. **RECOMMENDATION (Privacy Boundary):** Restrict personal `peer_blocks`, `profile_views`, private drafts (`asks.status = 'draft'`), and OTP secrets. |
| 10 | **Available actions** | **FACT:** All 12 default capabilities (`access_dashboard`, `access_teams`, `access_finance`, `regional_data`, `view_peers`, `manage_peers`, `request_actions`, `view_reports`, `manage_finance`, `coin_payouts`, `manage_roles`, `system_configs`) + `SessionManager.permissions -> LeaderPermissions.allGranted` in Flutter ([session_manager.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/helpers/session_manager.dart#L131)). Can configure commission rates, approve Build Circle requests, manage RBAC matrices, assign any leadership role, and broadcast notifications. |
| 11 | **Restricted actions** | **FACT:** Cannot self-approve own Store Wallet Maker-Checker adjustment (`MAKER_CHECKER_VIOLATION` in [AdminStoreWalletAdjustmentController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/V1/Admin/Store/AdminStoreWalletAdjustmentController.php#L80-L84)); cannot mutate/delete `ActivityLog` rows (`BadMethodCallException` in `ActivityLog.php`). |
| 12 | **Approval authority** | **FACT:** Full override approval/rejection across all 10 `PendingRequestAdminController` categories, `CircleJoinRequestService::approveAndJoinCircle()` (single-step full clearance), `BuildYourOwnCircleApprovalService::approve()`, `CertificationSubmissionController`, `LeadershipFinalDecision`, `finance_leader_bonus_awards`, and Maker-Checker peer-admin wallet adjustments. |
| 13 | **Escalation authority** | Terminal escalation recipient for unresolved Country/DED/ID bottlenecks (escalation engine itself is `Missing`). |
| 14 | **Reporting access** | All global and per-circle reports, CSV activity exports, financial exports, leadership selection analytics, and audit logs. |
| 15 | **Notification eligibility** | Receives system-critical alerts, Build Circle submissions, Leader Interest admin emails (`BecomeLeaderAdminNotificationMail`), and can send Super Admin push broadcasts (`/api/v1/leader/admin/notifications/send`). |
| 16 | **Existing backend enforcement** | **FACT:** Enforced in `LeaderFinanceController::updateCommissionRates()` ([L68](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Controllers/LeaderFinanceController.php#L68)), `PendingRequestAdminController::resolveLeaderScope()` ([L1381](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/Admin/PendingRequestAdminController.php#L1381)), and `AdminRoleMiddleware` (`global_admin` only, [L35](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Middleware/AdminRoleMiddleware.php#L35)). |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) `global_founder` is missing from `UserRoleResolverService::normalizeRoleKey()` and `AdminRoleMiddleware`; (2) `superAdmin` omits `track_milestones` in `getDefaultRoleCapabilities()` ([LeaderPermissionService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L214-L227)); (3) `/api/v1/rbac/*` is unauthenticated ([routes/api.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/routes/api.php#L312-L380)); (4) hardcoded OTP `'123456'` and `@gmail.com` bypasses expose Super Admin accounts in production. |

---

## 3. Role 2 — Country Director / EED

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | National executive leader responsible for scaling Circles, overseeing District Executive Directors and Industry Directors, governing national revenue/collections, and handling national-level approvals within an assigned country. |
| 2 | **Existing role identifier(s)** | **FACT:** `countryDirector` (Rank `90`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L18)); `roles.key`: `eed` (`EED`, L2, [RoleHierarchySeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/RoleHierarchySeeder.php#L47)); Flutter `UserRole.countryDirector` ([user_role.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/enums/user_role.dart#L11)); React Admin `eed` (`048fa24f-ef7b-402a-a92c-569df73ec78e`, `4c5fea6e-795b-4df3-b5d2-54f29e0888db`, [roleAccess.ts](file:///D:/office%20project/unity-admin-web/src/utils/roleAccess.ts#L169-L183)). |
| 3 | **Organizational level** | Level 2 — National / Country Executive Leadership (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Super Admin / Global Founder; supervises DEDs and Industry Directors within assigned country. |
| 5 | **Assigned geographic scope** | **Target:** Assigned Country (`admin_user_roles.scope_value` / `user_organizational_assignments.country_id`). **FACT (`Implemented but Incorrect`):** Unfiltered `Global Scope` in `LeaderPermissionService::resolveManagedCircles()` ([L517](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L517)), `LeaderDashboardService` ([L67](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderDashboardService.php#L67)), and `AdminAccess::SUPER_ROLE_KEYS` ([L20](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Support/AdminAccess.php#L20)) — only `PermissionService::dataScope()` ([L704-L725](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Admin/PermissionService.php#L704-L725)) filters `circles.country`! |
| 6 | **Industry scope** | All industry verticals operating within the assigned country. |
| 7 | **Circle scope** | All Circles whose `country` / `country_id` matches the assigned country (currently leaked globally in Leader App). |
| 8 | **Direct reports** | District Executive Directors (`ded`) and Industry Directors (`industry_director`) in the assigned country. |
| 9 | **Data visibility** | Deduplicated Peers, Circles, Activities, Asks, Pending Requests, Reports, and authoritative Finance collections within the assigned country. |
| 10 | **Available actions** | **FACT:** 10 default capabilities (`access_dashboard`, `access_teams`, `access_finance`, `regional_data`, `view_peers`, `manage_peers`, `request_actions`, `view_reports`, `manage_finance`, `coin_payouts`). |
| 11 | **Restricted actions** | Cannot modify global RBAC Role Matrix (`manage_roles`), global commission rates (`LeaderFinanceController::updateCommissionRates` checks `isSuperAdmin`), or system maintenance configs (`system_configs`). |
| 12 | **Approval authority** | **FACT:** Authorized in `PendingRequestAdminController::resolveLeaderScope()` ([L1383](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/Admin/PendingRequestAdminController.php#L1383)) and treated alongside `superAdmin` in `rejectCircleJoinRequestViaService` ([L609](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/Admin/PendingRequestAdminController.php#L609)). *(Note: Flutter `SessionManager._resolvePredefinedPermissions` L412 wrongly sets `canReviewPendingPeers: false` when offline/fallback!)* |
| 13 | **Escalation authority** | Receives escalations from DEDs and Industry Directors; escalates cross-country or policy exceptions to Super Admin (`Missing` in code). |
| 14 | **Reporting access** | Country-wide deduplicated KPIs, circle weekly reports, peer exports, and financial exports (`can_export_financial_data: true`, `can_export_global_data: true`). |
| 15 | **Notification eligibility** | National governance circulars, country milestone alerts, and escalated approvals. |
| 16 | **Existing backend enforcement** | **FACT:** Country filter exists in [PermissionService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Admin/PermissionService.php#L704-L725) and [OrganizationalScopeService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/OrganizationalScope/OrganizationalScopeService.php), **not** in `app/Leader/*`. |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: Missing country filter in `LeaderPermissionService::resolveManagedCircles()`, `LeaderDashboardService`, `LeaderTeamsService`, and `AdminCircleScope` (because `eed` is in `SUPER_ROLE_KEYS`); missing `track_milestones` capability by default; hardcoded `in_array($role, ['countryDirector'])` in `LeaderPermissionService` prevents revoking `manage_finance` or `coin_payouts` via the Role Matrix. |

---

## 4. Role 3 — District Executive Director (DED)

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Regional territory builder overseeing all Circles, Circle Founders, Circle Directors, and Peer growth across assigned district(s)/cities (`District -> Industries -> Circles -> Peers`). |
| 2 | **Existing role identifier(s)** | **FACT:** `districtExecDirector` (Rank `80`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L20)); `roles.key`: `ded` (`DED`, L3); `circles.ded_user_id`; Flutter `UserRole.districtExecDirector`; React Admin `ded` (`47aedecc-c312-4727-a31c-83a0fbe6e93f`, `2ee9a07b-c64d-4fc2-8b5f-3127d9785f6a`, `01931000-0000-7000-8000-000000000001`). |
| 3 | **Organizational level** | Level 3 — Regional / Territory Executive Leadership (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Country Director (`eed`) / Super Admin; oversees Circle Directors and Circle Founders within assigned district(s); collaborates laterally with Industry Directors. |
| 5 | **Assigned geographic scope** | Assigned District(s) & constituent Cities via `admin_ded_districts` (`district_id`), `circles.ded_user_id`, and `user_organizational_assignments` ([DistrictScopeService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Api/Ded/DistrictScopeService.php#L18-L57)). |
| 6 | **Industry scope** | All industry verticals present within the assigned district(s). |
| 7 | **Circle scope** | All Circles matching assigned `district_id`, constituent `city_id` / city name, or `circles.ded_user_id` ([AdminCircleScope.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Support/AdminCircleScope.php#L250-L320)). |
| 8 | **Direct reports** | Circle Directors (`circleDirector`) and Circle Founders (`circleFounder`) of Circles in the assigned district. |
| 9 | **Data visibility** | District-scoped deduplicated Peers, Circles, 7 Leadership role drilldowns (`/api/v1/ded/leadership/*`), Activities, Coins, Pending Requests, Finance collections, tenures, and milestones. |
| 10 | **Available actions** | **FACT:** 10 default capabilities (`access_dashboard`, `access_teams`, `access_finance`, `track_milestones`, `regional_data`, `view_peers`, `manage_peers`, `request_actions`, `view_reports`, `manage_finance`). Dedicated `/api/v1/ded/*` API suite (for `AdminUser` tokens) + `/api/v1/leader/*` API suite (for `User` tokens). |
| 11 | **Restricted actions** | Denied `coin_payouts` (unless leaked by `DynamicRbacSeeder` bug!), `manage_roles`, `system_configs`, commission-rate editing, and cross-district data access. |
| 12 | **Approval authority** | **FACT:** Stage-2 / Territory approval on Circle Join Requests (`pending_id_approval` & `ded_approval_status` via `PendingRequestAdminController` [L1625](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/Admin/PendingRequestAdminController.php#L1625) and `POST /api/v1/ded/pending-requests/circle-joining-requests/{id}/ded-approve`), plus district moderation of `visitor-registrations`, `event-joining-requests`, `coin-claims`, and `pending-impacts` ([DedPendingRequestsController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/V1/Ded/DedPendingRequestsController.php)). |
| 13 | **Escalation authority** | Receives circle-level escalations from CDs/CFs; escalates to Country Director / Super Admin (`Missing` runtime engine). |
| 14 | **Reporting access** | District Referral Report (`/api/v1/ded/referral-report`), Life Impact Report, `/api/v1/ded/reports/{type}`, Leader Weekly Reports, Peer CSV export, and Financial data export. |
| 15 | **Notification eligibility** | District join requests, impact/visitor approvals, circle launch notifications (`LeaderNotificationDispatcherService`), and tenure bonus milestones. |
| 16 | **Existing backend enforcement** | **FACT:** Strongly enforced on `/api/v1/ded/*` via `EnsureDedApiAccess` + `DistrictScopeService` ([routes/api.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/routes/api.php#L472-L553)); partially enforced on `/api/v1/leader/*` with IDOR bypasses. |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) Dual auth split: `/api/v1/ded/*` requires `AdminUser` (`EnsureDedApiAccess`), while `/api/v1/leader/*` requires `User` (`EnsureLeaderUser`); (2) `LeaderTeamsService::resolveDedDistrictId()` ([L28-L31](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderTeamsService.php#L28-L31)) accepts arbitrary `?district_id=<uuid>` from query params (**DED District IDOR**); (3) `LeaderPermissionService::resolveManagedCircles()` fallback ([L527](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L527)) reads `.value('district_id')`, dropping secondary districts for multi-district DEDs! |

---

## 5. Role 4 — Industry Director

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Sector/vertical owner governing category taxonomy integrity, cross-circle collaboration, circle launches, and Stage-2 industry clearance of Circle Join Requests across assigned Level-1 Industry vertical(s). |
| 2 | **Existing role identifier(s)** | **FACT:** `industryDirector` (Rank `85` — ranked above DED `80` in `UserRoleResolverService` [L19](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L19)); `roles.key`: `industry_director` (`ID`, L3); aliases `id`, `ied`; `circles.industry_director_user_id`; Flutter `UserRole.industryDirector`; React Admin `industry_director` (`390d6937-...`, `d4245e1e-...`, `0b90db61-...`). |
| 3 | **Organizational level** | Level 3 — Sector / Vertical Executive Leadership (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Country Director / Super Admin; coordinates laterally with DEDs; guides Circle Founders & Circle Directors within assigned industry categories. |
| 5 | **Assigned geographic scope** | City/Regional or Multi-City per `circles.industry_director_user_id` and `user_organizational_assignments` (note: `industry_director_assignments` has **no** `city_id`/`district_id` column — see [industry_director_assignments.sql](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/sql/industry_director_assignments.sql)). |
| 6 | **Industry scope** | Assigned Level-1 `circle_categories` (`industry_director_assignments.industry_id`) cascading to Level 2–4 subcategories via [IndustryDirectorScopeService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Admin/IndustryDirectorScopeService.php). |
| 7 | **Circle scope** | Circles mapped to assigned categories (`circle_category_mappings`), `circles.industry_director_user_id`, or `industry_tags`. |
| 8 | **Direct reports** | Dotted-line sector oversight over Circle Founders and Circle Directors of industry-aligned Circles. |
| 9 | **Data visibility** | Deduplicated Peers in assigned categories/circles, Industry cluster summaries, Stage-2 Circle Join Requests (`pending_id_approval`), Asks, Activities, and industry circle revenue/milestones. |
| 10 | **Available actions** | **FACT:** 7 default capabilities (`access_dashboard`, `access_teams`, `access_finance`, `regional_data`, `view_peers`, `request_actions`, `view_reports`). Also has dedicated Web Admin portal `/admin/industry-director/*` ([routes/web.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/routes/web.php#L748-L754)). |
| 11 | **Restricted actions** | Denied `manage_peers` (`can_add_edit_peer: false`), `manage_finance`, `coin_payouts`, `manage_roles`, and `system_configs`. |
| 12 | **Approval authority** | **FACT:** Authoritative Stage-2 approver for Circle Join Requests (`pending_id_approval -> pending_circle_fee` via `CircleJoinRequestService::approveById()` [L227-L274](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Circles/CircleJoinRequestService.php#L227-L274) and `PendingRequestAdminController` [L1423-L1425, L1625](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/Admin/PendingRequestAdminController.php#L1423-L1425)), including requests for categories that do not yet have an active circle! |
| 13 | **Escalation authority** | Resolves category-clash disputes escalated by CDs/CFs; escalates cross-sector policy issues to Country Director / Super Admin. |
| 14 | **Reporting access** | Industry Circle comparison, weekly circle reports (`can_submit_reports: true` in backend `LeaderPermissionService` L495 vs `✖` in legacy doc), and peer data export (`can_export_peer_data: true`). |
| 15 | **Notification eligibility** | Stage-2 join request alerts (`sendCdApprovedToUser` / ID queue), circle launch bonus tracking (`ID_CIRCLE_LAUNCH_*`, `ID_MEMBER_MILESTONE_*` in `finance_bonus_rules`). |
| 16 | **Existing backend enforcement** | **FACT:** Enforced in `IndustryDirectorScopeService`, `CircleJoinRequestService::approveById()`, and `PendingRequestAdminController::resolveLeaderScope()`. |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) `LeaderPermissionService::resolveManagedCircles()` ([L558](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L558)) uses `orWhere('industry_tags', 'like', "%{$indId}%")` — industry ID `1` matches `10`, `11`, `21`, leaking unrelated circles! (2) Missing `track_milestones` capability in `getDefaultRoleCapabilities()` despite `ID_*` bonus rules existing in `finance_bonus_rules`! (3) Flutter `SessionManager._resolvePredefinedPermissions` ([L373](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/helpers/session_manager.dart#L373)) sets `canReviewPendingPeers: false`, contradicting `UserRole.canModeratePendingRequests` (`true`) and backend `LeaderPermissionService` (`true`). |

---

## 6. Role 5 — Circle Founder

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Visionary entrepreneur who initiates, charters, and seeds a new Circle (often via *"Build Your Own Circle"*), curating founding Peers across open Level-4 business categories, achieving launch/momentum milestones, and transitioning monthly operations to a Circle Director. |
| 2 | **Existing role identifier(s)** | **FACT:** `circleFounder` (Rank `50`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L22)); `roles.key`: `circle_founder` (`CF`, L4) + dynamic `cf`, `founder`; PG enum `'founder'`; `circles.founder_user_id` & `circle_founder_user_id`; Flutter `UserRole.circleFounder`; React Admin `circle_founder` (`0ce4cbe4-...`, `4e8a4915-...`, `91710b30-...`). |
| 3 | **Organizational level** | Level 4 — Circle Founding Leadership (`leadership`; outside the numbered operational ladder on peersglobal.com, ranked `50` below `circleDirector` `60` in backend). |
| 4 | **Reporting hierarchy** | Reports to assigned DED and aligns vertically with Industry Director; partners with Circle Director and supervises Circle Chairs/Officers during formation. |
| 5 | **Assigned geographic scope** | Circle's assigned City/District (`Own Circle` scope). |
| 6 | **Industry scope** | Circle's mapped industry vertical(s) and 172+ Level-4 category seats. |
| 7 | **Circle scope** | Assigned Circle(s) where user holds `founder_user_id`, `circle_founder_user_id`, `calendar.leadership.founder_user_id`, or approved `circle_members.role = 'founder'`. |
| 8 | **Direct reports** | Founding Committee Chairs, Vice Chair, Secretary, and Peers (until operational handover to Circle Director). |
| 9 | **Data visibility** | Own-Circle Peers, Category Availability (open vs occupied seats), Circle Events, Activities, Asks, Stage-1 Pending Requests, Weekly Reports, and Circle Finance/Milestone progress (`CF_SPEED_*`, `CF_QUALITY_*`, `CF_CULTURE_LEGACY_6M`). |
| 10 | **Available actions** | **FACT:** 8 default capabilities (`access_dashboard`, `access_teams`, `access_finance`, `track_milestones`, `view_peers`, `manage_peers`, `request_actions`, `view_reports`). Can nominate open-category candidates (`POST /teams/circles/{id}/refer-category`), review pending requests, log P2P/Referrals, send celebration wishes, and submit reports. |
| 11 | **Restricted actions** | Denied `regional_data`, `manage_finance` (cannot record offline payments or edit commissions), `coin_payouts`, `manage_roles`, and `system_configs`. |
| 12 | **Approval authority** | **FACT:** Stage-1 Circle clearance (`pending_cd_approval -> pending_id_approval`) and circle-scoped pending request moderation in `PendingRequestAdminController` ([L1386, L1624-L1628](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/Admin/PendingRequestAdminController.php#L1624-L1628)). |
| 13 | **Escalation authority** | Escalates Stage-2 join bottlenecks or category conflicts to DED / Industry Director. |
| 14 | **Reporting access** | Circle weekly/monthly reports (`view_reports`, `can_submit_reports: true`), Circle Peer export (`can_export_peer_data: true`), and formation/tenure bonus tracking. |
| 15 | **Notification eligibility** | Build Circle approval, Stage-1 join requests, peer milestones/celebrations, and CF speed/legacy bonus notifications. |
| 16 | **Existing backend enforcement** | **FACT:** Provisioned automatically by `BuildYourOwnCircleApprovalService::approve()` ([L68-L142](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Circles/BuildYourOwnCircleApprovalService.php#L68-L142)); scoped via `LeaderTeamsService::resolveScopedCircleIds()` and `PendingRequestAdminController::resolveLeaderScope()`. |
| 17 | **Missing/broken functionality** | `Partially Implemented`: (1) `can_assign_circle_chair` is `true` in `LeaderPermissionService` ([L490](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L490)), **but no `/api/v1/leader/*` chair-assignment endpoint exists**; (2) No structured CF-to-CD handover workflow UI/API exists despite `CF_CULTURE_LEGACY_6M` referencing *"6 months after CF handover to CD"*; (3) Circle Details UI mislabels Circle Founder under `"Regional Leaders"` ([LEADER_APP_SCREEN_BY_SCREEN_AUDIT.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_SCREEN_BY_SCREEN_AUDIT.md#L104)). |

---

## 7. Role 6 — Circle Director

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Primary operational chief executive of a Circle (Stage 003 *"Monthly Leadership"*), accountable for monthly Conclave cadence, 90%+ attendance, Peer retention, Powerhouse committee execution, Stage-1 applicant vetting, and verified Peer collaboration outcomes. |
| 2 | **Existing role identifier(s)** | **FACT:** `circleDirector` (Rank `60`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L21)); `roles.key`: `circle_director` (`CD`, L4) + dynamic `cd`, `director`; PG enum `'director'`; `circles.director_user_id` & `circle_director_user_id`; Flutter `UserRole.circleDirector`; React Admin `circle_director` (`b70d6344-...`, `f4646a9b-...`, `0013d3b9-...`). |
| 3 | **Organizational level** | Level 4 — Circle Executive Leadership (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to District Executive Director (`ded`) and aligns with Industry Director; directly leads Circle Chairs, Vice Chair, Secretary, Committee Leaders, and Circle Peers. |
| 5 | **Assigned geographic scope** | Circle's City/District (`Own Circle` scope). |
| 6 | **Industry scope** | Circle's industry vertical(s) and Level-4 category seats. |
| 7 | **Circle scope** | Assigned Circle(s) (`director_user_id`, `circle_director_user_id`, `calendar.leadership.director_user_id`, or approved `circle_members.role = 'director'`). |
| 8 | **Direct reports** | Business Growth Chair, Membership Growth Chair, Events & Impacts Chair, Generic Circle Chair, Vice Chair, Secretary, Committee Leaders, and Circle Peers. |
| 9 | **Data visibility** | Full 360° visibility of Circle Peers (`LeaderMember360Service`), Circle Events & Attendance, Asks, Activities, Stage-1 Pending Requests, Weekly Reports, and Circle Finance/Tenure Milestones (`CD_SPEED_*`, `CD_QUALITY_*`, `CD_STRENGTH_50P..100P`). |
| 10 | **Available actions** | **FACT:** Identical 8 default capabilities to Circle Founder (`access_dashboard`, `access_teams`, `access_finance`, `track_milestones`, `view_peers`, `manage_peers`, `request_actions`, `view_reports`). Can flag peer status (`approved`, `needs_attention`, `under_review`), add coaching notes, send reminders, moderate circle posts/creatives, approve Stage-1 join requests, and submit weekly reports. |
| 11 | **Restricted actions** | Denied `regional_data`, `manage_finance`, `coin_payouts`, `manage_roles`, `system_configs`, and Stage-2 ID/DED join approval. |
| 12 | **Approval authority** | **FACT:** Primary Stage-1 approver (`CircleJoinRequestService::approveByCd()` / `rejectByCd()`, [L152-L225](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Circles/CircleJoinRequestService.php#L152-L225)) transitioning `pending_cd_approval -> pending_id_approval`, plus circle-scoped pending impacts, visitor registrations, event joining requests, and peer referrals via `PendingRequestAdminController`. |
| 13 | **Escalation authority** | Receives escalations from Circle Chairs/Officers; escalates unresolved attendance, conduct, or Stage-2 clearance issues to DED / Industry Director. |
| 14 | **Reporting access** | Submits and downloads Circle Weekly/Monthly Reports (`POST /api/v1/reports/submit`, `GET /reports/{id}/file`), exports Circle Peer data. |
| 15 | **Notification eligibility** | Circle join requests (note: initial submission notification is currently commented out at `CircleJoinRequestService.php:99`!), pending impacts, visitor registrations, peer celebrations, and CD strength/quality bonuses. |
| 16 | **Existing backend enforcement** | **FACT:** Enforced in `CircleJoinRequestService::approveByCd()`, `PendingRequestAdminController::resolveLeaderScope()`, and `LeaderMember360Service::resolveMember()`. |
| 17 | **Missing/broken functionality** | `Partially Implemented`: (1) `CircleJoinRequestService::submitRequest()` ([L99](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Circles/CircleJoinRequestService.php#L99)) has `// Notification on circle join request submission is intentionally disabled`, leaving CDs unaware of new join requests until polling; (2) Missing Leader App endpoint to assign/rotate Circle Chairs (`can_assign_circle_chair`); (3) `LeaderPeersController::update` is unrouted (`Implemented but Not Integrated`). |

---

## 8. Role 7 — Chair - Business Growth Committee

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Powerhouse Committee Chair driving commercial outcomes inside a Circle: 1-on-1 P2P meetings, qualified referrals, business deals closed, Ask fulfillment, and receiver confirmation of Life Impact. |
| 2 | **Existing role identifier(s)** | **FACT:** `chairBusinessGrowth` (Rank `40`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L23)); `roles.key`: `chair_business_growth_committee` (`CBG`, L5, [DynamicRbacSeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/DynamicRbacSeeder.php#L184)); `circles.business_growth_committee_chair_user_id` & `calendar.leadership.business_growth_committee_chair*`; Flutter `UserRole.chairBusinessGrowth`; React Admin `chair_business_growth_committee` (`d024091c-...`, `32120618-...`, `e932a30a-...`). |
| 3 | **Organizational level** | Level 5 — Circle Powerhouse Committee Chair (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Circle Director (and Circle Founder during formation); coordinates Business Growth Committee Leaders. |
| 5 | **Assigned geographic scope** | `Own Circle` (Circle's City). |
| 6 | **Industry scope** | Own Circle's Peers across categories. |
| 7 | **Circle scope** | Assigned Circle(s). |
| 8 | **Direct reports** | Business Growth Committee Leaders (`committee_leader`). |
| 9 | **Data visibility** | Own-Circle Peers, Business Deals, Referrals, P2P Meetings, Collaboration/Referral/Help Asks, Circle Events, and Circle Weekly Reports. **Denied** Circle/Platform Finance & Revenue (`can_view_overall_revenue: false`). |
| 10 | **Available actions** | **FACT:** 5 default capabilities (`access_dashboard`, `access_teams`, `view_peers`, `request_actions`, `view_reports`). Can view Circle & Peer 360° profiles, facilitate P2P meetings/referrals, nominate open-category peers, send celebration wishes, and submit committee/weekly reports. |
| 11 | **Restricted actions** | Denied `access_finance`, `manage_finance`, `manage_peers` (`can_add_edit_peer: false`), `can_assign_circle_chair`, `coin_payouts`, `regional_data`, `manage_roles`, `system_configs`, and Pending Request moderation (`403` in `PendingRequestAdminController::resolveLeaderScope()` L1390). |
| 12 | **Approval authority** | **None (`Denied`):** Excluded from `$allowedRoles` in `PendingRequestAdminController::resolveLeaderScope()` ([L1381-L1396](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/Admin/PendingRequestAdminController.php#L1381-L1396)) and `UserRole.canModeratePendingRequests` ([user_role.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/enums/user_role.dart#L28-L35)). |
| 13 | **Escalation authority** | Escalates inactive referral follow-ups or commercial disputes to Circle Director. |
| 14 | **Reporting access** | View & submit Circle/Committee reports (`view_reports`, `can_submit_reports: true`); denied CSV Peer/Finance exports (`can_export_peer_data: false`). |
| 15 | **Notification eligibility** | Circle commercial activity alerts, P2P/Referral triggers, celebration reminders, and circulars. |
| 16 | **Existing backend enforcement** | **FACT:** Blocked from `/leader/pending-requests/*` (`403` at `PendingRequestAdminController.php:1576`), **but** `CheckLeaderCapability` is unrouted on `/finance/*`, `/roles/*`, and `DELETE /members/{id}/posts/{post_id}`! |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) `LeaderPermissionService::resolvePermissionMatrix()` ([L479](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L479)) sets `can_review_pending_peers = $hasCap('access_teams') || ...`, which evaluates to **`true`** for Chairs (since Chairs have `access_teams`), showing the `"Review Applications"` UI button only for the API to reject them with `403`! (2) `LeaderPermissionService::resolveManagedCircles()` ([L564-L573](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L564-L573)) forgets to check `business_growth_committee_chair_user_id` and `calendar->leadership`! (3) No dedicated committee task/seat model exists. |

---

## 9. Role 8 — Chair - Membership Growth Committee

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Powerhouse Committee Chair responsible for Circle category expansion, visitor conversion follow-up, new-Peer onboarding experience, retention health, and milestone celebrations. |
| 2 | **Existing role identifier(s)** | **FACT:** `chairMembership` (Rank `38`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L24)); `roles.key`: `chair_membership_growth_committee` (`CMG`, L5, [DynamicRbacSeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/DynamicRbacSeeder.php#L190)); `circles.membership_growth_committee_chair_user_id` & `calendar` JSON; Flutter `UserRole.chairMembership` (`'Chair - Membership'`; stale `chairPeerExperience` in `README.md`); React Admin `chair_membership_growth_committee` (`dc6b0330-...`, `1454e712-...`, `553af86c-...`). |
| 3 | **Organizational level** | Level 5 — Circle Powerhouse Committee Chair (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Circle Director; coordinates Membership Growth Committee Leaders. |
| 5 | **Assigned geographic scope** | `Own Circle`. |
| 6 | **Industry scope** | Own Circle's open and filled Level-4 business categories. |
| 7 | **Circle scope** | Assigned Circle(s). |
| 8 | **Direct reports** | Membership Growth Committee Leaders (`committee_leader`). |
| 9 | **Data visibility** | Own-Circle Peers, Open Category seats (`/teams/circles/{id}/sub-industries`), Visitor Registrations, Recommended Peers, Introduced Peers, Celebrations, and Attendance/Retention stats. Denied Finance & Revenue. |
| 10 | **Available actions** | **FACT:** 5 default capabilities (`access_dashboard`, `access_teams`, `view_peers`, `request_actions`, `view_reports`). Can refer candidates to open categories, send birthday/anniversary wishes, track onboarding, and submit reports. |
| 11 | **Restricted actions** | Denied `access_finance`, `manage_finance`, `manage_peers` (in backend default; **note frontend discrepancy below**), `coin_payouts`, `manage_roles`, and formal `CircleJoinRequest` approval (`Requires Higher Approval` by CD -> ID/DED). |
| 12 | **Approval authority** | **None (`Requires Higher Approval`):** Can screen/recommend peers, but formal Stage-1 approval requires `circleDirector` / `circleFounder` (`PendingRequestAdminController.php:1381`). |
| 13 | **Escalation authority** | Escalates at-risk / low-attendance Peers (`needs_attention`) and pending join applications to Circle Director. |
| 14 | **Reporting access** | View & submit Circle/Membership reports (`view_reports`); denied bulk exports. |
| 15 | **Notification eligibility** | Visitor registrations, peer recommendations, celebrations, and circle circulars. |
| 16 | **Existing backend enforcement** | **FACT:** Same `EnsureLeaderUser` + `PendingRequestAdminController` (`403`) enforcement as `chairBusinessGrowth`. |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) Flutter `SessionManager._resolvePredefinedPermissions` ([session_manager.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/helpers/session_manager.dart#L335-L349)) sets `canAddEditPeer: true` for `chairMembership`, whereas backend `LeaderPermissionService` ([L130-L136](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L130-L136)) omits `manage_peers` (`can_add_edit_peer: false`); (2) Same `can_review_pending_peers: true` UI vs `403` backend mismatch; (3) `resolveManagedCircles()` omits `membership_growth_committee_chair_user_id`. |

---

## 10. Role 9 — Chair - Events & Impacts Committee

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Powerhouse Committee Chair orchestrating Circle Conclaves, guest speakers, event attendance/check-in readiness, and community Life Impact initiatives. |
| 2 | **Existing role identifier(s)** | **FACT:** `chairEventsPrograms` (Rank `36`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L25)); `roles.key`: `chair_events_impacts_committee` (`CEI`, L5, [DynamicRbacSeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/DynamicRbacSeeder.php#L196)); `circles.events_impacts_committee_chair_user_id` & `calendar` JSON; Flutter `UserRole.chairEvents` (`'Chair - Events & Programs'`; stale `chairOperationsTech` in `README.md`); React Admin `chair_events_impacts_committee` (`4b604e24-...`, `385752e6-...`, `a3200289-...`). |
| 3 | **Organizational level** | Level 5 — Circle Powerhouse Committee Chair (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Circle Director; coordinates Events & Impacts Committee Leaders. |
| 5 | **Assigned geographic scope** | `Own Circle`. |
| 6 | **Industry scope** | Own Circle's Peers. |
| 7 | **Circle scope** | Assigned Circle(s). |
| 8 | **Direct reports** | Events & Impacts Committee Leaders (`committee_leader`). |
| 9 | **Data visibility** | Own-Circle Events & Gatherings (`/teams/circles/{id}/events`), Event Registrations, Visitor Registrations, Life Impact streams (`/activities/impacts`), Peers, and Reports. Denied Finance & Revenue. |
| 10 | **Available actions** | **FACT:** 5 default capabilities (`access_dashboard`, `access_teams`, `view_peers`, `request_actions`, `view_reports`). Can inspect event rosters, track impact stories, send wishes, and submit post-meeting reports. |
| 11 | **Restricted actions** | Denied `access_finance`, `manage_finance`, `manage_peers`, `coin_payouts`, `manage_roles`, and formal Pending Impact / Event Join approval (restricted to CD/CF/ID/DED/EED/SA in `PendingRequestAdminController`). |
| 12 | **Approval authority** | **None (`Requires Higher Approval`):** Formal impact/visitor/event approval requires Circle Director or higher (`BUSINESS DECISION REQUIRED`: whether Events & Impacts Chair should be delegated `event_joining_requests` & `visitor_registrations` clearance). |
| 13 | **Escalation authority** | Escalates venue/budget needs and pending impact verifications to Circle Director. |
| 14 | **Reporting access** | View & submit Circle/Event attendance reports (`view_reports`, `can_submit_reports: true`). |
| 15 | **Notification eligibility** | Upcoming conclave reminders, event registration alerts, and circle circulars. |
| 16 | **Existing backend enforcement** | **FACT:** Enforced identically to `chairBusinessGrowth` in `EnsureLeaderUser` and `PendingRequestAdminController`. |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) Key naming drift across all 3 layers (`chairEventsPrograms` vs `chair_events_impacts_committee` vs `chairEvents`); (2) No Leader App event-creation or QR check-in host workflow exists (QR scanning lives in separate `scan_app_users` / Member App `EventCheckinService`); (3) `resolveManagedCircles()` omits `events_impacts_committee_chair_user_id`. |

---

## 11. Role 10 — Generic Circle Chair

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | General Circle presiding officer (`chair` / `circle_chair`) supporting the Circle Director in Circles that appoint a single Chair rather than (or alongside) specialized committee chairs. |
| 2 | **Existing role identifier(s)** | **FACT:** `circleChair` (Rank `34`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L26)); `roles.key`: `chair` (`CHR`, L5, [RoleHierarchySeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/RoleHierarchySeeder.php#L54)); PG enum `'chair'`; `circles.chair_user_id`; Flutter `UserRole.circleChair`; React Admin `chair` (`8f100834-...`, `d1762bfd-...`). |
| 3 | **Organizational level** | Level 5 — Circle Presiding Officer (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Circle Director; works alongside Vice Chair and Secretary. |
| 5 | **Assigned geographic scope** | `Own Circle`. |
| 6 | **Industry scope** | Own Circle's Peers. |
| 7 | **Circle scope** | Assigned Circle(s) (`circles.chair_user_id`, `calendar.leadership.chair_user_id`, or `circle_members.role = 'chair'`). |
| 8 | **Direct reports** | Vice Chair, Secretary, and Committee Leaders (per Circle configuration). |
| 9 | **Data visibility** | Own-Circle Peers, Teams/Circles, Activities, Asks, Events, and Weekly Reports. Denied Finance & Revenue. |
| 10 | **Available actions** | **FACT:** 5 default capabilities (`access_dashboard`, `access_teams`, `view_peers`, `request_actions`, `view_reports`). |
| 11 | **Restricted actions** | Denied `access_finance`, `manage_finance`, `manage_peers`, `coin_payouts`, `manage_roles`, and `PendingRequestAdminController` moderation. |
| 12 | **Approval authority** | **None (`Requires Higher Approval`):** Delegated moderation is restricted to CD/CF/ID/DED/EED/SA. |
| 13 | **Escalation authority** | Escalates Circle operational matters to Circle Director. |
| 14 | **Reporting access** | View & submit Circle Weekly Reports (`view_reports`, `can_submit_reports: true`). |
| 15 | **Notification eligibility** | Circle activity, meeting, and circular notifications. |
| 16 | **Existing backend enforcement** | **FACT:** Handled in `LeaderPermissionService::getDefaultRoleCapabilities()` ([L144-L150](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L144-L150)). |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: **Dead/Unreachable Code Bug!** `UserRoleResolverService::normalizeRoleKey()` ([L50](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L50)) maps `'circlechair'`, `'circle_chair'`, and `'chair'` to `'chairBusinessGrowth'`, and [L321-L323](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L321-L323) maps `circles.chair_user_id` to `'chairBusinessGrowth'`. Thus, every Generic Circle Chair is mislabeled `"Chair - Business Growth Committee"` in the mobile app! Worse, `UserRole` in Flutter uses `UserRole.circleChair` as the **silent fallback for any unrecognized role** ([session_manager.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/helpers/session_manager.dart#L62)). |

---

## 12. Role 11 — Vice Chair

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Deputy Circle officer assisting the Circle Director and Chair in meeting facilitation, peer engagement follow-ups, and standing in when the Chair is absent. |
| 2 | **Existing role identifier(s)** | **FACT:** `viceChair` (Rank `30`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L27)); `roles.key`: `vice_chair` (`VC`, L6, [RoleHierarchySeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/RoleHierarchySeeder.php#L56)); PG enum `'vice_chair'`; `circles.vice_chair_user_id`; **Absent** in Flutter `UserRole`; React Admin `vice_chair` (`1f9f3e22-...`, `5bd134ea-...`). |
| 3 | **Organizational level** | Level 6 — Circle Deputy Officer (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Circle Director and Circle Chair. |
| 5 | **Assigned geographic scope** | `Own Circle`. |
| 6 | **Industry scope** | Own Circle's Peers. |
| 7 | **Circle scope** | Assigned Circle(s) (`circles.vice_chair_user_id`, `calendar.leadership.vice_chair_user_id`, `circle_members.role = 'vice_chair'`). |
| 8 | **Direct reports** | None (assists Chair/Secretary). |
| 9 | **Data visibility** | Own-Circle Dashboard KPIs, Peers directory, Activities, Celebrations, and read-only Circle Reports (`view_reports`). Denied Teams tab (`access_teams: false`) and Finance tab (`access_finance: false`). |
| 10 | **Available actions** | **FACT:** 4 default capabilities (`access_dashboard`, `view_peers`, `request_actions`, `view_reports`). Can view Peers, log P2P/Referrals, send celebration wishes, and **read** weekly reports (`can_submit_reports: false` at `LeaderPermissionService.php:495`). |
| 11 | **Restricted actions** | Denied `access_teams`, `access_finance`, `manage_peers`, `can_submit_reports`, `can_review_pending_peers`, `can_export_*`, `coin_payouts`, `manage_roles`, and `system_configs`. |
| 12 | **Approval authority** | **None (`Denied`):** Blocked from pending request moderation (`PendingRequestAdminController.php:1390`). |
| 13 | **Escalation authority** | Escalates peer attendance or substitution issues to Circle Chair / Circle Director. |
| 14 | **Reporting access** | **Read-only** access to Circle Reports (`can_access_reports_tab: true`, `can_submit_reports: false`). |
| 15 | **Notification eligibility** | Circle meeting alerts, peer celebrations, and circulars. |
| 16 | **Existing backend enforcement** | **FACT:** Capability defaults defined in [LeaderPermissionService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L152-L157), **but** not enforced at route level (`leader.can` unused), and `DynamicRbacSeeder` leaks `coin_payouts` via `coins` module! |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) Missing from Flutter `UserRole` enum — coerced to `UserRole.circleChair`; (2) `LeaderPermissionService::resolveManagedCircles()` ([L564-L573](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L564-L573)) omits `vice_chair_user_id`; (3) Cannot act as formal temporary delegate for an absent Chair/CD because no delegation engine exists. |

---

## 13. Role 12 — Secretary

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Circle administrative officer responsible for meeting minutes, attendance roster accuracy, substitute logging, and official Circle record-keeping. |
| 2 | **Existing role identifier(s)** | **FACT:** `secretary` (Rank `20`, [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L28)); `roles.key`: `secretary` (`SEC`, L7, [RoleHierarchySeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/RoleHierarchySeeder.php#L57)); PG enum `'secretary'`; `circles.secretary_user_id`; **Absent** in Flutter `UserRole`; React Admin `secretary` (`0e76aa19-...`, `a84e0e1d-...`). |
| 3 | **Organizational level** | Level 7 — Circle Officer (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Circle Director and Circle Chair. |
| 5 | **Assigned geographic scope** | `Own Circle`. |
| 6 | **Industry scope** | Own Circle's Peers. |
| 7 | **Circle scope** | Assigned Circle(s) (`circles.secretary_user_id`, `calendar.leadership.secretary_user_id`, `circle_members.role = 'secretary'`). |
| 8 | **Direct reports** | None. |
| 9 | **Data visibility** | Own-Circle Dashboard KPIs, Peers directory, Celebrations, Activities, and Circle Reports. Denied Teams management and Finance. |
| 10 | **Available actions** | **FACT:** 4 default capabilities (`access_dashboard`, `view_peers`, `request_actions`, `view_reports`). *(Note: `can_submit_reports` is `false` in `LeaderPermissionService.php:495` — see business confirmation note!)* |
| 11 | **Restricted actions** | Denied `access_teams`, `access_finance`, `manage_peers`, `can_review_pending_peers`, `can_export_*`, `coin_payouts`, `manage_roles`, and `system_configs`. |
| 12 | **Approval authority** | **None (`Denied`).** |
| 13 | **Escalation authority** | Escalates chronic absences (`absence_warnings`) or roster discrepancies to Circle Director. |
| 14 | **Reporting access** | Read-only Circle Reports today (`can_submit_reports: false`). **BUSINESS DECISION REQUIRED:** Confirm whether Secretary should be granted `can_submit_reports: true` to file weekly meeting minutes & attendance rosters on behalf of the Circle Director! |
| 15 | **Notification eligibility** | Meeting schedule reminders, celebration alerts, and circulars. |
| 16 | **Existing backend enforcement** | **FACT:** Same as `viceChair` ([LeaderPermissionService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L159-L164)). |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) Missing in Flutter `UserRole` (coerced to `circleChair`); (2) `resolveManagedCircles()` omits `secretary_user_id`; (3) Because `/roles/matrix`, `/admin/notifications/*`, and `/finance/transactions/record-offline` lack `leader.can` / `superAdmin` guards, a `secretary` token can currently mutate RBAC permissions or broadcast push alerts via raw API calls! |

---

## 14. Role 13 — Committee Leader

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Operational contributor within a Circle Powerhouse Committee (e.g., Business Growth, Membership Experience, Events & Impact) executing specific member engagement, hospitality, or referral follow-up tasks under a Committee Chair. |
| 2 | **Existing role identifier(s)** | **FACT:** `roles.key`: `committee_leader` (`CML`, L8, [RoleHierarchySeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/RoleHierarchySeeder.php#L59)); PG enum `'committee_leader'` ([CircleMember.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Models/CircleMember.php#L32)); `circles.powerhouse_user_id`; React Admin `committee_leader` (`709b0c51-...`, `7797efd0-...`). **Missing** in `UserRoleResolverService` and Flutter `UserRole`! |
| 3 | **Organizational level** | Level 8 — Circle Powerhouse Committee Contributor (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to assigned Committee Chair and Circle Director. |
| 5 | **Assigned geographic scope** | `Own Circle`. |
| 6 | **Industry scope** | Own Circle's Peers. |
| 7 | **Circle scope** | Assigned Circle & Committee (`user_organizational_assignments.committee_key` / `circle_members.role = 'committee_leader'`). |
| 8 | **Direct reports** | None. |
| 9 | **Data visibility** | **Intended:** Own-Circle Dashboard, Peers directory, Celebrations, and assigned Committee tasks/events. **Current (`Implemented but Incorrect`):** Blocked from Leader App (`403 NOT_A_LEADER`). |
| 10 | **Available actions** | **FACT:** In Member App, can participate in Circle Leadership Group Chat (`/api/v1/circles/my-leadership-circles`, `CircleLeadershipController.php:25`, because `CircleMember::leadershipRoles()` includes `'committee_leader'`). In Web Admin, scoped to circle read views via `AdminCircleScope::ROLE_PRIORITY` ([L23](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Support/AdminCircleScope.php#L23)). |
| 11 | **Restricted actions** | Denied Finance, Teams mutation, Peer editing, Pending Request approvals, Report submission, Exports, and RBAC administration. |
| 12 | **Approval authority** | **None (`Denied`).** |
| 13 | **Escalation authority** | Escalates task blockers to Committee Chair. |
| 14 | **Reporting access** | Read-only Circle summary (`Not Yet Supported` in Leader App until resolver adds `committeeLeader`). |
| 15 | **Notification eligibility** | Circle leadership group chat messages (`circle_leadership_messages`) and committee circulars. |
| 16 | **Existing backend enforcement** | **FACT:** Recognized in `CircleMember::leadershipRoles()`, `AdminCircleScope`, and `DynamicRbacSeeder`, **rejected** by `EnsureLeaderUser` (`UserRoleResolverService::isLeaderRole()` returns `false`). |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: Missing from `UserRoleResolverService`, `LeaderPermissionService`, and Flutter `UserRole`; no `tasks` / committee work-item table exists anywhere in the backend. |

---

## 15. Role 14 — Leadership Team (`circle_leader`)

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Umbrella RBAC grouping role representing generic Circle Leadership Team membership in Dynamic RBAC and Web Admin navigation. |
| 2 | **Existing role identifier(s)** | **FACT:** `roles.key`: `circle_leader` (`CL`, L4, [RoleHierarchySeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/RoleHierarchySeeder.php#L51)); React Admin `circle_leader` (aliases `leadership_team`, `circleLeader`, UUIDs `05b1fcec-...`, `309f1015-...`, [roleAccess.ts](file:///D:/office%20project/unity-admin-web/src/utils/roleAccess.ts#L151-L167)). |
| 3 | **Organizational level** | Level 4 — Generic Circle Leadership Grouping (`leadership`). |
| 4 | **Reporting hierarchy** | Reports to Circle Director / DED. |
| 5 | **Assigned geographic scope** | `Own Circle`. |
| 6 | **Industry scope** | Own Circle. |
| 7 | **Circle scope** | Assigned Circle(s) via `admin_user_roles` / `AdminCircleScope`. |
| 8 | **Direct reports** | None (umbrella classification). |
| 9 | **Data visibility** | Scoped Circle modules in Web Admin (`role_data_scope.scope_type = 'circle'`). |
| 10 | **Available actions** | Module-driven Web Admin actions configured on `circle_leader` in Dynamic RBAC. |
| 11 | **Restricted actions** | Denied global/regional scope, finance configuration, and RBAC management. |
| 12 | **Approval authority** | None in `CircleJoinRequestService` or `PendingRequestAdminController`. |
| 13 | **Escalation authority** | None. |
| 14 | **Reporting access** | Scoped Circle referral/activity reports in Web Admin. |
| 15 | **Notification eligibility** | Web Admin role notifications only. |
| 16 | **Existing backend enforcement** | **FACT:** Checked in `RoleLifespanController.php:152` and `AdminCircleScope`, unmapped in `UserRoleResolverService`. |
| 17 | **Missing/broken functionality** | `Implemented but Not Integrated`: Exists in `roles` table and React Admin `roleAccess.ts`, but if assigned to a user instead of `circle_director`/`chair`/`vice_chair`/`secretary`, `UserRoleResolverService` treats them as non-leader (`member`) in the Flutter Leadership App. |

---

## 16. Role 15 — Functional Admin Teams (`marketing_team`, `analytics_team`, `content_team`, `read_only`)

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Internal Peers Global HQ staff roles operating specific functional modules in the React/Blade Admin Panel (campaigns/ads/brand partners, analytics/reports, tutorials/stories/posts moderation, or read-only staff inspection). |
| 2 | **Existing role identifier(s)** | **FACT:** `roles.key` (L9, `role_type = 'functional'`, [RoleHierarchySeeder.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/database/seeders/RoleHierarchySeeder.php#L60-L65)): `marketing_team` (`MKT`), `analytics_team` (`ANA`), `content_team` (`CNT`), `read_only` (`RO`); React Admin `ROLE_GROUPS` ([roleAccess.ts](file:///D:/office%20project/unity-admin-web/src/utils/roleAccess.ts#L236-L288)). |
| 3 | **Organizational level** | Level 9 — HQ Functional Operations (`functional`, non-hierarchical). |
| 4 | **Reporting hierarchy** | Reports to Super Admin / Operations Head. |
| 5 | **Assigned geographic scope** | Configured per `role_data_scope` (typically `global` read/module-scoped in Web Admin). |
| 6 | **Industry scope** | Platform-wide for permitted functional modules only. |
| 7 | **Circle scope** | Non-circle-governing (module-specific). |
| 8 | **Direct reports** | None. |
| 9 | **Data visibility** | Strictly confined to enabled `admin_modules` in `role_module_access` & `role_page_permissions` (e.g., `marketing_team` -> Ads/Brand Partners/Campaigns; `analytics_team` -> Reports/Analytics; `content_team` -> Timeline/Stories/Tutorials; `read_only` -> view-only across permitted pages). |
| 10 | **Available actions** | Web Admin CRUD on permitted pages (`PermissionService::can()`), **except** `read_only` which is blocked from mutations by `AdminAccess::isEditAllowed()` ([AdminAccess.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Support/AdminAccess.php#L565-L595)). |
| 11 | **Restricted actions** | **Denied** Flutter Leadership App login (`EnsureLeaderUser` rejects `AdminUser` tokens and `ensureLeaderUserFromAdminAccount` excludes functional roles), RBAC mutation, Wallet adjustments, and Circle leadership governance. |
| 12 | **Approval authority** | Functional content moderation only where granted (e.g., `content_team` on stories/posts; `marketing_team` on advertiser assets); **no** Circle Join or Leadership Election authority. |
| 13 | **Escalation authority** | Escalates operational tickets/content flags to Super Admin. |
| 14 | **Reporting access** | `analytics_team` & `read_only` view permitted Web Admin reports; no Leader App mobile report submission. |
| 15 | **Notification eligibility** | Admin panel operational alerts only. |
| 16 | **Existing backend enforcement** | **FACT:** Enforced in Web Admin via `PermissionService` & `AdminAccess::isEditAllowed()`. |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect`: (1) `LeaderPermissionService::resolveUserRole()` ([L289-L298](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L289-L298)) returns `'superAdmin'` if passed **any** `AdminUser` instance; (2) `PermissionService::can()` fails **open (`return true`)** on database exceptions ([PermissionService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Admin/PermissionService.php#L289)). |

---

## 17. Role 16 — Ambassador (Growth Milestone Honour vs Custom DB Alias)

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Cultural trust-carrier and community evangelist who introduces relevant entrepreneurs to Peers Global (*"You carry the name. You carry the trust. This is not a sales role"*). Primarily an earned **10-Peer-Introductions Growth Milestone Honour**, with an unintegrated custom DB role alias. |
| 2 | **Existing role identifier(s)** | **FACT:** Milestone Honour `"Ambassador"` (`required_count = 10` in `ContributionMilestoneResolver.php:30` & `Track1GrowthHonoursSeeder.php:39`); custom DB role typo alias `'ambassador' => 'ambassader'` in [RolePermissionMatrixController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Admin/Rbac/RolePermissionMatrixController.php#L76-L77) and `backendKey: 'ambessader'` (`UUID: e09ddd1c-8cf6-43e0-9de6-cfed96e43237`) in [roleAccess.ts](file:///D:/office%20project/unity-admin-web/src/utils/roleAccess.ts#L290-L299). |
| 3 | **Organizational level** | Non-hierarchical Recognition Honour / Cultural Outreach (outside the 6-stage leadership ladder). |
| 4 | **Reporting hierarchy** | Self-directed Peer contributor; coordinates outreach with Circle Directors / Membership Chairs. |
| 5 | **Assigned geographic scope** | `Not Applicable` (Personal outreach; no territory ownership). |
| 6 | **Industry scope** | `Not Applicable`. |
| 7 | **Circle scope** | Member's own joined Circle(s) (`Own` / `Self` scope). |
| 8 | **Direct reports** | None. |
| 9 | **Data visibility** | Own Introduced Peers (`/api/v1/introduced-peers`), own Referral Links, own Visitor Registrations, and public Peer directory in the **Unity Member App**. |
| 10 | **Available actions** | Share referral links, submit Recommend a Peer, Register a Visitor, claim introducer links, and earn Milestone Badges in the Member App. |
| 11 | **Restricted actions** | **Denied** Flutter Leadership App login (`is_leader: false` unless holding a separate Circle/Regional leadership role), Circle management, Finance, Approvals, and Peer PII exports. |
| 12 | **Approval authority** | **None (`Denied`).** |
| 13 | **Escalation authority** | None. |
| 14 | **Reporting access** | Personal introduction & referral stats in Member App (`GET /api/v1/referrals/stats`). |
| 15 | **Notification eligibility** | WhatsApp/Push Milestone Award recognition (`AwardRecognitionWhatsappService` -> `member_award_sends`) and referral conversion alerts. |
| 16 | **Existing backend enforcement** | **FACT:** Badge/honour awarded via `MilestoneBadgeService` & `ContributionMilestoneResolver`; blocked from Leader App by `EnsureLeaderUser`. |
| 17 | **Missing/broken functionality** | `Requires Business Confirmation`: Typo `'ambassader'` / `'ambessader'` hardcoded in `RolePermissionMatrixController.php` and `roleAccess.ts` with no backend scope or capability definition. Keep Ambassador as a Recognition Honour unless Product Owner explicitly defines an outreach workflow. |

---

## 18. Role 17 — Peer Member

| # | Attribute | Specification & Verified Code Evidence |
|---:|---|---|
| 1 | **Role definition** | Verified entrepreneur participant in the Peers Global ecosystem (`free_peer`, `free_trial_peer`, `Only Unity Peer`/`global_peer`, `circle_peer`, `multi_circle_peer`, `charter_peer`) using the **Flutter Unity Member App** for P2P networking, referrals, deals, asks, events, and learning. |
| 2 | **Existing role identifier(s)** | **FACT:** `member` (`is_leader: false` in [UserRoleResolverService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Shared/Services/UserRoleResolverService.php#L376-L381)); `roles.key`: `member` (`MBR`, L10, `own` scope); PG enum `'member'` (`CircleMember::ROLE_MEMBER`); React Admin `member` (`f195134d-...`, `95645284-...`, `14f5c8cc-...`). |
| 3 | **Organizational level** | Level 10 — Foundation Peer Participant (Stage 001 *"Begin With Contribution"*). |
| 4 | **Reporting hierarchy** | Participates in assigned Circle(s) facilitated by Circle Director and Committee Chairs. |
| 5 | **Assigned geographic scope** | `Self / Own` (plus visibility of public/circle feeds permitted by membership tier). |
| 6 | **Industry scope** | Holds one exclusive Level-4 category seat per joined Circle (`circle_member_category_selections`). |
| 7 | **Circle scope** | Joined Circle(s) where `circle_members.status = 'approved'` and `left_at IS NULL`. |
| 8 | **Direct reports** | None. |
| 9 | **Data visibility** | Own profile, own wallet/coins/invoices, own `"Who Viewed My Profile"` (`profile_views`), own `peer_blocks`, Timeline feed, Ask feed, Circle directory, and Events in the **Member App**. |
| 10 | **Available actions** | Create P2P requests/logs, Referrals, Business Deals, Testimonials, Collaboration/Help/Referral Asks, Event RSVPs/QR check-ins, Certifications, Visitor Registrations, Peer Recommendations, Build Circle proposals, and Leader Interest applications (`POST /api/v1/forms/leader-interest`). |
| 11 | **Restricted actions** | **Strictly Denied** access to the Flutter Leadership App (`EnsureLeaderUser` returns `403`), React Admin Portal, other Peers' private financial/moderation records, and all approval queues. |
| 12 | **Approval authority** | **Self/Bilateral Confirmation Only:** Confirms incoming P2P meeting requests/reschedules, responds to Asks, and confirms received contributions (per the receiver-confirmation rule). Zero administrative approval authority. |
| 13 | **Escalation authority** | Submits Help & Support Tickets (`POST /api/v1/support/tickets`) and Post Reports (`POST /api/v1/posts/{post}/report`). |
| 14 | **Reporting access** | Own Last Month Activity Summary (`GET /api/v1/profile/last-month-activity`) and Monthly Impact Script. |
| 15 | **Notification eligibility** | Consumer Member App notifications (`app_notifications`), WhatsApp 30-day onboarding journey, habit-loop nudges, and membership expiry reminders. |
| 16 | **Existing backend enforcement** | **FACT:** Enforced on `/api/v1/leader/*` by `EnsureLeaderUser` (`403`), **CRITICAL FAILURE** on `/api/v1/admin/*`, `/api/v1/rbac/*`, and `/api/v1/admin/access/*`! |
| 17 | **Missing/broken functionality** | `Implemented but Incorrect` (**CRITICAL P0 Security Bugs**): Because `/api/v1/admin/*` ([routes/api.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/routes/api.php#L701-L1305)) only checks `['auth:sanctum', 'unity.user']`, `/api/v1/admin/access/*` ([L2319](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/routes/api.php#L2319)) checks `auth:sanctum,admin`, `EnsureAdminAuthenticated` auto-creates `AdminUser` rows ([L39-L47](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Middleware/EnsureAdminAuthenticated.php#L39-L47)), and `/api/v1/rbac/*` is completely unauthenticated, a regular `member` Bearer token can currently invoke admin APIs or self-assign `global_admin`! |
