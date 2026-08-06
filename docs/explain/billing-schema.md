# Billing schema — tables and columns

Money flow: **`services` → `service_orders` → `invoices` → `payments`**.
Migrations: `api/database/migrations/2026_07_28_1*` and `2026_07_29_100000`.

Two conventions everywhere:
- `net` = excludes VAT, `gross` = includes it. No ambiguous "price" column.
- Money is `decimal(10,2)` at rest, **integer cents in motion** (`App\Support\Money`).

---

## `services` — the catalogue (what we sell)

| Column | Meaning |
|---|---|
| `slug` unique | Stable key; guest-checkout URLs use it, so renaming is safe |
| `billing_type` | `one_off` \| `recurring` \| `milestone` |
| `default_price_net` | Pre-fill for lines; a line may override |
| `default_billing_interval` | `monthly` \| `yearly`, recurring only |
| `vat_rate` (20.00) | Per-service rate; a *default*, not the final rate (see TaxService) |
| `supports_deposit`, `default_deposit_percent` | Whether a split is offered, and at what % |
| `is_publicly_orderable` | **Only true rows appear at `/v1/public/services`** |
| `active` | Soft retirement. Services are deactivated, never deleted |
| `sort_order` | Catalogue display order |

## `service_orders` — a customer's purchase

Spans **multiple invoices** (deposit + balance, milestones 1/2/3). The thing tickets
will eventually hang off.

| Column | Meaning |
|---|---|
| `user_id` **restrict** | Deleting a user with orders fails at the DB. Deactivate instead |
| `order_number` unique | `SO-000001` |
| `status` | `draft` → `awaiting_payment` → `active` → `in_delivery` → `completed`; `cancelled` from any non-terminal |
| `account_manager_id` nullOnDelete | Owning staffer; order survives their departure |
| `subtotal_net` | **List price BEFORE discount** (easy to misread) |
| `discount_total` | What was taken off |
| `vat_total` | Sum of per-line VAT (lines can differ) |
| `total_gross` | = subtotal_net − discount_total + vat_total |
| `deposit_percent` nullable | Agreed split. **Null → preview falls back to 50%** |
| `reverse_charge` | Cached from line resolution (EU B2B) |
| `started_at` / `completed_at` / `cancelled_at` | Separate stamps, not derived from status |

Index `(user_id, status)` — "this client's active orders".

## `service_order_items` — order lines

| Column | Meaning |
|---|---|
| `service_id` nullable **nullOnDelete** | Line keeps its own description + prices if the catalogue entry goes |
| `quantity` decimal(8,2) | Decimal so 7.5 hours works |
| `unit` | "hour", "month", "page" |
| `unit_price_net`, `discount_percent` | Per-unit price and per-line % off |
| `vat_rate` | **Resolved** rate (0.00 for EU B2B), frozen at creation |
| `line_total_net` / `line_total_gross` | See billing-money-math.md for the formulas |
| `sort_order` | Display order **and** the join key for balance remainders |

## `invoices` — extended in place (legal document)

Pre-existing table. Three raw `ALTER`s first (no doctrine/dbal):
`subscription_id` + `package_id` → nullable, `status` ENUM → `VARCHAR(32) default 'draft'`.

| Column | Meaning |
|---|---|
| `service_order_id` nullable | Which order this bills |
| `type` nullable | `deposit`\|`milestone`\|`balance`\|`one_off`\|`recurring`\|`credit_note`. **Null = legacy plan-tier row** |
| `parent_invoice_id` | Self-ref: a credit note points at what it corrects |
| `subtotal_net`, `discount_total`, `vat_total` | Same meanings as the order |
| `vat_rate` nullable | *Display only* — null on mixed-rate invoices; `vat_total` is the truth |
| `total_gross`, `amount_paid` | |
| `amount_due` | Stored, not computed. Recomputed under the payment row lock |
| `due_at` indexed | Stamped at issue from NET-14. Dunning queries it |
| `sent_at`, `cancelled_at` | |
| **`locked_at`** | The immutability latch — see billing-invariants.md |
| `reference`, `terms`, `notes` | Client PO + printed text |
| `reverse_charge` | Drives the Art. 196 note on the PDF |
| `public_token` (64) unique + `public_token_expires_at` | Pay-link credential, 30 days |
| `deleted_at` | §132 BAO 7-year retention — soft delete only |

Caveat: `down()` leaves `subscription_id` nullable on purpose (NULL rows pre-exist;
restoring NOT NULL dies as MySQL 1138).

## `invoice_items` — frozen snapshot of the lines

Column-identical to `service_order_items` (`invoice_id` instead). **Copied, not
referenced**, so editing an order can never rewrite an issued invoice.

## `payments` — one money movement

Many per invoice: partials, retries, refunds.

