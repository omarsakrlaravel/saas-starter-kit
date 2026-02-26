# Phase 8: Support Tickets - Context

**Gathered:** 2026-02-26
**Status:** Ready for planning

<vision>
## How This Should Work

Users submit support tickets from a new "Support" tab inside settings (alongside Profile, Security, Notifications, etc.). They fill in a subject, description, and pick a category (Bug, Billing, Feature Request, General). After submitting, they see a "My Tickets" list with status badges -- open, in progress, resolved. They can track their ticket's lifecycle and close/reopen from their dashboard.

Replies happen via email, not in-app threads. Admin replies from Filament or email, user gets notified via the existing notification system. When status changes, a notification fires so the user knows their ticket moved forward without having to check back.

Two completely separate interfaces, one shared table. Users submit and track on the frontend. Admin manages and resolves in Filament. They never cross.

The restricted account page already links to support -- that link points to this same settings tab. Clean loop: restricted user -> restricted page -> "Contact Support" -> settings/support tab -> submit ticket.

</vision>

<essential>
## What Must Be Nailed

- **Admin workflow efficiency** -- The bottleneck is never the user submitting, it's the admin resolving. If the admin can't quickly filter by category, sort by oldest-unresolved, see which tickets have no response, assign priority, write internal notes, and reply -- they're spending 2 minutes per ticket instead of 30 seconds. That compounds across 30+ tickets a day.
- **Filament table done right** -- Filterable by status + category, sortable by created date, time-since-created as colored badges (green < 24h, yellow < 48h, red > 48h), quick actions for status change and priority assignment without opening the detail page.
- **Ticket detail page** -- User info at top, description, internal notes field (user never sees), reply action that sends email to user. Status change auto-triggers user notification.

</essential>

<specifics>
## Specific Ideas

- **Category over priority on user form** -- Categories are objective (user knows if it's a bug or billing question). Priority is subjective (every user picks High). Category gives admin routing signal without gaming. Admin assigns priority internally after reading.
- **Categories:** Bug, Billing, Feature Request, General
- **Database:** `category` column (user-facing), `priority` column (admin-only, set in Filament)
- **Settings tab pattern** -- Support lives under settings as a tab. No standalone page, no new nav entry needed. The tab structure is already built.
- **Same tab houses both** -- Submit form (behind a "New Ticket" button) and ticket list with status badges on the same Support tab.
- **Upgrade path to in-app threads is clean** -- When a product needs in-app replies later, add a messages table and thread UI under each ticket. The ticket model, status tracking, and notification wiring are already done. Nothing gets thrown away.

</specifics>

<notes>
## Additional Context

The user-facing side is intentionally simple: two Volt pages (or one tab with form + list). The complexity budget goes to the admin Filament resource where triage efficiency matters most.

Status notifications leverage the existing notification system -- one event listener, no new infrastructure.

No `support_ticket_messages` table in this phase. Replies are email-based. The messages table is a future upgrade when a product needs in-app conversation threads.

</notes>

---

*Phase: 08-support-tickets*
*Context gathered: 2026-02-26*
