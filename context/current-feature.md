# Current Feature: General Client Fixes

<!-- Feature Name -->

## Status

<!-- Not Started|In Progress|Completed -->

In Progress

## Goals

<!-- Goals & requirements -->

- **Hide the retired Plans & Billing link** from the avatar menu. It points at
  `/client/plans-billing`, which `isDisabledModuleRoute` already blocks — a dead link today.
  Gated behind `appFeatures.subscriptionPlans`, not deleted, per the flag-off pattern.
- **Hide the unbuilt Tickets entries** from the client nav behind a new `appFeatures.tickets`
  flag; the client entry currently targets an 11-line placeholder page. Agent and admin
  Tickets items have no `to:` at all and get the same treatment.
- **Rebuild the client dashboard on real billing data.** Replace the three hardcoded widgets
  (`25.00` / `01-03-26` / `150.00`) and the dead "Pay now" button with a next-invoice-due card,
  a KPI strip, recent invoices and active services. Requires extending
  `ClientBillingController::summary()` to expose the oldest unpaid invoice's id, number and
  amount — it computes that invoice today but only returns its date.
- **Make logout actually end the session.** Eight recorded defects: hard-coded API host,
  cookies assigned `false` when `useCookie` only deletes on `null`, `localStorage`
  user/subscription/trial_fp left behind, and teardown skipped entirely when the request 401s.
  Session teardown moves into one shared `clearSession()` helper.
- **Protect admin and agent routes.** The guard's `protectedRoutes` list auto-expands only for
  `client-*`, so a logged-out visitor can mount `/admin/invoices`. Logged-out users must land
  on the login page; the protected list becomes generic rather than hand-maintained.
- **Make the app multilingual** — `de` (Austrian), `en` (fallback), `sq` — across views,
  emails and validation. `vue-i18n` is installed and the navbar switcher is already mounted,
  but no plugin is registered and no locale files exist, so `$t` throws today. Active language
  resolved backend-side from the request IP (`CF-IPCountry`, else bundled GeoLite2), persisted
  to `users.locale` so cron-dispatched dunning mail can address recipients correctly.
- **Make 2FA OTP optional**, disabled by default, re-enabled by the user from account settings
  behind a current-password confirmation. Login currently never issues a token at all — it
  always defers to `verify-otp` — so `store()` gains the token-minting path the SPA already
  knows how to handle.
- **Scrub pre-pivot branding.** All six transactional emails still render a "Sentri Gate"
  wordmark; the older name survives in four more files. The product is QuantumLogic.

## Notes

<!-- Any extra notes -->

Full spec: [`fixes/general-client-fixes.md`](fixes/general-client-fixes.md) — seven fixes with
file:line anchors, the phase → commit table, out-of-scope list and open questions.

**Branch.** `general-client`, cut from `feature/billing-and-payments` (not from `main`) — these
fixes sit on top of the billing work and must merge back there, **not** into `main`.

**Ordering.** Fix 7 (branding) lands before the 5.2/5.4 translation phases so string extraction
captures final English rather than text that changes again. Fix 6 (2FA) is independent of 1–5
and can be pulled forward; it shares only `pages/client/account.vue` with fix 5.4.

**Constraints carried in from `CLAUDE.md` / `context/`:**
- Do not touch the dormant security module — this includes the `sentrigate.txt` reference in
  `DomainController.php`, which fix 7's grep will match and must skip
- Thin controllers, logic in `app/Services/`; FormRequests for validation; API Resources
- SPA is JavaScript, Vue 3 + Vuetify 3 + Vuexy; light **and** dark parity required
- `check-subscription` keeps its 403 JSON shape; `guards.js` keeps exactly one `next()`
- New migrations only (`users.locale`, `users.two_factor_enabled`); never edit historical ones
- Do not add a fifth signup endpoint — fix 5b touches all four only to set `locale`

**Agent/skill sync required in the same change** (per `context/ai-interaction.md`): fix 6 →
`.claude/agents/auth-auditor.md`; fix 5 → `code-scanner.md` + `refactor-scanner.md`; fixes 2–3
→ `ui-reviewer.md`; fix 7 → `.claude/skills/cleanup/SKILL.md`.

