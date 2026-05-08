# SentriGate

Intelligent web & server security platform — AI-driven threat detection, WAF/traffic filtering, origin protection, real-time alarms, and a multi-tenant SaaS billing layer (Basic & Premium).

**Status:** private beta, in active development. Some flows below are partially implemented — verify against current code before assuming behavior.

---

## Repo layout

This is a monorepo with two independent apps:

```
/var/www/sentrigate
├── api/   # Laravel 10 backend (REST API, v1)
└── web/   # Vue 3 SPA (Vuexy admin template)
```

The two are deployed and developed separately. There is no top-level package manager — `cd` into `api/` or `web/` for any command.

---

## Backend — `api/`

### Stack
- **Laravel** 10.10+, **PHP** 8.1+
- Auth: **Passport** 12.3 + **Sanctum** 3.3 + **tymon/jwt-auth** 2.1 (three packages installed; the API guard is the primary one — confirm in `config/auth.php` before touching auth).
- Payments: **srmklive/paypal** 3.0
- Real-time: **Pusher** PHP server 7.2, **FCM** via `laravel-notification-channels/fcm`
- API docs: **L5-Swagger** 8.6
- Other: Google API client, Guzzle

### Layout (`api/app/`)
- `Http/Controllers/` — feature controllers (root) + `Api/` (most v1 endpoints live here)
- `Http/Middleware/` — 15 middleware including role gates and feature gates
- `Models/` — 22 Eloquent models
- `Jobs/`, `Events/`, `Notifications/`, `Mail/`, `Console/`, `Providers/`
- `Services/` — currently empty (extension point)

### Domain model (key models)
- **User** — base auth model (large, `app/Models/User.php`)
- Roles split into: **Admin**, **Agent**, **Client** (enforced by middleware, not separate tables)
- Billing: `Subscription`, `Package`, `Invoice`, `SubscriptionPayment`
- Security: `Domain`, `IncomingAlarm`, `AlarmIncomingLog`
- Messaging: `Chat`, `Message`, `ChatAgentClientOnLine`, `AgentQueue`
- Notifications: `NotificationReminder`, `NotificationReminderGroup`, `NotificationList`, `FirebaseToken`
- Auth flows: `OTP`, `ResetCodePassword`, `RegisteredClients`
- Profile: `UserPreference`, `UserRequestUpdates`, `AccountDetail`

### Middleware aliases (`api/app/Http/Kernel.php:55-72`)
- `auth` — `Authenticate`
- `guest` — `RedirectIfAuthenticated`
- `admin`, `agent`, `client` — role gates (`EnsureUserIs{Admin,Agent,Client}`)
- `check-subscription` — `CheckSubscription` (active sub or trial; returns 403 JSON so the SPA can redirect to `/client/pricing`)
- `trial-guard` — `PreventTrialAbuse` (anti-abuse for `generateTrialInvoice`)
- `check.feature:NAME` — feature gate, wired up but verify the registration before relying on it

### Routes (`api/routes/api.php`)
All API routes are under `v1/`. High-level groups:

**Public (no auth):**
- `POST /v1/login`, `verify-otp`
- `POST /v1/register-client-email`, `register-new-client`, `register_client` (three signup variants — clean up before launch)
- Forgot password: `password/email`, `password/token/check`, `password/reset`
- PayPal callbacks: `GET /v1/paypal/{success,cancel}`

**Authenticated (`auth:api`):**
- Alarms: `fetchAlarms`, `fetchAlarmsForAgents`, `alarm/{id}/respondToAlarm`, `changeStatusByAgent`, `logs`
- Chat: `chat/checkAgentStatus`, `sendMessage`, `assignAgentToClient`, `fetchMessagesByClient`, `clientSwitchLiveOff`
- Notifications & reminders: `notifications/fetch`, `readUnread`, `admin/notifReminders/*`
- User profile / preferences / approve-or-decline profile-update requests
- Admin user management: `admin/registerNewAgent|Client|Admin`, update/delete equivalents, `blockUnblockUser`, `deactivateUser`
- Firebase: `firebase/registerToken`, `unRegisterToken`, `notification`
- Billing: `client/sub/{startTrial,subscribe,upgradeDowngrade,generateInvoice,changePlanInvoice/{id},generateTrialInvoice}` (trial invoice is `trial-guard` protected), `client/invoice/{id}`, `paypal/payment`
- Domains (require `check.subscription`): `client/{fetchDomains,createDomain,showDomain/{id},domains/{id}/verify}`

