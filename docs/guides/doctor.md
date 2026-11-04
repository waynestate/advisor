---
title: When it does not show up
description: What to do when the advisor server does not show up in your client.
---

# When it does not show up

This page is the one to follow when you added the config, opened the project, and the `advisor` server is not in the list.

## Run the doctor

```bash
php vendor/bin/advisor doctor [--json]   # check the install when the server does not show up
```

It checks the four things that keep a server from appearing, in plain words, and changes nothing. `--json` prints the findings as JSON.

## What it checks

- **php**: the PHP on your machine satisfies ADVISOR's own `composer.json` (8.3 or newer).
- **content**: the bundled `data/` still matches its manifest.
- **client:claude**, **client:warriorgpt**, **client:cursor**, **client:vscode**: each client's MCP config: is the file there, does it have the `advisor` entry, and is the script it points at in the project? A missing file is a warning, not a failure; you only need the one client you use.
- **mcp**: the server actually starts. Doctor boots it in-process and asks for its tools.

## Reading the result

A `fail` is something to fix; a `warn` is something you may not care about. The exit code is `0` when nothing failed and `1` when something did. If doctor itself cannot run, it reports a `doctor` failure and exits `2`.

The advisor will see you now.

## Related

- [Quick start](quick-start.md)
- [One line for Claude Code](claude-code.md)
- [No PHP? Use Docker](docker.md)
- [Configuration](../reference/configuration.md)
- [Install](../install.md)
