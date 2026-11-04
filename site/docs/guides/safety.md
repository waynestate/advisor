---
title: "Safety | ADVISOR docs"
description: "How ADVISOR stays read-only and safe to run."
url: https://advisor.apps.wayne.edu/docs/guides/safety.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Safety[#](https://advisor.apps.wayne.edu/docs/guides/safety.html#safety)

This page collects the safety rules ADVISOR enforces and the settings behind them, so you know what it will and will not do.

## Safety, in short[#](https://advisor.apps.wayne.edu/docs/guides/safety.html#safety-in-short)

- Off unless enabled; refuses production; refuses to start on a bad setting or a content file that fails its hash.
- Read-only by default; no tool takes a command, a URL or a free path.
- Every result is cleaned of invisible characters and labeled as data; secrets in PROCTOR output are redacted.
- `run_proctor_check` runs one fixed program with an argument array and an allowlisted environment, never a shell.
- `upgrade_path` runs Booster's read-only `plan` the same way: one fixed program, an argument array, an allowlisted command, never a shell. It plans; it never runs a hop.
- No network calls, and no model calls, from ADVISOR itself.

## Settings that enforce it[#](https://advisor.apps.wayne.edu/docs/guides/safety.html#settings-that-enforce-it)

| Variable | Default | Meaning |
| --- | --- | --- |
| `ADVISOR_READ_ONLY` | on | Set to `0` or `false` to allow tools that write. None exist in 0.1. Read-only wins over `ADVISOR_GROUPS`. |
| `ADVISOR_ENV` | `APP_ENV`, then `local` | `production` or `prod` always refuses to start. |
| `ADVISOR_PROJECT_ROOT` | the working directory | The folder `run_proctor_check` may look inside. Paths outside it are refused. |
| `ADVISOR_AUDIT_LOG` | off | A file for a one-line-per-call audit log: time, tool, a hash of the arguments, outcome, duration. Arguments are hashed, never written. |
| `ADVISOR_GROUPS` | all | Comma list of tool groups to offer (`stack`, `proctor`, `booster`). The [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html) page says what each group may start. |

Warning

A bad value stops the server with a message on stderr. It does not guess.

## Best practices[#](https://advisor.apps.wayne.edu/docs/guides/safety.html#best-practices)

From `BEST_PRACTICES.md`, the safety rules:

- **Run it in development only.** It refuses to start unless `ADVISOR_ENABLED` is set and refuses in production. Enforced.
- **Keep secrets out of what you ask.** Do not paste tokens into prompts. Audit logs hold hashes, never arguments, and PROCTOR output is redacted. Enforced.
- **Read-only first.** Every tool states all four hints (read-only, destructive, idempotent, open-world). A tool that writes is left out unless `ADVISOR_READ_ONLY` is turned off, and that switch beats tool groups. Enforced (contract test).
- **Narrow tools, closed schemas.** No tool takes a command, a URL or a free path. Schemas set `additionalProperties: false`, and arguments are validated again on our side. Enforced (contract test).
- **Run other programs the safe way.** Fixed program, argument array, allowlisted environment, a timeout, and no shell. Paths are resolved and must stay inside the project root. Enforced.
- **Strip invisible characters.** The TAG block, zero-width and bidirectional controls are removed from everything served and rejected in tool metadata. Enforced (contract test).
- **Serve from verified data.** Bundled content is checked against a SHA-256 manifest at start-up and in CI; a mismatch stops the server. Enforced.

## Related[#](https://advisor.apps.wayne.edu/docs/guides/safety.html#related)

- [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html)
- [Configuration](https://advisor.apps.wayne.edu/docs/reference/configuration.html)
- [Best practices](https://advisor.apps.wayne.edu/docs/reference/best-practices.html)
- [Quick start](https://advisor.apps.wayne.edu/docs/guides/quick-start.html)
- [Install](https://advisor.apps.wayne.edu/docs/install.html)
