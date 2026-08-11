# General Client Fixes (Laravel + Vue)

Branch: `general-client` (off `feature/billing-and-payments`)

## Problem

The client-facing portal still carries leftovers from the SentriGate/subscription era and
from placeholder scaffolding:

- The avatar menu links to **Plans & Billing**, a page belonging to the retired
  subscription-plans module — the router guard already blocks it, so it is a dead link.
- **My Tickets** is in the client nav but tickets have no model, migration or API.
- The client dashboard shows **hardcoded fake numbers** (`25.00`, `01-03-26`, `150.00`) and a
  "Pay now" button wired to nothing, while a real billing summary endpoint already exists.
- **Logout is leaky**: it hits a hard-coded host, leaves cookies and `localStorage` behind,
  and silently does nothing if the request fails. Separately, admin and agent routes are
  absent from the guard's protected list, so a logged-out visitor can mount those pages.
- The app is **English-only**. `vue-i18n` is installed and the navbar switcher is already
  mounted, but no i18n plugin is registered and no locale files exist — `$t` would throw today.
- **OTP is mandatory for every login**, with no way to turn it off. Login never issues a
  token at all — it always returns `{redirect: 'checkpoint'}` and defers to `verify-otp`.

## Root Cause

- Retired-module UI was flagged off in the nav (`navigation/vertical/index.js`) but the same
  pass never reached the avatar menu.
- The dashboard was built from screenshots with placeholder data and never rewired once
  `GET /v1/client/billing/summary` landed in the Billing & Payments work.
- Session teardown lives inline in one component instead of a shared helper, so it has no
  single definition of "what a session consists of".
- The route guard's protected list was written by hand and only auto-expands for `client-*`
  routes; `admin-*` / `agent-*` were never added.
- i18n was scaffolded by the Vuexy template and left switched off; no application-level
  locale strategy was ever chosen.
- OTP was built as an unconditional step in `store()` rather than as a user-held setting, so
  there is no flag to read and no code path that mints a token without it.

## Solution

Six independent fixes, each committable on its own, in the order below. Fixes 1–2 are
hide-only. Fix 3 needs a small backend addition. Fix 4 is a correctness/security fix. Fix 5 is
the large one and is phased internally. Fix 6 changes the login contract.

---

## Changes Required

---

### 1. Hide "Plans & Billing" from the avatar menu