### Config & ops
- `.env.example` covers app, DB (mysql), mail (smtp/Mailpit dev), Pusher, Redis, AWS placeholders. PayPal/Firebase/Google keys are populated only in real `.env`.
- Queue worker (per `notes.txt`): `nohup php artisan queue:work &`
- Auto-renew via cron: `* * * * * cd /var/www/sentrigate/api && php artisan schedule:run >> /dev/null 2>&1` (path in `notes.txt` references `/var/www/ds-api` — that's stale; update if used).
- `storage/logs/laravel.log` is large (≈17MB) — already in active use.

### Common commands
```bash
cd api
php artisan migrate
php artisan queue:work
php artisan schedule:run     # for renewals
php artisan l5-swagger:generate
./vendor/bin/sail up         # if using Sail
./vendor/bin/pint            # formatter
./vendor/bin/phpunit
```

---

## Frontend — `web/`

### Stack
- **Vue 3.4.21** (Composition API)
- **Vuetify 3.5.2** (NOT Vue 2 / Vuetify 2 despite the doc link the user shared)
- **Vuexy admin template** v9.1.1 (Vue 3 / Vite edition — package name in `package.json` is `dsconnect-admin-template`)
- **Pinia** 2.1.7, **Vue Router** 4.3.0, **vue-i18n** 9.10.1
- **CASL** (`@casl/ability` + `@casl/vue`) for UI ability checks
- **Vite** 5.4.9, package manager **pnpm 8.6.2**
- Real-time: **pusher-js** 8.4.0-rc2; charts: ApexCharts + Chart.js; calendar: FullCalendar; chat UI: vue3-beautiful-chat; PDF: html2pdf.js; maps: Mapbox GL

> **Heads-up — template version mismatch with vendor docs**: the link `demos.pixinvent.com/vuexy-vuejs-admin-template-vue2/...` is for the **Vue 2** Vuexy. This codebase uses the Vue 3 / Vite / Vuetify 3 edition. When consulting Vuexy docs, use the Vue 3 variant (`vuexy-vuejs-admin-template`, no `-vue2` suffix). Composition-api / `<script setup>` / Vuetify 3 component names apply.

### Source layout (`web/src/`)
- `@core/` — template internals: composables, stores (`access.js`, `config.js`, `useChatStore.js`), utils, chart libs
- `@layouts/` — Vuexy layout primitives + layout config store
- `layouts/` — app layouts (default, blank)
- `pages/` — file-based routes via `unplugin-vue-router` (auto-typed in `typed-router.d.ts`)
- `views/` — heavier feature views (not auto-routed; imported into pages)
- `plugins/`
  - `1.router/` — router setup + **route guards** (`guards.js`)
  - `2.pinia.js`
  - `casl/`, `vuetify/`, `iconify/`, `webfontloader.js`
- `components/`, `composables/`, `utils/` (incl. `api.js` — the ofetch client), `assets/`, `navigation/`

### Auth & API client
- Tokens are stored in **cookies**: `userData`, `accessToken`, `isOtp`, `redirect_uri`.
- HTTP client: `src/utils/api.js` — `ofetch` instance with an `onRequest` interceptor that attaches `Authorization: Bearer <accessToken>`.
- Base URL comes from `import.meta.env.VITE_API_BASE_URL` (falls back to `/api`).
- **Watch out:** `plugins/1.router/guards.js` currently has a hard-coded API URL (`https://api-ds.bitemybytes.com/api/v1/...`) used during guard checks. Parameterize this via env before any new env / domain rename.

### Route guards (`plugins/1.router/guards.js`)
- Reads cookies → if no `userData`/`accessToken`, redirect to `/login` (unless route is public).
- OTP flow: if `isOtp` cookie set, force `/checkpoint`.
- Role redirect on auth: admin → `/admin`, agent → `/agent`, client → `/client`.
- Client subscription gate: clients without an active sub get sent to `/client/pricing`.

### Pages by role
- **Auth:** `login`, `register`, `checkpoint` (OTP), `add-user-email`, `register-success`, `reset/forgot-password`, `verify-email`, `not-authorized`, `[...error]`
- **Client (`pages/client/...` + `views/client/...`):** dashboard, alarm-alerts (list + detail + logs + filters), billing-plans (upgrade/downgrade, history), domains (add, verify via DNS/file/meta, status, logs), pay-now (Wise card dialog), preferences, account
- **Admin:** dashboard, clients/agents/admins CRUD, invoices (list/edit/add/preview), reports/[tab] (users, tickets), notifications-reminders, account, preferences
- **Agent:** dashboard, alarm-alerts, account, preferences

### Common commands
```bash
cd web
pnpm install
pnpm dev            # vite, default port 5173
pnpm build          # production build → dist/
pnpm preview        # static preview on :5050
pnpm lint           # eslint --fix
pnpm build:icons    # rebuild iconify bundle
```

Docker: `web/dev.Dockerfile` + `web/docker-compose.dev.yml` for the SPA dev server; `prod.Dockerfile` + `nginx.conf` for the prod image.

---

## Cross-cutting concerns

### Auth flow (typical client signup → use)
1. `register-client-email` / `register-new-client` / `register_client` (three variants currently coexist — pick one before launch)
2. OTP issued → `verify-otp` → cookies `userData`/`accessToken` set, `isOtp` cleared
3. Client lands on `/client`; without a subscription, route guard sends them to `/client/pricing`
4. `startTrial` or `subscribe` → optional PayPal flow (`paypal/payment` → approval URL → `paypal/success` callback)
5. Backend `subscribe` upgrades existing trial / extends active sub / creates new sub, stores payment token, enables auto-renew, generates invoice (`Invoice` + `SubscriptionPayment`)

### Feature gating
- Backend: route-level `check.feature:NAME` middleware
- Frontend: `GET /v1/user/features` → boolean map → toggle UI / show upgrade CTA
- CASL is also wired for view-level ability checks; do **not** use CASL alone as a security boundary — always pair UI gating with backend middleware.

### Real-time
- Pusher app keys come from backend env (`PUSHER_*`) and frontend env (`VITE_PUSHER_*`)
- FCM tokens are registered/unregistered via `firebase/registerToken` / `unRegisterToken`

### Anti-trial abuse
- `PreventTrialAbuse` middleware aliased as `trial-guard` on `generateTrialInvoice`. Logic lives in `app/Http/Middleware/PreventTrialAbuse.php` — read it before changing trial issuance rules.

---

## Conventions & gotchas

- **Routes are versioned (`v1/`)** — keep new endpoints inside the existing `v1` group unless intentionally bumping.
- **403 = redirect** — `CheckSubscription` returns `403` JSON specifically so the SPA can redirect to `/client/pricing`. Don't change the status code without updating the guard.
- **Three auth packages installed** (Passport, Sanctum, JWT). Before touching auth code, check `config/auth.php` to see which guard a given route uses — they aren't interchangeable.
- **Three signup endpoints** exist. They are not duplicates by design; consolidate before public launch.
- **Hard-coded API URL in router guards** (`web/src/plugins/1.router/guards.js`) — replace with `VITE_API_BASE_URL` if you change environments.
- **Vuexy is Vue 3 / Vuetify 3 here** — ignore Vue 2 Vuexy docs; component APIs differ.
- **`api/notes.txt` references `/var/www/ds-api`** — this is the prior project name. The current repo path is `/var/www/sentrigate/api`. Update any cron/systemd you copy from there.
- **No `Services/` layer yet** — controllers tend to hold business logic directly. New non-trivial logic should go into `app/Services/`.

---

## Companion docs

- [`context/project-overview.md`](context/project-overview.md) — product summary, audience, plans, integrations, stack at a glance.
- [`context/backend-history.md`](context/backend-history.md) — topic-organized backend evolution distilled from the pre-monorepo `api/` git history (≈104 commits, 2024-10 → 2026-01). Read this for the *why* behind a backend feature.
- [`context/frontend-history.md`](context/frontend-history.md) — same, for the SPA (`web/`, ≈86 commits).
- [`context/current-feature.md`](context/current-feature.md) — **in-flight feature only** (status + per-feature notes). Cumulative project history lives in the two history docs above; don't duplicate it here. Keep CLAUDE.md focused on stable architecture & conventions.

**IMPORTANT:** Do not add Claude to any commit messages
