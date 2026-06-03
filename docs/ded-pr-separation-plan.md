# DED PR Separation Plan

This document records the safe Git strategy for separating the completed District Executive Director (DED) implementation from the Contact Post API work without changing runtime behavior.

## Repository facts observed

- Current checked-out branch: `work`.
- Current working tree was clean before this document was added.
- The Contact Post API work is identifiable in Git history by:
  - `cefc0be Add contact posts API`
  - `839db6f Allow public contact post requests`
  - `117f137 Merge pull request #314 from DevelopersAequitas/codex/add-post-api-for-contact-details`
- Changes currently after the Contact Post API merge commit are event/admin-event related, not DED-specific, when using `git diff --name-status 117f137..HEAD`.

## DED-related files identified in this checkout

These files contain direct DED, district-scope, DED role, DED assignment, or DED circle-leadership references and should be treated as the DED candidate set when building the DED-only PR.

### Controllers

- `app/Http/Controllers/Admin/Circles/CircleController.php`
- `app/Http/Controllers/Admin/UsersController.php`
- `app/Http/Controllers/Api/CircleController.php`
- `app/Http/Controllers/Api/MyCircleController.php`
- `app/Http/Controllers/Api/V1/Admin/CircleManagementController.php`
- `app/Http/Controllers/Api/V1/Admin/DashboardController.php`
- `app/Http/Controllers/Api/V1/Admin/ImpactAdminController.php`
- `app/Http/Controllers/Api/V1/Admin/IndustryManagementController.php`
- `app/Http/Controllers/Api/V1/Admin/LeadershipController.php`
- `app/Http/Controllers/Api/V1/Admin/UserManagementController.php`

### Middleware and support/services

- `app/Http/Middleware/AdminRoleMiddleware.php`
- `app/Services/Admin/AdminAuditService.php`
- `app/Services/Admin/AdminScopeService.php`
- `app/Support/AdminAccess.php`
- `app/Services/Circles/CircleJoinRequestService.php`

### Requests and resources

- `app/Http/Requests/Admin/Circles/StoreCircleRequest.php`
- `app/Http/Requests/Admin/Circles/UpdateCircleRequest.php`
- `app/Http/Requests/Circle/StoreCircleRequest.php`
- `app/Http/Requests/Circle/UpdateCircleRequest.php`
- `app/Http/Requests/Forms/StoreLeaderInterestRequest.php`
- `app/Http/Resources/CircleResource.php`

### Models and schema

- `app/Models/Circle.php`
- `app/Models/User.php`
- `database/migrations/0000_00_00_000000_create_unity_schema.php`

### Views

- `resources/views/admin/circles/create.blade.php`
- `resources/views/admin/circles/edit.blade.php`
- `resources/views/admin/circles/index.blade.php`
- `resources/views/admin/circles/show.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/partials/sidebar.blade.php`
- `resources/views/admin/partials/topbar.blade.php`

### Routes

- `routes/api.php`
- `routes/web.php`

## Non-DED files identified from Contact Post API work

These files belong to the Contact Post API PR and must not be included in the DED-only PR unless a later DED hunk is independently required and staged manually.

- `app/Http/Controllers/Api/V1/ContactPostController.php`
- `app/Models/ContactPost.php`
- `routes/api.php` Contact Post route hunks only

## Non-DED files currently after the Contact Post API merge commit

The following files are not DED-specific based on the repository diff from `117f137..HEAD`; they should not be moved into a DED-only PR merely because they are present on the current branch.

