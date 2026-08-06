# Current Feature: Billing & Payments

<!-- Feature Name -->

## Status

<!-- Not Started|In Progress|Completed -->

In Progress

## Goals

<!-- Goals & requirements -->

- **Retire the SaaS subscription module** behind `FEATURE_SUBSCRIPTION_PLANS` / `VITE_FEATURE_SUBSCRIPTION_PLANS`, mirroring the security-module pattern. Clients must no longer be locked out of the portal for lacking a plan. Code stays on disk; recovery guide at `docs/modules/subscriptions/README.md`.
- **Model what the agency actually sells**: `Service` catalogue (Website, SEO, Hosting, Maintenance, Consulting) → `ServiceOrder` + `ServiceOrderItem` → `Invoice` + `InvoiceItem` → `Payment`. Recurring revenue moves to a per-service `RecurringPlan`, not an account tier.
- **Support all three money shapes**: 50% deposit + balance on delivery, one-off, and recurring (hosting / SEO retainers). Milestone invoices fall out of the same model.
- **Three payment rails**: Stripe (Payment Element, SCA/3DS, saved cards), PayPal (rebuilt against invoices), and SEPA bank transfer with EPC QR + client proof upload + admin reconciliation. Cash-in-person from the source screenshots is dropped.
- **Webhooks are the source of truth** for both Stripe and PayPal — payment application must be idempotent under a simultaneous webhook and browser redirect. Today a closed tab loses a PayPal payment.
- **Guest checkout**: a prospect buys from the public site with no account — card details plus a real 50% deposit are the identity check. The account is created as a by-product via `ClientAccountService`, **not** a fifth signup endpoint. Plus public `/pay/{token}` links for clients who never log in.
- **Full Client ↔ Admin lifecycle**: admin issues → client notified (email + in-app + FCM) → client pays or uploads proof → admin confirms → receipt → dunning at 3/7/14 days → credit note and refund on dispute. Every transition written to an append-only audit trail.
- **Austrian invoicing compliance**: gapless sequential numbering allocated under lock at issue time, immutability after issue (corrections via credit note only), 20% VAT with EU B2B reverse charge and non-EU zero-rating, full legal footer from `config/company.php`, soft-delete-only 7-year retention.
- **Client UI** built from the screenshots: billing list with KPI strip, invoice detail (EPC QR replacing the decorative barcode), split-pane checkout, pay-balance/selected/custom-amount page, saved payment methods, and a My Services view.
- **Admin UI** built from the screenshots: invoice list with status filter drawer, invoice detail with the reminder + client rails, a restructured create-invoice flow, plus the screens the mocks were missing — bank transfer reconciliation queue, payments list, service catalogue, and orders.
- **Tests + docs**: concurrency test on invoice numbering, VAT and deposit-split correctness, webhook replay safety, guest-checkout account safety, invoice immutability. `.claude/agents/*` and module docs updated in the same change per `context/ai-interaction.md`.

## Notes

<!-- Any extra notes -->

Full spec: [`features/billing-and-payments.md`](features/billing-and-payments.md) — phased 0–7, with the
data model, the Austrian legal constraints, per-screenshot deviations and their reasoning, the
Client ↔ Admin lifecycle diagram, out-of-scope list, and risk table.

**Naming.** "Subscription" is retired as a product concept. The module is **Billing & Payments**;
the domain nouns are Service / Service Order / Invoice / Payment / Payment Method / Recurring Plan.
Client nav gets *Billing* and *My Services*; admin nav gets *Invoices*, *Payments*, *Services*, *Orders*.

**Source screenshots** live in `screenshots/{client,admin}/subscription/` and were built for a
Kosovan security company in Albanian. Everything is rebuilt in English for an Austrian agency.
Deliberate deviations, each justified in the spec: the four hardcoded local banks → Stripe Payment
Element; barcode → EPC QR (Giro-Code); cash-in-person +€2 fee → dropped; VAT 18% → 20%; NET 7 →
NET 14; "delete invoice" → cancel or credit note (legal retention); free editing of issued invoices
→ draft-only editing; client-side pagination → server-side.

**Constraints carried in from `CLAUDE.md` / `context/`:**
- Do **not** add a fifth signup endpoint — guest checkout routes through a shared internal service,
  and should shrink the existing four rather than add to them
- Do not touch the dormant security module
- Thin controllers, logic in `app/Services/`; FormRequests for validation; API Resources for responses
- SPA is JavaScript, Vue 3 + Vuetify 3 + Vuexy; light **and** dark parity required
- `check-subscription` keeps its 403 JSON shape — the flag is the switch, not a rewrite
- New migrations only; never edit historical ones

**Commit cadence.** One detailed commit per phase, in spec order, gates before each — the
phase → commit map is the `## Commit plan` table in
[`features/billing-and-payments.md`](features/billing-and-payments.md), the message format is in
[`../.claude/skills/feature/actions/commit.md`](../.claude/skills/feature/actions/commit.md).

**Open items to settle during implementation:**
- Confirm VAT treatment and the reverse-charge wording with the accountant before go-live
- Decide whether VIES UID validation is live-checked or admin-entered
- Confirm whether any live beta `Subscription` rows need migrating to `RecurringPlan`
- Populate the real `COMPANY_*` values (UID, Firmenbuchnummer, register court, IBAN/BIC)

## History

<!-- Keep this updated. Earliest to latest. The /feature complete action appends here automatically; deeper, topic-organized history lives in ./backend-history.md and ./frontend-history.md. -->

- 2026-05-16 — **Subscription System Hardening** — Replaced ad-hoc trial/subscribe/renewal flow with `SubscriptionState` enum + `SubscriptionService` (locked transactions, idempotent), `TrialService` (4-way abuse gate), `InvoiceService` (one invoice per cycle). Hardened scheduler with `withoutOverlapping` + per-row locks; PayPal callback now uses the locked service. SPA: `useTrialFingerprint` composable, relative `/v1/*` URLs via `VITE_API_BASE_URL`, pay-now actually calls `startTrial`, `guards.js` rewritten to single `next()` per navigation. Side fixes: `User::activeSubscription()` was calling a non-existent relation; `StartTrialRequest::authorize()` returned `false`; `upgradeDowngrade` route pointed to a missing controller method. Full manifest in [`../history/2026-05-16-subscription-system-hardening.md`](../history/2026-05-16-subscription-system-hardening.md).
- 2026-07-25 — **QuantumLogic pivot** — Repurposed the SentriGate security codebase as QuantumLogic Core, the agency's customers/services/tickets platform. Security module switched off behind `FEATURE_SECURITY_MODULE` / `VITE_FEATURE_SECURITY_MODULE` (code retained; recovery guide at [`../docs/modules/security/README.md`](../docs/modules/security/README.md)), and removed from the auto-loaded context files so it costs no tokens. Rebranded: theme purple `#301068` with a lightened `#7C5CD6` for dark mode plus `primary-accent` lavender, new `AppLogo.vue` with a theme-swapped brand mark cropped from the site wordmark, favicon, and copy across SPA pages and backend emails. Nav standardised to English. Git remote moved to `gentw/quantumlogic-core`. Side fixes: added the missing `DomainController` import that made every domain route 500; replaced two hard-coded `beta.sentrigate.com` email links with `config('app.frontend_url')`; swapped the old brand red `#dc3545` out of the email templates; removed a debug `console.log` plus dead locals from `guards.js` and the admin reports list; replaced a hard-coded light-grey that broke dark mode on the client dashboard widgets.
