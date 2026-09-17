# Unity App Developer & AI Rules 🚀

> **Pragmatic Laravel standards with strict product boundaries.**  
> The goal is to move fast without allowing changes in one product to accidentally break another.

---

> ### 📌 Mandatory AI Prompt Suffix (Team Copy-Paste Directive)
> **Copy and paste this line at the end of every prompt you send to any AI assistant:**  
> ```text
> 🔒 Adhere strictly to DEVELOPER_RULES.md. Restrict all changes strictly to the designated Product/Domain. Do NOT touch, refactor, or alter shared models, legacy APIs, or unrelated product files without explicit permission.
> ```

---

## 1. Core Principles

* **Do not over-engineer.** Prefer Laravel, Eloquent, Services, Form Requests, Policies, Jobs, and Resources. Add abstractions only when they solve a real problem.
* **Keep controllers thin.** HTTP handling belongs in controllers; business logic belongs in Services/Actions.
* **Fail clearly.** Do not silently swallow exceptions or return fake/empty success responses.
* **Preserve existing behavior.** Existing production APIs are contracts unless explicitly approved for change.
* **Small, focused changes.** Do not refactor unrelated code while implementing a feature.
* **Never optimize for elegance at the cost of compatibility.**

---

## 2. 🚨 Product Boundary Rules — MOST IMPORTANT

This repository contains multiple products. **Treat them as separate applications even though they share one Laravel project/database.**

### Products

* **Member App**
* **Leader App**
* **Admin Panel**
* **Scan App**
* **DED**
* **Shared/Integration infrastructure**

### Mandatory rules

* A **Leader** task must primarily touch Leader code.
* A **Member** task must primarily touch Member code.
* Never modify another product's controller, route, service, API contract, or business logic unless the task explicitly requires it.
* **Shared data does not mean shared business logic.**
* Do not move product-specific logic into shared classes merely to reuse code.
* Before changing shared code, verify every affected product.
* Never assume a model/service is safe to change just because it is used by the current feature.

### Dependency direction

```text
Product
   ↓
Shared Kernel / Infrastructure
   ↓
Database
```

**Product A must not depend on Product B.**

---

## 3. 🛡️ AI Development Rules

AI assistants must treat this repository as a **protected multi-product system**.

Before changing code:

1. Identify which product owns the feature.
2. Identify the routes, controllers, services, models, middleware and tests involved.
3. Check whether any changed class is shared with another product.
4. Make the smallest safe change.
5. Do not modify unrelated legacy code.
6. Do not perform broad refactors unless explicitly requested.

### AI must NOT

* Rewrite large files unnecessarily.
* Rename/move existing APIs without approval.
* Change API response structures casually.
* Modify legacy Member behavior while implementing Leader functionality.
* Change shared models simply to make a new feature easier.
* Delete or replace old endpoints because a new implementation exists.
* Introduce a new architecture/pattern without a concrete need.

**If a shared dependency must change, stop and identify the impact before changing it.**

---

## 4. 🏗️ Product Architecture

New code should follow the product boundary.

### Leader

```text
app/Http/Controllers/Api/V1/Leader/
app/Services/Leader/
app/Http/Requests/Leader/
app/Http/Resources/Leader/
app/Policies/Leader/
routes/leader.php
tests/Feature/Leader/
```

### Other products

Follow the same principle as their boundaries are introduced.

**Do not put new Leader business logic into generic locations such as:**

```text
app/Http/Controllers/Api/
app/Services/
```

unless the code is genuinely shared infrastructure.

---

## 5. 🛣️ Routing Rules

* Product routes must live in product-specific route files.
* Leader routes belong in `routes/leader.php`.
* Keep API versioning and middleware explicit.
* Use class-based controller references.
* Preserve existing public API URLs during migrations unless a breaking change is explicitly approved.
* Never delete an existing endpoint merely because it has been moved internally.
* Use UUID route constraints where the application expects UUIDs.
* After route changes, verify the route list and middleware.

### Important

**Route organization must not accidentally change:**

* URL
* HTTP method
* middleware
* authentication
* authorization
* route name
* parameter behavior
* response contract

---

## 6. 🎯 Controllers, Requests & Responses

### Controllers

Controllers should:

