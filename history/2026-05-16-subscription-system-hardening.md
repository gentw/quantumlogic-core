# Subscription System Hardening — Explanation

Date: 2026-05-16
Branch: feature/subscription-system-hardening

## Files Changed

### Backend — Database

**`api/database/migrations/2026_05_15_100000_add_state_machine_and_trial_fields_to_subscriptions_table.php`** (new)
Adds the new state-machine columns (`state`, `payment_method_token`, `payment_method_brand`, `grace_period_ends_at`, `renewal_failure_count`, `cancelled_at`) and trial-abuse columns (`trial_started_at`, `trial_used_at`, `trial_ip`, `trial_device_hash`) to `subscriptions`. Also indexes them for fast lookup, and **backfills `state` from the legacy `status` enum** so existing rows enter the new world consistently.

**`api/database/migrations/2026_05_15_100100_add_billing_period_columns_to_invoices_table.php`** (new)
Adds `billing_period_start`/`billing_period_end` to invoices and creates a unique index on `(subscription_id, billing_period_start)` — this is the DB-level guarantee that a subscription cannot have two invoices for the same cycle. Also lazily adds `payment_method` and `subscribe_payment_id` (which were referenced by code but never actually migrated).

### Backend — Domain primitives

**`api/app/Enums/SubscriptionState.php`** (new)
PHP 8.1 backed enum with the five states (`trial_active`, `active`, `past_due`, `expired`, `cancelled`). Helpers: `entitled()` returns the entitling states, `entitlesAccess()`/`isTerminal()` for predicates. The `Subscription` model casts `state` to this enum so the rest of the code works in types, not strings.

**`api/app/Exceptions/TrialAbuseException.php`** (new)
Domain exception with a structured `reason` field (e.g. `user_used`, `email_used`, `ip_used`, `device_used`, `fingerprint_missing`) so the SPA can show specific copy or open a support flow.

**`api/app/Exceptions/SubscriptionStateException.php`** (new)
Domain exception for invalid state transitions / unknown payment methods. Surfaces as 409 from the controller.

### Backend — Services (the new business-logic layer)

**`api/app/Services/SubscriptionService.php`** (new)
Single source of truth for all subscription writes. Key methods:
- `withUserLock(User, Closure)` — wraps the closure in `DB::transaction()` and `lockForUpdate()`s the user's existing trial/active/past_due rows. Every write goes through this, so concurrent subscribe / PayPal callback / scheduler runs serialise on the same row.
- `subscribe()` — idempotent entry: converts a trial in-place, extends an active sub, or creates a fresh one. Never produces duplicate rows for the same user.
- `startTrial()` — creates a trial with `state=trial_active`, `price=0`, payment method stored as token only; writes `trial_used_at`/`trial_ip`/`trial_device_hash` for future abuse detection.
- `markPastDue()` / `markExpired()` / `cancel()` / `recordRenewalSuccess()` — explicit state transitions, each row-locked.

**`api/app/Services/TrialService.php`** (new)
Eligibility check: throws `TrialAbuseException` if the same user, email, IP, or device fingerprint has ever started a trial. Reads from `subscriptions` (the new source of truth), **not** from invoices like the old middleware did.

**`api/app/Services/InvoiceService.php`** (new)
`recordForPeriod()` — invoice creation keyed by `(subscription_id, billing_period_start)`. Catches the unique-violation `QueryException` (MySQL error 1062) so two concurrent paths (scheduler + manual subscribe, two PayPal callbacks) safely converge on one invoice instead of crashing.

### Backend — Models

**`api/app/Models/Subscription.php`** (modified)
Added all new fillable columns and `casts` (state→enum, dates→datetime, booleans). Replaced the broken `isPaymentDue()` (called `Carbon::` without an import) and added `isEntitled()` which is the canonical "should this user have access" predicate.

**`api/app/Models/Invoice.php`** (modified)
Added the new fillable + casts; declared the `user()` relation that was implied but missing.

