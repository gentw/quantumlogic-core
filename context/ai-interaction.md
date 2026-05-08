# AI Interaction

Rules for how AI assistants (Claude Code et al.) should behave in this repo.

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
- Three auth packages are installed (Passport, Sanctum, JWT). Before touching auth, read `api/config/auth.php` to confirm which guard a route uses — they aren't interchangeable.
- API routes are versioned `v1/`. Keep new endpoints inside that group unless intentionally bumping.
- `CheckSubscription` middleware returns **403 with JSON** specifically so the SPA can redirect to `/client/pricing`. Don't change the status code.

## Don't touch unsolicited

- `web/src/@core/` and `web/src/@layouts/` — these are Vuexy template internals. Modify only when explicitly asked.
- Historical migrations under `api/database/migrations/` — write a new migration, don't edit existing ones.
- The three coexisting signup endpoints (`register-client-email`, `register-new-client`, `register_client`) — don't add a fourth; consolidation is a known TODO.

## Keep agents and skills in sync

When a feature change affects how `.claude/agents/` or `.claude/skills/` should operate, update them in the **same** change — don't let them rot.

- New auth flow / new auth package / new signup endpoint → update `.claude/agents/auth-auditor.md`
- New `app/Services/` pattern, new middleware, new module → update `.claude/agents/code-scanner.md` and `.claude/agents/refactor-scanner.md`
- New role, new dashboard area, new SPA route group → update `.claude/agents/ui-reviewer.md`
- New step in the feature workflow, new test runner wired up, new commands → update `.claude/skills/feature/actions/*.md`
- New housekeeping concern (e.g., a new debug helper to scrub) → update `.claude/skills/cleanup/SKILL.md`

If you're not sure whether an agent or skill needs updating, flag it in your response so the user can decide.

## When in doubt

- Spend up to a minute on read-only investigation (grep, read related files) before asking a clarifying question — a specific question beats a generic one.
- For irreversible operations (delete, force-push, mass rename, dropping data), confirm before acting even if previously approved in this session.
- Prefer editing existing files over creating new ones. Don't create `.md` files unless explicitly asked.
