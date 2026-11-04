---
title: Install
description: How to install ADVISOR in a project and what it needs.
---

# Install

This page shows what ADVISOR needs and how to add it to a Wayne State PHP app.

## Requirements

PHP 8.3 or newer (8.3, 8.4 and 8.5 are tested). The package requires:

- PHP `^8.3`
- extensions `ext-fileinfo`, `ext-json` and `ext-mbstring`
- `composer/semver` `^3.0`
- `mcp/sdk` `0.9.0`
- `opis/json-schema` `^2.4`
- `psr/log` `^3.0`
- `symfony/console` `^7.4`
- `symfony/process` `^7.4`

The platform is pinned to PHP 8.3.0 in `composer.json`.

## Install

Add it to the app as a dev dependency:

```bash
composer require --dev waynestate/advisor
```

The command is then `php vendor/bin/advisor`. This works the same on Windows, macOS and Linux, because it is one PHP command and not a shell script.

ADVISOR is on Packagist: `composer require --dev waynestate/advisor:^0.1`. No extra repository needed.

## Verify

Check the install with:

```bash
php vendor/bin/advisor doctor
```

It checks the four things that keep a server from appearing, in plain words, and changes nothing:

- **php**: the PHP on your machine satisfies ADVISOR's own `composer.json` (8.3 or newer).
- **content**: the bundled `data/` still matches its manifest.
- **client:claude**, **client:warriorgpt**, **client:cursor**, **client:vscode**: each client's MCP config.
- **mcp**: the server actually starts. Doctor boots it in-process and asks for its tools.

A `fail` is something to fix; a `warn` is something you may not care about. The exit code is `0` when nothing failed and `1` when something did; `--json` prints the findings as JSON.

## Next steps

- [Quick start](guides/quick-start.md): register the `advisor` server with a client.
- [What it offers](guides/what-it-offers.md): the nine read-only tools.
- [Configuration](reference/configuration.md): the environment variables.

## Related

- [Overview](index.md)
- [Quick start](guides/quick-start.md)
- [Configuration](reference/configuration.md)
- [Commands](reference/commands.md)
