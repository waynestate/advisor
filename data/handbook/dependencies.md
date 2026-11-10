# Dependencies

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- Runtime dependencies MUST NOT use `dev-master`, `dev-*` or `*`
  constraints (the stack's version policy). PROCTOR check:
  `unbounded-constraints`.
- A lock file is required and MUST be committed (the stack's version
  policy). PROCTOR checks: `lock-committed` (the lock is on disk and in sync)
  and `lock-tracked` (git tracks it; a warning).
- `composer audit` MUST report no advisories at or above the policy's severity
  threshold (the stack's version policy). PROCTOR check: `composer-audit`.
- The policy's `audit_severity_threshold` uses Composer's words. npm's
  `audit-level` reads them as: `low` is `low`, `medium` is `moderate`, `high`
  is `high`, `critical` is `critical` (npm's `info` has no Composer word).
  A tool that reads the threshold for npm MUST apply this map (the stack's
  version policy). Held by: Preflight's `npm-audit` component (`medium`
  means `moderate`).

CREED's `abandoned_packages` rule is `fail`, matching Composer's default since
2.7, so `composer audit` fails on an abandoned package. An app that has to keep
one MUST replace it or record an override with a reason; PROCTOR's
`composer-audit` check applies the same setting. PROCTOR checks:
`composer-audit` (fails on the abandoned package) and `overrides` (validates
the recorded one).

- `composer.json` `require.php` MUST NOT allow an EOL version as its lowest
  bound (the stack's version policy). PROCTOR check: `php-floor`.
- Apps SHOULD set a Composer cooldown of 3 days (`config.policy.cooldown.period`,
  Composer 2.11; the stack's dependency decision, 2026-11-03) and exempt the
  stack's own packages (`"ignore": ["waynestate/*"]`), which the stack's CI tests
  before tagging. Sourdough's shapes set both. PROCTOR check:
  `composer-cooldown` (a warning, never a failure).
- Every project MUST be a framework-free core plus thin adapters, and its
  `composer.json` MUST require only `php` and PSR packages; Laravel, Inertia
  and Vue are `suggest` (the stack's core-plus-adapters design). No check
  exists yet.
- Adapters MUST call the core and MUST NOT re-implement it (the stack's core-plus-adapters design). No check exists yet.
- Before adding a third-party package, check it comes from a trusted source, and where the source is open, read enough of it to judge it sound and fit (SHOULD). The audit and abandoned-package rules in this chapter apply after that choice (WSU developer standards, 2024). No check exists yet.

## Private packages

- Internal-only packages MUST be installed from one GitLab package registry
  per ecosystem (Composer, npm), not from one `vcs`, `path` or `artifact`
  repository entry per package (the stack's private-packages decision).
  No check exists yet.
- Credentials MUST NOT appear in any repository URL in `composer.json` or
  `composer.lock`, or as a literal value in a committed `.npmrc`. Tokens live
  in a gitignored `auth.json`, the npm user config, or a CI or build-time
  secret (the stack's private-packages decision). PROCTOR check:
  `repo-url-credentials`.
- Builds MUST use a read-only deploy token (`read_package_registry` only);
  personal access tokens MUST NOT be used in builds (the stack's
  private-packages decision). No check exists yet.
- The private Composer registry entry MUST be canonical, and the Packagist
  entry SHOULD exclude the internal vendor names, so a public package with
  the same name can never be installed instead (the stack's private-packages
  decision). No check exists yet.
- A released version MUST map to one commit for ever: tags are protected,
  and a version is never republished from a different commit (the stack's
  private-packages decision). Held by: Preflight's `package-publish`
  component, which reads the registry first and refuses a version that
  already exists with different contents.
- Public code MUST NOT go through the private registry; it is published to
  Packagist or npm (the stack's private-packages decision). No check exists
  yet.
- Apps SHOULD NOT proxy or mirror public registries in v1 (the stack's
  private-packages decision). No check exists yet.

Today PROCTOR's `repo-url-credentials` check holds rule 2 and Preflight's
`package-publish` component holds rule 5. Checks for rule 1 (one registry
per ecosystem) and rule 4 (the canonical entry and the Packagist exclusion)
are planned, not built: earlier versions of this chapter named them as
`package-sources` and `internal-vendor-shadowing`, but PROCTOR has never
shipped either.
