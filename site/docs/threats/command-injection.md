---
title: "Command injection | ADVISOR docs"
description: "A tool argument running a program nobody approved, and the allowlist that prevents it."
url: https://advisor.apps.wayne.edu/docs/threats/command-injection.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Command injection[#](https://advisor.apps.wayne.edu/docs/threats/command-injection.html#command-injection)

An attacker who controls a tool argument wants ADVISOR to start a program or run a command the developer never approved, using the server's privileges on the developer's machine.

## Where it applies in ADVISOR[#](https://advisor.apps.wayne.edu/docs/threats/command-injection.html#where-it-applies-in-advisor)

Only two tools start a child process, and both do it on purpose:

- `run_proctor_check` starts the app's own `vendor/bin/proctor` (`src/Security/ProctorRunner.php:58-63`).
- `upgrade_path` starts Booster's `plan`, preferring the app's own `vendor/bin/booster` and falling back to the operator's configured path (`src/Security/BoosterRunner.php:96-118`).

Everything the model sends for those tools flows into the argument list of one of those two commands, so the argument list is the attack surface. No other tool starts a process; the reference tools answer from bundled files only.

The server usually runs on the developer's own machine, next to the credentials it protects, which is why no-shell matters here more than in a container.

Both tools take a few extra arguments, but each is validated against a closed set before it reaches the command: the check ids against a fixed list (`src/Security/ProctorRunner.php:46-50`), and the version targets against strict patterns (`src/Security/BoosterRunner.php:37-45`).

## What stops it[#](https://advisor.apps.wayne.edu/docs/threats/command-injection.html#what-stops-it)

- **The program is fixed, not chosen.** The binary is located only in the project's own `vendor/bin` or the operator's configured setting; nothing a model sends picks which program runs (`src/Security/ProctorRunner.php:58-63`, `src/Security/BoosterRunner.php:96-118`). The fallback path must look like a PHP script or phar before it is accepted (`src/Security/BoosterRunner.php:107-113`, `src/Security/BoosterRunner.php:121-127`).
- **The subcommand is part of the allowlist.** PROCTOR may only run `check` and Booster only `plan`; a third slot holding anything else is refused (`src/Security/CommandAllowlist.php:53`, `src/Security/CommandAllowlist.php:81`).
- **Argument array, no shell.** The process runner hands Symfony Process an argv array with no stdin, so there is no shell to interpret spaces, semicolons or quotes (`src/Security/SymfonyProcessRunner.php:30-31`).
- **The whole command shape is allowlisted.** Before anything runs, `CommandAllowlist` checks the exact shape `[php, script, subcommand, --option=value ...]`: only known options, each at most once, each value matched against a regex, and the required options present (`src/Security/CommandAllowlist.php:51-70` for PROCTOR, `src/Security/CommandAllowlist.php:79-98` for Booster; option tables at `src/Security/CommandAllowlist.php:20-26` and `src/Security/CommandAllowlist.php:35-42`). Booster's `run`, the subcommand that edits files, is deliberately absent (`src/Security/CommandAllowlist.php:28-31`).
- **The run is capped.** A timeout stops a runaway child, and its output is truncated to 2 MB before it is read (`src/Security/SymfonyProcessRunner.php:17`, `src/Security/SymfonyProcessRunner.php:39-43`).

The tests prove the refusals: another program, another script, another subcommand, an extra flag, a flag in the path slot, a bare argument, a changed format, a duplicate option, a timeout of `1;rm -rf /`, a missing policy and a single-dash option are all refused for PROCTOR (`tests/Security/CommandAllowlistTest.php:35-62`); Booster's `run` subcommand and a `--from-php=7.4;rm` value are refused the same way (`tests/Security/CommandAllowlistTest.php:83-103`). The contract test adds that no tool may be named like a shell or take a free-form command, code, sql or url argument (`tests/Contract/ServerContractTest.php:59-68`).

A reviewer can check this by reading the two bad-command providers in `tests/Security/CommandAllowlistTest.php` and running `composer test`.

## What is left[#](https://advisor.apps.wayne.edu/docs/threats/command-injection.html#what-is-left)

The allowlist pins the shape of the command; it does not audit what the child program does. PROCTOR and BOOSTER run with full access to the project folder and their own logic, so a vulnerability in either one is outside this control.

The environment handed to the child is scrubbed (see [Data exfiltration](https://advisor.apps.wayne.edu/docs/threats/data-exfiltration.html)), and the timeout caps how long it runs, but neither limit stops a well-behaved-looking program from reading files inside the project, which is what it was started to do.

A misconfigured machine can point `ADVISOR_BOOSTER_BIN` at a different PHP script; the allowlist still pins what it may be told to do, but the choice of program is then the operator's. And the allowlist is enforced inside ADVISOR, on the machine that runs it: a compromised ADVISOR install could simply stop calling it.
