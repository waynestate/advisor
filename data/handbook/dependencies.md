# Dependencies

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- No `dev-master`, `dev-*` or `*` constraints on runtime dependencies
  (the stack's version policy). PROCTOR check: `unbounded-constraints`.
- A lock file is required and MUST be committed (the stack's version
  policy). PROCTOR check: `lock-committed`.
- `composer audit` MUST report no advisories at or above the policy's severity
  threshold (the stack's version policy). PROCTOR check: `composer-audit`.

CREED's `abandoned_packages` rule is `fail`, matching Composer's default since
2.7, so `composer audit` fails on an abandoned package. An app that has to keep
one replaces it or records an override with a reason; PROCTOR's
`composer-audit` check applies the same setting.

- `composer.json` `require.php` MUST NOT allow an EOL version as its lowest
  bound (the stack's version policy). PROCTOR check: `php-floor`.
- Apps SHOULD set a Composer cooldown of 3 days, once Composer 2.11 is stable
  and in the base images; until then no cooldown is set (the stack's dependency
  decision, 2026-11-03). A PROCTOR check for a missing cooldown is planned, not
  built.
- Every project is a framework-free core plus thin adapters. `composer.json`
  requires only `php` and PSR packages; Laravel, Inertia and Vue are
  `suggest` (the stack's core-plus-adapters design).
- Adapters call the core and never re-implement it (the stack's core-plus-adapters design).
- Before adding a third-party package, check it comes from a trusted source, and where the source is open, read enough of it to judge it sound and fit (SHOULD). The audit and abandoned-package rules in this chapter apply after that choice (WSU developer standards, 2024).

## Private packages

- Internal-only packages MUST be installed from one GitLab package registry
  per ecosystem (Composer, npm), not from one `vcs`, `path` or `artifact`
  repository entry per package (the stack's private-packages decision).
- Credentials MUST NOT appear in any repository URL in `composer.json` or
  `composer.lock`, or as a literal value in a committed `.npmrc`. Tokens live
  in a gitignored `auth.json`, the npm user config, or a CI or build-time
  secret (the stack's private-packages decision). PROCTOR check:
  `repo-url-credentials`.
- Builds MUST use a read-only deploy token (`read_package_registry` only);
  personal access tokens MUST NOT be used in builds (the stack's
  private-packages decision).
- The private Composer registry entry MUST be canonical, and the Packagist
  entry SHOULD exclude the internal vendor names, so a public package with
  the same name can never be installed instead (the stack's private-packages
  decision).
- A released version MUST map to one commit for ever: tags are protected,
  and a version is never republished from a different commit (the stack's
  private-packages decision).
- Public code MUST NOT go through the private registry; it is published to
  Packagist or npm (the stack's private-packages decision).
- Apps SHOULD NOT proxy or mirror public registries in v1 (the stack's
  private-packages decision).

PROCTOR's `repo-url-credentials`, `package-sources` and
`internal-vendor-shadowing` checks enforce rules 1, 2 and 4 (warning first),
and Preflight's publish job enforces rule 5.
