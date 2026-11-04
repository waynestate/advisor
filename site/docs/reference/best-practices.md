---
title: "Best practices | ADVISOR docs"
description: "The rules ADVISOR holds itself to, and the tests that prove them."
url: https://advisor.apps.wayne.edu/docs/reference/best-practices.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Best practices[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#best-practices)

These are the rules ADVISOR holds itself to, the short version for people who use ADVISOR and people who add to it, with the test that enforces each rule where there is one.

Tags: Enforced (code or CI stops the bad outcome), Documented (written down only), Not yet (a gap).

## Approve it by name, and review the config like code[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#approve-it-by-name-and-review-the-config-like-code)

`.mcp.json` starts a program on whoever opens the project. Keep it out of git (this repo ignores it); `advisor install` warns when you have not. Enforced (warning).

## Treat answers as data[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#treat-answers-as-data)

Every result says where it came from and that it is reference text. PROCTOR in CI decides pass or fail; ADVISOR never does. Enforced (labels), Documented (the rule).

## Run it in development only[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#run-it-in-development-only)

It refuses to start unless `ADVISOR_ENABLED` is set and refuses in production. Enforced.

## Keep secrets out of what you ask[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#keep-secrets-out-of-what-you-ask)

Do not paste tokens into prompts. Audit logs hold hashes, never arguments, and PROCTOR output is redacted. Enforced.

## Run Boost beside it in Laravel apps[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#run-boost-beside-it-in-laravel-apps)

Boost knows the framework, ADVISOR knows WSU. They do not overlap. Documented.

## Read-only first[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#read-only-first)

Every tool states all four hints (read-only, destructive, idempotent, open-world). A tool that writes is left out unless `ADVISOR_READ_ONLY` is turned off, and that switch beats tool groups. Enforced (contract test).

## Narrow tools, closed schemas[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#narrow-tools-closed-schemas)

No tool takes a command, a URL or a free path. Schemas set `additionalProperties: false`, and arguments are validated again on our side. Enforced (contract test).

## Run other programs the safe way[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#run-other-programs-the-safe-way)

Fixed program, argument array, allowlisted environment, a timeout, and no shell. Paths are resolved and must stay inside the project root. Enforced.

## Keep stdout clean[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#keep-stdout-clean)

Only protocol lines go to stdout; logs, warnings and stray output go to stderr. Enforced (subprocess test).

## Strip invisible characters[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#strip-invisible-characters)

The TAG block, zero-width and bidirectional controls are removed from everything served and rejected in tool metadata. Enforced (contract test).

## Load modules from a manifest only[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#load-modules-from-a-manifest-only)

From the `Waynestate\` namespace, implementing `Module`. Never scan. Enforced.

## Serve from verified data[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#serve-from-verified-data)

Bundled content is checked against a SHA-256 manifest at start-up and in CI; a mismatch stops the server. Enforced.

## Review any change to the tool list[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#review-any-change-to-the-tool-list)

`tools/list` is snapshotted; a changed description is a reviewed diff. Enforced (snapshot test).

## Cite sources and date[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#cite-sources-and-date)

Policy answers carry the day they were worked out for and the file they came from. Enforced.

## Not yet[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#not-yet)

- Testing on a native Windows PHP (the SDK has no Windows CI), and the MCP Inspector in CI.
- Signing of any state handed back to a client (no tool needs it in 0.1).
- A threat model mapped to OWASP and NIST; a full-capture audit mode.

## Related[#](https://advisor.apps.wayne.edu/docs/reference/best-practices.html#related)

- [Proof](https://advisor.apps.wayne.edu/docs/reference/proof.html)
- [Commands](https://advisor.apps.wayne.edu/docs/reference/commands.html)
- [Safety](https://advisor.apps.wayne.edu/docs/guides/safety.html)
- [Changelog](https://advisor.apps.wayne.edu/docs/changelog.html)
