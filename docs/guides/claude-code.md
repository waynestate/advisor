---
title: One line for Claude Code
description: The one line that connects Claude Code to ADVISOR.
---

# One line for Claude Code

This page gives the one line that connects Claude Code to ADVISOR, and the `install` command that does the same thing.

## The one line

If you would rather type it than generate it, this does the same as `install --client=claude --write`; project scope keeps it out of your other projects, and the same "do not commit it" warning applies:

```bash
claude mcp add --scope project advisor -e ADVISOR_ENABLED=1 -- php vendor/bin/advisor mcp
```

## The install command

The `install` command does the same thing and supports Claude among its clients:

```bash
php vendor/bin/advisor install [--client=claude] [--write] [--force] [--path=.]
```

`install` puts `ADVISOR_ENABLED` in the client config's env block and warns when the file is not in `.gitignore`.

## Keep it out of git

> [!WARNING]
> Approve it by name, and review the config like code. `.mcp.json` starts a program on whoever opens the project. Keep it out of git (this repo ignores it); `advisor install` warns when you have not.

## Related

- [No PHP? Use Docker](docker.md)
- [Quick start](quick-start.md)
- [Install](../install.md)
- [Laravel apps](laravel.md)
