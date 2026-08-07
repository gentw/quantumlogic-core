# Billing & Payments — hard test plan

Ordered by the commits that built each thing, so a failure points straight at a phase.
Work top to bottom; each round assumes the previous one passed.

Every round says **PASS** (what you should see) and **ANOMALY** (what means something
is wrong). If a round anomalies, stop and fix before moving on — later rounds build on it.

---

# ⚠️ Round 0 — Read this before running anything

Recon of this machine on 2026-07-30 found three things that make careless testing
expensive.

### Hazard 1 — mail goes to real inboxes

`MAIL_HOST=smtp.gmail.com`. **Not Mailpit.** Anything that sends mail sends it for real.
The database has **5,160 client rows** with real addresses.

**Rule: create one dedicated test client with an address you own, and never issue a
test invoice against a real client row.** Filter by it for every test below.

Currently safe to run because the new-model tables are empty (0 orders, 0 payments,
0 reminders, 0 recurring plans; the 4 existing invoices are legacy `paid`/`unpaid`
rows with `type = null`). That safety disappears the moment you start creating data —
re-check before running any `billing:*` command.

If you want zero risk, point mail at a local catcher first:

```bash
# api/.env
MAIL_MAILER=log            # writes to storage/logs/laravel.log instead of sending
# or run mailpit and use MAIL_MAILER=smtp MAIL_HOST=127.0.0.1 MAIL_PORT=1025
```

### Hazard 2 — production-ish config

`APP_ENV=local` but `APP_URL=https://core.quantumlogic.at` and `APP_DEBUG=true`.
Confirm you're pointed at the dev database (`DB_DATABASE=quantum`) and not something
you care about. **Never** run `migrate:fresh` or `RefreshDatabase` here — it wipes 5,160 clients.

### Hazard 3 — failed-job baseline

`failed_jobs` already holds **35 rows**, all `App\Events\MessageSent` from 2024-10-29
(the chat module, unrelated). Record that number:

```bash
php8.3 artisan tinker --execute="echo DB::table('failed_jobs')->count();"   # expect 35
```

**Any increase during testing is a real new failure.** Check it immediately:

```bash
php8.3 artisan tinker --execute="
foreach(DB::table('failed_jobs')->where('failed_at','>','2026-07-01')->get() as \$j){
  echo \$j->failed_at.' | '.substr(strtok(\$j->exception, PHP_EOL),0,140).PHP_EOL;}"
```

### Readiness — status as of 2026-08-07

| Prerequisite | Status |
|---|---|
| `STRIPE_KEY` / `STRIPE_SECRET` | **READY** (test mode) |
| `STRIPE_WEBHOOK_SECRET` | **READY** — Dashboard endpoint `we_1U1vIk…`, verified: signed → 200, tampered → 400 |
| `VITE_STRIPE_PUBLISHABLE_KEY` | **READY** — baked into `dist/assets/stripe-*.js` |
| Passport keys | **READY** — were missing entirely; generated 2026-08-07 |
| Services seeded / both feature flags off | **READY** |
| `PAYPAL_WEBHOOK_ID` | **BLOCKED** — Round 9's webhook half will 400 until set |
| `COMPANY_*` (UID, FN, court, IBAN) | **BLOCKED** — Round 16's legal footer renders blank |
| Queue worker | **must be started manually** — see Pre-flight |

Two environment traps already found and fixed here; check for them if a round misbehaves:

- **`.env` had an unterminated quote** on `WEB_LINK` that silently swallowed every
  variable after line 91 — all `FEATURE_*`, `BILLING_*`, `STRIPE_*`, `COMPANY_*` and
  `PAYPAL_WEBHOOK_ID` read as empty. Both flags being correct was luck, not config.
  If config seems ignored, run `php8.3 -r 'require "vendor/autoload.php";
  var_dump(count(Dotenv\Dotenv::createArrayBacked(getcwd())->load()));'` and compare
  against the number of `KEY=` lines in `.env`.
- **The SPA is served from `web/dist`**, not a dev server (nginx: `core.quantumlogic.at`
  → `/var/www/quantumlogic-core/web/dist`). Vite bakes `VITE_*` at build time, so any
  `web/.env` change needs `npm run build` before it is live. Hard-refresh after building.

`FRONTEND_URL=http://178.105.16.221:5173/` has a **trailing slash** — watch for `//`
in generated pay links and email links (Round 14, Round 17).

### Login works — but know the two quirks

`AuthenticationController::store()` finds **non-client** users by matching your input
against the **`phone`** column, and clients by `email`. So:

- `admin@ds.com`, `agent@ds.com`, `agent2@ds.com` → log in with the email (their `phone`
  column holds it)
- personal admin/agent accounts whose `phone` holds a real number → log in with the
  **number** (`0441231233`, `044123458`); typing their email returns **500**
  (`Attempt to read property "blocked" on null` — unguarded null on the fallback branch)

### Pre-flight

```bash
cd /var/www/quantumlogic-core/api
php8.3 artisan migrate:status | tail -20     # all 2026_07_28/29 rows "Ran"
php8.3 artisan queue:work                    # SEPARATE TERMINAL — webhooks + mail are queued
php8.3 artisan tinker --execute="echo App\Models\Service::count();"   # expect 6
```

