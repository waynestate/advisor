---
title: Commands
description: Every advisor command and what it does.
---

# Commands

Every command the `advisor` CLI takes, and what it does.

## Running a command

Run any command with `php vendor/bin/advisor <command>`. This is one PHP command, not a shell script, so it works the same on Windows, macOS and Linux. The `mcp` command is started by your MCP client, not by you.

```
php vendor/bin/advisor mcp                          # serve over stdio (your client starts this)
php vendor/bin/advisor install [--client=claude] [--write] [--force] [--path=.]
php vendor/bin/advisor content:verify               # check the bundled content against its manifest
php vendor/bin/advisor content:generate [--sources=<dir>] [--check]
php vendor/bin/advisor stack-parts [--write=<readme>] [--check=<readme>]
php vendor/bin/advisor doctor [--json]              # check the install when the server does not show up
```

Stdout carries only protocol messages. Logs, warnings and stray output go to stderr, so a client never sees garbage.

## Commands

<!-- generated:commands -->
| Command | What it does |
| --- | --- |
| `bin/advisor doctor` | Check the install: PHP, the bundled content, the client config, and the server. |
| `bin/advisor install` | Print (or with --write, write) the MCP client config that starts ADVISOR. |
| `bin/advisor mcp` | Serve ADVISOR to an MCP client over stdio (started by the client, not by hand). |
| `bin/advisor stack-parts` | Render the stack parts table from the seed; write or check the marked block in a README. |
| `bin/advisor content:generate` | Rebuild the bundled content from the stack repositories (--check only compares). |
| `bin/advisor content:verify` | Check the bundled content against its manifest. |

### `bin/advisor doctor`

Check the install: PHP, the bundled content, the client config, and the server.

Usage: `bin/advisor doctor [--json]`

| Option | Meaning | Default |
| --- | --- | --- |
| `--json` | Print the findings as JSON. |  |
| `--silent` | Do not output any message |  |

### `bin/advisor install`

Print (or with --write, write) the MCP client config that starts ADVISOR.

Usage: `bin/advisor install [-c|--client CLIENT] [--write] [--force] [--path PATH]`

| Option | Meaning | Default |
| --- | --- | --- |
| `-c`, `--client=VALUE` | claude, warriorgpt, cursor, vscode or codex. | `claude` |
| `--write` | Write the file in the project instead of printing it. |  |
| `--force` | Replace an existing, different "advisor" entry. |  |
| `--path=VALUE` | Project folder to write into. | `.` |
| `--silent` | Do not output any message |  |

### `bin/advisor mcp`

Serve ADVISOR to an MCP client over stdio (started by the client, not by hand).

Usage: `bin/advisor mcp`

| Option | Meaning | Default |
| --- | --- | --- |
| `--silent` | Do not output any message |  |

### `bin/advisor stack-parts`

Render the stack parts table from the seed; write or check the marked block in a README.

Usage: `bin/advisor stack-parts [--write WRITE] [--check CHECK]`

| Option | Meaning | Default |
| --- | --- | --- |
| `--write=VALUE` | Replace the block between the stack-parts markers in this file (repeatable) |  |
| `--check=VALUE` | Exit 1 when the block in this file is stale (repeatable) |  |
| `--silent` | Do not output any message |  |

### `bin/advisor content:generate`

Rebuild the bundled content from the stack repositories (--check only compares).

Usage: `bin/advisor content:generate [-s|--sources SOURCES] [--check]`

| Option | Meaning | Default |
| --- | --- | --- |
| `-s`, `--sources=VALUE` | Folder that holds the stack repositories (creed, proctor, wayne-ui, ...). Default: ADVISOR_STACK_DIR. |  |
| `--check` | Exit 1 when the bundle differs from what the sources produce; write nothing. |  |
| `--silent` | Do not output any message |  |

### `bin/advisor content:verify`

Check the bundled content against its manifest.

Usage: `bin/advisor content:verify`

| Option | Meaning | Default |
| --- | --- | --- |
| `--silent` | Do not output any message |  |

### Options every command accepts

| Option | Meaning | Default |
| --- | --- | --- |
| `-h`, `--help` | Display help for the given command. When no command is given display help for the list command |  |
| `-q`, `--quiet` | Only errors are displayed. All other output is suppressed |  |
| `-v|-vv|-vvv`, `--verbose` | Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug |  |
| `-V`, `--version` | Display this application version |  |
| `--ansi` | Force (or disable --no-ansi) ANSI output |  |
| `--no-ansi` | Negate the "--ansi" option |  |
| `-n`, `--no-interaction` | Do not ask any interactive question |  |
<!-- /generated -->

## Exit codes

- `content:generate --check` exits `1` on drift, when the bundled content no longer matches the source repos.
- `doctor` exits `0` when nothing failed and `1` when something did; `--json` prints the findings as JSON. If doctor itself cannot run, it reports a `doctor` failure and exits `2`.
- `composer content:drift` exits `1` when `data/` no longer matches the stack repos, or when `ADVISOR_STACK_DIR` is not set.

## Related

- [Quick start](../guides/quick-start.md)
- [When it does not show up](../guides/doctor.md)
- [Refreshing the content](../deploying/refreshing-the-content.md)
- [Configuration](configuration.md)
