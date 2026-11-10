---
title: Troubleshooting
description: Symptoms, causes and fixes for ADVISOR.
---

# Troubleshooting

This page lists the failure symptoms ADVISOR's own docs describe, with the cause, the fix and the command that checks it.

## The advisor server is not in the list

You added the config, opened the project, and the `advisor` server is not in the list.

**Cause.** One of six things keeps a server from appearing:

- **php**: the PHP on your machine satisfies ADVISOR's own `composer.json` (8.3 or newer).
- **php-path**: which `php` your client will start. Clients start `php` by name, so they get the first one on the PATH. A warning when there is none, or when it is not the PHP running doctor (then run doctor with that one too). Doctor only looks; it never runs what it finds.
- **content**: the bundled `data/` still matches its manifest.
- **client:claude**, **client:warriorgpt**, **client:cursor**, **client:vscode**: each client's MCP config: is the file there, does it have the `advisor` entry, and is the script it points at in the project? A missing file is a warning, not a failure; you only need the one client you use.
- **mcp**: the server starts. Doctor boots it in-process and asks for its tools.
- **stdio**: the real thing. Doctor starts `php vendor/bin/advisor mcp` as a child process, the way your client does, and asks it to initialize, list its tools and answer one `policy_lookup` call. Every stdout line must be protocol, and the child must exit 0 when its input closes. The pass line names your OS and PHP version, so on Windows it is the proof that stdio works there.

**Fix.** Run doctor and fix anything it reports as a `fail`. A `fail` is something to fix; a `warn` is something you may not care about.

**Check.**
```bash
php vendor/bin/advisor doctor
```
The exit code is `0` when nothing failed and `1` when something did; `--json` prints the findings as JSON.

## The server refuses to start because it is not enabled

**Cause.** `ADVISOR_ENABLED` defaults to off. It must be `1` or `true`, or the server refuses to start.

**Fix.** Set `ADVISOR_ENABLED=1` in the client config's environment. `advisor install` sets it in the client config.

**Check.**
```bash
php vendor/bin/advisor doctor
```

## The server refuses to start in production

**Cause.** `ADVISOR_ENV` reads `APP_ENV`, then `local`. `production` or `prod` always refuses to start.

**Fix.** Run ADVISOR in development only. It refuses to start in production by design.

**Check.**
```bash
php vendor/bin/advisor doctor
```

## A bad setting stops the server

**Cause.** A bad value stops the server with a message on stderr. It does not guess.

**Fix.** Read the message on stderr and correct the value. ADVISOR reads the process environment only, never a file.

**Check.**
```bash
php vendor/bin/advisor doctor
```

## The server refuses to start because a content file fails its hash

**Cause.** The bundled `data/` is verified at start-up against `data/manifest.json` (a SHA-256 for every file). If a file does not match, the server refuses to start rather than serve it.

**Fix.** Regenerate the bundle from the sibling stack repositories with `content:generate`, then verify it.

**Check.**
```bash
php vendor/bin/advisor content:verify
```

## Related

- [When it does not show up](guides/doctor.md)
- [Configuration](reference/configuration.md)
- [Commands](reference/commands.md)
- [Served content](guides/served-content.md)
- [Refreshing the content](deploying/refreshing-the-content.md)
- [Install](install.md)
