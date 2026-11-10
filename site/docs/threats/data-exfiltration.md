---
title: "Data exfiltration | ADVISOR docs"
description: "Secrets leaving the machine through tool answers, and the controls that block the channels."
url: https://advisor.apps.wayne.edu/docs/threats/data-exfiltration.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Data exfiltration [#](https://advisor.apps.wayne.edu/docs/threats/data-exfiltration.html#data-exfiltration)

An attacker wants a secret (an environment variable, a token, a key, a file's contents) to leave the machine and land somewhere the model, a log, or a report can carry it out.

## Where it applies in ADVISOR [#](https://advisor.apps.wayne.edu/docs/threats/data-exfiltration.html#where-it-applies-in-advisor)

Three channels could carry a secret out:

- The child processes behind `run_proctor_check` and `upgrade_path` inherit the server's environment, and whatever they print becomes a tool result (`src/Security/SymfonyProcessRunner.php:21-30`).
- Finding messages and plan warnings quote the project's own files (`src/Security/ProctorRunner.php:116`, `src/Security/BoosterRunner.php:209`).
- The optional audit log records every tool call (`src/Server/AuditLog.php:20-35`).

ADVISOR itself makes no network calls, so "out" means into the model's context or onto disk in a log; the client then decides what happens to that text (`docs/guides/safety.md:19`). A fourth, quieter channel is the tool descriptions and prompts the client reads before any call; they ship in the repo and are scanned for hidden characters (`tests/Contract/ServerContractTest.php:70-83`).

## What stops it [#](https://advisor.apps.wayne.edu/docs/threats/data-exfiltration.html#what-stops-it)

- **Child processes get an allowlisted environment.** Only the names in `EnvAllowlist::NAMES` survive; everything else, including the app's `.env` values, tokens and cloud keys, is removed before the process starts (`src/Security/EnvAllowlist.php:18-24`, applied at `src/Security/SymfonyProcessRunner.php:30`).
- **Secret-shaped text is redacted.** Every string that came from a child process or a project file passes through the redactor, which blanks credential-shaped `key=value` pairs, Bearer tokens, PEM private-key blocks and well-known token shapes (`src/Security/Redactor.php:16-26`, `src/Security/Redactor.php:28-41`; applied at `src/Security/ProctorRunner.php:82`, `src/Security/ProctorRunner.php:116`, `src/Security/ProctorRunner.php:142` and `src/Security/BoosterRunner.php:80`, `src/Security/BoosterRunner.php:209`).
- **Paths in results are project-relative**, so a report does not reveal where the project lives on the host (`src/Security/ProctorRunner.php:127`, `src/Security/BoosterRunner.php:140`), and the policy file is described rather than located (`src/Security/BoosterRunner.php:172-174`).
- **The audit log stores a hash of the arguments, never the arguments** (`src/Server/AuditLog.php:28`).
- **`playbook_lookup` refuses secret-looking keys.** A key ending in SECRET, PASSWORD, TOKEN or KEY is refused before any source is consulted (`src/Playbook/Settings.php:17`, `src/Playbook/Settings.php:51-55`).
- **Output is capped.** Child output beyond 2 MB is dropped before it is read, so a chatty process cannot flood the context with data (`src/Security/SymfonyProcessRunner.php:17`, `src/Security/SymfonyProcessRunner.php:39-43`).
- **The server holds no secrets of its own.** ADVISOR needs no API keys; when the optional Playbook package is present it is the only holder of shared values, and the lookup tool is the only door to it.

The tests prove the first two: `APP_KEY`, `DB_PASSWORD`, `AWS_SECRET_ACCESS_KEY` and `COMPOSER_AUTH` do not survive into a child environment while `PATH` does (`tests/Security/EnvAndRedactionTest.php:13-30`), and credential shapes plus a private-key block are blanked while plain words stay (`tests/Security/EnvAndRedactionTest.php:32-52`). The contract test keeps private-key blocks and token-shaped strings out of the repository text itself (`tests/Contract/ServerContractTest.php:101-110`).

A reviewer can check the channels by reading `tests/Security/EnvAndRedactionTest.php` and the audit-log writer, and by running `composer test`.

## What is left [#](https://advisor.apps.wayne.edu/docs/threats/data-exfiltration.html#what-is-left)

The redactor is pattern-based and blunt on purpose: a false positive costs a word, a miss costs a key (`src/Security/Redactor.php:7-11`). The patterns cover the shapes developers actually leak; they are not a general-purpose secret scanner. A secret that matches no known shape, such as an unusual variable name or a quoted value with no key, passes through.

Files inside the project root remain readable by the child programs and quotable in their output; confinement decides where, not what. The environment allowlist is a whitelist of names, not values: an allowed name such as `PATH` is passed through untouched.

Once a value reaches the model's context, the client's own logging, retention and network behavior decide the rest, which is outside ADVISOR's control. The audit log helps a developer spot an odd call after the fact, but it hashes the arguments rather than hiding them from the model, which already saw them.
