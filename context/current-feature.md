# Current Feature: Subscription System Hardening

## Status

In Progress

## Goals

- Prevent trial abuse: one trial per user_id, email, and IP; no re-trial after cancellation or expiry
- Enforce a strict subscription state machine (`trial_active`, `active`, `past_due`, `expired`, `cancelled`) with access gated on state + `ends_at`
- Require payment method collection at trial start (stored as token, never charged during trial)
- Make subscription creation idempotent — never create duplicate rows; extend, reactivate, or convert existing records
- Wrap all subscription mutations in `DB::transaction()` + `lockForUpdate()` to prevent race conditions
- Harden the scheduler for auto-renewals: `withoutOverlapping()`, per-subscription locking, DB transactions
- Enforce invoice integrity: one invoice per billing cycle per subscription, invoice linked to payment record
- Expose subscription state clearly to the SPA so UI gating reflects real backend state

## Notes

- Root cause: trial and billing logic are decoupled, no strict lifecycle contract, middleware validates access but isn't the single source of truth
- New DB fields needed: `trial_used_at`, `trial_ip`, `trial_device_hash` on the subscriptions/users table
- Trial price must always be `0.0` — no charges or pre-authorizations at trial start
- Subscription row must be the single source of truth (`ends_at` + `state` column)
- Scheduler must use `withoutOverlapping()` to prevent concurrent renewal runs
- If payment method becomes invalid during trial, mark state accordingly and restrict auto-renewal
- Business decision needed: deny trial entirely if user refuses to provide a payment method, or allow a limited trial

## History

<!-- Keep this updated. Earliest to latest. The /feature complete action appends here automatically; deeper, topic-organized history lives in ./backend-history.md and ./frontend-history.md. -->
