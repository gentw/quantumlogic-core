# Billing & Payments

## Problem

QuantumLogic sells **services** — website builds, SEO retainers, hosting, maintenance,
consulting — and needs to get paid for them. Three different money shapes:

1. **Prepayment / deposit** — 50% up front on a new website, balance on delivery
2. **One-off** — a fixed-scope job, paid in full
3. **Recurring** — hosting and SEO retainers, monthly or annual

The platform today has none of that. What it has is a **SaaS plan-tier subscription
system** inherited from SentriGate: Basic/Premium packages, a 7-day trial with abuse
protection, upgrade/downgrade proration, and a `check-subscription` middleware that
**locks a client out of the portal** unless they hold an active plan. For an agency this
is actively wrong — a customer whose website we are mid-build on has no "plan" and would
be redirected to a pricing page.

There is also no way for a prospect to become a paying customer without an account. Today
a new customer must be registered by an admin (or self-register through one of four
coexisting signup endpoints) before they can pay anything.

---

## Root Cause

The billing domain was modelled around **account tiers**, not around **work sold**.

- The billable object is `Package` (a plan), not a service the agency delivers
- `Invoice` is welded to `subscription_id` — `InvoiceService::recordForPeriod()` is keyed
  on `(subscription_id, billing_period_start)`, so an invoice cannot exist without a
  subscription cycle
- `SubscriptionPayment` records money against a *subscription*, not against an invoice, so
  partial payments and deposits are unrepresentable
- Access control is entangled with billing (`check-subscription` gating the whole portal)
- Payment confirmation depends on a **browser redirect** (`paypal/success`) rather than a
  webhook, so a closed tab loses the payment
- There is no customer-facing purchase path at all — no service catalogue, no order, no
  checkout

---

## Solution

Introduce **Billing & Payments**: an order-and-invoice model with pluggable payment rails,
covering the full Client ↔ Admin money lifecycle including guest purchase.

Retire the SaaS subscription system behind a feature flag (same treatment as the security
module) and reuse only what genuinely transfers: the `Invoice` table, the PayPal client
plumbing, and the idempotency patterns from `InvoiceService`.

### Domain vocabulary

| Noun | Meaning |
|---|---|
| **Service** | Catalogue entry — what the agency sells (Website Build, SEO Retainer, Hosting, Maintenance) |
| **Service Order** | A specific customer's purchase; spans one or more invoices |
| **Invoice** | A bill against an order — typed `deposit`, `milestone`, `balance`, `one_off`, `recurring`, `credit_note` |
| **Payment** | One money movement against an invoice (Stripe, PayPal, bank transfer, manual) |
| **Payment Method** | A saved instrument — Stripe card, PayPal billing agreement |
| **Recurring Plan** | Per-service recurring charge (hosting, SEO retainer) — replaces `Subscription` |

### Rails

- **Stripe** — cards + SCA/3DS, via `stripe/stripe-php` (not Cashier — Cashier is
  subscription-shaped, we are invoice-shaped). Webhook is the source of truth.
- **PayPal** — existing `srmklive/paypal`, rebuilt against `Invoice` instead of
  `Subscription`, plus a webhook so the redirect is no longer load-bearing.
- **SEPA bank transfer** — IBAN/BIC + EPC QR code, client uploads the bank slip, admin
  reconciles.

Cash-in-person from the source screenshots is **dropped**.

---

## Changes Required

---

### Phase 0 — Retire the subscription plan module

Mirror the security-module pattern exactly. Code stays on disk; flags decide reachability.

**Backend**
- `api/config/features.php` — add `'subscription_plans' => (bool) env('FEATURE_SUBSCRIPTION_PLANS', false)`
- `.env.example` — add `FEATURE_SUBSCRIPTION_PLANS=false`
- `api/routes/api.php` — wrap `client/sub/*` and the trial routes in `if (config('features.subscription_plans'))`
- `CheckSubscription` middleware — return `$next($request)` unchanged when the flag is off.
  **Do not** remove the alias or change the 403 shape; the flag is the switch, so
  re-enabling stays a one-line change.
- `check.feature:NAME` — allow-all when the flag is off. Leave the middleware wired.
- Leave the renewal scheduler command registered but no-op when the flag is off.

