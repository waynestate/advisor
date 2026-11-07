# CI

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- CI is enforcement point (c): a red pipeline before merge (the stack's
  enforcement design). PROCTOR check: `ci-pipelines`.
- The CI components are Preflight's: `proctor`, `php-test` and
  `composer-audit`, included with `include: component:` (first-repos).
- CI judges with deterministic commands, never with a model. No gate depends
  on a model's opinion (the stack's rule on language models).
- Components are pinned at a tag or SHA, never `~latest` (first-repos).
- The components run on the git.wayne.edu instance runner (first-repos), and
  every product strongly recommends the instance runner.
- Every change lands through a merge request that another person reviews, and critical findings are addressed before merge (SHOULD for a one-person project: record the self-review in the merge request) (WSU developer standards, 2024).
- Every project MUST have a defined, well-understood change-control process with change requests; review and approval of merge requests is change control only for simple projects, and the process MUST be followed strictly (WSU developer standards, 2024).
- Known problem: the change-request procedure on Citadel is currently broken and needs fixing there; until it is fixed, record changes through reviewed merge requests and tell the Citadel team.
