# Wave Upstream Tracker

Tracks our relationship with [thedevdojo/wave](https://github.com/thedevdojo/wave)
so we know where to resume when cherry-picking.

**Upstream mirror:** `wave-upstream-local` remote (or `/Users/omarsakr/Development/MyProjects/wave-upstream`)

---

## Checkpoint 1 -- Fork (2025-02-12)

| Our commit | Wave commit | Wave tag |
|---|---|---|
| `b42e4d08` | `6cee13e8` | post-`3.1.5` (tip of `main`) |

**Copied:** Full Wave codebase (auth, billing, admin, blog, pages, plugins,
themes, changelog, profiles, API keys, notifications, activity log, forms,
plan limits, settings, roles).

**Removed same day** (`55daa0fc`):
Blog/Posts/Categories, Pages CMS, Plugin system, Multi-theme + vendor packages,
Settings admin (replaced with config), Installation wizard, Media manager,
`role_id` on plans, old billing columns, payment methods table.

**We then built** (not in upstream):
- Stripe Cashier billing overhaul (invoices, transactions, coupons, refunds, plan change resolver)
- Organizations / multi-tenancy (members, invites, seat management, tenant scoping)
- Account status system (suspend/restrict with history)
- File service with private signed URLs
- Feature flags (Laravel Pennant + admin UI + per-org overrides)
- Rich admin dashboard (MRR, churn, revenue chart, at-risk subscribers)

---

## Checkpoint 2 -- Backport (2026-03-21)

| Our commit | Wave commit | Status |
|---|---|---|
| `1044adbd` | `6cee13e8` | Wave `main` unchanged since fork |

**Backported:**
1. Configurable currency symbol per plan (`currency` column, admin select, all views updated)
2. Pagination `onEachSide(1)` on activity page
3. Configurable file storage disk via `config('wave.storage.disk')`

**Skipped:** Blog, Pages, Plugins, Themes, Install wizard, DB-settings, Media,
Translations, Demo mode -- all deliberately removed or not needed.

**Watch:** Upstream branches `feature/referral-tracking` and `feature/plan-feature-limits`.

---

## Adding a Checkpoint

```bash
cd /Users/omarsakr/Development/MyProjects/wave-upstream && git pull
git log 6cee13e8..HEAD --oneline  # see what changed since last checkpoint
```

Then add a new section here with: date, our commit, their commit, what was
backported, what was skipped.
