# Phase 1: Tenant Data Scoping - Context

**Gathered:** 2026-02-26
**Status:** Ready for planning

<vision>
## How This Should Work

The infrastructure is invisible -- middleware auto-resolves the current organization from the authenticated user and binds it to a TenantContext in the container. Scoping is explicit opt-in per model via a `BelongsToTenant` trait. Add the trait to a model, and reads and writes are both handled automatically.

**How it feels to use:**
1. Add `use BelongsToTenant;` to a model
2. Run the migration to add `organization_id`
3. Done -- queries are scoped, creates auto-fill the org, relationships are ready

**What happens behind the scenes:**
- Middleware resolves current org once per request (from `current_organization_id` on the user) and sets TenantContext
- The trait applies a global scope in `booted()` that filters queries by TenantContext
- The trait auto-sets `organization_id` on the `creating` event when TenantContext is active
- No TenantContext set (admin panel, console, queue workers) = no scope applied = naturally sees all tenants
- Models without the trait are naturally global (plans, roles, permissions, changelogs, system config)

**Queue jobs** that operate on tenant data need explicit TenantContext setting. A `TenantAware` job trait or middleware that pulls the org from the job payload is the clean pattern.

</vision>

<essential>
## What Must Be Nailed

- **Safety wins when it conflicts with ergonomics** -- design the DX first (Laravel-native feel), then audit every path for leaks. If good DX requires weakening the safety guarantee, safety wins every time.
- **Both reads and writes are auto-handled** -- the trait applies the global scope AND auto-fills `organization_id` on create. Developers don't think in two places.
- **TenantContext drives everything** -- scoping is determined by whether TenantContext exists, not by "are we in admin." No context = no scope. This naturally handles admin, console, queue workers, and any future context.
- **Audit all leak vectors** -- direct DB queries, raw joins, relationship eager loads, `withoutGlobalScopes()` usage, queue jobs, model factories in tests. The happy path is easy; the edge cases are where leaks happen.
- **Guardrails catch what good DX can't prevent** -- a test helper or approach that flags `withoutGlobalScopes()` on tenant models. Six months from now someone will bypass the scope in a reporting feature and nobody will notice unless there's a guardrail.

</essential>

<specifics>
## Specific Ideas

- Trait applies scope in `booted()` AND auto-sets `organization_id` on `creating` event
- TenantContext as a container singleton -- middleware sets it, scope reads it
- If TenantContext is set but `organization_id` missing on create, the trait fills it automatically (not a validation error)
- `withoutGlobalScopes()` on tenant models should be flaggable -- test helper like `TenantScopeBypassDetector`
- Queue jobs need a `TenantAware` job trait that sets context from the job payload
- Testing is straightforward: set tenant context in `setUp()`, scoped models just work, global models just work

</specifics>

<notes>
## Additional Context

User views this as the most foundational phase -- every future feature inherits its correctness. The trait needs to feel so natural that developers never skip it. If it fights the framework, people work around it, and then correctness is moot.

Priority order when forced to choose: correctness > ergonomics > features.

</notes>

---

*Phase: 01-tenant-data-scoping*
*Context gathered: 2026-02-26*
