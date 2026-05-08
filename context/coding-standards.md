# Coding Standards

## PHP (`api/`)

- PHP 8.1+; use typed properties, parameter types, return types — `mixed` only when truly polymorphic
- `declare(strict_types=1);` not enforced repo-wide; if you add it to a file, do so consistently with sibling files in that directory
- Follow PSR-12; format with `./vendor/bin/pint` before committing
- Avoid `mixed`/untyped function returns — use union types, generics in DocBlocks, or DTOs/value objects
- Constructor property promotion + `readonly` for value objects

## JavaScript (`web/`)

- The SPA is **JavaScript** (`jsconfig.json`, no `tsconfig.json`). Don't introduce `.ts` files unless you also wire up TypeScript end-to-end.
- Document non-trivial functions and Vue props with JSDoc — `@param`, `@returns`, `@type` — so editor IntelliSense works without TS
- `const` by default, `let` when reassigned, never `var`
- `defineProps` / `defineEmits` with explicit shapes (object syntax, not array shorthand) — Vue runtime-validates them and that's our type safety

## Vue 3

- Composition API with `<script setup>` — no Options API in new files
- Single-file components: `<template>`, `<script setup>`, `<style scoped>`
- One job per component — extract sub-components when render logic exceeds ~150 lines
- Reusable logic → composables under `web/src/composables/` (`useX.js`)
- Shared state → Pinia stores (use `defineStore` with the setup syntax to match the rest of the codebase)
- Don't fetch data inside leaf components — use a composable or a Pinia action
- Routes are file-based via `unplugin-vue-router`; new pages go under `web/src/pages/<role>/<page>.vue` and are auto-registered (check `typed-router.d.ts` after adding)

## Laravel 10

- Routes versioned under `v1/` in `api/routes/api.php` — keep new endpoints inside that group unless intentionally bumping
- **Controllers stay thin**: validate input, delegate to a Service, return a Resource. Business logic does not live in controllers.
- New non-trivial logic → `app/Services/<Name>Service.php` (the `Services/` directory exists but is empty — populate it instead of bloating controllers)
- Validation → `app/Http/Requests/<Name>Request.php` (FormRequest), not inline `$request->validate([...])` for anything non-trivial
- Long-running work (PayPal callbacks, FCM blasts, alarm fanout) → Jobs in `app/Jobs/`, dispatched onto the queue (`nohup php artisan queue:work &`)
- Authorization → middleware (`admin`, `agent`, `client`, `check.subscription`) and Policies, never duplicated controller checks
- API responses → `app/Http/Resources/` (Eloquent API Resources) for consistent shape; never return raw model arrays
- Three coexisting signup endpoints (`register-client-email`, `register-new-client`, `register_client`) — do not add a fourth; consolidate before adding new flows
- Run `./vendor/bin/pint` and `php artisan test` before pushing

## Vuetify 3 / Vuexy

**CRITICAL**: This is **Vue 3 + Vuetify 3 + Vuexy** (Vue 3 edition). Ignore Vue 2 Vuexy docs — component APIs differ.

- Theme is configured in `web/src/plugins/vuetify/theme.js` (and adjacent files under `plugins/vuetify/`). Don't inline theme overrides per-component.
- Use Vuetify components (`<v-btn>`, `<v-text-field>`, `<v-data-table>`, `<v-snackbar>`) — don't mix raw HTML controls with Vuetify ones in the same view
- Use Vuetify spacing/layout utilities (`pa-`, `ma-`, `gap-`, `d-flex`) over hand-written CSS
- Light **and** dark theme parity required — Vuexy ships both; new pages must be checked in both
- Iconify via `@iconify/vue`; rebuild bundle with `pnpm build:icons` if you reference a new icon set

## File Organization

Backend (`api/app/`):
- `Http/Controllers/Api/<Name>Controller.php` — v1 endpoints (most live here)
- `Http/Requests/<Name>Request.php` — FormRequest validation
- `Http/Resources/<Name>Resource.php` — response shaping
- `Http/Middleware/` — role gates, feature gates, subscription gates
- `Models/<Name>.php` — Eloquent models
- `Services/<Name>Service.php` — business logic (currently empty — start populating)
- `Jobs/<Name>Job.php`, `Events/`, `Notifications/`, `Mail/`

