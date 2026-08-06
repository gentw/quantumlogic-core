# Security module (dormant)

> **This file is intentionally NOT loaded into AI context.** `CLAUDE.md` does not
> `@`-import it. Read it only when you are deliberately working on this module.

QuantumLogic Core began life as **SentriGate**, an origin-protection product. When
the codebase was repurposed as the agency platform on **2026-07-25**, the security
capability was switched **off, not deleted**.

Nothing was dropped: no tables, no migrations, no models, no pages. Two flags decide
whether the module exists at runtime.

---

## The switch

| App | Flag | Default | Effect when `false` |
|---|---|---|---|
| `api/` | `FEATURE_SECURITY_MODULE` | `false` | Alarm and domain routes are never registered |
| `web/` | `VITE_FEATURE_SECURITY_MODULE` | `false` | Nav items hidden; router guard blocks the pages |

Backend flag is read via `api/config/features.php` → `config('features.security')`.
Frontend flag is read via `web/src/utils/features.js` → `appFeatures.security`.

**Keep both in the same state.** Enabling only the UI produces pages that 404 against
the API; enabling only the API leaves endpoints reachable with no way in from the SPA.

Both flags fail **closed**. The SPA compares against the exact string `true` (Vite env
values are always strings), so `TRUE`, `1`, `yes` or an absent variable all leave the
module off. Write exactly `VITE_FEATURE_SECURITY_MODULE=true`.

## Re-enabling

```bash
# 1. backend
cd api
#   set FEATURE_SECURITY_MODULE=true in .env
php artisan config:clear
php artisan route:list | grep -Ei 'alarm|domain'   # expect 10 routes

# 2. frontend
cd ../web
#   set VITE_FEATURE_SECURITY_MODULE=true in .env
pnpm build     # or restart `pnpm dev` — Vite only reads env at startup
```

Then confirm as a client user: "My Websites" and "Threat Activity" reappear in the
sidebar and `/client/domains` no longer bounces to `/client`.

To switch it back off, reverse both flags and re-run `config:clear` / rebuild.

---

## What it did

- **Domains** — a client registered a domain, verified ownership by DNS record, file
  upload or meta tag, and the domain was then brought "under protection".
- **Incoming alarms** — threat events arrived from the edge, were shown to the client,
  and were triaged by an agent (respond, change status, append a log entry).
- **Protection status** — per-domain dashboard of blocked attacks, inspected requests,
  WAF rules triggered.

## File inventory

Everything below is present and untouched unless noted.

**Backend — models**
- `api/app/Models/Domain.php`
- `api/app/Models/IncomingAlarm.php`
- `api/app/Models/AlarmIncomingLog.php`

**Backend — controllers**
- `api/app/Http/Controllers/DomainController.php` — note: `App\Http\Controllers`, *not* the `Api` sub-namespace
- `api/app/Http/Controllers/Api/AlarmAlertController.php`

**Backend — routes** (`api/routes/api.php`)
- alarm block, inside the `auth:api` group, wrapped in `if (config('features.security'))`
- domain group, wrapped in the same condition, carries `['auth:api', 'check.subscription']`

**Backend — console commands** (still registered; **not** scheduled, so nothing runs on cron)
- `api/app/Console/Commands/CheckNoResponseIncomingAlarms.php`
- `api/app/Console/Commands/MakePatroledAlarmsResolvedAfterTwoHours.php`

`app/Console/Kernel.php` loads the whole `Commands/` directory, so these two are still
registered as invokable artisan commands even with the module off. Only
`subscriptions:check-payments` is on the schedule, so neither executes automatically.
Both build a `Pusher\Pusher` in their **constructor** — see known issue 5 below.

**Backend — migrations** (never reverted; the tables still exist)
- `2024_10_17_001230_create_incoming_alarms_table.php`
- `2024_11_01_232258_add_status_to_incoming_alarms_table.php`
- `2024_11_01_233250_create_alarm_incoming_logs_table.php`
- `2024_11_05_011203_add_base_notified_column_to_incoming_alarms_table.php`
- `2026_01_05_003134_create_domains_table.php`

**Frontend — pages** (still registered by `unplugin-vue-router`; the guard blocks them)
- `web/src/pages/client/domains/{index,add-domain,[id]}.vue`, `management/[tab].vue`
- `web/src/pages/client/alarm-alerts/index.vue`, `details/[id].vue`
- `web/src/pages/agent/alarm-alerts/index.vue`

**Frontend — views**
- `web/src/views/client/domains/**` — list, filters, store, `useDomainList.js`, and `management/{AddDomain,VerifyDomain,ProtectionStatus}.vue` plus `management/protection_status/**`
- `web/src/views/client/alarm-alerts/**` — list, filters, datatable, store, `Details.vue`
- `web/src/views/agent/alarm-alerts/**` — same shape for the agent side

**Frontend — wiring**
- `web/src/utils/features.js` — flag, `SECURITY_ROUTE_PREFIXES`, `isDisabledModuleRoute()`
- `web/src/navigation/vertical/index.js` — nav entries preserved inside `...(appFeatures.security ? [...] : [])`
- `web/src/plugins/1.router/guards.js` — calls `isDisabledModuleRoute(to.name)` early in `beforeEach`

---

## Known issues to fix before trusting it again

1. **`DomainController` was never imported into the route file.** Before the pivot,
   `routes/api.php` referenced `DomainController::class` with no `use` statement, so it
   resolved to `\DomainController` in the global namespace — a class that does not exist.
   **Every domain endpoint returned a 500.** The missing
   `use App\Http\Controllers\DomainController;` was added during the pivot, so the block
   is correct now, but it means the domain flow was never working end-to-end and has
   effectively never been integration-tested.

2. **`sentrigate.txt` is still the file-verification filename.**
   `DomainController.php:146` fetches `https://{domain}/sentrigate.txt`. This was left
   deliberately: any domain already verified by that method has that file on its server,
   so renaming it would silently break them. If you rebrand it, migrate existing rows
   and support both filenames for a transition period.

3. **Feature gating depends on `Package.features` JSON.** The `check.feature:NAME`
   middleware (`api/app/Http/Middleware/CheckFeature.php`) calls `User::hasFeature()`,
   which reads `$subscription->package->features[$feature]`. If you gate security
   capabilities per plan, those JSON keys must exist on the `packages` rows.

4. **Copy was rebranded.** The dormant views now say "QuantumLogic", not "SentriGate".
   If this module is revived as a separately-branded product, the copy needs another pass.

5. **Pusher is constructed in console-command constructors.** `CheckNoResponseIncomingAlarms`
   and `MakePatroledAlarmsResolvedAfterTwoHours` (and the *active*
   `SendNotificationReminders`) do `new Pusher(...)` in `__construct()`. Because
   `Console\Kernel` instantiates every command in `Commands/` to read its signature, an
   invalid or empty `PUSHER_*` config makes **every** `php artisan` invocation fail — not
   just these commands. This is pre-existing and affects active code too; it was left
   alone during the pivot. Move the client into `handle()` (or inject it lazily) when you
   next touch these files.

## Deliberately left enabled

**Chat** (`ChatController`, Pusher, `ChatAgentClientOnLine`, `AgentQueue`,
`AssignAgentToClient`) was *not* switched off. It is generic client↔staff messaging and
is the natural transport for ticket support in the agency product. It is not gated by
the security flag. Note that agent auto-assignment was originally driven by alarm
fanout, so with alarms off, assignment now needs to be triggered by whatever creates
tickets.
