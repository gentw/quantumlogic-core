# Frontend History (`web/`)

Distilled from the standalone Vue/Vuexy repo's git history (≈86 commits, **2024-10-18 → 2026-01-25**) before the monorepo merge on 2026-05-07. Topic-grouped, roughly chronological inside each topic. Use this to understand *why* and *when* something exists; use the code itself for *how* it works today.

> The original `.git` lived under `web/.git` after the merge and has since been removed — this doc is the permanent record.

---

## Foundation

- Initial Vue 3 + Vuetify 3 + Vuexy admin template scaffold (`Setting up FE`).
- Codebase-wide cleanup pass; login view reworked to integrate Passport auth; **`onRequest` interceptor** added to the ofetch client (`web/src/utils/api.js`) that attaches `Authorization: Bearer <accessToken>`.

## Auth UI

- **OTP view (`/checkpoint`)** wired to the backend: validation, interceptor logic, cookie management. The `isOtp` cookie is removed when the tab closes so a stale OTP state doesn't trap the user.
- **Forgot password page** built view-only first, then connected to backend (email → token → reset).
- **Register view** + cross-page linking (login ↔ register ↔ forgot).
- `AddUserEmail` template added for the "attach email after phone-only signup" flow; redirects after login key off user status.
- Logout flow added.
- Several follow-ups: `isFormDone` improvements, login redirect bug fix, debug alert in login (since removed).

## Layout, theme, branding

- Sidebar, header, and main layout adjusted to match Figma; Feather icons imported.
- General appearance polish (two passes labelled "Appearance improvements 1, 2").
- Copyright text fix on small devices; user profile header responsiveness.
- "Web personalization for Sentrigate" — product strings/branding renamed from the prior project. "Removing unnecessary details" cleanup commit followed.

## Role-based routing

- Route guards (`web/src/plugins/1.router/guards.js`) added to **redirect users by role** (admin → `/admin`, agent → `/agent`, client → `/client`) and restrict access for unauthorized roles. Views split by role accordingly.
- `guards adjustments for auto fetch client routes` — fine-tuned which routes auto-fetch vs. wait for the user.
- **Subscription gate:** clients without an active subscription bounce to `/client/pricing` (`5b6bc85 Restrict access for unsubscribed clients`).
- ⚠️ The guards file currently contains a **hard-coded API URL** (`https://api.quantumlogic.at/...`). Parameterize with `VITE_API_BASE_URL` before any environment / domain rename.

## Real-time alarms

- Pusher integrated with a test-modal harness for triggering alarms.
- **Alarm alert dialog** integrated; later split into its own classes/components (one accidental-removal/recovery cycle along the way: `9270429 Re-added AlarmAlertDialog component after accidental removal`).
- **Client side:**
  - `Created alarm alerts index page` + interaction between page/view/module js.
  - **Filters integration with backend** (`AlarmAlerts` list).
  - **"Jam une / nuk jam une"** (was-me / wasn't-me) modal submit linked to backend response endpoint.
  - **AJAX → Pinia store** refactor for fetching alarms.
  - Alarm logs view; notifications made clickable (deep-link into the alarm).
  - Modal accessible *via* the logs/notification path, not just live triggers.
- **Agent side:**
  - `AlarmAlerts for agents` view added.
  - Real-time **indicator** for new incoming alarms.
  - Proper status values in agent-side filters.
- "Listening alternative pusher" — a second Pusher channel feeds notifications from `DS-SYNC-API` (the upstream sync system).
- Z-index housekeeping for stacking with the new modals.

## Chat & notifications UI

- Initial chat UI (`vue3-beautiful-chat`) added on a `chat-feature` branch.
- Notifications feature merged separately on a `notifications-feature` branch.
- **New chat implementation client/agent without sockets** — fallback path that doesn't rely on real-time wiring; later commits layer Pusher on top.
- Notifications **read/unread** state in the SPA, plus polish (clickable, status sync).
- **Client switch live off** — closes the live chat session from the client side.
- V-menu CSS separated for the notifications menu.

## User account & preferences

- `User account and Preferences pages` + components.
- **Backend integration:** preferences linked to `UserPreference` API; account settings linked to update-profile / change-password endpoints.

## Dashboards

- **Client dashboard** + components.
- **Agent dashboard** + sidebar menu items.
- **Admin dashboard** + sidebar menu items.
- Multi-use account & preferences pages reused for agent and admin (single set of components, role-aware data sources).

## Admin — clients management

- Boilerplate clients page (fetch only) → pagination CSS → filters → list view improvements → dedicated client page + subpage active-menu fix → minor improvements pass.

## Admin — agents management

- Necessary agent pages added.
- **Agents list:** more options in `computedMoreList`, minor bug fixes.
- **Block/unblock + deactivate** wired up.
- Update agent from admin side.
- Completed Agents module with security + blocked-user login restriction.

## Admin — sub-admins management

- Reused the agents-management pattern for sub-admins (same abilities, separate page set).

## Admin — reports

- Reports header + tabs (Users, Tickets, …) for splitting reports.
- Reports page completed.

## Admin — notification reminders

- Notifications & reminders for admins.
- Reminder notifications (Part 1) — phased rollout; check the backend `NotificationReminder*` endpoints for the full feature surface.

## Admin — invoices

- Invoices **list**.
- Invoices **add / edit / preview** views (preview is read-only).

## Admin — accept/decline edit requests

- Admins can accept/decline client profile-edit requests, mirroring the same pattern used for agents.

## Domain management (client side)

- `Add frontend views for domain management: creation, verification, and protection overview` (one large commit covering create + verify (DNS / file / meta) + status panel).
- Logs and filters added alongside the alarms work.

## Billing — Plans, invoices, pay-now

- **Plans & Billing** view (upgrade/downgrade UI driven by backend `upgradeDowngrade` + `changePlanInvoice/{id}`).
- **Bank transfer** ability in the pay-now path.
- **`Pay now` view:** loads invoice, detects trial state, checks for an existing invoice before creating a new one.
- **Generate invoice on Upgrade click** in pricing plans (matches backend `generateInvoice` / pricing-plan invoice generation).
- **Trial protection:** device fingerprint added on the SPA side to feed the backend `PreventTrialAbuse` middleware.
- **Registration process integrated** end-to-end (no-OTP first-time path → trial/subscribe flow).

## Operational

- `dev.Dockerfile` + `docker-compose.dev.yml` for the SPA dev server; `prod.Dockerfile` + `nginx.conf` for the prod image.
- Common scripts: `pnpm dev`, `pnpm build`, `pnpm preview` (port 5050), `pnpm lint`, `pnpm build:icons`.
