# Project Overview

## What is SentriGate?

SentriGate is a modern origin protection and optimization platform that protects websites, APIs, and applications from cyber threats, abusive bots, and performance bottlenecks. It sits in front of customer origin servers and provides AI-driven monitoring, traffic filtering, and incident response.

**One-line definition:** a security system for your websites that protects them from harmful attacks, malicious traffic, hackers, and anything that could cause your site to break, go offline, or lose data.

**Key differentiator:** SentriGate operates at the HTTP layer, so it is universally compatible with any backend technology — no framework plugins, no code changes.

## Status

Private beta. Already in use by existing clients while we work toward a fully scalable public launch with integrated billing and tiered plans.

## Audience

- **Clients** — site owners protecting one or more domains. Self-serve signup, trial → paid conversion, manage domains and subscription from the SPA.
- **Agents** — security operators who triage alarms, chat with clients, and respond to incidents.
- **Admins** — internal staff managing users (clients, agents, other admins), invoices, reports, and notification reminders.

### Target users
- **Primary:** developers and DevOps engineers running VPS or dedicated servers; agencies managing multiple client sites; SaaS companies protecting their APIs.
- **Secondary:** small business owners on shared hosting (limited feature set); enterprise IT teams layering extra protection on top of an existing CDN.

## Core Capabilities

| Capability | Description |
|---|---|
| Reverse proxying | Routes traffic through SentriGate before reaching origin |
| Intelligent caching | Reduces backend load by serving cached responses at the edge |
| Form & bot protection | Blocks bots from abusing forms, logins, and APIs |
| Request risk scoring | Every request is scored for threat level in real time |
| JS challenge | Browser validation to stop non-human traffic |
| Edge protection | DDoS, flood, and volumetric attack absorption |
| WAF | Blocks known attack patterns before they reach the app |
| Origin shielding | The real server is never directly exposed |
| Centralized analytics | Full visibility into traffic, attacks, and blocked requests |
| Alarm pipeline | `IncomingAlarm` / `AlarmIncomingLog` with client + agent workflows (respond, change status, log) |
| Real-time response | Pusher-driven chat between client and assigned agent; FCM push for mobile |
| Live agent assignment | `AssignAgentToClient` job routes alarms; free-agent state in `ChatAgentClientOnLine` / `AgentQueue`; auto-detach after 2h inactivity |
| Domain verification | DNS, file, and meta methods before a domain is brought under protection |
| Trial + subscription billing | Trial start, upgrade/downgrade, proration via `changePlanInvoice`, anti-trial-abuse via `PreventTrialAbuse`, auto-renew via scheduler cron |

## Architecture

### Request flow

```
Client Browser
      |
      v
SentriGate Edge          (DDoS / JS challenge / bot filtering / risk scoring)
      |
      v
Edge Layer (reverse proxy)
      ├─ WAF (known threats)
      ├─ Bot / IP filtering
      ├─ Cache / static serving
      └─ SentriGate decision (fast path)
            |
            v
Application Backend (last resort)
            |
            └─ SentriGate Agent (metadata + intelligence)
                      |
                      └─ SentriGate Core (brain)
```

### Components

**SentriGate Edge** — outermost layer, sits in front of all customer infrastructure. Handles DDoS mitigation, JS browser validation, bot filtering, per-request reputation scoring, rate limiting, ASN/geo filtering, challenge orchestration, and distributed IP reputation. Only traffic that passes the Edge reaches origin.

**SentriGate Agent** — lightweight binary on the customer's VPS or dedicated server. Intelligence layer, **not** a traffic handler. It validates headers/cookies/JWTs at the app level, detects request-pattern anomalies, optionally hashes uploads for malware scanning, reports cacheability and slow endpoints, and generates dynamic backend-bypass rules. Framework-agnostic — no dependency on Laravel/WordPress/Django/Rails internals.

**SentriGate Core** — the brain. Does not sit in the traffic path. Aggregates traffic patterns, bot fingerprints, risk events, agent anomaly reports, WAF events, and global IP reputation across **all** protected sites. Produces bypass rules, cache rules, block rules (IP/ASN/UA/geo), emergency lockdown rules, and AI predictions. Threat intel detected on one site protects every other site.