- `app/Http/Controllers/Admin/EventManagementController.php`
- `app/Http/Controllers/Api/EventController.php`
- `app/Http/Controllers/Api/PostController.php`
- `app/Http/Controllers/Api/V1/Admin/AdminOpsController.php`
- `app/Http/Controllers/Api/V1/Forms/VisitorRegistrationController.php`
- `app/Http/Requests/Event/UpsertAdminEventRequest.php`
- `app/Http/Requests/Event/VisitorEventRegistrationRequest.php`
- `app/Http/Requests/Forms/StoreVisitorRegistrationRequest.php`
- `app/Http/Resources/Event/EventDetailResource.php`
- `app/Http/Resources/Event/EventOccurrenceListResource.php`
- `app/Http/Resources/Event/EventRegistrationResource.php`
- `app/Http/Resources/EventResource.php`
- `app/Models/Event.php`
- `app/Models/EventRegistration.php`
- `app/Models/VisitorRegistration.php`
- `app/Services/Events/EventPaymentService.php`
- `app/Services/Events/EventQrService.php`
- `app/Services/Events/EventRegistrationService.php`
- `app/Services/Events/EventService.php`
- `composer.json`
- `resources/views/admin/events/create.blade.php`
- `resources/views/admin/events/index.blade.php`
- `resources/views/admin/events/show.blade.php`
- `routes/web.php` event/admin-event route hunks only

## Safest Git strategy

Use a copy-first strategy rather than rewriting the working implementation directly:

1. Create a backup branch at the exact current mixed state.
2. Create the DED branch from the intended clean base, normally the latest `origin/main` after Contact Post API is merged, or the pre-Contact base if the DED PR must be independent of Contact Post API.
3. Restore only DED-specific files/hunks from the backup branch.
4. For shared files such as `routes/api.php`, `routes/web.php`, `app/Models/User.php`, and `app/Models/Circle.php`, stage with `git add -p` and accept only DED hunks.
5. Verify that Contact Post API files and route hunks are absent from the DED branch.
6. Compare the DED branch against the backup branch for every DED path to prove the DED implementation is byte-for-byte identical after separation.
7. Open the DED PR from the new DED branch.
8. Return to the original Contact Post API branch and remove DED hunks there, again with a backup branch kept available until both PRs are merged.

## Exact commands

Replace branch names as needed, but keep the order.

```bash
# 0. Confirm a clean working tree.
git status --short

# 1. Save the current mixed implementation exactly as-is.
git branch backup/mixed-contact-ded

# 2. Create the clean DED branch from the correct base.
git fetch origin
git switch -c feature/ded-only origin/main

# 3. Restore newly-created DED-only files from the backup.
git restore --source backup/mixed-contact-ded -- \
  app/Http/Controllers/Api/V1/Admin/CircleManagementController.php \
  app/Http/Controllers/Api/V1/Admin/DashboardController.php \
  app/Http/Controllers/Api/V1/Admin/ImpactAdminController.php \
  app/Http/Controllers/Api/V1/Admin/IndustryManagementController.php \
  app/Http/Controllers/Api/V1/Admin/LeadershipController.php \
  app/Http/Controllers/Api/V1/Admin/UserManagementController.php \
  app/Services/Admin/AdminAuditService.php \
  app/Services/Admin/AdminScopeService.php

# 4. Restore candidate shared files, then stage only DED hunks interactively.
git restore --source backup/mixed-contact-ded -- \
  app/Http/Controllers/Admin/Circles/CircleController.php \
  app/Http/Controllers/Admin/UsersController.php \
  app/Http/Controllers/Api/CircleController.php \
  app/Http/Controllers/Api/MyCircleController.php \
  app/Http/Middleware/AdminRoleMiddleware.php \
  app/Http/Requests/Admin/Circles/StoreCircleRequest.php \
  app/Http/Requests/Admin/Circles/UpdateCircleRequest.php \
  app/Http/Requests/Circle/StoreCircleRequest.php \
  app/Http/Requests/Circle/UpdateCircleRequest.php \
  app/Http/Requests/Forms/StoreLeaderInterestRequest.php \
  app/Http/Resources/CircleResource.php \
  app/Models/Circle.php \
  app/Models/User.php \
  app/Services/Circles/CircleJoinRequestService.php \
  app/Support/AdminAccess.php \
  database/migrations/0000_00_00_000000_create_unity_schema.php \
  resources/views/admin/circles/create.blade.php \
  resources/views/admin/circles/edit.blade.php \
  resources/views/admin/circles/index.blade.php \
  resources/views/admin/circles/show.blade.php \
  resources/views/admin/dashboard.blade.php \
  resources/views/admin/partials/sidebar.blade.php \
  resources/views/admin/partials/topbar.blade.php \
  routes/api.php \
  routes/web.php

git add -p

# 5. Remove any accidentally restored Contact Post API files from the DED branch.
git restore --staged --worktree -- \
  app/Http/Controllers/Api/V1/ContactPostController.php \
  app/Models/ContactPost.php

# 6. Verify Contact Post API is not included.
git diff --cached --name-only | rg 'ContactPost|contact-post|contact_posts' && echo 'ERROR: contact post leaked' || echo 'OK: no contact post files staged'
git diff --cached -- routes/api.php | rg 'contact-post|contact_posts|ContactPostController' && echo 'ERROR: contact post route leaked' || echo 'OK: no contact post route staged'

# 7. Verify every staged DED file matches the backup branch exactly for DED-owned paths.
# Run this before committing; any output identifies a path that still differs from the current working DED implementation.
git diff --name-only --cached | while read -r path; do
  git diff --quiet backup/mixed-contact-ded -- "$path" || echo "DIFF_FROM_BACKUP $path"
done

# 8. Commit the DED-only branch.
git commit -m "Add DED administration module"

# 9. Push and open the DED-only PR.
git push -u origin feature/ded-only
```

