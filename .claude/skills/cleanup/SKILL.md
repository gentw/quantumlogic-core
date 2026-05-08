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
7. Check that `api/.env.example` lists every key present in `api/.env` (values may differ — only flag missing keys). `web/` has no env files committed; skip unless one is added.
8. Find `eslint-disable` / `eslint-disable-next-line` comments in `web/src/` and `@phpstan-ignore` / `@phpcs:ignore` comments in `api/app/` that may be stale

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