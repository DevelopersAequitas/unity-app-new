# 10 — Role-Wise Mobile Screen Access Specification (Shared Flutter Leadership App)

> **Audit Scope:** Role-by-role mobile UX, navigation, actions, restrictions, and scope specification inside the single shared permission-driven Flutter Leadership App (`com.unity.leadersapp`, `D:\office project\LeaderApp-Fornt-end-`)  
> **Overall Status:** `Partially Implemented` (Single permission-gated app shell exists with 5 bottom tabs and 28 routes; lacks `viceChair`/`secretary`/`committeeLeader` enum values, assignment switcher, and role-tailored home prioritization)  
> **Related Deliverables:** [06-complete-role-hierarchy.md](./06-complete-role-hierarchy.md) · [07-role-wise-responsibilities.md](./07-role-wise-responsibilities.md) · [08-master-role-permission-matrix.md](./08-master-role-permission-matrix.md) · [09-organizational-data-scope-matrix.md](./09-organizational-data-scope-matrix.md) · [11-approval-and-delegation-authority.md](./11-approval-and-delegation-authority.md)

---

## 1. Shared Permission-Driven Architecture Principles

**FACT:** All leadership roles share **one Flutter application** (`com.unity.leadersapp`, MVP + BLoC architecture) rooted at [dashboard_view.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/dashboard/view/dashboard_view.dart) and [app_routes.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/routes/app_routes.dart). Navigation tabs and action buttons adapt dynamically from `SessionManager().permissions` ([session_manager.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/helpers/session_manager.dart#L130-L131)) and [dashboard_bottom_nav_bar.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/dashboard/view/widgets/dashboard_bottom_nav_bar.dart#L41-L92).

> [!IMPORTANT]
> **Mandatory UX & Brand Rules Across All Roles:**
> 1. **Hide, Never Merely Disable:** Actions, tabs, and destructive controls (e.g., *Record Offline Payment*, *Configure Commission Rates*, *Role Matrix*, *Delete Post/Creative*) that a role cannot perform must be **completely omitted from the widget tree**, never shown disabled.
> 2. **Strict Vocabulary Constraint:** User-facing strings must use **Peer / Peers / Leaders / Circle Participants / Circle Ecosystem / Collaboration** and never prohibited terms (`member`/`members`, `network` — [README.md](file:///D:/office%20project/LeaderApp-Fornt-end-/README.md#L234-L239)).
> 3. **Truthful Revenue & Verified Impact:** Never fall back from `overall_revenue` to `business_deals` (`dashboard_hero_card.dart:60-67`); display `—` (`Unavailable`) when authoritative ledger/payment data is absent, and distinguish *Declared* vs *Receiver-Confirmed* impact.

### Master Screen × Role Visibility Overview

| Screen / Surface (`AppRoutes`) | Super Admin | Country Dir. | DED | Industry Dir. | Circle Founder | Circle Director | Chairs (×4) | Vice Chair | Secretary | Committee Leader |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Bottom Tab 0: Dashboard** (`/home`) | Visible | Visible | Visible | Visible | Visible | Visible | Visible | Visible | Visible | Target: Visible (Now `403`) |
| **Bottom Tab 1: Peers** (`/peers`) | Visible | Visible | Visible | Visible | Visible | Visible | Visible | Visible | Visible | Target: Visible (Read-only) |
| **Bottom Tab 2: Teams / Circles** (`/teams`) | Visible | Visible | Visible | Visible | Visible | Visible | Visible | **Hidden** | **Hidden** | **Hidden** |
| **Bottom Tab 3: Finance** (`/finance`) | Visible | Visible | Visible | Visible | Visible | Visible | **Hidden** | **Hidden** | **Hidden** | **Hidden** |
| **Bottom Tab 4: Report** (`/reports`) | Visible | Visible | Visible | Visible | Visible | Visible | Visible | Visible (Read-only) | Target: Submit / Read | **Hidden** |
| **Pending Approvals Queue** | Full (10 cats) | Country (Stage 1/2) | District (Stage 2 + 5 cats) | Industry (Stage 2) | Circle (Stage 1 + Circle cats) | Circle (Stage 1 + Circle cats) | **Hidden** (Fix UI bug!) | **Hidden** | **Hidden** | **Hidden** |
| **Finance: Record Offline Payment** | Visible | Visible | Visible | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** |
| **Finance: Commission Setup** | Visible | **Hidden** (Fix flag!) | **Hidden** (Fix flag!) | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** |
| **Profile: Role & Permission Matrix** | Visible | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** | **Hidden** |

---

## 2. Role 1 — Super Admin (`superAdmin` / `global_founder`)

- **A. Dashboard:**
  - **FACT (Current):** Displays `Super Admin` badge + `"Global Scope"` pill ([dashboard_view.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/dashboard/view/dashboard_view.dart#L173-L337)), `DashboardActionCenterCard`, `DashboardHeroCard` (Revenue, Lives Impacted, Business Deals, Peers, Circles), `DashboardPerformanceSection`, 6 `DashboardQuickActions` (*Review Applications*, *View Peers*, *Manage Circles*, *Role Matrix*, *View Reports*, *Official Circulars*), `DashboardUpcomingEventsSection`, `DashboardRecentActivitySection`, and `DashboardTopImpacters`.
  - **RECOMMENDATION (Target):** Surface global exception/escalation KPIs first, show deduplicated global Peer count and multi-currency authoritative revenue breakdown, and remove synthetic fallbacks (`activeTenures ?? 42`, `overall_revenue -> deals`).
- **B. Navigation:**
  - **Bottom Nav:** All 5 tabs (*Dashboard*, *Peers*, *Teams*, *Finance*, *Report*).
  - **Tabs & Detail Screens:** All Peer 360° tabs (*Overview*, *Activity*, *Collaborations*, *Need Help*, *Referrals*, *Events*, *Testimonials*, *Coins*, *Introduced*), Circle Details (*Category Availability*, *Circle Peers*, *Sub-Industries*, *Circle Events*, *Circle Overview*), Report Details, Notifications, Official Circulars, and **Role & Permission Matrix** (`/role-management`).
  - **Bottom Sheets / Forms:** Circle Switcher pill, Record Offline Payment sheet, Configure Commission Rates sheet, Submit Report form, Create/Edit Custom Role sheet, Edit Profile & Avatar sheet.
- **C. Actions:** Approve/reject all 10 pending request categories, configure role commission cuts, record offline payments, toggle 13 capabilities per role, create/delete custom roles, publish circulars (once routed), delete violating circle posts/creatives (with confirmation dialog), export CSV/PDF.
- **D. Restrictions:** Personal Peer privacy logs (`peer_blocks`, `profile_views`, unpublished Ask drafts) remain **omitted** per the Super Admin Privacy Boundary; own Maker-Checker wallet adjustments cannot be self-approved.
- **E. Data Scope:** `Global Scope` with Circle/Country/District/Industry drilldown filter pill; Profile groups assignments by role rather than listing 44 unlabeled rows.

---

## 3. Role 2 — Country Director / EED (`countryDirector` / `eed`)

- **A. Dashboard:**
  - **FACT (Current):** Identical layout to Super Admin except *Role Matrix* quick action is hidden (`can_access_role_management: false`). Currently displays unfiltered global numbers due to backend `LeaderDashboardService.php:67` bug.
  - **RECOMMENDATION (Target):** Country Command Center showing `Global › <Assigned Country>` scope breadcrumb, DED territory coverage cards, Industry vertical health across the country, national deduplicated Peer count, and country-level pending approvals/escalations.
- **B. Navigation:**
  - **Bottom Nav:** All 5 tabs (*Dashboard*, *Peers*, *Teams*, *Finance*, *Report*).
  - **Detail Screens & Sheets:** Country-scoped Peers, Celebrations, Teams/Circles, Circle Details, Finance ledger + *Record Offline Payment* sheet, Reports (*Submit* + *History & Analytics*), Notifications, Profile, Official Circulars.
- **C. Actions:** Moderate country-scoped pending requests, edit peer records (`canAddEditPeer: true`), record verified offline payments (`canModifyFinanceSettings: true`), issue approved coin payouts (`canIssueCoins: true`), submit/download reports, export country peer & financial data.
- **D. Restrictions:** **Hide** *Role & Permission Matrix* (`/role-management`), **hide** *Configure Commission Rates* sheet (currently exposed in UI because `canModifyFinanceSettings` conflates offline payments with Super-Admin-only commission rates!), and block cross-country circles.
- **E. Data Scope:** Strictly bound to assigned country (`country_id` / `scope_value`), with District -> Industry -> Circle filter hierarchy.

---

## 4. Role 3 — District Executive Director (`districtExecDirector` / `ded`)

- **A. Dashboard:**
  - **FACT (Current):** Shows `District Exec Director` badge and `"District Scope"`, Action Required card (district pending requests), Hero KPIs (with revenue visible via `canViewOverallRevenue: true`), Quick Actions (*Review Applications*, *View Peers*, *Manage Circles*, *View Reports*, *Official Circulars*), Upcoming Conclaves, and Top Impacters.
  - **RECOMMENDATION (Target):** Territory Builder Command Center displaying `Country › State › <Assigned District(s)>` breadcrumb, Circle Health matrix (Active vs At-Risk circles, vacant CD/Chair seats), Stage-2 Join Request & 5-category DED moderation queue at top, and DED tenure bonus progress.
- **B. Navigation:**
  - **Bottom Nav:** All 5 tabs (*Dashboard*, *Peers*, *Teams*, *Finance*, *Report*).
  - **Detail Screens & Sheets:** District Circle Switcher (`All District Circles` + individual circles), Peers & Celebrations, Teams (18 Industries filter + Active/At-Risk status chips), Circle Details, Finance (*KPIs*, *Transactions*, *Milestones*, *Record Offline Payment* sheet), Reports (*Submit* + *History*), Notifications, Profile, Circulars.
- **C. Actions:** Approve/reject Stage-2 Circle Join Requests (`pending_id_approval` & `ded_approval_status`) and district `visitor_registrations`, `event_joining_requests`, `coin_claims`, and `pending_impacts`; add/edit peer status & coaching notes; nominate open-category candidates; record offline payments (`with circle_id`); export district peer & financial data.
- **D. Restrictions:** **Hide** *Role Matrix*, **hide** *Configure Commission Rates* sheet (Super Admin only), **hide** Coin Payout issuance (`canIssueCoins: false`), and block access to circles outside assigned `admin_ded_districts`.
- **E. Data Scope:** Assigned District(s) & constituent Cities (`All Circles` defaults to deduplicated Peers across the DED's assigned districts; multi-district DEDs get a District + Circle switcher).

---

## 5. Role 4 — Industry Director (`industryDirector`)

- **A. Dashboard:**
  - **FACT (Current):** Displays `Industry Director` + `"Industry Scope"`, Hero KPIs (Revenue, Deals, Impact, Peers, Circles), Industry/Circle performance breakdown, Quick Actions (*Review Applications* via `role.canModeratePendingRequests`, *View Peers*, *Manage Circles*, *View Reports*, *Official Circulars*).
  - **RECOMMENDATION (Target):** Sector Owner Command Center displaying assigned Level-1 Industry vertical pill(s), Stage-2 Circle Join Requests awaiting category clearance (including pre-launch categories without an active circle), cross-circle collaboration metrics, and `ID_CIRCLE_LAUNCH_*` / `ID_MEMBER_MILESTONE_*` bonus trackers.
- **B. Navigation:**
  - **Bottom Nav:** All 5 tabs (*Dashboard*, *Peers*, *Teams*, *Finance*, *Report*).
  - **Detail Screens & Sheets:** Industry Circle Switcher, Peers (read-only profiles + Celebrations), Teams & Circle Details (with deep *Sub-Industries / Open Categories* inspection), Finance (*Read-only* KPIs, Revenue Trend, Milestones — **zero** write sheets), Reports (*Submit* + *History*), Notifications, Profile, Circulars.
- **C. Actions:** Approve/reject Stage-2 Circle Join Requests (`pending_id_approval -> pending_circle_fee`), refer entrepreneurs into open Level-4 subcategories (`POST /teams/circles/{id}/refer-category`), send wishes, log P2P/Referrals, submit sector reports, export industry peer CSV (`canExportPeerData: true`).
- **D. Restrictions:** **Hide** Peer Edit sheet, **hide** Peer Post/Creative trash/delete icons (`canAddEditPeer: false`), **hide** *Record Offline Payment* and *Commission Setup* sheets (`canModifyFinanceSettings: false`), **hide** Financial Export, and **hide** *Role Matrix*. Mask Peer phone/email when `hide_phone`/`hide_email` is set.
- **E. Data Scope:** Assigned Level-1 Industry Categories cascading across L2–L4 subcategories and mapped Circles (`IndustryDirectorScopeService`).

---

## 6. Role 5 — Circle Founder (`circleFounder`)

- **A. Dashboard:**
  - **FACT (Current):** Displays `Circle Founder` + `"Own Circle"`, Action Required card, Hero KPIs (with Circle Revenue & Deals), Quick Actions (*Review Applications*, *View Peers*, *Manage Circles*, *View Reports*, *Official Circulars*), Upcoming Conclaves, Top Impacters.
  - **RECOMMENDATION (Target):** Circle Formation & Charter Dashboard highlighting founding Peer strength vs charter threshold (e.g., 25 / 30 / 50 Peers), Open vs Occupied Category Seats (`171 Open · 1 Occupied`), Stage-1 Join Request queue, `CF_SPEED_*` / `CF_QUALITY_ATTENDANCE_90_FIRST6` / `CF_CULTURE_LEGACY_6M` milestone trackers, and CF-to-CD handover status.
- **B. Navigation:**
  - **Bottom Nav:** All 5 tabs (*Dashboard*, *Peers*, *Teams*, *Finance*, *Report*).
  - **Detail Screens & Sheets:** Managed Circle Switcher (if founder of >1 circle), Peers + Member 360° + Celebrations, Teams + Circle Details (*Open Categories* + *Refer Category* tab + *Events*), Finance (*Read-only* Circle Revenue, Collections, Commission Due & Tenure Milestones), Reports (*Submit* + *History*), Notifications, Profile, Circulars.
- **C. Actions:** Stage-1 Circle Join approval/rejection (`pending_cd_approval -> pending_id_approval`), scoped pending request moderation (`pending_impacts`, `visitor_registrations`, `event_joining_requests`, `circle_peer_referrals`), nominate founding Peers to open categories, flag Peer status / add notes / send reminders, delete inappropriate circle posts/creatives (with confirmation), submit weekly reports, export Circle Peer CSV.
- **D. Restrictions:** **Hide** *Record Offline Payment* & *Commission Setup* sheets (`canModifyFinanceSettings: false`), **hide** Coin Payouts, **hide** *Role Matrix*, and **hide** Regional/Global exports. Fix Circle Details so Circle Founder appears under *Circle Leadership*, not *Regional Leaders*.
- **E. Data Scope:** Strictly `Own Circle` (assigned Circle(s) where user is `founder_user_id` / `circle_founder_user_id` or approved `founder` in `circle_members`).

---

## 7. Role 6 — Circle Director (`circleDirector`)

- **A. Dashboard:**
  - **FACT (Current):** Identical widget set and 8 capabilities to Circle Founder (`access_dashboard`, `access_teams`, `access_finance`, `track_milestones`, `view_peers`, `manage_peers`, `request_actions`, `view_reports`).
  - **RECOMMENDATION (Target):** Monthly Circle Operations Command Center prioritizing: (1) Stage-1 Join Requests & Pending Impact/Visitor approvals, (2) Next Circle Conclave countdown & RSVP/Attendance rate (`90%+` target), (3) At-Risk Peers needing 1-on-1 check-in, (4) Committee Chair & Powerhouse activity summary, and (5) `CD_SPEED_*` / `CD_STRENGTH_*` milestone progress.
- **B. Navigation:**
  - **Bottom Nav:** All 5 tabs (*Dashboard*, *Peers*, *Teams*, *Finance*, *Report*).
  - **Detail Screens & Sheets:** Own-Circle Switcher (if multi-circle CD), Peers + Peer 360° (all tabs + coaching note/reminder/flag sheets), Teams + Circle Details + Circle Events + Sub-Industries, Finance (*Read-only* Circle collections, commission summary & CD tenure bonuses), Reports (*Submit Report* form with preloaded Circle Peer roster + *History & Analytics* + *Report Details*), Notifications, Profile, Circulars.
- **C. Actions:** Approve/reject Stage-1 Circle Join Requests (`approveByCd` / `rejectByCd`) and circle pending requests, flag Peer status (`approved`, `needs_attention`, `under_review`), add coaching notes, send reminders & birthday/anniversary wishes, moderate circle Peer posts/creatives, refer open-category Peers, submit & download weekly reports, export Circle Peer data, and (recommended once routed) assign/rotate Circle Chairs & Officers.
- **D. Restrictions:** **Hide** *Record Offline Payment*, **hide** *Configure Commission Rates*, **hide** Coin Payouts, **hide** *Role Matrix*, and **disallow** Stage-2 ID/DED self-clearance on Circle Join Requests.
- **E. Data Scope:** Strictly `Own Circle` (approved `director` / `circle_director_user_id` circles only — never circles where the CD is merely a regular `member`).

---

## 8. Role 7 — Chair (Generic `circleChair` & 3 Specialized Committee Chairs: `chairBusinessGrowth`, `chairMembership`, `chairEventsPrograms`)

- **A. Dashboard:**
  - **FACT (Current):** Hero card swaps *Total Revenue* to *Total Coins* because `canViewOverallRevenue` is `false` ([LEADER_APP_SCREEN_BY_SCREEN_AUDIT.md](file:///D:/office%20project/LeaderApp-Fornt-end-/documents/audit/LEADER_APP_SCREEN_BY_SCREEN_AUDIT.md#L58)). Quick Actions currently show *View Peers*, *Manage Circles*, *View Reports*, *Official Circulars*, **plus a broken *Review Applications* button** (shown because backend sets `can_review_pending_peers = true` via `access_teams`, even though `PendingRequestAdminController` returns `403`!).
  - **RECOMMENDATION (Target):** Role-tailored Powerhouse Chair Home:
    - **Business Growth Chair (`chairBusinessGrowth`):** Open Asks needing matches, P2P 1-on-1 completion rate, Referrals in progress, Declared vs Confirmed Business Deals.
    - **Membership Growth Chair (`chairMembership`):** Visitor Registrations to follow up, Open Category seats to fill, New Peer 30-day onboarding checklist, Upcoming Celebrations.
    - **Events & Impacts Chair (`chairEventsPrograms` / `chairEvents`):** Next Conclave RSVP list, Guest Visitors, Post-Event Feedback score, Circle Life Impact stories.
    - **Generic Circle Chair (`circleChair`):** Combined Circle meeting, attendance, and Peer collaboration summary (with distinct `'Circle Chair'` label fixed in `UserRoleResolverService`).
- **B. Navigation:**
  - **Bottom Nav:** **4 tabs only** — *Dashboard* (0), *Peers* (1), *Teams* (2), *Report* (4). **Tab 3 (*Finance*) is hidden** (`canAccessFinanceTab: false`, [dashboard_bottom_nav_bar.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/dashboard/view/widgets/dashboard_bottom_nav_bar.dart#L72-L81)).
  - **Detail Screens & Sheets:** Peers list & Celebrations, read-only Peer 360° profile, Teams (Own Circle card -> Circle Details -> Circle Peers, Sub-Industries, Circle Events), Activity drilldown screens (*P2P Meetings*, *Referrals*, *Business Deals*, *Testimonials*, *Life Impacts*, *Requirements/Asks*), Reports (*Submit* + *History*), Notifications, Profile, Circulars.
- **C. Actions:** Log/facilitate P2P meetings & referrals, nominate open-category candidates (`refer-category`), send celebration wishes & peer reminders, and submit Circle/Committee reports (`canSubmitReports: true`).
- **D. Restrictions:** **Completely hide** *Finance* tab and all revenue/commission KPIs, **hide** *Review Applications / Pending Approvals* queue (fixing `can_review_pending_peers` bug), **hide** Peer Edit (`canAddEditPeer: false` — fixing `chairMembership` override in `session_manager.dart:341`), **hide** Peer Post/Creative delete trash icons, **hide** CSV/Financial exports, **hide** *Role Matrix*, and mask `hide_phone`/`hide_email` contacts.
- **E. Data Scope:** Strictly `Own Circle` (requiring `LeaderPermissionService::resolveManagedCircles()` fix to include `chair_user_id`, all 3 `*_committee_chair_user_id` columns, and `calendar->leadership`).

---

## 9. Role 8 — Vice Chair (`viceChair`)

- **A. Dashboard:**
  - **FACT (Current — Buggy):** Because `viceChair` is missing from `UserRole` ([user_role.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/enums/user_role.dart)), `UserSession.fromJson` labels them `Circle Chair`. When server `permissions` JSON is loaded (`access_dashboard`, `view_peers`, `request_actions`, `view_reports`), *Finance* and *Teams* tabs are hidden; if offline fallback runs, `UserRole.circleChair` wrongly exposes *Teams* and *Submit Report*!
  - **RECOMMENDATION (Target):** Display accurate `Vice Chair` role badge + Own Circle pill, upcoming Conclave readiness card, Peer engagement & celebration reminders, and Circle collaboration KPIs (Coins/Impact/P2P/Referrals — zero revenue figures).
- **B. Navigation:**
  - **Bottom Nav:** **3 tabs only** — *Dashboard* (0), *Peers* (1), *Report* (4). **Both *Teams* (Tab 2) and *Finance* (Tab 3) are hidden** (`canAccessTeamsTab: false`, `canAccessFinanceTab: false`). *(Note: Tapping Circle Events from Dashboard opens `/circle-events` directly via `_openTabOrFallback`.)*
  - **Detail Screens & Sheets:** Peers directory & Celebrations, read-only Peer 360° profile, Activity stream screens, Reports (**History & Analytics read-only view** — *Submit Report* tab hidden because `canSubmitReports: false`), Notifications, Profile, Official Circulars.
- **C. Actions:** View Own-Circle Peers, send birthday/anniversary wishes, log P2P meetings & referrals, view Circle weekly reports and circulars. *(Conditional: if formally delegated acting-Chair/CD authority for a date window, temporarily unlock delegated capabilities.)*
- **D. Restrictions:** **Hide** *Teams* tab, **hide** *Finance* tab & all revenue metrics, **hide** *Submit Report* form, **hide** *Pending Approvals*, **hide** Peer Edit & Post/Creative delete icons, **hide** all CSV/PDF bulk exports, **hide** *Role Matrix*, and respect Peer `hide_phone`/`hide_email` flags.
- **E. Data Scope:** Strictly `Own Circle` (adding `vice_chair_user_id` & `calendar.leadership.vice_chair_user_id` to `resolveManagedCircles()`).

---

## 10. Role 9 — Secretary (`secretary`)

- **A. Dashboard:**
  - **FACT (Current — Buggy):** Same missing `UserRole.secretary` enum coercion to `UserRole.circleChair` as Vice Chair; backend grants 4 capabilities (`access_dashboard`, `view_peers`, `request_actions`, `view_reports`).
  - **RECOMMENDATION (Target):** Display accurate `Secretary` role badge + Own Circle pill, Next Conclave Attendance & Substitute Tracker card, Meeting Report filing status, Upcoming Celebrations, and Official Circulars.
- **B. Navigation:**
  - **Bottom Nav:** **3 tabs** — *Dashboard* (0), *Peers* (1), *Report* (4) (*Teams* Tab 2 and *Finance* Tab 3 hidden).
  - **Detail Screens & Sheets:** Own-Circle Peers & Celebrations, read-only Peer 360° attendance & activity tabs, Circle Events & Attendance roster, Reports (*History & Analytics*, plus *Submit Report* form if confirmed by Product Owner), Notifications, Profile, Official Circulars.
- **C. Actions:** View Own-Circle Peer roster & attendance history, send birthday/anniversary wishes, log P2P/Referrals, read Official Circulars, and (**RECOMMENDATION / `BUSINESS DECISION REQUIRED`**) draft or submit Circle meeting attendance reports (`can_submit_reports`) for Circle Director sign-off.
- **D. Restrictions:** **Hide** *Teams* management tab, **hide** *Finance* tab & revenue KPIs, **hide** *Pending Approvals*, **hide** Peer Edit & Post/Creative deletion, **hide** CSV/Finance exports, and **hide** *Role Matrix* & Admin Notification Console (must also be blocked by `leader.can` on backend!).
- **E. Data Scope:** Strictly `Own Circle` (adding `secretary_user_id` & `calendar.leadership.secretary_user_id` to `resolveManagedCircles()`).

---

## 11. Role 10 — Committee Leader & Additional Ecosystem Roles

### 11.1 Committee Leader (`committee_leader`)
- **FACT (Current):** Rejected at login/session verification by `EnsureLeaderUser` (`403: "Access denied. You do not hold an active Unity Leadership role."`) because `committee_leader` is missing from `UserRoleResolverService`. Currently only accesses Circle Leadership Group Chat inside the consumer **Unity Member App** (`/api/v1/circles/my-leadership-circles`).
- **RECOMMENDATION (Target):**
  - **A. Dashboard:** Lightweight Powerhouse Contributor Home showing `Committee Leader · <Committee Name>` badge, assigned Circle Conclave schedule, personal outreach/hospitality commitments, and Circle collaboration highlights (zero financial metrics).
  - **B. Navigation:** **2–3 tabs** — *Dashboard* (0) and *Peers* (1) (plus read-only Circulars/Notifications; *Teams*, *Finance*, and *Report* tabs hidden).
  - **C. Actions:** View Own-Circle Peers (respecting privacy flags), send celebration wishes, log P2P meetings & referrals, participate in committee coordination.
  - **D. Restrictions:** **Hide** *Teams*, *Finance*, *Reports*, *Pending Approvals*, Peer Edit, Post Delete, Exports, and *Role Matrix*.
  - **E. Data Scope:** Strictly `Own Circle` + assigned `committee_key`.

### 11.2 Additional Non-Mobile-Executive Roles (`circle_leader`, Functional Admin Teams, `Ambassador`, `Peer Member`)
- **`circle_leader` (*Leadership Team*):** Umbrella Web Admin RBAC role (`Implemented but Not Integrated` in mobile). Should map to concrete assigned Circle officer role (`CD`, `Chair`, `VC`, `SEC`, or `CML`) rather than a separate mobile experience.
- **Functional Admin Teams (`marketing_team`, `analytics_team`, `content_team`, `read_only`):** Web Admin HQ staff (`unity-admin-web`). **Restricted (`403`)** from the Flutter Leadership App (`Not Required` on mobile).
- **Ambassador (`Ambassador` Milestone Honour / `ambassader`):** Earned 10-introductions recognition honour displayed on Peer/Leader profile badges in the **Unity Member App** (`Requires Business Confirmation` before any dedicated Leader App screen is considered).
- **Peer Member (`member`):** Uses the **Flutter Unity Member App** (`peers-unity`). Strictly blocked (`403`) from logging into the Flutter Leadership App.
