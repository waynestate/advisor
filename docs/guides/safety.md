---
title: Safety
description: How ADVISOR stays read-only and safe to run.
---

# Safety

This page collects the safety rules ADVISOR enforces and the settings behind them, so you know what it will and will not do.

Each control is also written up as a threat page: [prompt injection](../threats/prompt-injection.md), [path traversal](../threats/path-traversal.md), [command injection](../threats/command-injection.md) and [data exfiltration](../threats/data-exfiltration.md).

## Safety, in short

- Off unless enabled; refuses production; refuses to start on a bad setting or a content file that fails its hash.
- Read-only by default; no tool takes a command, a URL or a free path.
- Every result is cleaned of invisible characters and labeled as data; secrets in PROCTOR output are redacted.
- `run_proctor_check` runs one fixed program with an argument array and an allowlisted environment, never a shell.
- `upgrade_path` runs Booster's read-only `plan` the same way: one fixed program, an argument array, an allowlisted command, never a shell. It plans; it never runs a hop.
- No network calls, and no model calls, from ADVISOR itself.

## Settings that enforce it

| Variable | Default | Meaning |
|---|---|---|
| `ADVISOR_READ_ONLY` | on | Set to `0` or `false` to allow tools that write. None exist in 0.1. Read-only wins over `ADVISOR_GROUPS`. |
| `ADVISOR_ENV` | `APP_ENV`, then `local` | `production` or `prod` always refuses to start. |
| `ADVISOR_PROJECT_ROOT` | the working directory | The folder `run_proctor_check` may look inside. Paths outside it are refused. |
| `ADVISOR_AUDIT_LOG` | off | A file for a one-line-per-call audit log: time, tool, a hash of the arguments, outcome, duration. Arguments are hashed, never written. |
| `ADVISOR_GROUPS` | all | Comma list of tool groups to offer (`stack`, `proctor`, `booster`). The [What it offers](what-it-offers.md) page says what each group may start. |

> [!WARNING]
> A bad value stops the server with a message on stderr. It does not guess.

## Best practices

From `BEST_PRACTICES.md`, the safety rules:

- **Run it in development only.** It refuses to start unless `ADVISOR_ENABLED` is set and refuses in production. Enforced.
- **Keep secrets out of what you ask.** Do not paste tokens into prompts. Audit logs hold hashes, never arguments, and PROCTOR output is redacted. Enforced.
- **Read-only first.** Every tool states all four hints (read-only, destructive, idempotent, open-world). A tool that writes is left out unless `ADVISOR_READ_ONLY` is turned off, and that switch beats tool groups. Enforced (contract test).
- **Narrow tools, closed schemas.** No tool takes a command, a URL or a free path. Schemas set `additionalProperties: false`, and arguments are validated again on our side. Enforced (contract test).
- **Run other programs the safe way.** Fixed program, argument array, allowlisted environment, a timeout, and no shell. Paths are resolved and must stay inside the project root. Enforced.
- **Strip invisible characters.** The TAG block, zero-width and bidirectional controls are removed from everything served and rejected in tool metadata. Enforced (contract test).
- **Serve from verified data.** Bundled content is checked against a SHA-256 manifest at start-up and in CI; a mismatch stops the server. Enforced.

## Related

- [What it offers](what-it-offers.md)
- [Configuration](../reference/configuration.md)
- [Best practices](../reference/best-practices.md)
- [Quick start](quick-start.md)
- [Install](../install.md)