Snapshot the counts so you can prove you cleaned up afterwards:

```bash
php8.3 artisan tinker --execute="
printf('orders=%d invoices=%d payments=%d users=%d reminders=%d plans=%d'.PHP_EOL,
App\Models\ServiceOrder::count(), App\Models\Invoice::count(), App\Models\Payment::count(),
App\Models\User::count(), App\Models\InvoiceReminder::count(), App\Models\RecurringPlan::count());"
```

### The fixture — paste once per tinker session

```php
use App\Enums\{InvoiceType, PaymentProvider, ServiceOrderStatus};
use App\Models\{Invoice, Payment, Service, ServiceOrder, User};
use App\Services\{BillingInvoiceService, PaymentService, ServiceOrderService, TaxService, BankTransferService, InvoiceNumberService};

$orders   = app(ServiceOrderService::class);
$invoices = app(BillingInvoiceService::class);
$payments = app(PaymentService::class);
$bank     = app(BankTransferService::class);

// YOUR email — mail from these tests lands in your inbox, not a client's.
$me = User::firstOrCreate(
    ['email' => 'kamerialmir@gmail.com'],
    ['name' => 'ZZ Billing Test', 'password' => bcrypt(Str::random(32)),
     'role' => 'client', 'country_code' => 'AT', 'origin' => 'admin']
);
$admin = User::where('role','admin')->first();
```

Seeded catalogue (slugs you'll use): `website-development` (milestone, 4800.00, 50% deposit,
public), `web-application` (milestone, 12000.00, **not** public), `seo-retainer` (recurring,
590.00, public), `hosting` (recurring, 39.00, public), `maintenance-support` (recurring,
190.00, public), `consulting` (one_off, 140.00, **not** public).

---

# Round 1 — Automated baseline · `ecd328b`

```bash
php8.3 artisan test                    # expect: Tests: 41 passed (107 assertions)
php8.3 artisan test --filter=Billing   # expect: 39 passed (105 assertions)
./vendor/bin/pint --test               # expect: clean
```

**PASS** 41/107, ~2.5s. **ANOMALY** any failure — the suite covers numbering, VAT,
splits, idempotency, immutability, proofs, guest checkout and recurring. A red test
here means a later round will lie to you.

---

# Round 2 — Flag retirement · `c7de49d`

```bash
php8.3 artisan route:list | grep -c 'client/sub/'                      # expect 0
FEATURE_SUBSCRIPTION_PLANS=true php8.3 artisan route:list | grep -c 'client/sub/'   # expect 6
php8.3 artisan route:list | grep -cE 'v1/(webhooks|public)/'           # expect >= 7
```

In the SPA (both flags off):

- Log in as a client **with no subscription** → lands on `/client` and the dashboard
  renders. **ANOMALY:** redirected to `/client/pricing`, or a blank page.
- Visit `/client/pricing`, `/client/plans-billing` → bounced to `/client`.
- Visit those URLs **logged out** → also bounced, no redirect loop, no console error.
- Visit `/client/domains` → bounced (dormant security module).

**ANOMALY** a redirect loop, a flash of the retired page before the bounce, or more
than one `next()` firing per navigation.

---

# Round 3 — Schema and model guards · `4c8fde3` `8647d44`

**FK protection.** Financial history must be undeletable at the database level:

```php
$c = User::whereHas('invoices')->first();
try { $c->delete(); echo "ANOMALY: deleted a client with invoices\n"; }
catch (\Throwable $e) { echo "PASS: ".get_class($e)."\n"; }   // QueryException, MySQL 1451
```

**Hidden fields.** `public_token`, `trial_fingerprint`, `ip_address` must never serialize:

```php
$keys = array_keys(Invoice::first()->toArray());
echo implode(',', array_intersect($keys, ['public_token','trial_fingerprint','ip_address'])) ?: "PASS: none exposed";
```

Also check the HTTP surface — `GET /v1/client/billing/invoices/{id}` as the owner, and
confirm the JSON has no `public_token`.

**Legacy enum casts.** The 4 pre-existing invoices have `status` `paid`/`unpaid` and
`type = null`:

```php
foreach (Invoice::withTrashed()->limit(4)->get() as $i)
    echo $i->id.' status='.$i->status->value.' type='.($i->type?->value ?? 'null').PHP_EOL;
```

**ANOMALY** a cast exception on any legacy row — that means `InvoiceStatus` lost a
legacy case.

**Append-only trail.** `invoice_activities` must have no `updated_at`:

```bash
php8.3 artisan tinker --execute="echo implode(',', Schema::getColumnListing('invoice_activities'));"
```

**ANOMALY** `updated_at` appears in the list.

---

# Round 4 — Numbering and immutability · `79c70e6`

**Drafts must not burn numbers:**

```php
$order = $orders->create($me, [['service_id' => 1, 'quantity' => 1]], ['deposit_percent' => 50]);
$draft = $invoices->createDraftFromOrder($order, InvoiceType::Deposit, 0.5);
echo $draft->invoice_number.PHP_EOL;    // PASS: starts "draft-"
$issued = $invoices->issue($draft, $admin);
echo $issued->invoice_number.PHP_EOL;   // PASS: QL-2026-000N
```