## Commands to clean the original Contact Post API branch

Run these only after the DED branch is safely committed and pushed.

```bash
# 1. Return to the original mixed branch.
git switch <original-contact-post-branch>

# 2. Create another safety branch before removing DED from the original PR.
git branch backup/contact-pr-before-ded-removal

# 3. Remove DED-only created files from the Contact Post API branch.
git rm \
  app/Http/Controllers/Api/V1/Admin/CircleManagementController.php \
  app/Http/Controllers/Api/V1/Admin/DashboardController.php \
  app/Http/Controllers/Api/V1/Admin/ImpactAdminController.php \
  app/Http/Controllers/Api/V1/Admin/IndustryManagementController.php \
  app/Http/Controllers/Api/V1/Admin/LeadershipController.php \
  app/Http/Controllers/Api/V1/Admin/UserManagementController.php \
  app/Services/Admin/AdminAuditService.php \
  app/Services/Admin/AdminScopeService.php

# 4. For shared files, restore the clean base version and then re-apply/stage only Contact Post API hunks.
git restore --source origin/main -- \
  routes/api.php \
  routes/web.php \
  app/Models/User.php \
  app/Models/Circle.php

git restore --source backup/contact-pr-before-ded-removal -- \
  app/Http/Controllers/Api/V1/ContactPostController.php \
  app/Models/ContactPost.php

git add -p

# 5. Verify DED references are gone from the Contact Post API branch diff.
git diff --cached | rg "ded_user_id|'ded'|DED|AdminScopeService|visibleDistrict" && echo 'ERROR: DED leaked' || echo 'OK: no DED staged'

# 6. Commit the cleanup.
git commit -m "Remove DED changes from Contact Post API PR"
```

## Verification checklist

Run these checks on the DED branch before opening the PR:

```bash
# Shows exactly which files will be in the DED PR.
git diff --name-status origin/main..HEAD

# Contact Post API files must not appear.
git diff --name-only origin/main..HEAD | rg 'ContactPost|contact-post|contact_posts' && echo 'ERROR' || echo 'OK'

# Contact Post route hunks must not appear.
git diff origin/main..HEAD -- routes/api.php | rg 'ContactPostController|contact-post|contact_posts' && echo 'ERROR' || echo 'OK'

# DED code in the new PR must match the backup mixed implementation.
git diff --name-only origin/main..HEAD | while read -r path; do
  git diff --quiet backup/mixed-contact-ded -- "$path" || echo "DIFF_FROM_BACKUP $path"
done

# Laravel/PHP sanity checks.
php artisan route:list | rg 'admin|ded|circle|join|dashboard'
php artisan test
```

If the `DIFF_FROM_BACKUP` loop prints no paths, the DED files in the new PR are identical to the current working DED implementation for every file included in the PR.
