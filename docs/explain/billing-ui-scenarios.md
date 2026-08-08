# Billing & Payments — every scenario testable from the browser

Complete catalogue of what can be exercised through the web interface. Grouped by
surface, so you can work one screen at a time. For backend-only invariants
(concurrency races, lock probes, idempotency under parallel load) see
[billing-test-plan.md](billing-test-plan.md) — this file is browser-only.

**Run every screen twice: light theme and dark.** And at 375 / 768 / 1280 px.

**Mail is `MAIL_MAILER=log`** — "an email arrives" below means *a rendered mail appears
in `api/storage/logs/laravel.log`*. Never switch to smtp while 5000+ real client rows exist.

**The queue worker must be running** or nothing settles.

---

## A. Public — no login required

### A1. Guest checkout — `/order`

| # | Scenario | Expect |
|---|---|---|
| 1 | Open the page anonymously | Blank layout, no nav, no auth redirect |
| 2 | Catalogue contents | Only publicly orderable services: Website Development, SEO Retainer, Hosting, Maintenance. **Not** Web Application or Consulting |
| 3 | Pick a service, change quantity | Quote pane updates live: net, VAT, gross |
| 4 | Set country **AT** | 20% VAT applied |
| 5 | Set country **DE** + valid UID (`DE123456789`) | VAT drops to 0, reverse-charge notice appears |
| 6 | Set country **DE**, no UID | Back to 20% (B2C) |
| 7 | Set country **US** | 0%, export note |
| 8 | UID from the wrong country (`ATU…` with DE) | Stays 20% — no reverse charge |
| 9 | Submit with a **new** email → pay the 50% deposit | Redirect to `/order/success`; the welcome mail carries the password they chose |
| 10 | Check the DB/log after 9 | Account created `origin=guest_checkout`, order `active`, deposit invoice `paid`, welcome mail rendered |
| 11 | Log in with the password typed at checkout | Works immediately — no set-password step |
| 12 | Submit with an **existing** client email | Order attaches to that account and is payable inline; "you already have an account" notice; the typed password is **ignored** and no `pay_token` is returned |
| 13 | Submit 12+ times rapidly | Throttled (429) after ~10 |
| 14 | Deposit amount shown | Exactly 50% of gross; balance stated as due later |
| 15 | Refresh mid-checkout | No duplicate order, no duplicate account |

### A2. Guest success — `/order/success`

16. Confirmation shows order number and what happens next.
17. Visiting it directly without an order → no crash, sensible empty state.

### A3. Public pay link — `/pay/{token}`

| # | Scenario | Expect |
|---|---|---|
| 18 | Open a valid link, logged out | Invoice summary + seller block, no login required |
| 19 | Payload contents | Number, lines, amounts, due date, seller identity **only** — no client name, email, address, or other invoices |
| 20 | Pay by card | Settles via webhook; page reflects paid |
| 21 | Bank transfer tab | IBAN/BIC/reference + EPC QR |
| 22 | Change one character of the token | Bare 404 |
| 23 | Expired token | **Identical** 404 — indistinguishable from unknown |
| 24 | Already-settled invoice link | Clear terminal state, no payment form |
| 25 | Rotate the link in admin, retry the old one | Old token 404s |
| 26 | 20 rapid requests | Throttled |

### A4. Personalised order link — `/order/{slug}?coupon={code}`

Create the code first in `/admin/services` → ticket icon → **Create code**, then copy
its link. Browse it **logged out** — the role guard bounces logged-in users out of
`/order` to their own dashboard.

| # | Scenario | Expect |
|---|---|---|
| 26a | Open the copied link | Only that one service is shown, pre-selected; heading is the service name |
| 26b | Quote pane | "Discount" row with the code as a chip; Net is the **list** price, the discount is subtracted below it |
| 26c | VAT | Charged on the **discounted** net, not the list price |
| 26d | Complete the purchase | Invoice line carries `discount_percent`; deposit is 50% of the discounted gross |
| 26e | Reuse a `max_uses = 1` code | Second visit prices at full list, warning banner names the code |
| 26f | Expired code, deactivated code, typo'd code | All behave identically — no hint which one it was |
| 26g | Code from service A used on service B's link | Ignored, full price, warning banner |
| 26h | Code in the wrong case (`FOR-EROS-SEFA`) | Applies — codes are case-insensitive |
| 26i | `/order/{slug}` with no coupon | Works as a plain single-service order at list price |
| 26j | `/order/unknown-slug` | Warning banner, falls back to the full catalogue |
| 26k | Deactivated service's link | Same fallback — a dead link must never quietly sell something else |

