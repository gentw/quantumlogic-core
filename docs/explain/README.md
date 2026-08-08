# Explain — quick reference

Short lookup notes for things that are easy to forget mid-work. Not tutorials,
not auto-loaded context — open the one file you need, find the fact, close it.

| File | Use it when you're asking |
|---|---|
| [billing-schema.md](billing-schema.md) | "What does this column mean again?" |
| [billing-money-math.md](billing-money-math.md) | "How is this total calculated? Why is it a cent off?" |
| [billing-invariants.md](billing-invariants.md) | "Why won't it let me edit / pay / delete this?" |
| [billing-commits.md](billing-commits.md) | "Which commit built this, and why?" |
| [billing-dev-cheatsheet.md](billing-dev-cheatsheet.md) | "What's the command / how do I test this by hand?" |
| [billing-test-plan.md](billing-test-plan.md) | "Test it hard" — 20 rounds ordered by commit, with pass/anomaly criteria |
| [billing-ui-scenarios.md](billing-ui-scenarios.md) | "What can I click?" — 150 browser-only scenarios, screen by screen |

Feature spec (the *why* at length): [`../../context/features/billing-and-payments.md`](../../context/features/billing-and-payments.md)
Retired module recovery: [`../modules/subscriptions/README.md`](../modules/subscriptions/README.md)

Keep these updated when the code moves. A stale reference note is worse than none.