**Frontend**
- `web/src/utils/features.js` — add `subscriptionPlans` to `appFeatures`; add
  `client-pricing`, `client-plans-billing`, `client-invoice-change-plan` to the disabled
  route prefixes (a `SUBSCRIPTION_ROUTE_PREFIXES` list alongside `SECURITY_ROUTE_PREFIXES`)
- `web/.env.example` — `VITE_FEATURE_SUBSCRIPTION_PLANS=false`
- `guards.js` — the client subscription gate must not fire when the flag is off. Keep the
  single-`next()`-per-navigation invariant.
- Navigation — drop plan/pricing entries, add the new Billing entries (below)

**Docs**
- `docs/modules/subscriptions/README.md` — recovery guide mirroring
  `docs/modules/security/README.md`: what it did, what turning it back on requires, and
  the fact that `RecurringPlan` now owns recurring revenue.

**Data**
- Private beta, so most likely no live subscriptions. If beta data exists, write a one-off
  `php artisan billing:migrate-subscriptions` command mapping active `Subscription` rows to
  `RecurringPlan`. Do not migrate trials.

---

### Phase 1 — Domain model

New migrations under `api/database/migrations/` — **never edit historical migrations**.
Every FK gets explicit `onDelete()`. Every column that gets filtered or joined gets an index.

#### `services`
Catalogue of what the agency sells.

`id, name, slug (unique), description, category, billing_type (enum: one_off|recurring|milestone),
default_price_net, default_billing_interval (enum: monthly|yearly|null), vat_rate,
supports_deposit (bool), default_deposit_percent, is_publicly_orderable (bool), active (bool),
sort_order, timestamps`

Seeder with the real catalogue: Website Development, Web Application, SEO Retainer,
Hosting, Maintenance & Support, Consulting.

#### `service_orders`
`id, user_id (FK), order_number (unique), status (enum: draft|awaiting_payment|active|
in_delivery|completed|cancelled), account_manager_id (FK users, nullable), currency,
subtotal_net, discount_total, vat_total, total_gross, deposit_percent, notes,
reverse_charge (bool), started_at, completed_at, cancelled_at, timestamps`

#### `service_order_items`
`id, service_order_id (FK cascade), service_id (FK nullOnDelete), description, quantity,
unit, unit_price_net, discount_percent, vat_rate, line_total_net, line_total_gross,
sort_order, timestamps`

#### `invoices` — extend the existing table
Add: `service_order_id (FK nullOnDelete), type (enum: deposit|milestone|balance|one_off|
recurring|credit_note), parent_invoice_id (FK, for credit notes), account_manager_id,
subtotal_net, discount_total, vat_total, vat_rate, total_gross, amount_paid, amount_due,
due_at, sent_at, cancelled_at, reference, terms, notes, reverse_charge (bool),
public_token (unique, nullable), public_token_expires_at, locked_at`

Keep `subscription_id` nullable for the retired module. Do **not** drop it.

#### `invoice_items`
Snapshot of the order lines at issue time — invoices must not shift when an order is edited.
Same shape as `service_order_items`, plus `invoice_id (FK cascade)`.

#### `payments`
Generalises `SubscriptionPayment`. Leave the old table alone.

`id, invoice_id (FK), user_id (FK), provider (enum: stripe|paypal|bank_transfer|manual),
provider_payment_id (indexed), provider_customer_id, idempotency_key (unique),
amount, currency, status (enum: pending|processing|awaiting_confirmation|succeeded|failed|
refunded|partially_refunded), method_brand, method_last4, paid_at, failure_reason,
confirmed_by_admin_id (FK users, nullable), confirmed_at, refunded_amount, metadata (json),
timestamps`

#### `payment_proofs`
`id, payment_id (FK cascade), invoice_id (FK), uploaded_by_user_id (FK), file_path,
original_name, mime_type, size_bytes, note, status (enum: pending|accepted|rejected),
reviewed_by_user_id, reviewed_at, rejection_reason, timestamps`

Stored on a **private** disk. Served only through a signed, authorised controller route —
never a public URL.

#### `payment_methods`
`id, user_id (FK cascade), provider (enum: stripe|paypal), provider_token, provider_customer_id,
brand, last4, exp_month, exp_year, is_default (bool), verified_at, timestamps`

Never store PAN, CVC or full card data — Stripe tokens only.

#### `recurring_plans`
Per-service recurring revenue. Replaces `Subscription`.

