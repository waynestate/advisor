# Changelog

All notable changes are listed here. The format follows Keep a Changelog; versions follow Semantic Versioning.

## [Unreleased]

First build, aimed at 0.1.0.

### Added

- Read-only MCP server over stdio (`php vendor/bin/advisor mcp`) on the official `mcp/sdk` 0.9.0, speaking both the
  legacy `initialize` handshake and the 2026-07-28 per-request form.
- Six tools: `list_projects`, `get_project_status`, `read_project_docs`, `policy_lookup`, `list_stack_rules`,
  `run_proctor_check`. Resources `advisor://policy` and `advisor://creed/<chapter>`. Prompts `orient_me` and
  `plan_upgrade`.
- Bundled, hash-verified content (CREED handbook and policy, project docs, the WayneUI markup contract) with a
  generator, `content:generate --check` and `content:verify`.
- `advisor install` for Claude, WarriorGPT, Cursor, VS Code (written) and Codex (printed).
- Laravel adapter on `laravel/mcp` (`php artisan mcp:start advisor`), so ADVISOR runs beside Laravel Boost.
- Safety: off unless enabled, refuses production, read-only by default, path confinement, allowlisted child
  environment, redaction, invisible-character stripping, manifest-only module loading, argument-hash audit log.
- `composer ci`: validate, content verify, PHPUnit, Pint, PHPStan level 8 (Pinstripe settings), audit. CI files for
  GitLab (PREFLIGHT 0.1.0) and GitHub.
