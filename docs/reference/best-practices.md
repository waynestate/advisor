---
title: Best practices
description: The rules ADVISOR holds itself to, and the tests that prove them.
---

# Best practices

These are the rules ADVISOR holds itself to, the short version for people who use ADVISOR and people who add to it, with the test that enforces each rule where there is one.

Tags: Enforced (code or CI stops the bad outcome), Documented (written down only), Not yet (a gap).

## Approve it by name, and review the config like code

`.mcp.json` starts a program on whoever opens the project. Keep it out of git (this repo ignores it); `advisor install` warns when you have not. Enforced (warning).

## Treat answers as data

Every result says where it came from and that it is reference text. PROCTOR in CI decides pass or fail; ADVISOR never does. Enforced (labels), Documented (the rule).

## Run it in development only

It refuses to start unless `ADVISOR_ENABLED` is set and refuses in production. Enforced.

## Keep secrets out of what you ask

Do not paste tokens into prompts. Audit logs hold hashes, never arguments, and PROCTOR output is redacted. Enforced.

## Run Boost beside it in Laravel apps

Boost knows the framework, ADVISOR knows WSU. They do not overlap. Documented.

## Read-only first

Every tool states all four hints (read-only, destructive, idempotent, open-world). A tool that writes is left out unless `ADVISOR_READ_ONLY` is turned off, and that switch beats tool groups. Enforced (contract test).

## Narrow tools, closed schemas

No tool takes a command, a URL or a free path. Schemas set `additionalProperties: false`, and arguments are validated again on our side. Enforced (contract test).

## Run other programs the safe way

Fixed program, argument array, allowlisted environment, a timeout, and no shell. Paths are resolved and must stay inside the project root. Enforced.

## Keep stdout clean

Only protocol lines go to stdout; logs, warnings and stray output go to stderr. Enforced (subprocess test).

## Strip invisible characters

The TAG block, zero-width and bidirectional controls are removed from everything served and rejected in tool metadata. Enforced (contract test).

## Load modules from a manifest only

From the `Waynestate\` namespace, implementing `Module`. Never scan. Enforced.

## Serve from verified data

Bundled content is checked against a SHA-256 manifest at start-up and in CI; a mismatch stops the server. Enforced.

## Review any change to the tool list

`tools/list` is snapshotted; a changed description is a reviewed diff. Enforced (snapshot test).

## Cite sources and date

Policy answers carry the day they were worked out for and the file they came from. Enforced.

## Threat model

`BEST_PRACTICES.md` carries the threat model: eleven threats, each mapped to the rules above and to OWASP Top 10:2025, OWASP ASVS 5.0 or NIST SSDF, plus what is out of scope. Four of them have a page of their own: [prompt injection](../threats/prompt-injection.md), [path traversal](../threats/path-traversal.md), [command injection](../threats/command-injection.md) and [data exfiltration](../threats/data-exfiltration.md).

## Not yet

- Testing on a native Windows PHP (the SDK has no Windows CI), and the MCP Inspector in CI.
- Signing of any state handed back to a client (no tool needs it yet).
- A full-capture audit mode.

## Related

- [Proof](proof.md)
- [Commands](commands.md)
- [Safety](../guides/safety.md)
- [Changelog](../changelog.md)
