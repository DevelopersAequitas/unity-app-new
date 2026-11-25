# 34 — Peers Global Leadership Notification System: Development Server Handoff

**Purpose:** Exact instructions for pushing and verifying the Leadership App notification system on the **Development Server**.  
**Rule:** Do **NOT** run development/CI commands against Production. No secrets are included in this document.

---

## 1. Exact Backend Changed-File List (Notification Scope)

### Created Files
- `app/Leader/Services/LeaderNotificationPolicyService.php`
- `app/Console/Commands/LeaderScheduledNotificationsCommand.php`
- `database/migrations/2026_11_25_000001_ensure_leader_notification_columns_on_app_notifications.php`
- `tests/Unit/Leader/LeaderNotificationSystemTest.php`
- `.github/workflows/verify-backend.yml`
- `docs/peers-global-leadership-audit/33-notification-navigation-and-deep-link-matrix.md`
- `docs/peers-global-leadership-audit/34-notification-development-server-handoff.md`

### Modified Files
- `app/Leader/Services/LeaderNotificationDispatcherService.php`
- `app/Leader/Controllers/LeaderNotificationsController.php`
- `app/Services/Circulars/CircularNotificationService.php`
- `app/Services/Circles/CircleJoinRequestNotificationService.php`
- `app/Services/Events/EventFlowService.php`
- `app/Services/Impacts/ImpactService.php`
- `app/Leader/Controllers/LeaderFinanceController.php`
- `app/Leader/Controllers/LeaderReportsController.php`
- `app/Leader/Controllers/LeaderRoleManagementController.php`
- `app/Leader/Controllers/LeaderTeamsController.php`
- `app/Http/Controllers/Api/V1/Forms/VisitorRegistrationController.php`
- `app/Http/Controllers/Api/V1/Forms/WebsiteFormsController.php`
- `app/Http/Controllers/Api/V1/CoinClaimController.php`
- `app/Http/Controllers/Api/V1/PostReportController.php`
- `app/Console/Commands/SendCircleMembershipExpiryReminders.php`
- `app/Console/Commands/MembershipsExpire.php`
- `routes/leader.php`
- `routes/console.php`
- `docs/peers-global-leadership-audit/31-master-actionable-notification-catalogue.md`
- `docs/peers-global-leadership-audit/32-role-wise-notification-matrix.md`

---

## 2. New Migration Name & Purpose
- `2026_11_25_000001_ensure_leader_notification_columns_on_app_notifications.php`
  - **Purpose:** Idempotently ensures `app_notifications` has `dedupe_key` (indexed, `varchar(128)`), `sent_at`, `failed_at`, `failure_reason`, `screen`, `category`, and `priority` on both existing and fresh PostgreSQL installations. Preserves all existing notification rows.

---

## 3. Required Environment Variables (Names Only — No Secrets)
Verify `.env` on the Development Server already defines:
- `APP_TIMEZONE=Asia/Kolkata`
- `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION` (`database` or `redis` recommended for async FCM jobs; `sync` works for immediate smoke tests)
- `FIREBASE_CREDENTIALS` / `FIREBASE_PROJECT_ID` (used by `App\Services\Firebase\FcmService`)
- `FIREBASE_DEFAULT_ANDROID_CHANNEL_ID=peers_unity_notifications_woodblock`

---

## 4. Required Queue Workers
Ensure at least one queue worker is running on the Development Server if `QUEUE_CONNECTION != sync`:
```bash
php artisan queue:work --queue=default,notifications --tries=3 --timeout=60
```

---

## 5. Required Scheduler / Cron Configuration
Ensure Laravel's scheduler cron entry is active on the Development Server:
```cron
* * * * * cd /path/to/unity-app-new && php artisan schedule:run >> /dev/null 2>&1
```
This runs `leader:dispatch-scheduled-notifications` daily at `09:00 Asia/Kolkata` (`routes/console.php`), plus existing `memberships:expire` and `memberships:send-circle-expiry-reminders`.

---

## 6. FCM Prerequisites
- Valid Firebase Service Account JSON readable by PHP-FPM/CLI on the Development Server.
- Flutter Leader App builds must bundle `android/app/src/main/res/raw/peers_woodblock.wav` and `ios/Runner/peers_woodblock.wav` (for iOS, verify `peers_woodblock.wav` is in Xcode `Copy Bundle Resources`).
- Leader devices must have registered tokens via `POST /api/leader/push-token`.

---

## 7. Safe Development Server Commands (Run on DEV SERVER ONLY)
```bash
# 1. Inspect pending migrations before running
php artisan migrate:status

# 2. Run additive migration on DEV database
php artisan migrate

# 3. Clear route/config caches only if route or config caching is enabled on DEV
php artisan route:clear
php artisan config:clear

# 4. Dry-run scheduled leadership notifications safely (no DB writes, no FCM pushes)
php artisan leader:dispatch-scheduled-notifications --dry-run
```
> **NEVER** run `migrate:fresh`, `db:wipe`, or test suites against Production.

---

## 8. Local / CI Test Commands (Do NOT Run Against Production)
```bash
# Pint check on notification files
./vendor/bin/pint --test \
  app/Leader/Services/LeaderNotificationPolicyService.php \
  app/Leader/Services/LeaderNotificationDispatcherService.php \
  app/Leader/Controllers/LeaderNotificationsController.php \
  app/Services/Circulars/CircularNotificationService.php \
  app/Console/Commands/LeaderScheduledNotificationsCommand.php

# Unit tests (no DB mutation)
php artisan test tests/Unit/Leader/LeaderNotificationSystemTest.php

# Isolated PostgreSQL Feature Tests (CI / local test DB ONLY)
php artisan test tests/Feature/Leader
```

