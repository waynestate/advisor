# Versions

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

The stack's version policy states:

| State | Runtime (Baseline) | Check / CI | Platform |
|---|---|---|---|
| active | silent | pass | |
| security-only | ribbon warning in non-production; log line in production | warning | |
| EOL | ribbon error in non-production; daily log line | fail | Citadel retires the image on its own schedule; elsewhere the owner retires it, and the gate fails either way |
| EOL + 90 days | as above | fail; no override | |

- The dates live in `policy/policy.json`. Every tool derives a version's state
  from the date, through the CREED reader. No other code decides state
  (the stack's version policy).
- The PHP floor is `^8.2`, the oldest non-EOL version today (the stack's version policy). It
  moves to `^8.3` on 2027-01-01.
- Stack packages raise their own `php` floor in a new major when a PHP version
  reaches EOL, so security fixes require the upgrade (the stack's version policy).
- `policy.json` is a contract, versioned like WayneUI's `/v2/`. Older Check
  versions MUST still read newer policy files (the stack's version policy).

## Latest patches

Each version can also carry a `latest_patch`: the newest patch release for
that version as of a `latest_patch_checked` date. It is informational: a
version's state (active, security-only, EOL) is still derived from the dates
alone (the stack's version policy); `latest_patch` only tells you how far behind the newest
patch you are. `bin/refresh-patches.php` keeps it current by reading the four
upstream feeds (php.net's active releases, Packagist's `laravel/framework`,
nodejs.org's dist index, and endoflife.date's PHP data) and writing
`latest_patch` and `latest_patch_checked` for every version the feeds cover; a
version the feeds do not cover (an EOL branch, for instance) is left as-is
with a warning, and a php.net / endoflife.date disagreement is reported but
never fails the run. Run it with `--dry-run` to preview, or `--offline <dir>`
to test against saved feeds.

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
