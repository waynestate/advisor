---
title: "Upgrading | ADVISOR docs"
description: "How to upgrade ADVISOR, version by version."
url: https://advisor.apps.wayne.edu/docs/upgrading.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Upgrading[#](https://advisor.apps.wayne.edu/docs/upgrading.html#upgrading)

This page walks through each released version of ADVISOR and what changed, so you can see whether a new version affects your app.

## \[0.1.6\] - 2026-11-06[#](https://advisor.apps.wayne.edu/docs/upgrading.html#0-1-6-2026-11-06)

No breaking changes are marked for this release. Update with `composer update waynestate/advisor`; the MCP server reports version 0.1.6.

- symfony/console and symfony/process accept ^8.0 as well as ^7.4, so apps on Pest 5 / PHPUnit 13 can install ADVISOR.
- The bundled data is resynced with the stack's main branches through the evening of 2026-11-06, including Booster 1.9.0, WELD 0.1.3, the accessibility gates across the stack and the Sourdough raw PHP + Vue shape.
- A docs site built from `docs/` is added and checked in `composer ci`, and the front page and docs pass an accessibility gate. The proof runner is dev tooling and is not part of the served product.

## \[0.1.5\] - 2026-11-03[#](https://advisor.apps.wayne.edu/docs/upgrading.html#0-1-5-2026-11-03)

No breaking changes are marked for this release.

- The deploy layer requires HELMET ^0.3 and PROCTOR ^0.4 (locked at 0.3.0 and 0.4.0) and sets minimum-stability to stable, which clears PROCTOR 0.4's new minimum-stability warning. HELMET 0.3 reads an empty `HELMET_PROXIES=` as trust nobody; the front page sets only the `citadel` preset, so nothing changes for it.
- The deploy layer's Squire kit is refreshed to 0.5.0 (updated `base-fallback.Dockerfile`, `scripts/release.sh` and `scripts/parity.ps1`).
- The bundled data is resynced with the 2026-11-03 releases: Booster 1.8.0, Preflight 0.1.4, PROCTOR 0.4.0, Pinstripe 0.1.2, CREED 0.1.6, Rollcall 0.1.9, HELMET 0.3.0, SESAME 0.4.0, Drawbridge 0.1.1, Sourdough 0.1.5, Squire 0.5.0 and WELD 0.1.2, and with each project's main since the last pass.
- One Playbook settings resolver (`Waynestate\Advisor\Playbook\Settings`, ADR-0018) is added. `playbook_lookup` now answers from `.env.playbook` when the package is not installed (same output shape).
- `.env.playbook` is git-ignored; the agent-ready files (`site/llms.txt`, `site/llms-full.txt`) are regenerated.
- Docs and package metadata name nougat as the canonical repository, with git.wayne.edu and GitHub as read-only mirrors, until git.wayne.edu has CI runners.

## \[0.1.4\] - 2026-11-03[#](https://advisor.apps.wayne.edu/docs/upgrading.html#0-1-4-2026-11-03)

No breaking changes are marked for this release.

