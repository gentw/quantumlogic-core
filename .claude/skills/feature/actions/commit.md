# Commit Action

How every commit in a feature branch is made. `start` calls this after each phase — it is
not a separate thing the user has to ask for.

**One commit per phase, minimum.** Not one commit per feature, not one per file.

## When to commit

- As soon as a phase from the spec's `## Changes Required` is finished and its gates pass
- Never carry a finished phase into the next one uncommitted, and never batch two finished
  phases into a single commit
- Split a phase further when it spans separable units — one per payment rail, one per UI
  area, one per group of services. Finer than a phase is fine; coarser is not
- If a phase can't stand alone (needs a later phase to actually run), commit it anyway and
  say so in the body — don't hold it back
- Don't ask for permission between phases. Commit, report the subject, keep building

## Gates before each commit

Run what applies to the files the phase touched; skip only what genuinely doesn't apply.

- `cd api && ./vendor/bin/pint` then `php artisan test`
- `cd web && pnpm lint`
- No `dd()` / `dump()` / `var_dump()` / `console.log`, no commented-out code
  (`context/coding-standards.md`)
- Stage the phase's files explicitly. `git add -A` sweeps up unrelated work in progress —
  check `git status` first and leave anything that isn't this phase alone

If a gate fails, fix it before committing. If something is genuinely unfixable here, commit
with it named under `Verified:` — never silently.

## Message format

```
<type>(<scope>): <what the phase delivers, imperative, ≤72 chars>

<Why this phase exists — 1-3 sentences. The problem it closes or the constraint it
satisfies, not a restatement of the subject.>

<Area>:
- <change, and the reason it is that way>

Decisions:
- <deviation from the spec, the screenshots, or the obvious approach — with the reason>

Verified: <what actually ran, and its result>
Phase <n> of <spec path>
```

- `type`: `feat` | `fix` | `refactor` | `chore` | `docs` | `test`
- `scope`: the module (`billing`, `subscriptions`, `auth`) or the app (`api`, `web`)
- Group bullets by area (`Backend`, `Frontend`, `Migrations`, `Docs`, `Tests`) once a phase
  touches more than one. A single-area phase can use one flat list
- Explain **why**; the diff already shows what
- `Decisions:` is where the next reader looks for why the code disagrees with the spec or
  the mocks. Open questions go here too. Omit the block when there were none
- `Verified:` lists only what was actually run — `php artisan test (42 passing)`,
  `pint clean`, `pnpm lint clean`, `checked light + dark`. If tests were skipped or a
  suite is red, that is what the line says
- No `Co-Authored-By: Claude` trailer, no `Generated with Claude Code` line
  (`context/ai-interaction.md`)

Commit `727b1a7` is the reference for tone and body structure.

## After committing

Report the phase name and the commit subject in one line. Don't paste the body back — the
user can read it with `git show`.
