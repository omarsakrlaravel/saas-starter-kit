---
phase: 03-private-file-urls
plan: 02
subsystem: api
tags: [file-service, signed-urls, download-controller, storage, authorization]

# Dependency graph
requires:
  - phase: 03-private-file-urls
    plan: 01
    provides: File model, FileAccessLevel enum, FilePolicy, FileFactory
provides:
  - FileService with store(), storeFromContent(), signedUrl(), delete(), exists() methods
  - FileDownloadController with signature validation and policy authorization
  - Named route `files.download` with auth + signed middleware
affects: [03-03-PLAN, 03-04-PLAN]

# Tech tracking
tech-stack:
  added: []
  patterns: [signed-url file serving, facade-based service class]

key-files:
  created:
    - wave/src/Services/FileService.php
    - wave/src/Http/Controllers/FileDownloadController.php
    - tests/Feature/FileServiceTest.php
  modified:
    - wave/routes/web.php

key-decisions:
  - "Organization type hint uses App\\Models\\Organization (not Wave\\Organization which does not exist)"
  - "FileDownloadController uses Gate::authorize() since base Controller lacks AuthorizesRequests trait"

patterns-established:
  - "File operations go through FileService, never direct Storage calls from consuming code"
  - "Files served exclusively via temporary signed URLs with policy authorization"

issues-created: []

# Metrics
duration: 8min
completed: 2026-03-11
---

# Plan 03-02: FileService and Download Controller Summary

**FileService for upload/content storage with signed URL generation, plus FileDownloadController serving authorized downloads via validated signatures -- 19 tests passing**

## Performance

- **Duration:** 8 min
- **Tasks:** 2 completed
- **Files created:** 3
- **Files modified:** 1

## Accomplishments
- FileService with 5 methods: store() for uploaded files, storeFromContent() for raw content, signedUrl() for temporary signed download URLs, delete() for disk+DB cleanup, exists() for disk checks
- FileDownloadController (invokable) resolves files by UUID, authorizes via Gate/FilePolicy, validates disk existence, and serves download response
- Route `files.download` registered with `auth` and `signed` middleware outside the auth group
- 19 comprehensive tests covering all service methods, controller authorization, signature validation, org member access, and unauthenticated redirect

## Task Commits

Each task was committed atomically:

1. **Task 1: Create FileService with store, signedUrl, and delete methods** - `3c0627e` (feat)
2. **Task 2: Create FileDownloadController and register signed download route** - `08e146f` (feat)

## Files Created/Modified
- `wave/src/Services/FileService.php` - Central file operations service with 5 methods
- `wave/src/Http/Controllers/FileDownloadController.php` - Invokable download controller with UUID lookup and Gate authorization
- `wave/routes/web.php` - Added `files.download` route with auth+signed middleware
- `tests/Feature/FileServiceTest.php` - 19 tests for FileService and FileDownloadController

## Decisions Made
- Used `App\Models\Organization` instead of `Wave\Organization` (which does not exist) for the organization type hint in FileService
- Used `Gate::authorize()` in FileDownloadController since the base `App\Http\Controllers\Controller` does not include the `AuthorizesRequests` trait

## Deviations from Plan

### Auto-fixed Issues

**1. Organization namespace mismatch**
- **Found during:** Task 1 (FileService creation) -- caught by tests
- **Issue:** Plan referenced `Wave\Organization` but the Organization model lives at `App\Models\Organization`
- **Fix:** Changed the import from `Wave\Organization` to `App\Models\Organization`
- **Verification:** All 19 tests pass
- **Committed in:** `08e146f` (Task 2 commit, which included the Pint-ordered fix)

**2. Gate::authorize instead of $this->authorize**
- **Found during:** Task 2 (FileDownloadController creation)
- **Issue:** Plan suggested `$this->authorize()` but the base Controller class is empty (no `AuthorizesRequests` trait)
- **Fix:** Used `Gate::authorize('download', $fileRecord)` directly
- **Verification:** Download authorization tests pass (uploader allowed, unauthorized denied, org member allowed, app_public allowed)
- **Committed in:** `08e146f` (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (1 namespace, 1 authorization approach)
**Impact on plan:** Both fixes are necessary adaptations to the actual codebase. No scope creep.

## Issues Encountered
None

## Next Phase Readiness
- FileService and download infrastructure complete, ready for avatar migration (plan 03-03)
- All existing File model tests (18) continue to pass alongside the new 19 tests

---
*Plan: 03-02*
*Completed: 2026-03-11*
