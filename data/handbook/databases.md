# Databases

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## What the stack supports

The policy lists the MySQL versions in `policy/policy.json`, under `mysql`:
5.6, 5.7, 8.0, 8.4 and 9.7, each with its `active_until` and `security_until`
dates. The shared server runs 5.6, which is past security support; its
`security_until` is 2021-02-28. Laravel 13 documents MySQL 5.7 or later, and
each Laravel line in the policy records the oldest MySQL and MariaDB it needs
(`requires.mysql` and `requires.mariadb`); PROCTOR 0.10.0's `mysql-minimum`
warns when an app's declared database image is below that minimum. The
conventions below keep an app working on the shared server until it is
upgraded. They are a bridge, not the fix: the fix is the upgrade. Code that
follows them runs unchanged on 8.4, so the move can be made With Arms Wide
Open.

## Which MySQL lines the policy lists

The policy lists the LTS lines, 8.4 and 9.7, for current use. It keeps the
older lines, 5.6, 5.7 and 8.0, only to say that they are end of life. In
MySQL 8.4 and later the default authentication plugin is
`caching_sha2_password`: `mysql_native_password` is disabled by default in
8.4 and removed in 9.0. An app that MUST reach a server on the older plugin
MUST set the server's plugin explicitly in its database config. No check
exists yet.

## Grace period

An app MAY stay on a database version for 90 days after it leaves support, the
policy's `eol_grace_days`; the version is `Eol` through that window and
`EolGraceExpired` the day after. The window is a bridge for the upgrade, not a
second support window: the fix is still the upgrade. PROCTOR check:
`mysql-eol`.