**Commit cadence.** One detailed commit per fix, in the order of the `## Commit plan` table in
[`fixes/general-client-fixes.md`](fixes/general-client-fixes.md); message format in
[`../.claude/skills/feature/actions/commit.md`](../.claude/skills/feature/actions/commit.md).
⚠️ Per project memory: use `php8.3` explicitly, and `pnpm`/`eslint` may be unavailable — if a
gate cannot run, say so in the `Verified:` line rather than implying it passed.

**Open items to settle during implementation:**
- **Blocking fix 6:** should 2FA stay enforced for admins? As specified it defaults off for
  every role, so admin accounts with full client, invoice and payment access lose their second
  factor on migrate. Recommendation: off by default for clients, forced on for `admin`.
- Should an admin be able to reset a locked-out user's 2FA? Without it, a user who enables 2FA
  and loses email access has no recovery path.
- Should the invoice PDF follow the customer's locale, or always render German for AT
  customers? Needs the accountant, alongside the reverse-charge wording already pending below.
- Does `sq` need a Kosovo (`XK`) vs Albania (`AL`) split, or is one Albanian locale enough?

**⏸️ Parked: Billing & Payments (In Progress).** This slot held that feature before the fixes
were loaded. Spec and phase plan are intact in
[`features/billing-and-payments.md`](features/billing-and-payments.md); work is committed on
`feature/billing-and-payments` through `657a3b4`. Its open items, which lived only here:
- Confirm VAT treatment and the reverse-charge wording with the accountant before go-live
- Decide whether VIES UID validation is live-checked or admin-entered
- Confirm whether any live beta `Subscription` rows need migrating to `RecurringPlan`
- Populate the real `COMPANY_*` values (UID, Firmenbuchnummer, register court, IBAN/BIC)

Reload it with `/feature load billing-and-payments` once the fixes merge back.

## History

<!-- Keep this updated. Earliest to latest. The /feature complete action appends here automatically; deeper, topic-organized history lives in ./backend-history.md and ./frontend-history.md. -->

- 2026-05-16 — **Subscription System Hardening** — Replaced ad-hoc trial/subscribe/renewal flow with `SubscriptionState` enum + `SubscriptionService` (locked transactions, idempotent), `TrialService` (4-way abuse gate), `InvoiceService` (one invoice per cycle). Hardened scheduler with `withoutOverlapping` + per-row locks; PayPal callback now uses the locked service. SPA: `useTrialFingerprint` composable, relative `/v1/*` URLs via `VITE_API_BASE_URL`, pay-now actually calls `startTrial`, `guards.js` rewritten to single `next()` per navigation. Side fixes: `User::activeSubscription()` was calling a non-existent relation; `StartTrialRequest::authorize()` returned `false`; `upgradeDowngrade` route pointed to a missing controller method. Full manifest in [`../history/2026-05-16-subscription-system-hardening.md`](../history/2026-05-16-subscription-system-hardening.md).
- 2026-07-25 — **QuantumLogic pivot** — Repurposed the SentriGate security codebase as QuantumLogic Core, the agency's customers/services/tickets platform. Security module switched off behind `FEATURE_SECURITY_MODULE` / `VITE_FEATURE_SECURITY_MODULE` (code retained; recovery guide at [`../docs/modules/security/README.md`](../docs/modules/security/README.md)), and removed from the auto-loaded context files so it costs no tokens. Rebranded: theme purple `#301068` with a lightened `#7C5CD6` for dark mode plus `primary-accent` lavender, new `AppLogo.vue` with a theme-swapped brand mark cropped from the site wordmark, favicon, and copy across SPA pages and backend emails. Nav standardised to English. Git remote moved to `gentw/quantumlogic-core`. Side fixes: added the missing `DomainController` import that made every domain route 500; replaced two hard-coded `beta.sentrigate.com` email links with `config('app.frontend_url')`; swapped the old brand red `#dc3545` out of the email templates; removed a debug `console.log` plus dead locals from `guards.js` and the admin reports list; replaced a hard-coded light-grey that broke dark mode on the client dashboard widgets.
