# Billing money math

All calculation happens in **integer cents** via `App\Support\Money`; columns stay
`decimal(10,2)` and convert at the boundary. Never float euros.

```php
Money::toCents(float|string $euros): int      // round($euros * 100)
Money::toEuros(int $cents): float            // round($cents / 100, 2)
Money::split(int $total, float $fraction)    // [part, total - part]  <- rest absorbs remainder
```

`split()` returns `[part, rest]` where `rest = total − part`, so a split is
arithmetically **incapable** of not summing to its input.

---

## Line totals

From `ServiceOrderService::recalculate()`:

```
undiscounted = round(unit_price_cents × quantity)
net          = round(undiscounted × (1 − discount_percent/100))    -> line_total_net
line_vat     = round(net × vat_rate/100)
gross        = net + line_vat                                      -> line_total_gross
```

Two roundings, both in cents, both at named points.

## Order totals — and the invariant

```
subtotal_net   += undiscounted        <- LIST PRICE, BEFORE DISCOUNT
discount_total += undiscounted − net
vat_total      += line_vat            <- VAT on the DISCOUNTED net
total_gross    += net + line_vat
```

**`total_gross` = `subtotal_net` − `discount_total` + `vat_total`**

There is **no** order-level "net after discount" column. That figure — the one
actually taxed — is implied by subtraction. Compute it first when reconciling.

### Worked example

€3,200 website build + 10 consulting hours @ €95 less 10%, both 20% VAT:

| | undiscounted | net | vat | gross |
|---|---|---|---|---|
| Line 1 | 320000 | 320000 | 64000 | 384000 |
| Line 2 | 95000 | 85500 | 17100 | 102600 |

→ `subtotal_net` 4150.00, `discount_total` 95.00, `vat_total` 811.00, `total_gross` 4866.00
Check: 4150 − 95 + 811 = 4866 ✓

## Why `vat_total` is a sum, never `net × rate`

1. Lines can carry **different** rates (20% build next to a 0% reverse-charged line).
2. Even at one uniform rate the numbers differ: each line's VAT rounds to a whole
   cent independently, so summing rounded line VATs ≠ rounding one big multiplication.

Austrian invoices are checked line by line, so the per-line sum is the legally
correct one. Same reason `invoices.vat_rate` is nullable and display-only.

---

## Deposit / balance split — the cent-leak trap

`deposit_percent` null → `depositSplit()` falls back to **50%**.

**Deposit** lines are scaled: `Money::split(origNet, fraction)[0]`, gross derived
from that net.

**Balance** lines are NOT scaled. They take the exact remainder
(`BillingInvoiceService::snapshotLines` + `alreadyInvoicedCents`):

```php
$netCents   = $origNet   - $prior['net'];
$grossCents = $origGross - $prior['gross'];
```

`$prior` = what the order's **other** invoices already carry, keyed by `sort_order`,
**excluding cancelled invoices and credit notes**.

### Why (the bug this prevents)

Line net 855.01 = 85501 cents, 50/50 split:

| | old (scale twice) | now (remainder) |
|---|---|---|
| Deposit | round(85501 × 0.5) = **42751** | 42751 |
| Balance | round(85501 × 0.5) = **42751** | 85501 − 42751 = **42750** |
| Sum | 85502 ✗ **one cent invented** | 85501 ✓ |

Gross leaks independently, because VAT rounds separately: a 3-cent net line at 20%
has a 4-cent gross; scaling gives 2 + 1 = 3, remaindering gives 2 + 2 = 4. That's
why **gross is remainder-computed too**, not re-derived from the remaindered net.

Fuzz-tested: 30 random orders at 30/40/50% deposits, zero leaks.
Test: `api/tests/Feature/Billing/DepositSplitTest.php`

## `sort_order` is the join key — and its one caveat

`invoice_items` are copies with fresh primary keys and **no FK back to the order
line**. `sort_order` is the only identity that survives the snapshot, so it's what
`alreadyInvoicedCents()` keys its map by.

**Caveat:** reordering an order's lines *after* a deposit invoice is issued would
attribute prior amounts to the wrong lines. The order total stays right (it's a
permutation), but individual balance lines could come out wrong or negative.
Nothing in the UI offers line reordering today. If you add drag-to-reorder on an
order that already has invoices, revisit this.

---

## VAT resolution (`TaxService::resolve`)

| Customer | Rate | Note |
|---|---|---|
| AT | 20% (or the service's rate) | domestic |
| Other EU **with usable UID** | 0% | **reverse charge** + mandatory Art. 196 note |
| Other EU without UID | seller rate | B2C |
| Non-EU | 0% | service export |
| Null country | domestic fallback | |

"Usable UID" = format check **plus** country prefix match. VIES live lookup only
when `BILLING_VIES_LIVE_CHECK=true`, and **fails open** with a logged error — a VIES
outage must never block invoicing.

The resolved rate is **written onto the line** and never recomputed. If Austria
changes its rate, historical invoices keep the rate that was correct when issued.
