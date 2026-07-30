# QuantumLogic Core

Internal platform for the QuantumLogic agency (quantumlogic.at) — manage **customers,
services and tickets**, with order-based billing & payments (Stripe, PayPal, SEPA),
Austrian-compliant invoicing and a multi-role admin back office.

**Status:** private beta, in active development. Some flows below are partially
implemented — verify against current code before assuming behavior.

> **Dormant module:** this codebase started as *SentriGate*, a web-security product. The
> security capability (domains, threat alarms, WAF reporting) is switched **off** and is
> not part of the product. Do not explore it, audit it, or factor it into suggestions.
> If a task genuinely requires it, read [`docs/modules/security/README.md`](docs/modules/security/README.md)
> — and nothing else about it — then ask before proceeding.

---

## Context Files

Read the following to get the full context of the project:

- @context/project-overview.md
- @context/coding-standards.md
- @context/ai-interaction.md
- @context/current-feature.md

For historical *why* behind a feature, see also: [`context/backend-history.md`](context/backend-history.md), [`context/frontend-history.md`](context/frontend-history.md).

---

## Repo layout

Monorepo at `/home/gex/projects/quantumlogic-core` with two independent apps:

```
quantumlogic-core/
├── api/      # Laravel 10 backend (REST API, v1)
├── web/      # Vue 3 SPA (Vuexy admin template)
├── context/  # AI context files (auto-loaded, see above)
├── docs/     # long-form docs, NOT auto-loaded
└── history/  # per-feature change manifests
```

The two apps are deployed and developed separately. There is no top-level package
manager — `cd` into `api/` or `web/` for any command.

Short lookup notes (columns, formulas, invariants, commands) live in
[`docs/explain/`](docs/explain/README.md) — read one file instead of re-deriving.

---

## Backend — `api/`

### Stack
- **Laravel** 10.10+, **PHP** 8.1+
- Auth: the `api` guard uses the **passport** driver (`config/auth.php`). Sanctum 3.3 and `tymon/jwt-auth` 2.1 are also installed but the API routes do not use them — they aren't interchangeable.
- Payments: **srmklive/paypal** 3.0
- Real-time: **Pusher** PHP server 7.2, **FCM** via `laravel-notification-channels/fcm`
- API docs: **L5-Swagger** 8.6
- Other: Google API client, Guzzle

### Layout (`api/app/`)
- `Http/Controllers/` — feature controllers (root) + `Api/` (most v1 endpoints live here)
- `Http/Middleware/` — role gates, subscription gate (flag-off pass-through), feature gate
- `Models/` — Eloquent models
- `Services/` — Billing & Payments: `ServiceCatalogueService`, `ServiceOrderService`, `InvoiceNumberService`, `BillingInvoiceService`, `TaxService`, `PaymentService`, `StripeGateway`, `PayPalGateway`, `BankTransferService`, `RecurringBillingService`, `ClientAccountService`, `BillingNotifier`; retired module: `SubscriptionService`, `TrialService`, `InvoiceService`
- `Support/` — `Money` (billing math in integer cents)
- `Enums/` — `InvoiceType`, `InvoiceStatus`, `ServiceOrderStatus`, `PaymentProvider`, `PaymentStatus`, `PaymentProofStatus`, `RecurringPlanState`, `BillingType`, `SubscriptionState` (retired)
- `Jobs/`, `Events/`, `Notifications/`, `Mail/`, `Console/`, `Providers/`

### Data model (active)
- **User** — base auth model (large, `app/Models/User.php`; billing profile: `country_code`, `company_name`, `vat_id`, `origin`)
- Roles split into **Admin**, **Agent**, **Client** (enforced by middleware, not separate tables)
- Billing & Payments: `Service` (catalogue) → `ServiceOrder` + `ServiceOrderItem` → `Invoice` + `InvoiceItem` → `Payment` (+ `PaymentProof`, `PaymentMethod`, `RecurringPlan`, `InvoiceReminder`, `InvoiceActivity` append-only audit trail, `InvoiceSequence` gapless numbering)
- Retired plan-tier module (flag `FEATURE_SUBSCRIPTION_PLANS`): `Subscription`, `Package`, `SubscriptionPayment` — see `docs/modules/subscriptions/README.md`
- Messaging: `Chat`, `Message`, `ChatAgentClientOnLine`, `AgentQueue`
- Notifications: `NotificationReminder`, `NotificationReminderGroup`, `NotificationList`, `FirebaseToken`
- Auth flows: `Otp`, `ResetCodePassword`, `RegisteredClients`
- Profile: `UserPreference`, `UserRequestUpdates`, `AccountDetail`