**Where:** [`web/src/layouts/components/UserProfile.vue:97-102`](web/src/layouts/components/UserProfile.vue#L97-L102)

The item links to `/client/plans-billing`. That route name is already listed in
`SUBSCRIPTION_ROUTE_PREFIXES` ([`web/src/utils/features.js`](web/src/utils/features.js)) and
blocked by `isDisabledModuleRoute` in
[`plugins/1.router/guards.js:63-65`](web/src/plugins/1.router/guards.js#L63-L65) — clicking it
today bounces the user back to `/client`.

**Rules:**
- Do **not** delete it. Wrap it in `v-if="appFeatures.subscriptionPlans"`, matching how the nav
  already handles retired-module entries
  ([`navigation/vertical/index.js:35-52`](web/src/navigation/vertical/index.js#L35-L52)).
  Flag-off, not delete — the repo pattern from `context/ai-interaction.md`.
- The menu item is also mislabelled in source: its comment reads `<!-- Settings -->`. Fix it.
- Profile stays. Logout stays.

---

### 2. Hide "My Tickets"

**Where:** [`web/src/navigation/vertical/index.js:11-17`](web/src/navigation/vertical/index.js#L11-L17) (client → `second-page`)

Tickets are unbuilt — `context/project-overview.md` lists them as **Not built**. The client
entry points at `pages/second-page.vue`, an 11-line placeholder.

**Rules:**
- Add a `tickets: false` entry to `appFeatures` in
  [`web/src/utils/features.js`](web/src/utils/features.js) and gate the nav entry on it, same
  shape as `security` / `subscriptionPlans`.
- **Decision:** frontend-only flag, no matching `api/config/features.php` key. `CLAUDE.md`
  requires both sides to agree for module flags, but tickets have no backend routes to gate —
  a backend flag would gate nothing. Add the API-side key in the same change as the first
  ticket endpoint.
- Same-shape leftovers on the other roles — agent `My Tickets`
  ([`:94-100`](web/src/navigation/vertical/index.js#L94-L100)) and admin `Tickets`
  ([`:160-166`](web/src/navigation/vertical/index.js#L160-L166)) — have no `to:` at all and
  render as dead nav items. Out of the stated client-side scope but a one-line fix each;
  gate them on the same flag while the file is open.
- Leave `pages/second-page.vue` on disk. It is still referenced by `guards.js` (see fix 4,
  which removes that reference).

---

### 3. Rebuild the client dashboard on real data

**Where:** [`web/src/pages/client/index.vue`](web/src/pages/client/index.vue) (635 lines, Options API)

**What is fake today:**
| Location | Problem |
|---|---|
| `:384-400` `widgetData` | Hardcoded `Latest Invoice 25.00`, `Payment Due Date 01-03-26`, `Balance 150.00` |
| `~:508` "Pay now" `VBtn` | No click handler |
| `:449-455` Welcome card | Static "QuantumLogic" body text, no user context |
| `:519` `TicketsStatisticsWidget` | Statistics for a module that does not exist |

**Backend change required.** `GET /v1/client/billing/summary`
([`ClientBillingController::summary`](api/app/Http/Controllers/Api/ClientBillingController.php#L28-L47))
returns `last_invoice_total`, `last_invoice_number`, `next_due_at`, `outstanding_balance`,
`open_invoices`. Note `last_invoice_*` describe the **most recently sent** invoice, which is
not necessarily the one that needs paying. The controller already computes `$nextDue` (oldest
`due_at`, status `Sent`/`AwaitingConfirmation`, `amount_due > 0`) but only exposes its date.

Extend the payload with the next-due invoice's `id`, `invoice_number`, `amount_due` and
`status` so the dashboard can render it and deep-link the pay action. Keep the existing keys —
they are consumed elsewhere in the billing views.

**New dashboard composition** (replacing the three fake widgets and the tickets widget):

1. **Next invoice due** — primary card. Invoice number, gross amount, due date, a
   days-until/overdue chip, and a **Pay now** button routing to
   `client-billing-checkout-id` with the invoice id. Empty state: "You're all caught up."
2. **KPI strip** — Outstanding balance · Open invoices · Active services. First two from the
   summary endpoint; active services count from `GET /v1/client/services`.
3. **Recent invoices** — last 5 from `GET /v1/client/billing/invoices?per_page=5`, status
   chip per row, row click → `client-billing-invoices-id`, footer link → `client-billing`.
4. **My Services** — active services with next renewal date where a `RecurringPlan` exists,
   footer link → `client-services`.

**Rules:**
- Fetch through a Pinia store action or a composable, never inline in the component
  (`context/coding-standards.md`). One `Promise.all` on mount, skeleton loaders while pending.
- All money via the existing billing formatter — do not re-implement cent math in the view.
- Remove `TicketsStatisticsWidget` from this page; leave the component file on disk for when
  tickets ship.
- Keep the chat widget block at the bottom of the file untouched.
- Light **and** dark parity, checked via the Vuexy theme toggle.
- The file is Options API. Converting it wholesale to `<script setup>` is not in scope — add
  new logic in the existing style, or extract the dashboard body into a `<script setup>`
  component under `views/client/dashboard/` and keep the page as a thin wrapper. Prefer the
  extraction; do not rewrite the chat plumbing to achieve it.

---

### 4. Make logout actually end the session

**Where:** [`web/src/layouts/components/UserProfile.vue:8-28`](web/src/layouts/components/UserProfile.vue#L8-L28)
and [`web/src/plugins/1.router/guards.js`](web/src/plugins/1.router/guards.js)

**Defects found:**

1. **Hard-coded host.** The call targets `https://api.quantumlogic.at/api/v1/logout`, bypassing
   `VITE_API_BASE_URL`. Violates `context/coding-standards.md` ("No hard-coded URLs"); breaks
   local and staging. Must be `$api('/v1/logout', { method: 'POST' })`.
2. **Cookies are not deleted, only falsified.** `@core/composable/useCookie.js` only issues a
   `maxAge: -1` delete when the value is `null`/`undefined`. Logout assigns `false`, so every
   cookie physically survives holding the literal `false`. Assign `null`.
3. **Incomplete teardown.** Cleared: `isOtp`, `accessToken`, `phoneNo`, `userData`,
   `chatClientId`, `chatChatId`. **Not** cleared:
   - `redirect_uri` cookie — set at [`pages/login.vue:91`](web/src/pages/login.vue#L91) and
     read back at [`:50-52`](web/src/pages/login.vue#L50-L52), so a stale value redirects the
     *next* user who logs in on that browser.
   - `localStorage.user` and `localStorage.subscription` — written by
     [`guards.js:24-25`](web/src/plugins/1.router/guards.js#L24-L25) and
     [`:119`](web/src/plugins/1.router/guards.js#L119). Leaves the previous account's profile
     readable after logout.
   - `localStorage.trial_fp` — [`App.vue:29`](web/src/App.vue#L29).
   - Pinia store state (chat participants, message list) and the live Pusher subscription.
4. **Failure leaves the user logged in.** The whole teardown sits *after* `await $api(...)`
   inside `try`. An expired token returns 401, the `catch` only `console.error`s, and nothing
   is cleared. Teardown must run in `finally` — the server call is best-effort.
5. **`location.reload()` instead of navigating.** Relies on the guard to bounce. Replace with
   `router.replace({ name: 'login' })` after teardown.
6. **Crash on next mount.** [`UserProfile.vue:4`](web/src/layouts/components/UserProfile.vue#L4)
   reads `useCookie('userData').value.img` at setup with no optional chaining → TypeError once
   `userData` is falsy. Same pattern at `:58`, `:83`, `:85`, `:89`.
7. **Admin and agent routes are unprotected.**
   [`guards.js:41-50`](web/src/plugins/1.router/guards.js#L41-L50) builds `protectedRoutes` from
   `['second-page','root','client','agent','admin']` plus every `client-*` route. `admin-*` and
   `agent-*` sub-routes are never added, so a logged-out visitor hitting `/admin/invoices` or
   `/agent/account` mounts the page and only fails when its API calls 401. **This is the core
   of "the login page should be shown instead."**
8. **Wrong post-login-page redirect.**
   [`guards.js:93-94`](web/src/plugins/1.router/guards.js#L93-L94) sends an already-logged-in
   user visiting `/login` to `second-page` — the tickets placeholder being hidden in fix 2.
   Send them to their role root instead.

**Backend:** [`AuthenticationController::destroy`](api/app/Http/Controllers/Api/AuthenticationController.php#L253-L263)
revokes only the current access token. It should also revoke the token's refresh tokens
(`RefreshToken::where('access_token_id', ...)`), and return a 200 JSON body in the
`Auth::user() === null` branch instead of falling off the end and returning an empty body.

**Rules:**
- Extract teardown into a single `clearSession()` helper in `web/src/utils/` (auto-imported)
  so there is one definition of what a session is. Both the logout button and the 401 handler
  should call it.
- Build the protected list generically: any route whose name starts with `client-`, `admin-`
  or `agent-`, plus the role roots. Do not hand-maintain a list.
- Keep **exactly one `next()` per navigation** — `guards.js` is explicitly documented that way
  in `CLAUDE.md`.
- Preserve the 403 → `/client/pricing` behaviour of the subscription gate; the flag is the
  switch, not a rewrite.
- Verify manually: log in → log out → browser back button → login page, not a cached
  dashboard. Then paste `/admin/invoices` while logged out → login page.

---

### 5. Multilingual: views, emails, everything

**Locales:** `de` (Austrian German), `en` (fallback/default), `sq` (Albanian).
**Detection:** backend IP → country, no third-party service.

#### 5a. Current state

- `vue-i18n@9.10.1` is a dependency, `@intlify/unplugin-vue-i18n@2.0.0` is in devDeps, and
  `'vue-i18n'` is in the auto-import list ([`vite.config.js:64`](web/vite.config.js#L64)) —
  but **no i18n plugin is registered** in `web/src/plugins/` and **no locale files exist**.
  Calling `$t` today throws.
- [`web/themeConfig.js:18-27`](web/themeConfig.js#L18-L27): `i18n.enable: false`, one lang.
- The navbar switcher is already mounted —
  `@core/components/I18n.vue` in
  [`DefaultLayoutWithVerticalNav.vue:86`](web/src/layouts/components/DefaultLayoutWithVerticalNav.vue#L86).
  Nothing new to build for the UI control.
- Backend: `config/app.php:88` `locale => 'en'`, **no `lang/` directory at all**, 7 Blade
  emails in `resources/views/emails/`, `resources/views/billing/invoice.blade.php`, 15
  Mailables in `app/Mail/`.
- No geo package in `composer.json`, no `locale` column on `users`.

**Scope:** 64 pages + 32 views + 22 components + 10 layouts = **128 SFCs**, plus nav titles
(the `@layouts` nav components already pipe titles through i18n), plus 8 Blade templates.

#### 5b. Backend — locale resolution

Add `app/Services/LocaleService.php` (thin controllers, logic in services):

```
resolve order:  users.locale (explicit choice)
             →  geo country from request IP
             →  Accept-Language
             →  config('app.fallback_locale')  // en
```

- **Country → locale map:** `AT`, `DE`, `CH` → `de`; `AL`, `XK`, `MK` → `sq`; everything
  else → `en`. Keep the map in `config/locale.php`, not inline.
- **IP → country:** read `CF-IPCountry` when present (free and exact behind Cloudflare),
  otherwise a bundled MaxMind GeoLite2-Country DB. No per-request external call.
- ⚠️ `app/Http/Middleware/TrustProxies.php` sets `$proxies = '*'`, so `X-Forwarded-For` is
  caller-controllable when not actually behind a proxy. Spoofing only changes the *guessed*
  language, so severity is low here — but the same `$request->ip()` feeds `PreventTrialAbuse`.
  Pin `$proxies` to the real edge before go-live; note it, don't fix it in this branch.
- New middleware `SetLocale` on the `v1` group → `App::setLocale(...)`. Register the alias in
  `app/Http/Kernel.php`.

**Migration:** add `users.locale` — nullable `char(2)`, indexed. Required, not optional:
queued and cron-dispatched mail (`billing:send-reminders` at 09:00,
`billing:charge-recurring` at 03:00) runs with **no request and no IP**, so the recipient's
language must be persisted on the row. New migration only — never edit a historical one.

Populate it on: guest checkout via `ClientAccountService`, all four signup endpoints, and
admin-created clients.

#### 5c. Backend — translations

- Create `lang/{en,de,sq}/` with `auth.php`, `validation.php`, `billing.php`, `mail.php`.
  Laravel ships `en` validation strings; `de` and `sq` need publishing/translating.
- Convert the 7 email Blades + `billing/invoice.blade.php` to `__()` keys.
- **Mailables must carry the recipient's locale.** A queue worker has no request context —
  use `Mail::to($user)->locale($user->locale)` or `Mailable::locale()` at dispatch, for all 15
  classes in `app/Mail/`.
- The email templates still carry pre-pivot branding. That is **fix 7** — land it first so the
  translation pass works on correct English source strings, not on text that has to change again.
- **Invoices are legal documents.** The invoice PDF should follow the customer's locale, but
  the `config/company.php` legal footer, the reverse-charge wording and the VAT labels must be
  reviewed before shipping a German version — this is already an open item in
  `context/current-feature.md`. Translate the layout; leave the statutory wording flagged for
  the accountant.

#### 5d. Frontend

- Create `web/src/plugins/i18n.js` (numbered to load before the router, like `2.pinia.js`)
  and `web/src/plugins/i18n/locales/{en,de,sq}.json`.
- Flip `themeConfig.app.i18n.enable` to `true` and add all three `langConfig` entries
  (`isRTL: false` for all three). The existing switcher then renders.
- Seed the active locale from `GET /v1/locale` (public, unauthenticated — the login page needs
  it) and from the login response for authenticated users. Persist the user's manual switch to
  the Vuexy namespaced storage key **and** `PATCH` it to `users.locale`.
- **Key structure:** namespace by area — `nav.*`, `billing.*`, `auth.*`, `dashboard.*`,
  `common.*`. Not one flat file per page.
- **Untranslated Albanian is already sitting in the SPA** and the extraction pass will hit it:
  `pages/client/preferences.vue` has Albanian subtitles (`:34`, `:43`, `:51`, `:60`, `:69`,
  `:77`, `:85`), and `pages/client/account.vue` has Albanian validation messages (`:43-44`).
  These need an `en` key written from scratch plus a real `sq` value — they are not
  translations of existing English. The same subtitle block is duplicated in the admin and
  agent preferences pages.
- Nav titles in `navigation/vertical/index.js` become keys; the `@layouts` nav components
  already resolve them through i18n.
- **Formatting:** money and dates through `vue-i18n` number/datetime formats, not hand-rolled
  strings. `de-AT` renders `1.234,56 €` — currently formatted the `en` way everywhere.
- Vuetify's own strings (data-table footers, pickers) need the Vuetify locale set in
  `plugins/vuetify/` to match.

#### 5e. Phasing

Fix 5 does not land in one commit:

| Phase | Content |
|---|---|
| 5.1 | Backend: `LocaleService`, `config/locale.php`, `SetLocale` middleware, `users.locale` migration, `GET /v1/locale` |
| 5.2 | Backend: `lang/` files, Blade emails converted, Mailable locale plumbing, branding scrub |
| 5.3 | Frontend: i18n plugin, three locale files, `themeConfig` flip, switcher wired, Vuetify locale |
| 5.4 | Frontend: extract strings — auth + nav + client pages first (the stated scope) |
| 5.5 | Frontend: extract strings — admin + agent pages |

Phases 5.4/5.5 are mechanical and large. Do them per-area with a working app after each.

---

### 6. Make 2FA (OTP) optional — off by default, opt-in from settings

**Where:** [`api/app/Http/Controllers/Api/AuthenticationController.php`](api/app/Http/Controllers/Api/AuthenticationController.php),
[`web/src/pages/login.vue`](web/src/pages/login.vue),
[`web/src/pages/client/account.vue`](web/src/pages/client/account.vue)

#### Current behaviour

`store()` **never issues a token**. Every successful password check generates a 6-digit code,
mails it, and returns `{redirect: 'checkpoint'}`; the token is only minted in
[`verifyOtp`](api/app/Http/Controllers/Api/AuthenticationController.php#L311-L341) via
`$user->createToken('appToken')`. OTP is generated at three separate sites in the same file
(`:138`, `:191`, `:359`) with the same copy-pasted `rand()` + `Otp::updateOrCreate` block.

**The SPA already supports the OTP-off path.**
[`pages/login.vue:75-86`](web/src/pages/login.vue#L75-L86) branches on whether the response
carries a `redirect`: with no redirect it sets `accessToken` / `userData` and routes straight
to `/{role}`. That branch is currently dead code because the backend always sends a redirect.
So the login page needs **no change** — only the backend has to start taking the other branch.

#### Target behaviour

| `two_factor_enabled` | Login response | Result |
|---|---|---|
| `false` (default) | `{ success, token: { token }, user }` | Straight into `/{role}` |
| `true` | `{ redirect: 'checkpoint' }` | Existing OTP flow, unchanged |

#### Storage

New migration adding `users.two_factor_enabled` — `boolean`, `default(false)`, not null.

**Decision: on `users`, not `user_preferences`.** `store()` must read the flag *before*
authentication completes, on every login. `user_preferences` is a side table with a nullable
FK and no guaranteed row per user ([migration](api/database/migrations/2024_11_08_225934_create_user_preferences_table.php)),
so reading it there means a join plus a null-row fallback on the hot auth path. It is also a
security setting, not a notification preference — the rest of that table is email/push opt-ins.

Existing users get `false` on migrate, matching "disabled by default". Flag in the commit
body that this **downgrades every current account from mandatory 2FA to none**.

#### Backend changes

- Extract the triplicated OTP block into `app/Services/TwoFactorService.php` — `issueFor(User)`
  (generate, persist, mail) and `verify(string $identifier, string $code)`. Thin controllers,
  logic in services, per `context/coding-standards.md`.
- In `store()`, after a successful `Auth::attempt`, branch on `$user->two_factor_enabled`:
  enabled → `TwoFactorService::issueFor()` and return the redirect; disabled → mint the token
  and return the same `{token, user}` shape `verifyOtp` returns, so the SPA branch works
  unmodified.
- `add-user-email` path (client with no email on file) keeps its existing redirect regardless
  of the flag — a user with no email cannot receive a code, and that branch is about
  collecting the address, not about 2FA.
- New endpoints under the authenticated group:
  `GET /v1/user/two-factor` and `POST /v1/user/two-factor` (`{ enabled: bool }`).
  **Require the current password** in the enable/disable request — a settings toggle that
  flips 2FA from a hijacked session is worse than no 2FA. FormRequest, not inline validation.
- Enabling should verify the user can actually receive codes: send a test OTP and only persist
  `true` once it is confirmed. Otherwise a user with a stale email locks themselves out.

#### Frontend changes

- Add a **Two-Factor Authentication** card to
  [`pages/client/account.vue`](web/src/pages/client/account.vue), next to the existing
  password-change section at `:392-470` — that is where security settings belong, and the
  page already has the current-password field pattern to reuse. (`preferences.vue` is
  notification opt-ins; the toggle does not belong there.)
- `VSwitch` bound to the fetched state, current-password confirmation, explanatory subtitle,
  snackbar on success/failure, and a clear "codes are sent to <email>" line.
- Same card for agent and admin accounts — the toggle is per-user, not per-role.
- `pages/login.vue` and `pages/checkpoint.vue`: **no changes**. `guards.js` `isOtp` handling:
  **no changes**.

#### Adjacent defects found in the same code — fix while here

1. **Null-pointer 500s on unknown accounts.** `store()` does
   `User::where('email', request('phone'))->where('role','client')->first()` and then reads
   `$user->blocked` with no null check ([`:122-128`](api/app/Http/Controllers/Api/AuthenticationController.php#L122-L128)),
   and the same pattern on the phone branch ([`:162-169`](api/app/Http/Controllers/Api/AuthenticationController.php#L162-L169)).
   An unknown email returns a **500 instead of 401**, which is also a user-enumeration oracle.
   Fix 6 restructures those branches anyway.
2. **`rand()` is not cryptographically secure** — use `random_int()`. Cheap, and now that 2FA
   is opt-in, the users who deliberately turn it on are the ones relying on it.
3. **No throttling on `verify-otp`.** A 6-digit code with unlimited guesses is brute-forceable
   in minutes. Add Laravel's `throttle` middleware to the route and an attempt counter on the
   `Otp` row.

Items 2 and 3 are pre-existing and strictly speaking belong to an auth pass, but they sit in
the exact lines fix 6 rewrites, and shipping "optional 2FA" while the optional part is weak
would be the wrong trade. Include them; call them out under `Decisions:` in the commit.

---

### 7. Scrub pre-pivot branding — the product is QuantumLogic

The 2026-07-25 pivot rebranded email bodies and footers but missed the header wordmark and the
`<title>` tags, so **every transactional email still renders "Sentri Gate" at the top**. Two
older names survive in the codebase and neither should appear anywhere: the app is
**QuantumLogic**.

**Every remaining occurrence** (grepped, excluding the dormant security module):

| File | Line | Content |
|---|---|---|
| `api/resources/views/emails/send_otp.blade.php` | 15 | `Sentri <span…>Gate</span>` wordmark |
| `api/resources/views/emails/send_otp_welcome.blade.php` | 15 | same wordmark |
| `api/resources/views/emails/reset_password.blade.php` | 15 | same wordmark |
| `api/resources/views/emails/new_user.blade.php` | 15 | same wordmark |
| `api/resources/views/emails/join_waitlist.blade.php` | 15 | same wordmark |
| `api/resources/views/emails/payment_confirmation.blade.php` | 15 | `Sentri<span…>Gate</span>` |
| `api/resources/views/emails/send_otp.blade.php` | 6 | `<title>DELTA CONNECT - Reset password</title>` |
| `api/resources/views/emails/send_otp_welcome.blade.php` | 6 | same wrong title |
| `api/resources/views/emails/new_user.blade.php` | 6 | same wrong title |
| `api/resources/views/emails/reset_password.blade.php` | 6 | same title (only correct one) |
| `api/app/Mail/SendUserRegisterConfirmation.php` | 34 | subject `Mirë se vini në Delta Connect` |
| `web/src/pages/client/preferences.vue` | 26 | `Tips on getting more out of Delta Connect.` |
| `web/src/pages/admin/preferences.vue` | 26 | same string |
| `web/src/pages/agent/preferences.vue` | 26 | same string |

**Rules:**
- Replace the wordmark with the same treatment the bodies already use — plain `QuantumLogic`
  with the brand purple `#301068` on the second half if the two-tone look is kept. Emails
  cannot import the SPA's `AppLogo.vue`; keep it inline HTML.
- Three of the four `<title>` tags say "Reset password" on templates that are **not** password
  resets (OTP, welcome OTP, new user). Give each its real subject while fixing the brand.
- `SendUserRegisterConfirmation` line 34 is also Albanian; fix 5 will key it properly, so here
  just correct the brand name and leave the language alone rather than churning it twice.
- **`api/app/Http/Controllers/DomainController.php:144`** references a `sentrigate.txt` probe
  file. It belongs to the dormant security module — **do not touch it**
  (`context/ai-interaction.md`). It is also a real external filename, not a brand string.
- Add a grep to the cleanup pass so this cannot silently return:
  `grep -rniE "delta ?connect|sentri ?gate|sentrigate"` over `web/src` and `api/{app,resources,config}`.
  Consider wiring it into [`.claude/skills/cleanup/SKILL.md`](.claude/skills/cleanup/SKILL.md)
  as a new housekeeping check — that file explicitly asks for new scrub concerns to be added.

---

## Agents and skills to update

`context/ai-interaction.md` requires agent/skill definitions to move in the **same** change:

- **Fix 6** changes the auth flow → update
  [`.claude/agents/auth-auditor.md`](.claude/agents/auth-auditor.md) with the optional-2FA
  contract, `users.two_factor_enabled`, `TwoFactorService`, and the two new endpoints.
- **Fix 5** adds a service and middleware (`LocaleService`, `SetLocale`) → update
  [`.claude/agents/code-scanner.md`](.claude/agents/code-scanner.md) and
  [`.claude/agents/refactor-scanner.md`](.claude/agents/refactor-scanner.md).
- **Fixes 2–3** change the client nav and dashboard → update
  [`.claude/agents/ui-reviewer.md`](.claude/agents/ui-reviewer.md).
- **Fix 2** adds a module flag → per the same rule, update every agent that enumerates
  modules or routes.
- **Fix 7** adds a housekeeping scrub → update
  [`.claude/skills/cleanup/SKILL.md`](.claude/skills/cleanup/SKILL.md) with the stale-brand grep.

## Commit plan

One commit per fix, in order, per `context/ai-interaction.md` — conventional subject, a body
explaining *why* grouped by area, deviations under `Decisions:`, and a `Verified:` line naming
only what actually ran.

| # | Subject |
|---|---|
| 1 | `fix(client): hide the retired Plans & Billing link from the avatar menu` |
| 2 | `fix(nav): gate the unbuilt Tickets entries behind a feature flag` |
| 3 | `feat(client): rebuild the dashboard on real billing data` |
| 4 | `fix(auth): tear down the full session on logout and protect admin/agent routes` |
| 5.1–5.5 | `feat(i18n): …` one per phase in the 5e table |
| 6 | `feat(auth): make two-factor OTP an opt-in per-user setting` |
| 7 | `fix(branding): drop the pre-pivot names from emails and preferences` |

**Ordering:** fix 7 should land **before** fix 5.2/5.4 so the translation pass extracts final
English, not text that changes again. Fix 6 is independent of 1–5 and can be pulled forward if
it is the priority — it shares no files with them except `pages/client/account.vue`, which
fix 5.4 also touches.

## Gates before each commit

```bash
cd api  && ./vendor/bin/pint && php artisan test
cd web  && pnpm lint && pnpm build
```

⚠️ Per project memory: use `php8.3` explicitly; `pnpm`/`eslint` may not be available in this
environment — if a gate cannot run, say so in the commit's `Verified:` line rather than
implying it passed.

## Out of scope

- The dormant security module — do not touch (`context/ai-interaction.md`).
- Consolidating the four signup endpoints — known TODO, not this branch (but fix 5b touches
  all four to set `locale`).
- Converting `pages/client/index.vue` wholesale to `<script setup>`.
- Building tickets. Fix 2 only hides the entry points.
- Pinning `TrustProxies::$proxies` — noted in 5b, belongs to a security pass.
- TOTP/authenticator-app or SMS as 2FA methods. Fix 6 keeps the existing email-code mechanism
  and only makes it optional.
- The `sentrigate.txt` probe filename in the dormant security module (fix 7).

## Open questions

- Should the **invoice PDF** follow the customer's locale, or always render German for AT
  customers? Needs the accountant's answer alongside the reverse-charge wording already
  pending in `context/current-feature.md`.
- Does `sq` need a Kosovo (`XK`) vs Albania (`AL`) split, or is one Albanian locale enough?
- Should an admin be able to override a client's locale from the client edit screen?
- **Should 2FA stay enforced for admins?** Fix 6 as specified defaults it off for *every*
  role, so admin accounts with full client, invoice and payment access lose the second factor
  on migrate. Recommendation: default off for clients as asked, but keep it forced on for
  `admin` (and optionally `agent`) via a `config/auth.php` role list, so the toggle is hidden
  rather than off for them. Flagging rather than assuming — say the word and the spec changes.
- Should an admin be able to reset a locked-out user's 2FA from the client edit screen? Without
  it, a user who enables 2FA and then loses email access has no recovery path.