**Gaplessness under concurrency** — the real test, four processes at once:

```bash
cd /var/www/quantumlogic-core/api
for i in 1 2 3 4; do
  php8.3 artisan tinker --execute="
    for(\$j=0;\$j<25;\$j++){ echo app(App\Services\InvoiceNumberService::class)->allocate().PHP_EOL; }
  " > /tmp/seq-\$i.txt 2>/dev/null &
done; wait
cat /tmp/seq-*.txt | grep -oE '[0-9]+$' | sort -n | uniq | wc -l   # expect 100
cat /tmp/seq-*.txt | grep -oE '[0-9]+$' | sort -n | uniq -d        # expect EMPTY
```

**PASS** 100 unique numbers, no duplicates, and the max minus the min is exactly 99
(no gaps). **ANOMALY** fewer than 100 uniques (a duplicate = two invoices share a
legal number), or a gap in the range.

> This burns 100 numbers on the dev sequence. Fine on dev; note it if you compare
> numbers later.

**The immutability triad:**

```php
$i = Invoice::whereNotNull('locked_at')->latest('id')->first();
try { $i->subtotal_net = 1; $i->save(); echo "ANOMALY: content edited\n"; }
catch (\App\Exceptions\InvoiceLockedException $e) { echo "PASS frozen: ".$e->getMessage().PHP_EOL; }

$i->amount_paid = 1.00; $i->save(); echo "PASS payment tracking moves\n";

try { $i->locked_at = null; $i->save(); echo "ANOMALY: UNLOCKED\n"; }
catch (\App\Exceptions\InvoiceLockedException $e) { echo "PASS cannot unlock\n"; }

try { $invoices->cancel($i); echo "ANOMALY: issued invoice cancelled\n"; }
catch (\App\Exceptions\InvalidInvoiceStateException $e) { echo "PASS cancel refused\n"; }
```

**Overdue is derived.** Backdate a due date and confirm no cron is needed:

```php
$i->forceFill(['due_at' => now()->subDays(5)])->saveQuietly();  // saveQuietly to bypass the guard for setup only
echo $i->fresh()->is_overdue ? "PASS derived\n" : "ANOMALY\n";
echo Invoice::overdue()->count()." in overdue scope\n";
```

---

# Round 5 — VAT resolution · `56bbbfc`

```php
$tax = app(TaxService::class);
foreach ([
  ['AT', null,           'domestic 20'],
  ['AT', 'ATU12345678',  'domestic B2B still 20'],
  ['DE', 'DE123456789',  'EU B2B -> 0 + Art.196 note'],
  ['DE', null,           'EU B2C -> 20'],
  ['DE', 'ATU12345678',  'wrong-country UID -> 20'],
  ['US', null,           'non-EU -> 0'],
  [null, null,           'null country -> domestic 20'],
] as [$c, $uid, $label]) {
  $r = $tax->resolve($c, $uid, 20.0);
  printf("%-32s rate=%5.2f rc=%s note=%s\n", $label, $r->rate,
    $r->reverseCharge ? 'Y':'N', $r->note ? 'yes':'-');
}
```

**PASS** rates 20/20/0/20/20/0/20, reverse charge only on row 3, and **only** row 3
carries a note. **ANOMALY** reverse charge on a wrong-country UID (that would zero-rate
a domestic sale — a tax liability), or a missing note on the genuine RC row (invalid
invoice under Art. 196).

**VIES must fail open.** With `BILLING_VIES_LIVE_CHECK=true` and no network, invoicing
must still work:

```bash
BILLING_VIES_LIVE_CHECK=true php8.3 artisan tinker --execute="
\$r = app(App\Services\TaxService::class)->resolve('DE','DE123456789',20.0);
echo 'rate='.\$r->rate.' (must not hang or throw)'.PHP_EOL;"
```

---

# Round 6 — Money math · `b52f26a`

Use an **ugly** total — round numbers hide the bug this was built to prevent.

```php
$order = $orders->create($me, [
  ['description' => 'Ugly build', 'quantity' => 1, 'unit_price_net' => 3196.67, 'vat_rate' => 20],
  ['service_id' => 6, 'description' => 'Consulting', 'quantity' => 7.5,
   'unit_price_net' => 140.00, 'discount_percent' => 10, 'vat_rate' => 20],
], ['deposit_percent' => 50]);

printf("subtotal=%s discount=%s vat=%s gross=%s\n",
  $order->subtotal_net, $order->discount_total, $order->vat_total, $order->total_gross);
printf("invariant holds: %s\n",
  bccomp(bcsub(bcadd($order->subtotal_net,$order->vat_total,2),$order->discount_total,2),
         $order->total_gross, 2) === 0 ? 'YES' : 'NO — ANOMALY');
```

**PASS exactly:** `subtotal=4246.67 discount=105.00 vat=828.33 gross=4970.00`
(4246.67 − 105.00 + 828.33 = 4970.00).

Now the split — the headline correctness test:

