---
title: "Path traversal | ADVISOR docs"
description: "A path argument reaching files outside the project, and the guard that confines it."
url: https://advisor.apps.wayne.edu/docs/threats/path-traversal.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Path traversal[#](https://advisor.apps.wayne.edu/docs/threats/path-traversal.html#path-traversal)

An attacker who controls a tool's path argument wants to read files outside the project: private keys, credentials, neighboring projects, or enough of the disk layout to plan a next step.

## Where it applies in ADVISOR[#](https://advisor.apps.wayne.edu/docs/threats/path-traversal.html#where-it-applies-in-advisor)

Two tools take a `path` argument, and both hand the resolved folder to a child program:

- `run_proctor_check` runs the app's own PROCTOR on the given folder; the schema says the path must be inside the project root (`src/Tools/RunProctorCheck.php:30`).
- `upgrade_path` runs Booster's read-only `plan` on the given folder, with the same constraint (`src/Tools/UpgradePath.php:31`).

Both default to the project root itself, so even a call with no arguments goes through the guard. The guard's root comes from `ADVISOR_PROJECT_ROOT` and is fixed once when the server starts (`src/Server/Runtime.php:44`); nothing a model sends can move it.

The schema caps the argument at 1024 characters, the same limit the guard enforces (`src/Tools/RunProctorCheck.php:30`, `src/Tools/UpgradePath.php:31`).

PROCTOR and BOOSTER read whatever sits under the folder they are given, so that folder is the only boundary between their file access and the rest of the machine. The other tools take no path at all: `read_project_docs` takes a project id and a document name from closed lists (`src/Tools/ReadProjectDocs.php:27-28`), and the rest take no file input.

## What stops it[#](https://advisor.apps.wayne.edu/docs/threats/path-traversal.html#what-stops-it)

- **Odd input is refused before resolution.** Empty paths, paths over 1024 characters and null bytes are rejected (`src/Security/PathGuard.php:40-42`), as are paths that start with `~` (`src/Security/PathGuard.php:43-45`).
- **Parent segments are refused outright.** Any segment equal to `..`, under forward or back slashes, ends the call (`src/Security/PathGuard.php:46-50`).
- **Resolution follows symlinks, then checks containment.** The path is resolved to its real location and must sit inside the resolved root (`src/Security/PathGuard.php:52-57`), so a symlink inside the project that points out of it fails the same way `../../` does.
- **Missing and outside get the same error**, so a refusal does not map the disk for the attacker (`src/Security/PathGuard.php:54-56`).
- **Prefix tricks fail.** Containment compares against the root plus a separator, so a sibling folder whose name merely starts with the root's is refused (`src/Security/PathGuard.php:70`), and on Windows the comparison is case-insensitive (`src/Security/PathGuard.php:65-68`).
- **Drive-letter roots are handled.** Absolute-path detection covers `/`, `\` and `C:` style roots, so a Windows absolute path cannot slip past the prefix check (`src/Security/PathGuard.php:83-86`).
- **The root itself is validated.** The constructor refuses a root that is not a directory, so the guard cannot be pointed at a file (`src/Security/PathGuard.php:21-28`).
- **Output never shows the host layout.** Paths in results are rendered relative to the project root (`src/Security/PathGuard.php:73-80`, used at `src/Security/ProctorRunner.php:127` and `src/Security/BoosterRunner.php:140`).

The tests prove each refusal: hostile paths covering `..` segments, nested and backslash parents, `~`, null bytes, absolute paths elsewhere, missing files and overlong strings (`tests/Security/PathGuardTest.php:43-63`); a same-prefix sibling folder (`tests/Security/PathGuardTest.php:65-72`); a symlink that leaves the project (`tests/Security/PathGuardTest.php:74-82`); and errors that never show the host layout (`tests/Security/PathGuardTest.php:84-92`). The contract test adds that no tool may be named like a shell command or take a free-form command, cmd, script, code, sql or url argument at all (`tests/Contract/ServerContractTest.php:59-68`).

The same two tools are the only ones that start a process; see [Command injection](https://advisor.apps.wayne.edu/docs/threats/command-injection.html) for how their argument lists are pinned.

## What is left[#](https://advisor.apps.wayne.edu/docs/threats/path-traversal.html#what-is-left)

Confinement is to the configured project root, and everything inside that root is readable by these tools by design, since they exist to inspect the app. The guard checks the path once per call; a symlink swapped in after the check, before PROCTOR or BOOSTER reads the file, is not caught, and a path that moves out later (a bind mount, a moved volume) is outside the guard's view.

The guard also trusts the root it is given (`src/Security/PathGuard.php:21-28`): pointing `ADVISOR_PROJECT_ROOT` at a wide folder is an operator decision, and the guard will faithfully confine to it. Because the child programs run with normal file access inside the root, the guard decides where they may look, not what they may do with what they find.
