# Changelog

All notable changes are listed here. The format follows Keep a Changelog; versions follow Semantic Versioning.

## [Unreleased]

### Added

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
