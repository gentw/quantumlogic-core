# Backend History (`api/`)

Distilled from the standalone Laravel repo's git history (≈104 commits, **2024-10-15 → 2026-01-25**) before the monorepo merge on 2026-05-07. Topic-grouped, roughly chronological inside each topic. Use this to understand *why* and *when* something exists; use the code itself for *how* it works today.

> The original `.git` lived under `api/.git` after the merge and has since been removed — this doc is the permanent record.

---

## Foundation

- Fresh Laravel 10 + Passport scaffold.
- Token-based login auth, then full Passport login/logout/OTP support.
- Role differentiation introduced early: agents/admins bypass OTP and log in by email; clients use OTP. Swagger docs added at the same time.

## Auth & onboarding

- **Password reset:** dedicated mail template, then full Forgot Password API (email → token check → reset) with `exists:users` validation.
- **Client login flexibility:** clients can log in with email *or* phone; auto password reset on first login failure; email is primary OTP receiver; OTP mail template added.
- **Login store:** repeated refactors and small bug fixes (documentation refactor, minor regressions during email/phone work).
- **Client signup:** three endpoints accumulated over time — `registerClientEmail`, `register-new-client`, and `register_client`. Email uniqueness enforced. OTP confirm bug fixed when clients log in via email. **Pending consolidation before public launch.**
- **Registration without OTP** path introduced alongside subscription work (first-time registration integrated with subscribe flow).

## Roles & user management

- Role middleware split into `EnsureUserIsAdmin`, `EnsureUserIsAgent`, `EnsureUserIsClient` and aliased as `admin` / `agent` / `client` in `Http/Kernel.php`.
- Controllers split by role: `ClientController`, `AgentController`, `AdminController` — each progressively gained matching CRUD abilities (register/update/update-password/delete + list).
- `RegisteredClients` model added for pending-approval flow.
- `blockUnblockUser` and `deactivateUser` for admins; blocked users prevented from logging in.
- `showUserDataById`, `findUserByName` for autoselect.
- Mail template for user registration confirmation wired into the registration flow.
- Decline-edit-request ability added to client management; admins can accept/decline profile-update requests.

## Profile & preferences

- `User profile`: show profile data + update password.
- `UserRequestUpdates` flow: user submits a profile update → admin approves/declines (documented endpoint).
- `UserPreference` added with documentation.

## Alarms

- `IncomingAlarm` model + migration added early.
- `AlarmAlertController`: `fetchAlarms` with filters, then `fetchAlarmsForAgents` with client relation.
- `AlarmIncomingLog` added; logs endpoint exposed.
- `respondToAlarm` for clients ("jam une / nuk jam une" — was-me / wasn't-me modal).
- `changeStatusByAgent` for agents.
- **Scheduled commands:**
  - `CheckNoResponseIncomingAlarm` — pushes a notification when an alarm goes unanswered.
  - "Patroled" alarms auto-marked as resolved after 2 hours via command.
- `fetchAlarmsForAgents` and notification fetching iteratively enhanced (relations, time_ago).
- API documentation generated for the alarms endpoints.

## Chat & live agent assignment

- Chat foundation: models, controller, `checkAgentStatus`, `AssignAgentToClient` job, migrations.
- `ChatAgentClientOnLine` model tracks which agent is paired with which client; gained a `live` column for online state.
- `Command for checking free agents` + improvements to `AssignAgentToClient`.
- `sendMessage` reconstructed multiple times; `fetchMessagesByClient` added.
- Notifications integrated with chat: notification on message-send event; status flips to unread when a new message lands in an existing line; read/unread endpoint; `time_ago` on each notification.
- Routes moved inside the `auth` middleware group.
- Real-time delivery via Pusher: real-time chat between client/agent, then upgrade so it only fires when both parties are online.
- **Detach command:** removes the agent from a client line after 2 hours of passive chat.
- `updated_at` on `ChatAgentClientOnLine` is touched every time the agent sends a message (keeps detach window correct).
- `EnsureUserIsAgent` middleware introduced alongside agent-only chat actions.

## Push notifications (FCM)

- Firebase implementation inside Laravel (FCM channel via `laravel-notification-channels/fcm`).
- `firebase/registerToken` / `unRegisterToken` endpoints; `FirebaseToken` model.
- One commit temporarily disabled the Firebase ability — confirm it's re-enabled before relying on it.

## Notification reminders (admin tooling)

- `NotificationReminder` and related assets/functionality.
- Send-notification-reminder command (scheduled).
- Delete/fetch reminder endpoints.

## Domains

- Initial Domains scaffold landed in the same wave as Packages/Subscriptions/Invoices model stubs (not yet wired). The current full domain CRUD + verification (DNS / file / meta) was filled in later — see code in `app/Http/Controllers/Api/` and migrations under `database/migrations/`.

## Billing — packages, subscriptions, invoices

Built up in layers — read in this order if you're tracing the design:

1. **Schema:** `Package`, `Subscription`, `Invoice` models + migrations created together; fields/relations filled in next pass. Migration dates are 2026-01-14.
2. **Feature gating:** `User` model helpers + `GET /v1/user/features` endpoint; `check.feature:NAME` middleware (frontend reads the boolean map for UI, backend enforces the route).
3. **Upgrade/downgrade:** `upgradeDowngrade` endpoint creates a proration invoice for the price difference. Originally gated by role — gate removed later (the endpoint determines applicability itself).
4. **Auto-renew:** `auto_renew` column on subscriptions + a recurring-payment simulation command (run via `php artisan schedule:run` from cron).
5. **Subscription status helper** for backend + frontend consumption.
6. **`PackageTableSeeder`** added so dev environments boot with plans available.
7. **Trial start:** `startTrial` feature + missing migrations.
8. **Subscribe ability** + a fix in `routes/api.php`.
9. **`SubscriptionPayment`** integrated with subscribe + startTrial workflow (token storage).
10. **Subscription `upgradeDowngrade` integrated with first-time registration** (no OTP path).
11. **Trial invoice generation** (`generateTrialInvoice`) and the **`PreventTrialAbuse`** middleware aliased as `trial-guard` — also fixed client IP detection so the abuse check works behind proxies.
12. **`changePlanInvoice/{id}`** — proration flow for plan changes.
13. **Invoice show** endpoint.
14. **Pricing-plan invoice generation** on Upgrade click.

## PayPal

- `srmklive/paypal` v3 integrated. `PayPalController` for order creation + success/cancel callbacks (`/v1/paypal/{success,cancel}`).
- Frontend pay-now flow consumes the approval URL.

## CheckSubscription middleware

- Returns **403 JSON** on inactive subscription so the SPA can detect and redirect to `/client/pricing` (don't change the status code without updating the SPA guard).
- Applied to domain routes and other client-only feature endpoints.

## Branding

- "Sentrigate personalization" + joinlist commit renamed product strings from the prior project name.

## Operational notes

- Queue worker: `nohup php artisan queue:work &`.
- Renewal cron: `* * * * * cd /var/www/sentrigate/api && php artisan schedule:run >> /dev/null 2>&1`.