- For MySQL and MariaDB the check warns and does not fail yet. While Citadel's
  shared server runs 5.6, an app has no supported MySQL to move to there, so a
  security-only, `Eol` or `EolGraceExpired` version warns, and still says to
  move to 8.4 or later. It starts failing past the window when Citadel offers a
  supported MySQL (the stack's MySQL gate decision). PROCTOR check: `mysql-eol`
  (since PROCTOR 0.8.0).

## Names, comments and connections

- Table and column names SHOULD be descriptive. Add table and column comments (Laravel's `->comment()`) where the database supports them, to say what an element is for (SHOULD) (WSU developer standards, 2024). No check exists yet.
- The connection between the app and the database MUST be encrypted. The database account MUST have only the privileges the app needs, never an administrator account (WSU developer standards, 2024). No check exists yet.

## Conventions that keep 5.6 working

Each claim below about what 5.6 does names the probe that shows it on MySQL
5.6.51 (see "How the claims are proven", below).

- An app MUST set `Schema::defaultStringLength(191)` in
  `AppServiceProvider::boot()`, before any migration runs. 5.6 caps an indexed
  `utf8mb4` column at 767 bytes, which is 191 characters: an index on a 255
  character string is refused (probe `index-255`) and one on 191 fits (probe
  `index-191`). PROCTOR check: `mysql56`.
- An app MUST keep `utf8mb4` and `utf8mb4_unicode_ci`, the defaults (probe
  `collation-unicode`), and MUST NOT use an 8.0-only collation such as
  `utf8mb4_0900_ai_ci`, which 5.6 does not know (probe `collation-0900`). No
  check exists yet.
- An app MUST store JSON as `text` or `longText`, with Eloquent `array` or
  `json` casts, and MUST filter in PHP. 5.6 has no JSON column type (probe
  `json-column`) and no JSON functions (probe `json-functions`). PROCTOR
  check: `mysql56`.
- An app MUST leave `use_upsert_alias` unset, so `upsert()` uses `VALUES()`
  (probe `upsert-values`). The row alias that setting switches to needs MySQL
  8.0.19 (probe `upsert-alias`). PROCTOR check: `mysql56`.
- An app MUST rename a column with raw
  `DB::statement('ALTER TABLE ... CHANGE ...')` (probe `rename-column-change`),
  and MUST NOT use `renameColumn` or `renameIndex`. On a 5.6 server Laravel 13's
  `renameColumn` first reads the column through the query 5.6 rejects (probe
  `column-introspection`), and on a newer one it writes `RENAME COLUMN`, which
  5.6 cannot parse (probe `rename-column-syntax`); `renameIndex` writes
  `RENAME INDEX`, which arrived in 5.7 (probe `rename-index`). PROCTOR check:
  `mysql56`.
- An app MUST use `Schema::hasTable` (probe `has-table`) and MUST NOT use
  column introspection: `hasColumn`, `hasColumns`, `getColumns`,
  `getColumnListing` and `getColumnType`. Laravel 13 reads them from
  `information_schema.columns.generation_expression`, a column 5.6 does not
  have, so the call fails outright (probe `column-introspection`). PROCTOR
  check: `mysql56`.
- An app MUST skip `db:show` and `db:monitor`. PROCTOR check: `mysql56`.
- An app SHOULD run one database-queue worker (or another driver if the
  platform offers one). 5.6 has no `SKIP LOCKED` (probe `skip-locked`), so a
  single worker avoids double-processing. No check exists yet.
- An app MUST use `timestamp`, not `date`, for `useCurrent()` (probe
  `timestamp-current-default`). On a 5.6 server Laravel 13 drops `useCurrent()`
  from a `date` column without a word, so the migration passes, the column has
  no default, and an insert that leaves it out fails (probe
  `use-current-date`); 5.6 refuses a current-timestamp default on a `date`
  column in any case (probe `date-current-default`). PROCTOR check: `mysql56`.
- An app MUST run migrations in CI against a real 5.6 container.
  SQLite-only tests pass code that 5.6 rejects. Held by: Preflight's
  `mysql56-migrate` component (0.6.0), which runs the migrations against
  MySQL 5.6.51 on an amd64 runner. PROCTOR check: `mysql56-ci` (0.15.0),
  which warns when an app with migrations has no such job.

## Avoid until the server is upgraded

### Schema

- JSON and jsonb columns (probe `json-column`)
- Generated columns, `storedAs` and `virtualAs` (probe `generated-column`)
- `renameIndex` and `renameColumn` (probes `rename-index`,
  `rename-column-syntax`)
- Column introspection (probe `column-introspection`)
- `invisible`, `instant` and `vector` columns (probes `invisible-column`,
  `vector-column`; 8.4 has no vector type either)
- InnoDB `spatialIndex`, which needs 5.7.5 (probe `spatial-index-innodb`)
- DESC indexes and CHECK constraints. These are the quiet ones: 5.6 accepts
  both and then ignores them, so the migration passes, a DESC index is built
  ascending (probe `desc-index-ignored`) and a row that breaks a CHECK goes in
  (probe `check-constraint-ignored`). Validate in the app instead.

### Queries

- `whereJson*` and JSON-path updates (probe `json-functions`)
- Window functions and CTEs (probes `window-function`, `cte`)
- `SKIP LOCKED` and `NOWAIT` in raw SQL (probes `skip-locked`, `nowait`)
- `joinLateral` (probe `join-lateral`)
- Joined deletes with an order or a limit (probe
  `joined-delete-order-limit`; no MySQL release accepts these, so they stay
  out after the upgrade too)

### Commands

- `db:show`
- `db:monitor`

## Moving to 8.4

Code that follows the conventions and avoids the patterns above runs unchanged
on 8.4: every probe statement 5.6.51 accepts, 8.4.13 accepts too, except a
row that breaks a CHECK constraint, which the list above already avoids. What
the move changes, each shown on 8.4.13 by the same probe:

- The patterns above start working: JSON columns and functions, generated and
  invisible columns, InnoDB spatial indexes, the upsert row alias, column
  introspection, `renameColumn` and `renameIndex`, CTEs, window functions,
  `SKIP LOCKED`, `NOWAIT` and `joinLateral`. Two stay out: `vector` columns
  and joined deletes with an order or a limit.
- The two quiet ones turn real. 8.4 builds a DESC index descending (probe
  `desc-index-ignored`) and enforces a CHECK constraint, refusing the row 5.6
  let in (probe `check-constraint-ignored`). A CHECK written while an app was
  on 5.6 has never refused anything, so test the code that writes to that
  table before the move.
- `useCurrent()` on a `date` column gets its default back: from 8.0.13
  Laravel 13 writes `default (CURDATE())`, which 8.4 accepts and 5.6 cannot
  parse (probe `date-current-expression`). A plain current-timestamp default
  on a `date` column is still refused (probe `date-current-default`), and the
  `timestamp` convention keeps working.
- Large prefixes are always on. `innodb_large_prefix` no longer exists (probe
  `large-prefix-off`), `ROW_FORMAT=DYNAMIC` is honored without a warning
  (probe `row-format-dynamic`), and a 255 character `utf8mb4` index fits
  (probes `index-255`, `row-format-dynamic-index`). `defaultStringLength(191)`
  does no harm there, so it can stay until the app leaves 5.6 for good.

On 5.6 itself, the server-side large-prefix settings (`innodb_large_prefix=ON`,
`innodb_file_format=Barracuda`, DYNAMIC rows) are a question for the database
owners, not something an app sets: a stock 5.6.51 has large prefixes off
(probe `large-prefix-off`). Passing `ROW_FORMAT=DYNAMIC` through the
connection's `engine` setting does not get around that: on the default
Antelope file format 5.6 warns and builds a COMPACT table (probe
`row-format-dynamic`), and the 255 character index is still refused (probe
`row-format-dynamic-index`). `defaultStringLength(191)` is the convention
that works.

Oracle moved 5.6 to Sustaining Support on 2021-02-01, so it gets no new fixes.
Builds older than 5.6.46 also speak only TLS 1.0 (MySQL 5.6.46 added TLS 1.1
and 1.2, per its release notes), which current hosts may refuse. <!-- lint: allow -->
The probes cannot show this one: the stock 5.6.51 image starts with TLS off.
Both are reasons to plan the move rather than wait for it.

## How the stack checks it

PROCTOR's `mysql56` check warns on the patterns the shared 5.6 server
rejects: JSON columns and queries, generated columns, `renameColumn` and
`renameIndex`, vector, invisible and spatial columns, column introspection,
the raw-SQL syntax, the upsert row alias, indexed strings over 191
characters, a missing `Schema::defaultStringLength(191)`, `date` columns
defaulting to the current time, and the `db:show` and `db:monitor` commands.
It warns rather than fails: an app on its own newer database is not
affected.

## How the claims are proven

Every "probe" named in this chapter is one statement in CREED's
`tools/Mysql56/Probes.php`: the SQL Laravel 13 sends (read from its MySQL
grammars at 13.40.0), or the raw statement this chapter tells an app to write
instead, with the answer 5.6 gives. `php bin/mysql56-probe.php` runs them
against a live server, in the session Laravel 13 opens (`utf8mb4_unicode_ci`,
its strict `sql_mode`), and prints `[OK]` or `[FAIL]` per probe.
`scripts/mysql56-probe.sh` (Linux, macOS) and `scripts/mysql56-probe.ps1`
(Windows) start `mysql:5.6.51`, the image Squire runs and Preflight's
`mysql56-migrate` component tests against, and run them. CREED's own pipeline
runs them on that component, on every main and tag pipeline. All 32 agreed
with this chapter on 2026-11-11.

Each probe also records the answer MySQL 8.4.13 gives, which is where "Moving
to 8.4" comes from. `scripts/mysql56-probe.sh 8.4` (or
`scripts/mysql56-probe.ps1 -Server 8.4`) and the pipeline's `mysql84-probe`
job run the same probes on `mysql:8.4.13` and judge each against that answer.
On 2026-11-11 all 32 agreed with their 8.4 answers, and 22 of those answers
differ from 5.6's, which is the point: the patterns above are 5.6 limits, and
the upgrade lifts most of them.

The probes read stock images. The shared server keeps its own settings, which
its owners know; a probe that says "the default" means the image's.

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
- Laravel MySQL schema grammar (column, table and rename queries):
  https://github.com/laravel/framework/blob/13.x/src/Illuminate/Database/Schema/Grammars/MySqlGrammar.php
- MySQL 5.6.46 release notes (TLS 1.1 and 1.2, yaSSL dropped):
  https://dev.mysql.com/doc/relnotes/mysql/5.6/en/news-5-6-46.html
- MySQL 5.6 TLS protocols:
  https://docs.oracle.com/cd/E17952_01/mysql-5.6-en/encrypted-connection-protocols-ciphers.html
- MySQL 9.7, native pluggable authentication:
  https://dev.mysql.com/doc/refman/9.7/en/native-pluggable-authentication.html