`id, user_id (FK), service_order_id (FK), service_id (FK), payment_method_id (FK nullable),
interval (enum: monthly|yearly), amount_net, vat_rate, currency, state (enum: active|paused|
past_due|cancelled), current_period_start, current_period_end, next_charge_at (indexed),
failure_count, last_failure_reason, cancelled_at, timestamps`

#### `invoice_reminders`
`id, invoice_id (FK cascade), offset_days (int, negative = before due), channel (enum:
email|push|both), scheduled_for, sent_at, created_by_user_id, timestamps`

#### `invoice_activities`
Append-only audit trail — essential when a payment is disputed.

`id, invoice_id (FK cascade), actor_user_id (nullable — null = system), event (string),
description, metadata (json), created_at`

#### `invoice_sequences`
Backs gapless numbering (see below).

`id, year (unique per prefix), prefix, last_number, timestamps`

---

### Phase 1b — Invoicing rules (Austrian law)

These are legal requirements, not preferences. They change the admin UI from the source
screenshots, which allowed free editing of issued invoices.

**Gapless sequential numbering.** Austrian invoices require a continuous number series
(`fortlaufende Rechnungsnummer`). Format `QL-2026-0001`.
- Allocate inside a transaction with `SELECT ... FOR UPDATE` on `invoice_sequences`.
- **Never** `max(id)+1` or `count()+1` — both race and both leave gaps.
- Numbers are allocated at **issue** (draft → sent), not at draft creation, so abandoned
  drafts do not burn numbers.

**Immutability after issue.** Once an invoice is `sent`, `locked_at` is stamped and the row
becomes read-only. Corrections happen via a **credit note** (`type: credit_note`,
`parent_invoice_id` set), never by editing. The existing admin "edit invoice" screen must
enforce this — editable only while `draft`.

**VAT.**
- Austrian standard rate **20%**, configurable per service and per line
- **Reverse charge** for EU B2B with a valid UID: 0% VAT, plus the mandatory note
  *"Reverse charge — Steuerschuldnerschaft des Leistungsempfängers (Art. 196 MwStSystRL)"*.
  Optional VIES validation of the customer UID.
- Non-EU customers: 0% VAT (export of services)
- Store the resolved rate **on the line**, never recompute historical invoices from config

**Mandatory invoice content** — from `config/company.php`, never hardcoded in a Blade
template: legal name, address, **UID-Nummer** (ATU…), Firmenbuchnummer + registration court,
IBAN/BIC. Plus per-invoice: invoice number, issue date, delivery/service period, customer
name + address (+ UID for reverse charge), line detail, net/VAT/gross split.

**Payment terms.** Default NET 14 (`BILLING_PAYMENT_TERMS_DAYS`), configurable per invoice.
The source screenshot's NET 7 is too tight for B2B.

**Retention.** 7 years (§132 BAO) — invoices and payments are **soft-deleted only**. Admin
"delete invoice" from the screenshot becomes **cancel** (draft) or **credit note** (issued).

**Overdue is derived, not stored.** `due_at < now() && amount_due > 0`. Expose as an
accessor + query scope. Do not add a cron job just to flip a status column.

---

### Phase 1c — Services

Thin controllers, logic in `api/app/Services/`, following `SubscriptionService` as the
reference pattern.

| Service | Responsibility |
|---|---|
| `ServiceCatalogueService` | Catalogue CRUD, pricing resolution |
| `ServiceOrderService` | Order creation, totals, deposit split, state transitions |
| `InvoiceNumberService` | Gapless sequential allocation under lock |
| `BillingInvoiceService` | Issue, send, credit-note, cancel, recompute `amount_due` |
| `TaxService` | VAT rate resolution, reverse-charge determination, optional VIES lookup |
| `PaymentService` | Provider-agnostic orchestration, idempotent application of payments to invoices |
| `StripeGateway` | PaymentIntents, SetupIntents, refunds, webhook verification |
| `PayPalGateway` | Orders v2 create/capture/refund, webhook verification |
| `BankTransferService` | EPC QR payload, proof intake, admin accept/reject |
| `RecurringBillingService` | Cycle advance, charge attempt, retry/dunning, state machine |
| `ClientAccountService` | Find-or-create a client account (used by guest checkout **and** the existing signup endpoints) |

