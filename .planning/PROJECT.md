# Wave SaaS Starter Kit

## What This Is

A Laravel-based SaaS framework that provides the architectural foundations every subscription-based application needs. Wave handles auth, billing, organizations, permissions, and admin so developers can focus on their product-specific features. Built on Laravel 12, Filament v4, Livewire 3, and Stripe via Cashier.

## Core Value

Every SaaS built on this kit inherits correctness by default -- tenant data isolation, secure file access, and proper account lifecycle management are baked in, not bolted on.

## Requirements

### Validated

- Auth + 2FA + social login (Google, GitHub) -- existing via devdojo/auth
- Stripe subscription billing with Cashier -- existing via laravel/cashier
- Organizations with seat-based billing -- existing with polymorphic billable
- Role-based permissions (owner, member) -- existing via spatie/laravel-permission
- Filament v4 admin panel with CRUD resources -- existing
- User impersonation for support -- existing via lab404/laravel-impersonate
- Activity logging with queued writes and auto-cleanup -- existing
- API keys with JWT authentication -- existing via tymon/jwt-auth
- Dark mode support -- existing in theme
- Data export and account deletion with grace period -- existing
- Session management -- existing with database driver
- Plan feature limits and usage tracking -- existing via HasPlanFeatures trait
- Invoice sync from Stripe -- existing with sync commands
- Coupons and promotion codes -- existing with Stripe sync
- Pending plan changes (schedule downgrades to end of billing period) -- existing

### Active

- [ ] Tenant data scoping (BelongsToTenant trait + TenantAware middleware + automatic org-scoped queries)
- [ ] Credits/token system (ledger, transactions, consumption, top-ups, auto-refill, credits-included-with-plan)
- [ ] Feature flags per org/user (polymorphic feature_flags table + @featureEnabled directive + admin UI)
- [ ] Account suspension (status enum on users/orgs + middleware + admin controls)
- [ ] Private/signed file URLs (ownership-checked access + PrivateFile middleware pattern)
- [ ] Real-time broadcasting via Laravel Reverb (wire up existing notification system)
- [ ] Email template system (branded base layout + Blade templates + test-send in admin)
- [ ] Minimal support tickets (form -> DB -> email to support, no assignment/routing)
- [ ] Onboarding checklist (JSON on user, dismissible component, configurable steps)

### Out of Scope

- Full helpdesk/live chat -- this is a product, not a starter kit feature; embed third-party widget slot instead
- Referral system -- growth feature, easy to add later, doesn't shape architecture
- Database backup UI -- belongs in infrastructure (RDS snapshots, spatie/laravel-backup), not application layer
- Outgoing webhooks -- complex to do right; instead, dispatch domain events everywhere so it's webhook-ready
- Cookie consent/GDPR banner -- use third-party service (CookieYes, Termly); legal landscape changes too fast
- Waiting list/early access gating -- too product-specific; a simple is_approved flag is 10 minutes when needed
- Org-scoped audit logs -- existing activity logging + a query filter when needed, not a new system

## Context

- Brownfield project with mature billing and auth infrastructure
- Theme system hardcoded to "anchor" theme (no multi-theme switching)
- Wave framework lives in `wave/` directory as part of the repo (not a Composer package)
- Models split between `wave/src/` (framework) and `app/Models/` (app-specific)
- Organizations already have polymorphic billing (User or Organization as billable entity)
- Existing concerns: 795-line webhook handler, 797-line User model, missing database indexes, cache invalidation gaps
- Items 1-5 are architectural and influence how everything else is built; items 6-9 are additive

## Constraints

- **Backward compatible**: Existing migrations and data must not break -- changes are additive
- **Laravel first-party preferred**: Use Reverb for broadcasting, Cashier for billing, built-in features where possible
- **Starter kit philosophy**: Features should be foundational patterns, not full products; keep implementations lean

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Correctness over features | Tenant scoping, suspension, private files before polish features | -- Pending |
| Credits as billing primitive | Alongside subscriptions, not replacing them | -- Pending |
| Feature flags over config | Runtime toggleable per org/user, not deploy-time config | -- Pending |
| Reverb for broadcasting | First-party Laravel, replaces need for Pusher | -- Pending |
| No outgoing webhooks | Domain events instead; defer webhook delivery infrastructure | -- Pending |

---
*Last updated: 2026-02-26 after initialization*
