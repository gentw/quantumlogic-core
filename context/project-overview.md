# Project Overview

## What is QuantumLogic Core?

QuantumLogic Core is the internal operations platform for **QuantumLogic**, a web and
software agency based in Austria (quantumlogic.at). It is where the agency manages its
**customers, the services it delivers to them, and the support tickets** that come out of
that work — plus the billing, invoicing and back-office admin around it.

**One-line definition:** the agency's own back office — who our customers are, what we're
building or running for them, what needs doing, and what they've been billed.

## Status

Private beta, in active development. The codebase was repurposed on **2026-07-25** from
*SentriGate*, a web-security product. Billing, auth, admin user management and messaging
are inherited and working; the customers/services/tickets core is the next build.

> The security capability is **switched off**, not deleted. It is out of scope for all
> normal work. See [`../docs/modules/security/README.md`](../docs/modules/security/README.md)
> if and only if you are deliberately reviving it.

## Audience

- **Clients** — the agency's customers. Self-serve login, see their services, raise and
  follow tickets, view invoices, manage their subscription and account.
- **Agents** — agency staff who pick up tickets, talk to clients, and do the work.
- **Admins** — internal staff managing clients, agents, other admins, invoices, reports
  and notification reminders.

## Core Capabilities

| Capability | Status | Description |
|---|---|---|
| Client / agent / admin roles | Working | Middleware-enforced role separation, three dashboards |
| Admin user management | Working | Full CRUD for clients, agents and admins; block/unblock, deactivate |
| Subscription billing | Working | Trial start, upgrade/downgrade, proration via `changePlanInvoice`, anti-trial-abuse, auto-renew via scheduler cron |
| Invoicing | Working | `Invoice` + `SubscriptionPayment`, admin invoice CRUD, PDF preview, client-facing invoice pages |
| PayPal checkout | Working | Order creation, approval redirect, success/cancel callbacks |
| Real-time messaging | Working | Pusher-driven chat between client and assigned agent; free-agent state in `ChatAgentClientOnLine` / `AgentQueue` |
| Push notifications | Working | FCM tokens, notification reminders and reminder groups |
| OTP auth + password reset | Working | OTP on login/signup, reset-code flow |
| Reports | Partial | Admin reports with users and tickets tabs |
| **Tickets** | **Not built** | Dashboard widgets and nav placeholders exist; no model, migration or API yet |
| **Customers** | **Not built** | Currently just the `client`-role `User`; no separate customer/company entity |
| **Services** | **Not built** | What the agency delivers per customer — no model yet |

The three "not built" rows are the point of the platform and the next feature. Everything
above them is inherited infrastructure to build on, not to rewrite.

## Architecture

Two independently deployed apps against one MySQL database:

```
Browser (Vue 3 SPA, web/)
      |
      |  REST, /v1/*, Bearer token from cookie
      v
Laravel API (api/)
      ├─ Passport auth (api guard)
      ├─ Role middleware: admin | agent | client
      ├─ Subscription gate: check-subscription
      ├─ Services: SubscriptionService, TrialService, InvoiceService
      ├─ Queue worker  → jobs, FCM blasts, mail
      └─ Scheduler cron → subscription auto-renewal
      |
      ├── MySQL
      ├── Pusher      (real-time chat)
      ├── FCM         (mobile push)
      ├── PayPal      (checkout)
      └── SMTP        (transactional mail; Mailpit in dev)
```

### Design principles
- **Thin controllers, logic in services.** `app/Services/` holds the non-trivial work;
  `SubscriptionService`, `TrialService` and `InvoiceService` are the reference pattern.
- **The backend is the security boundary.** CASL and hidden nav items are UX, never
  enforcement. Every gated action needs middleware behind it.
- **One database, three role views.** Roles are a column on `User` plus middleware, not
  separate tables. Keep it that way unless there's a strong reason.
- **Modules that aren't part of the product get flagged off, not deleted.** See
  `config/features.php` and `web/src/utils/features.js`.

## Roles and what they can do

| | Client | Agent | Admin |
|---|---|---|---|
| Own dashboard | ✓ | ✓ | ✓ |
| Raise / view own tickets | ✓ (planned) | — | ✓ (planned) |
| Work tickets | — | ✓ (planned) | ✓ (planned) |
| Chat | ✓ with assigned agent | ✓ with assigned clients | — |
| Invoices | own | — | all, editable |
| Subscription / plan changes | own | — | — |
| Manage users | — | — | ✓ clients, agents, admins |
| Reports | — | — | ✓ |
| Notification reminders | receive | receive | ✓ manage |

## Plans

Two tiers: **Basic** and **Premium**. Premium-gated capabilities are enforced server-side
via `check.feature:NAME` middleware and surfaced to the SPA through
`POST /v1/user/features`, which returns the `Package.features` JSON as a boolean map.

## Integrations

- **PayPal** (`srmklive/paypal`) — checkout, order creation, success/cancel callbacks
- **Pusher** — real-time chat and live updates
- **Firebase Cloud Messaging** — mobile push
- **Google API client** — installed; verify which features depend on it before changes
- **SMTP / Mailpit** — transactional email (OTP, welcome, password reset, payment confirmation)

## Tech stack at a glance

- **Backend:** Laravel 10.10+ on PHP 8.1+, REST API under `/v1/`, MySQL, Passport auth, queue worker, scheduler cron for renewals.
- **Frontend:** Vue 3.4 + Vuetify 3.5 + Vuexy admin template v9.1.1, Vite 5, pnpm 8.6, Pinia, file-based routing via `unplugin-vue-router`.
- **Branding:** brand purple `#301068`, lavender accent `#CDBDF0`; theme in `web/src/plugins/vuetify/theme.js`, logo in `web/src/components/AppLogo.vue`.
- **Repo:** monorepo at `/home/gex/projects/quantumlogic-core` split into `api/` and `web/`, deployed independently.

For codebase-specific details (middleware aliases, full route map, gotchas), see
[`../CLAUDE.md`](../CLAUDE.md). For cumulative history, see
[`./backend-history.md`](./backend-history.md) and [`./frontend-history.md`](./frontend-history.md).
For the in-flight feature, see [`./current-feature.md`](./current-feature.md).
