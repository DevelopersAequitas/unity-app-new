# Unity App AI Agent Custom Rules

You are an AI coding assistant (e.g. Antigravity) working on the **Unity App** repository, a PHP 8.2 / Laravel 12 codebase. Always adhere strictly to the guidelines defined below and in the primary developer rules: [DEVELOPER_RULES.md](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/DEVELOPER_RULES.md).

---

## 1. Code Generation Guidelines

*   **Strict Types:** Always add `declare(strict_types=1);` at the top of every new PHP file you create.
*   **Strong Typing:** Always declare parameter types, property types, and return types for all classes, methods, and functions. Avoid using `mixed` unless absolutely necessary.
*   **Thin Controllers:** Place business logic inside Service classes (`app/Services/`) or Action classes. Keep Controllers clean and lightweight.
*   **Validation:** Use custom Form Request classes (`app/Http/Requests/`) for request validation instead of inline controller validation.
*   **UUID Configuration:** 
    *   Models must define key fields:
        ```php
        protected $primaryKey = 'id';
        protected $keyType = 'string';
        public $incrementing = false;
        ```
    *   Generate UUIDs inside the `booted()` method's `creating` hook using `Str::uuid()`.
*   **Routing Conventions:** Always define routes using the controller class reference. Restrict resource parameters to UUIDs by adding `->whereUuid('id')` to routes.
*   **Laravel Pint:** After writing or editing PHP files, always run `./vendor/bin/pint` to ensure code styling matches the project's formatting standard.

---

## 2. Safety & Development Workflow Rules

*   **Never Modify Existing Migrations:** If a column needs to be added, changed, or deleted, create a **new** migration file instead of editing an existing one that has already been executed.
*   **Security:** Verify authorization using policy classes (`app/Policies/`) before processing any requests in controller actions. Use route-model binding or explicit checks.
*   **Sensitive Configurations:** Do not write credentials, API keys, or private keys directly in the code or environment configurations. Use `config('services.name')` with mappings in `.env` and `.env.example`.
*   **Testing:** When building new features or endpoints, create matching feature tests in `tests/Feature/`. Mock external integrations using `Http::fake()` or custom mocks.
*   **Errors:** Fail early and throw appropriate HTTP/JSON exceptions for error states in APIs.

---

## 3. Communication Guidelines

*   Be direct and keep explanations brief when communicating code edits.
*   Link to related files using markdown file paths (e.g. `[User.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Models/User.php)`).
