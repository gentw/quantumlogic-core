# AI Interaction

Rules for how AI assistants (Claude Code et al.) should behave in this repo.

## The dormant security module — don't spend tokens on it

This codebase was *SentriGate*, a web-security product, before it became QuantumLogic
Core on 2026-07-25. The security capability (domains, threat alarms, WAF reporting,
protection status) is **switched off but still present on disk**.

- **Don't read it, grep it, audit it, or scan it.** Files under
  `web/src/{pages,views}/client/domains/`, `web/src/{pages,views}/*/alarm-alerts/`,
  `api/app/Http/Controllers/DomainController.php`,
  `api/app/Http/Controllers/Api/AlarmAlertController.php` and the `Domain` /
  `IncomingAlarm` / `AlarmIncomingLog` models are out of scope by default.
- **Don't propose security features**, don't suggest re-enabling it, and don't count it
  when describing what the product does.
- **Don't include it in refactors, cleanups or dependency work** — a "helpful" tidy-up
  of dormant code is churn with no upside.
- If a task genuinely needs it, read **only**
  [`../docs/modules/security/README.md`](../docs/modules/security/README.md) and ask
  before going further.
- Same rule for any future module behind `config('features.*')` / `appFeatures.*`.

## General

- Match the existing code style — read neighboring files before writing new ones.
- Don't restructure folders, rename files, or migrate to new patterns unsolicited.
- When unsure between two valid approaches, ask once with a concrete recommendation rather than guess.
- Be terse. Don't summarize what you just did when the diff already shows it.

## Don't add Claude to commit messages

- No `Co-Authored-By: Claude ...` trailers.
- No `🤖 Generated with Claude Code` lines.
- Commit messages should read as if a human wrote them.

## Stack-specific

- This is **Vue 3 / Vuetify 3 / Vuexy** — when consulting docs, use the Vue 3 Vuexy edition (`vuexy-vuejs-admin-template`), not the Vue 2 one. Component APIs differ.
- The SPA is **JavaScript** (jsconfig, no tsconfig). Don't introduce `.ts` files unless you also wire up TypeScript end-to-end.
- Three auth packages are installed (Passport, Sanctum, JWT) but the `api` guard uses the **passport** driver. Read `api/config/auth.php` before touching auth — they aren't interchangeable.
- API routes are versioned `v1/`. Keep new endpoints inside that group unless intentionally bumping.
- `CheckSubscription` middleware returns **403 with JSON** specifically so the SPA can redirect to `/client/pricing`. Don't change the status code.

## Don't touch unsolicited

- `web/src/@core/` and `web/src/@layouts/` — these are Vuexy template internals. Modify only when explicitly asked.
- Historical migrations under `api/database/migrations/` — write a new migration, don't edit existing ones.
- The four coexisting signup endpoints (`register-client-email`, `register-new-client`, `register_client`, `register_client2`) — don't add a fifth; consolidation is a known TODO.
- The dormant security module (see the top of this file).

## Keep agents and skills in sync

When a feature change affects how `.claude/agents/` or `.claude/skills/` should operate, update them in the **same** change — don't let them rot.

- New auth flow / new auth package / new signup endpoint → update `.claude/agents/auth-auditor.md`
- New `app/Services/` pattern, new middleware, new module → update `.claude/agents/code-scanner.md` and `.claude/agents/refactor-scanner.md`
- New role, new dashboard area, new SPA route group → update `.claude/agents/ui-reviewer.md`
- A module flagged on or off → update every agent that lists modules or routes, and `docs/modules/<name>/README.md`
- New step in the feature workflow, new test runner wired up, new commands → update `.claude/skills/feature/actions/*.md`
- New housekeeping concern (e.g., a new debug helper to scrub) → update `.claude/skills/cleanup/SKILL.md`

If you're not sure whether an agent or skill needs updating, flag it in your response so the user can decide.

## When in doubt

- Spend up to a minute on read-only investigation (grep, read related files) before asking a clarifying question — a specific question beats a generic one.
- For irreversible operations (delete, force-push, mass rename, dropping data), confirm before acting even if previously approved in this session.
- Prefer editing existing files over creating new ones. Don't create `.md` files unless explicitly asked.