1. Receive the request.
2. Validate/authorize through dedicated classes.
3. Call the appropriate Service/Action.
4. Return the API response.

### Validation

* Use Form Requests for non-trivial validation.
* Never trust client input.
* Authorization must be explicit.

### API Responses

* Use stable, explicit API response structures.
* Prefer API Resources/DTO-style response shaping where appropriate.
* **Do not expose Eloquent models directly as accidental API contracts.**
* Existing mobile API response structures must be treated as contracts.

---

## 7. 🧠 Models & Shared Models

Models represent data, but **business behavior must remain product-aware**.

* Avoid turning `User`, `Circle`, or other shared models into "God Models."
* Do not add Leader-specific business logic to a shared model unless it is truly domain-wide.
* Do not change shared model relationships/casts/accessors without checking all consumers.
* Prefer product Services/Actions for product-specific behavior.
* Keep shared models focused on shared data and universally valid behavior.

**Shared model = high-risk change.**

---

## 8. ⚙️ Services & Business Logic

* Put meaningful business logic in Services/Actions.
* Services should have one clear responsibility.
* Keep external integrations behind dedicated services.
* Do not create Repository/Interface layers without a real requirement.
* Use dependency injection.
* Avoid Services that become another "God Class."

For integrations such as Razorpay/Zoho:

* Credentials come from configuration/environment.
* Validate webhooks/signatures.
* Use Jobs for appropriate asynchronous work.
* Never hardcode secrets.

---

## 9. 🗄️ Database & Migrations

* **Never edit an already-applied production/staging migration.**
* Create a new migration for schema changes.
* Add appropriate indexes and foreign keys.
* Be careful with shared tables: a schema change can affect every product.
* Do not rename/delete shared columns without checking all consumers.
* Treat database schema changes as cross-product changes.

---

## 10. 🔐 Security

* Validate all external input.
* Authorize every protected resource.
* Use Policies/Middleware where appropriate.
* Never trust IDs supplied by clients without ownership/permission checks.
* Use Eloquent/query bindings instead of unsafe raw SQL.
* Never hardcode secrets.
* Never log passwords, tokens, API keys, or sensitive personal data.

---

## 11. 🧪 Testing & Regression Protection

Every meaningful feature should have tests appropriate to its risk.

### Especially required for:

* Authentication
* Authorization/capabilities
* Leader flows
* Payments
* External integrations
* Critical business rules
* Existing APIs being migrated/refactored

### Cross-product rule

If a change touches shared code, test the affected existing product flows as well.

**A successful new test is not enough if an old product flow breaks.**

Use fake external services in tests.

---

## 12. 🚦 Legacy & Production API Protection

Existing production APIs are **protected contracts**.

Classify APIs internally as:

* **Frozen Legacy** — do not modify behavior casually.
* **Active** — changes require compatibility consideration.
* **New** — can follow the new architecture.

When replacing/moving an existing endpoint:

> **Extract → Verify → Test → Protect → Then improve.**

Do not use a migration as an excuse for a simultaneous rewrite.

---

## 13. 👑 Leader App Launch Rules

Leader is currently being prepared for production/store launch.

Therefore:

* Leader is the **first product to receive the new architectural boundary**.
* Existing Leader API URLs should remain unchanged unless explicitly approved.
* Leader routes should be isolated into `routes/leader.php`.
* Leader controllers/services should remain under their Leader namespaces/directories.
* Leader-specific business logic must not be added to Member/legacy code.
* Every Leader endpoint should have appropriate regression/feature coverage before launch.
* Changes to shared models or shared services during Leader work require explicit impact review.

**Goal: launch Leader cleanly without creating new coupling to legacy Member flows.**

---

## 14. 📋 Before Committing

Run:

1. `vendor/bin/pint`
2. Relevant PHPUnit/feature tests
3. `php artisan route:list` when routes changed
4. Check `.env.example` for new environment variables
5. Review the Git diff
6. Confirm no unrelated product files changed

### Final AI check

Before finishing any task, ask:

> **Did I change anything outside the product/domain requested?**

If yes, explain why it was necessary.

---

## 15. 🚫 Golden Rule

> **Do not make a local problem a global change.**

Keep product behavior isolated, keep shared code minimal, preserve existing API contracts, and make changes small enough that another developer—or an AI assistant—can understand their impact.
