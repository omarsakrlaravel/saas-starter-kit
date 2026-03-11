---
phase: 03-private-file-urls
plan: 03
subsystem: ui
tags: [avatar, file-service, signed-urls, filament, volt, morphOne]

# Dependency graph
requires:
  - phase: 03-private-file-urls
    plan: 01
    provides: File model, FileAccessLevel enum, FilePolicy
  - phase: 03-private-file-urls
    plan: 02
    provides: FileService with storeFromContent(), signedUrl(), delete() methods
provides:
  - Avatar upload wired through FileService on private disk
  - User::avatar() returns signed URLs or default fallback
  - avatarFile() morphOne relationship on User model
  - Filament UserResource and MemberResource upload/display via FileService
  - Organizations MembersRelationManager avatar display via signed URLs
affects: [03-04-PLAN]

# Tech tracking
tech-stack:
  added: []
  patterns: [morphOne fileable for single-file associations, saveUploadedFileUsing for Filament-FileService integration]

key-files:
  created: []
  modified:
    - resources/themes/anchor/pages/settings/profile.blade.php
    - wave/src/User.php
    - app/Filament/Resources/Users/UserResource.php
    - app/Filament/Resources/Members/MemberResource.php
    - app/Filament/Resources/Organizations/RelationManagers/MembersRelationManager.php

key-decisions:
  - "User::avatar() handles only two cases: avatarFile exists (signed URL) or default -- no legacy path handling needed"
  - "Filament FileUpload uses saveUploadedFileUsing/getUploadedFileUsing callbacks to integrate with FileService instead of direct disk storage"
  - "MembersRelationManager also updated for consistent avatar display across all admin surfaces"

patterns-established:
  - "morphOne fileable pattern: model has morphOne(File::class, 'fileable') for single-file associations like avatars"
  - "Filament FileUpload integration: saveUploadedFileUsing stores via FileService, returns UUID; getUploadedFileUsing resolves UUID to signed URL preview"

issues-created: []

# Metrics
duration: 8min
completed: 2026-03-11
---

# Plan 03-03: Avatar System Wired Through Private File Infrastructure

**Avatar upload and display fully routed through FileService with signed URLs -- no user content on the public disk**

## Performance

- **Duration:** 8 min
- **Tasks:** 2 completed
- **Files modified:** 5

## Accomplishments
- Avatar upload in settings profile now stores images via FileService::storeFromContent() on the local (private) disk with app_public access level
- User::avatar() returns signed download URLs when an avatar File record exists, or the default image URL otherwise
- Filament admin panel (UserResource, MemberResource, MembersRelationManager) fully integrated: upload creates File records via saveUploadedFileUsing, display resolves signed URLs via getUploadedFileUsing and getStateUsing
- Previous avatar File records are cleaned up (deleted) before storing new ones
- Sidebar avatar display works through getFilamentAvatarUrl() which delegates to avatar()

## Task Commits

Each task was committed atomically:

1. **Task 1: Wire avatar upload through FileService** - `ee94220` (feat)
2. **Task 2: Wire avatar display through signed URLs** - `bcc9d49` (feat)

## Files Created/Modified
- `resources/themes/anchor/pages/settings/profile.blade.php` - saveNewUserAvatar() now uses FileService::storeFromContent() instead of Storage::disk('public')->put()
- `wave/src/User.php` - Added avatarFile() morphOne relationship, replaced avatar() with signed URL logic, added scopeWithAvatarFile, removed unused Storage import
- `app/Filament/Resources/Users/UserResource.php` - FileUpload uses saveUploadedFileUsing/getUploadedFileUsing for FileService integration, ImageColumn uses getStateUsing for signed URLs
- `app/Filament/Resources/Members/MemberResource.php` - Same FileUpload and ImageColumn changes as UserResource
- `app/Filament/Resources/Organizations/RelationManagers/MembersRelationManager.php` - ImageColumn uses getStateUsing for signed URLs

## Decisions Made
- User::avatar() handles only two cases (avatarFile with signed URL or default) -- no legacy path branching since this is a new project
- Filament FileUpload integrated via saveUploadedFileUsing (creates UploadedFile from TemporaryUploadedFile, stores via FileService, returns UUID) and getUploadedFileUsing (resolves File record to signed URL array for preview)
- Also updated Organizations MembersRelationManager for consistent avatar display, even though not listed in the plan

## Deviations from Plan

### Auto-fixed Issues

**1. MembersRelationManager also needed avatar update**
- **Found during:** Task 2 (avatar display wiring)
- **Issue:** Organizations MembersRelationManager also displays user avatars via ImageColumn but was not listed in the plan
- **Fix:** Applied the same getStateUsing pattern for consistent signed URL display
- **Files modified:** app/Filament/Resources/Organizations/RelationManagers/MembersRelationManager.php
- **Verification:** ImageColumn now resolves avatar through User::avatar() like all other admin surfaces
- **Committed in:** bcc9d49 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (missing file in plan)
**Impact on plan:** Necessary for consistency -- all admin avatar displays now use the same signed URL path.

## Issues Encountered
None

## Next Phase Readiness
- Avatar system fully wired through private file infrastructure
- Phase 03 complete (all 4 plans done) -- ready for phase transition
- All file-related tests (37) pass with SQLite in-memory database

---
*Plan: 03-03*
*Completed: 2026-03-11*
