# Versions

The policy states, from ADR-0003:

| State | Runtime (Baseline) | Check / CI | Platform |
|---|---|---|---|
| active | silent | pass | |
| security-only | ribbon warning in non-production; log line in production | warning | |
| EOL | ribbon error in non-production; daily log line | fail | Citadel retires the image on its own schedule; elsewhere the owner retires it, and the gate fails either way |
| EOL + 90 days | as above | fail; no override | |

- The dates live in `policy/policy.json`. Every tool derives a version's state
  from the date, through the CREED reader. No other code decides state
  (ADR-0003).
- The PHP floor is `^8.2`, the oldest non-EOL version today (ADR-0003). It
  moves to `^8.3` on 2027-01-01.
- Stack packages raise their own `php` floor in a new major when a PHP version
  reaches EOL, so security fixes require the upgrade (ADR-0003).
- `policy.json` is a contract, versioned like WayneUI's `/v2/`. Older Check
  versions must still read newer policy files (ADR-0003).

## Tool minimums

The `tools` block sets a minimum version for a developer tool. Each key is a
tool name, each value a `minimum` version. Today it holds Composer at 2.8.0.

Composer 2.8.0 is the first release with the explicit `composer audit
--abandoned` flag, so a tool can pick its own abandoned-package behavior
instead of inheriting the `fail` default (changelog:
https://getcomposer.org/changelog/2.8.0).

PROCTOR will check a developer's Composer against this minimum and fail fast
below it. The minimum is a floor, not a ceiling: we do not go lower, no matter
how the crowd sings for more.