```php
$dep = $invoices->issue($invoices->createDraftFromOrder($order, InvoiceType::Deposit, 0.5), $admin);
$bal = $invoices->issue($invoices->createDraftFromOrder($order, InvoiceType::Balance), $admin);

printf("deposit=%s balance=%s sum=%s order=%s\n",
  $dep->total_gross, $bal->total_gross,
  bcadd($dep->total_gross, $bal->total_gross, 2), $order->total_gross);
printf("net sum=%s order net=%s\n",
  bcadd($dep->subtotal_net, $bal->subtotal_net, 2),
  bcsub($order->subtotal_net, $order->discount_total, 2));
```

**PASS exactly:** `deposit=2485.01 balance=2484.99 sum=4970.00`. Note the two invoices
differ by 2 cents — that is the remainder being absorbed correctly, not an error.
Net sum must equal 4141.67.

**ANOMALY** sum ≠ 4970.00 by any amount. One cent means the balance is being re-scaled
instead of remaindered, and every deposit order in the system is subtly wrong.

Repeat with `deposit_percent` 30 and 33 — must still sum exactly.

**Credit note:**

```php
$note = $invoices->creditNote($dep, $admin, 'client dispute');
printf("type=%s parent=%d gross=%s (parent was %s) locked=%s\n",
  $note->type->value, $note->parent_invoice_id, $note->total_gross,
  $dep->total_gross, $note->locked_at ? 'Y':'N');
echo "parent status=".$dep->fresh()->status->value." due=".$dep->fresh()->amount_due.PHP_EOL;
echo "trail: ".$dep->activities()->pluck('event')->implode(', ').PHP_EOL;
try { $invoices->creditNote($note); echo "ANOMALY: credit-noted a credit note\n"; }
catch (\App\Exceptions\InvalidInvoiceStateException $e) { echo "PASS: refused\n"; }
```

**PASS** negated gross, parent `cancelled` with 0.00 due, note locked immediately,
trail contains `created`,`issued`,`credit_noted`.

---

# Round 7 — Payment idempotency and error shapes · `7385e99` `c2229a9`

```php
$inv = $invoices->issue($invoices->createDraftFromOrder(
  $orders->create($me, [['service_id' => 6, 'quantity' => 1]]), InvoiceType::OneOff), $admin);

$k = 'test:replay:'.uniqid();
$p1 = $payments->apply($inv, PaymentProvider::Stripe, $k, 50.00);
$p2 = $payments->apply($inv, PaymentProvider::Stripe, $k, 50.00);   // replay
printf("same row: %s | applied once: paid=%s due=%s | rows=%d\n",
  $p1->id === $p2->id ? 'YES':'ANOMALY', $inv->fresh()->amount_paid,
  $inv->fresh()->amount_due, $inv->payments()->count());
```

**PASS** same payment id, `amount_paid` 50.00 (not 100.00), one payment row.

Settle it, then prove a genuinely new key is rejected but replays still aren't:

```php
$payments->apply($inv, PaymentProvider::Stripe, 'test:rest:'.uniqid(), (float) $inv->fresh()->amount_due);
echo "status=".$inv->fresh()->status->value." due=".$inv->fresh()->amount_due.PHP_EOL;  // paid / 0.00
try { $payments->apply($inv, PaymentProvider::Stripe, 'test:new:'.uniqid(), 10.00);
      echo "ANOMALY: paid onto a settled invoice\n"; }
catch (\App\Exceptions\PaymentAlreadyAppliedException $e) { echo "PASS: 409 path\n"; }
$again = $payments->apply($inv, PaymentProvider::Stripe, $k, 50.00);   // old key
echo "PASS: replay still no-ops (id ".$again->id.")\n";
```

**Two-process race** — the webhook-vs-redirect scenario:

```bash
INV=<id of a fresh issued invoice>
for i in 1 2; do
  php8.3 artisan tinker --execute="
    for(\$j=0;\$j<10;\$j++){ try{ app(App\Services\PaymentService::class)->apply(
      App\Models\Invoice::find($INV), App\Enums\PaymentProvider::Stripe, 'race:$INV', 120.00);
    }catch(\Throwable \$e){} }" >/dev/null 2>&1 &
done; wait
php8.3 artisan tinker --execute="
\$i=App\Models\Invoice::find($INV);
echo 'payment rows='.\$i->payments()->count().' amount_paid='.\$i->amount_paid.PHP_EOL;"
```

**PASS** exactly 1 payment row, `amount_paid` = 120.00. **ANOMALY** 2+ rows or double
the amount — the invoice was paid twice for one real transaction.

**Refund reopens:**

```php
$payments->refund($p1, 20.00, $admin);
echo "status=".$inv->fresh()->status->value." due=".$inv->fresh()->amount_due
   ." paid_at=".($inv->fresh()->paid_at ?? 'null').PHP_EOL;   // sent / 20.00 / null
```

**HTTP status shapes** — hit the API as an admin and check codes, not messages:

| Action | Expect |
|---|---|
| PATCH an issued invoice | **409** `{ "message": … }` |
| Issue an already-issued invoice | **422** |
| Cancel an issued invoice | **422** |
| A gateway failure (no Stripe keys) | **502** |