Invoices are **immutable once issued** (model-level lock; corrections via credit note only),
numbered gaplessly at issue time, soft-deleted only (7-year retention). Payment application
is idempotent per key — webhooks are the source of truth for Stripe and PayPal.

Customers/tickets are **not yet modelled** — the SPA has ticket dashboard
widgets and nav placeholders, but no backend behind them. That is the next feature.

### Middleware aliases (`api/app/Http/Kernel.php`)
- `auth` — `Authenticate`
- `guest` — `RedirectIfAuthenticated`
- `admin`, `agent`, `client` — role gates (`EnsureUserIs{Admin,Agent,Client}`)
- `check-subscription` — `CheckSubscription` (active sub or trial; returns 403 JSON so the SPA can redirect to `/client/pricing`)
- `trial-guard` — `PreventTrialAbuse` (anti-abuse for `generateTrialInvoice`)
- `check.feature:NAME` — per-plan feature gate, reads `Package.features` JSON

### Routes (`api/routes/api.php`)
All API routes are under `v1/`.

**Public (no auth):**
- `POST /v1/login`, `verify-otp`, `token/refresh`
- `POST /v1/register-client-email`, `register-new-client`, `register_client`, `register_client2` (**four** signup variants — consolidate before launch)
- Forgot password: `password/email`, `password/token/check`, `password/reset`
- PayPal callbacks: `GET /v1/paypal/{success,cancel}`

**Public Billing & Payments (no auth by design):**
- Webhooks: `POST /v1/webhooks/{stripe,paypal}` — provider signature is the authentication
- Guest checkout (throttled): `GET /v1/public/services`, `POST /v1/public/checkout/{quote,start}`
- Public pay links (throttled, expiring 64-char token): `GET /v1/public/invoices/{token}`, `POST /v1/public/invoices/{token}/pay`
- PayPal browser returns: `GET /v1/paypal/billing/{success,cancel}` (UX only; capture is idempotent with the webhook)

**Authenticated (`auth:api`):**
- Chat: `chat/checkAgentStatus`, `sendMessage`, `assignAgentToClient`, `fetchMessagesByClient`, `clientSwitchLiveOff`
- Notifications & reminders: `notifications/fetch`, `readUnread`, `admin/notifReminders/*`
- User profile / preferences / approve-or-decline profile-update requests
- Admin user management: `admin/registerNew{Agent,Client,Admin}`, update/delete equivalents, `blockUnblockUser`, `deactivateUser`
- Firebase: `firebase/registerToken`, `unRegisterToken`, `notification`
- Client billing (`client` middleware): `client/billing/{summary,invoices,invoices/{id}}`, `client/services`, `client/invoices/{id}/{stripe/intent,paypal/create,bank-details,payment-proof,print}`, `client/stripe/setup-intent`, `client/payments/{id}/status`, `client/payment-methods*`, `client/recurring-plans/{id}/{pause,cancel}`
- Admin billing (`admin` middleware): `admin/billing/invoices*` (CRUD, issue, cancel, credit-note, payments, reminders, public-link, print), `admin/billing/{payments,orders*,services*,recurring-plans*}`, `admin/billing/clients/{id}/{orders,summary}`, `admin/payment-proofs*` (reconciliation)
- Retired module (only when `FEATURE_SUBSCRIPTION_PLANS` is on): `client/sub/*`, legacy `client/invoice/{id}`, `paypal/payment`
- Feature map: `POST /v1/user/features`

Route groups wrapped in `if (config('features.security'))` or
`if (config('features.subscription_plans'))` belong to switched-off modules — ignore them.