| Column | Meaning |
|---|---|
| `invoice_id`, `user_id` **restrict** | Financial records under retention |
| `provider` | `stripe`\|`paypal`\|`bank_transfer`\|`manual` |
| `provider_payment_id` indexed | PaymentIntent / capture id |
| **`idempotency_key` unique** | `stripe:pi:{id}`, `paypal:pmt:{id}`, `manual:{…}`. The unique index is the last defence in the webhook-vs-redirect race |
| `amount` | Capped server-side at the invoice's open amount |
| `status` | `pending`\|`processing`\|`awaiting_confirmation`\|`succeeded`\|`failed`\|`refunded`\|`partially_refunded` |
| `method_brand` / `method_last4` | "visa" / "4242". Never a full number |
| `paid_at`, `failure_reason` | |
| `confirmed_by_admin_id` + `confirmed_at` | Bank-transfer accountability |
| `refunded_amount` | Cumulative — lets webhooks apply only the *delta* |
| `metadata` json | Provider payloads; SCA client secret when parked |
| `deleted_at` | Soft delete only |

Status meanings: `pending` = row created before calling the provider (key exists
before any race). `awaiting_confirmation` = SEPA, client claims payment, applies
**nothing**. `processing` = SCA needs the client to finish 3DS.

## `payment_proofs` — SEPA slips

| Column | Meaning |
|---|---|
| `file_path` | On the **private** disk; streamed via an admin-guarded route |
| `mime_type` | **Content-sniffed**, not the browser's claim. 10 MB cap |
| `status` | `pending`\|`accepted`\|`rejected` |
| `reviewed_by_user_id`, `reviewed_at`, `rejection_reason` | Reason mandatory on reject |

**Uploading never marks anything paid.** Only an admin accepting does.

## `payment_methods` — saved instruments

`provider_token` only (Stripe pm id / PayPal agreement) plus `brand`, `last4`,
`exp_month`, `exp_year`, `is_default`, `verified_at`. Token is in the model's
`$hidden`. **No column can hold a PAN or CVC** — storing card data is impossible
by schema, not just discouraged.

## `recurring_plans` — recurring revenue (replaces Subscription)

Per **service**, not per account. A client can run hosting + SEO independently,
and neither gates portal access.

| Column | Meaning |
|---|---|
| `service_id` **restrict** | A service with live plans can't be hard-deleted |
| `payment_method_id` nullable | **Null is a real state** — card deleted → warning in My Services, plan doesn't break |
| `interval`, `amount_net`, `vat_rate` | Price changes take effect next cycle |
| `state` | `active`\|`paused`\|`past_due`\|`cancelled` |
| `current_period_start` / `_end` | The Leistungszeitraum printed on the invoice |
| `next_charge_at` | **Null while `past_due`** — that's how a parked plan stops being swept |
| `failure_count` | Position on the 1/3/7-day ladder. **Not** incremented for SCA |
| `last_failure_reason` | Kept so a human knows why it parked |

Index `(state, next_charge_at)` — exactly what the daily sweep filters on.

## `invoice_reminders` — dunning schedule

| Column | Meaning |
|---|---|
| `offset_days` **signed** | Relative to `due_at`. Negative = before due (courtesy reminders possible). Defaults +3/+7/+14/+21 |
| `channel` | `email`\|`push`\|`both` |
| `scheduled_for` indexed | Absolute date the daily sweep queries |
| `sent_at` | Null = pending. Stamped on send |
| `created_by_user_id` | Null = one of the four automatic defaults |

Paid/cancelled invoices get their unsent reminders **pruned**.

## `invoice_activities` — append-only audit trail

`actor_user_id` (**null = system**: webhook or cron), `event` indexed
(`created`, `issued`, `payment_received`, `proof_uploaded`, `credit_noted`, …),
`description`, `metadata` json, `created_at`.

**No `updated_at` column exists** — not unused, absent. The model sets
`UPDATED_AT = null`. Append-only is structural, not a convention.

## `invoice_sequences` — the gapless counter

`prefix` + `year` + `last_number`, **unique(prefix, year)**. Allocated with
`SELECT … FOR UPDATE`. The unique index also settles the first-allocation-of-a-year
race: the losing insert re-selects the winner's row under lock.

## `users` — two additions

| Column | Meaning |
|---|---|
| `country_code` (2) | **Drives VAT.** AT → 20%, EU → RC/B2C, non-EU → 0%. Null → domestic |
| `company_name` | Printed above the address |
| `vat_id` (20) | The UID. **Its presence is what makes an EU sale B2B** |
| `origin` indexed | `guest_checkout`\|`admin`\|`self_signup` |

---

## FK deletion policy (deliberate per table)

- **restrict** — orders, invoices, payments, plans: deleting a user with billing
  history fails *at the database*, not at an app check someone can bypass.
- **cascade** — lines, proofs, reminders, activities, payment methods: operational
  data, goes with its parent.
- **nullOnDelete** — catalogue and staff pointers: the row survives, loses the link.

Plus soft deletes on invoices and payments. Financial history is structurally hard to lose.
