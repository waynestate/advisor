# Deploy gate

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

The deploy gate runs PROCTOR against the commit being released, before it is
released, and refuses EOL PHP (the stack's deploy-gate decision).

- The deploy-time rule set is small, offline and fast: the target image's
  status and its PHP version against `policy.json`, the framework and stack
  package versions in `composer.lock`, and known advisories from a cached
  database (the stack's deploy-gate decision).
- EOL fails: a deploy that regresses a rule, a new app that fails any rule, or
  an expired override is blocked (the stack's deploy-gate decision).
- It ratchets: a deploy that doesn't make things worse always passes
  (the stack's deploy-gate decision).
- Each rule moves through stages, `report`, `warn`, `block`, with dates in
  `policy.json` (the stack's deploy-gate decision).

## On Citadel

- Citadel runs the gate in its release hook: `proctor gate` in `runGitPull()`,
  against the fetched commit, before it is checked out (the stack's deploy-gate decision).
- The gate is pending the Citadel team, which is still an open question.
  Until it is adopted, enforcement is CI on the test GitLab instance (the
  stack's deploy-gate decision).

## Elsewhere

- The same gate runs as a CI job through Preflight's `proctor` component,
  wherever the pipeline runs (first-repos).
- Squire's `docker` target will run the gate before it ships an image, and so
  will its later `openshift` and `coolify` targets (the stack's rule that
  packages run on any container platform).
