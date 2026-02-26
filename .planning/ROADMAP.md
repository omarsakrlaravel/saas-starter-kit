# Roadmap: Wave SaaS Starter Kit

## Overview

Transform Wave from a billing-ready SaaS framework into a correctness-by-default platform. The first five phases establish architectural foundations -- tenant isolation, suspension, private files, credits, and feature flags -- that shape how every future feature behaves. The final four phases layer on additive capabilities: real-time updates, branded emails, support tickets, and onboarding.

## Domain Expertise

None

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

- [ ] **Phase 1: Tenant Data Scoping** - BelongsToTenant trait, TenantAware middleware, automatic org-scoped queries
- [ ] **Phase 2: Account Suspension** - Status enum on users/orgs, middleware guards, admin controls
- [ ] **Phase 3: Private File URLs** - Ownership-checked access with PrivateFile middleware and signed URLs
- [ ] **Phase 4: Credits/Token System** - Ledger, transactions, consumption, top-ups, auto-refill, plan integration
- [ ] **Phase 5: Feature Flags** - Polymorphic feature_flags table, @featureEnabled directive, admin UI
- [ ] **Phase 6: Real-time Broadcasting** - Laravel Reverb setup, tenant-aware channels, notification integration
- [ ] **Phase 7: Email Template System** - Branded base layout, Blade templates, test-send in admin
- [ ] **Phase 8: Support Tickets** - Submission form, database storage, email notification, admin view
- [ ] **Phase 9: Onboarding Checklist** - JSON on user, dismissible Livewire component, configurable steps

## Phase Details

### Phase 1: Tenant Data Scoping
**Goal**: Every database query automatically scopes to the current organization, preventing cross-tenant data leaks
**Depends on**: Nothing (foundation phase)
**Research**: Unlikely (Eloquent global scopes, middleware -- established Laravel patterns)
**Plans**: 3 plans

Plans:
- [ ] 01-01: BelongsToTenant trait + migrations (add organization_id to tenant-owned tables)
- [ ] 01-02: TenantAware middleware + automatic query scoping via global scopes
- [ ] 01-03: Tenant isolation tests + audit existing queries for scope gaps

### Phase 2: Account Suspension
**Goal**: Suspended users/orgs are blocked from accessing the application with clear messaging and admin controls
**Depends on**: Phase 1
**Research**: Unlikely (enum column, middleware guard, Filament actions -- standard patterns)
**Plans**: 3 plans

Plans:
- [ ] 02-01: Status enum + migrations on users and organizations tables
- [ ] 02-02: Suspension middleware + route guards + user-facing suspension page
- [ ] 02-03: Admin suspension controls in Filament (suspend/unsuspend actions, reason field)

### Phase 3: Private File URLs
**Goal**: Files are served through ownership-checked routes with signed URLs, preventing unauthorized access
**Depends on**: Phase 1
**Research**: Unlikely (Laravel signed URLs, Storage facade, middleware -- established patterns)
**Plans**: 3 plans

Plans:
- [ ] 03-01: File ownership model + PrivateFile middleware (verify tenant ownership)
- [ ] 03-02: Signed URL generation + storage integration + download routes
- [ ] 03-03: File access authorization tests + edge cases (expired URLs, wrong tenant)

### Phase 4: Credits/Token System
**Goal**: Organizations can consume, purchase, and auto-refill credits alongside their subscription plan
**Depends on**: Phase 1
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
**Research**: Unlikely (polymorphic table, Blade directive, Filament resource -- standard patterns)
**Plans**: 3 plans

Plans:
- [ ] 05-01: Feature flags model + migration + HasFeatureFlags trait (polymorphic org/user)
- [ ] 05-02: @featureEnabled Blade directive + featureEnabled() helper + middleware
- [ ] 05-03: Feature flags admin UI in Filament (toggle per org/user, bulk operations)

### Phase 6: Real-time Broadcasting
**Goal**: Users receive instant notifications and live updates via Laravel Reverb
**Depends on**: Phase 1
**Research**: Likely (Laravel Reverb is newer, need current setup docs and tenant-aware channel patterns)
**Research topics**: Reverb installation and configuration, private/presence channel authorization with tenant scoping, Echo client setup, notification channel integration
**Plans**: 3 plans

Plans:
- [ ] 06-01: Laravel Reverb installation + configuration + channel authorization
- [ ] 06-02: Notification broadcasting integration + tenant-aware private channels
- [ ] 06-03: Frontend Echo listeners + notification UI component (toast/dropdown)

### Phase 7: Email Template System
**Goal**: Branded, consistent email templates with admin test-send capability
**Depends on**: Nothing (additive feature)
**Research**: Unlikely (Laravel Mail, Blade components, Filament admin -- standard patterns)
**Plans**: 2 plans

Plans:
- [ ] 07-01: Branded base email layout + common transactional email templates
- [ ] 07-02: Test-send functionality in Filament admin (preview + send test email)

### Phase 8: Support Tickets
**Goal**: Users can submit support requests that are stored and forwarded to the support email
**Depends on**: Phase 1
**Research**: Unlikely (model + form + mail notification -- standard CRUD)
**Plans**: 2 plans

Plans:
- [ ] 08-01: Ticket model + migration + user submission form (Livewire component)
- [ ] 08-02: Email notification to support + admin Filament resource (view/reply)

### Phase 9: Onboarding Checklist
**Goal**: New users see a dismissible checklist guiding them through initial setup steps
**Depends on**: Nothing (additive feature)
**Research**: Unlikely (JSON column, Livewire component -- standard patterns)
**Plans**: 2 plans

Plans:
- [ ] 09-01: Onboarding data model (JSON on user) + configurable step definitions
- [ ] 09-02: Dismissible Livewire checklist component + dashboard integration

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8 -> 9

| Phase | Plans Complete | Status | Completed |
|-------|---------------|--------|-----------|
| 1. Tenant Data Scoping | 0/3 | Not started | - |
| 2. Account Suspension | 0/3 | Not started | - |
| 3. Private File URLs | 0/3 | Not started | - |
| 4. Credits/Token System | 0/4 | Not started | - |
| 5. Feature Flags | 0/3 | Not started | - |
| 6. Real-time Broadcasting | 0/3 | Not started | - |
| 7. Email Template System | 0/2 | Not started | - |
| 8. Support Tickets | 0/2 | Not started | - |
| 9. Onboarding Checklist | 0/2 | Not started | - |
