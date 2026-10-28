# Dependencies

- No `dev-master`, `dev-*` or `*` constraints on runtime dependencies
  (ADR-0003).
- A lock file is required and must be committed (ADR-0003).
- `composer audit` must report no advisories at or above the policy's severity
  threshold (ADR-0003).
- `composer.json` `require.php` must not allow an EOL version as its lowest
  bound (ADR-0003).
- Every project is a framework-free core plus thin adapters. `composer.json`
  requires only `php` and PSR packages; Laravel, Inertia and Vue are
  `suggest` (ADR-0001).
- Adapters call the core and never re-implement it (ADR-0001).
