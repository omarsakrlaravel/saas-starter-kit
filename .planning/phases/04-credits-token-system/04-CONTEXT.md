# Phase 4: Credits/Token System - Context

**Gathered:** 2026-02-26
**Status:** Ready for research

<vision>
## How This Should Work

A hybrid consumption credits system that works as the universal starter kit default. Organizations get credits included with their subscription plan monthly, can purchase top-up packs when they run out, and optionally enable auto-refill. The system doesn't care what the product charges credits for -- that's the product builder's job.

The three billing patterns it covers:
- **Subscription includes monthly credits.** User subscribes to Pro plan, gets 1000 credits/month. Cashier webhook fires on renewal, credit grant entry lands in the ledger.
- **Top-up for heavy users.** User burns through credits mid-month, buys a 500-credit pack via Stripe one-time payment. Another Cashier webhook, another credit grant.
- **Auto-refill (optional).** "When balance drops below 100, charge me for 500 more." A product-level feature built on top of the `CreditsLow` event the system emits. The starter kit provides the event and the infrastructure, not the auto-refill UI itself.

Consumption is generic -- whether the product charges credits for API calls, report generation, AI inference, file exports, or team seat-hours, the ledger doesn't care. It's always `$org->consumeCredits(10, 'generate_report', idempotencyKey: $requestId)`.

**Developer experience follows the Laravel notification pattern:**
- **HasCredits trait** on Organization for 95% of usage: `$org->consumeCredits()`, `$org->grantCredits()`, `$org->creditBalance()`, `$org->creditsRemaining()`. Feels like Eloquent, autocomplete works, obvious what it does.
- **CreditService** for complex flows: batch grants, admin adjustments with audit context, refunds referencing specific transactions, migration scripts, queue jobs without a model instance.
- Trait delegates to service internally. One implementation, two interfaces. Override the service to customize behavior -- trait picks it up automatically.

**Events emitted:** `CreditsGranted`, `CreditsConsumed`, `CreditsLow`, `CreditsDepleted`. Product builders wire these to whatever behavior they want.

</vision>

<essential>
## What Must Be Nailed

- **Ledger correctness above all else.** If the ledger is wrong, nothing else matters. A beautiful API that double-charges users is a billing dispute. The four invariants that must hold:
  1. Two concurrent `consumeCredits(500)` on a 600-credit balance -- one succeeds, one fails. Never -400.
  2. Same `idempotencyKey` called twice -- one ledger entry, not two.
  3. `balance_cached` always equals `SUM(amount)` from `credit_transactions` for that account. Always. Add a scheduled job that verifies this.
  4. Grant + consume in the same request with a failure between them -- transaction rolled back, ledger unchanged.

These four invariants define the test suite written before anything else.

</essential>

<specifics>
## Specific Ideas

**Header component (always visible):**
- Credit balance number in the app chrome (header/sidebar)
- Color shift: green to yellow to red as balance depletes
- Click navigates to credits page

**Credits page (under settings/billing):**
- Current balance + plan allowance ("47 of 1000 credits remaining this month")
- Transaction history table (date, action, amount, balance after) -- paginated query on `credit_transactions`
- "Buy more credits" button leading to Stripe checkout (Cashier one-time payment)
- Next renewal date + how many credits will be granted

**What NOT to ship in the starter kit:**
- Auto-refill settings UI -- product decision, not starter kit material. The `CreditsLow` event is there; product builder wires it.
- Usage charts/analytics -- transaction history table gives raw data; charts are polish.
- Per-action cost breakdown page -- too product-specific.

**Ledger handles:** grants (subscription, top-up, admin adjustment), consumption (any action), refunds.

</specifics>

<notes>
## Additional Context

The definition of what costs credits and how much is the product builder's job, not the starter kit's. The starter kit provides the infrastructure: ledger, events, Cashier integration, UI components. The product builder defines their consumption rules.

This architecture doesn't need to be rewritten when the product builder discovers their billing model. It just works for API-style usage, metered feature access, and hybrid consumption patterns.

</notes>

---

*Phase: 04-credits-token-system*
*Context gathered: 2026-02-26*
