# Phase 01 Tenant Data Scoping Test Scenarios

Date: 2026-03-05
Scope: Browser first validation of implemented tenant scoping work using Laravel Boost and Playwright

## Scenario Checklist

- [x] S01 Reset and prepare deterministic tenant browser data
  - Create a dedicated test user with known credentials.
  - Create two organizations and membership for that user.
  - Seed tenant specific activity logs and API keys for both organizations.

- [x] S02 Laravel Boost runtime sanity checks
  - Confirm routes list resolves.
  - Confirm `TenantContext` resolves from container.
  - Confirm tenant scope is registered on `ActivityLog` and `ApiKey`.

- [x] S03 Playwright public page checks
  - Open home page and login page.
  - Confirm page load and no immediate fatal browser issues.

- [x] S04 Playwright authenticated flow for tenant A
  - Login with prepared user.
  - Open settings activity page.
  - Confirm tenant A activity exists and tenant B activity is not shown.

- [x] S05 Playwright tenant switch verification for tenant B
  - Switch `current_organization_id` to tenant B via Laravel Boost.
  - Refresh settings activity page.
  - Confirm tenant B activity exists and tenant A activity is not shown.

- [x] S06 Playwright API key visibility check after tenant switch
  - Open settings API page while tenant B is active.
  - Confirm tenant B API key label is visible.
  - Confirm tenant A API key label is not visible.

- [x] S07 Browser console and app log review
  - Check browser console output for severe errors during scenarios.
  - Check backend last error for new tenant feature related exceptions.

- [x] S08 Playwright profile update flow
  - Open settings profile page as test user.
  - Update profile fields and save.
  - Reload profile page and verify values persisted.
  - Verify profile update activity log entry exists.

## Execution Log

- Checklist reset per user request to run browser first validation with Playwright.
- S01 PASS
  - Prepared user `tenant.playwright@example.com` with password `Password123!`.
  - Created org A and org B test records and owner membership.
  - Confirmed tenant specific data exists: one log and one API key per tenant.
- S02 PASS
  - Laravel Boost route listing resolved and includes Folio routes `settings.activity` and `settings.api`.
  - `TenantContext` resolves as singleton in container.
  - `TenantScope` is registered on `ActivityLog` and `ApiKey`.
- S03 PASS
  - Opened `http://127.0.0.1:8000/` and `http://127.0.0.1:8000/login` in Playwright.
  - Home and login pages load with expected titles and UI structure.
  - Observed one non blocking static asset console error for auth background image.
- S04 PASS
  - Logged in as `tenant.playwright@example.com` via Playwright.
  - Opened `http://127.0.0.1:8000/settings/activity` with org A active.
  - Found `Tenant A Browser Log` and confirmed `Tenant B Browser Log` is not visible.
- S05 PASS
  - Switched `current_organization_id` to org B using Laravel Boost tinker.
  - Reloaded `settings/activity` in the same Playwright session.
  - Found `Tenant B Browser Log` and confirmed `Tenant A Browser Log` is not visible.
- S06 PASS
  - Opened `http://127.0.0.1:8000/settings/api` while org B is active.
  - Confirmed `TenantB Browser Key` is visible.
  - Confirmed `TenantA Browser Key` is not visible.
- S07 PASS
  - Playwright console error level output is clean for the tested authenticated pages.
  - Laravel log tools show no new tenant scoping exceptions generated during this run.
  - `last-error` points to an older historical console command issue from 2026-02-14.
- S08 PASS
  - Updated profile fields in Playwright and saved successfully.
  - Success toast displayed: `Successfully saved your profile settings`.
  - Reload confirmed persisted values for name, about, and occupation fields.
  - Laravel Boost verified `profile_updated` activity log exists for this user in org B.
