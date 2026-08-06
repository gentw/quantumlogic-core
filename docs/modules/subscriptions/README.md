# Subscription plans module (retired)

> **This file is intentionally NOT loaded into AI context.** `CLAUDE.md` does not
> `@`-import it. Read it only when you are deliberately working on this module.

QuantumLogic Core inherited a **SaaS plan-tier subscription system** from SentriGate:
Basic/Premium packages, a 7-day trial with abuse protection, upgrade/downgrade with
proration, auto-renewal on a scheduler, and a middleware that locked clients out of the
portal without an active plan. For an agency that sells project work this model was
wrong, so on the Billing & Payments build it was switched **off, not deleted**.

Recurring revenue (hosting, SEO retainers) is now owned by **`RecurringPlan`** in
Billing & Payments — a per-service charge, not an account tier. If you re-enable this
module you are running two recurring-billing systems side by side; don't.

Nothing was dropped: no tables, no migrations, no models, no pages. Two flags decide
whether the module exists at runtime.

---

## The switch

| App | Flag | Default | Effect when `false` |
|---|---|---|---|
| `api/` | `FEATURE_SUBSCRIPTION_PLANS` | `false` | `client/sub/*` routes never registered; `check-subscription` and `check.feature` pass everything through; renewal sweep no-ops |
| `web/` | `VITE_FEATURE_SUBSCRIPTION_PLANS` | `false` | Router guard blocks pricing/plans/change-plan pages; the client subscription gate never fires |

Backend flag is read via `api/config/features.php` → `config('features.subscription_plans')`.
Frontend flag is read via `web/src/utils/features.js` → `appFeatures.subscriptionPlans`.

**Keep both in the same state.** Both flags fail **closed**: the SPA compares against the
exact string `true`, so `TRUE`, `1`, `yes` or an absent variable all leave the module off.

What deliberately did **not** change, so re-enabling stays a flag flip:
- the `check-subscription` alias and its **403 JSON shape** (`subscription_required: true`)
  — the SPA still keys its `/client/pricing` redirect on that exact contract
- the `check.feature:NAME` per-plan gate class and `User::hasFeature()`
- `subscriptions:check-payments` stays registered *and scheduled*; it logs a skip and
  exits while the flag is off
- `trial-guard` (`PreventTrialAbuse`) stays aliased; its routes just aren't registered

## Re-enabling

```bash
# 1. backend
cd api
#   set FEATURE_SUBSCRIPTION_PLANS=true in .env
php artisan config:clear
php artisan route:list | grep -i 'client/sub'   # expect 6 routes

# 2. frontend
cd ../web
#   set VITE_FEATURE_SUBSCRIPTION_PLANS=true in .env
pnpm build     # or restart `pnpm dev` — Vite only reads env at startup
```

Then confirm as a client user: `/client/pricing` renders instead of bouncing to the
client dashboard, and a client without an active subscription is redirected to it.

To switch it back off, reverse both flags and re-run `config:clear` / rebuild.

---

## What it did

- **Packages** — Basic/Premium tiers with monthly/yearly pricing and a `features` JSON
  map that fed per-plan gating (`check.feature:NAME`, `POST /v1/user/features`).
- **Trials** — 7-day trial with a 4-way abuse gate (`TrialService`,
  `PreventTrialAbuse`): per-user, per-email-domain, per-fingerprint, per-IP.
- **Subscribe / upgrade / downgrade** — `SubscriptionService` state machine
  (`SubscriptionState` enum), locked transactions, proration via `changePlanInvoice`.
- **Auto-renewal** — `subscriptions:check-payments` daily at 02:00: renews due
  subscriptions, transitions `past_due`, expires lapsed grace periods.
- **Portal lockout** — `check-subscription` middleware 403'd clients without an active
  plan; the SPA guard sent them to `/client/pricing`.

## File inventory

Everything below is present and untouched unless noted.

**Backend**
- Models: `Subscription`, `Package`, `SubscriptionPayment` (+ `Invoice.subscription_id`,
  kept nullable — the `invoices` table itself is live and extended by Billing & Payments)
- `api/app/Enums/SubscriptionState.php`
- Services: `SubscriptionService`, `TrialService`, `InvoiceService`
- Middleware: `CheckSubscription` (flag pass-through added at the top),
  `CheckFeature` (same), `PreventTrialAbuse` (untouched)
- Controller: `Api/SubscriptionController`
- Console: `Commands/CheckSubscriptionPayments.php` (flag no-op added at the top of
  `handle()`; still scheduled in `Console/Kernel.php`)
- Routes: the `client/sub/*` block in `routes/api.php`, wrapped in
  `if (config('features.subscription_plans'))`

**Frontend**
- Pages: `client/pricing.vue`, `client/plans-billing.vue`, `client/invoice/change-plan/**`
- `web/src/utils/features.js` — flag, `SUBSCRIPTION_ROUTE_PREFIXES`,
  `isDisabledModuleRoute()`
- `web/src/plugins/1.router/guards.js` — the client subscription gate, now behind
  `appFeatures.subscriptionPlans`
- `useTrialFingerprint` composable

**Data** — `subscriptions`, `packages`, `subscription_payments` tables still exist and
keep their rows (7-year retention applies to the payment records regardless).

## Known issues to fix before trusting it again

1. **Four coexisting signup endpoints** feed the trial flow; consolidation was still a
   TODO when the module was retired.
2. **`/v1/user/features` returns `[]` for plan-less users.** With the module off, every
   client is plan-less, so anything keyed on that map reads all-false while the
   `check.feature` API gate allows all — the map and the gate disagree while the flag
   is off. Nothing active depends on the map today; re-check before wiring new UI to it.
3. **The renewal charge is a stub.** `CheckSubscriptionPayments::charge()` simulates
   success for `cc`/`paypal`; no real gateway was ever wired to renewals.
4. **`paypal/payment` + `paypal/{success,cancel}`** were left registered (Billing &
   Payments rebuilds them against invoices). Re-enabling subscriptions after that
   rebuild means the subscription flow's PayPal entry points need re-verifying.
