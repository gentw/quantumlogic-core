# Current Feature

<!-- Feature Name -->

## Status

<!-- Not Started|In Progress|Completed -->

Not Started

## Goals

<!-- Goals & requirements -->

## Notes

<!-- Any extra notes -->

## History

<!-- Keep this updated. Earliest to latest. The /feature complete action appends here automatically; deeper, topic-organized history lives in ./backend-history.md and ./frontend-history.md. -->

- 2026-05-16 — **Subscription System Hardening** — Replaced ad-hoc trial/subscribe/renewal flow with `SubscriptionState` enum + `SubscriptionService` (locked transactions, idempotent), `TrialService` (4-way abuse gate), `InvoiceService` (one invoice per cycle). Hardened scheduler with `withoutOverlapping` + per-row locks; PayPal callback now uses the locked service. SPA: `useTrialFingerprint` composable, relative `/v1/*` URLs via `VITE_API_BASE_URL`, pay-now actually calls `startTrial`, `guards.js` rewritten to single `next()` per navigation. Side fixes: `User::activeSubscription()` was calling a non-existent relation; `StartTrialRequest::authorize()` returned `false`; `upgradeDowngrade` route pointed to a missing controller method. Full manifest in [`../history/2026-05-16-subscription-system-hardening.md`](../history/2026-05-16-subscription-system-hardening.md).
