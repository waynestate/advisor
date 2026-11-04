---
title: "Contributing | ADVISOR docs"
description: "How to work on ADVISOR."
url: https://advisor.apps.wayne.edu/docs/contributing.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Contributing[#](https://advisor.apps.wayne.edu/docs/contributing.html#contributing)

This page tells you how to make a change to ADVISOR, what rules the change must keep, and what work is still open.

## Where changes go[#](https://advisor.apps.wayne.edu/docs/contributing.html#where-changes-go)

Development happens on nougat, the canonical repository: enterprise-apps/advisor. This GitHub repository and git.wayne.edu are read-only mirrors: `main` and tags are copied automatically. Changes, issues and merge requests go to nougat. Nobody pushes to the mirrors.

## Build and test[#](https://advisor.apps.wayne.edu/docs/contributing.html#build-and-test)

| Task | Command |
| --- | --- |
| Install | `composer install` |
| All checks (what CI runs) | `composer ci` |
| Regenerate `data/` from the stack repos | `php bin/advisor content:generate --sources=<folder holding the stack repos>` |
| Check `data/` against the repos | add `--check` to the line above (exit 1 on drift) |
| Check `data/` against its manifest | `composer content:verify` |
| Regenerate the parts table in the README | `php bin/advisor stack-parts --write=README.md` |
| Diagnose a client setup | `php vendor/bin/advisor doctor` (in an app) |

`composer ci` runs `composer validate --strict`, `content:verify`, `stack-parts --check=README.md`, PHPUnit, Pint, PHPStan and `composer audit`. CI cannot see the sibling repos, so it verifies the manifest; regenerating is a local step.

With only Docker:

```
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 sh -c "composer install --no-interaction && composer ci"
```

Snapshot tests are updated with `ADVISOR_UPDATE_SNAPSHOTS=1` (`$env:ADVISOR_UPDATE_SNAPSHOTS = "1"` in PowerShell) before `vendor/bin/phpunit`.

## Rules a change must keep[#](https://advisor.apps.wayne.edu/docs/contributing.html#rules-a-change-must-keep)

Every change to ADVISOR must keep the following practices:

1. **Read-only first.** Every tool states all four hints (read-only, destructive, idempotent, open-world). A tool that writes is left out unless `ADVISOR_READ_ONLY` is turned off, and that switch beats tool groups. Enforced (contract test).
2. **Narrow tools, closed schemas.** No tool takes a command, a URL or a free path. Schemas set `additionalProperties: false`, and arguments are validated again on our side. Enforced (contract test).
3. **Run other programs the safe way.** Fixed program, argument array, allowlisted environment, a timeout, and no shell. Paths are resolved and must stay inside the project root. Enforced.
4. **Keep stdout clean.** Only protocol lines go to stdout; logs, warnings and stray output go to stderr. Enforced (subprocess test).
5. **Strip invisible characters.** The TAG block, zero-width and bidirectional controls are removed from everything served and rejected in tool metadata. Enforced (contract test).
6. **Load modules from a manifest only,** from the `Waynestate\` namespace, implementing `Module`. Never scan. Enforced.
7. **Serve from verified data.** Bundled content is checked against a SHA-256 manifest at start-up and in CI; a mismatch stops the server. Enforced.
8. **Review any change to the tool list.** `tools/list` is snapshotted; a changed description is a reviewed diff. Enforced (snapshot test).
9. **Cite sources and date.** Policy answers carry the day they were worked out for and the file they came from. Enforced.

## Keeping the content fresh[#](https://advisor.apps.wayne.edu/docs/contributing.html#keeping-the-content-fresh)

Run `content:generate` after any change that lands in CREED's policy or handbook, a project's README or best practices, an extra doc, or the projects seed. Commit the regenerated `data/` with the source commits in the message. To add a project to the parts list, add its `name` and `tagline` to `generator/projects.seed.json`, then regenerate and `stack-parts --write`.

Beyond a project's README and best practices, a seed may bundle further files under `extra_docs` (a map of slug to path in the project); each lands in `data/docs/<id>/<slug>.md` and in the project's docs list, and a missing file is skipped, not an error. WayneUI's entry bundles its MIGRATING.md as the `migrating` doc.

Hand-written guides live in `generator/guides/`, one Markdown file per slug, served as `advisor://guide/<slug>`. Edit them there, then run `content:generate`.

## Catch stale bundled docs[#](https://advisor.apps.wayne.edu/docs/contributing.html#catch-stale-bundled-docs)

`composer content:drift` compares `data/` with a checkout of the stack repos and exits 1 when they no longer agree. It takes the folder from `ADVISOR_STACK_DIR`; without it, it prints how to run it and exits 1:

```
ADVISOR_STACK_DIR=<folder holding the stack repos> composer content:drift
```

It writes nothing. On drift it lists the files that moved; regenerate with `composer content:generate` and review the diff before committing.

Run the drift check before every tag: a release must carry docs that still match the repos it advises on.

## Releasing[#](https://advisor.apps.wayne.edu/docs/contributing.html#releasing)

ADVISOR is public, so its bundle must not carry internal names. The public-posture test always checks generic shapes; private strings are loaded from a denylist file named by `ADVISOR_PRIVATE_DENYLIST`, kept outside the repository (in CI, a masked file-type variable).

Warning

The denylist file is never committed. The bundle is regenerated with the file present before every release.

1. Move the lines under `## [Unreleased]` in `CHANGELOG.md` (Keep a Changelog) to a new `## [X.Y.Z] - YYYY-MM-DD` heading.
2. With the denylist file present, regenerate: `php bin/advisor content:generate --sources=<stack folder>`, then `composer ci` (the public-posture test must pass).
3. Both pipelines green for that commit. Run a secrets scan of the history.
4. Tag `X.Y.Z` (no `v`) on nougat. The mirror carries the tag to GitHub, and Packagist picks it up from there.
5. A release needs a maintainer's approval. Never move or delete a published tag.

## Open work[#](https://advisor.apps.wayne.edu/docs/contributing.html#open-work)

The following items are open questions or not yet built:

- Testing on a native Windows PHP (the SDK has no Windows CI), and the MCP Inspector in CI.
- Signing of any state handed back to a client (no tool needs it in 0.1).
- A threat model mapped to OWASP and NIST; a full-capture audit mode.
- "Ask ADVISOR" web chat: needs a model, an open stack question and an accessibility pass of its own.
- HTTP transport and OAuth: stdio first; remote needs a threat model.
- Tools that write (scaffold with Sourdough, apply a Booster step): the approved list is read-only; the read-only switch and tool groups are built so they can arrive safely.
- Per-project modules in their own repositories: the loader and `Module` interface exist and are tested; the other projects have not published modules.
- WarriorGPT-backed features: no model calls until an open stack question is settled.
- Plan handles, `requestState` signing and elicitation: no tool needs state or a question to the user yet.
- Full-capture audit mode: hashes only by default; an opt-in needs a retention decision.
- MCP Inspector in CI, native Windows and macOS CI.

## Related[#](https://advisor.apps.wayne.edu/docs/contributing.html#related)

- [Upgrading](https://advisor.apps.wayne.edu/docs/upgrading.html)
- [Releasing](https://advisor.apps.wayne.edu/docs/deploying/releasing.html)
- [Best practices](https://advisor.apps.wayne.edu/docs/reference/best-practices.html)
- [Commands](https://advisor.apps.wayne.edu/docs/reference/commands.html)
