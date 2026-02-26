# Codebase Concerns

**Analysis Date:** 2026-02-26

## Tech Debt

**Massive Stripe Webhook Handler:**
- Issue: Single class handles 7+ webhook event types with complex nested logic (795 lines)
- File: `app/Listeners/HandleStripeWebhook.php`
- Why: Incremental feature additions without refactoring
- Impact: Hard to test in isolation, high bug risk, violates SRP
- Fix approach: Split into `HandleCheckoutSession`, `HandleInvoiceEvents`, `HandleChargeEvents`, `HandleCouponEvents`

**Large User Model with Mixed Concerns:**
- Issue: Handles subscriptions, invoices, roles, permissions, profiles, caching, billing context, feature limits, and JWT (797 lines)
- File: `wave/src/User.php`
- Why: Central model accumulated responsibilities
- Impact: High coupling, hard to test, cache invalidation scattered
- Fix approach: Extract `SubscriptionManager`, `InvoiceManager`, `BillingContextResolver`

**Migration Data Transformation Without Transaction:**
- Issue: Bulk data transformation from old vendor columns to Stripe columns uses raw DB queries without transaction wrapping
- File: `database/migrations/2026_02_14_000001_add_cashier_stripe_columns.php`
- Why: Migration written during billing provider switch
- Impact: Partial failure during migration could corrupt data
- Fix approach: Wrap subscription update loop in DB transaction

## Known Bugs

**Commented-Out Debug Code:**
- Symptoms: `// dd($data['fields']);` left in production code
- Trigger: Accidentally uncommenting breaks production
- File: `app/Filament/Resources/Forms/Pages/EditForms.php` (lines 28, 36)
- Workaround: None needed unless uncommented
- Root cause: Debug code not removed during development

## Security Considerations

**Filament Admin Bypasses Stripe Seat Billing:**
- Risk: Adding/removing organization members via admin panel bypasses Stripe seat billing
- File: `app/Filament/Resources/Organizations/RelationManagers/MembersRelationManager.php`
- Current mitigation: Documented in code comments
- Recommendations: Enforce seat limit checks in relation manager; sync member changes to Stripe

**Silent Cache Clearing Failures:**
- Risk: Cache clearing fails silently with empty catch block, leaving stale subscription data
- File: `wave/src/User.php` (lines 736-738)
- Current mitigation: None (silently swallowed)
- Recommendations: Log cache clearing failures; emit event on failure

## Performance Bottlenecks

**N+1 Query in Invoice Mapping:**
- Problem: `billingInvoices()` maps over invoices calling `asStripeInvoice()` per invoice
- File: `wave/src/User.php` (lines 425-462)
- Cause: Individual Stripe API call per invoice
- Improvement path: Batch fetch from Stripe; use eager loading for line items

**Dashboard Widget Full Table Scans:**
- Problem: `DB::raw()` grouping by `YEAR()`, `MONTH()`, `DATE()` without indexes
- Files: `app/Filament/Widgets/RevenueChartWidget.php`, `StatsOverviewWidget.php`
- Cause: No composite indexes on timestamp columns
- Improvement path: Add indexes on `paid_at`, `created_at`; consider materialized views

**Missing Indexes on Invoice Table:**
- Problem: No index on `subscription_id` and `paid_at` columns
- File: `database/migrations/2026_02_15_000001_create_invoices_table.php`
- Cause: Overlooked during migration creation
- Improvement path: Add migration with indexes for frequently queried columns

## Fragile Areas

**Webhook Event Deduplication:**
- Why fragile: Only checkout sessions use cache-based dedup; other events can be processed multiple times on Stripe retries
- File: `app/Listeners/HandleStripeWebhook.php` (lines 78-82)
- Common failures: Duplicate subscriptions, invoices, or transactions if Stripe retries
- Safe modification: Implement idempotency key on all webhook handlers using Stripe event ID
- Test coverage: No integration tests for webhook flow

