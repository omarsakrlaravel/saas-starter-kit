# Phase 9: Onboarding Checklist - Context

**Gathered:** 2026-02-26
**Status:** Ready for planning

<vision>
## How This Should Work

A guided checklist widget lives on the dashboard. New users see it every time they log in until they complete all steps or dismiss it. It shows progress ("3 of 5 done") and each step links to the relevant page.

The widget is one component in one place -- no scattered onboarding conditionals across Blade files. Steps are defined in code via Spatie Onboard's `completeIf` closures in a single file. Product builders open that file, see the steps, add/remove their own. Done.

Dismissal is permanent. One `dismissed_at` timestamp. If not null, never show the widget again. No resurface logic, no settings toggle, no "it's been 7 days" passive-aggressive reappearance. The user clicked dismiss -- respect it.

Not a wizard. Not contextual nudges. A dashboard checklist widget that's centralized, dismissable, and easy to customize.

</vision>

<essential>
## What Must Be Nailed

- **Developer ergonomics above all** -- the product builder is the real user. One file with closures that return booleans. No config arrays, no database seeding, no abstract classes. If this file is clear, the feature succeeds even if the widget is ugly.
- **One file, one pattern** -- open the onboarding definition file, see steps like `Onboard::addStep('Complete your profile')->link('/settings/profile')->completeIf(fn (User $user) => ...)`. Delete what you don't want, add your own.
- **No forced completion** -- onboarding is UX, not authorization. User can use the app with 0 steps complete.

</essential>

<specifics>
## Specific Ideas

Default steps the starter kit ships:
- Complete your profile (name, avatar)
- Invite a team member
- Set up billing
- (Empty slot for product-specific step -- documented, not implemented)

Widget behavior:
- Progress bar showing "X of Y done"
- Each step links to the relevant settings/action page
- Dismiss button stores `dismissed_at` timestamp permanently
- No gamification (no points, badges, confetti per step)
- No re-showing after dismissal -- if activation nudges are needed, that's the email/notification system's job

</specifics>

<notes>
## Additional Context

The product builder will likely restyle the widget to match their product's design language, so visual polish matters less than structural clarity. A Blade component with clear markup that's easy to modify beats a beautiful but opaque widget.

The `completeIf` closures should reference existing relationships and methods on the User model (avatar check, tenant members count, subscription status) so the onboarding steps serve as documentation of what the app can do.

</notes>

---

*Phase: 09-onboarding-checklist*
*Context gathered: 2026-02-26*
