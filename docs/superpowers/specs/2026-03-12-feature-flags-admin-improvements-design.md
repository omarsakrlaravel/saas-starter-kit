# Feature Flags Admin Improvements

## Problem

The current Feature Flags admin page is a flat list with no way to:
- See which organizations have what features (per-org state visibility)
- Modify per-org overrides efficiently (only one-at-a-time modal)
- Control rollout percentages (hardcoded in feature classes)
- Drill into a feature to see all its overrides

## Design

### A) Feature Definition Resource

Replace the custom `FeatureFlagsPage` with a proper `FeatureDefinitionResource`.

**List page:**
- Kill switch section at top via custom header widget
- Table of non-kill-switch features
- Columns: name, type (badge), description, is_active (toggle), rollout % (Rollout type only), last changed by, updated at
- Row click navigates to View page

**View page (per feature):**
- Top section: feature metadata infolist (name, type, description, active status)
- For Rollout type: editable rollout percentage field with inline edit action
- Organization Overrides table: every org with an explicit Pennant override
  - Columns: org name, state (active/inactive), set at
  - Actions: toggle state, remove override (reverts to default resolution)
  - Header action: "Add Override" to set state for a new org

**Database change:**
- Add `rollout_percentage` (nullable integer) to `feature_definitions`
- Rollout feature classes read percentage from `FeatureDefinition` instead of hardcoded `Lottery`

### B) Organization Features Tab

On the existing Organization Resource, add a "Features" relation manager / custom table.

- Queries Pennant's `features` table filtered by `scope = "App\Models\Organization|{id}"`
- Columns: feature name, type (badge from feature_definitions), state (active/inactive badge), set at
- Actions per row: toggle state, remove override
- Header action: "Set Feature" -- pick a feature + active/inactive
- Only shows explicit overrides (note: "Features without overrides resolve using their default rules")

## Migration

- Delete `FeatureFlagsPage` and its Blade view after Resource is complete
- Move kill switch action logic into the Resource's list page
- Existing tests updated to target new Resource classes

## Files Affected

- `app/Filament/Resources/FeatureDefinitionResource.php` (new)
- `app/Filament/Resources/FeatureDefinitionResource/Pages/ListFeatureDefinitions.php` (new)
- `app/Filament/Resources/FeatureDefinitionResource/Pages/ViewFeatureDefinition.php` (new)
- `app/Filament/Resources/OrganizationResource.php` (add relation manager)
- `app/Filament/Resources/OrganizationResource/RelationManagers/FeaturesRelationManager.php` (new)
- `database/migrations/xxxx_add_rollout_percentage_to_feature_definitions.php` (new)
- `app/Models/FeatureDefinition.php` (add rollout_percentage to fillable/casts)
- `app/Features/NewEditor.php` (read rollout % from DB)
- `app/Filament/Pages/FeatureFlagsPage.php` (delete)
- `resources/views/filament/pages/feature-flags.blade.php` (delete)
- Tests updated/added
