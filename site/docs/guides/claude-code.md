---
title: "One line for Claude Code | ADVISOR docs"
description: "The one line that connects Claude Code to ADVISOR."
url: https://advisor.apps.wayne.edu/docs/guides/claude-code.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# One line for Claude Code [#](https://advisor.apps.wayne.edu/docs/guides/claude-code.html#one-line-for-claude-code)

This page gives the one line that connects Claude Code to ADVISOR, and the `install` command that does the same thing.

## The one line [#](https://advisor.apps.wayne.edu/docs/guides/claude-code.html#the-one-line)

If you would rather type it than generate it, this does the same as `install --client=claude --write`; project scope keeps it out of your other projects, and the same "do not commit it" warning applies:

```bash
claude mcp add --scope project advisor -e ADVISOR_ENABLED=1 -- php vendor/bin/advisor mcp
```

## The install command [#](https://advisor.apps.wayne.edu/docs/guides/claude-code.html#the-install-command)

The `install` command does the same thing and supports Claude among its clients:

```bash
php vendor/bin/advisor install [--client=claude] [--write] [--force] [--path=.]
```

`install` puts `ADVISOR_ENABLED` in the client config's env block and warns when the file is not in `.gitignore`.

## Keep it out of git [#](https://advisor.apps.wayne.edu/docs/guides/claude-code.html#keep-it-out-of-git)

Warning

Approve it by name, and review the config like code. `.mcp.json` starts a program on whoever opens the project. Keep it out of git (this repo ignores it); `advisor install` warns when you have not.

## Related [#](https://advisor.apps.wayne.edu/docs/guides/claude-code.html#related)

- [No PHP? Use Docker](https://advisor.apps.wayne.edu/docs/guides/docker.html)
- [Quick start](https://advisor.apps.wayne.edu/docs/guides/quick-start.html)
- [Install](https://advisor.apps.wayne.edu/docs/install.html)
- [Laravel apps](https://advisor.apps.wayne.edu/docs/guides/laravel.html)