### Config & ops
- `.env.example` covers app, DB (mysql), mail (smtp/Mailpit dev), Pusher, Redis, AWS placeholders. PayPal/Firebase/Google keys are populated only in real `.env`.
- `FEATURE_SECURITY_MODULE=false` keeps the dormant module's routes unregistered.
- `FEATURE_SUBSCRIPTION_PLANS=false` keeps the retired plan-tier module off (`docs/modules/subscriptions/README.md`).
- Billing env: `BILLING_*` (number prefix, NET-14 terms, VAT, VIES toggle), `STRIPE_*`, `PAYPAL_WEBHOOK_ID`, `COMPANY_*` (legal footer + SEPA beneficiary — populate before go-live).
- Billing cron: `billing:charge-recurring` (03:00) and `billing:send-reminders` (09:00) ride the same scheduler cron.
- `FRONTEND_URL` is the SPA origin used to build links in emails (`config('app.frontend_url')`) and PayPal return URLs. Never hard-code the SPA host.
- Queue worker: `nohup php artisan queue:work &`
- Auto-renew via cron: `* * * * * cd <repo>/api && php artisan schedule:run >> /dev/null 2>&1`

### Common commands
```bash
cd api
php artisan migrate
php artisan queue:work
php artisan schedule:run     # for renewals
php artisan l5-swagger:generate
./vendor/bin/pint            # formatter
php artisan test
```

---

## Frontend — `web/`

### Stack
- **Vue 3.4.21** (Composition API)
- **Vuetify 3.5.2** (NOT Vue 2 / Vuetify 2)
- **Vuexy admin template** v9.1.1 (Vue 3 / Vite edition — package name in `package.json` is `dsconnect-admin-template`)
- **Pinia** 2.1.7, **Vue Router** 4.3.0, **vue-i18n** 9.10.1
- **CASL** (`@casl/ability` + `@casl/vue`) for UI ability checks
- **Vite** 5.4.9, package manager **pnpm 8.6.2**
- Real-time: **pusher-js**; charts: ApexCharts + Chart.js; calendar: FullCalendar; chat UI: vue3-beautiful-chat; PDF: html2pdf.js; maps: Mapbox GL

> **Heads-up — vendor docs**: use the **Vue 3** Vuexy docs (`vuexy-vuejs-admin-template`,
> no `-vue2` suffix). Composition-api / `<script setup>` / Vuetify 3 component names apply.

