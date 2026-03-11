---
phase: 05-feature-flags
plan: 01
subsystem: infra
tags: [pennant, feature-flags, enums, eloquent]

# Dependency graph
requires:
  - phase: 01-tenant-scoping
    provides: TenantContext singleton for organization-scoped resolution
provides:
  - Laravel Pennant installed with database driver
  - Tenant-scoped default Feature resolution via TenantContext
  - FeatureFlagType enum (PlanGated, Rollout, KillSwitch)
  - FeatureDefinition model and migration for admin metadata
  - Three example feature classes (AiReports, NewEditor, MaintenanceMode)
  - FeatureDefinitionSeeder with sample data
affects: [05-feature-flags]

# Tech tracking
tech-stack:
  added: [laravel/pennant v1.21]
  patterns: [class-based Pennant features with #[Name] attribute, tenant-scoped default resolution]

key-files:
  created:
    - config/pennant.php
    - database/migrations/2022_11_01_000001_create_features_table.php
    - database/migrations/2026_03_11_232832_create_feature_definitions_table.php
    - app/Enums/FeatureFlagType.php
    - app/Models/FeatureDefinition.php
    - app/Features/AiReports.php
    - app/Features/NewEditor.php
    - app/Features/MaintenanceMode.php
    - database/seeders/FeatureDefinitionSeeder.php
  modified:
    - composer.json
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Feature::resolveScopeUsing resolves to Organization via TenantContext, falling back to auth user when no org context"
  - "Kill switches use Feature::for(null)->active() for global scope, not tenant scope"
  - "Feature classes use #[Name] attribute (Laravel 12 convention) not $name property"

patterns-established:
  - "Plan-gated features check scope->activeSubscription()->plan->features array"
  - "Gradual rollouts use Lottery::odds() -- Pennant caches result per scope in DB"
  - "Kill switches resolve to false by default, toggled via Feature::for(null)->activate()"
  - "FeatureDefinition stores admin metadata separately from Pennant's resolved values"

issues-created: []

# Metrics
duration: 8min
completed: 2026-03-12
---

# Plan 05-01: Pennant Infrastructure Summary

**Laravel Pennant installed with DB driver, tenant-scoped resolution, FeatureFlagType enum, FeatureDefinition model, and 3 example feature classes (plan-gated, rollout, kill switch)**

## Performance

- **Duration:** 8 min
- **Started:** 2026-03-12
- **Completed:** 2026-03-12
- **Tasks:** 2
- **Files modified:** 11

## Accomplishments
- Installed Laravel Pennant v1.21 with database driver and published config/migration
- Configured default scope resolution to resolve to Organization via TenantContext, falling back to authenticated user
- Created FeatureFlagType enum with PlanGated, Rollout, KillSwitch cases and label/color methods
- Created FeatureDefinition model with migration for admin-facing metadata (name, type, description, is_active, last_changed_by)
- Created 3 example feature classes demonstrating all three flag patterns
- Created idempotent seeder with 3 sample definitions

## Task Commits

Each task was committed atomically:

1. **Task 1: Install Pennant and configure tenant-scoped default resolution** - `ad410f9` (feat)
2. **Task 2: Create feature definitions infrastructure and example feature classes** - `bfc00c4` (feat)

## Files Created/Modified
- `composer.json` - Added laravel/pennant dependency
- `config/pennant.php` - Pennant config with database driver
- `database/migrations/2022_11_01_000001_create_features_table.php` - Pennant's features table
- `database/migrations/2026_03_11_232832_create_feature_definitions_table.php` - Admin metadata table
- `app/Providers/AppServiceProvider.php` - Feature::resolveScopeUsing with TenantContext
- `app/Enums/FeatureFlagType.php` - PlanGated, Rollout, KillSwitch enum
- `app/Models/FeatureDefinition.php` - Admin metadata model
- `app/Features/AiReports.php` - Plan-gated feature (checks plan features array)
- `app/Features/NewEditor.php` - Gradual rollout feature (10% via Lottery)
- `app/Features/MaintenanceMode.php` - Kill switch feature (default off)
- `database/seeders/FeatureDefinitionSeeder.php` - Sample definitions

## Decisions Made
- Feature::resolveScopeUsing resolves to Organization via TenantContext, falling back to auth user when no org context exists
- Kill switches use `Feature::for(null)->active()` for global scope, not tenant scope
- Feature classes use `#[Name]` attribute (Laravel 12 convention) not `$name` property
- FeatureDefinition stores admin-facing metadata separately from Pennant's resolved values table
- No changes to existing @canUseFeature / HasPlanFeatures system -- Pennant is additive

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## Next Phase Readiness
- Pennant infrastructure complete and ready for admin UI (Plan 02)
- All 3 feature flag patterns demonstrated with working examples
- FeatureDefinition model ready for Filament resource in Plan 02
- Existing tests unaffected (46 pre-existing failures, 0 new failures)

---
*Phase: 05-feature-flags*
*Completed: 2026-03-12*
