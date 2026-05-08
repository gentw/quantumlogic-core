# Custom Subagents

Custom Claude Code subagents used in this project for automated code analysis and review. These files live in `.claude/agents/` and are auto-discovered by Claude Code.

- `code-scanner.md` — Scans `api/` and `web/` for security, performance, and code quality issues
- `auth-auditor.md` — Deep-dives Laravel auth code (Passport / Sanctum / JWT, OTP, password reset, the three signup endpoints) for security vulnerabilities; writes a report to `docs/audit-results/AUTH_SECURITY_REVIEW.md`
- `refactor-scanner.md` — Identifies repeated logic in `api/app/` and `web/src/` that should become services / FormRequests / composables / utils
- `ui-reviewer.md` — Drives the SPA via the Playwright MCP and reviews visual / responsiveness / accessibility / SaaS-dashboard concerns