### Source layout (`web/src/`)
- `@core/`, `@layouts/` — Vuexy template internals (don't modify unsolicited)
- `layouts/` — app layouts (default, blank)
- `pages/` — file-based routes via `unplugin-vue-router` (auto-typed in `typed-router.d.ts`)
- `views/` — heavier feature views (imported into pages)
- `plugins/` — `1.router/` (setup + `guards.js`), `2.pinia.js`, `casl/`, `vuetify/`, `iconify/`
- `components/`, `composables/`, `utils/`, `assets/`, `navigation/`

Note: `src/utils/` and `src/composables/` are in the `unplugin-auto-import` dirs list, so
their named exports are available globally without an import.

### Branding
- Theme: `web/src/plugins/vuetify/theme.js`. Brand purple `#301068` is the light-mode primary; dark mode uses the lightened `#7C5CD6`, because `#301068` is unreadable against dark surfaces. Lavender `#CDBDF0` is exposed as `primary-accent`.
- Logo: `web/src/components/AppLogo.vue` swaps the brand mark between colour and white variants by active theme. Wired in via `web/themeConfig.js`.
- `themeConfig.app.title` is **not** decorative — it renders as the company name on invoices (`views/admin/invoices/InvoiceEditable.vue`, `pages/admin/invoices/preview/[id].vue`) and namespaces client-side storage keys (`@layouts/stores/config.js`). Changing it resets stored user preferences.

### Auth & API client
- Tokens are stored in **cookies**: `userData`, `accessToken`, `isOtp`, `redirect_uri`.
- HTTP client: `src/utils/api.js` — `ofetch` instance with an `onRequest` interceptor that attaches `Authorization: Bearer <accessToken>`.
- Base URL comes from `import.meta.env.VITE_API_BASE_URL` (falls back to `/api`). Keep request paths relative (`/v1/...`).

### Route guards (`plugins/1.router/guards.js`)
- Blocks routes belonging to switched-off modules (`isDisabledModuleRoute` from `utils/features.js`).
- Reads cookies → if no `userData`/`accessToken`, redirect to `/login` (unless route is public).
- OTP flow: if `isOtp` cookie set, force `/checkpoint`.
- Role-based path enforcement: admin → `/admin`, agent → `/agent`, client → `/client`.
- Client subscription gate: clients without an active sub get sent to `/client/pricing`. Pricing and invoice pages are exempt.
- Exactly one `next()` per navigation — keep it that way.

### Pages by role
- **Auth:** `login`, `register`, `checkpoint` (OTP), `add-user-email`, `register-success`, `reset/forgot-password`, `reset/password/[token]`, `reset/verify-email`, `not-authorized`, `[...error]`
- **Public (blank layout):** `order` (guest checkout), `order/success`, `pay/[token]` (public pay link)
- **Client:** dashboard, tickets (placeholder → `second-page`), billing (list, invoice detail, checkout, pay, payment-methods), services (My Services), preferences, account; retired: pricing, plans-billing, invoice/change-plan
- **Admin:** dashboard, clients/agents/admins CRUD, invoices (list/detail/add stepper), payments (ledger + reconciliation), services (catalogue), orders, reports/[tab] (users, tickets), notifications-reminders, account, preferences
- **Agent:** dashboard, account, preferences

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

Docker: `web/dev.Dockerfile` + `web/docker-compose.dev.yml` for the SPA dev server;
`prod.Dockerfile` + `nginx.conf` for the prod image.

---

## Cross-cutting concerns

### Auth flow (typical client signup → use)
1. One of the signup endpoints (four coexist — pick one before launch)
2. OTP issued → `verify-otp` → cookies `userData`/`accessToken` set, `isOtp` cleared
3. Client lands on `/client`; without a subscription, the route guard sends them to `/client/pricing`
4. `startTrial` or `subscribe` → optional PayPal flow (`paypal/payment` → approval URL → `paypal/success` callback)
5. Backend `subscribe` upgrades existing trial / extends active sub / creates new sub, stores payment token, enables auto-renew, generates invoice (`Invoice` + `SubscriptionPayment`)

### Module flags vs per-plan gating
Two different mechanisms — don't confuse them:
- **Module flags** — `config('features.*')` (api) / `appFeatures.*` (web). Whole modules that ship but are switched off. Currently just `security`. Both sides must agree.
- **Per-plan gating** — `check.feature:NAME` middleware + `POST /v1/user/features` → boolean map from `Package.features` JSON. Toggles UI and blocks endpoints by subscription tier.

CASL is wired for view-level ability checks but is **not** a security boundary — always
pair UI gating with backend middleware.

### Real-time
- Pusher app keys come from backend env (`PUSHER_*`) and frontend env (`VITE_PUSHER_*`)
- FCM tokens are registered/unregistered via `firebase/registerToken` / `unRegisterToken`

### Anti-trial abuse
`PreventTrialAbuse` (alias `trial-guard`) guards trial issuance. Logic lives in
`app/Http/Middleware/PreventTrialAbuse.php` and `app/Services/TrialService.php` — read
them before changing trial rules.

---

## Conventions & gotchas

- **Routes are versioned (`v1/`)** — keep new endpoints inside the existing `v1` group unless intentionally bumping.
- **403 = redirect** — `CheckSubscription` returns `403` JSON specifically so the SPA can redirect to `/client/pricing`. Don't change the status code without updating the guard.
- **Three auth packages installed, one in use.** The `api` guard is Passport. Check `config/auth.php` before touching auth code.
- **Four signup endpoints** exist (`register-client-email`, `register-new-client`, `register_client`, `register_client2`). Don't add a fifth; consolidate before public launch.
- **Vuexy is Vue 3 / Vuetify 3 here** — ignore Vue 2 Vuexy docs; component APIs differ.
- **Controllers still hold business logic** in older code. New non-trivial logic goes in `app/Services/` — three services exist to copy the pattern from.
- **Light + dark theme parity is required.** Check both via the Vuexy theme toggle.
- **The SPA is JavaScript**, not TypeScript. Don't add `.ts` files.

---

**IMPORTANT:** Do not add Claude to any commit messages
