# Changelog

All notable changes are listed here. The format follows Keep a Changelog; versions follow Semantic Versioning.

## [Unreleased]

### Added

- Added: an accessibility gate (scripts/a11y/check.mjs) that loads every HTML page under site/ in a real browser and fails on any axe-core WCAG violation, in light and dark themes at phone and desktop width, plus a reduced-motion pass; runs as the a11y job in GitLab CI.
- Added: a docs site (site/docs/) built by the shared docs builder from docs/, checked in composer ci.
- Changed: the bundled data is resynced with the stack's main branches of 2026-11-03 (afternoon): SESAME S45 and the DRAWBRIDGE module floor (from branch heads awaiting main), HELMET H42 4xx pages, H43, H53 and H41 notes; SESAME S4, S41, S42 and S27; plus the Booster, Preflight, PROCTOR and Squire docs that moved.
- Changed: the bundled data is resynced again (evening): PROCTOR composer-audit-off, not-audited, scoped overrides and the ignore-entries count; Pinstripe Node 22/24/26 matrix, the Laravel + Vue fixture and ADR-0001; plus the other projects docs that moved on main.
- Changed: the bundled data is resynced with the stack's main branches (late evening of 2026-11-03): Sourdough (Playbook required by default, released HELMET/SESAME/PROCTOR pins in the templates, Preflight pinned to a SHA), Squire (release workflow, docs builder gates), WELD, HELMET 0.3, SESAME 0.4, Preflight, Booster and Drawbridge docs; ADVISOR's own version reads 0.1.5.
- Changed: the bundled data is resynced again (night of 2026-11-03): the Pinstripe and Squire readmes moved; Booster, ROLLCALL and WELD docs were already current.
- Changed: the bundled data is resynced again (late night of 2026-11-03): Sourdough (`composer playbook` script), HELMET, Preflight and Squire readmes moved; WELD docs were already current.

### Fixed

- Fixed: front-page scroll reveals start hidden from first paint (plain CSS under `html.motion`, set in `<head>` before the body parses) instead of fading out after load, so unscrolled below-the-fold content sits at opacity 0 rather than being caught mid-fade; the motion class is only set where IntersectionObserver exists, so a page that hides content can always bring it back.
- Fixed: docs code blocks are keyboard focusable (`tabindex="0"`, the same treatment the table wrappers already had), satisfying the scrollable-region-focusable rule.
- Fixed: the changelog docs page keeps its `# Changelog` heading, so the page has exactly one level-1 heading.
- Fixed: table-of-contents sub-links carry the base `docs-toc__item` class again, so they get the block display and padding that keep them at or above the 24px target size (and their hover color).
## [0.1.5] - 2026-11-03

- Changed: the deploy layer requires HELMET ^0.3 and PROCTOR ^0.4 (locked at 0.3.0 and 0.4.0) and sets minimum-stability to stable, which clears PROCTOR 0.4's new minimum-stability warning. HELMET 0.3 reads an empty `HELMET_PROXIES=` as trust nobody; the front page sets only the `citadel` preset, so nothing changes for it. The front page site owes a Quick Update.
- Changed: the deploy layer's Squire kit is refreshed to 0.5.0 (updated `base-fallback.Dockerfile`, `scripts/release.sh` and `scripts/parity.ps1`). The front page site owes a Quick Update.
- Changed: the bundled data is resynced with the 2026-11-03 releases: Booster 1.8.0, Preflight 0.1.4, PROCTOR 0.4.0, Pinstripe 0.1.2, CREED 0.1.6, ROLLCALL 0.1.9, HELMET 0.3.0, SESAME 0.4.0, Drawbridge 0.1.1, Sourdough 0.1.5, Squire 0.5.0 and WELD 0.1.2 (versions in `projects.json`), and with each project's main since the last pass (PROCTOR's new `composer-config` and `composer-audit` rule ids, Booster's newcomer-friendly install and run notes, Preflight's `job_name` inputs, and updates to the Drawbridge, Pinstripe, ROLLCALL, SESAME, HELMET, Squire and Sourdough docs).
- Added: one Playbook settings resolver (`Waynestate\Advisor\Playbook\Settings`, ADR-0018). A name resolves from the real environment first, then the Playbook package, then a `.env.playbook` file in the project root; secret-looking names are still refused. `playbook_lookup` now uses it, so it answers from `.env.playbook` when the package is not installed (same output shape), and the "how to get it" text now includes the `.env.playbook` download for Linux, macOS and Windows.
- Changed: `.env.playbook` is git-ignored; the agent-ready files (`site/llms.txt`, `site/llms-full.txt`) are regenerated.