**ANOMALY** a 500, an HTML error page, or a stack trace in the JSON body.

---

# Round 8 — Stripe rail · `bc73ff4`  *(needs test keys)*

Add to `api/.env`: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`; and
`VITE_STRIPE_PUBLISHABLE_KEY` to `web/.env`. Then:

```bash
stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe
# copy the printed whsec_… into STRIPE_WEBHOOK_SECRET, restart the server
```

**Signature first.** An unsigned webhook must be rejected before any work happens:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://localhost:8000/api/v1/webhooks/stripe \
  -H 'Content-Type: application/json' -d '{"type":"payment_intent.succeeded"}'   # expect 400
```

**ANOMALY** anything but 400 — especially 200, or a queued job appearing.

Then, in the SPA at `/client/billing/checkout/{id}`:

| Card | Expect |
|---|---|
| `4242 4242 4242 4242` | succeeds; invoice `paid` **via the webhook**, not the redirect |
| `4000 0025 0000 3155` | 3DS challenge; completes after auth |
| `4000 0000 0000 0002` | declined; payment `failed` with the reason shown |
| `4000 0000 0000 9995` | insufficient funds; invoice stays `sent` |

**The closed-tab test — the whole point of the rail.** Start a card payment, and the
instant the 3DS window opens, **kill the browser tab**. Let the webhook land.

**PASS** invoice reaches `paid` anyway, exactly one payment row.
**ANOMALY** invoice stuck `sent` with money taken (check the Stripe dashboard) — that's
the original bug back.

**Replay safety:** `stripe events resend <evt_id>` → nothing changes, no second payment.

**Partial payment:** `?amount=` less than due → invoice stays `sent` with the remainder;
try `?amount=` **greater** than due → must be capped server-side, not accepted.

**Saved cards:** add via `/client/billing/payment-methods` (SetupIntent), confirm the
`setup_intent.succeeded` webhook stored it, first card became default, deleting the
default promotes the next, and the API response contains **no** `provider_token`.

---

# Round 9 — PayPal rail · `47639da`  *(sandbox credentials present — testable now)*

Full round trip from `/client/billing/checkout/{id}` → PayPal → approve → return.

- **PASS** invoice `paid`, one payment row, `provider_payment_id` set.
- **`custom_id` must be the payments row id, not the invoice id:** check the sandbox
  transaction detail. **ANOMALY** it holds an invoice id — webhook settlement will
  attach to the wrong row.
- **Closed-tab test again:** approve at PayPal, then close the tab before the return
  redirect. The webhook must settle it. **ANOMALY** money taken, invoice unpaid.
- **Double callback:** replay the return URL twice → still one payment.
- **Cancel** at PayPal → returns cleanly, payment stays `pending`/`failed`, invoice untouched.
- **Unsigned webhook** → 400, same as Stripe.
- **Refund** in the sandbox → `REFUNDED` webhook applies only the delta beyond any
  admin-recorded refund, and the invoice reopens to `sent`.

---

# Round 10 — SEPA rail · `367f78d`

**EPC payload** must be exactly 11 lines and ask for the **remaining** amount:

```php
$inv = Invoice::whereNotNull('locked_at')->where('amount_due','>',0)->first();
$p = $bank->epcPayload($inv);
echo $p.PHP_EOL."lines=".count(explode("\n", $p)).PHP_EOL;   // BCD/002/1/SCT/... , 11 lines
```

**PASS** line 1 `BCD`, line 2 `002`, line 4 `SCT`, the amount line reads `EUR<amount_due>`
(not the gross total), and the reference is the invoice number.
**ANOMALY** the amount equals `total_gross` on a partially-paid invoice — the client
would overpay.

Scan the QR from `/client/billing/invoices/{id}` with a real banking app: the beneficiary,
IBAN and amount must pre-fill. (Blank beneficiary is the missing `COMPANY_*`, not a bug.)

**Upload validation — try to break it:**

| Upload | Expect |
|---|---|
| Valid PDF/JPEG/PNG under 10 MB | accepted; invoice → `awaiting_confirmation` |
| `.exe` renamed to `.pdf` | **rejected** (content sniffed, not extension) |
| A 15 MB file | rejected |
| A 0-byte file | rejected |

**PASS after a valid upload:** invoice `awaiting_confirmation`, payment
`awaiting_confirmation`, `amount_paid` still **0.00**.
**ANOMALY** the invoice shows any money paid — an upload must never settle anything.

