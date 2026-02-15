# Filament Admin Panel Overhaul

## Overview

Transform the admin panel from a basic CRUD interface into a full SaaS command center with local Stripe data sync, rich analytics, and direct billing actions.

## Goals

- **Operations:** Quickly find users, troubleshoot billing, handle support
- **Business Intelligence:** MRR, churn, revenue trends, plan distribution
- **Admin Control:** Refund, cancel, change plans, apply coupons — all from Filament

## Decisions

- Stripe data synced to local DB via webhooks (not API reads or external links)
- Full admin billing control (calls Stripe API behind the scenes)
- Full analytics dashboard with cards, charts, and action tables

---

## 1. New Models & Migrations

### Invoice

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| stripe_id | string | Unique, e.g. `in_xxx` |
| stripe_customer_id | string | |
| billable_type | string | `user` or `organization` |
| billable_id | unsignedBigInteger | |
| subscription_id | unsignedBigInteger | nullable, FK to subscriptions |
| number | string | nullable, Stripe invoice number |
| status | string | `draft`, `open`, `paid`, `void`, `uncollectible` |
| currency | string | 3-letter code |
| amount_due | integer | In cents |
| amount_paid | integer | In cents |
| amount_remaining | integer | In cents |
| subtotal | integer | In cents |
| tax | integer | nullable, in cents |
| total | integer | In cents |
| period_start | timestamp | nullable |
| period_end | timestamp | nullable |
| due_date | timestamp | nullable |
| paid_at | timestamp | nullable |
| hosted_invoice_url | text | nullable |
| invoice_pdf | text | nullable |
| line_items | json | nullable, array of line item objects |
| metadata | json | nullable |
| created_at / updated_at | timestamps | |

### Transaction

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| stripe_id | string | Unique, charge or payment_intent ID |
| stripe_customer_id | string | |
| invoice_id | unsignedBigInteger | nullable, FK to invoices |
| billable_type | string | |
| billable_id | unsignedBigInteger | |
| amount | integer | In cents |
| currency | string | |
| status | string | `succeeded`, `failed`, `pending`, `refunded`, `partially_refunded` |
| payment_method_type | string | nullable, `card`, `bank_transfer`, etc. |
| payment_method_last4 | string | nullable |
| payment_method_brand | string | nullable, `visa`, `mastercard`, etc. |
| failure_code | string | nullable |
| failure_message | text | nullable |
| refunded_amount | integer | Default 0, in cents |
| description | text | nullable |
| metadata | json | nullable |
| created_at / updated_at | timestamps | |

### Coupon

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| stripe_id | string | Unique |
| name | string | nullable, display name |
| amount_off | integer | nullable, in cents |
| percent_off | decimal(5,2) | nullable |
| currency | string | nullable |
| duration | string | `forever`, `once`, `repeating` |
| duration_in_months | integer | nullable |
| max_redemptions | integer | nullable |
| times_redeemed | integer | Default 0 |
| active | boolean | Default true |
| valid | boolean | Default true |
| redeem_by | timestamp | nullable |
| metadata | json | nullable |
| created_at / updated_at | timestamps | |

### PromotionCode

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| stripe_id | string | Unique, `promo_xxx` |
| coupon_id | unsignedBigInteger | FK to coupons |
| code | string | The customer-facing code string |
| active | boolean | Default true |
| max_redemptions | integer | nullable |
| times_redeemed | integer | Default 0 |
| first_time_transaction | boolean | Default false |
| minimum_amount | integer | nullable, in cents |
| minimum_amount_currency | string | nullable |
| expires_at | timestamp | nullable |
| metadata | json | nullable |
| created_at / updated_at | timestamps | |

### CouponRedemption

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| coupon_id | unsignedBigInteger | FK to coupons |
| promotion_code_id | unsignedBigInteger | nullable, FK to promotion_codes |
| invoice_id | unsignedBigInteger | nullable, FK to invoices |
| subscription_id | unsignedBigInteger | nullable, FK to subscriptions |
| billable_type | string | |
| billable_id | unsignedBigInteger | |
| discount_amount | integer | In cents |
| redeemed_at | timestamp | |
| created_at / updated_at | timestamps | |

### PaymentMethod

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| stripe_id | string | Unique, `pm_xxx` |
| stripe_customer_id | string | |
| billable_type | string | |
| billable_id | unsignedBigInteger | |
| type | string | `card`, `bank_transfer`, `sepa_debit`, etc. |
| brand | string | nullable, `visa`, `mastercard`, etc. |
| last4 | string | nullable |
| exp_month | integer | nullable |
| exp_year | integer | nullable |
| is_default | boolean | Default false |
| metadata | json | nullable |
| created_at / updated_at | timestamps | |

