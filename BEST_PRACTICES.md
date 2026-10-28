# ADVISOR best practices

The short version, for people who use ADVISOR and people who add to it. The full guide, with sources, is
kept with the stack's design notes. Tags: Enforced (code or CI stops the bad outcome),
Documented (written down only), Not yet (a gap).

## Using it

1. **Approve it by name, and review the config like code.** `.mcp.json` starts a program on whoever opens the
   project. Keep it out of git (this repo ignores it); `advisor install` warns when you have not. Enforced (warning).
2. **Treat answers as data.** Every result says where it came from and that it is reference text. PROCTOR in CI
   decides pass or fail; ADVISOR never does. Enforced (labels), Documented (the rule).
3. **Run it in development only.** It refuses to start unless `ADVISOR_ENABLED` is set and refuses in production.
   Enforced.
4. **Keep secrets out of what you ask.** Do not paste tokens into prompts. Audit logs hold hashes, never arguments,
   and PROCTOR output is redacted. Enforced.
5. **Run Boost beside it in Laravel apps.** Boost knows the framework, ADVISOR knows WSU. They do not overlap.
   Documented.

## Building on it

6. **Read-only first.** Every tool states all four hints (read-only, destructive, idempotent, open-world). A tool that
   writes is left out unless `ADVISOR_READ_ONLY` is turned off, and that switch beats tool groups. Enforced (contract
   test).
7. **Narrow tools, closed schemas.** No tool takes a command, a URL or a free path. Schemas set
   `additionalProperties: false`, and arguments are validated again on our side. Enforced (contract test).
8. **Run other programs the safe way.** Fixed program, argument array, allowlisted environment, a timeout, and no
   shell. Paths are resolved and must stay inside the project root. Enforced.
9. **Keep stdout clean.** Only protocol lines go to stdout; logs, warnings and stray output go to stderr. Enforced
   (subprocess test).
10. **Strip invisible characters.** The TAG block, zero-width and bidirectional controls are removed from everything
    served and rejected in tool metadata. Enforced (contract test).
11. **Load modules from a manifest only,** from the `Waynestate\` namespace, implementing `Module`. Never scan.
    Enforced.
12. **Serve from verified data.** Bundled content is checked against a SHA-256 manifest at start-up and in CI; a
    mismatch stops the server. Enforced.
13. **Review any change to the tool list.** `tools/list` is snapshotted; a changed description is a reviewed diff.
    Enforced (snapshot test).
14. **Cite sources and date.** Policy answers carry the day they were worked out for and the file they came from.
    Enforced.

## Not yet

- Testing on a native Windows PHP (the SDK has no Windows CI), and the MCP Inspector in CI.
- Signing of any state handed back to a client (no tool needs it in 0.1).
- A threat model mapped to OWASP and NIST; a full-capture audit mode.