**Private disk:** find the `file_path` and try to reach it without auth:

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8000/storage/<file_path>   # expect 404
```

**ANOMALY** 200 — payment proofs are publicly readable.

**Reconciliation** at `/admin/payments/reconciliation`:

- Slip preview opens (fetched as an authenticated blob). **ANOMALY** a 401 in the console.
- Amount-mismatch chip appears when the claimed amount ≠ open amount.
- **Accept** → invoice `paid`, `confirmed_by_admin_id` and `confirmed_at` set.
- **Reject** without a reason → refused. With a reason → payment `failed`, invoice back
  to `sent`, client emailed the reason.
- **Double-review** the same proof → no-op, no second payment.
- Two pending proofs on one invoice, reject one → invoice must stay
  `awaiting_confirmation` (the other is still pending), not reopen to `sent`.

---

# Round 11 — Client UI · `0c46fcb` `1107ae9` `3142a28`

Run the whole round **twice — light theme and dark** — via the Vuexy toggle, and at
**375 / 768 / 1280** px.

| Page | Check |
|---|---|
| `/client/billing` | KPI strip numbers match the DB; status chips; `is_overdue` shows overdue not "sent"; server-side pagination actually re-fetches (watch the network tab) |
| `/client/billing/invoices/{id}` | Lines with qty/unit/discount/VAT; reverse-charge notice only on an RC invoice; EPC QR present only while payable; PDF download works |
| `/client/billing/checkout/{id}` | Three rails; switching rails doesn't lose the amount |
| `/client/billing/pay` | Pay latest / selected / custom / everything; custom amount settles oldest debt first |
| `/client/billing/payment-methods` | Cards list, default badge, delete promotes next |
| `/client/services` | Recurring cards + order accordion; a plan with no card shows the warning linking to payment-methods |

**Drafts must be invisible.** Create a draft, then as that client:

```bash
curl -s -H "Authorization: Bearer <client token>" \
  http://localhost:8000/api/v1/client/billing/invoices | grep -c '"draft"'   # expect 0
curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer <client token>" \
  http://localhost:8000/api/v1/client/billing/invoices/<draft id>            # expect 403/404
```

**IDOR — the important one.** As client A, request client B's invoice id directly:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer <client A token>" \
  http://localhost:8000/api/v1/client/billing/invoices/<client B invoice id>   # expect 403
```

Repeat for `payment-methods/{id}`, `payments/{id}/status`, `recurring-plans/{id}/pause`,
and the bank-details and payment-proof endpoints. **ANOMALY** any 200.

**Role isolation:** with a client token, call an `admin/billing/*` endpoint → 403.
With an admin token, call `client/billing/*` → 403. Also confirm the client nav shows
no admin links.

Also check: empty states (a client with no invoices), loading states on every async
action, and that an API error surfaces as a snackbar rather than a silent failure
(kill the API mid-action to test).

---

# Round 12 — Admin UI · `8f40978` `fe6d90a` `fdfacb8` `483b4e5`

| Screen | Check |
|---|---|
| `/admin/invoices` | Filter drawer with status dots + active-count badge; debounced search over number/reference/client; **Paid and Outstanding columns** reflect partial payments |
| `/admin/invoices/{id}` | Draft fields editable; **after issue an immutability banner and the menu switches Edit/Cancel → Credit note**; audit timeline in order; `?action=` opens the payment and reminder dialogs |
| `/admin/invoices/add-invoice` | 5 steps; service picker pre-fills description and price; VAT preview shows the reverse-charge notice for an EU B2B client; Save-as-draft and Issue-now both work |
| `/admin/payments` | Provider/status filters; search by provider id, invoice number, client; refund deltas visible; CSV export of the page |
| `/admin/services` | Create/edit dialog; **deactivate, no delete**; slug auto-generates |
| `/admin/orders/{id}` | Deposit-split preview; invoice schedule links through; only status-appropriate transition buttons appear |
| `/admin/clients/{id}` | Billing card: LTV, outstanding, invoice count, recent invoices |

**Manual payment** must be capped: try to record more than `amount_due` → refused or
clamped, never an invoice with negative due.

**Invalid transitions:** on an order in `draft`, the UI must not offer "complete".
Force it via the API → **422**.

**ANOMALY** any "delete invoice" affordance anywhere; an editable field on an issued
invoice; a transition button that 500s.

---

# Round 13 — Guest checkout · `6710328`

Public, unauthenticated, from `/order`.

**Only publicly-orderable services may appear:**

```bash
curl -s http://localhost:8000/api/v1/public/services | python3 -m json.tool | grep -E '"slug"'
```

**PASS** `website-development`, `seo-retainer`, `hosting`, `maintenance-support` only.
**ANOMALY** `web-application` or `consulting` present — those are `is_publicly_orderable = false`.

Then try to order a non-public one by id anyway → must be refused. **ANOMALY** an order
is created.

**New email path:** complete `/order` with a fresh address and pay the 50% deposit.

**PASS** — user created with `origin = guest_checkout`, `email_verified_at` null before
payment and set after; order `awaiting_payment` → `active`; deposit invoice `paid`;
welcome + set-password mail arrives; the set-password link works and lands you logged in.

**ANOMALY** `origin` null (the `$fillable` bug), account activated before the deposit
settled, or a second reset-token mechanism appearing.

**Existing email path — the security-critical one.** Run checkout with the email of an
**existing** account:

```bash
curl -s -X POST http://localhost:8000/api/v1/public/checkout/start \
  -H 'Content-Type: application/json' \
  -d '{"email":"<an existing client email>","name":"X","country_code":"AT","lines":[{"service_id":1,"quantity":1}]}' \
  | python3 -m json.tool
```