---

## 2. Navigation Restructure

```
Dashboard (analytics widgets)

PEOPLE
  Users
  Members (admin team)
  Organizations

BILLING
  Subscriptions
  Plans
  Invoices (NEW)
  Transactions (NEW)
  Coupons & Promotions (NEW)
  Payment Methods (NEW)

CONTENT
  Changelogs
  Forms

SETTINGS
  Roles & Permissions
```

---

## 3. Enhanced Existing Resources

### Users Resource

**New relation managers:**
- Subscriptions — all subscriptions for this user (personal + via org)
- Invoices — all invoices where billable is this user
- Activity Logs — recent activity entries
- Organizations — orgs this user belongs to with role
- API Keys — user's API keys with last used

**New filters:**
- Role (select)
- Subscription status: subscribed / trial / expired / none
- Signed up date range
- Has organization (boolean)

**New actions:**
- Cancel subscription
- Apply coupon to subscription
- Send notification
- Export user data

**View page info list:**
- Stripe customer ID (with link to Stripe Dashboard)
- Current plan + billing cycle
- MRR contribution
- Lifetime value (sum of paid invoices)
- Member since date

### Organizations Resource

**New relation managers:**
- Subscription (current active)
- Invoices
- Activity Logs

**New filters:**
- Active / inactive
- Has subscription (boolean)
- Plan (select)

**New actions:**
- Manage seats (increase/decrease)
- Cancel subscription

**Table enhancements:**
- Show occupied/total seats as `X/Y` column
- Show current plan name

### Plans Resource

**New relation manager:**
- Subscriptions — all active subscriptions on this plan

**Stats header widgets:**
- Active subscriber count
- MRR generated by this plan
- Churn rate for this plan (last 30 days)

**New actions:**
- Sync with Stripe (verify price IDs exist and are active)

### Subscriptions Resource

**New relation managers:**
- Invoices
- Transactions

**New actions:**
- Cancel subscription (immediate or end of period)
- Refund last payment
- Change plan (swap)
- Apply coupon/promotion
- Adjust seat quantity

**New filters:**
- Plan (select)
- Billable type: user / organization
- Date range (created)
- Trial ending within X days
- Past due

**Form improvements:**
- Billable as searchable Select showing user name or org name (not raw type + ID)
- Status shown as color-coded badge on edit (not editable)
- Link to Stripe subscription page

---

## 4. New Filament Resources

### Invoices Resource

**Table columns:**
- Invoice number
- Customer name (with billable type badge)
- Amount (formatted currency)
- Status (badge: paid=green, open=amber, void=gray, uncollectible=red)
- Date
- Line items count
- PDF link (icon button)
- Stripe link (icon button)

**Filters:**
- Status (multi-select)
- Date range
- Plan
- Billable type
- Amount range (min/max)

**Actions:**
- Refund (full or partial — calls Stripe API)
- Void (calls Stripe API)
- Mark uncollectible (calls Stripe API)
- Download PDF
- Open in Stripe Dashboard

**Form:** Read-only view page showing all invoice details + line items table

### Transactions Resource

**Table columns:**
- Transaction ID
- Customer name
- Amount (formatted)
- Status (badge: succeeded=green, failed=red, pending=amber, refunded=gray)
- Payment method (brand icon + last 4)
- Related invoice (link)
- Date

**Filters:**
- Status (multi-select)
- Date range
- Amount range
- Payment method type
- Has refund (boolean)

**Actions:**
- Refund (full — calls Stripe API)
- Partial refund (amount input — calls Stripe API)
- Open in Stripe Dashboard

**Form:** Read-only view page

### Coupons Resource

**Table columns:**
- Name / Stripe ID
- Discount display (e.g. "20%" or "$10.00 off")
- Duration (badge)
- Times redeemed / max redemptions
- Active (boolean toggle)
- Expires at

**Form (full CRUD — syncs with Stripe):**
- Name
- Discount type (percentage or fixed amount)
- Amount off / Percent off
- Currency (for fixed amount)
- Duration (select: forever, once, repeating)
- Duration in months (visible if repeating)
- Max redemptions
- Redeem by date

**Relation managers:**
- Promotion Codes
- Redemptions (who used this coupon)

**Actions:**
- Deactivate (calls Stripe API)
- Duplicate (create new coupon with same settings)

### Promotion Codes Resource

