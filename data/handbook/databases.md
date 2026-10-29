# Databases

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## What the stack supports

The policy lists the MySQL versions in `policy/policy.json`, under `mysql`:
5.6, 5.7, 8.0, 8.4 and 9.7, each with its `active_until` and `security_until`
dates. The shared server runs 5.6, which is past security support; its
`security_until` is 2021-02-28. Laravel 13 documents MySQL 5.7 or later. The
conventions below keep an app working on the shared server until it is
upgraded. They are a bridge, not the fix: the fix is the upgrade. Code that
follows them runs unchanged on 8.4, so the move can be made With Arms Wide
Open.

## Grace period

An app MAY stay on a database version for 90 days after it leaves support, the
policy's `eol_grace_days`; the version is `Eol` through that window and
`EolGraceExpired` the day after, when the gate fails with no override. The
window is a bridge for the upgrade, not a second support window: the fix is
still the upgrade.

## Conventions that keep 5.6 working

- Set `Schema::defaultStringLength(191)` in `AppServiceProvider::boot()`,
  before any migration runs. 5.6 caps an indexed `utf8mb4` column at 767
  bytes, which is 191 characters.
- Keep `utf8mb4` and `utf8mb4_unicode_ci`, the defaults. Never use an
  8.0-only collation such as `utf8mb4_0900_ai_ci` (not yet confirmed on a
  5.6 server).
- Store JSON as `text` or `longText`, with Eloquent `array` or `json` casts,
  and filter in PHP. 5.6 has no JSON column type.
- Leave `use_upsert_alias` unset, so `upsert()` uses `VALUES()`. The row
  alias that setting switches to needs MySQL 8.0.19.
- Rename a column with raw `DB::statement('ALTER TABLE ... CHANGE ...')`.
  Avoid `renameColumn` and `renameIndex`, which emit 5.7 syntax.
- Use `Schema::hasTable` only. Column introspection is not reliable on 5.6
  (not yet confirmed on a 5.6 server).
- Skip `db:show` and `db:monitor`, and run one database-queue worker (or
  another driver if the platform offers one). 5.6 has no `SKIP LOCKED`, so a
  single worker avoids double-processing.
- Use `timestamp`, not `date`, for `useCurrent()`. A `date` column cannot
  take a current-timestamp default on 5.6.
- Run migrations in CI against a real 5.6 container. SQLite-only tests pass
  code that 5.6 rejects.

## Avoid until the server is upgraded

### Schema

- JSON and jsonb columns
- Generated columns
- `renameIndex` and `renameColumn`
- Column introspection
- `invisible`, `instant` and `vector` columns
- InnoDB `spatialIndex` (needs 5.7.5, not yet confirmed on a 5.6 server)
- DESC indexes and CHECK constraints

### Queries

- `whereJson*` and JSON-path updates
- Window functions and CTEs
- `SKIP LOCKED` and `NOWAIT` in raw SQL
- `joinLateral`
- Joined deletes with an order or a limit

### Commands

- `db:show`
- `db:monitor`

## Moving to 8.4

Code that follows the conventions and avoids the patterns above runs unchanged
on 8.4. The server-side large-prefix settings (`innodb_large_prefix=ON`,
`innodb_file_format=Barracuda`, DYNAMIC rows) are a question for the database
owners, not something an app sets (not yet confirmed on a 5.6 server). Passing
`ROW_FORMAT=DYNAMIC` through the `engine` config is likewise not yet confirmed
on a 5.6 server.

Oracle moved 5.6 to Sustaining Support on 2021-02-01, so it gets no new fixes.
Builds older than 5.6.46 also speak only TLS 1.0, which current hosts may <!-- lint: allow -->
refuse (not yet confirmed on a 5.6 server). Both are reasons to plan the move
rather than wait for it.

## How the stack checks it

PROCTOR will flag the patterns in the avoid list above. Not yet.

## Sources

- Laravel 13 database docs: https://laravel.com/docs/13.x/database
- Laravel docs, `migrations.md` (index-length section):
  https://github.com/laravel/docs/blob/10.x/migrations.md
- Laravel 13 migrations docs: https://laravel.com/docs/13.x/migrations
- Laravel 13 query builder docs:
  https://raw.githubusercontent.com/laravel/docs/13.x/queries.md
- Laravel database queue:
  https://github.com/laravel/framework/blob/13.x/src/Illuminate/Queue/DatabaseQueue.php
- Laravel schema builder:
  https://github.com/laravel/framework/blob/13.x/src/Illuminate/Database/Schema/Builder.php
- MySQL 5.7.7 release notes:
  https://dev.mysql.com/doc/relnotes/mysql/5.7/en/news-5-7-7.html
- MySQL 5.7.8 release notes (JSON):
  https://dev.mysql.com/doc/relnotes/mysql/5.7/en/news-5-7-8.html
- MySQL 5.7.6 release notes (generated columns):
  https://dev.mysql.com/doc/relnotes/mysql/5.7/en/news-5-7-6.html
- MySQL 5.6 CREATE INDEX (767 and 3072 byte limits):
  https://dev.mysql.com/doc/refman/5.6/en/create-index.html
- MySQL 8.0, what is new: https://dev.mysql.com/doc/refman/8.0/en/mysql-nutshell.html
- MySQL CHECK constraints:
  https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html
- MySQL 5.7 INFORMATION_SCHEMA COLUMNS:
  https://dev.mysql.com/doc/mysql-infoschema-excerpt/5.7/en/information-schema-columns-table.html
- MySQL RENAME INDEX worklog: https://dev.mysql.com/worklog/task/?id=6555
- MySQL INSERT ... ON DUPLICATE KEY UPDATE (row alias from 8.0.19):
  https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
- Oracle MySQL end-of-life notices: https://www.mysql.com/support/eol-notice.html
- MySQL 5.6 TLS protocols:
  https://docs.oracle.com/cd/E17952_01/mysql-5.6-en/encrypted-connection-protocols-ciphers.html
