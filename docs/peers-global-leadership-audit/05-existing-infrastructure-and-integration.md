# 05 — Existing Infrastructure, Firebase, Storage & Integration Audit

> **Scope:** Authentication/Sanctum, Dio Networking, Offline Caching, Firebase/FCM (and Firestore verification), Multi-Channel Messaging (WhatsApp & Email), PDF/QR/Creative Engines, Payment Gateways, Background Jobs, Scheduled Tasks, and OTA Release Tooling.  
> **Related Deliverables:** [01-existing-system-architecture.md](./01-existing-system-architecture.md) · [20-authentication-and-role-resolution-flow.md](./20-authentication-and-role-resolution-flow.md) · [28-notification-generation-and-delivery-flow.md](./28-notification-generation-and-delivery-flow.md) · [30-existing-notification-system-audit.md](./30-existing-notification-system-audit.md) · [42-rbac-security-audit.md](./42-rbac-security-audit.md)

---

## 1. Firebase & Cloud Firestore Verification (`FACT`)

A mandatory requirement of this audit was to inspect existing Firebase infrastructure and Firestore collections, if applicable.

**FACT:** Cross-repository manifest and source inspection (`unity-app-new`, `LeaderApp-Fornt-end-`, `peers-unity/unity_app`, `unity-admin-web`) proves conclusively:
1. **ZERO Cloud Firestore Usage:** Neither `cloud_firestore`, `firebase-admin` Firestore SDKs, nor any `firestore.rules` / Firestore collection exists in any of the four repositories. All application, RBAC, chat, activity, and notification persistence is 100% **PostgreSQL** (with real-time sockets handled by **Laravel Reverb** `laravel/reverb: ^1.7`).
2. **Firebase Core + Firebase Cloud Messaging (FCM) Only:**
   - **Flutter Leadership App (`LeaderApp-Fornt-end-`)**: Uses `firebase_core: ^5.1.0` and `firebase_messaging: ^17.0.1` ([pubspec.yaml](file:///D:/office%20project/LeaderApp-Fornt-end-/pubspec.yaml#L49-L51)), configured via [firebase_options.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/firebase_options.dart) and `android/app/google-services.json` *(Note: `ios/Runner/GoogleService-Info.plist` was present in `member_app` but missing from `leader_app/ios/Runner/` in the file scan — verify before iOS App Store build!)*.
   - **Flutter Member App (`peers-unity/unity_app`)**: Uses `firebase_core: ^4.15.0`, `firebase_messaging: ^16.7.0`, and `google_sign_in: ^6.2.2`.
   - **Laravel 12 Backend (`unity-app-new`)**: Sends push notifications via **FCM HTTP v1 API** (`https://fcm.googleapis.com/v1/projects/{projectId}/messages:send`) in [FcmService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Firebase/FcmService.php), minting OAuth2 access tokens from service-account credentials referenced by `config/firebase.php`.
   - **Security Note (`CRITICAL`):** File scan revealed `storage/app/firebase/peers-global-app-firebase-adminsdk-fbsvc-bd2fc1cc00.json` on disk inside `storage/app/firebase/`. Verify `.gitignore` excludes `storage/app/firebase/*.json` and ensure credentials are loaded strictly via `config('firebase')` / environment secrets in production!

---

## 2. Authentication, Token Storage & Networking Stack

| Layer | Implementation File(s) | Current Mechanism (`FACT`) | Audit Status & Identified Limitations |
|---|---|---|---|
| **Backend Token Provider** | [config/sanctum.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/config/sanctum.php#L53), [LeaderAuthService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderAuthService.php#L175-L208) | Laravel Sanctum personal access tokens (`personal_access_tokens` table), `'expiration' => null` (never expires). Issues token named `'leader-mobile-app'` on `App\Models\User`. | `Implemented but Incorrect` — (1) Unconditional `'123456'` OTP backdoor in prod (`L156-159`); (2) Generates fake `$refreshToken = Str::random(40)` (`L180`) that is **never stored** in DB; (3) Tokens never expire. |
| **Mobile Secure Storage** | [secure_storage_service.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/storage/secure_storage_service.dart), [session_manager.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/helpers/session_manager.dart) | `flutter_secure_storage ^9.2.4` stores `access_token` and unused `refresh_token`; `SessionManager` caches `UserSession` & `LeaderPermissions` in Hive. | `Partially Implemented` — Clean storage abstraction, but `SessionManager` has no reactive `401` forced-logout handler when a token is revoked server-side. |
| **Mobile HTTP Client & Interceptors** | [api_client.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/network/api_client.dart), [api_endpoints.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/constants/api_endpoints.dart) | Singleton `Dio` (`^5.8.0+1`) client injecting `Accept: application/json` and `Authorization: Bearer <token>`. Supports `local`, `dev` (`dev.peersunity.com/api/v1`), and `prod` (`peersunity.com/api/v1`). | `Requires Enhancement` — (1) Logs raw `Bearer` token to console under `kDebugMode`; (2) Throws `UnauthenticatedException` on `401` without triggering global session sign-out; (3) No retry/refresh interceptor. |
| **Offline Cache Layer** | [hive_cache_service.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/storage/hive_cache_service.dart) | `hive ^2.2.3` + `hive_flutter ^1.1.0` caches JSON responses per endpoint/key for offline fallback when network fails. | `Requires Enhancement` — Verify cache keys are namespaced by `userId + role + circleId` and flushed completely on logout so Leader B on a shared device never briefly sees Leader A's cached metrics! |

---

## 3. Multi-Channel Notification & Messaging Infrastructure

**FACT:** `unity-app-new` already possesses an enterprise-grade, multi-channel notification stack (audited in full in [30-existing-notification-system-audit.md](./30-existing-notification-system-audit.md)):

1. **In-App Dual Inbox Tables**:
   - `app_notifications` ([AppNotification.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Models/Notifications/AppNotification.php)): Rich schema with `category`, `priority`, `reference_type`, `reference_id`, `screen`, `data` JSON, `dedupe_key`, `status`, `read_at`, `clicked_at`.
   - `notifications` ([Notification.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Models/Notification.php)): Legacy/universal activity table with PostgreSQL `notification_type_enum`.
   - Read together by [LeaderNotificationsController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Controllers/LeaderNotificationsController.php#L26-L360) with `CONSUMER_EXCLUDED_TYPES` filtering.
2. **Push Token Registry (`user_push_tokens`)**:
   - [UserPushToken.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Models/UserPushToken.php) distinguishes Leader App tokens (`app = 'leader'`) from Member App tokens (`app = 'member'`/`null`) and mirrors active tokens onto `users.leader_android_fcm_token` / `users.leader_ios_fcm_token`.
   - Automatic stale-token deactivation on FCM HTTP `404` / `410` / `UNREGISTERED`.
3. **WhatsApp Dual-Transport Engine**:
   - Direct **Meta WhatsApp Cloud API v22.0** (`WhatsAppMessageService` → `WhatsAppCloudApiService` → `whatsapp_messages`, `whatsapp_message_logs`) for migrated templates.
   - **Webhook Automation Relay** (`WhatsappNotificationService` → `whatsapp_templates` → WATI/AiSensy/n8n → `whatsapp_message_delivery_logs`) for non-migrated templates, plus 30-day onboarding journeys (`MemberJourney30DayService`) and milestone awards (`AwardRecognitionWhatsappService`).
4. **Email & Mailable Auditing**:
   - **42 Mailable classes** in `app/Mail/*` + DB-driven `EmailTemplate`s, logged with delivery/failure telemetry in `email_logs` via [EmailLogService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/EmailLogs/EmailLogService.php).

---

## 4. Document, Creative, Media & Payment Integrations

| Capability | Existing Backend Implementation (`FACT`) | Used By | Status |
|---|---|---|---|
| **PDF Generation** | `dompdf/dompdf: ^3.1` powering [LeaderReportPdfGenerator.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderReportPdfGenerator.php) (`performance-report-pdf.blade.php`) and [CertificateGeneratorService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Certifications/CertificateGeneratorService.php) (`resources/views/pdf/certificates/`). | Weekly/Monthly Circle Reports, Entrepreneur & Leadership Certificates | `Implemented but Incorrect` (Report PDF download endpoint skips `$user` scope check & `/api/v1/files/{id}/download` is public!) |
| **QR Code Generation** | `endroid/qr-code: ^6.0` generating event/visitor check-in QR passes (`event_registrations.qr_token`), scanned via `/api/v1/events/checkin/scan` and `/api/v1/scan-app/*`. | Activity #17 (Visitors) & #21 (Events) | `Fully Implemented` |
| **Server-Side Creative Cards** | `intervention/image: 2.7` + Blade/HTML templates in [WelcomeCreativeService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Creative/WelcomeCreativeService.php), `IntroductionCreativeService.php`, `LifeImpactCreativeGenerator.php`, and Election `CreativeController.php`. | Onboarding, Peer Introductions (#27), Life Impact (#22), Election Winners (#13) | `Fully Implemented` |
| **Payments & Billing** | `razorpay/razorpay: ^2.9` (`PaymentController`, `CircleJoinRequestPaymentController`, `AdvertiserPaymentApiController`) + **Zoho Billing** (`ZohoPlansController`, `ZohoPaymentWebhookController`, `zoho_invoice_documents`). | Memberships (#32), Circle Join Fees (#20), Paid Events (#21), Brand Partner Ads (#12) | `Fully Implemented` (Core billing solid; `LeaderFinanceService` aggregation queries need scope/attribution fixes) |
| **OTA & Store Updates** | `shorebird.yaml` bundled in both Flutter apps; `AppConfigService` + `in_app_update: ^5.0.0` (Android Immediate/Flexible) + `upgrader: ^13.7.0` / `url_launcher` (iOS App Store). | Flutter Leadership App & Member App | `Fully Implemented` |

---

## 5. Background Jobs, Scheduled Tasks & Audit Logging

### 5.1 Scheduled Tasks (`routes/console.php`)
**FACT:** `unity-app-new` has **62 Artisan commands** in `app/Console/Commands/`, with **25 active scheduled jobs** wired in `routes/console.php`:
- **Every 1–15 min:** `members:mark-offline-stale`, `app:check-maintenance-transitions`, `campaigns:run`, `celebrations:publish-due`, `whatsapp:dispatch-scheduled-campaigns`, `speed-networking:auto-start`, `habit-loop:send-daily`, `whatsapp:journey:process`, `notifications:campaigns`.
- **Hourly:** `memberships:expire-users`, `users:expire-trial`, `app:update-reminder-notifications`, `profile-completion:send-reminders`.
- **Daily / Weekly:** `memberships:expire`, `collaborations:expire`, `celebrations:sync-schedules`, `PGU:brand-partner-expiry-alerts`, `PGU:advertiser-subscription-expiry-check`, `connections:send-pending-reminders`, `circle-recommendation:send-reminders`, `memberships:send-*-expiry-reminders`, `PGU:brand-partner-weekly-report`.
- **Verified Gap (`NOT IMPLEMENTED`):** Zero scheduled commands exist today for **overdue Circle Report reminders**, **unreviewed Pending Approval SLA reminders/escalations** (`circle_join_requests`, `impacts`, `visitor_registrations`), or **expired `admin_user_roles.expires_at` cleanup**.

### 5.2 Audit Logging Tables (`FACT`)
Four distinct audit tables already exist in PostgreSQL (do **not** create a fifth!):
1. `activity_logs` ([ActivityLog.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Models/ActivityLog.php) via [ActivityLoggerService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Services/Audit/ActivityLoggerService.php)) — Immutable (`updating`/`deleting` blocked), scrubs 20 sensitive keys (`password`, `token`, `otp`, etc.). **Recommended standard audit sink for all Leader App governance mutations.**
2. `admin_audit_logs` (`AdminAuditLog` via `AdminAuditService`) — Tracks Web/API admin CRUD diffs (`old` / `new`).
3. `user_activity_logs` (`UserActivityLog` via `UserActivityLogService`) — Client telemetry/forensic screen actions.
4. `activities_audit` (`ActivityAudit`) + domain history tables (`ask_status_histories`, `web_leadership_audit_logs`, `finance_policy_audit_logs`).

### 5.3 Existing Automated Test Coverage
- **Flutter Leadership App (`LeaderApp-Fornt-end-/test/`)**: **25 test files** covering cold-start & authenticated startup, `NotificationNavigator`, `LeaderNotificationModel`, `AppFormatters`, `PhoneNumberHelper`, `EventTimeZones`, `MonotoneSpline`, `PrimaryButton` responsiveness, `DashboardHeroCard`, `ReportRosterRules`, `LoginBloc`, `OtpBloc`, and `brand_vocabulary_test.dart`.
- **Laravel Backend (`unity-app-new/tests/`)**: PHPUnit `^11.5.3` test suite (`composer test`); **requires dedicated `tests/Feature/Leader/` coverage** for `leader.can` capability gates, cross-circle/cross-district IDOR rejection, OTP bypass removal, and authoritative finance calculations (see [42-rbac-security-audit.md](./42-rbac-security-audit.md) and [50-final-implementation-roadmap.md](./50-final-implementation-roadmap.md)).
