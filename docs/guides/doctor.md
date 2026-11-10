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

It checks the six things that keep a server from appearing, in plain words, and changes nothing. `--json` prints the findings as JSON.

## What it checks

- **php**: the PHP on your machine satisfies ADVISOR's own `composer.json` (8.3 or newer).
- **php-path**: which `php` your client will start. Clients start `php` by name, so they get the first one on the PATH. A warning when there is none, or when it is not the PHP running doctor (then run doctor with that one too). Doctor only looks; it never runs what it finds.
- **content**: the bundled `data/` still matches its manifest.
- **client:claude**, **client:warriorgpt**, **client:cursor**, **client:vscode**: each client's MCP config: is the file there, does it have the `advisor` entry, and is the script it points at in the project? A missing file is a warning, not a failure; you only need the one client you use.
- **mcp**: the server starts. Doctor boots it in-process and asks for its tools.
- **stdio**: the real thing. Doctor starts `php vendor/bin/advisor mcp` as a child process, the way your client does, and asks it to initialize, list its tools and answer one `policy_lookup` call. Every stdout line must be protocol, and the child must exit 0 when its input closes. The pass line names your OS and PHP version, so on Windows it is the proof that stdio works there.

## Reading the result

A `fail` is something to fix; a `warn` is something you may not care about. The exit code is `0` when nothing failed and `1` when something did. If doctor itself cannot run, it reports a `doctor` failure and exits `2`.

## On Windows

Run the same command in PowerShell; it needs no bash or WSL:

```powershell
php vendor\bin\advisor doctor
```

A `pass` on the **stdio** line reads like `php bin/advisor mcp runs as a child process on Windows (PHP 8.4.x)`. That one line is the evidence ADVISOR's first open question asks for (does the MCP SDK's stdio work on native Windows PHP), so if you are on Windows, paste it into an issue on git.wayne.edu and you have moved the stack forward. If **php-path** warns that there is no `php` on the PATH, your client will not find PHP either: put PHP's folder on the PATH, or write the full path to `php.exe` as the `command` in the client config.

The advisor will see you now.

## Related

- [Quick start](quick-start.md)
- [One line for Claude Code](claude-code.md)
- [No PHP? Use Docker](docker.md)
- [Configuration](../reference/configuration.md)
- [Install](../install.md)
