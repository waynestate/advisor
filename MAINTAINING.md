# MAINTAINING: advising the advisor

How to build, test, release and keep ADVISOR current. ADVISOR is the stack's MCP server: it gives a developer's coding
agent read-only tools into every stack project (versions against CREED's policy, which packages to use, how deploys
work, the handbook, the parts list). Its advice is only as good as its last briefing, so most of the maintenance is
keeping its bundled content fresh.

## What ADVISOR is and what it depends on

- `waynestate/advisor` on Packagist, with the CLI `bin/advisor` (the MCP server over stdio, `doctor`,
  `content:generate`, `content:verify`, `stack-parts`) and a Laravel provider.
- Runtime dependencies: `mcp/sdk` (pinned), `opis/json-schema`, `symfony/console`, `symfony/process`,
  `composer/semver`, `psr/log`. No other stack package at runtime: the stack's content is bundled in `data/`.
- **`data/` is generated, never hand-edited:** `policy.json` and `handbook/` from CREED, `projects.json` from each
  project repo and `generator/projects.seed.json`, plus each project's README and best practices. `data/manifest.json`
  holds a SHA-256 for every file, and the server refuses to start if one does not match.
- Every tool is read-only and says so (`readOnlyHint`). Nothing here needs a secret.

## Where it lives

Development happens on Wayne State's GitLab. This GitHub repository is a read-only release mirror: `main` and tags are
copied here automatically, and changes, issues and merge requests go to GitLab first. Nobody pushes to GitHub
directly.

## Build and test

| Task | Command |
|---|---|
| Install | `composer install` |
| All checks (what CI runs) | `composer ci` |
| Regenerate `data/` from the stack repos | `php bin/advisor content:generate --sources=<folder holding the stack repos>` |
| Check `data/` against the repos | add `--check` to the line above (exit 1 on drift) |
| Check `data/` against its manifest | `composer content:verify` |
| Regenerate the parts table in the README | `php bin/advisor stack-parts --write=README.md` |
| Diagnose a client setup | `php vendor/bin/advisor doctor` (in an app) |

`--sources` can also come from `ADVISOR_STACK_DIR`. Snapshot tests are updated with `ADVISOR_UPDATE_SNAPSHOTS=1`
(`$env:ADVISOR_UPDATE_SNAPSHOTS = "1"` in PowerShell) before `vendor/bin/phpunit`.

With only Docker:

```
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 sh -c "composer install --no-interaction && composer ci"
```

`composer ci` runs `composer validate --strict`, `content:verify`, `stack-parts --check=README.md`, PHPUnit, Pint,
PHPStan and `composer audit`. CI cannot see the sibling repos, so it verifies the manifest; regenerating is a local
step.

## CI

- **GitLab:** Preflight's `php-test` (PHP 8.3, 8.4, 8.5) and `composer-audit` components, plus a `composer-ci` job.
- **GitHub:** `.github/workflows/ci.yml` calls Preflight's reusable workflow with `composer ci`.
- Green means `composer ci` passed on all three PHP versions on both.

## Keeping the content fresh

Run `content:generate` after any change that lands in CREED's policy or handbook, a project's README or best
practices, or the projects seed. Commit the regenerated `data/` with the source commits in the message. To add a
project to the parts list, add its `name` and `tagline` to `generator/projects.seed.json`, then regenerate and
`stack-parts --write`.

## Releasing

ADVISOR is public, so its bundle must not carry internal names. The public-posture test always checks generic shapes;
private strings are loaded from a denylist file named by `ADVISOR_PRIVATE_DENYLIST`, kept outside the repository (in
CI, a masked file-type variable). See "Releasing" in the README.

1. Move the lines under `## [Unreleased]` in `CHANGELOG.md` (Keep a Changelog) to a new `## [X.Y.Z] - YYYY-MM-DD`
   heading.
2. With the denylist file present, regenerate: `php bin/advisor content:generate --sources=<stack folder>`, then
   `composer ci` (the public-posture test must pass).
3. Both pipelines green for that commit. Run a secrets scan of the history.
4. Tag `X.Y.Z` (no `v`) on GitLab. The mirror carries the tag to GitHub, and Packagist picks it up from there.
5. A release needs a maintainer's approval. Never move or delete a published tag.

## Things that trip people up

- Native Windows PHP with the MCP SDK over stdio is not verified yet; CI runs on Linux.
- A client's `.mcp.json` starts a program on the developer's machine, so it is never committed.

## The front page

`site/` is the static front page, for `https://advisor.apps.wayne.edu/` on Citadel, served through a small raw-PHP
`deploy/` layer generated with Squire.

## Who maintains it

Adam Perkins, Wayne State University Enterprise Applications. Internal runbook (accounts, tokens, mirrors, the
release denylist): ask Enterprise Applications.
