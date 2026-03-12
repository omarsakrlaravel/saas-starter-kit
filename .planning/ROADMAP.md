# Roadmap: Wave SaaS Starter Kit

## Overview

Transform Wave from a billing-ready SaaS framework into a correctness-by-default platform. The first five phases establish architectural foundations -- tenant isolation, suspension, private files, credits, and feature flags -- that shape how every future feature behaves. The final four phases layer on additive capabilities: real-time updates, branded emails, support tickets, and onboarding.

## Package Strategy

**Principle:** Use packages when they solve the problem better than custom code. Roll your own when the problem is simple or needs tight integration with existing systems.

**Already installed:**
- `spatie/laravel-permission` -- RBAC with teams mode, wired up with Filament Shield
- `laravel/cashier-stripe` -- Stripe billing, subscriptions, invoices, webhooks
- Custom activity logging (`wave/src/ActivityLog.php`) -- queued writes, auto-cleanup, retention config

**Adding (4 packages):**
- `laravel/pennant` -- Feature flags (Phase 5). First-party, DB driver, scopes by tenant/user, cached per-request
- `laravel/reverb` -- Real-time broadcasting (Phase 6). First-party WebSocket server, no vendor dependency
- `spatie/laravel-database-mail-templates` -- Email templates (Phase 7). DB-stored with Mustache placeholders, correct security boundary for user-editable templates
- `spatie/laravel-onboard` -- Onboarding (Phase 9). Define steps as `completeIf` closures, lightweight computation-only

**Rolling your own (5 features):**
- Tenant data scoping -- Packages solve multi-DB problems we do not have
- Account suspension -- A column, a middleware, a history table
- Private file URLs -- Laravel built-ins: `Storage::temporaryUrl()` + `URL::temporarySignedRoute()`
- Credits ledger -- Needs tight integration with billing + tenant scoping
- Support tickets -- Two tables + a form, lighter than adopting a package

## Domain Expertise

None

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

- [x] **Phase 1: Tenant Data Scoping** - BelongsToTenant trait, TenantAware middleware, automatic org-scoped queries `[custom]`
- [x] **Phase 2: Account Suspension** - Status enum on users/orgs, middleware guards, admin controls `[custom]`
- [x] **Phase 3: Private File URLs** - Ownership-checked access with signed URLs via Laravel built-ins `[custom]`
- [ ] **Phase 4: Credits/Token System** - Ledger, transactions, consumption, top-ups, auto-refill, plan integration `[custom]`
- [x] **Phase 5: Feature Flags** - Laravel Pennant with DB driver, tenant/user scoping, admin UI `[laravel/pennant]`
- [ ] **Phase 6: Real-time Broadcasting** - Laravel Reverb setup, tenant-aware channels, notification integration `[laravel/reverb]`
- [ ] **Phase 7: Email Template System** - DB-stored templates with Mustache placeholders, test-send in admin `[spatie/laravel-database-mail-templates]`
- [ ] **Phase 8: Support Tickets** - Submission form, database storage, email notification, admin view `[custom]`
- [ ] **Phase 9: Onboarding Checklist** - completeIf closures, dismissible Livewire component, configurable steps `[spatie/laravel-onboard]`

## Phase Details

### Phase 1: Tenant Data Scoping
**Goal**: Every database query automatically scopes to the current organization, preventing cross-tenant data leaks
**Depends on**: Nothing (foundation phase)
**Approach**: Custom. Multi-tenant packages solve multi-DB problems we do not have. Eloquent global scopes + middleware are established Laravel patterns.
**Research**: Unlikely
**Plans**: 3 plans

Plans:
- [x] 01-01: BelongsToTenant trait + migrations (add organization_id to tenant-owned tables)
- [x] 01-02: TenantAware middleware + automatic query scoping via global scopes
- [x] 01-03: Tenant isolation tests + audit existing queries for scope gaps

### Phase 2: Account Suspension
**Goal**: Suspended users/orgs are blocked from accessing the application with clear messaging and admin controls
**Depends on**: Phase 1
**Approach**: Custom. A status column, a middleware, a history table. Package adds polymorphic abstraction we will not use.
**Research**: Unlikely
**Plans**: 3 plans

Plans:
- [x] 02-01: Status enum + migrations on users and organizations tables
- [x] 02-02: Suspension middleware + route guards + user-facing suspension page
- [x] 02-03: Admin suspension controls in Filament (suspend/unsuspend actions, reason field)

### Phase 3: Private File URLs
**Goal**: Files are served through ownership-checked routes with signed URLs, preventing unauthorized access
**Depends on**: Phase 1
**Approach**: Custom using Laravel built-ins. `Storage::temporaryUrl()` + `URL::temporarySignedRoute()` are first-party -- no package needed.
**Research**: Unlikely
**Plans**: 4 plans

Plans:
- [x] 03-01: File model + FileAccessLevel enum + migration + factory + FilePolicy
- [x] 03-02: FileService + FileDownloadController + signed download route
- [x] 03-03: Avatar system wired through private file infrastructure
- [x] 03-04: File access authorization tests + edge cases (expired URLs, wrong tenant)

