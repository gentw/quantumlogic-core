# Complete Action

Phases were already committed as they landed (see [commit.md](commit.md)). This action
closes the feature out — it is **not** where the work gets committed.

1. Run `git status`. Anything left over from this feature gets its own commit in the
   [commit.md](commit.md) format. Leave unrelated work in progress alone — don't sweep it in
2. Switch to main and merge the feature branch with `--no-ff`, so the phase commits stay
   grouped under one merge commit (no push yet)
3. Delete the local feature branch
4. Reset current-feature.md:
   - Change H1 back to `# Current Feature`
   - Clear Goals and Notes sections (keep placeholder comments)
   - Add feature summary to the END of History
5. Commit the reset: `chore: reset current-feature.md after completing [feature]`
6. Push main to origin ONCE (single push with all changes)
7. If feature branch was previously pushed, delete it from origin