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
- `symfony/console` `^7.4 || ^8.0`
- `symfony/process` `^7.4 || ^8.0`
- `waynestate/creed` `^0.1.8` (CREED's policy reader)

The platform is pinned to PHP 8.3.0 in `composer.json`.

## Install

Add it to the app as a dev dependency:

```bash
composer require --dev waynestate/advisor
```

The command is then `php vendor/bin/advisor`. This works the same on Windows, macOS and Linux, because it is one PHP command and not a shell script.

ADVISOR is on Packagist: `composer require --dev waynestate/advisor:^0.3`. No extra repository needed.

> [!NOTE]
> Native Windows is not yet proven. ADVISOR is one PHP command, so it is built to run the same everywhere, but it has not been proven on native Windows. If you are on Windows, run `php vendor\bin\advisor doctor`: its **stdio** line says whether the server works over stdio on your machine ([On Windows](guides/doctor.md#on-windows)). If it fails, [use Docker](guides/docker.md).

## Verify

Check the install with:

```bash
php vendor/bin/advisor doctor
```

It checks the six things that keep a server from appearing, in plain words, and changes nothing:

- **php**: the PHP on your machine satisfies ADVISOR's own `composer.json` (8.3 or newer).
- **php-path**: which `php` your client will start. Clients start `php` by name, so they get the first one on the PATH. A warning when there is none, or when it is not the PHP running doctor (then run doctor with that one too). Doctor only looks; it never runs what it finds.
- **content**: the bundled `data/` still matches its manifest.
- **client:claude**, **client:warriorgpt**, **client:cursor**, **client:vscode**: each client's MCP config.
- **mcp**: the server starts. Doctor boots it in-process and asks for its tools.
- **stdio**: the real thing. Doctor starts `php vendor/bin/advisor mcp` as a child process, the way your client does, and asks it to initialize, list its tools and answer one `policy_lookup` call. Every stdout line must be protocol, and the child must exit 0 when its input closes. The pass line names your OS and PHP version, so on Windows it is the proof that stdio works there.

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
