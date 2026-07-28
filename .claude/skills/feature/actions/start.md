# Start Action

1. Read current-feature.md - verify Goals are populated
2. If empty, error: "Run /feature load first"
3. Set Status to "In Progress"
4. Create and checkout the feature branch (derive name from H1 heading)
5. Read the full spec linked from `## Notes` and list its **phases** (the headings under
   `## Changes Required`), not just the goals. If the spec has a `## Commit plan`, that is
   the agreed phase → commit breakdown — follow it
6. If the spec has no phases, split the goals into phases yourself and show that split
   before writing code
7. Implement phase by phase, in spec order. At the end of every phase, without being asked:
   - run the gates in [commit.md](commit.md)
   - commit that phase using the message format in [commit.md](commit.md)
   - report the phase and its commit subject in one line, then start the next phase
8. Stop and ask only when a phase is blocked by a decision that is genuinely the user's —
   finish and commit every phase that isn't blocked first
