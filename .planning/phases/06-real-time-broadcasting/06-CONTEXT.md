# Phase 6: Real-time Broadcasting - Context

**Gathered:** 2026-02-26
**Status:** Ready for research

<vision>
## How This Should Work

Reverb provides the WebSocket infrastructure, Echo listens on the frontend. The only use case the starter kit ships is real-time notifications -- the bell icon updates its count and a toast appears for high-priority items, all without a page refresh.

The notification system already exists (table, UI, bell icon, dropdown, preferences page). The gap is the WebSocket push. When a notification is created, it broadcasts on a tenant-scoped private channel. Echo catches it, increments the bell count, and conditionally shows a toast if the notification is marked high-priority.

This is infrastructure shipped through the one use case every SaaS needs. Product builders get a proven broadcasting setup -- channel auth, Echo config, Reverb config, tenant-scoped channel naming -- all working. When they later need live-updating data or presence for their specific product, they add new event classes and listeners. They've seen the pattern in action and can replicate it.

</vision>

<essential>
## What Must Be Nailed

- **Tenant-isolated channel authorization** -- Channel name includes tenant ID (`private-tenant.{tenantId}`). Auth callback checks: user is authenticated, user is an active member of this tenant, tenant is not suspended. A notification for Org A must never reach Org B's users. This is the broadcasting equivalent of the tenant data boundary test.
- **Subtle notification delivery** -- Bell count increments on every notification (silent). Toast only fires for notifications marked `high_priority`. Product builder decides what's high-priority for their product. Starter kit defaults: payment failures, account restrictions, credits depleted. Everything else is silent bell-count-only.

</essential>

<specifics>
## Specific Ideas

- Channel naming: `private-tenant.{tenantId}` -- tenant-scoped, not user-scoped
- Channel auth must verify: authenticated + active member of tenant + tenant not suspended
- Broadcast payload includes `count` (unread), `toast` (boolean for high-priority), `title`
- Frontend: Echo listener always increments badge, conditionally shows toast based on `toast` flag -- one `if` statement
- Switching tenants (multi-org users) resubscribes to the new channel and leaves the old one
- The critical test: two tenants, one user each, fire notification for Tenant A, assert Tenant B's user never receives it

</specifics>

<notes>
## Additional Context

The user explicitly scoped out live-updating data (Option 2) and collaborative presence (Option 3) as product-specific features that don't belong in a starter kit. The principle: ship infrastructure through the one universal use case, let product builders extend it.

The `high_priority` flag on the notification payload is designed as a clean upgrade path. When a product matures and has 15+ notification types, the flag becomes the default that per-type user preferences override. No retrofit needed.

</notes>

---

*Phase: 06-real-time-broadcasting*
*Context gathered: 2026-02-26*
