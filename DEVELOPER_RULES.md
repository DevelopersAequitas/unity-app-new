# PHP / Laravel Developer Rules 🚀

> **Production-first Laravel standards for a multi-product system.**
>
> The goal is to keep the codebase maintainable, predictable, secure, backward-compatible, and easy to scale without creating unnecessary complexity.

---

> ### 📌 Mandatory AI Prompt Suffix (Team Copy-Paste Directive)
> **Copy and paste this line at the end of every prompt you send to any AI assistant:**  
> ```text
> 🔒 Adhere strictly to DEVELOPER_RULES.md. Restrict all changes strictly to the designated Product/Domain. Do NOT touch, refactor, or alter shared models, legacy APIs, or unrelated product files without explicit permission.
> ```

---

## 1. 👨‍💻 Development Standard

All backend and Admin Panel development must be approached with the discipline of a **senior PHP/Laravel engineer with 20+ years of professional software-development experience**.

Prioritize:

* Stability over cleverness.
* Maintainability over shortcuts.
* Backward compatibility over unnecessary rewrites.
* Clear architecture over excessive abstraction.
* Small, focused changes over large refactors.
* Production safety over development convenience.

**Always follow this `DEVELOPER_RULES.md` file strictly. Do not bypass, ignore, or partially apply these rules for convenience.**

---

# 2. 🏢 Product Boundaries

This repository contains multiple products/applications that share infrastructure and/or database resources.

Current products include:

* Member App
* Leader App
* Admin Panel
* Scan App
* DED
* Shared/Integration Infrastructure

### Mandatory Rules

* Treat every product as an independent domain.
* A feature must primarily modify code belonging to that product.
* Never modify another product's business logic without explicit requirement.
* Shared database tables do **not** mean shared business logic.
* Never assume shared code is safe to change.
* Before changing shared code, identify all known consumers.
* Product A must never depend on Product B's business logic.

### Architecture Direction

```text
Product Domain
      ↓
Shared Services / Infrastructure
      ↓
Database
```

Never create:

```text
Product A
   ↓
Product B
```

---

# 3. 🤖 AI Development Rules

AI assistants must treat this repository as a **production multi-product system**, not as a greenfield project.

Before making changes, AI must:

1. Identify the product/domain owning the feature.
2. Identify the affected routes, controllers, services, models and resources.
3. Check whether affected code is shared.
4. Check existing API contracts before changing responses.
5. Make the smallest safe implementation.
6. Preserve existing behavior unless a change is explicitly requested.
7. Review the final diff for unrelated changes.

### AI Must NOT

* Rewrite large files unnecessarily.
* Perform unrelated refactoring.
* Rename existing APIs without approval.
* Remove existing APIs because a new implementation exists.
* Change response structures casually.
* Change shared models simply to make a feature easier.
* Move business logic between products.
* Introduce unnecessary Repository/Interface/Factory/Abstract layers.
* Replace working production architecture merely because another pattern looks cleaner.
* Delete legacy code without confirming all consumers.
* Change database structure without considering every product using it.

### Mandatory AI Rule

> **Before completing any task, verify that the implementation follows every applicable rule in this file and that no unrelated product, API, database structure, or shared business logic was changed.**

---

# 4. 🧱 Architecture

Use Laravel's existing architecture wherever possible.

Preferred structure:

```text
Controller
    ↓
Form Request / Authorization
    ↓
Service / Action
    ↓
Model / Query
    ↓
Resource / Response
```

Use:

* Controllers for HTTP handling.
* Form Requests for validation.
* Policies/Gates for authorization.
* Services/Actions for meaningful business logic.
* API Resources for response formatting.
* Jobs for appropriate asynchronous work.
* Models for data relationships and universally valid model behavior.

Do not create abstractions unless they solve an actual problem.

---

# 5. 🎯 Controllers

Controllers must remain thin.

A controller should generally:

1. Receive the request.
2. Validate/authorize.
3. Call the appropriate Service/Action.
4. Return the appropriate response.

Do not place large business workflows directly inside controllers.

Avoid:

```php
public function store(Request $request)
{
    // 200+ lines of business logic
}
```

Prefer:

```php
public function store(StorePeerRequest $request)
{
    $peer = $this->peerService->create($request->validated());

    return new PeerResource($peer);
}
```

---

# 6. ⚙️ Services & Business Logic

Business logic belongs in appropriate Services/Actions.

Rules:

* Each service should have a clear responsibility.
* Avoid "God Services".
* Use dependency injection.
* Keep integrations isolated.
* Do not duplicate the same business rule in multiple controllers.
* Do not create a Service layer merely for simple CRUD that does not need it.

