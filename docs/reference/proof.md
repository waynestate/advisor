---
title: Proof
description: How ADVISOR is checked: composer ci and what each check proves.
---

# Proof

This page walks through `composer ci`, the one command that proves the build, and what each step of it proves.

The same command runs on GitLab and GitHub.

## The command

```
composer ci
```

No PHP on your machine? Use Docker:

```
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 sh -c "composer install --no-interaction && composer ci"
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