### A5. Manual payment — `/order/{slug}?manual-payment`

| # | Scenario | Expect |
|---|---|---|
| 26l | Open the link | Copy says "by bank transfer"; the CTA reads **Place the order** |
| 26m | Place the order | **No Stripe element ever loads.** IBAN, BIC, reference, amount and EPC QR shown inline |
| 26n | Reference | The invoice number — that is what reconciliation matches on |
| 26o | New email | Button opens `/pay/{token}` to upload the transfer receipt |
| 26p | Existing email | No token; button sends them to sign in instead |
| 26q | State afterwards | Invoice issued and payable, order stays `awaiting_payment` — nothing is settled until an admin reconciles |
| 26r | Combine with `?coupon=` | Both apply; discount visible in the quote and on the invoice |

---

## B. Client

### B1. Billing list — `/client/billing`

| # | Scenario | Expect |
|---|---|---|
| 27 | KPI strip | Outstanding, next due, open count match the invoice list |
| 28 | Status filter each value | List narrows correctly |
| 29 | Search by number / reference | Server-side — watch the network tab re-fetch |
| 30 | Paginate | New request per page, not client-side slicing |
| 31 | **Drafts** | Never appear, in any filter |
| 32 | An overdue invoice | Chip reads *Overdue*, not *Sent* |
| 33 | Client with no invoices | Helpful empty state, not a blank panel |
| 34 | Row actions | View and Pay route correctly |

### B2. Invoice detail — `/client/billing/invoices/{id}`

| # | Scenario | Expect |
|---|---|---|
| 35 | Open a `sent` invoice | Lines with qty/unit/discount/VAT; net/VAT/gross/paid/due panel |
| 36 | EPC QR | Present while payable; **absent** once paid |
| 37 | Scan the QR with a banking app | Beneficiary, IBAN, amount pre-fill (amount = **remaining**, not original) |
| 38 | A reverse-charge invoice | Art. 196 notice shown |
| 39 | A partially paid invoice | Paid and due both shown, QR asks for the remainder |
| 40 | Download invoice (PDF) | Renders, filename is the invoice number |
| 41 | A paid invoice | No Pay button; status chip *Paid* |
| 42 | A credit note | Negative amounts, links to the parent invoice |
| 43 | Another client's invoice id in the URL | 403 / not-found — **never** their data |

### B3. Checkout — `/client/billing/checkout/{id}`

| # | Scenario | Expect |
|---|---|---|
| 44 | Open on a payable invoice | Split pane: summary left, three rails right |
| 45 | Card `4242 4242 4242 4242` | Succeeds; invoice `paid` **via the webhook** |
| 46 | Card `4000 0025 0000 3155` | 3DS challenge, then succeeds |
| 47 | Card `4000 0000 0000 0002` | Declined, reason shown, invoice unchanged |
| 48 | Card `4000 0000 0000 9995` | Insufficient funds, invoice stays `sent` |
| 49 | **Close the tab mid-3DS** | Invoice still reaches `paid` — the whole point of the rail |
| 50 | Pay the same invoice twice | Second attempt refused; exactly one payment row |
| 51 | Switch rails back and forth | No duplicate Payment Elements, no lost amount |
| 52 | PayPal → approve | Returns and settles |
| 53 | PayPal → cancel | Returns cleanly, invoice untouched |
| 54 | Bank transfer tab | IBAN/BIC/reference + EPC QR |
| 55 | Upload a valid proof (pdf/jpg/png) | Invoice → *awaiting confirmation*, **not** paid |
| 56 | Upload an `.exe` renamed `.pdf` | Rejected — content sniffed |
| 57 | Upload a 15 MB file | Rejected |
| 58 | After proof upload, switch to card | **Still payable** — awaiting_confirmation is payable |
| 59 | Open checkout for a **paid** invoice | "This invoice is settled" terminal state, no card form |
| 60 | Open checkout for a **cancelled** invoice | "Nothing to pay" state |
| 61 | `?amount=` less than due | Partial payment; remainder still owed |
| 62 | `?amount=` greater than due | Capped server-side |