For external integrations:

* Credentials must come from configuration/environment.
* Never hardcode secrets.
* Validate signatures/webhooks.
* Use Jobs when asynchronous processing is appropriate.
* Handle failures explicitly.

---

# 7. 🛣️ Routing

Product-specific routes must remain isolated.

For example:

```text
routes/
├── api.php
├── leader.php
├── admin.php
└── ...
```

Leader routes belong in:

```text
routes/leader.php
```

Routes must preserve:

* URL
* HTTP method
* Authentication
* Middleware
* Authorization
* Route name
* Parameters
* API response contract

Use UUID constraints where applicable.

Never change a production endpoint's public contract simply to make internal code cleaner.

---

# 8. 📦 API Contract Rules

### Production API responses are contracts.

Before modifying an existing response:

1. Search for all consumers.
2. Check mobile/web/Admin Panel usage.
3. Determine whether the field is already used.
4. Preserve existing fields unless removal/change is explicitly approved.
5. Prefer backward-compatible additions.

Never casually change:

```text
field names
field types
nullability
nested structures
pagination structure
status values
enum values
```

### Do NOT expose Eloquent models directly

Use API Resources or controlled response objects.

Bad:

```php
return $peer;
```

Preferred:

```php
return new PeerResource($peer);
```

---

# 9. 👥 Peer API Data Contract — STRICT

Whenever an API shares **Peer/Member information**, use the following canonical Peer fields.

### Canonical Peer JSON

```json
{
    "id": "b8be8e51-6ba9-4269-8b20-d5fc60de4271",
    "name": "Vinit Chavda",
    "display_name": "Vinit Chavda",
    "first_name": "Vinit",
    "last_name": "Chavda",
    "city": "Ahmedabad, IN",
    "company_name": "CK FutureTech",
    "life_impacted_count": 42754,
    "introduced_count": 0,
    "profile_photo_image": "https://dev.peersunity.com/api/v1/files/01a0950a-4c7e-72f1-9cf7-4473b0bc2491",
    "membership_status": "free_trial_peer",
    "designation": "Developer",
    "level4_category": "DGFT Consultants",
    "is_bookmark": false,
    "is_following": false,
    "is_verified": false,
    "is_pro": false,
    "is_connected": false,
    "connection_status": "pending_sent",
    "is_requested": true,
    "can_send_connection_request": false,
    "match_percentage": 20
}
```

### 🚨 No Duplicate/Copy Fields

**Never create duplicate fields representing the same information.**

For example, do NOT introduce additional alternatives such as:

```text
photo
photo_url
avatar
avatar_url
profilePhoto
profilePhotoUrl
image
image_url
profile_pic
profile_pic_url
profile_image
profile_photo_url
```

when the canonical Peer contract already defines the required fields.

**Use the exact established field names and meanings.**

Do not rename, duplicate, or create "temporary" alternative fields without explicit approval.

### Important

The single canonical profile-image field is:

```text
profile_photo_image
```

Do not create additional profile-image fields (such as `profile_image` or `profile_photo_url`).

If the backend implementation internally has different names, map them to `profile_photo_image` in the canonical API response instead of exposing additional fields.

### Peer API Consistency

Whenever Peer information is returned from:

* Search
* Recommendations
* Matching
* Connections
* Followers
* Following
* Bookmarks
* Leaderboard
* Introductions
* Networking
* Notifications containing Peer information
* Any other Peer-related endpoint

use the established Peer response contract consistently.

Do not create a different Peer structure for every endpoint.

Additional fields may only be added when they represent genuinely new information and are approved as part of the API contract.

---

# 10. 🧠 Models

Models should represent data and universally valid model behavior.

Avoid creating "God Models".

For shared models such as:

```text
User
Peer
Circle
Company
Membership
```

be extremely careful before adding:

* Product-specific business logic.
* Product-specific relationships.
* Product-specific scopes.
* Product-specific accessors.
* Product-specific side effects.

If the logic belongs only to Leader, keep it in Leader code.

If the logic belongs only to Member, keep it in Member code.

Shared model changes are **high-risk changes**.

---

# 11. 🗄️ Database Rules

### Never modify an already-applied migration.

If the schema needs to change:

```text
Create a new migration.
```

Never edit an old production migration to "fix" the schema.

Before database changes:

* Check all products using the table.
* Check foreign keys.
* Check indexes.
* Check existing queries.
* Check API consumers.
* Check nullable/default behavior.
* Check data migration requirements.

### Shared tables

A shared table is a cross-product dependency.

