# Unity App Developer Rules Book 🚀
> **Pragmatic, High-Performance PHP & Laravel Standards for Solo Developers & Small Teams**

This rules book defines the engineering standards, architecture decisions, and coding style for the Unity App codebase. It is designed to maximize developer velocity while ensuring a robust, secure, and clean codebase.

---

## 1. Core Philosophy (The Pragmatic "One-Man Army" Approach)

When working as a solo developer or in a small team, **simplicity, clarity, and reliability are your best friends.** Avoid over-engineering and premature optimization.

*   **No Over-Engineering:** Do not create layers of abstraction (e.g., Repository Pattern, excessive interfaces) unless they solve a concrete, immediate problem. Use Laravel's Eloquent ORM, custom query builders, and Services directly.
*   **Fail Loudly & Early:** Let errors throw exceptions. Do not catch exceptions just to return empty values unless you have a specific fallback strategy. Use Laravel's global exception handler or HTTP responses.
*   **Automate Style:** Never argue about code styling. The formatter is the source of truth. Always run the formatting tools before committing.
*   **Write Tests for Core Logic:** You don't need 100% code coverage, but critical business paths (e.g., payment flows, campaign runs, auth checks, custom commands) **must** have feature tests.

---

## 2. PHP 8.2+ & Modern Coding Standards

Since this codebase is running on PHP 8.2+, take full advantage of modern language features.

*   **Strict Types:** Always add `declare(strict_types=1);` at the top of every new PHP file.
*   **Strict Typing:** Type-hint all method arguments, return values, and class properties. Use PHP 8 constructor property promotion to simplify classes.
    ```php
    declare(strict_types=1);

    namespace App\Services;

    use App\Models\User;

    class PeerService
    {
        // Use constructor property promotion
        public function __construct(
            protected SmsService $smsService
        ) {}

        public function registerPeer(User $user): void
        {
            // Business logic
        }
    }
    ```
*   **Strict Expressions:** Use `match` expressions instead of long `switch` statements where applicable. Use nullsafe operators (`?->`) to avoid nested null checks.
*   **Linting & Styling:** 
    *   This project uses **Laravel Pint** for style enforcement.
    *   Before committing any code, run:
        ```bash
        ./vendor/bin/pint
        ```
    *   Adhere to **PSR-12 / PER Coding Style**.

---

## 3. Laravel 12 Architecture & Patterns

### 3.1 Routing
*   **Class-Based Routes:** Always reference controller methods using array syntax. Never use string-based controllers.
*   **UUID Constraints:** Because this codebase uses UUIDs for primary keys, always append the `whereUuid` constraint to route parameters.
*   **Route Groups:** Group routes by middleware, versioning (`v1`), prefix, and namespaces.
    ```php
    // Correct routing pattern
    Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
        Route::get('/posts/{id}', [AdminOpsController::class, 'postShow'])->whereUuid('id');
    });
    ```

### 3.2 Controllers
*   **Thin Controllers:** Keep controllers focused on handling HTTP requests and returning responses.
*   **Validation:** Never write validation logic directly in the controller methods. Use custom **Form Request** classes.
*   **Response Helpers:** Use uniform JSON responses for API endpoints.
    ```php
    public function show(GetPostRequest $request, string $id): JsonResponse
    {
        $post = $this->postService->findPostWithDetails($id);
        
        return response()->json([
            'status' => 'success',
            'data' => new PostResource($post)
        ]);
    }
    ```

### 3.3 Models
*   **UUID Primary Keys:** Use string primary keys and generate UUIDs automatically using the `creating` event within the model's `booted()` method.
    ```php
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
    ```
*   **Casting:** Explicitly define casts for attributes (e.g., `'industry_tags' => 'array'`, `'membership_expiry' => 'datetime'`, `'is_verified' => 'boolean'`).
*   **Soft Deletes:** Use Laravel's `SoftDeletes` trait on all entities containing business or user-generated data.

### 3.4 Services & Actions
*   **Encapsulate Business Logic:** Write business logic (e.g., external API calls, complex payment math, notification dispatching) inside Service classes located in `app/Services/`.
*   **Dependency Injection:** Resolve Services via the Laravel container in controllers or commands.

---

## 4. Third-Party Integrations

When dealing with third-party APIs (e.g., **Razorpay**, **Zoho**):
*   **Dedicated Services:** Build a wrapper service class under `app/Services/` (e.g., `app/Services/RazorpayService.php`).
*   **Configuration & Credentials:** Never hardcode api keys or secrets. Retrieve them from `config/services.php` (which reads from `.env`).
*   **Webhook Handlers:** Use robust webhook controllers. Validate signature payloads. Log webhook payloads under a specific log channel or custom database table (`WebhookEvent`) for troubleshooting.
*   **Asynchronous Jobs:** Dispatch heavy API transactions or notification deliveries to the queue using Laravel Jobs (`app/Jobs/`).

---

## 5. Database & Migrations

*   **Immutable Migrations:** Never edit a database migration file that has already been executed on other environments or staging/production database. Write a new migration file to modify columns.
*   **Explicit Constraints & Indexes:** Always define foreign keys and add indexes to frequently queried columns (e.g., `status`, `user_id`, `created_at`).
*   **Seeders:** Use database seeders for lookups and initial setup data (e.g., categories, roles) so anyone can set up the environment with `php artisan migrate --seed`.

---

## 6. Testing Strategy

*   **Pest or PHPUnit:** This codebase uses PHPUnit for running feature tests.
*   **Test Naming:** Use clear descriptive names: `test_user_is_downgraded_after_expiry()` or `test_razorpay_webhook_creates_subscription()`.
*   **External Faking:** Always mock external API requests in tests using `Http::fake()` or custom mocking objects to prevent tests from calling real services.
*   **Database Cleanup:** Use `Illuminate\Foundation\Testing\RefreshDatabase` or transactions on test methods to ensure test isolation.

---

## 7. Security Rules

*   **Validation:** Validate ALL inputs. Reject invalid payloads early.
*   **Authorization:** Use Laravel Policy classes (`app/Policies/`) to check permissions. Never assume database lookups are safe without verifying if the user owns the record.
*   **SQL Injection:** Always use Eloquent or query builder bindings. Avoid raw SQL strings unless bindings are fully configured.
*   **Data Masking:** Mask sensitive user details (e.g., password hashes, personal identification numbers) in logs.

---

## 8. Command Checklist for Developers
Before you commit and push to Git:
1.  **Format Code:** `vendor/bin/pint`
2.  **Run Tests:** `php artisan test` or `./vendor/bin/phpunit`
3.  **Check Routes:** Verify routes list if routes were added: `php artisan route:list`
4.  **Confirm Env:** Ensure any new env variable is added to `.env.example`
