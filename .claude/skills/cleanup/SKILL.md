---
name: cleanup
description: Clean up project housekeeping tasks (add "run" to execute fixes)
argument-hint: run|check
---

Review the codebase for cleanup tasks:

1. Make sure that the history in @context/current-feature.md is in order from oldest to newest
2. Find leftover debug output: `console.log` / `console.debug` in `web/src/`, and `dd()` / `dump()` / `var_dump()` / `ray()` in `api/app/`
3. Find unused imports — `import` statements in `web/src/` and `use` statements in `api/app/`
4. Check for stale TODO/FIXME/XXX comments in `api/app/` and `web/src/`
5. Find orphaned/unused files in `web/src/` (components, composables, views, utils not referenced anywhere) and `api/app/` (controllers/models/jobs not referenced by routes, schedules, or other code)
6. Check that context files (CLAUDE.md, context/project-overview.md, context/backend-history.md, context/frontend-history.md) match actual project state — flag drift, not minor wording
7. Check that `api/.env.example` lists every key present in `api/.env`, and that `web/.env.example` lists every `VITE_*` key present in `web/.env` (values may differ — only flag missing keys)
8. Find `eslint-disable` / `eslint-disable-next-line` comments in `web/src/` and `@phpstan-ignore` / `@phpcs:ignore` comments in `api/app/` that may be stale
9. Find pre-pivot brand names. This codebase was *SentriGate* and, before that, carried
   *Delta Connect* copy — the product is **QuantumLogic** and neither name should appear:

   ```bash
   grep -rniE "delta ?connect|sentri ?gate|sentrigate" web/src api/app api/resources api/config
   ```

   The wordmark also appears split across HTML in the email templates
   (`Sentri <span …>Gate</span>`), which the pattern above will not catch — grep `Sentri`
   on its own in `api/resources/views/` as well. **Two exclusions:** the dormant security
   module owns `sentrigate.txt` in `DomainController.php`, which is a real external filename
   and out of scope per `context/ai-interaction.md`; and `context/`, `docs/` and `history/`
   legitimately record the old names as history.

**Mode: $ARGUMENTS**

If no argument or argument is "check":

- Only report findings, don't modify anything
- List what WOULD be cleaned up

If the argument is "run" or "fix":

- First, report all findings with numbered items
- Then ask: "Which items would you like me to fix? (enter numbers like 1,3,5 or 'all' or 'none')"
- Wait for user response before making any changes
- Only fix the items the user specifies
- Report what you changed