**`api/app/Models/User.php`** (modified)
Fixed `activeSubscription()` — was calling `$this->subscription()` (singular, doesn't exist) and would runtime-fatal. Now uses the `subscriptions()` relation, filters by entitling states, and returns the row only if `isEntitled()` is true. `hasFeature()` and `features()` go through the same path.

### Backend — Controllers

**`api/app/Http/Controllers/Api/SubscriptionController.php`** (rewritten)
Was 440 lines of business logic. Now ~250 lines that validate via FormRequests, delegate to `SubscriptionService` / `TrialService` / `InvoiceService`, and shape the JSON response. The three duplicate write paths (`subscribe` / `upgradeDowngrade` / `startTrial`) are consolidated; `upgradeDowngrade()` is a one-liner that calls `subscribe()`.

**`api/app/Http/Controllers/Api/PayPalController.php`** (modified)
`success()` now goes through `SubscriptionService::subscribe()` (which locks the row), then updates the invoice in a small follow-up transaction. The previous version did its own `DB::transaction` but **without** a row lock — concurrent PayPal callbacks could double-create.

**`api/app/Http/Controllers/Api/UserController.php`** (modified)
Only `clientProfile()` semantically — now exposes `state`, `grace_period_ends_at`, `has_payment_method` so the SPA can drive UI gating. Pint reformatted the rest of the file as a side effect (whitespace, brace placement, single quotes).

### Backend — Middleware & FormRequests

**`api/app/Http/Middleware/CheckSubscription.php`** (modified)
Now reads from the `state` column AND `end_date`, not just `status='active'`. Trials, active subs, and not-yet-expired records all pass. Returns 403 with the same JSON shape so the existing SPA redirect to `/client/pricing` still works.

**`api/app/Http/Middleware/PreventTrialAbuse.php`** (modified)
Was a no-op in practice — it checked invoices with `status='paid'`, but trial invoices are created `unpaid`, so it never blocked. Now delegates to `TrialService::assertEligible` which reads from subscriptions.

**`api/app/Http/Requests/StartTrialRequest.php`** (modified)
`authorize()` was returning `false` (the request was unusable). Fixed to require an authenticated user. Added `payment_brand` field.

**`api/app/Http/Requests/SubscribeRequest.php`** (new)
Single FormRequest powering both `subscribe()` and `upgradeDowngrade()`.

### Backend — Scheduler

**`api/app/Console/Commands/CheckSubscriptionPayments.php`** (rewritten)
Two passes: (1) `expireGracePeriods()` transitions past-due subs whose grace expired into `expired`, (2) `processDueRenewals()` chunks active/past-due subs whose `end_date` passed and runs `renewOne()` per row inside a row-locked transaction. Failure → `markPastDue()`, success → `recordRenewalSuccess()`.

**`api/app/Console/Kernel.php`** (modified)
Added `withoutOverlapping(60)`, `onOneServer()`, `runInBackground()` to the daily renewal job — prevents overlapping runs on a single host and prevents simultaneous runs across hosts when scaled out.

### Backend — Routes

**`api/routes/api.php`** (modified)
- `client/sub/upgradeDowngrade` was pointing to `UserController` which doesn't have that method (broken route). Re-pointed to `SubscriptionController`.
- `client/sub/startTrial` now wrapped in `trial-guard` middleware (was previously bypassable by hitting it directly).

### Frontend — SPA

**`web/src/composables/useTrialFingerprint.js`** (new)
Single source of truth for the device fingerprint that goes into the `X-Trial-Fingerprint` header. Reads from localStorage; lazily generates a `crypto.randomUUID()` if missing.

**`web/src/pages/client/pricing.vue`** (rewritten)
Hard-coded `https://api-ds.bitemybytes.com/...` URLs replaced with relative `/v1/...`. Uses the fingerprint composable. Surfaces the new `trial_blocked` error from the backend with a real toast instead of a generic message.

**`web/src/pages/client/invoice/pay-now/[id].vue`** (rewritten)
Same URL/fingerprint cleanup. The big new behaviour: when the user is on the trial flow and submits the credit-card modal, it now actually calls `POST /v1/client/sub/startTrial` with the (simulated) payment token, instead of just showing an `alert()`. Trial errors surface their `reason`/`message` from the backend.

**`web/src/pages/client/invoice/change-plan/[id].vue`** (modified)
URL + fingerprint cleanup only.

**`web/src/plugins/1.router/guards.js`** (modified)
Three fixes: relative URL via `$api`, response shape (was reading `res.data.X` against an unwrapped body), and a new `isEntitled()` helper that accepts trials (was hard-coded to `status === 'active'`, which would have redirected trial users to pricing).

**`web/.env.example`** (new) + **`web/.env`** (new, gitignored)
Documents `VITE_API_BASE_URL` so relative `/v1/...` paths in the SPA resolve to the correct backend domain (`https://api-ds.bitemybytes.com/api`).

### Project metadata

**`context/current-feature.md`** (modified)
The feature spec & in-progress status that the workflow tracks.

**`context/fixes/subscription-system-hardening.md`** (new)
The original spec the user loaded.

---

## How It All Connects

Two layers were re-architected:

**1. Backend write path** — every subscription mutation now flows through `SubscriptionService`, which serialises concurrent writers via `DB::transaction()` + `lockForUpdate()`. The old code had three controller methods + a PayPal callback + a scheduler all writing directly to the model with no locking — meaning a user clicking "subscribe" twice, or a PayPal callback firing while the scheduler ran, could produce duplicate rows or double-billing. The state machine (in the new `state` column, type-safe via `SubscriptionState` enum) replaces the implicit lifecycle that was scattered across controllers.

**2. Trial abuse gate** — moved from a broken invoice-status check to a subscription-fields check. When a user starts a trial, `SubscriptionService` now writes `trial_used_at` + `trial_ip` + `trial_device_hash` to the subscription row. Future trial attempts hit `TrialService::assertEligible`, which queries those columns across all four signals (user, email, IP, device).

**Request flow for the canonical trial-then-paid journey:**

```
SPA: pricing.vue
  → POST /v1/client/sub/generateTrialInvoice    (creates a $0 invoice draft)
  → /client/invoice/pay-now/{id}
SPA: pay-now.vue (credit card modal submit)
  → POST /v1/client/sub/startTrial              [trial-guard middleware → TrialService]
       SubscriptionController::startTrial
         TrialService::assertEligible           (4-way abuse check)
         SubscriptionService::startTrial        (DB::transaction + lock)
           creates payment record (amount=0)
           creates subscription (state=trial_active)
         InvoiceService::recordForPeriod        (unique on subscription+period)
       returns 200 with state=trial_active
SPA: trial active, user uses the product for 7 days

… 7 days later, user upgrades:
SPA: pricing.vue → /client/invoice/pay-now/{id}
  → POST /v1/paypal/payment                     (returns approval_url)
  → window.location = approval_url
PayPal completes, redirects to /v1/paypal/success
  → SubscriptionService::subscribe              (locks user's trial row)
       convertTrial()                           (state: trial_active → active, price>0, end_date+1mo)
  → invoice.update(status='paid')
  → email confirmation
  → redirect SPA /client?payment=success

… cycle ends, scheduler fires:
  CheckSubscriptionPayments (cron, withoutOverlapping)
    → renewOne(id)                              (row-locked transaction)
        charge() succeeds
          recordRenewalSuccess()                (state stays active, end_date+1cycle)
        OR fails
          markPastDue()                         (state=past_due, grace=now+3d)
    → expireGracePeriods()                      (past_due AND grace<now → expired)
```

**SPA gating** consumes the new `state` field via `clientProfile()` and `isEntitled()` in `guards.js`, so route-level access correctly accepts trials and rejects past_due/expired. CheckSubscription middleware is the backend mirror of the same predicate, so frontend gating cannot be the only line of defence.

---

## Manual Testing Checklist

Run these before `/feature complete`. Categories are ordered by spec risk.

### Trial path (most important — abuse logic is new)

1. **Happy path.** New user signs up → clicks "Start 7-Day Free Trial" on `/client/pricing` → enters card on `/client/invoice/pay-now/<id>` → "Activate Trial".
   - Expect: redirect to `/client`, dashboard renders, no blank screen.
   - DB check:
     ```sql
     SELECT id, state, status, trial_used_at, trial_ip, trial_device_hash, payment_method_token
     FROM subscriptions WHERE user_id = <test_user_id>;
     ```
     Expect one row, `state=trial_active`, `trial_used_at` populated, `trial_ip` matches your IP, `trial_device_hash` matches `localStorage.getItem('trial_fp')`, `payment_method_token` is `tok_sim_*`.

2. **Same user retries trial.** Clear cookies / log out, log back in as same user, click "Start Trial" again.
   - Expect: 403 with `{ trial_blocked: true, reason: "user_used", message: "Trial already used by this account." }`. SPA shows the toast.

3. **Same browser, different account.** Sign up a fresh user (different email) on the same browser → "Start Trial".
   - Expect: 403 with `reason: "device_used"` OR `"ip_used"` (whichever check fires first).

4. **Different IP.** Use a VPN / different network with the fresh user.
   - Expect: still blocked by `device_used` because `trial_fp` in localStorage hasn't changed. To bypass for testing, manually `localStorage.removeItem('trial_fp')` first → then expect `ip_used` to be the gate.

### Subscribe & PayPal

5. **Trial → paid conversion.** As an active-trial user, go to pricing → "Upgrade" on Starter → pay-now → PayPal sandbox → approve.
   - Expect: same subscription row (no new row), `state` flips `trial_active → active`, `end_date` ~+1 month, `auto_renew=true`.
   - DB check:
     ```sql
     SELECT id, state, start_date, end_date, billing_cycle, payment_method_token
     FROM subscriptions WHERE user_id = <test_user_id>;
     ```

6. **PayPal callback idempotency.** Immediately after the success redirect, hit the same `/v1/paypal/success?token=...` URL again (browser back + forward, or curl).
   - Expect: second hit is a no-op — the early-return at `if ($invoice->status === 'paid' && $invoice->subscription_id)` triggers. No duplicate invoice, no second payment row.
   - DB check:
     ```sql
     SELECT id, billing_period_start, status, subscribe_payment_id
     FROM invoices WHERE subscription_id = <id>;
     ```
     Expect exactly one row per period.

### State machine / gating

7. **Manually flip state to `past_due`.**
   ```sql
   UPDATE subscriptions SET state = 'past_due', grace_period_ends_at = NOW() + INTERVAL 3 DAY
   WHERE user_id = <test_user_id>;
   ```
   Reload SPA → expect redirect to `/client/pricing` (CheckSubscription middleware blocks; guards.js mirrors).

8. **Active but expired.** `UPDATE subscriptions SET state='active', end_date = NOW() - INTERVAL 1 DAY WHERE id=<id>;` → reload SPA → expect same pricing redirect (entitlement is `state` AND `end_date > now`).

9. **Trial user accesses domains.** With an active trial, navigate to `/client/domains` (or any `check-subscription`-gated client route).
   - Expect: page loads (CheckSubscription accepts `trial_active`).

### Scheduler (optional but recommended before launch)

10. **Successful renewal.**
    ```sql
    UPDATE subscriptions SET end_date = NOW() - INTERVAL 1 HOUR, auto_renew = 1
    WHERE id = <id>;
    ```
    Then `php8.2 artisan subscriptions:check-payments`.
    - Expect: `state` stays `active`, `end_date` extended ~+1 cycle, new `subscription_payments` row with `status=success`, no duplicate invoice.

11. **Failed renewal → past_due → expired.** Set `recurring_payment_method = 'invalid'` (or null `payment_method_token`/`paypal_payment_id`/`cc_payment_id`) on a due subscription, then run the command twice (once to fail-and-mark-past-due, then advance time and run again to expire).
    - Expect first run: `state=past_due`, `grace_period_ends_at` ~3 days out, `renewal_failure_count=1`.
    - Expect second run after grace: `state=expired`, `auto_renew=false`.

### Pre-completion cleanup

- Confirm all checks above pass.
- Drop test rows so production data isn't polluted:
  ```sql
  DELETE FROM subscription_payments WHERE subscription_id IN (SELECT id FROM subscriptions WHERE user_id IN (...test_user_ids...));
  DELETE FROM invoices                WHERE user_id IN (...test_user_ids...);
  DELETE FROM subscriptions           WHERE user_id IN (...test_user_ids...);
  ```

### Known gaps to be aware of while testing

- **Card token is fake.** `pay-now.vue` builds `tok_sim_<timestamp>_<last4>`. Trial flow stores it; nothing actually charges it. Expected during dev — must be replaced with real Stripe/Adyen/etc. tokenization before production launch.
- **Scheduler `charge()` simulates success.** `CheckSubscriptionPayments::charge()` returns `success: true` for all valid methods. Real gateway calls (Raiffeisen API for CC, PayPal recurring billing API) need wiring before launch.
- **`trial_active_but_payment_invalid` state not implemented.** Spec calls this out as a "business decision" edge case. Current behaviour: payment-method invalidation isn't detected; subscription stays `active` until renewal fails on the next cycle.