**PASS** `requires_login: true`, `client_secret: null`, `pay_token: null`, and the
response shape is **identical in key set** to the new-email response.
**ANOMALY** any secret or token returned — an anonymous request could pay onto (and read)
an established account.

**No fifth signup endpoint:**

```bash
grep -cE "register" routes/api.php    # the four known ones only
php8.3 artisan route:list | grep -iE 'register' # expect exactly 4 public signup routes
```

**Throttle:** fire 15 rapid `checkout/start` calls → **429** after 10.

**Idempotent account creation:** run the same new email twice concurrently → exactly
one user row.

---

# Round 14 — Public pay links · `6e93a9a`

```php
// as admin, mint a link for an issued invoice
$inv = Invoice::whereNotNull('locked_at')->where('amount_due','>',0)->first();
// via the API: POST /v1/admin/billing/invoices/{id}/public-link
```

**PASS** a 64-char token, a URL built from `config('app.frontend_url')`.
**Check for `//`** — `FRONTEND_URL` ends in a slash on this box. **ANOMALY** `http://host:5173//pay/…`.

```bash
T=<token>
curl -s http://localhost:8000/api/v1/public/invoices/$T | python3 -m json.tool
```

**PASS** payload contains only: number, lines (description/qty/gross), amounts, due date,
seller identity, bank details/QR while payable.
**ANOMALY** any client name, email, address, user id, other invoices, or account data.

| Token | Expect |
|---|---|
| Valid | 200 |
| One character changed | **bare 404** |
| Expired (`update public_token_expires_at` to the past) | **bare 404, identical body to the unknown case** |
| Draft invoice's link request | **422** |

**ANOMALY** different bodies/messages for unknown vs expired — that distinguishes
"exists but expired" from "never existed".

Rotate the token → the old one 404s. Pay via `/pay/{token}` → settles through the same
webhook path. Throttle: 20 rapid requests → 429.

---

# Round 15 — Recurring billing · `4dc553d`

**Never point this at real client rows** — it charges cards.

```php
// Create a plan on your test client, due now, then:
```
```bash
php8.3 artisan billing:charge-recurring
```

**PASS** one invoice created and paid, `current_period_*` advanced, `next_charge_at`
moved a month. Run it **again immediately** → no second charge, no second invoice.
**ANOMALY** a duplicate invoice or charge — the row lock isn't holding.

**Retry ladder.** Force declines (Stripe test card `4000 0000 0000 0341` for
off-session failure) and step the clock forward:

| Failure | `failure_count` | `next_charge_at` | `state` |
|---|---|---|---|
| 1st | 1 | +1 day | active |
| 2nd | 2 | +3 days | active |
| 3rd | 3 | +7 days | active |
| 4th | 4 | **null** | **past_due** |

**PASS** `last_failure_reason` kept, client emailed each time, admins get an in-app
notification on the handoff.
**ANOMALY** the plan reaching `cancelled` — silent cancellation is explicitly forbidden.

**SCA:** trigger `requires_action` (`4000 0027 6000 3184`). **PASS** payment parks as
`processing` with the intent secret in metadata, retry in 3 days, and **`failure_count`
stays 0**. **ANOMALY** the counter increments — a client not finishing 3DS would burn
the retry ladder.

**Card removed:** delete the plan's payment method → plan survives, My Services shows
the warning, the sweep skips it rather than erroring.

**Pause / resume / cancel:** from `/client/services` and the admin screen; a paused plan
is skipped by the sweep; price change takes effect **next** cycle, not this one.

---

# Round 16 — Invoice document · `b9304f2`

Open the print route for a **DE B2B deposit invoice** and check all nine probes:

1. Invoice number 2. Issue date 3. Seller UID 4. Firmenbuchnummer + register court
5. Customer UID 6. Art. 196 reverse-charge note 7. Giro-Code QR 8. IBAN in the footer
9. Leistungsdatum / service period

Blank items 3/4/8 = missing `COMPANY_*`, expected. **ANOMALY** a missing invoice number
or absent reverse-charge note on an RC invoice — both make the document legally invalid.

Also check: a partially-paid invoice shows paid **and** open amounts; a recurring
invoice prints its billing period; print in both themes; the PDF download matches
the on-screen render.

---

# Round 17 — Mail and notifications · `108cb76`

**Set `MAIL_MAILER=log` first** unless every recipient is your own address.

Trigger each and check rendering, links and the legal footer:

| Event | Mail |
|---|---|
| Admin issues | InvoiceIssued |
| Payment applies | PaymentReceipt |
| Payment fails | PaymentFailed |
| Reminder due | InvoiceReminder |
| Proof uploaded | PaymentProofReceived → **all admins** |
| Proof rejected | PaymentProofRejected (with reason) |
| Guest deposit settles | GuestWelcome (set-password link) |
| Recurring charge fails | RecurringChargeFailed |