### B4. Pay page — `/client/billing/pay`

63. Pay latest / pay selected / custom amount / pay everything all route correctly.
64. Custom amount settles the **oldest** debt first.
65. "Pay everything" walks invoices one at a time (one intent per invoice, by design).
66. Client with nothing owed → clear empty state.

### B5. Payment methods — `/client/billing/payment-methods`

67. Add a card (SetupIntent) → appears in the list after the webhook.
68. First card added becomes **default** automatically.
69. Set a different card default → badge moves.
70. Delete the default → next card is promoted.
71. Delete the last card → empty state; any active recurring plan shows a warning.
72. Card details shown are brand + last4 only — **never** a full number or token.

### B6. My Services — `/client/services`

73. Recurring services render as cards: price, interval, next charge, state chip.
74. An active plan with **no card on file** → warning linking to payment-methods.
75. Pause a plan → state changes, next charge clears.
76. Resume → next charge repopulates.
77. Cancel → terminal state, no further charges.
78. Orders accordion shows status, date, total, and line detail.
79. A `past_due` plan is clearly distinguished from `active`.

---

## C. Admin

### C1. Invoice list — `/admin/invoices`

| # | Scenario | Expect |
|---|---|---|
| 80 | Filter drawer | Status dots, active-count badge, multi-select |
| 81 | Search by number / reference / client name | Debounced, server-side |
| 82 | **Paid and Outstanding columns** | Reflect partial payments correctly |
| 83 | Row menu on a **draft** | Offers Issue and Cancel |
| 84 | Row menu on an **issued** invoice | Offers Credit note — **never Delete** |
| 85 | Deep links (`?action=`) | Open the record-payment and reminder dialogs |

### C2. Invoice detail — `/admin/invoices/{id}`

| # | Scenario | Expect |
|---|---|---|
| 86 | Open a **draft** | Fields editable; Issue and Cancel available |
| 87 | Edit and save a draft | Saves; totals recalculate |
| 88 | Issue it | Gets `QL-2026-XXXX`, due date = issue + 14 days, status `sent` |
| 89 | Open it again after issuing | **Immutability banner**; fields read-only |
| 90 | Try to cancel an issued invoice | Refused — credit note only |
| 91 | Issue credit note | Lines negated, parent `cancelled`, both documents remain |
| 92 | Record manual payment | Applies; capped at amount due |
| 93 | Record more than the amount due | Refused or clamped — never negative due |
| 94 | Add reminder | Scheduled relative to the due date |
| 95 | Activity timeline | `created → issued → payment_received …` in order |
| 96 | Client rail | UID shown for a B2B client |
| 97 | Generate public pay link | 64-char token, URL uses the configured frontend host (**check for `//`**) |
| 98 | Print / PDF | Legal document renders |

### C3. Create invoice — `/admin/invoices/add-invoice`

99. Step 1: client search finds existing clients.
100. Step 2: pick an existing order **or** build one from the catalogue; service picker pre-fills description and price.
101. Step 3: choose Deposit(%) / Milestone / Balance / One-off / Recurring.
102. Step 4: due date, reference, terms, notes.
103. Step 5: preview shows net/VAT/gross and a reverse-charge notice for an EU B2B client.
104. **Save as draft** → appears in the list as draft, no number allocated.
105. **Issue now** → number allocated immediately.
106. Abandon the wizard at preview → a draft remains, no number burned.
107. Deposit + Balance for the same order → the two invoices sum **exactly** to the order total.

### C4. Payments ledger — `/admin/payments`

108. Filter by provider (stripe / paypal / bank_transfer / manual).
109. Filter by status.
110. Search by provider id, invoice number, client name.
111. Refunded payments show the refund delta against the amount.
112. Row links through to the invoice.
113. CSV export downloads the current page.

### C5. Reconciliation — `/admin/payments/reconciliation`

