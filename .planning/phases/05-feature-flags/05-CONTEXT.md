# Phase 5: Feature Flags - Context

**Gathered:** 2026-02-26
**Status:** Ready for planning

<vision>
## How This Should Work

Feature flags serve three distinct purposes on the same Pennant infrastructure, each answering a different question:

1. **Plan-gating** -- "Is this tenant allowed to use this feature?" A business rule. Free plan doesn't get advanced reports, Pro does, Enterprise gets SSO. This extends the existing `@canUseFeature` quota system with boolean availability toggles.

2. **Gradual rollouts** -- "Is this feature ready for everyone?" A stability/confidence question. Ship a new editor to 5% of tenants, watch error rates, expand to 50%, then 100%. Without this, every feature launch is all-or-nothing.

3. **Kill switches** -- "Something is broken, turn it off NOW." A global toggle. Your AI feature is hammering a third-party API and racking up costs -- one toggle, it's off for everyone. Without this, you're deploying a hotfix at 3am.

Pennant sits **alongside** the existing `@canUseFeature` system, not replacing or wrapping it. They solve different problems:

| System | Question | Type | Example |
|---|---|---|---|
| `@canUseFeature` | "Can they use more of this?" | Quantitative (quota) | 73/100 reports used |
| Pennant `@feature` | "Is this available at all?" | Boolean (toggle) | AI reports enabled for this tenant |

These compose naturally:
```php
@feature('ai-reports')
    @canUseFeature('reports')
        <button>Generate Report</button>
    @else
        <span>Report limit reached</span>
    @endcanUseFeature
@else
    <span>Coming soon</span>
@endfeature
```

Feature flags are NOT authorization. Flags control rollout and availability. Permissions control who can do what. Don't mix them.

</vision>

<essential>
## What Must Be Nailed

- **Tenant-scoped defaults** -- Pennant's default scope must resolve to the current organization (via TenantContext), not User. Developer defines a flag, it's tenant-scoped automatically without extra config. This is the foundation everything else depends on. If scoping is wrong, the admin UI toggles the wrong thing and examples teach the wrong thing.
- **Global flags for kill switches** -- Explicit `Feature::for(null)->active('maintenance')` pattern for global flags. Kill switches that require a deploy aren't kill switches.
- **Clear separation from @canUseFeature** -- Two systems, two questions, zero ambiguity. Developer always knows which one to reach for.

</essential>

<specifics>
## Specific Ideas

- **Pennant with DB driver**, tenant (Organization) as default scope, cached per-request
- **Three evaluation strategies** on the same infrastructure: plan-gated checks tenant's plan, rollout uses Pennant's native percentage-based resolution, kill switch uses null scope (global)
- **Separate `feature_definitions` table** for human context (type, description, last changed by). Pennant stores resolved values. This table stores the admin-facing metadata. They join on the flag key.
- **Filament admin page** with:
  - Top section: global kill switches with red toggle buttons + confirmation modal
  - Below: searchable table of all flags, filterable by type (plan-gate / rollout / kill-switch)
  - Type badges so admin knows impact before toggling -- kill switch gets red badge
  - Description column so admin doesn't have to reverse-engineer flag keys
  - Last changed + by whom for incident response
  - Click a row: see per-org overrides, toggle for specific tenant, see change history (from activity log)
- **Example flag definitions** showing all three patterns so devs know how to add their own
- **`@feature('flag-name')` Blade directive** that resolves against current tenant
- **User-scoped flags** are opt-in for the rare case, not the default

**What NOT to ship:**
- Percentage-based rollout UI (Pennant handles percentages in code)
- A/B test analytics (that's a product, not infrastructure)
- User-scoped flags in default setup (tenant scope covers 90% of cases)

</specifics>

<notes>
## Additional Context

The existing `@canUseFeature` / `featureUsage()` / `featureRemaining()` / `@featureNearLimit` system handles quota math and must remain untouched. Pennant is additive, not a replacement.

Admin UI is essential -- non-developers need to toggle flags without a PR/deploy cycle. But the UI must show flag type metadata to prevent mistakes (e.g., admin accidentally killing a global flag thinking it's per-org).

</notes>

---

*Phase: 05-feature-flags*
*Context gathered: 2026-02-26*