### Phase 4: Credits/Token System
**Goal**: Organizations can consume, purchase, and auto-refill credits alongside their subscription plan
**Depends on**: Phase 1
**Approach**: Custom. Needs tight integration with billing (Cashier) + tenant scoping. Packages fight you here.
**Research**: Likely (ledger pattern design decisions, Cashier integration for credits-included-with-plan)
**Research topics**: Single-entry vs double-entry ledger, credit balance caching strategy, Cashier metered billing vs custom ledger, top-up checkout flow via Stripe
**Plans**: 4 plans

Plans:
- [ ] 04-01: Credit ledger model + migration + transaction recording (credit/debit entries)
- [ ] 04-02: Consumption tracking + balance queries + HasCredits trait
- [ ] 04-03: Top-ups via Stripe + auto-refill rules + plan-included credits allocation
- [ ] 04-04: Credits admin UI in Filament (balances, transaction history, manual adjustments)

### Phase 5: Feature Flags
**Goal**: Features can be toggled per organization or user at runtime via admin panel
**Depends on**: Phase 1
**Approach**: `laravel/pennant` -- First-party feature flags. DB driver, scopes by tenant or user, cached per-request. No reason to roll your own when Laravel ships one.
**Research**: Unlikely (Pennant is well-documented, standard integration)
**Plans**: 3 plans

Plans:
- [x] 05-01: Install Pennant + configure DB driver + define feature classes scoped to org/user
- [x] 05-02: Filament admin page with kill switch section, feature table, per-org overrides
- [x] 05-03: Pennant integration tests + Filament page tests (21 tests)

### Phase 6: Real-time Broadcasting
**Goal**: Users receive instant notifications and live updates via Laravel Reverb
**Depends on**: Phase 1
**Approach**: `laravel/reverb` -- First-party WebSocket server. Notification system is already built -- Reverb makes it real-time. No vendor dependency (Pusher/Ably), runs on your own infra.
**Research**: Likely (Reverb is newer, need current setup docs and tenant-aware channel patterns)
**Research topics**: Reverb installation and configuration, private/presence channel authorization with tenant scoping, Echo client setup, notification channel integration
**Plans**: 3 plans

Plans:
- [ ] 06-01: Laravel Reverb installation + configuration + channel authorization
- [ ] 06-02: Notification broadcasting integration + tenant-aware private channels
- [ ] 06-03: Frontend Echo listeners + notification UI component (toast/dropdown)

### Phase 7: Email Template System
**Goal**: Branded, consistent email templates with admin test-send capability
**Depends on**: Nothing (additive feature)
**Approach**: `spatie/laravel-database-mail-templates` -- DB-stored templates with Mustache placeholders. Avoids Blade/PHP execution in user-editable templates, which is the correct security boundary.
**Research**: Unlikely (package is straightforward, standard integration)
**Plans**: 2 plans

Plans:
- [ ] 07-01: Install package + branded base layout + common transactional email templates in DB
- [ ] 07-02: Test-send functionality in Filament admin (preview + send test email)

### Phase 8: Support Tickets
**Goal**: Users can submit support requests that are stored and forwarded to the support email
**Depends on**: Phase 1
**Approach**: Custom. Two tables + a form. Lighter than adopting and then patching an unmaintained package.
**Research**: Unlikely
**Plans**: 2 plans

Plans:
- [ ] 08-01: Ticket model + migration + user submission form (Livewire component)
- [ ] 08-02: Email notification to support + admin Filament resource (view/reply)

### Phase 9: Onboarding Checklist
**Goal**: New users see a dismissible checklist guiding them through initial setup steps
**Depends on**: Nothing (additive feature)
**Approach**: `spatie/laravel-onboard` -- Define onboarding steps as `completeIf` closures on the User model. Lightweight computation-only, no heavy schema or admin UI to maintain. Rolling your own would be more code for the same result.
**Research**: Unlikely (package is straightforward)
**Plans**: 2 plans

Plans:
- [ ] 09-01: Install package + define onboarding step closures on User model + configurable steps
- [ ] 09-02: Dismissible Livewire checklist component + dashboard integration

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8 -> 9

| Phase | Plans Complete | Status | Completed |
|-------|---------------|--------|-----------|
| 1. Tenant Data Scoping | 3/3 | Complete | 2026-03-04 |
| 2. Account Suspension | 3/3 | Complete | 2026-03-11 |
| 3. Private File URLs | 4/4 | Complete | 2026-03-11 |
| 4. Credits/Token System | 0/4 | Not started | - |
| 5. Feature Flags | 3/3 | Complete | 2026-03-12 |
| 6. Real-time Broadcasting | 0/3 | Not started | - |
| 7. Email Template System | 0/2 | Not started | - |
| 8. Support Tickets | 0/2 | Not started | - |
| 9. Onboarding Checklist | 0/2 | Not started | - |