---

## 9. API Endpoints to Test
- `GET /api/leader/notifications?category=all&page=1&per_page=20` (supports `unread`, `action_required`, `critical`, `governance`, `membership`, `finance`, `performance`, `events`, `industry`, `compliance`, `security`, `system`, `unread_only=1`)
- `GET /api/leader/notifications/unread-count`
- `POST /api/leader/notifications/mark-read` / `POST /api/leader/notifications/{id}/read` / `POST /api/leader/notifications/mark-all-read`
- `GET /api/leader/notifications/preferences` & `PUT /api/leader/notifications/preferences`
- `GET /api/leader/admin/notifications/config` & `POST /api/leader/admin/notifications/config` (`system_configs` capability)
- `POST /api/leader/admin/notifications/send` (`system_configs` capability)
- `POST /api/leader/push-token` & `DELETE /api/leader/push-token`

---

## 10. Example Notification Payloads (Safe Dummy IDs)

### Offline Payment (`M-04`, Critical, Masked Lock-Screen)
```json
{
  "schema_version": "2.0",
  "notification_id": "00000000-0000-4000-8000-000000000101",
  "notification_type": "offline_payment_verification",
  "audience": "leadership",
  "category": "finance",
  "priority": "urgent",
  "action_required": true,
  "entity_type": "offline_payment",
  "entity_id": "00000000-0000-4000-8000-000000000201",
  "target_record_id": "00000000-0000-4000-8000-000000000201",
  "destination": "/finance",
  "destination_tab": 3
}
```
- **Expected Recipients:** `superAdmin`, `countryDirector` (scoped to country), `districtExecDirector` (scoped to district). Roles without `access_finance` are excluded.
- **Expected Flutter Behavior:** Opens `/home` tab `3` (Finance), scrolls to transaction `00000000-0000-4000-8000-000000000201`, highlights 3 times. Non-finance roles are blocked and redirected to `/notifications`.

### Visitor Registration Pending (`A-01`, High)
```json
{
  "schema_version": "2.0",
  "notification_id": "00000000-0000-4000-8000-000000000102",
  "notification_type": "visitor_registration_pending",
  "audience": "leadership",
  "category": "events",
  "priority": "high",
  "action_required": true,
  "entity_type": "approval",
  "entity_id": "00000000-0000-4000-8000-000000000202",
  "target_record_id": "00000000-0000-4000-8000-000000000202",
  "circle_id": "00000000-0000-4000-8000-000000000301",
  "destination": "/leader-activities",
  "destination_filter": "visitor-registrations"
}
```
- **Expected Recipients:** `circleDirector`, `chairEventsPrograms`, `districtExecDirector`, `circleFounder`, `viceChair`, `secretary` (scoped to `circle_id` / `district_id`). Super Admin is NOT pushed.
- **Expected Flutter Behavior:** Opens `/leader-activities` (`visitor-registrations`), paginates if off-page, scrolls to record, highlights 3 times.

### Official Circular (`L-09`, Medium)
```json
{
  "schema_version": "2.0",
  "notification_id": "00000000-0000-4000-8000-000000000103",
  "notification_type": "official_circle_circular",
  "audience": "leadership",
  "category": "governance",
  "priority": "medium",
  "action_required": false,
  "entity_type": "circular",
  "entity_id": "00000000-0000-4000-8000-000000000203",
  "target_record_id": "00000000-0000-4000-8000-000000000203",
  "destination": "/circulars"
}
```
- **Expected Recipients:** Only leaders matching `circular.target_roles` (or `circle_id` leaders when scoped to a circle).
- **Expected Flutter Behavior:** Opens `/circulars`, resets priority filter to `All` if hidden, scrolls to circular, highlights 3 times.

---

## 11. Verifying Member App Behavior Remains Unchanged
- Trigger a consumer event (post like, chat message, birthday celebration, member circular) for a normal member account.
- Verify `notifications` / `app_notifications` rows carry `audience: 'member'` (or legacy consumer types) and continue delivering to the Member App.
- Verify `GET /api/leader/notifications` for a Leader holding that account **excludes** all Member App social/engagement items.

---

## 12. Rollback Steps
The migration `2026_11_25_000001_ensure_leader_notification_columns_on_app_notifications.php` is additive and non-destructive; `down()` preserves existing columns/data so rolling back the migration record via `php artisan migrate:rollback --step=1` leaves existing notification data intact. To revert code changes, `git revert` the notification commits without resetting database data.

---

## 13. Known Limitations & Unsupported Workflows
- **Admin-Web-Only Workflows:** `A-09` (Support ticket SLA desk) and `L-03` (Jury evaluation scoring) exist in `unity-admin-web`; Leadership mobile app falls back to `/notifications` or `/leader-activities`.
- **Workflows Without Automated Triggers:** `L-01`, `L-02`, `L-06`, `L-07`, `M-07`, `M-09`, `M-10`, `C-01`, `C-02`, `C-03`, `C-04`, `C-06`, `D-01..D-03`, `I-02`, `I-03`, `A-06` remain documented in `31-master-actionable-notification-catalogue.md` as awaiting product/SLA threshold approval or dedicated source triggers.
- **Configured Thresholds Requiring Founder Sign-off:** `config('leader.overdue_report_days', 14)` in `LeaderScheduledNotificationsCommand`.
