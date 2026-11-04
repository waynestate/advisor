---
title: Configuration
description: Every ADVISOR environment variable, its default and its meaning.
---

# Configuration

ADVISOR reads the process environment only, never a file. This page lists every variable with its default and its meaning, and the placeholders are in `.env.example`. Nothing in 0.1 needs a secret, key, certificate, token or host, so every setting is optional.

## Environment variables

<!-- generated:env -->
| Variable | Meaning | Default | Read in |
| --- | --- | --- | --- |
| ADVISOR_AUDIT_LOG | A file for a one-line-per-call audit log: time, tool, a hash of the arguments, outcome, duration. Arguments are hashed, never written. | off | src/Config.php |
| ADVISOR_BOOSTER_BIN | Where `upgrade_path` finds Booster when the app has no `vendor/bin/booster` of its own: the path to booster's `bin/booster` (or a `booster.phar`). The app's own `vendor/bin/booster` always wins. | none | src/Config.php |
| ADVISOR_BOOSTER_TIMEOUT | Seconds before a Booster plan is stopped (1 to 900). A plan is local and fast, so the default is shorter than a PROCTOR run. | `60` | src/Config.php |
| ADVISOR_ENABLED | Must be `1` or `true`, or the server refuses to start. `advisor install` sets it in the client config. | off | src/Command/ClientConfig.php |
| ADVISOR_ENV | `production` or `prod` always refuses to start. | `APP_ENV`, then `local` | src/Config.php |
| ADVISOR_GROUPS | Comma list of tool groups to offer (`stack`, `proctor`, `booster`). See below for what each group may start. | all | src/Config.php |
| ADVISOR_LOG_LEVEL | Log level on stderr (`debug` to `emergency`). | `warning` | src/Config.php |
| ADVISOR_MAX_CHARS | Largest piece of text one doc call returns (1000 to 45000). | `20000` | src/Config.php |
| ADVISOR_MODULES_FILE | An extra module manifest (see "Modules"). | none | src/Config.php |
| ADVISOR_PRIVATE_DENYLIST | A file (kept outside the repo) with private strings to strip from the bundle; used by `content:generate` and the public-posture test. See "Releasing". | none | src/Content/Denylist.php |
| ADVISOR_PROCTOR_TIMEOUT | Seconds before a PROCTOR run is stopped (1 to 900). | `120` | src/Config.php |
| ADVISOR_PROJECT_ROOT | The folder `run_proctor_check` may look inside. Paths outside it are refused. | the working directory | src/Command/DoctorCommand.php |
| ADVISOR_READ_ONLY | Set to `0` or `false` to allow tools that write. None exist in 0.1. Read-only wins over `ADVISOR_GROUPS`. | on | src/Config.php |
| ADVISOR_STACK_DIR | Folder holding the sibling stack repos; used only by `content:generate`. | none | src/Command/ContentGenerateCommand.php |
<!-- /generated -->

## Example

A short example, with placeholder values:

```bash
ADVISOR_ENABLED=1
ADVISOR_ENV=local
ADVISOR_PROJECT_ROOT=/path/to/your/app
ADVISOR_AUDIT_LOG=/path/to/advisor-audit.log
```

`advisor install` sets `ADVISOR_ENABLED` in the client config for you.

> [!WARNING]
> A bad value stops the server with a message on stderr. It does not guess.

## Related

- [Commands](commands.md)
- [Quick start](../guides/quick-start.md)
- [No PHP? Use Docker](../guides/docker.md)
- [Safety](../guides/safety.md)