- CI pulls its Docker Hub images through the GitLab Dependency Proxy (the group's CI_DEPENDENCY_PROXY_GROUP_IMAGE_PREFIX), so pipelines no longer hit Docker Hub's anonymous pull limit.

## \[0.1.3\] - 2026-11-02[#](https://advisor.apps.wayne.edu/docs/upgrading.html#0-1-3-2026-11-02)

No breaking changes are marked for this release.

- Hand-written guides are bundled from `generator/guides/` and served as `advisor://guide/<slug>`; the first guide is "Connect to Oracle on Citadel".
- `policy_lookup` returns `latest_patch`; the bundled docs drop hero images, badges and GIFs.
- WayneUI's migration guide (MIGRATING.md) is bundled as the `migrating` doc, and every project entry carries its version when it publishes one (composer.json, else package.json, else the newest CHANGELOG heading).
- A one-line `claude mcp add` and a Docker `.mcp.json` for machines without PHP are documented.
- `composer content:drift` and an optional CI job fail when `data/` no longer matches the sibling repos.
- The deploy layer's Squire kit is refreshed to 0.3.4 (the local `.env.squire` file is now git-ignored).

## \[0.1.2\] - 2026-10-29[#](https://advisor.apps.wayne.edu/docs/upgrading.html#0-1-2-2026-10-29)

No breaking changes are marked for this release.

- An agent-ready site (prompt blocks, Markdown twins, llms.txt) is added from Squire's kit.
- The `playbook_lookup` tool answers "what is the value of X?" for WSU shared values. An environment variable of the same name wins, then the private `waynestate/playbook` package (Playbook) supplies the rest; without it the tool says how to get it instead of failing. Keys that look like secrets (ending in SECRET, PASSWORD, TOKEN or KEY) are refused. `waynestate/playbook` is suggested, never required.
- Docs copies are resynced; proof results now live on the proof site.
- Project names are written as words except acronyms.
- Stale capability claims in the site and docs are fixed.

## \[0.1.1\] - 2026-10-29[#](https://advisor.apps.wayne.edu/docs/upgrading.html#0-1-1-2026-10-29)

No breaking changes are marked for this release.

- The `owner_of` tool answers "which project owns X?" from a new bundled `data/ownership.json` table (sourced from `generator/ownership.json`, verified by `content:verify`). Fuzzy match: every word of the topic must appear in a row's topic or role; a no-match answer lists every known topic.
- `upgrade_path` moves from the `stack` group to a new `booster` group, so `ADVISOR_GROUPS=stack` offers reference tools only and never starts a program. `ADVISOR_GROUPS` now accepts `stack`, `proctor` and `booster`.
- PHPStan includes Pinstripe's `raw.neon` preset (`waynestate/pinstripe` 0.1.0 as a dev dependency) instead of a hand copy of its settings; two docblocks were tightened to satisfy the preset's method-signature checks. Pint already used the `per` preset, so `pint.json` is unchanged.

## \[0.1.0\] - 2026-10-29[#](https://advisor.apps.wayne.edu/docs/upgrading.html#0-1-0-2026-10-29)

This is the first release, so there is nothing to break.

- Read-only MCP server over stdio (`php vendor/bin/advisor mcp`) on the official `mcp/sdk` 0.9.0, speaking both the legacy `initialize` handshake and the 2026-07-28 per-request form.
- Seven tools: `list_projects`, `get_project_status`, `read_project_docs`, `policy_lookup`, `list_stack_rules`, `run_proctor_check`, `upgrade_path`. Resources `advisor://policy` and `advisor://creed/<chapter>`. Prompts `orient_me` and `plan_upgrade`.
- `upgrade_path` plans the app's PHP and/or Laravel upgrade, one supported hop at a time, by running Booster's read-only `plan` (never `run`) against the bundled policy. New settings `ADVISOR_BOOSTER_TIMEOUT` (default 60) and `ADVISOR_BOOSTER_BIN` (fallback when the app has no `vendor/bin/booster` of its own).
- Bundled, hash-verified content (CREED handbook and policy, project docs, the WayneUI markup contract) with a generator, `content:generate --check` and `content:verify`.
- `advisor install` for Claude, WarriorGPT, Cursor, VS Code (written) and Codex (printed).
- Laravel adapter on `laravel/mcp` (`php artisan mcp:start advisor`), so ADVISOR runs beside Laravel Boost.
- Safety: off unless enabled, refuses production, read-only by default, path confinement, allowlisted child environment, redaction, invisible-character stripping, manifest-only module loading, argument-hash audit log.
- `composer ci`: validate, content verify, PHPUnit, Pint, PHPStan level 8 (Pinstripe settings), audit. CI files for GitLab (Preflight 0.1.0) and GitHub.

## Taking a new version[#](https://advisor.apps.wayne.edu/docs/upgrading.html#taking-a-new-version)

ADVISOR is on Packagist: `composer require --dev waynestate/advisor:^0.1`. No extra repository needed.

The command is then `php vendor/bin/advisor`. This works the same on Windows, macOS and Linux, because it is one PHP command and not a shell script.

## Related[#](https://advisor.apps.wayne.edu/docs/upgrading.html#related)

- [Install](https://advisor.apps.wayne.edu/docs/install.html)
- [Changelog](https://advisor.apps.wayne.edu/docs/changelog.html)
- [Releasing](https://advisor.apps.wayne.edu/docs/deploying/releasing.html)
- [Refreshing the content](https://advisor.apps.wayne.edu/docs/deploying/refreshing-the-content.html)
