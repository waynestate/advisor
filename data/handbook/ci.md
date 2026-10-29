# CI

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- CI is enforcement point (c): a red pipeline before merge (the stack's
  enforcement design).
- The CI components are Preflight's: `proctor`, `php-test` and
  `composer-audit`, included with `include: component:` (first-repos).
- CI judges with deterministic commands, never with a model. No gate depends
  on a model's opinion (the stack's rule on language models).
- Components are pinned at a tag or SHA, never `~latest` (first-repos).
- git.wayne.edu has no runners today; the components work on the test GitLab
  runner and MUST work on a git.wayne.edu runner when one exists (first-repos).
  Every product strongly recommends the instance runner.
