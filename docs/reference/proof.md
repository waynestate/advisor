---
title: Proof
description: How ADVISOR is checked: composer ci and what each check proves.
---

# Proof

This page walks through `composer ci`, the one command that proves the build, and what each step of it proves.

The same command runs on GitLab and GitHub.

## Last run

| Check | Date | Commit | Result | Duration |
| --- | --- | --- | --- | --- |
| `composer validate --strict` | 2026-11-09 | 7818dae | pass: `./composer.json is valid` | 1 s |
| `content:verify` | 2026-11-09 | 7818dae | pass: `data/ is sound.` | under 1 s |
| `stack-parts --check=README.md` | 2026-11-09 | 7818dae | pass: the stack-parts block is up to date in 1 file | under 1 s |
| `phpunit` | 2026-11-09 | 7818dae | pass: 282 tests, 1535 assertions, 1 skipped | 56 s |
| `pint --test` | 2026-11-09 | 7818dae | pass: 111 files | 1 s |
| `site:agent-ready-check` | 2026-11-09 | 7818dae | pass: 31 file(s) current | under 1 s |
| `phpstan analyse` | 2026-11-09 | 7818dae | pass: no errors | 2 s |
| `composer audit` | 2026-11-09 | 7818dae | pass: no security advisories | 1 s |
| `docs:check` | 2026-11-09 | 7818dae | pass: 26 page(s) current | under 1 s |

Commit 7818dae is the 0.2.0 release. The skipped test is the private half of `PublicPostureTest`, which runs only when `ADVISOR_PRIVATE_DENYLIST` is set. To refresh this table, re-run `composer ci` (or each command above) on the commit you want to record and rewrite the date, commit, results and durations. Every check this page names was run on 2026-11-09 in Docker (`composer:2.11.1`, PHP 8.5) on a copy of the tracked files; none was skipped. The proof run (`bin/proof.php`, see the README) is dev tooling outside `composer ci` and was not run: it needs a corpus of app folders and the PROCTOR and Booster binaries.

## The command

```
composer ci
```

No PHP on your machine? Use Docker:

```
docker run --rm -v "${PWD}:/app" -v stack-composer-cache:/tmp/cache -w /app composer:2.11.1@sha256:8de2184c6de224f4689e8f80ed5f5edaf0030b6b8b7839a22a2bcb6722ac87d0 sh -c "composer install --no-interaction && composer ci"
```

## composer validate --strict

Runs `composer validate --strict` on the package metadata.

## content:verify

Runs `php bin/advisor content:verify`, which checks the bundled content against its manifest. CI cannot see the sibling repositories, so `composer ci` runs `content:verify`, which needs none of them.

## stack-parts --check=README.md

Runs `php bin/advisor stack-parts --check=README.md`, which proves the parts table in the README still matches `generator/projects.seed.json`.

## PHPUnit

Runs `phpunit`. Tests drive the real SDK server in-process with a small MCP client, snapshot `tools/list`, and start `bin/advisor mcp` as a subprocess to prove stdout stays clean.

> [!TIP]
> To accept an intended change to the tool list, run `ADVISOR_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit`, then review and commit the diff.

## Pint

Runs `pint --test`, the style check.

## site:agent-ready-check

Fails when the agent-ready files are stale, a page lacks its Markdown link, a prompt drifts from the docs, or the kit was edited here.

## PHPStan

Runs `phpstan analyse --memory-limit=1G`: level 8, Pinstripe's raw preset, included from `vendor/waynestate/pinstripe`.

## composer audit

Runs `composer audit`.

## docs:check

Fails when `site/docs/` is stale or a docs page has a broken link, block or nav entry.

## Related

- [Best practices](best-practices.md)
- [Commands](commands.md)
- [Releasing](../deploying/releasing.md)
- [Changelog](../changelog.md)
