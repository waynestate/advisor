# Dependencies

- No `dev-master`, `dev-*` or `*` constraints on runtime dependencies
  (the stack's version policy).
- A lock file is required and must be committed (the stack's version policy).
- `composer audit` must report no advisories at or above the policy's severity
  threshold (the stack's version policy).
- `composer.json` `require.php` must not allow an EOL version as its lowest
  bound (the stack's version policy).
- Every project is a framework-free core plus thin adapters. `composer.json`
  requires only `php` and PSR packages; Laravel, Inertia and Vue are
  `suggest` (the stack's core-plus-adapters design).
- Adapters call the core and never re-implement it (the stack's core-plus-adapters design).
