# Project Overview

## What is SentriGate?

SentriGate is an intelligent web & server security platform that protects websites from cyber threats, malicious traffic, and real-time abuse. It sits in front of customer origin servers and provides AI-driven monitoring, traffic filtering, and incident response.

## Status

Private beta. Already in use by existing clients while we work toward a fully scalable public launch with integrated billing and tiered plans.

## Audience

- **Clients** — site owners protecting one or more domains. Self-serve signup, trial → paid conversion, manage domains and subscription from the SPA.
- **Agents** — security operators who triage alarms, chat with clients, and respond to incidents.
- **Admins** — internal staff managing users (clients, agents, other admins), invoices, reports, and notification reminders.

## Core capabilities

- **AI-driven security monitoring & threat detection** — alarm pipeline (`IncomingAlarm`, `AlarmIncomingLog`) with client + agent workflows (respond, change status, log).
- **Web Application Firewall & traffic filtering** — Nginx + ModSecurity in front; system-level agents feed events into the alarm pipeline.
- **Origin server protection** — domain ownership verification (DNS, file, meta methods) before a domain is brought under protection.
- **Domain management** — clients add domains, verify ownership, view protection status and logs from `views/client/domains/`.
- **Real-time alarm response** — Pusher-driven chat between client and assigned agent (`vue3-beautiful-chat`); FCM push for mobile.
- **Live agent assignment** — incoming alarms route to an available agent via `AssignAgentToClient` job; free-agent state tracked in `ChatAgentClientOnLine`/`AgentQueue`; agents auto-detach from a client line after 2 hours of inactivity (scheduled command).
- **Detailed security stats & logging** — admin reports (`pages/admin/reports/[tab]`), per-domain logs, ticket statistics widgets.
- **Trial + subscription billing** — trial start, upgrade/downgrade, proration via `changePlanInvoice`, anti-trial-abuse via `PreventTrialAbuse` middleware, auto-renew on a Laravel scheduler cron.

## Plans

Two tiers planned at launch: **Basic** and **Premium**. Premium-gated capabilities are enforced both server-side (`check.feature:NAME` middleware) and surfaced in the SPA via `GET /v1/user/features`. CASL is wired for view-level ability checks but is **not** the security boundary — the backend middleware is.

## Integrations

- **Nginx + ModSecurity** — WAF and request filtering at the edge
- **System-level agents** — push events into the API
- **PayPal** (`srmklive/paypal`) — checkout, order creation, success/cancel callbacks
- **Pusher** — real-time chat & live updates
- **Firebase Cloud Messaging** — mobile push
- **Google API client** — installed; verify which features depend on it before changes

## Tech stack at a glance

- **Backend:** Laravel 10.10+ on PHP 8.1+, REST API under `/v1/`, MySQL, queue worker via `php artisan queue:work`, scheduler cron for renewals.
- **Frontend:** Vue 3.4 + Vuetify 3.5 + Vuexy admin template v9.1.1, Vite 5, pnpm 8.6, Pinia, file-based routing via `unplugin-vue-router`.
- **Auth:** Passport + Sanctum + JWT installed; cookie-based token storage on the SPA.
- **Repo:** monorepo at `/var/www/sentrigate` split into `api/` (Laravel) and `web/` (SPA), deployed independently.

For architecture details, middleware aliases, the full route map, and gotchas, see [`../CLAUDE.md`](../CLAUDE.md). For the cumulative project history, see [`./backend-history.md`](./backend-history.md) and [`./frontend-history.md`](./frontend-history.md). For the in-flight feature, see [`./current-feature.md`](./current-feature.md).