**PASS** every link uses `FRONTEND_URL` — **grep the rendered mail for `//pay` and for
`sentrigate`**. (Known: the legacy `payment_confirmation` template still says SentriGate;
that's pre-existing, not this feature.)

**In-app rows:** each event writes a `NotificationList` row for the recipient.
**ANOMALY** zero rows — that was the NOT NULL `user_id` bug.

**afterCommit:** wrap an issue in a transaction and roll it back → **no mail sent**.
**ANOMALY** mail delivered for a rolled-back invoice.

**FCM:** a user without tokens must not error; failures log, never throw.

Watch `failed_jobs` throughout this round — the count must stay at 35.

---

# Round 18 — Dunning · `a24055c`

```php
$inv = $invoices->issue(/* … */, $admin);
echo $inv->reminders()->pluck('offset_days')->implode(',').PHP_EOL;   // 3,7,14,21
```

Backdate `due_at` and `scheduled_for` so one is due, then:

```bash
php8.3 artisan billing:send-reminders
```

**PASS** exactly one reminder sends and stamps `sent_at`; running again sends nothing.

**Pruning — the important behaviour.** Settle the invoice, then run the sweep again:
the three remaining reminders must be **pruned**, not sent. **ANOMALY** a paid client
receives a dunning email.

Same for a cancelled invoice, and one in `awaiting_confirmation` (proof under review —
must not be dunned).

**Credit notes get no reminders:**

```php
echo $note->reminders()->count();   // expect 0
```

---

# Round 19 — Adversarial pass

Throw hostile input at every money endpoint. All must be refused with 4xx, never a
500 or a silently accepted value.

| Attack | Must not |
|---|---|
| Negative amount on pay/manual payment | create a negative payment |
| Amount greater than `amount_due` | overpay; must cap |
| `amount = 0` | create a payment row |
| `quantity` = 999999999 / `-1` | overflow `decimal(10,2)` or accept |
| `discount_percent` = 150 | produce a negative line |
| `vat_rate` = 999 | be accepted |
| Currency switched to USD mid-flow | mix currencies on one invoice |
| `' OR 1=1 --` in every search box | affect results (all searches are Eloquent) |
| `<script>alert(1)</script>` in description/notes | render unescaped in the SPA **or the PDF** |
| Another user's ids in every path param | return 200 (re-run Round 11's IDOR sweep everywhere) |
| Replay a captured webhook body with a **valid old signature** | apply twice |
| Concurrent issue of two drafts | produce a duplicate number |
| Concurrent accept of the same proof twice | create two payments |
| Delete a service that has orders | succeed |
| Soft-deleted invoice | disappear from an admin query that should see it |

Also: with `APP_DEBUG=true` here, force an error and confirm you know it would leak a
stack trace in production. **Set `APP_DEBUG=false` before go-live** and re-check that
errors still return `{ message }`.

---

# Round 20 — Regression on everything untouched

The feature changed shared files (`User`, `Invoice`, `Handler`, `guards.js`, nav,
`routes/api.php`). Confirm nothing else broke:

- Login → OTP (`/checkpoint`) → role dashboard, for **all three** roles.
- Password reset end to end (guest checkout reuses that flow).
- Admin user management: create/update/delete client, agent, admin; block/unblock; deactivate.
- Chat: client ↔ agent, agent online/offline via Pusher.
- Notification reminders admin screens.
- Reports tabs.
- The 4 legacy invoices still render on their legacy screens.
- `/client/domains` and `/client/pricing` still bounce.

**ANOMALY** anything in this list breaking — most likely from the `Invoice` enum cast
or the `Handler` change.

---

# Cleanup

Test data can't be hard-deleted casually (soft deletes + restrict FKs). Keep one
dedicated test client and mark it, rather than deleting:

```php
// identify everything created by this pass
$me = User::where('email','kamerialmir@gmail.com')->first();
printf("orders=%d invoices=%d payments=%d plans=%d\n",
  $me->serviceOrders()->count(), $me->invoices()->count(),
  $me->payments()->count(), $me->recurringPlans()->count());
```

To purge, order matters (children first): `payment_proofs` → `payments` →
`invoice_items`/`invoice_activities`/`invoice_reminders` → `invoices` (forceDelete) →
`service_order_items` → `service_orders` → the user. Anything else hits a restrict FK.

Reset the burned number sequence if you care about tidy numbers:

```php
App\Models\InvoiceSequence::where('year', now()->year)->update(['last_number' => 0]);
```

Finally re-check `failed_jobs` is still **35** and the snapshot counts match Round 0
plus what you intended to keep.

---

# Red flags — stop immediately if you see these

| Symptom | Means |
|---|---|
| Two invoices with the same number | numbering lock broken — **legal problem** |
| A gap in the number series | allocation happening outside the sequence |
| `amount_paid` double a real transaction | idempotency broken |
| Money taken but invoice unpaid | webhook not settling — the original bug |
| An issued invoice's totals changed | immutability bypassed |
| Deposit + balance ≠ order total | the cent leak is back |
| Reverse charge on a domestic sale | tax liability |
| RC invoice without the Art. 196 note | invalid invoice |
| Payment proof reachable without auth | data exposure |
| Guest checkout returning a secret for an existing email | account takeover vector |
| Unknown vs expired token distinguishable | enumeration oracle |
| Recurring plan reaching `cancelled` on failures | silent revenue loss |
| `failed_jobs` above 35 | a new queue failure |
| A dunning email to a paid client | pruning broken |
