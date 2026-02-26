# Phase 2: Account Suspension - Context

**Gathered:** 2026-02-26
**Status:** Ready for research

<vision>
## How This Should Work

Three statuses on both users and organizations: `active`, `restricted`, `suspended`. Two non-active states cover every real SaaS scenario without overcomplicating the model.

**Restricted** is for the 95% case -- billing problems, policy warnings, investigations. The user can still log in but lands on a dedicated page that says "We need your attention on something." The page shows the reason, a countdown/deadline if applicable (e.g., "14 days to update billing before suspension"), and three clear action links: update billing, contact support, export data. Helpful tone, not hostile. One middleware check redirects to `/account/restricted` -- no per-route logic, no second layout.

**Suspended** is for the 5% case -- abuse, fraud, ToS violations. Full wall. "Contact support" with a reference code. No billing access, no data access, nothing. Hard kill switch.

Both statuses also block: API token usage (Sanctum check), broadcasting channel auth, and queue jobs re-check status at execution time.

</vision>

<essential>
## What Must Be Nailed

- **Middleware is airtight** -- this is the #1 priority, non-negotiable. A gap in the middleware is a security hole, not a UX problem. Runs early in the pipeline, before route model binding. Covers all entry points: web, API, broadcasting, file downloads.
- **Allowlist approach** -- define the 3-4 routes restricted users CAN access (`/account/restricted`, `/billing/*`, `/support/*`, `/data-export`), block everything else by default. Never blocklist.
- **Queue jobs re-check status** at execution time, not just dispatch time.
- **Sanctum tokens reject** for restricted/suspended users so existing tokens can't bypass status checks.

</essential>

<specifics>
## Specific Ideas

- Status enum: `active`, `restricted`, `suspended` on both users and organizations tables
- Restricted page: single dedicated page at `/account/restricted` with reason, countdown/deadline banner, and action links (billing, support, data export)
- Suspended page: static wall at `/account/suspended` with support contact info and reference code
- Priority order for implementation: middleware logic first, then admin controls, then user-facing pages
- Adding a new status later is a one-line enum change for products that need finer granularity

</specifics>

<notes>
## Additional Context

The user's reasoning for the two-tier approach: most suspensions in SaaS are billing-related, not abuse-related. Hard-blocking a user with a billing issue creates a support nightmare (can't log in -> can't fix payment -> contacts support externally -> manual lift -> they pay -> re-verify). Graceful degradation for billing problems, hard block for abuse -- covers all real scenarios.

The user explicitly deprioritized admin UI polish and user-facing page copy relative to middleware correctness. Those are "recoverable if wrong" -- a middleware gap is not.

</notes>

---

*Phase: 02-account-suspension*
*Context gathered: 2026-02-26*
