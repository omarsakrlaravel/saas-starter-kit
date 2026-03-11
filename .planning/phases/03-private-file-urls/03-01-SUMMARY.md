---
phase: 03-private-file-urls
plan: 01
subsystem: database
tags: [eloquent, model, enum, factory, policy, tenant-scoping]

# Dependency graph
requires:
  - phase: 01-tenant-data-scoping
    provides: BelongsToTenant trait, TenantScope, TenantContext
provides:
  - File model with BelongsToTenant, uuid generation, relationships, scopes, helpers
  - FileAccessLevel enum (Private, AppPublic)
  - files migration with columns, foreign keys, and composite indexes
  - FileFactory with 4 states (appPublic, personal, forUser, forOrganization)
  - FilePolicy with download authorization (app_public, org membership, uploader ownership)
affects: [03-02-PLAN, 03-03-PLAN, 03-04-PLAN]

# Tech tracking
tech-stack:
  added: []
  patterns: [enum-backed access control, policy-per-wave-model registration via Gate::policy]

key-files:
  created:
    - app/Enums/FileAccessLevel.php
    - wave/src/File.php
    - database/migrations/2026_03_12_000001_create_files_table.php
    - database/factories/FileFactory.php
    - app/Policies/FilePolicy.php
    - tests/Feature/FileModelTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "FileFactory defaults organization_id to null (personal file) since Organization lacks HasFactory trait"
  - "FilePolicy registered explicitly via Gate::policy in AppServiceProvider since Wave\\File auto-discovery would fail"

patterns-established:
  - "Wave model policy registration: Gate::policy(WaveModel::class, Policy::class) in AppServiceProvider boot()"
  - "Factory for Wave models: set protected $model, override newFactory() on model to return factory"

issues-created: []

# Metrics
duration: 8min
completed: 2026-03-11
---

# Plan 03-01: File Model Data Layer Summary

**File model with tenant-scoped access control, FileAccessLevel enum, migration, factory, and authorization policy -- 18 tests passing**

## Performance

- **Duration:** 8 min
- **Tasks:** 2 completed
- **Files created:** 6
- **Files modified:** 1

## Accomplishments
- File model with BelongsToTenant trait, auto-generated UUIDs, uploadedBy/fileable relationships, forUser/accessibleBy scopes, and isPrivate/isAppPublic/isOwnedBy helpers
- FileAccessLevel enum with Private and AppPublic cases following AccountStatus convention
- Migration with all columns, foreign keys (nullOnDelete for org, cascadeOnDelete for user), and composite indexes (fileable morph, disk+path uniqueness)
- FileFactory with 4 states and FilePolicy with granular authorization (app_public open, org membership, uploader ownership)
- 18 comprehensive tests covering model creation, casting, relationships, scopes, factory states, and all policy scenarios

## Task Commits

Each task was committed atomically:

1. **Task 1: Create FileAccessLevel enum, File model, and migration** - `cd3eaea` (feat)
2. **Task 2: Create FileFactory, FilePolicy, and test suite** - `95337af` (feat)

## Files Created/Modified
- `app/Enums/FileAccessLevel.php` - Access level enum with Private and AppPublic cases
- `wave/src/File.php` - File model with BelongsToTenant, relationships, scopes, helpers
- `database/migrations/2026_03_12_000001_create_files_table.php` - Files table with all columns and indexes
- `database/factories/FileFactory.php` - Factory with 4 states for testing
- `app/Policies/FilePolicy.php` - Authorization policy for file access
- `app/Providers/AppServiceProvider.php` - Added Gate::policy registration for Wave\File
- `tests/Feature/FileModelTest.php` - 18 tests covering model and policy

## Decisions Made
- FileFactory defaults `organization_id` to `null` instead of `Organization::factory()` because Organization model does not use HasFactory trait. The `forOrganization()` state handles org assignment explicitly.
- FilePolicy registered via `Gate::policy()` in AppServiceProvider since Laravel auto-discovery expects `App\Policies\{Model}Policy` but the model is `Wave\File`, which would not be found.

## Deviations from Plan

### Auto-fixed Issues

**1. Organization::factory() unavailable in FileFactory**
- **Found during:** Task 2 (FileFactory creation)
- **Issue:** Plan specified `Organization::factory()` for default `organization_id` but Organization model lacks HasFactory trait
- **Fix:** Defaulted `organization_id` to `null` (personal file), with `forOrganization()` state for explicit org assignment
- **Verification:** Factory make/create works, tests pass
- **Committed in:** 95337af (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (factory default adjustment)
**Impact on plan:** Minimal -- factory still provides all 4 planned states, just with a different default.

## Issues Encountered
None

## Next Phase Readiness
- File model and data layer complete, ready for FileService (plan 03-02)
- Factory and policy in place for integration testing in subsequent plans

---
*Plan: 03-01*
*Completed: 2026-03-11*