`PaymentService::apply()` must be **idempotent** and take a row lock on the invoice —
webhooks retry, and Stripe and the browser redirect can land simultaneously.

Custom exceptions in `api/app/Exceptions/`: `InvoiceLockedException`,
`PaymentAlreadyAppliedException`, `PaymentGatewayException`, `InvalidInvoiceStateException`.
Shaped in `Handler.php` — never a per-controller error shape.

---

### Phase 2 — Payment rails

#### Stripe

- `composer require stripe/stripe-php`
- Config `api/config/stripe.php`; env `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`
- **PaymentIntent per payment attempt**, `idempotency_key` = the `payments.id`
- SCA/3DS handled by Stripe Payment Element — mandatory in the EU, do not build a bespoke
  card form
- **SetupIntent** to save a card, for recurring charges and for the guest-checkout identity
  check
- Webhook `POST /v1/webhooks/stripe` — **public route, no `auth:api`**, signature-verified
  via `Webhook::constructEvent()`. Reject unverified with 400.
  Handle: `payment_intent.succeeded`, `payment_intent.payment_failed`,
  `charge.refunded`, `charge.dispute.created`, `setup_intent.succeeded`
- The **webhook is the source of truth**. The browser return only navigates the UI; it must
  never be the thing that marks an invoice paid.
- Webhook events must be replay-safe — store `provider_payment_id` and short-circuit on
  a payment already in a terminal state.

#### PayPal

- Rebuild `PayPalController` against `Invoice` + `Payment`:
  `custom_id` = the `payments.id`, not the invoice id
- Add webhook `POST /v1/webhooks/paypal` — verify with `verify-webhook-signature`;
  handle `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.REFUNDED`
- Keep the existing success/cancel redirects for UX only
- Reuse the existing capture-idempotency logic — it is sound, it just needs repointing

#### SEPA bank transfer

- `GET /v1/client/invoices/{id}/bank-details` → account holder, IBAN, BIC, bank, reference
  (= invoice number), plus an **EPC QR (Giro-Code)** payload the client scans in their
  banking app. `composer require endroid/qr-code`.
- `POST /v1/client/invoices/{id}/payment-proof` — multipart upload (pdf/jpg/png, max 10 MB,
  **validate real MIME**, not just the extension), stored on a private disk
- Creates a `Payment` in `awaiting_confirmation` and moves the invoice to
  `awaiting_confirmation`. The invoice is **not** paid until an admin accepts.
- Admin accept → `Payment` succeeded + invoice recomputed; reject → reason + client notified

---

### Phase 3 — Client billing UI

Vue 3 `<script setup>` + Vuetify 3 only. **Light and dark parity required.** All HTTP through
`web/src/utils/api.js`; API calls live in the Pinia store or a composable, never inline in a
component.

Screenshots are the starting point — deviations below are deliberate.

#### `pages/client/billing/index.vue` — *from `client/subscription/transactions.png`*
KPI strip (**Outstanding balance**, **Next due**, **Last invoice**) + a **Pay now** CTA, a
status filter, search, and a `<v-data-table-server>` of invoices: No. / Status / Service /
Issued / Due / Amount / Actions (View, Pay).

