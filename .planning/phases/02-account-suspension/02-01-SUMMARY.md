---
phase: 02-account-suspension
plan: 01
subsystem: database
tags: [account-status, account-lifecycle, migrations, models, enums]
requires:
  - phase: 01
    provides: baseline tenant context and model conventions used by later middleware
provides:
  - account status enum shared by user and organization
  - account status migration columns for users and organizations
  - account status history table for auditable transitions
  - status helper and scope APIs on user/organization models
  - model-level account state tests
affects:
  - 02-02
tech-stack:
  added: []
  patterns:
    - enum-backed status columns on core identity models
    - polymorphic status history table with resilient applied_by constraint
key-files:
  created:
    - .planning/phases/02-account-suspension/02-01-SUMMARY.md
    - database/migrations/2026_03_05_000001_add_account_status_to_users_and_organizations.php
    - database/migrations/2026_03_05_000002_create_account_status_histories_table.php
    - app/Enums/AccountStatus.php
    - tests/Feature/AccountStatusModelTest.php
  modified:
    - app/Models/User.php
    - app/Models/Organization.php
key-decisions:
  - used string-based status values with `AccountStatus` enum casting
  - preserved legacy `active` columns and added indexes for reporting and filtering
  - kept history relation resilient to target record deletion by avoiding hard `suspendable` foreign keys
patterns-established:
  - model-level status helper methods for cross-entity checks
  - explicit `scopeWithStatus` and `scopeActiveOrRestricted` query helpers
  - shared enum for casted account state across identity models
issues-created: []

duration: 8min
completed: 2026-03-05
---

# Phase 02-01: Account State and Suspension History Baseline

**Added a shared `AccountStatus` enum, persisted per-account status fields on users and organizations, and added auditable status history support with model-level status behavior helpers for deterministic middleware decisions.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-03-05T00:00:00Z
- **Completed:** 2026-03-05T00:08:00Z
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments
- Added `App\Enums\AccountStatus` with shared state checks and admin labels.
- Added `status`, `status_reason`, and `status_expires_at` columns for users and organizations with indexes.
- Added `account_status_histories` table for reasoned status transitions and resilient auditor metadata.
- Added account status helpers and scopes to `User` and `Organization`, with coverage tests in `AccountStatusModelTest.php`.

## Task Commits

1. **Task 1: Create shared account status enum** - `3991e28`
2. **Task 2: Add account status columns and history table** - `d4d4391`
3. **Task 3: Add model-level status behavior and tests** - `732c172`

**Plan metadata:** `d4d4391`

## Files Created/Modified

- `app/Enums/AccountStatus.php` - Shared enum with blocking helpers and admin labels.
- `database/migrations/2026_03_05_000001_add_account_status_to_users_and_organizations.php` - Status fields and indexes for users and organizations.
- `database/migrations/2026_03_05_000002_create_account_status_histories_table.php` - Polymorphic history table for status transitions.
- `app/Models/User.php` - Added status cast/fillable, helper methods, and status scopes.
- `app/Models/Organization.php` - Added status cast/fillable, helper methods, and status scopes.
- `tests/Feature/AccountStatusModelTest.php` - Cast and helper coverage for user and organization models.

## Decisions Made
- Kept `users.active` untouched and added independent status lifecycle fields.
- Chose nullable `status_expires_at`/`status_reason` for future suspension automation and admin messaging.
- Used enum casting for status columns to keep behavior deterministic in code and middleware checks.

## Deviations from Plan

None - plan executed as specified.

## Issues Encountered

None.

## Next Phase Readiness

Phase 02-02 middleware planning can now use `AccountStatus` and model helpers (`isRestricted`, `isSuspended`, `organizationIsBlocked`, `isBlockedFromSession`, `scopeWithStatus`, `scopeActiveOrRestricted`) without extra query logic.

---
*Phase: 02-account-suspension*
*Completed: 2026-03-05*