Frontend (`web/src/`):
- `pages/<role>/<page>.vue` — auto-registered routes
- `views/<feature>/<View>.vue` — heavier views imported into pages
- `components/<Feature><Name>.vue` — reusable components
- `composables/useX.js` — reusable composition logic
- `stores/<feature>.js` — Pinia stores
- `utils/<name>.js` — pure helpers
- Layouts, navigation, plugins under their existing folders — don't restructure those

## Naming

PHP / Laravel:
- Classes: `StudlyCase` (`AlarmService`, `RegisterClientRequest`)
- Methods: `camelCase` (`fetchActiveSubscription`)
- DB columns: `snake_case` (`created_at`, `subscription_id`)
- Routes: `kebab-case` (`/v1/client/fetch-domains`)
- Constants: `SCREAMING_SNAKE_CASE`

Vue / JS:
- Components: `PascalCase` (`AlarmCard.vue`, `DomainVerifyDialog.vue`)
- Composables: `camelCase` prefixed `use` (`useAlarms.js`, `useSubscription.js`)
- Pinia stores: `useXStore` (`useAuthStore`, `useChatStore`)
- Props/data/methods: `camelCase`
- Pages (file-based router): `kebab-case` filenames map to URL segments

## Styling

- Vuetify components and utilities for all UI; raw CSS only when Vuetify can't express it
- No inline `style=` attributes — use `<style scoped>` or Vuetify utility classes
- Light + dark mode must both work — test both via the Vuexy theme toggle

## Database

- Use **Eloquent** for all queries — only drop to `DB::raw` / `whereRaw` / `selectRaw` when Eloquent genuinely can't express it, and never with concatenated user input
- Schema changes → new migration in `api/database/migrations/`. Don't edit historical migrations.
- Run `php artisan migrate` locally; verify the migration is reversible (`down()` exists and is correct)
- Add indexes when introducing a column you'll query/filter/join on
- Eager-load relationships (`with()` / `load()`) — never let an N+1 ship
- Use model scopes for repeated `where(...)` chains
- Foreign keys with explicit `onDelete()` behavior

## Data Fetching (SPA → API)

- All HTTP goes through `web/src/utils/api.js` (the `ofetch` instance) — don't `fetch()` directly
- Auth: bearer token attached automatically from the `accessToken` cookie. Don't read tokens directly in components.
- Validate inputs **on both sides**: Vuetify `:rules` for fast UX feedback, FormRequest in Laravel for the source-of-truth check
- Backend: return Eloquent API Resources, not raw `->toArray()` model dumps
- Frontend: keep API calls in Pinia store actions or composables, not inline in components

## Error Handling

- Laravel: use exceptions; let `app/Exceptions/Handler.php` shape the JSON response. Custom exceptions for domain errors (e.g., `TrialAbuseException`).
- API responses on error: `{ message, errors? }` (Laravel's default validation shape) — don't invent per-controller error shapes
- `CheckSubscription` returns **403 with JSON** specifically so the SPA can redirect to `/client/pricing` — don't change the status code without updating the guard
- Vue: surface API errors via Vuexy snackbar/toast; don't swallow them silently
- Wrap external integrations (Pusher, PayPal, FCM, Google) in try/catch and log via `Log::error()` — they are common silent-failure sources
- Never expose stack traces in production (`APP_DEBUG=false`)

## Code Quality

- No commented-out code unless paired with a TODO and a date/owner
- No unused `import` (Vue) or `use` (PHP) statements
- Keep functions under ~50 lines; extract once they grow past that
- No `dd()` / `dump()` / `var_dump()` / `console.log` left in committed code
- No hard-coded URLs or credentials — use `.env` (api) or `import.meta.env.VITE_*` (web). The hard-coded API URL in `web/src/plugins/1.router/guards.js` is a known issue; if you touch that file, fix it.