Changes from the screenshot: server-side pagination and filtering (the mock paginates 5 rows
client-side, which will not survive a real client's history); status chips extended to
Draft / Sent / Awaiting confirmation / Partially paid / Paid / Overdue / Cancelled /
Credited; a **Download PDF** row action.

#### `pages/client/billing/invoices/[id].vue` — *from `client/subscription/invoice-details.png`*
Header with invoice number + Download PDF. Left: invoice details (Billed to, our legal
entity, customer UID, address, issue date, due date, reference, **your account manager**,
terms). Right: amount due, status chip, net / discount / VAT / gross breakdown, outstanding
balance, **Pay now** CTA, late-payment note. Below: line items table.

Changes: the mock's **barcode is replaced with an EPC QR code** — scannable in any SEPA
banking app, actually useful rather than decorative. VAT 20% not 18%. "Nr. biznesi/tvsh"
becomes **UID-Nummer**. Reverse-charge note rendered when applicable. Payment history
section listing every `Payment` against the invoice — the mock had no way to show a partial
payment, which the deposit model needs.

#### `pages/client/billing/checkout/[id].vue` — *from `payment-method-choose{,2,3}.png`*
Split pane. Left: invoice summary (total, breakdown, number, billed to, due date,
reference). Right: payment method radio group, each expanding to its own panel.

Changes: the four hardcoded Kosovan banks are **replaced by the Stripe Payment Element** —
one integration, every EU card, SCA built in. **Cash-in-person and its +€2 collection fee
are dropped.** Methods: Card (Stripe) / PayPal / Bank transfer. A saved card, when present,
is offered as a one-click option.

#### `pages/client/billing/pay.vue` — *from `client/subscription/pay-invoice.png`*
Account-level payment. Left: customer details + outstanding balance. Right: Pay latest /
Pay selected invoices / Pay a custom amount / Pay everything.

Kept largely as designed — the custom-amount option earns its place here because deposits
and part-payments are core to the model. Custom amounts allocate oldest-invoice-first, and
that allocation must be shown before the client confirms.

#### `pages/client/billing/payment-methods.vue` — new
Saved cards and PayPal agreements: add, set default, remove. Needed for recurring hosting
and SEO, which have no equivalent in the screenshots.

#### `pages/client/services/index.vue` — new
The client's active services and orders, with each order's invoice schedule and progress.
Closes the "customers/services not built" gap from the project overview.

#### Components — `web/src/components/billing/`
`InvoiceStatusChip.vue`, `InvoiceSummaryCard.vue`, `PaymentMethodPicker.vue`,
`StripePaymentElement.vue`, `BankTransferDialog.vue` (*from `wise-transfer-payment.png`* —
one SEPA account + EPC QR, not four bank blocks), `PaymentProofDialog.vue`
(*from `wise-transfer-payment-proof.png`* — kept as designed, it is exactly right),
`InvoiceLineItemsTable.vue`, `PaymentHistoryList.vue`

#### Composables & store
`composables/useBillingInvoices.js`, `useStripeCheckout.js`, `usePaymentMethods.js`;
`stores/billing.js` (Pinia setup syntax).

#### Navigation
Client: **Billing** (`client-billing`) and **My Services** (`client-services`), replacing the
retired pricing/plans entries.

---

### Phase 4 — Admin billing UI

#### `pages/admin/invoices/index.vue` — *from `admin/subscription/manage-invoices.png`*
Rework the existing screen. Search, a **filter drawer** with per-status show/hide toggles and
colour dots, **New invoice** CTA, table: No. / Status / Billed to / Account manager / Issued /
Due / Amount / Actions.

Changes: the row menu's **"Delete invoice" becomes "Cancel"** (drafts) or **"Issue credit
note"** (issued) — legal retention forbids deletion. Added row actions: **Record manual
payment**, **Send reminder**, **Send invoice**. Added columns: **Paid** and **Outstanding**,
so partial payments are visible at a glance.

#### `pages/admin/invoices/[id].vue` — *from `admin/subscription/invoice-details.png`*
Invoice detail with the right rail from the mock: **Add reminder** (3 / 7 / 14 days after due,
plus final notice) and a searchable **Client** picker. Plus the activity/audit timeline and the
payment history.

Changes: fields are editable only while `draft` — after issue the screen is read-only and
offers a credit note instead.

#### `pages/admin/invoices/add-invoice.vue` — *from `admin/subscription/add-new-invoice.png`*
The mock's modal asks you to pick a client **and then pick an invoice** in order to create an
invoice, which is circular. Restructured to:

1. Pick or create a client
2. Pick an existing Service Order, or build one from the service catalogue
3. Pick invoice type — **Deposit (%) / Milestone / Balance / One-off / Recurring**
4. Set due date, reference, terms, notes
5. Preview totals (net / discount / VAT / gross, reverse charge applied if relevant)
6. Save as draft, or issue and send

#### `pages/admin/payments/reconciliation.vue` — new, and the piece the screenshots were missing
Bank transfer proof queue: pending proofs, the uploaded slip, expected vs claimed amount,
**Accept** (creates the succeeded `Payment`, recomputes the invoice, notifies the client) or
**Reject** with a reason. Without this screen the bank transfer flow has no ending.

#### `pages/admin/payments/index.vue` — new
All payments across clients: provider, status, amount, invoice, date. Filter and export.

#### `pages/admin/services/index.vue` + `[id].vue` — new
Service catalogue CRUD: price, VAT rate, billing type, deposit default, public orderability.

#### `pages/admin/orders/index.vue` + `[id].vue` — new
Service orders: status, line items, invoice schedule, account manager, per-order balance.

#### `pages/admin/clients/[id].vue` — extend
Add a **Billing** tab: lifetime value, outstanding balance, invoices, payments, saved methods.

#### Navigation
Admin: **Invoices** (existing), **Payments**, **Services**, **Orders**.

---

### Phase 5 — Guest checkout and public pay links

A prospect must be able to buy without an account: card details plus a real 50% deposit are
sufficient identity verification, and the account is created as a by-product.

#### Public routes — no `auth:api`, all rate-limited
- `GET /v1/public/services` — publicly orderable catalogue
- `POST /v1/public/checkout/quote` — price a selection, no persistence
- `POST /v1/public/checkout/start` — name, email, company, optional UID, selected services
  → creates a **provisional** client `User` (no password, `email_verified_at` null,
  `origin = guest_checkout`), a `ServiceOrder`, and a deposit `Invoice`; returns the
  `public_token` and a Stripe client secret
- `GET /v1/public/invoices/{token}` — invoice for a public pay link
- `POST /v1/public/invoices/{token}/pay` — start a payment against it

#### Account creation constraint
**Do not add a fifth signup endpoint.** `CLAUDE.md` is explicit and four already coexist.
Guest checkout creates its account through `ClientAccountService::findOrCreateForBilling()`,
an internal service. Point at least the newest of the four existing endpoints at that same
service so the consolidation debt shrinks rather than grows.

Existing email → attach the order to that account and **require login before payment**.
Never let an anonymous request pay onto an established account, and never leak whether the
email exists via a differing response shape.

#### On successful deposit payment
1. Provisional account is activated
2. Welcome email with a **set-password link** — reuse the existing `ResetCodePassword` flow,
   do not invent a second token mechanism
3. Order moves to `active`; admins notified in-app + push
4. Receipt emailed; client can log straight in and see the order

#### Public token security
64-char cryptographically random, unique-indexed, **expiring** (default 30 days), single
purpose. Rate-limit by IP and by token. The public invoice payload exposes only what the pay
page needs — amount, line descriptions, seller details, due date. No other invoices, no
account data, no client list.

#### Public pages — `blank` layout
`pages/pay/[token].vue` — public invoice pay page (admin emails this to clients who never log in)
`pages/order/index.vue` — public catalogue + guest checkout
`pages/order/success.vue` — confirmation + set-password prompt

---

### Phase 6 — Recurring services

Hosting and SEO retainers. `RecurringBillingService` + a scheduled command.

- `php artisan billing:charge-recurring` — daily, `withoutOverlapping()`, per-row locks.
  Carry over the hardening from the subscription scheduler; that part was done right.
- Each cycle: generate the invoice → charge the saved payment method → on success mark paid
  and email a receipt → on failure increment `failure_count`, set `past_due`, notify client
  **and** admin
- Retry schedule 1 / 3 / 7 days, then `past_due` and hand off to admin — never silently cancel
  a customer's hosting
- Client can pause or cancel from `pages/client/services/index.vue`; admin can do the same
  and can change price with effect from the next cycle
- SCA: off-session charges can require authentication. Handle
  `payment_intent.requires_action` by emailing the client an authentication link rather than
  treating it as a hard failure.

---

### Phase 7 — Documents, notifications, tests, docs

#### Invoice PDF
Blade template + `html2pdf.js` on the client side matching the existing preview approach, or
server-side generation for emailed invoices. Includes the EPC QR, the full legal footer from
`config/company.php`, and the reverse-charge note when applicable. **Never hardcode the
company details or the SPA host** — `config('app.frontend_url')` for links.

#### Mail — `api/app/Mail/`
`InvoiceIssuedMail`, `PaymentReceiptMail`, `PaymentFailedMail`, `InvoiceReminderMail`,
`PaymentProofReceivedMail` (admin), `PaymentProofRejectedMail`, `GuestWelcomeMail`,
`RecurringChargeFailedMail`. Brand-consistent with the existing templates.

#### Notifications
In-app + FCM on: invoice issued, payment received, proof uploaded (admins), proof
accepted/rejected, payment failed, invoice overdue.

#### Dunning
`php artisan billing:send-reminders` — daily, driven by `invoice_reminders`. Defaults per the
admin screenshot: 3 / 7 / 14 days after due, plus a final notice. Queue the sends.

#### Jobs — `api/app/Jobs/`
`ProcessStripeWebhookJob`, `ProcessPayPalWebhookJob`, `SendInvoiceEmailJob`,
`ChargeRecurringPlanJob`, `GenerateInvoicePdfJob`. Webhook handlers acknowledge fast and
queue the work — providers time out and retry otherwise.

#### Tests — `api/tests/`
Non-negotiable coverage:
- Invoice numbering is gapless and unique under **concurrent** issue
- VAT: domestic 20%, EU B2B reverse charge, non-EU zero-rate
- Deposit split: 50/50 sums exactly to the order total (no rounding leak)
- Stripe webhook: valid signature applies once; **replayed event is a no-op**; bad signature 400s
- PayPal capture idempotency survives a double callback
- Payment application is idempotent under a simultaneous webhook + redirect
- Bank proof accept/reject transitions, and that a pending proof does **not** mark paid
- Guest checkout creates exactly one user + one order; a repeat email does not duplicate
- Public token: expiry, rate limit, and no data leakage beyond the invoice
- Invoice immutability after issue; credit note is the only correction path
- Recurring charge failure → `past_due` + retry scheduling, never silent cancellation

`cd api && php artisan test` and `./vendor/bin/pint` must pass.

#### Config & env

`api/.env.example`:
```
FEATURE_SUBSCRIPTION_PLANS=false
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
BILLING_CURRENCY=EUR
BILLING_VAT_RATE=20
BILLING_PAYMENT_TERMS_DAYS=14
BILLING_DEPOSIT_PERCENT=50
BILLING_PUBLIC_TOKEN_TTL_DAYS=30
COMPANY_LEGAL_NAME=
COMPANY_ADDRESS=
COMPANY_UID=
COMPANY_FIRMENBUCH_NR=
COMPANY_REGISTER_COURT=
COMPANY_IBAN=
COMPANY_BIC=
COMPANY_BANK_NAME=
```

`web/.env.example`:
```
VITE_FEATURE_SUBSCRIPTION_PLANS=false
VITE_STRIPE_PUBLISHABLE_KEY=
```

New config files: `api/config/stripe.php`, `api/config/billing.php`, `api/config/company.php`.

#### Agents & skills sync
`context/ai-interaction.md` requires these to move in the same change:
- `.claude/agents/auth-auditor.md` — guest checkout account creation, public token auth,
  webhook routes that intentionally bypass `auth:api`
- `.claude/agents/code-scanner.md` and `.claude/agents/refactor-scanner.md` — the new
  `app/Services/` set, new middleware, the subscription module now flagged off
- `.claude/agents/ui-reviewer.md` — new route groups: client billing, client services,
  public `pay`/`order`, admin payments/services/orders
- `docs/modules/subscriptions/README.md` — new dormant-module recovery guide
- `CLAUDE.md` + `context/project-overview.md` — new capability rows, updated route map,
  subscription module marked dormant

---

## Commit plan

One commit per phase is the floor; the phases below that carry several separable pieces are
split further. Format, gates and the rest of the rules live in
[`../../.claude/skills/feature/actions/commit.md`](../../.claude/skills/feature/actions/commit.md).
Split a line further if it grows unwieldy — never merge two phases into one commit.

| Phase | Commits |
|---|---|
| 0 | `feat(billing): retire the subscription plan module behind a feature flag` |
| 1 | `feat(billing): add service, order, invoice and payment tables`<br>`feat(billing): add the billing Eloquent models, relations and scopes` |
| 1b | `feat(billing): allocate gapless invoice numbers and lock issued invoices`<br>`feat(billing): resolve VAT per line with EU reverse charge and non-EU zero rate` |
| 1c | `feat(billing): add the catalogue, order and invoice service layer`<br>`feat(billing): add tax resolution and idempotent payment application`<br>`feat(billing): add the billing domain exceptions and handler shaping` |
| 2 | `feat(billing): add the Stripe rail — Payment Element, SCA and webhooks`<br>`feat(billing): rebuild the PayPal rail against invoices with idempotent capture`<br>`feat(billing): add SEPA transfer with EPC QR and client proof upload` |
| 3 | `feat(web): add the client billing list and invoice detail`<br>`feat(web): add client checkout, pay-amount and saved payment methods`<br>`feat(web): add the client My Services view and billing nav` |
| 4 | `feat(web): add the admin invoice list and detail`<br>`feat(web): restructure create-invoice around draft-only editing`<br>`feat(web): add the bank transfer reconciliation queue and payments list`<br>`feat(web): add the admin service catalogue and orders screens` |
| 5 | `feat(billing): add guest checkout through ClientAccountService`<br>`feat(billing): add public pay links with scoped, expiring tokens` |
| 6 | `feat(billing): add recurring plans with retry and dunning handoff` |
| 7 | `feat(billing): add the invoice PDF with EPC QR and legal footer`<br>`feat(billing): add billing mail, in-app and FCM notifications`<br>`feat(billing): add the dunning reminder command`<br>`test(billing): cover numbering concurrency, VAT, webhook replay and immutability`<br>`docs: add the dormant subscription module guide and sync agents` |

Things that belong in a `Decisions:` block rather than being silently coded: every
screenshot deviation listed at the top of this spec, the VAT and reverse-charge wording
pending accountant sign-off, whether VIES is live-checked, and whether any beta
`Subscription` rows were migrated.

---

## Client ↔ Admin lifecycle

The end-to-end interaction this feature has to deliver:

```
                    CLIENT                          ADMIN
                      │                               │
  (a) guest ──────────┤ browses /order                │
                      │ picks services                │
                      │ pays 50% deposit (Stripe)     │
                      │ ──────────────────────────────►  order lands, admins notified
                      │ ◄── welcome + set password    │
                      │                               │
  (b) existing ───────┤                               ├─ creates Service Order
                      │                               ├─ issues Deposit invoice (50%)
                      │ ◄── email + in-app + push ────┤
                      │                               │
                      ├─ opens Billing                │
                      ├─ chooses a rail               │
                      │   ├ Stripe ──► webhook ───────►  invoice paid, receipt sent
                      │   ├ PayPal ──► webhook ───────►  invoice paid, receipt sent
                      │   └ Transfer ─► uploads proof ─►  reconciliation queue
                      │                               ├─ accepts / rejects proof
                      │ ◄── confirmation or reason ───┤
                      │                               │
                      │                               ├─ order → in_delivery, work starts
                      │                               ├─ on delivery: issues Balance invoice
                      │ ◄── email + in-app + push ────┤
                      ├─ pays balance ────────────────►  order → completed
                      │                               │
   recurring ─────────┤                               │
                      │ ◄── auto-charged each cycle ──┤  billing:charge-recurring
                      │ ◄── receipt / failure notice ─┤
                      │                               │
   overdue ───────────┤ ◄── dunning 3/7/14 days ──────┤  billing:send-reminders
                      │                               │
   dispute ───────────┤                               ├─ issues credit note + refund
                      │ ◄── credit note + refund ─────┤
```

---

## Out of Scope

- Quotes / proposals with client e-acceptance — a natural next feature, not this one
- Accounting-package export (BMD, RZL, DATEV)
- Multi-currency — EUR only
- Time tracking and hours-based billing
- Reviving the security module
- Tickets and the customer/company entity — separate features, though `ServiceOrder` is
  designed to have tickets hang off it later

---

## Risks

| Risk | Mitigation |
|---|---|
| Double-charging on webhook + redirect race | Idempotency keys, invoice row locks, terminal-state short-circuit |
| Invoice number gaps or duplicates | `FOR UPDATE` on a dedicated sequence table, allocate at issue, concurrency test |
| Guest checkout as an account-takeover vector | Existing email requires login before payment; identical response shape either way; rate limits |
| Payment proof upload abuse | Private disk, real MIME validation, size cap, authenticated signed serving |
| Rounding drift on a 50/50 split | Integer minor units internally; last invoice absorbs the remainder; explicit test |
| SCA failures on off-session recurring charges | Handle `requires_action` with an emailed authentication link, not a hard failure |
| Retiring subscriptions breaks the client route guard | Flag-gated on both sides; verify a flagless client can still reach the portal |
| Regulatory error on VAT | Rates stored per line at issue time; reverse charge explicit; confirm treatment with the accountant before go-live |