Changing it must be treated as a cross-product change.

Never rename/delete a shared column without checking all consumers.

---

# 12. 🔐 Security

Always:

* Validate external input.
* Authorize protected resources.
* Use Policies/Middleware appropriately.
* Verify ownership/permissions.
* Use parameter binding/Eloquent safely.
* Protect sensitive endpoints.
* Keep secrets in environment/configuration.

Never:

* Hardcode passwords.
* Hardcode API keys.
* Hardcode tokens.
* Log passwords.
* Log authentication tokens.
* Log sensitive personal information unnecessarily.
* Trust user-supplied IDs without authorization checks.

---

# 13. 🧪 Testing

Meaningful production changes must have appropriate tests.

Especially test:

* Authentication.
* Authorization.
* Permissions.
* Peer flows.
* Leader flows.
* Payments.
* Critical business rules.
* External integrations.
* Existing API contracts.
* Shared services/models.

If shared code changes:

> Test the existing product flows that depend on that shared code.

A new feature passing its own test is not enough if an existing product can break.

---

# 14. 🚨 Production & Legacy API Protection

Production APIs must be treated as protected contracts.

Classify APIs as:

### Frozen Legacy

Existing production behavior should not be changed casually.

### Active

Changes are allowed only with compatibility consideration.

### New

New architecture and conventions can be applied.

When replacing existing implementation:

```text
Understand
   ↓
Extract
   ↓
Implement
   ↓
Test
   ↓
Verify existing behavior
   ↓
Improve
```

Do not combine:

```text
feature development
+
architecture rewrite
+
legacy cleanup
+
database redesign
```

unless explicitly requested.

---

# 15. 🖥️ Admin Panel Rules

The Admin Panel is a product/interface of its own.

Do not place business logic directly inside:

* Blade templates.
* JavaScript UI code.
* Controllers with large workflows.

Admin functionality must use the same backend business rules as the API where appropriate.

Never bypass authorization merely because an action is performed from Admin Panel.

Admin users must still have explicit permissions for sensitive operations.

---

# 16. 🔄 Backward Compatibility

When adding a feature:

> **Add before removing.**

Prefer:

```text
Old API → Continue working
New API → Introduce new behavior
Migration → Gradually move consumers
Removal → Only after explicit approval
```

Do not break existing mobile applications because the backend was improved.

Remember:

> **The currently released mobile application is also a production consumer.**

---

# 17. 📁 Code Organization

Keep product-specific code inside product-specific namespaces/directories.

Example:

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           └── Leader/
│   ├── Requests/
│   │   └── Leader/
│   └── Resources/
│       └── Leader/
│
├── Services/
│   └── Leader/
│
└── Policies/
    └── Leader/
```

Avoid putting product-specific code into generic locations such as:

```text
app/Services/
app/Http/Controllers/
```

unless the class is genuinely shared.

---

# 18. 📐 Naming & Code Quality

Use clear, predictable names.

Prefer:

```text
PeerService
ConnectionService
LeaderboardService
CreatePeerRequest
PeerResource
```

Avoid:

```text
Helper
CommonHelper
GlobalService
MiscService
UtilityService
EverythingService
```

unless the responsibility is genuinely broad and justified.

Keep methods focused.

Avoid methods that perform multiple unrelated workflows.

---

# 19. 🧹 Refactoring Rules

Refactoring is allowed only when it directly supports the requested task or has been explicitly requested.

Before refactoring:

* Understand current behavior.
* Identify consumers.
* Confirm API compatibility.
* Check shared dependencies.
* Add/verify tests.

Do not turn:

```text
"Add leaderboard filter"
```

into:

```text
"Rewrite leaderboard architecture"
```

unless explicitly requested.

---

# 20. 🚀 New Feature Development

For every new feature:

### Step 1 — Understand

Identify:

* Product.
* User flow.
* API.
* Database tables.
* Existing services.
* Existing consumers.

### Step 2 — Design

Determine the smallest clean implementation.

### Step 3 — Implement

Follow existing architecture.

### Step 4 — Validate

Check:

* API response.
* Authorization.
* Validation.
* Error handling.
* Database behavior.
* Existing consumers.

### Step 5 — Test

Run relevant tests.

### Step 6 — Review

Review the final diff and remove unrelated changes.

---

# 21. 🔍 Error Handling

Never silently hide errors.

Bad:

```php
try {
    // operation
} catch (\Exception $e) {
    return [];
}
```

This can make production failures look like valid empty data.

Errors should be:

* Handled intentionally.
* Logged appropriately.
* Returned with meaningful API responses.
* Converted to user-safe messages where necessary.

Never expose internal stack traces, secrets, SQL queries, or sensitive implementation details to clients.

---

# 22. ⚡ Performance

Optimize based on actual problems.

Before optimizing:

* Identify the bottleneck.
* Check query count.
* Check indexes.
* Check N+1 queries.
* Check unnecessary API/database calls.
* Check caching opportunities.

Prefer simple optimizations:

```text
Eager loading
Indexes
Pagination
Caching
Query optimization
Queues
```

Do not introduce complex caching or infrastructure without a real requirement.

---

# 23. 📦 Dependencies

Before adding a package:

1. Check whether Laravel/PHP already provides the functionality.
2. Check whether an existing package already handles it.
3. Check package maintenance/status.
4. Consider production impact.
5. Consider security.
6. Consider long-term maintenance.

Do not add dependencies for trivial functionality.

---

# 24. 🌐 API Versioning

API versions must remain explicit.

Example:

```text
/api/v1/...
```

Do not introduce breaking changes into an existing version.

Breaking changes should generally require:

```text
New API version
+
Migration plan
+
Consumer verification
```

---

# 25. 👑 Leader App Rules

Leader App is a separate product domain.

Leader-specific code must remain isolated.

Example:

```text
routes/leader.php

