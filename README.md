# QuantumLogic Core

Internal operations platform for the QuantumLogic agency (quantumlogic.at) — manage customers, the services delivered to them, and support tickets, with subscription billing, invoicing and a multi-role admin back office (Basic & Premium plans).

**Status:** Private beta, in active development. Repurposed on 2026-07-25 from *SentriGate*, a web-security product; that capability is switched off but retained — see [`docs/modules/security/README.md`](docs/modules/security/README.md).

---

## Repo layout

Monorepo with two independently deployable apps.

```
quantumlogic-core/
├── api/      # Laravel 10 backend (REST API under /v1/)
└── web/      # Vue 3 SPA (Vuetify 3 / Vuexy admin template)
```

There is no top-level package manager. `cd` into `api/` or `web/` for any command.

---

## Tech stack

**Backend (`api/`):** Laravel 10.10+, PHP 8.1+, MySQL, Eloquent. Auth via Passport (the `api` guard; Sanctum and JWT are installed but unused). Real-time via Pusher; mobile push via FCM; payments via PayPal (`srmklive/paypal`). API docs via L5-Swagger.

**Frontend (`web/`):** Vue 3.4 (Composition API + `<script setup>`), Vuetify 3.5, Vuexy admin template (Vue 3 / Vite edition), Pinia, file-based routing via `unplugin-vue-router`. Vite 5, pnpm 8.6.

---

## Getting started

### Backend (`api/`)

Requires **PHP 8.1+**, **Composer**, **MySQL**.

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
# fill in DB / Pusher / PayPal / FCM / Mail credentials in .env
php artisan migrate
php artisan passport:install
php artisan serve            # http://localhost:8000
```

Run the queue worker and scheduler as long-running processes:

```bash
php artisan queue:work       # background jobs (mail, FCM, PayPal callbacks)
```

The scheduler typically runs as a cron entry:

```cron
* * * * * cd /path/to/quantumlogic-core/api && php artisan schedule:run >> /dev/null 2>&1
```

Sail is also configured if you prefer Docker:

```bash
./vendor/bin/sail up
```

### Frontend (`web/`)

Requires **Node 18+** and **pnpm**.

```bash
cd web
pnpm install
# set VITE_API_BASE_URL in .env to point at your api/ instance
pnpm dev                     # http://localhost:5173
```

Other useful commands:

```bash
pnpm build                   # production build → dist/
pnpm preview                 # static preview on :5050
pnpm lint                    # eslint --fix
pnpm build:icons             # rebuild iconify bundle
```

Docker dev/prod images are available at `web/dev.Dockerfile`, `web/docker-compose.dev.yml`, and `web/prod.Dockerfile`.

---

## Common commands

| Task | Command |
|---|---|
| Backend tests | `cd api && php artisan test` |
| Backend formatting | `cd api && ./vendor/bin/pint` |
| API docs (Swagger) | `cd api && php artisan l5-swagger:generate` |
| Frontend lint | `cd web && pnpm lint` |
| Frontend build | `cd web && pnpm build` |

---

## Documentation

The repo is documented in layers — start at the top and dive deeper as needed.

- **[`CLAUDE.md`](CLAUDE.md)** — architecture, route map, middleware aliases, gotchas, conventions. Start here.
- **[`context/project-overview.md`](context/project-overview.md)** — product summary, audience, plans, integrations.
- **[`context/coding-standards.md`](context/coding-standards.md)** — PHP/Laravel + Vue/Vuetify conventions for this repo.
- **[`context/ai-interaction.md`](context/ai-interaction.md)** — rules for AI assistants working in this repo.
- **[`context/current-feature.md`](context/current-feature.md)** — in-flight feature tracker.
- **[`docs/modules/security/README.md`](docs/modules/security/README.md)** — the dormant security module: what it was, and how to switch it back on. Not loaded into AI context.
- **[`context/backend-history.md`](context/backend-history.md)** & **[`context/frontend-history.md`](context/frontend-history.md)** — topic-organized history (~190 commits) for the *why* behind a feature.

---

## Repository

Private — `github.com/gentw/quantumlogic-core`.
