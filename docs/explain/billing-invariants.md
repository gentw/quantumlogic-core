# Billing invariants — why it won't let you do that

Three rules the whole module hangs off. Each was verified with a real concurrent
race during its phase and has a test guarding it now.

---

## 1. Issued invoices are immutable (`locked_at`)

**Meaning:** one nullable timestamp on `invoices`. **Null = draft, freely editable.
A date = issued, commercial content frozen forever.** The timestamp records when.

Legal basis: §11 UStG. Corrections go through **credit notes**, never edits.

### Why a separate column instead of `status`

`status` keeps moving for the invoice's whole life (`sent` → `paid` → `cancelled`,
back to `sent` on refund). A guard keyed on status would need to enumerate every
"issued" status and would silently stop covering any new one — fail-open, wrong
direction for a legal rule. `locked_at` is a **one-way latch**: set once, never
unset, so the question is just "was it frozen before this write?"

### Why not `issued_at`

`issued_at` is populated on **drafts** (legacy plan-tier baggage — see
`createDraftFromOrder`). Keying on it would freeze every draft at creation.

### How it's enforced — `Invoice::booted()`

```php
static::updating(function (Invoice $invoice) {
    if (! $invoice->getOriginal('locked_at')) return;
    $illegal = array_diff(array_keys($invoice->getDirty()), self::MUTABLE_WHEN_LOCKED);
    if ($illegal !== []) throw InvoiceLockedException::for($invoice, array_values($illegal));
});
```

It's a **whitelist of what may change**, not a blacklist. Add a column tomorrow and
it's frozen by default — you must deliberately opt it into mutability.

**`getOriginal()` matters:** the issue transition writes `invoice_number`, `status`,
the dates *and* `locked_at` in one save. Reading the dirty value would see "locked"
during the very save that locks it and reject the number — the invoice could never
be issued. `getOriginal()` asks "was it locked *before* this write?"

`forceFill` bypasses mass-assignment protection but **not** model events. The lock
can't be shortcut by using a different write method.

### `MUTABLE_WHEN_LOCKED` — the nine allowed columns

`status`, `amount_paid`, `amount_due`, `paid_at` (payment lifecycle) ·
`cancelled_at` (credit note cancels its parent) · `exported_pdf_path` (PDF cache) ·
`public_token`, `public_token_expires_at` (rotate a pay link without touching
content) · `updated_at` (Eloquent always touches it).

The thread: **none of these change what the invoice says.** They record what
happened *to* it. No line item, amount owed, VAT rate, date, party or reference is
reachable once locked.

`locked_at` is **not** on the list → there is no sequence of Eloquent calls that
unlocks an invoice.

### Limits (be honest about these)

Application guard, not a DB trigger. Raw SQL and query-builder mass updates
(`Invoice::where(...)->update([...])`) don't fire model events and slip past. Fine
today — everything goes through the model — but remember it if you write a
data-repair script.

### Check it by hand

```php
$i->subtotal_net = 1; $i->save();   // throws: "Invoice QL-2026-0001 is locked…"
$i->amount_paid = 10; $i->save();   // OK
$i->locked_at = null; $i->save();   // throws
```

In the admin UI: draft fields go read-only behind an immutability banner, and the
action menu switches from Edit/Cancel to Credit note.

Tests: `InvoiceImmutabilityTest.php`

---

## 2. Invoice numbers are gapless (`invoice_sequences`)

Austrian law needs a continuous series (*fortlaufende Rechnungsnummer*).

- `InvoiceNumberService::allocate()` runs in a transaction with
  `SELECT … FOR UPDATE` on the `(prefix, year)` row.
- **Never** `max(id)+1` or `count()+1` — both race, both leave gaps.
- **Allocated at issue only.** Drafts carry `draft-{uniqid}` placeholders, so an
  abandoned draft never burns a number.
- First-allocation-of-a-year race is settled by the unique index: the losing insert
  catches the duplicate-key error and re-selects the winner's row under lock.

Verified live: 4 processes × 25 allocations = 100 unique numbers, no gaps, no dupes.
In-suite: a second raw connection probes the held row with `FOR UPDATE NOWAIT`.

Tests: `InvoiceNumberingTest.php`

---

## 3. Payment application is idempotent (`idempotency_key`)

**The bug this fixes:** the old PayPal flow confirmed on the browser redirect —
close the tab and the money was received but never recorded.

Webhooks are the source of truth; the browser return is UX only. `PaymentService::apply()`
has three layers:

1. **Invoice row lock** serialises concurrent callers.
2. **Terminal payment with the same key → no-op replay** (returns the existing payment).
3. **Unique `idempotency_key`** catches the residual insert race; the duplicate-key
   error is caught and converted into a replay too.

Key shapes: `stripe:pi:{id}`, `paypal:pmt:{id}`, `manual:{…}`. The pending payment
row is created **before** calling the provider, so the key exists before any race
can start.

`guardPayable` distinction that makes this work: a genuinely **new** key against a
settled invoice throws `PaymentAlreadyAppliedException` (409), but **replays never
throw**.

Verified live: 2 processes × 20 applies of one key → 1 payment row, `amount_paid`
exactly 120.00.

Tests: `PaymentIdempotencyTest.php`

---

## Related rules worth remembering

| Rule | Where |
|---|---|
| Uploading a SEPA proof marks **nothing** paid — only admin accept does | `BankTransferService` |
| Drafts are invisible to clients (whitelist, so new statuses default to hidden) | `ClientBillingController` |
| Recurring failure ladder 1/3/7 days → `past_due`, **never silent cancellation** | `RecurringBillingService` |
| SCA `requires_action` does **not** increment `failure_count` | `RecurringBillingService` |
| Guest checkout on an **existing** email returns no client secret and no pay token | `PublicCheckoutController` |
| Unknown and expired public tokens are **both** bare 404s | `PublicInvoiceController` |
| Webhooks are public **by design** — the provider signature is the auth, verified before any work | `Stripe/PayPalWebhookController` |
| Overdue is **derived**, never stored (`is_overdue` accessor + `overdue()` scope) | `Invoice` |
| Services are deactivated, never deleted (FKs refuse anyway) | `ServiceCatalogueService` |

## Error → HTTP status map (`Handler::BILLING_STATUS`)

| Exception | Status |
|---|---|
| `InvoiceLockedException`, `PaymentAlreadyAppliedException` | **409** |
| `InvalidInvoiceStateException`, `InvalidOrderStateException` | **422** |
| `PaymentGatewayException` | **502** |

Body is always Laravel's default `{ message }`. Non-API requests fall through untouched.