**Cache Tag Fallback:**
- Why fragile: If cache driver doesn't support tags, falls back to manual key enumeration with N loops through plan names
- File: `wave/src/User.php` (lines 707-751)
- Common failures: Inconsistent behavior between cache drivers; stale data
- Safe modification: Document cache driver requirements; warn on startup
- Test coverage: Cache operations not tested

**Pending Plan Change Race Condition:**
- Why fragile: Pending plan stored in subscription; concurrent `ApplyPendingPlanChanges` commands could apply twice
- Files: `wave/src/Subscription.php`, `wave/src/Console/Commands/ApplyPendingPlanChanges.php`
- Common failures: Plan change applied twice; subscription in inconsistent state
- Safe modification: Add database-level lock or unique constraint

## Scaling Limits

**Webhook Processing:**
- Current capacity: No rate limiting; all webhooks processed immediately
- Limit: Resource exhaustion if Stripe sends burst of events
- Symptoms at limit: Database locks, cache thrashing, queue backup
- Scaling path: Queue webhook processing with backpressure; add rate limiting

## Dependencies at Risk

**Cashier Billable Override:**
- Risk: User overrides Cashier's `onTrial()` method with custom implementation
- File: `wave/src/User.php` (lines 30-32)
- Impact: Cashier version updates may change `onTrial()` behavior without this override being updated
- Migration plan: Track Cashier changelog closely; document why override exists

**Hard Dependency on Stripe API Schema:**
- Risk: No API version pinning visible; webhook handlers assume specific Stripe object structure
- Files: `app/Listeners/HandleStripeWebhook.php` (all handlers)
- Impact: Webhooks break on Stripe API version changes
- Migration plan: Pin Stripe API version; validate webhook payloads against schema

## Missing Critical Features

**No Webhook Retry/DLQ:**
- Problem: If webhook handler throws exception, event is lost
- Current workaround: Stripe retries, but no guarantee of success on retry
- Blocks: Reliable subscription state synchronization
- Implementation complexity: Medium (queued job with exponential backoff + webhook log table)

**No Audit Trail for Subscription Changes:**
- Problem: Subscription plan changes, metadata updates, or deletions not logged
- Current workaround: Stripe dashboard for manual audit
- Blocks: Debugging subscription issues; legal compliance
- Implementation complexity: Low (log mutations to `activity_logs`)

## Test Coverage Gaps

**Webhook Integration Flow:**
- What's not tested: Full checkout -> webhook -> subscription activation flow
- Risk: Integration bugs between webhook listeners not caught
- Priority: High
- Difficulty to test: Need Stripe webhook simulation setup

**Cache Invalidation:**
- What's not tested: Cache clearing on subscription/role changes
- Risk: Stale billing context shown to users
- Priority: Medium
- Difficulty to test: Need to verify cache state between operations

**Organization Billing Context:**
- What's not tested: Switching between personal/organization billing context
- Risk: Billing applied to wrong entity
- Priority: High
- Difficulty to test: Need organization with subscription setup in test fixtures

## Data Integrity

**Inconsistent Foreign Key Constraints:**
- Issue: Mixed use of `nullOnDelete()`, `cascadeOnDelete()`, and `restrict` across migrations
- Files: Multiple migration files for `subscriptions`, `invoices`, `organizations`
- Impact: Ambiguous deletion semantics; potential orphaned records
- Fix approach: Standardize: `restrict` for plans (prevent deletion if subscriptions exist), `cascade` for organization members

**Irreversible Migration:**
- Issue: `down()` method recreates columns but doesn't restore data
- File: `database/migrations/2026_02_14_000003_remove_old_billing_columns_from_subscriptions.php`
- Impact: Rollback permanently loses subscription data
- Fix approach: Document as irreversible; add data preservation if rollback needed

---

*Concerns audit: 2026-02-26*
*Update as issues are fixed or new ones discovered*
