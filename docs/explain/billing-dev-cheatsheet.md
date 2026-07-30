# Billing — dev cheatsheet

## Environment gotchas

- **Use `php8.3`, not `php`.** System `php` is 8.1; vendor requires ≥8.2.
- `pnpm` is not installed in this environment, and `web/`'s lint script points at a
  missing `.eslintrc.cjs` — frontend lint / typed-router regeneration need a real dev box.
- MySQL user can't create databases → tests run `DatabaseTransactions` against the
  **dev DB**. Never switch them to `RefreshDatabase`; it would wipe it.

## Commands

```bash
cd api
php8.3 artisan migrate
php8.3 artisan db:seed --class=ServicesTableSeeder   # 6 catalogue rows, safe to re-run
php8.3 artisan test                                  # 41 tests / 107 assertions
php8.3 artisan test --filter=Billing
./vendor/bin/pint                                    # formatter, run before committing
php8.3 artisan queue:work                            # REQUIRED — webhooks + mail are queued
php8.3 artisan billing:charge-recurring              # normally 03:00
php8.3 artisan billing:send-reminders                # normally 09:00
php8.3 artisan route:list --path=billing
```

```bash
cd web
pnpm dev        # :5173
pnpm build
pnpm lint
```

## Env keys that must be set

| Key | Without it |
|---|---|
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | card rail dead; webhook 400s |
| `VITE_STRIPE_PUBLISHABLE_KEY` | Payment Element won't mount |
| `PAYPAL_*` + `PAYPAL_WEBHOOK_ID` | PayPal rail dead |
| `COMPANY_*` (UID, FN, court, IBAN/BIC) | **invoice PDF renders with blank legal footer** |
| `BILLING_NUMBER_PREFIX`, `BILLING_PAYMENT_TERMS_DAYS`, `BILLING_VAT_RATE` | defaults apply |
| `BILLING_VIES_LIVE_CHECK` | defaults false (fails open anyway) |
| `FRONTEND_URL` | pay links and email links point nowhere |

Flags: `FEATURE_SUBSCRIPTION_PLANS=false`, `FEATURE_SECURITY_MODULE=false`
(+ the `VITE_` mirrors). Both fail closed.

Local webhooks: `stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe`
and make `STRIPE_WEBHOOK_SECRET` match what it prints.

## Manual end-to-end script

1. Admin: create a service → create an order → **issue** an invoice.
2. Client: check email (Mailpit), in-app notification, and the invoice appears.
3. Pay three ways: card (Element), PayPal sandbox, bank transfer (scan the QR,
   upload a proof → admin reconciliation → accept).
4. Try to **edit the issued invoice** → must refuse (409 / immutability banner).
5. Issue a **credit note** → parent cancelled, lines negated.
6. Guest checkout from `/order` with a fresh email → deposit → check the account was
   created and the welcome/set-password mail arrived.
7. Repeat guest checkout with an **existing** email → must return `requires_login`
   with no client secret and no pay token.
8. Admin: mint a public link → open `/pay/{token}` logged out → pay it.
9. Break a token by one character → bare 404. Same for an expired one.

## Arithmetic worth checking by hand

- `subtotal_net − discount_total + vat_total == total_gross` on an order with a
  discount **and** mixed VAT rates.
- Deposit + balance `total_gross` sum **exactly** to the order's — use an ugly total
  like 855.01 or 3836.41; round numbers hide the bug this was built to prevent.
- A German customer with a UID → line `vat_rate` = 0.00 and `reverse_charge` true,
  even though the catalogue service says 20.00.

## Invariants to try to break

```php
// 1. FK protection — should fail at MySQL
$clientWithInvoices->delete();

// 2. Immutability — should throw InvoiceLockedException
$issued->subtotal_net = 1; $issued->save();

// 3. Idempotency — second insert should hit the unique index
Payment::create([... 'idempotency_key' => 'same-key' ...]);
```

## Where things live

| Looking for | Path |
|---|---|
| Business logic | `api/app/Services/` (12 billing services) |
| Money math | `api/app/Support/Money.php` |
| Status vocabularies | `api/app/Enums/` |
| Webhook handlers | `api/app/Jobs/Process{Stripe,PayPal}WebhookJob.php` |
| Printable invoice | `api/resources/views/billing/invoice.blade.php` |
| Email template | `api/resources/views/emails/billing.blade.php` |
| Tests | `api/tests/Feature/Billing/` |
| Client pages | `web/src/pages/client/billing/`, `client/services/` |
| Admin pages | `web/src/pages/admin/{invoices,payments,services,orders}/` |
| Public pages | `web/src/pages/order/`, `web/src/pages/pay/[token].vue` |
| API surface (SPA) | `web/src/composables/useBilling.js` |

## Still open (from the spec)

- Accountant sign-off on the reverse-charge wording.
- VIES: live-checked or admin-entered?
- Populate real `COMPANY_*` values.
- Do any live beta `Subscription` rows need migrating to `RecurringPlan`?
- Guest checkout's `requires_login` boolean is a throttled enumeration compromise —
  flagged for a security pass.