114. Pending proofs listed with uploader, file size, note.
115. **Amount-mismatch chip** when the claimed amount ≠ the invoice's open amount.
116. Open the slip → renders (authenticated blob, **no 401 in the console**).
117. Accept → invoice `paid`, confirming admin recorded, receipt mail rendered.
118. Reject without a reason → refused.
119. Reject with a reason → payment failed, invoice back to `sent`, client mailed the reason.
120. Review the same proof twice → no-op, no second payment.
121. Two pending proofs on one invoice, reject one → invoice stays `awaiting confirmation`.

### C6. Service catalogue — `/admin/services`

122. Table shows billing type, net price, VAT, deposit default, public orderability, active.
123. Create a service → slug auto-generates.
124. Edit price/VAT → reflected on **new** orders only, never on issued invoices.
125. Toggle `is_publicly_orderable` → appears/disappears from `/order`.
126. **Deactivate** → hidden from new orders; **no Delete option exists**.

**Discount codes** — ticket icon on each row:

126a. Dialog lists this service's codes with the resulting price beside each percentage.
126b. Create a code → appears immediately with a ready-made `/order/{slug}?coupon={code}` link.
126c. Link icon copies that URL; the tooltip shows it in full.
126d. A duplicate code is refused — codes are unique across the whole catalogue, not per service.
126e. **Valid through** means end of that day, not midnight at its start.
126f. Status chip distinguishes live / inactive / expired / used up.
126g. **Deactivate**, never delete — a redeemed code is the record of why an order was discounted.
126h. On a service that is not publicly orderable, the dialog warns the link will not resolve.
126i. `used_count` rises only when an order is **placed**, never when a quote is priced.

### C7. Orders — `/admin/orders` and `/admin/orders/{id}`

127. List filters and search.
128. Detail shows lines with totals, client card, deposit-split preview.
129. Invoice schedule links through to each invoice.
130. Status buttons offer **only** valid transitions (draft → submit → activate → start delivery → complete).
131. An invalid transition is not offered; forcing it via URL is refused.
132. Cancel is available while non-terminal, absent once completed.

### C8. Client record — `/admin/clients/{id}`

133. Billing card at the foot: lifetime value, outstanding, invoice count, saved methods, five most recent invoices.
134. Figures match the invoice list for that client.

---

## D. Cross-cutting

### D1. Themes and responsive
135. Every screen above in **dark mode** — no hardcoded light greys, chips and QR still legible.
136. 375 px: nav drawer collapses, tables scroll rather than overflow the page.
137. 768 px and 1280 px: split panes reflow sensibly.

### D2. Roles and access
138. Client sees no admin/agent nav entries.
139. Agent sees no billing admin screens.
140. Client hitting an `/admin/*` URL directly → bounced.
141. Admin hitting a `/client/*` billing URL → bounced or 403.
142. Logged out, hitting any billing URL → `/login`.

### D3. Retired and dormant modules
143. `/client/pricing`, `/client/plans-billing`, `/client/invoice/change-plan/*` → bounced (retired).
144. `/client/domains`, `/client/alarm-alerts` → bounced (dormant security).
145. All of the above bounce **logged out** too, with no redirect loop or console error.

### D4. Error and edge states
146. Stop the API mid-action → error surfaces as a snackbar, never a silent failure.
147. Every async action shows a loading state.
148. Every list has a meaningful empty state.
149. A page loading a nonexistent id → clean not-found, not a blank shell.
150. XSS probe (`<script>alert(1)</script>`) in description/notes/reference → escaped in the SPA **and** in the printed invoice.

---

## Known gaps — expected, not bugs

- **`COMPANY_*` unset** → invoice PDF legal footer (UID, Firmenbuchnummer, IBAN) renders blank. Scenarios 98 and 37's beneficiary will look incomplete.
- **`PAYPAL_WEBHOOK_ID` unset** → PayPal's webhook returns 400. Scenario 52 works via the browser return; the webhook backstop does not.
- **Legacy pages** `admin/invoices/edit/{id}` and `admin/invoices/preview/{id}` belong to the retired subscription module and still render — out of scope.
- **Login quirk:** non-client accounts are matched on the `phone` column. `admin@ds.com` works; an admin whose phone column holds a number must log in with that number.
