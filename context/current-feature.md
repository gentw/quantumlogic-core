# Current Feature

<!-- Feature Name -->

## Status

<!-- Not Started|In Progress|Completed -->

Not Started

## Goals

<!-- Goals & requirements -->

## Notes

<!-- Any extra notes -->

Next up is the actual product core: **customers, services, tickets**. None of it is
modelled yet — the SPA has ticket dashboard widgets and nav placeholders (`My Tickets` →
`second-page`, admin `Tickets`, admin `Reports` tickets tab) but there is no backend
behind any of them. Agree the data model before writing code.

## History

<!-- Keep this updated. Earliest to latest. The /feature complete action appends here automatically; deeper, topic-organized history lives in ./backend-history.md and ./frontend-history.md. -->

- 2026-05-16 — **Subscription System Hardening** — Replaced ad-hoc trial/subscribe/renewal flow with `SubscriptionState` enum + `SubscriptionService` (locked transactions, idempotent), `TrialService` (4-way abuse gate), `InvoiceService` (one invoice per cycle). Hardened scheduler with `withoutOverlapping` + per-row locks; PayPal callback now uses the locked service. SPA: `useTrialFingerprint` composable, relative `/v1/*` URLs via `VITE_API_BASE_URL`, pay-now actually calls `startTrial`, `guards.js` rewritten to single `next()` per navigation. Side fixes: `User::activeSubscription()` was calling a non-existent relation; `StartTrialRequest::authorize()` returned `false`; `upgradeDowngrade` route pointed to a missing controller method. Full manifest in [`../history/2026-05-16-subscription-system-hardening.md`](../history/2026-05-16-subscription-system-hardening.md).
- 2026-07-25 — **QuantumLogic pivot** — Repurposed the SentriGate security codebase as QuantumLogic Core, the agency's customers/services/tickets platform. Security module switched off behind `FEATURE_SECURITY_MODULE` / `VITE_FEATURE_SECURITY_MODULE` (code retained; recovery guide at [`../docs/modules/security/README.md`](../docs/modules/security/README.md)), and removed from the auto-loaded context files so it costs no tokens. Rebranded: theme purple `#301068` with a lightened `#7C5CD6` for dark mode plus `primary-accent` lavender, new `AppLogo.vue` with a theme-swapped brand mark cropped from the site wordmark, favicon, and copy across SPA pages and backend emails. Nav standardised to English. Git remote moved to `gentw/quantumlogic-core`. Side fixes: added the missing `DomainController` import that made every domain route 500; replaced two hard-coded `beta.sentrigate.com` email links with `config('app.frontend_url')`; swapped the old brand red `#dc3545` out of the email templates; removed a debug `console.log` plus dead locals from `guards.js` and the admin reports list; replaced a hard-coded light-grey that broke dark mode on the client dashboard widgets.
