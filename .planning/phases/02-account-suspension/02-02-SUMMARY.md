---
phase: 02-account-suspension
plan: 02
subsystem: auth
tags: [jwt, broadcast, queue, middleware]

# Dependency graph
requires:
  - phase: 02-01
    provides: status enum on users/organizations and status helpers in models
  - phase: 01-tenant-data-scoping
    provides: tenant context resolution used when evaluating effective account state
provides:
  - account status enforcement for web and API request paths
  - broadcast channel authorization checks that respect account state
  - queue execution-time account-state checks for activity log jobs
affects:
  - account-suspension
  - api-auth
  - broadcasting
  - queues

# Tech tracking
tech-stack:
  added: []
  patterns:
    - shared account status middleware with allowlist for restricted accounts
    - job middleware and execution-time data re-check before sensitive writes
    - broadcast channel registration with account-state gates

key-files:
  created:
    - app/Http/Middleware/AccountStatusMiddleware.php
    - app/Http/Controllers/AccountRestrictedController.php
    - resources/views/account/restricted.blade.php
    - resources/views/account/suspended.blade.php
    - routes/channels.php
    - app/Jobs/Middleware/EnsureAccountActive.php
    - tests/Feature/AccountSuspensionMiddlewareTest.php
  modified:
    - bootstrap/app.php
    - routes/web.php
    - routes/api.php
    - wave/src/Jobs/CreateActivityLog.php
    - wave/src/Http/Middleware/TokenMiddleware.php
key-decisions:
  - "Use middleware gating on each entry point and keep status semantics centralized."
  - "Allow restricted users through a dedicated flow and hard-block suspended users."
  - "Re-check account state at queue execution time to avoid stale authorization."

issues-created: []

# Metrics
duration: 22 min
completed: 2026-03-05
---

# Phase 2: Account Suspension Summary

**Account status enforcement now applies to web, API, token, broadcast, and queued activity paths before request-sensitive or async work runs**

## Performance

- **Duration:** 22 min
- **Started:** 2026-03-05T16:40:00Z
- **Completed:** 2026-03-05T17:02:00Z
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments
- Added shared account-status middleware and restricted/suspended user experiences.
- Wired account checks into web middleware stack, API endpoint guards, broadcast channels, and token middleware.
- Re-validated activity log creation in queue execution with job middleware and explicit account-state checks.

## Task Commits

Each task was committed atomically:

1. **Task 1: Implement shared account status middleware and restricted route** - `b21c5d5` (feat)
2. **Task 2: Wire middleware into web/API/broadcast and queue/job checks** - `76d656a` (feat)
3. **Task 3: Test the gate behavior and edge cases** - `def0a11` (test)

## Files Created/Modified
- `app/Http/Middleware/AccountStatusMiddleware.php` - Central middleware handling restricted/suspended decisions.
- `app/Http/Controllers/AccountRestrictedController.php` - Restricted route handler with status reason and guidance.
- `resources/views/account/restricted.blade.php` - Restricted state user page.
- `resources/views/account/suspended.blade.php` - Suspended state block screen with support reference.
- `routes/web.php` - Added restricted route and data-export entry alignment.
- `bootstrap/app.php` - Added account middleware registration and channel routing.
- `routes/api.php` - Added explicit account-status checking on token-auth endpoints.
- `routes/channels.php` - Added channel auth callbacks for private user and organization channels.
- `app/Jobs/Middleware/EnsureAccountActive.php` - Queue middleware for account-state validation.
- `wave/src/Jobs/CreateActivityLog.php` - Added execution-time account-state guard and middleware registration.
- `wave/src/Http/Middleware/TokenMiddleware.php` - Added account-state guard for token-authenticated requests.
- `tests/Feature/AccountSuspensionMiddlewareTest.php` - Added coverage for web, API, token, queue, and channel gates.

## Decisions Made
- Centralize blocked-account behavior in shared middleware and reuse it across entry points.
- Use allowlist routing for restricted users and strict hard-block for suspended users.
- Apply account-state re-checks in queue middleware to cover post-dispatch state changes.

## Deviations from Plan

None - plan executed as specified.

## Issues Encountered

None.

## Next Phase Readiness
- Ready for `02-03-PLAN.md` in Account Suspension phase.
- No known blockers.

