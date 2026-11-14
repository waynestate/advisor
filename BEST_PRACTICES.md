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
5. **Run Boost beside it in Laravel apps.** Boost knows the framework and keeps your app's own project rules,
   ADVISOR knows WSU. Do not copy CREED rules into Boost's `.ai/rules`: a project rule adds to CREED and never
   overrides it (the Laravel guide, "Boost's project rules and CREED's rules"). Documented.

## Building on it

6. **Read-only first.** Every tool states all four hints (read-only, destructive, idempotent, open-world). A tool that
   writes is left out unless `ADVISOR_READ_ONLY` is turned off, and that switch beats tool groups. Enforced (contract
   test).
7. **Narrow tools, closed schemas.** No tool takes a command, a URL or a free path. Schemas set
   `additionalProperties: false`, and arguments are validated again on our side. Answers are closed too: every tool
   publishes an output schema with each field described, and the tests check every answer against it. Enforced
   (contract test).
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

## Threat model

ADVISOR is a local stdio MCP server started by a developer's AI client. What it protects: the developer's
machine and project, the AI session it answers, and the trust people put in its answers. It has no network
listener, no sign-in and no network calls of its own.

| Threat | Rules | Framework |
|---|---|---|
| A cloned project's `.mcp.json` starts a program the developer never approved | 1, 3 | OWASP Top 10:2025 A02 Security Misconfiguration |
| Served text steers the AI client (prompt injection through answers) | 2, 10, 14 | OWASP Top 10:2025 A05 Injection |
| A tool's description changes quietly after approval (tool poisoning) | 13, 10 | OWASP Top 10:2025 A08 Software or Data Integrity Failures |
| Secrets pasted into prompts end up in logs or reports | 4 | OWASP Top 10:2025 A09 Security Logging and Alerting Failures; OWASP ASVS 5.0 (logging) |
| A tool argument smuggles a command, URL or path outside the project | 7, 8 | OWASP Top 10:2025 A05 Injection and A01 Broken Access Control; OWASP ASVS 5.0 (input validation) |
| A tool writes when the developer expected a read | 6 | OWASP Top 10:2025 A01 Broken Access Control |
| Stray output on stdout corrupts the protocol stream | 9 | OWASP Top 10:2025 A10 Mishandling of Exceptional Conditions |
| A planted class is loaded as a module | 11 | OWASP Top 10:2025 A03 Software Supply Chain Failures |
| Bundled content is tampered with after release | 12 | OWASP Top 10:2025 A08; NIST SP 800-218 (SSDF) PS.2, release integrity |
| A stale or unsourced policy answer is taken as current | 14, 2 | OWASP Top 10:2025 A06 Insecure Design |
| The server runs in production, where nobody reviews its use | 3 | OWASP Top 10:2025 A02 Security Misconfiguration |

**Out of scope**

- A network attacker (stdio only, no HTTP listener).
- Sign-in and identity (no accounts, so NIST SP 800-63 does not apply).
- A developer machine that is already compromised.

Rule 5 (run Boost beside it) is a usage note and answers no threat.

The first green native-os run is 43577372340 (2026-11-14, head 4c57819): `advisor doctor` and the stdio tests passed on Windows and macOS, PHP 8.3 and 8.5.

## Not yet

- The MCP Inspector in CI (open question 7).
- Signing of any state handed back to a client (no tool needs it yet).
- A full-capture audit mode.
