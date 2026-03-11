---
phase: 02-account-suspension
plan: 03
subsystem: admin
tags: [filament, admin-panel, suspension, account-lifecycle, relation-manager]

# Dependency graph
requires:
  - phase: 02-01
    provides: account status enum, status columns on users/organizations, history migration
  - phase: 02-02
    provides: middleware enforcement using model-level status helpers
provides:
  - AccountStatusHistory Eloquent model with enum casts and polymorphic relationships
  - recordStatusTransition method on User and Organization models
  - suspend/restrict/unsuspend table actions on UserResource and OrganizationResource
  - status badge columns and filters on both admin list views
  - StatusHistoryRelationManager on both User and Organization edit pages
  - Pest tests covering all admin suspension controls
affects:
  - account-suspension

# Tech tracking
tech-stack:
  added: []
  patterns:
    - polymorphic history model with enum casts and default ordering scope
    - shared recordStatusTransition method on identity models
    - Filament table actions with modal forms for admin status transitions
    - read-only relation managers for audit trail display

key-files:
  created:
    - app/Models/AccountStatusHistory.php
    - app/Filament/Resources/Users/RelationManagers/StatusHistoryRelationManager.php
    - app/Filament/Resources/Organizations/RelationManagers/StatusHistoryRelationManager.php
    - tests/Feature/AdminSuspensionControlsTest.php
  modified:
    - app/Models/User.php
    - app/Models/Organization.php
    - app/Filament/Resources/Users/UserResource.php
    - app/Filament/Resources/Organizations/OrganizationResource.php

key-decisions:
  - "Status transitions go through table actions only, not form edits, to enforce history recording."
  - "recordStatusTransition method lives directly on User and Organization models rather than a trait."
  - "Relation managers tested directly as Livewire components due to Filament v4 lazy loading of tabs."

patterns-established:
  - Filament table actions with modal forms for admin-initiated status changes
  - Read-only relation managers for chronological audit trail display
  - Direct relation manager testing with ownerRecord and pageClass parameters

issues-created: []

# Metrics
duration: 12min
completed: 2026-03-11
---

# Phase 02-03: Admin Suspension Controls in Filament Panel

**Admins can now suspend, restrict, and unsuspend users and organizations from Filament with recorded reasons and full status history audit trail.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-03-11T18:45:00Z
- **Completed:** 2026-03-11T18:57:00Z
- **Tasks:** 5
- **Files modified:** 8

## Accomplishments
- Created AccountStatusHistory Eloquent model with enum casts, morphTo/belongsTo relationships, and forEntity scope.
- Added recordStatusTransition method to both User and Organization models for atomic status changes with history recording.
- Added status badge columns, status filters, and suspend/unsuspend table actions with reason modals to both UserResource and OrganizationResource.
- Created read-only StatusHistoryRelationManager for both resources showing transition details on edit pages.
- All 13 Pest tests pass covering actions, visibility, filters, history recording, and relation manager display.

## Task Commits

Each task was committed atomically:

1. **Task 1: Create AccountStatusHistory model and wire morphMany relationships** - `565b983` (feat)
2. **Task 2: Add status columns, filters, and suspend/unsuspend actions to UserResource** - `1268c93` (feat)
3. **Task 3: Add status columns, filters, and suspend/unsuspend actions to OrganizationResource** - `b1ec19b` (feat)
4. **Task 4: Add StatusHistory relation managers to User and Organization edit pages** - `412ec93` (feat)
5. **Task 5: Write Pest tests for admin suspension controls** - `afc0f56` (test)

## Files Created/Modified
- `app/Models/AccountStatusHistory.php` - Eloquent model with enum casts, morphTo, belongsTo, forEntity scope, and default descending order.
- `app/Models/User.php` - Added statusHistories morphMany and recordStatusTransition method.
- `app/Models/Organization.php` - Added statusHistories morphMany and recordStatusTransition method.
- `app/Filament/Resources/Users/UserResource.php` - Status badge column, status filter, suspend/unsuspend actions, read-only status section on form.
- `app/Filament/Resources/Organizations/OrganizationResource.php` - Status badge column, status filter, suspend/unsuspend actions, read-only status section on form.
- `app/Filament/Resources/Users/RelationManagers/StatusHistoryRelationManager.php` - Read-only relation manager for user status history.
- `app/Filament/Resources/Organizations/RelationManagers/StatusHistoryRelationManager.php` - Read-only relation manager for organization status history.
- `tests/Feature/AdminSuspensionControlsTest.php` - 13 Pest tests covering all admin suspension control functionality.

## Decisions Made
- Status changes route through table actions exclusively to enforce audit trail recording via recordStatusTransition.
- recordStatusTransition lives on the models directly rather than in a trait since the duplication is minimal.
- Relation manager tests use direct Livewire component testing rather than assertSeeLivewire on the edit page, accommodating Filament v4 lazy tab loading.

## Deviations from Plan

None - plan executed as specified.

## Issues Encountered

None.

## Next Phase Readiness
- Phase 02 Account Suspension is now complete across all three plans.
- Ready for next phase per the roadmap.

---
*Phase: 02-account-suspension*
*Completed: 2026-03-11*
