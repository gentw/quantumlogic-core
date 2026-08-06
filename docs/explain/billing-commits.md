# Billing & Payments — phase → commit map

26 commits on `feature/billing-and-payments`. Each commit message carries its own
why-body, `Decisions:` and `Verified:` lines — `git show <hash>` for the full text.

Build order logic: make the old model unreachable → data model → legal correctness
→ safe money application → the three rails → both UIs → non-account customers →
automate recurring + overdue → prove and document.

| Hash | What it did |
|---|---|
| **Phase 0 — retire the old model** | |
| `c7de49d` | `FEATURE_SUBSCRIPTION_PLANS` (default off, fails closed). Routes unregister; `CheckSubscription`/`CheckFeature` pass through but keep the 403 JSON shape. SPA mirror + guards. Recovery guide written. |
| **Phase 1 — data model** | |
| `4c8fde3` | 12 migrations. `invoices` extended in place (`subscription_id` nullable — an invoice can finally exist without a subscription cycle), status ENUM → VARCHAR, soft deletes. `restrictOnDelete` on financial FKs. Catalogue seeder. |
| `8647d44` | 8 enums + 10 models. Fixed: a strict `=== 'paid'` in `PayPalController` that the enum cast would have broken; moved `public_token`/`trial_fingerprint`/`ip_address` into `$hidden` (they were leaking to clients). |
| **Phase 1b — legal core** | |
| `79c70e6` | Gapless numbering under `FOR UPDATE`, allocated at issue only. `locked_at` + the `updating` hook. Overdue derived, not stored. `config/billing.php`. |
| `56bbbfc` | `TaxService`: AT 20% / EU B2B reverse charge (Art. 196) / EU B2C / non-EU zero. Billing profile columns on users. VIES optional and fails open. |
| **Phase 1c — service layer** | |
| `b52f26a` | `Money` (integer cents), catalogue/order/invoice services, order transition map, credit notes. **Fixed the deposit+balance cent leak** — balance takes exact per-line remainders. |
| `7385e99` | `PaymentService::apply()` — row lock + replay short-circuit + unique key. Fixed: `apply()` was writing the legacy `payment_method` column and the lock guard rightly rejected it. |
| `c2229a9` | 5 domain exceptions → one status map in `Handler` (409/422/502), one `{ message }` shape. |
| **Phase 2 — three rails** | |
| `bc73ff4` | Stripe: PaymentIntents with derived idempotency keys, SetupIntents, signature-verified webhook → queued job (succeeded/failed/refunded/dispute/setup). |
| `47639da` | PayPal rebuilt against invoices. `custom_id` = payments row id. Pending row created before the provider order, so redirect and webhook settle the same key — **a closed tab no longer loses a payment**. |
| `367f78d` | SEPA: `config/company.php`, EPC069-12 Giro-Code QR (amount = `amount_due`, not total), content-sniffed proof upload to the private disk, admin accept/reject. |
| **Phase 3 — client UI** | |
| `0c46fcb` | Billing list (KPI strip, server-side pagination) + invoice detail with the EPC QR replacing the mock's barcode. `useBilling.js`. Drafts hidden by whitelist. |
| `1107ae9` | Split-pane checkout (Element / PayPal / bank transfer), pay-amount page, saved cards. Runtime Stripe.js loader. Cash-in-person dropped. |
| `3142a28` | My Services (recurring cards + order accordion), client nav gains Billing + My Services. |
| **Phase 4 — admin UI** | |
| `8f40978` | Invoice list (filter drawer, **Paid + Outstanding columns the mock lacked**) and detail. "Delete invoice" → cancel (drafts) or credit note (issued). Draft-only PATCH. |
| `fe6d90a` | Create-invoice rebuilt as a 5-step stepper (the mock was circular: pick an invoice to create an invoice). Draft created at the preview step so totals are real. |
| `fdfacb8` | Reconciliation queue + payments ledger — **the two screens the mocks never had**. Without the queue the SEPA rail has no ending. Slip fetched as an authenticated blob (a plain href 401s). |
| `483b4e5` | Service catalogue, orders list/detail, client billing card, admin nav. |
| **Phase 5 — selling without an account** | |
| `6710328` | Guest checkout via `ClientAccountService` — **not** a fifth signup endpoint. Existing email → no secret, no token. Fixed: `origin` missing from `$fillable` silently no-oped activation; `ResetCodePassword` uses `token`, not `code`. |
| `6e93a9a` | Public pay links: 64-char 30-day token, whitelisted payload, unknown = expired = 404. |
| **Phase 6 — recurring** | |
| `4dc553d` | `RecurringBillingService`: chunked sweep under per-row locks, 1/3/7 retry ladder → `past_due` (never silent cancellation), SCA parks without counting as a failure. `billing:charge-recurring` at 03:00. |
| **Phase 7 — document, comms, proof** | |
| `b9304f2` | §11 UStG printable invoice, every company detail from `config/company.php`, Giro-Code, German-first labels. |
| `108cb76` | 8 mails on one template + `BillingNotifier` (mail + in-app + FCM). Fixed: `$afterCommit` redeclaration is a PHP fatal (set it in the constructor); `NotificationList.user_id` is NOT NULL so in-app rows were silently failing. |
| `a24055c` | `billing:send-reminders` at 09:00 — 3/7/14/21 ladder, prunes reminders for invoices that got paid. *Gates ran post-hoc on this one (wrong cwd); all passed.* |
| `ecd328b` | 39 tests / 105 assertions. `DatabaseTransactions` on real MySQL (sqlite impossible — MySQL-specific DDL; `RefreshDatabase` would wipe the dev DB). |
| `b6aa6aa` | Synced `CLAUDE.md`, project overview and all four `.claude/agents/*` — so a future audit doesn't flag the design (public webhooks, flag-off pass-through) as a bug. |

## Deliberate deviations from the source screenshots

| Mock | Built | Why |
|---|---|---|
| 4 hardcoded Kosovan banks | Stripe Payment Element | SCA/3DS is mandatory in the EU |
| Decorative barcode | EPC069-12 Giro-Code QR | actually scannable |
| Cash in person, +€2 fee | dropped | not a rail the agency wants |
| VAT 18% | 20% + reverse charge | Austrian seller |
| NET 7 | NET 14 | agency terms |
| "Delete invoice" | cancel (draft) / credit note (issued) | §132 BAO retention |
| Free editing of issued invoices | draft-only editing | §11 UStG |
| Client-side pagination | server-side | scale |
| Pick client → pick invoice → create invoice | 5-step stepper | the mock was circular |
| *(missing)* | reconciliation queue, payments ledger, catalogue, orders | the SEPA rail had no ending |