**Table columns:**
- Code string
- Linked coupon (with discount display)
- Times used / max uses
- Active (boolean)
- First-time only (boolean)
- Minimum amount
- Expires at

**Form (syncs with Stripe):**
- Coupon (select)
- Code string
- Max redemptions
- First-time transaction only (toggle)
- Minimum amount
- Expiry date

**Actions:**
- Deactivate (calls Stripe API)

### Payment Methods Resource

**Table columns:**
- Customer name (with billable type)
- Type (card, bank, etc.)
- Brand + Last 4 (e.g. "Visa ****4242")
- Expiry (MM/YY)
- Default (boolean badge)
- Expiring soon (computed: within 60 days)

**Filters:**
- Expiring soon (boolean)
- Type
- Brand

**Read-only** — no create/edit actions (too risky to modify payment methods from admin)

---

## 5. Dashboard Overhaul

### Row 1 — Stat Cards (4 columns)

| Card | Calculation |
|---|---|
| MRR | Sum of active subscriptions' monthly-equivalent prices |
| Active Subscribers | Count of subscriptions with status `active` or `trialing` |
| New Signups | Users created in last 30 days, with trend % vs previous 30 days |
| Churn Rate | Subscriptions cancelled in last 30 days / total active at start of period |

### Row 2 — Charts (2 columns)

| Chart | Type | Data |
|---|---|---|
| Revenue Over Time | Line chart | Monthly revenue from paid invoices, last 12 months |
| Plan Distribution | Donut chart | Active subscription count per plan |

### Row 3 — Action Tables (2 columns)

| Table | Content |
|---|---|
| Recent Transactions | Last 10 transactions with status, amount, customer, date |
| At-Risk Subscribers | Trials ending in 7 days + past-due subscriptions |

---

## 6. Webhook Expansion

### New Events to Handle

| Event | Local Action |
|---|---|
| `invoice.created` | Create Invoice record |
| `invoice.updated` | Update Invoice record |
| `invoice.paid` | Update status + paid_at, create CouponRedemption if discount |
| `invoice.payment_failed` | Update status |
| `invoice.voided` | Update status |
| `charge.succeeded` | Create Transaction |
| `charge.failed` | Create Transaction with failure details |
| `charge.refunded` | Update Transaction status + refunded_amount |
| `charge.updated` | Update Transaction |
| `coupon.created` | Create Coupon |
| `coupon.updated` | Update Coupon |
| `coupon.deleted` | Soft-deactivate Coupon |
| `promotion_code.created` | Create PromotionCode |
| `promotion_code.updated` | Update PromotionCode |
| `payment_method.attached` | Create PaymentMethod |
| `payment_method.detached` | Delete PaymentMethod |
| `payment_method.updated` | Update PaymentMethod |
| `customer.updated` | Update default payment method flag |

---

## 7. Sync Commands (Backfill)

For existing Stripe data before webhooks were expanded:

```
php artisan stripe:sync-invoices      — Fetch all invoices from Stripe, create local records
php artisan stripe:sync-coupons       — Fetch all coupons + promotion codes
php artisan stripe:sync-payment-methods — Fetch payment methods for all customers
php artisan stripe:sync-all           — Runs all of the above
```

Each command should:
- Support `--customer=cus_xxx` for single-customer sync
- Show progress bar
- Be idempotent (update existing, create new)
- Log results

---

## 8. Implementation Phases

### Phase 1: Foundation
- New models, migrations, factories, seeders
- Navigation restructure with groups
- Webhook expansion (all new event handlers)
- Sync commands

### Phase 2: New Resources
- Invoices resource with filters + actions
- Transactions resource with filters + actions
- Coupons resource with Stripe CRUD
- Promotion Codes resource
- Payment Methods resource (read-only)

### Phase 3: Enhanced Existing Resources
- Users: relation managers, filters, actions, view page
- Organizations: relation managers, filters, actions
- Plans: relation manager, stats header
- Subscriptions: relation managers, actions, filters, form improvements

### Phase 4: Dashboard
- Stat card widgets (MRR, subscribers, signups, churn)
- Chart widgets (revenue trend, plan distribution)
- Action table widgets (recent transactions, at-risk subscribers)

### Phase 5: Admin Billing Actions
- Cancel subscription action (Stripe API)
- Refund action (full/partial, Stripe API)
- Change plan action (swap, Stripe API)
- Apply coupon action (Stripe API)
- Adjust seats action (Stripe API)
- Void invoice action (Stripe API)
- Create coupon (Stripe API + local)
- Create promotion code (Stripe API + local)