### Design principles
- **The backend is the last resort.** The Edge serves cached/static content, challenges/blocks suspicious traffic, and absorbs attacks. Only clean, verified requests reach the application.
- **Intelligence flows upward.** Agent reports behavior to Core; Core sends rules back to the Edge.
- **Threat intelligence is shared.** Attack patterns detected on one customer's site immediately protect every other site — no manual config.

### Agent ↔ Core communication
- **Push (Agent → Core):** traffic metrics, anomalies, response latency, upload hashes.
- **Pull (Agent ← Core):** updated rules, caching instructions, block lists, emergency directives.
- Transport: HTTP REST (default) or WebSocket/gRPC for real-time rule delivery. Agent caches rules locally so it remains functional during a Core outage.

## Universal Compatibility

The Agent operates at the HTTP layer with no application-level coupling. Supported platforms:

PHP · WordPress · Laravel · Symfony · Node.js · Python (Django, Flask, FastAPI) · Java (Spring Boot, etc.) · Ruby (Rails, etc.) · .NET · custom HTTP services.

## Shared Hosting Mode

On shared hosting (no reverse-proxy install, no kernel firewall access), SentriGate runs a reduced feature set:

- **Available:** form protection and honeypots, bot detection, browser validation, request analysis, challenge signaling.
- **Not available:** reverse proxy, kernel-level firewall, low-level network filtering, full backend bypass.

VPS or dedicated server is required for the full feature set.

## Plans

Two tiers planned at launch: **Basic** and **Premium**. Premium-gated capabilities are enforced both server-side (`check.feature:NAME` middleware) and surfaced in the SPA via `GET /v1/user/features`. CASL is wired for view-level ability checks but is **not** the security boundary — the backend middleware is.

## Competitive Positioning

SentriGate is **not** a Cloudflare replacement — it is a complement. When used alongside Cloudflare or any CDN, SentriGate adds an AI-driven layer of behavioral analysis, advanced reporting, and origin-level protection that generic CDN tools cannot provide.

### SentriGate + Cloudflare

```
Internet
   |
Cloudflare           (DDoS absorption, basic bot filtering, CDN, SSL)
   |
SentriGate Edge      (AI threat detection, behavioral analysis)
   |
SentriGate Agent     (origin-level intelligence)
   |
Application Backend
```

| Feature | Cloudflare only | Cloudflare + SentriGate |
|---|---|---|
| DDoS protection | Yes | Yes + AI monitoring |
| Bot detection | Basic (rule-based) | + behavioral AI |
| Threat alerts | None | Real-time + AI recommendations |
| Reporting | Basic | Attack history, advanced analytics |
| Custom AI rules | None | Predictive AI on traffic patterns |
| API integration | Partial | Full sync of allowlists/blocklists |
| Behavioral analysis | None | Full user-behavior + anomaly detection |

Set up by adjusting DNS / reverse-proxy settings to route traffic through SentriGate, or by connecting via the Cloudflare API for deeper integration. No Cloudflare config changes required.

## Integrations

- **Nginx + ModSecurity** — WAF and request filtering at the edge.
- **System-level agents** — push events into the API.
- **PayPal** (`srmklive/paypal`) — checkout, order creation, success/cancel callbacks.
- **Pusher** — real-time chat & live updates.
- **Firebase Cloud Messaging** — mobile push.
- **Google API client** — installed; verify which features depend on it before changes.
- **Cloudflare** — optional API-level integration (see above).

## Tech stack at a glance

- **Backend:** Laravel 10.10+ on PHP 8.1+, REST API under `/v1/`, MySQL, queue worker via `php artisan queue:work`, scheduler cron for renewals.
- **Frontend:** Vue 3.4 + Vuetify 3.5 + Vuexy admin template v9.1.1, Vite 5, pnpm 8.6, Pinia, file-based routing via `unplugin-vue-router`.
- **Auth:** Passport + Sanctum + JWT installed; cookie-based token storage on the SPA.
- **Repo:** monorepo at `/var/www/sentrigate` split into `api/` (Laravel) and `web/` (SPA), deployed independently.

For codebase-specific details (middleware aliases, full route map, gotchas), see [`../CLAUDE.md`](../CLAUDE.md). For the cumulative project history, see [`./backend-history.md`](./backend-history.md) and [`./frontend-history.md`](./frontend-history.md). For the in-flight feature, see [`./current-feature.md`](./current-feature.md).