app/Http/Controllers/Api/V1/Leader/
app/Http/Requests/Leader/
app/Http/Resources/Leader/
app/Services/Leader/
app/Policies/Leader/
tests/Feature/Leader/
```

Leader code must not introduce unnecessary coupling to Member functionality.

Leader-specific business rules must not be placed inside shared models/services simply for convenience.

If shared functionality is genuinely required:

1. Identify the shared behavior.
2. Verify all consumers.
3. Extract only the genuinely shared portion.
4. Test existing products.
5. Document the dependency.

---

# 26. 🔔 Notifications & Automated Workflows

Notifications must respect product and role boundaries.

Before implementing automation:

* Identify who triggers it.
* Identify who receives it.
* Check role permissions.
* Avoid duplicate notifications.
* Ensure retries are safe.
* Use Jobs where appropriate.

Never allow a lower-level role to access data/actions outside its permission scope merely because the notification workflow needs it.

---

# 27. 📊 Logging & Observability

Production logs should help developers debug issues without exposing sensitive information.

Log:

* Important failures.
* Integration failures.
* Critical workflow failures.
* Relevant identifiers where safe.

Do not log:

* Passwords.
* Access tokens.
* API secrets.
* Private keys.
* Sensitive authentication information.

Use structured/contextual logging where appropriate.

---

# 28. 🛑 Shared Code Change Rule

Before modifying any shared:

```text
Model
Service
Migration
Middleware
Helper
Resource
Enum
Configuration
```

ask:

```text
Who uses this?
Which products use this?
Could this change alter existing behavior?
Is the change backward compatible?
```

If the answer is unclear, investigate before changing it.

> **Never make a local problem a global change.**

---

# 29. ✅ Before Commit / Pull Request

Run the applicable checks:

```bash
vendor/bin/pint
```

Run relevant tests:

```bash
php artisan test
```

If routes changed:

```bash
php artisan route:list
```

Also verify:

* No unrelated files changed.
* No debugging code remains.
* No secrets were committed.
* No accidental API response changes occurred.
* No duplicate Peer fields were introduced.
* No production migration was modified.
* No unrelated product was affected.
* New environment variables are documented.
* Database changes are backward compatible.

---

# 30. 🧠 Final Development Checklist

Before considering any task complete, verify:

```text
[ ] Correct product/domain identified
[ ] Existing architecture understood
[ ] Shared dependencies checked
[ ] Existing API contract preserved
[ ] Peer API uses canonical fields
[ ] No duplicate/copy fields introduced
[ ] Validation implemented
[ ] Authorization implemented
[ ] Business logic placed correctly
[ ] Database impact checked
[ ] Error handling implemented
[ ] Tests added/updated where required
[ ] Existing flows verified
[ ] No unrelated refactoring
[ ] No secrets/debug code
[ ] Git diff reviewed
[ ] Production compatibility verified
```

---

# 🔒 GOLDEN RULE

> **Do not make a local problem a global change.**

> **Do not create duplicate data fields when an existing contract already defines the correct field.**

> **Do not break a production API simply to make the implementation cleaner.**

> **Keep products isolated, keep shared code genuinely shared, keep API contracts stable, and make every change understandable to the next developer.**

**Most importantly: follow `DEVELOPER_RULES.md` completely and consistently for every development task.**
