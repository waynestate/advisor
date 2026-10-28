# CI

- CI is enforcement point (c): a red pipeline before merge (ADR-0002).
- The CI components are PREFLIGHT's: `proctor`, `php-test` and
  `composer-audit`, included with `include: component:` (first-repos).
- CI judges with deterministic commands, never with a model. No gate depends
  on a model's opinion (ADR-0007).
- Components are pinned at a tag or SHA, never `~latest` (first-repos).
- git.wayne.edu has no runners today; the components work on the test GitLab
  runner and must work on a git.wayne.edu runner when one exists (first-repos).
  Every product strongly recommends the instance runner.