- Changed: docs and package metadata name nougat as the canonical repository, with git.wayne.edu and GitHub as read-only mirrors, until git.wayne.edu has CI runners.

## [0.1.4] - 2026-11-03

- Changed: CI pulls its Docker Hub images through the GitLab Dependency Proxy (the group's CI_DEPENDENCY_PROXY_GROUP_IMAGE_PREFIX), so pipelines no longer hit Docker Hub's anonymous pull limit.

## [0.1.3] - 2026-11-02

### Added

- Added: hand-written guides bundled from generator/guides/ and served as advisor://guide/<slug>; first guide: Connect to Oracle on Citadel.
- Added: policy_lookup returns latest_patch; the bundled docs drop hero images, badges and GIFs.
- Added: WayneUI's migration guide (MIGRATING.md) is bundled as the migrating doc, and every project entry carries its version when it publishes one (composer.json, else package.json, else the newest CHANGELOG heading).
- Docs: a one-line `claude mcp add` and a Docker `.mcp.json` for machines without PHP.
- Added: composer content:drift and an optional CI job that fail when data/ no longer matches the sibling repos.

### Changed

- Changed: the deploy layer's Squire kit is refreshed to 0.3.3 (release options can now live in ./.env.squire).
- Changed: the deploy layer's Squire kit is refreshed to 0.3.4 (the local .env.squire file is now git-ignored).

## [0.1.2] - 2026-10-29

### Added

- Added: agent-ready site (prompt blocks, Markdown twins, llms.txt), from Squire's kit.
- `playbook_lookup` tool: answers "what is the value of X?" for WSU shared values. An environment variable of the
  same name wins, then the private `waynestate/playbook` package (Playbook) supplies the rest; without it the tool
  says how to get it instead of failing. Keys that look like secrets (ending in SECRET, PASSWORD, TOKEN or KEY) are
  refused. `waynestate/playbook` is suggested, never required.

### Changed

- Changed: docs copies resynced; proof results now live on proof.apps.wayne.edu.
- Changed: project names written as words except acronyms.

### Fixed

- Fixed: stale capability claims in the site and docs.

## [0.1.1] - 2026-10-29

### Added

- `owner_of` tool: answers "which project owns X?" from a new bundled `data/ownership.json` table (sourced from
  `generator/ownership.json`, verified by `content:verify`). Fuzzy match: every word of the topic must appear in a
  row's topic or role; a no-match answer lists every known topic.

### Changed

- `upgrade_path` moves from the `stack` group to a new `booster` group, so `ADVISOR_GROUPS=stack` offers reference
  tools only and never starts a program. `ADVISOR_GROUPS` now accepts `stack`, `proctor` and `booster`.
- PHPStan includes Pinstripe's `raw.neon` preset (`waynestate/pinstripe` 0.1.0 as a dev dependency) instead of a hand
  copy of its settings; two docblocks were tightened to satisfy the preset's method-signature checks. Pint already
  used the `per` preset, so `pint.json` is unchanged.

## [0.1.0] - 2026-10-29

First build, aimed at 0.1.0.

### Added

- Read-only MCP server over stdio (`php vendor/bin/advisor mcp`) on the official `mcp/sdk` 0.9.0, speaking both the
  legacy `initialize` handshake and the 2026-07-28 per-request form.
- Seven tools: `list_projects`, `get_project_status`, `read_project_docs`, `policy_lookup`, `list_stack_rules`,
  `run_proctor_check`, `upgrade_path`. Resources `advisor://policy` and `advisor://creed/<chapter>`. Prompts
  `orient_me` and `plan_upgrade`.
- `upgrade_path`: plans the app's PHP and/or Laravel upgrade, one supported hop at a time, by running BOOSTER's
  read-only `plan` (never `run`) against the bundled policy. New settings `ADVISOR_BOOSTER_TIMEOUT` (default 60)
  and `ADVISOR_BOOSTER_BIN` (fallback when the app has no `vendor/bin/booster` of its own).
- Bundled, hash-verified content (CREED handbook and policy, project docs, the WayneUI markup contract) with a
  generator, `content:generate --check` and `content:verify`.
- `advisor install` for Claude, WarriorGPT, Cursor, VS Code (written) and Codex (printed).
- Laravel adapter on `laravel/mcp` (`php artisan mcp:start advisor`), so ADVISOR runs beside Laravel Boost.
- Safety: off unless enabled, refuses production, read-only by default, path confinement, allowlisted child
  environment, redaction, invisible-character stripping, manifest-only module loading, argument-hash audit log.
- `composer ci`: validate, content verify, PHPUnit, Pint, PHPStan level 8 (Pinstripe settings), audit. CI files for
  GitLab (PREFLIGHT 0.1.0) and GitHub.
