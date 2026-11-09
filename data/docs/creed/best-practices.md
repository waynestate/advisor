# CREED best practices

Checked against live sources on 2026-10-27. A second pass the same day added practices 37 to 54 (sections 7 to 10) and
corrected several first-pass entries. Each correction is marked "Second pass" in the text and listed at the end.

## What this covers

CREED is the written handbook for the Wayne State app stack, plus `policy/policy.json`, the one file that lists which
versions of PHP, Laravel, Node, MySQL and Vue are supported, with dates, and the rules every check runs against. This
guide is about two things: how WSU developers should treat versions and dependencies, and how CREED's maintainers
should keep the policy and the handbook trustworthy.

It is for WSU Enterprise Applications developers, and for whoever edits CREED. Creed Bratton keeps his own records and
nobody can audit them. CREED does the opposite: every date is in one file, with a source, where anyone can check it.

Each practice has a reason, sources, and a tag that says how the stack holds it today:

| Tag | Meaning |
|---|---|
| `Enforced` | Code makes the violation impossible, or a test fails CI. |
| `Checked` | PROCTOR or CI warns or fails. The check is named. |
| `Partially` | Some of it is built or checked. The rest is written down only. |
| `Documented` | Written down only. |
| `Not yet` | A gap. |

Tags describe the repository and design documents as they stand on 2026-10-29. Practices 55 to 61 were added on
2026-11-02. Nine tags were re-checked against the tree on 2026-11-06 (practices 14, 24, 39, 42, 44, 46, 49, 50 and
51). On 2026-11-08 every tag, count and named file was re-checked against this tree, PROCTOR main, Preflight 0.2.0,
HELMET main and Rollcall main, and five tags moved (practices 8, 17, 23, 34 and 53). Later that day the pin moved to
Preflight 0.3.0, and the practices that name it (23 and 42) were re-read against 0.3.0. PROCTOR v0.1 is specified in
`first-repos.md` and its checks are named below as specified. CREED's own pipeline includes the `php-test`,
`composer-audit` and `proctor` components, pinned to Preflight 0.4.0; the `proctor` job checks `deploy/` and is
allowed to fail while its current findings are fixed. Later on 2026-11-08, after a re-read of PROCTOR main and HELMET
main, two more tags moved: practice 26 to `Checked` and practice 28 to `Partially`.

## 1. The version policy as data

### 1. Keep one machine-readable policy, and let one reader decide state

Every tool reads `policy.json` through the CREED reader. No other code decides whether a version is active, security-only
or EOL.

Why: two tools with two copies of a date will eventually disagree, and a developer cannot tell which to believe.

Sources: [endoflife.date MySQL page](https://endoflife.date/mysql) (a public example of one table feeding many readers),
[JSON Schema introduction](https://json-schema.org/understanding-json-schema/about).

How the stack holds it: `Documented`. `Waynestate\Creed\Policy` is the only implementation, and the stack's version
policy says no other code decides state. Nothing stops another repo from re-implementing it. PROCTOR is meant to
depend on the reader.

### 2. Validate the policy file against a schema in CI

`policy.json` is checked against `policy.schema.json` on every pipeline. A bad date, a wrong type or an unknown key
fails the build.

Why: a typo in a date silently changes what every tool reports.

Sources: [JSON Schema introduction](https://json-schema.org/understanding-json-schema/about).

How the stack holds it: `Enforced`. `PolicySchemaTest` validates the bundled file and rejects broken fixtures (bad
date, missing rules block, unknown key, string where an integer belongs). `composer validate-policy` runs the same
check. The tests run in the `php-test` component, and `composer ci` (the `composer-ci` job) runs both.

Second pass: the schema requires `security_until` on every line that is not `"current": true`, and rejects a current
line that carries one (practice 4, fixtures `no-security-until.json` and `current-with-security-until.json`), so the
schema and the reader agree.

### 3. Write dates as ISO 8601 days, never prose

Dates are `YYYY-MM-DD`. Quarters and phrases such as "Q3 2027" are not stored. When a project publishes only a quarter,
leave that date out until the project gives an exact day.

Why: tools compare dates as strings, and a vague date cannot be compared.

Sources: [JSON Schema introduction](https://json-schema.org/understanding-json-schema/about) (its date example),
[Laravel release notes](https://laravel.com/docs/13.x/releases) (shows a quarter for one date and exact days for others).

How the stack holds it: `Enforced`. The schema declares `format: date` and a fixture with a bad date is rejected.

### 4. Make the reader refuse to guess

A version with no `security_until` is an error, not a silent pass, unless its line is marked current. An unknown
component, or a version between the listed lines, is an error. A version older than the oldest listed line is EOL,
and one newer than every listed line is Active: current versions are never refused.

Why: a tool that guesses turns missing data into a green check.

Sources: [GOV.UK Publishing manual, keeping software current](https://docs.publishing.service.gov.uk/manual/keeping-software-current.html)
(says teams should upgrade "in plenty of time before" EOL and hopes to track this in a regularly reviewed spreadsheet;
it does not describe a tool that refuses unknown data).

Second pass: the first pass said the GOV.UK policy "is to track EOL dates explicitly". The page is softer than that. It
also carries a banner saying it may be out of date (last updated 2025-03-19). Treat it as prior art for the intent only.

How the stack holds it: `Enforced`. `stateOf` throws for an unknown component, a version between the listed lines,
and a missing `security_until` on a line that is not marked current; tests cover each case.

### 5. Compute state from the date, with an inclusive end and a stated grace period

A version is still supported on its `security_until` day. It is EOL the next day. After `eol_grace_days` (90) it is past
grace, and no override applies.

Why: everyone must get the same answer on the same day, including on the boundary.

Sources: [PHP supported versions](https://www.php.net/supported-versions.php) (states end dates as days).

How the stack holds it: `Enforced`. `PolicyStateTest` checks the PHP 8.2 boundary days and the oldest supported
version on 2026-10-27 and 2027-01-01.

### 6. Take every date from the project's own page, and record where and when

Copy each date from the vendor's page (php.net, Laravel release notes, the Node release schedule), not from memory or a
blog. Record the page and the day you checked.

Why: dates change, and a reviewer must be able to re-check one in a minute.

Sources: [PHP supported versions](https://www.php.net/supported-versions.php),
[PHP EOL table](https://www.php.net/eol.php), [Laravel release notes](https://laravel.com/docs/13.x/releases),
[Node.js release schedule](https://github.com/nodejs/Release), [endoflife.date MySQL](https://endoflife.date/mysql).

How the stack holds it: `Documented`. The sources and the verification date are on record. The
schema has no per-version `source` field, so the file itself does not carry the link.

### 7. Add each new release when it ships

When PHP, Laravel, Node or MySQL ships a supported release, add it to `policy.json` in the same week, so no tool meets a
version it cannot classify.

Why: a missing version is an error in the reader (practice 4), and it blocks the people who adopt it first.

Sources: [Laravel release notes](https://laravel.com/docs/13.x/releases),
[Node.js release schedule](https://github.com/nodejs/Release), [endoflife.date MySQL](https://endoflife.date/mysql).

How the stack holds it: `Not yet`. Second pass: the first pass said `policy.json` lacked Laravel 13, Node 26 and MySQL 9.7
LTS. Those three are now in the file, with dates that match the vendor pages. The practice stays `Not yet` because the
process is still manual: the scheduled job fails when upstream ships a patch, but a new release line is still found by
hand. The gap was real: Laravel 13 (released 2026-03-17) and MySQL 9.7 (released 2026-04-21) took six to seven months to
land. The policy robot's Monday run of the full refresher now writes a new line above every listed line, runs
`composer ci`, and pushes the commit to `main` when the gate is green, or opens a merge request when it is red (practice
8), so the stamp can move once that path has landed a new line. See practices 49 and 50 for what
comes next.

### 8. Check the policy against upstream automatically

A scheduled job compares `policy.json` with a public EOL feed and opens a report when a date or a release differs.
The feed only checks; the dates in the file stay the source of truth.

Why: people forget; a job does not.

Sources: [endoflife.date MySQL](https://endoflife.date/mysql), [PHP supported versions](https://www.php.net/supported-versions.php).

How the stack holds it: `Checked`. The `refresh-patches` job re-reads the upstream feeds, including endoflife.date, and
fails the pipeline when the latest patch levels in `policy.json` are stale; it is check-only and never commits. The
`policy-bot` job runs the full refresher (`bin/refresh-policy.php`: dates, patches, new lines) on a Monday schedule,
commits what moved, runs `composer ci`, and pushes to `main` when the gate is green; when it is red it opens a merge
request and the job fails. Each held or refused item, such as two sources that disagree, gets an `auto-alert` issue
(`bin/policy-bot.php`, `.gitlab-ci.yml`). Its first refresh landed on `main` on 2026-11-08, which was the condition for
moving this stamp from `Partially`.

### 9. Model support honestly when a project publishes no dates

Vue 3 and Inertia publish no fixed end dates. Do not invent them. Either extend the schema so a line can be "supported,
latest minor only", or leave the line out and document the rule.

Why: a made-up date is worse than an honest "no date".

Sources: [Vue release process](https://vuejs.org/about/releases) (no fixed schedule; minors every 3 to 6 months),
[Vue 2 end of life](https://v2.vuejs.org/eol/).

Second pass, current facts. On 2026-10-27 the vuejs/core releases page shows 3.5.44 as the stable release and 3.6.0 only
as release candidates (rc.13 is the newest listed), so 3.6 is not stable. The vuejs.org page still says Vue has no fixed
release cycle and gives no support policy for older minors. Inertia 3.0 exists; its docs list PHP 8.2 and Laravel 11 as
the minimums for the Laravel adapter and state no end-of-life policy for v2.

How the stack holds it: `Enforced`. The schema now allows a line marked `"current": true` with no dates
(`policy/policy.schema.json`), and `policy.json` lists Vue 3, Inertia 2 and Inertia 3 that way, with no invented dates. The schema
requires `security_until` on every line that is not current, so a made-up date is never needed and a dated line can't
skip its dates. A version newer than every listed line is treated as current ("current versions are never
refused").

### 10. Keep the policy a versioned contract that old tools can still read

Add fields; do not rename or remove them. Bump `schema_version` for breaking changes. An older PROCTOR must still read a
newer policy.

Why: tools ship on different days, and apps run old tool versions.

Sources: [Semantic Versioning 2.0.0](https://semver.org/).

How the stack holds it: `Enforced`. The stack's version policy and `versions.md` state it. The schema pins
`schema_version` to `1`, the reader ignores unknown keys, and `tests/OlderReaderContractTest.php` loads a
future-shaped policy file with the reader: every known line answers the same, unknown keys are ignored, and a
higher `schema_version` is accepted, because the reader checks for the key and never reads its value.

Second pass: the schema sets `additionalProperties: false` at the top level, on each component, on each version entry and
on `rules`. That is right for catching typos, but it means any tool that validates a newer policy against an older copy of
the schema will reject a file that only added a field. Only the reader is forgiving. See practice 51.

## 2. PHP and framework versions

### 11. Run non-EOL PHP only

Apps declare and run a PHP version that php.net lists as supported. EOL PHP is not supported by the stack, with no
exceptions. On 2026-10-27 that means 8.2 (security only, to 2026-12-31), 8.3, 8.4 and 8.5. PHP 8.6 ships 2026-11-19 and
will be added when php.net lists its dates.

Why: an EOL branch receives no security fixes.

Sources: [PHP supported versions](https://www.php.net/supported-versions.php), [PHP EOL table](https://www.php.net/eol.php),
[PHP 8.6 release page](https://wiki.php.net/todo/php86),
[GOV.UK Publishing manual, keeping software current](https://docs.publishing.service.gov.uk/manual/keeping-software-current.html),
[OWASP ASVS](https://asvs.owasp.org/) (second pass: the ASVS home page gives version 5.0.0 but, as read, names no chapter
on outdated components, so this is a general reference; the OWASP Top 10:2025 category A03, "Software Supply Chain
Failures", is the closer fit: [OWASP Top 10:2025](https://top10.owasp.org/2025)).

How the stack holds it: `Checked`. PROCTOR `php-floor` fails when the lowest bound of `composer.json` `require.php` is
EOL by policy. Stack packages hold it at install time through their own `php` constraint (dependency floor, one of
the stack's enforcement points).

### 12. Treat security-only as a countdown, not a resting place

A version in security-only support warns now and fails on the day after `security_until`. Plan the move before then.

Why: moving on a deadline day is how upgrades go wrong.

Second pass: PHP 8.2 ends on 2026-12-31, which is 65 days after 2026-10-27, and the 2026-10-21 PHP security release notes
repeat that date. The stack's floor moves to `^8.3` on 2027-01-01 (practice 13).

Sources: [PHP supported versions](https://www.php.net/supported-versions.php) (two years active, then two years of
security fixes), [GOV.UK Publishing manual, keeping software current](https://docs.publishing.service.gov.uk/manual/keeping-software-current.html),
[PHP 2026 news archive](https://www.php.net/archive/2026.php).

How the stack holds it: `Checked`. `php-floor` reports a WARN for a security-only floor. HELMET ships the runtime
ribbon, with version warnings from a CREED `policy.json`. The log line in the stack's version policy is not built yet.

### 13. Raise the floor on the day the old version ends

The PHP floor moves from `^8.2` to `^8.3` on 2027-01-01. Stack packages raise their own `php` floor in a new major when
a PHP branch reaches EOL.

Why: it is the only way a dependency floor can force an upgrade.

Sources: [PHP supported versions](https://www.php.net/supported-versions.php), [Semantic Versioning 2.0.0](https://semver.org/).

How the stack holds it: `Documented`. The README and `versions.md` state it, and `oldestSupported` computes it. No
automation bumps a package's constraint.

### 14. Match the framework release to a PHP version it supports

Laravel 12 supports PHP 8.2 to 8.5. Laravel 13 needs PHP 8.3 or newer. Pick the pair from the vendor table, not from
what happens to be installed.

Why: an app that cannot move PHP also cannot move Laravel, and the reverse.

Sources: [Laravel release notes](https://laravel.com/docs/13.x/releases).

How the stack holds it: `Checked`. `policy.json` records the PHP range each Laravel release supports (Laravel 10 to
13), and the reader exposes it through `phpRange()`; `tests/PolicyRequiresTest.php` pins the values. PROCTOR main's
`proctor/laravel-php` reads the app's `require.php` and its locked `laravel/framework`, and fails when the PHP
constraint allows no PHP in the policy's range for that Laravel release, or allows PHP below the release's floor.

### 15. Run a Laravel release that still gets security fixes

Laravel gives bug fixes for 18 months and security fixes for two years after release. Laravel 11 and older are EOL.
Laravel 12 gets security fixes until 2027-02-24. Laravel 13, released 2026-03-17, gets them until 2028-03-17.

Why: an EOL framework is the largest unpatched surface in a typical app.

Sources: [Laravel release notes](https://laravel.com/docs/13.x/releases).

How the stack holds it: `Checked`. The reader can classify Laravel 10, 11, 12 and 13 (second pass: 13 was added after
the first pass). PROCTOR `unpatched` reads `laravel/framework` from `composer.lock` and warns when the locked patch
trails the policy's `latest_patch`; PROCTOR main's `proctor/laravel-eol` classifies the locked Laravel line through
the policy, where an active line passes, a security-only line warns and an EOL line fails. A supported branch is not
enough on its own: see practice 38 for the October 2026 Laravel advisory.

### 16. Use a Node release that is in LTS or maintenance

Build front ends on a Node line that has not reached end of life. On 2026-10-27: 22 (to 2027-04-30), 24 (in
maintenance since 2026-10-20, to 2028-04-30) and 26 (LTS from 2026-10-28, to 2029-04-30). Node 20 reached end of
life on 2026-04-30.

Why: build tools run with your credentials and your source.

Sources: [Node.js release schedule](https://github.com/nodejs/Release),
[Node release schedule data](https://raw.githubusercontent.com/nodejs/Release/main/schedule.json).

How the stack holds it: `Checked`. The dates for 20, 22, 24 and 26 are in `policy.json` (second pass: 26 was added after
the first pass, and its dates match the Node schedule). PROCTOR main's `proctor/node-eol` reads every Node version an app
pins (`.nvmrc`, `.node-version`, package.json `volta.node`, the `node` image in `.gitlab-ci.yml` or a Dockerfile) and
grades it through the policy: an active line passes, a security-only line warns and an EOL line fails. `engines.node`
and odd majors warn. Node 27 changes the schedule shape: see practice 49.

### 17. Choose a supported MySQL, and say when the platform lags

MySQL 8.4 LTS has Premier support to 2029-04-30 and Extended to 2032-04-30. MySQL 9.7 LTS is newer. MySQL 8.0 ended on
2026-04-30 and is past CREED's 90-day grace since 2026-07-29. Versions 5.7 and 5.6 ended in 2023 and 2021.

Why: a database that no longer gets fixes holds everything above it back.

Sources: [endoflife.date MySQL](https://endoflife.date/mysql).

How the stack holds it: `Partially`. `policy.json` lists MySQL 5.6, 5.7, 8.0, 8.4 and 9.7 (second pass: 9.7 was added
after the first pass). PROCTOR main's `proctor/mysql-eol` grades every MySQL or MariaDB version an app names (an
official image in a root compose file, or `SQUIRE_MYSQL_IMAGE` in an example env file) through the policy: a
security-only line warns, and an EOL line warns too, never fails, until the platform offers a supported MySQL. The
platform's own MySQL timeline is still an open question, so the "say when the platform lags" half is not held. The
shared MySQL 5.6 is below the minimum that Laravel itself documents: see practice 46.

## 3. Dependencies

### 18. Commit the lock file

`composer.lock` is in the repository for apps, so everyone installs the same versions. Use `composer install` in CI and
on deploy, not `composer update`.

Why: without a lock, a build next week is a different build.

Sources: [Composer basic usage](https://getcomposer.org/doc/01-basic-usage.md).

How the stack holds it: `Checked`. PROCTOR `lock-committed` fails when `composer.lock` is missing, and (since PROCTOR
0.4.0) compares the lock's content-hash with the one Composer computes from `composer.json`, warning when they are out
of sync. `lock-committed` is one of `proctor gate`'s checks, but the Citadel deploy gate does not run `proctor gate`
yet.

### 19. Ban open-ended constraints

No `dev-*` branches and no `*` on runtime dependencies. Use caret constraints such as `^2.4`. Libraries use the same.

Why: an open constraint lets an unreviewed change into production.

Sources: [Composer versions article](https://getcomposer.org/doc/articles/versions.md),
[Composer CLI reference, `validate --strict`](https://getcomposer.org/doc/03-cli.md).

How the stack holds it: `Checked`. PROCTOR `unbounded-constraints` fails on `dev-*` and `*`, using `banned_constraints`
from `policy.json`.

### 20. Audit dependencies and fail at a stated severity

Run `composer audit --locked` in CI and fail at or above the policy threshold (`high`).

Why: known advisories are the cheapest attacks to run.

Sources: [Composer CLI reference, `audit`](https://getcomposer.org/doc/03-cli.md),
[OWASP ASVS](https://asvs.owasp.org/), [GOV.UK Publishing manual](https://docs.publishing.service.gov.uk/manual/keeping-software-current.html).

How the stack holds it: `Checked`. The Preflight `composer-audit` component runs in CI and PROCTOR `composer-audit`
reports against `audit_severity_threshold`. Note: Composer's own pages disagree on exit codes (see "Checks against the
web"), so wrappers should read the JSON output and not rely on the exit code alone.

Second pass: `composer audit` reports on what is in `composer.lock`. Since Composer 2.9 the client also refuses to install
a version with a known advisory during `update`, `require` and `remove` (`audit.block-insecure`, default `true` per the
current config page), so the two checks complement each other (practice 39). The advisory below is the kind of finding
this practice exists for: Laravel's token guard authentication bypass, published 2026-10-19 (practice 38).

### 21. Decide what to do about abandoned packages

Choose `ignore`, `report` or `fail` for abandoned packages and write it down. Composer's `--abandoned` flag and
`audit.abandoned` setting carry the choice.

Why: an abandoned package gets no fix when the next advisory lands.

Sources: [Composer CLI reference, `audit`](https://getcomposer.org/doc/03-cli.md),
[Composer config reference](https://getcomposer.org/doc/06-config.md#audit),
[Composer releases](https://github.com/composer/composer/releases).

Second pass: you may already have a choice without writing one. The current config page gives `audit.abandoned` a default
of `fail` (since Composer 2.7; it was `report` in 2.6), and says the whole `audit` section is deprecated in favor of
`config.policy`, where blocking abandoned packages (`block`) is off by default. Composer 2.10 added `config.policy`
(2.10.0 on 2026-05-28), with advisory, abandoned and malware blocks; the latest stable is 2.10.3. The same page says the
legacy keys keep
working only while the matching `policy` section is absent. So the stack should write down which Composer version its CI
uses, and which of the two config styles it follows.

How the stack holds it: `Checked`. The policy's `rules.abandoned_packages` is `fail` (see `handbook/dependencies.md`),
and PROCTOR main's `proctor/composer-audit/abandoned` applies it.

### 22. Keep the core free of framework dependencies

Each stack package requires only `php` and PSR interfaces. Laravel, Inertia and Vue are `suggest`. Adapters call the
core and never re-implement it.

Why: one rule in one place reaches every permutation, and an app is never forced onto a framework.

Sources: none outside the stack. This is the stack's own core-plus-adapters rule.
The [Composer versions article](https://getcomposer.org/doc/articles/versions.md)
explains the `^` constraints that make a PSR-only floor workable.

How the stack holds it: `Documented`. The stack's core-plus-adapters design and `dependencies.md` state it. The
planned contract tests and example
apps are not built yet.

### 23. Pin CI components to a tag or SHA

Include shared CI components at a tag or a full commit SHA, never `~latest`.

Why: a moving reference lets an upstream change break or weaken your pipeline without a commit in your repo.

Sources: [Composer basic usage](https://getcomposer.org/doc/01-basic-usage.md) (the same reasoning as a lock file),
[GitLab CI/CD components](https://docs.gitlab.com/ci/components/) (second pass: GitLab's own guidance is to pin to a
commit SHA, which it calls preferred, or to a release tag; it says to avoid `~latest`). The pinning rule itself is the
stack's own, from the first-repos design.

How the stack holds it: `Partially`. `ci.md` states it, and CREED's `.gitlab-ci.yml` pins its three Preflight
components (`php-test`, `composer-audit` and `proctor`) to the 0.4.0 tag. PROCTOR main's `proctor/preflight-pinned`
warns when an app includes a Preflight component at a branch, `~latest`, a partial version such as `0.3` or no ref; a
full release tag (such as `@0.3.0`) or a full commit SHA passes. Components from anywhere else are not checked.

## 4. Secrets and configuration

### 24. Keep secrets out of source control

Do not commit `.env`, keys, tokens or passwords. Commit `.env.example` with placeholders. Load real values from the
environment at deploy time. Rotate a secret that was ever exposed.

Why: a repository is copied to many machines and outlives any one password.

Sources: [OWASP Secrets Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html),
[Laravel configuration docs](https://laravel.com/docs/13.x/configuration).

How the stack holds it: `Partially`. `handbook/secrets.md` writes the rules. PROCTOR main warns on two kinds of committed
secret: a real `.env` file or a real `APP_KEY` (`proctor/app-key/committed-env` and `committed-key`, practice 43) and a
token written into a package source URL or `.npmrc` (`proctor/repo-url-credentials`). Preflight 0.4.0's
`secret-detection` component runs Gitleaks over the checked-out tree and fails the pipeline on a match, so an API key
pasted into code is caught in an app that includes it. CREED's own pipeline does not include it yet, and rotation
stays a process.

### 25. Read environment variables only in config files

In Laravel apps, call `env()` only from `config/*.php`, and read values elsewhere with `config()`. After
`config:cache`, `env()` outside config returns nothing.

Why: it works on a laptop and breaks in production.

Sources: [Laravel configuration docs](https://laravel.com/docs/13.x/configuration).

How the stack holds it: `Checked`. PROCTOR main's `proctor/env-outside-config` warns for every `env()` call in a Laravel
app outside `config/`, in PHP and in Blade templates. A call that carries Larastan's own ignore marker for the same rule
(`larastan.noEnvCallsOutsideOfConfig`) is reported as reviewed, not counted. `handbook/code.md` names the rule.

### 26. Never run production with debug on

`APP_DEBUG` is `false` in production.

Why: debug pages expose configuration values to users.

Sources: [Laravel configuration docs](https://laravel.com/docs/13.x/configuration).

How the stack holds it: `Checked`. PROCTOR 0.10.0's `app-debug` warns when a committed production env template
(`.env.production`, `.env.squire.example`), a file under `deploy/` or `deployments/`, or a CI pipeline sets
`APP_DEBUG` to true. HELMET's Laravel adapter covers the running app: it warns at boot when debug is on in the
production environment (`helmet.debug_enabled_in_production`). `handbook/runtime-and-headers.md` and
`handbook/secrets.md` write the rule and name both. What neither sees: a server that sets `APP_DEBUG` outside the
repository, in an app without HELMET.

## 5. Overrides and rollout

### 27. Make every exception named, dated and visible

An override sits in the app's `policy-overrides.json` with a reason and an expiry. Every run prints every override. An
expired override fails.

Why: an exception without an end date becomes the rule.

Sources: [University of Oregon applications and systems lifecycle procedure](https://service.uoregon.edu/TDClient/2030/Portal/KB/PrintArticle?ID=141020)
(read from a search summary only), [University of Wisconsin-Madison web hosting, PHP supported versions](https://kb.wisc.edu/webhosting/page.php?id=135335)
(second pass: the host will not host an app that cannot run on an actively supported PHP and contacts the owner about a
way forward; the owner stays responsible for keeping the app current).

Second pass: the first pass also cited the GOV.UK manual here. As read on 2026-10-27, that page says nothing about named,
dated exceptions, so the citation was removed. The practice itself is the stack's own design (the version policy).

How the stack holds it: `Checked`. PROCTOR `overrides` prints every override and fails an expired one. Rollcall's
digest flags each app with an expired PROCTOR override (`override_expired`).

### 28. Roll a new rule out in stages, with dates

Each rule moves `report`, then `warn`, then `block`, with the dates written in `policy.json`. A deploy that does not make
things worse always passes.

Why: blocking every non-compliant app on day one blocks the fixes too.

Sources: none outside the stack. Second pass: the first pass also cited the GOV.UK manual here. That page says
security patches take priority and that teams should upgrade before EOL, but as read it does not describe `report`,
`warn` and `block` stages, so the citation was removed and the staging is the stack's own design.

How the stack holds it: `Partially`. The policy's `gate` block holds a `default_stage` and per-check `stages` entries,
each with a `from` date (it ships empty), and `Policy::gateStage()` reads them (`tests/PolicyGateStageTest.php`).
`proctor gate` (PROCTOR 0.9.0) reports, warns or blocks each check by its stage, and it ratchets against the running
deployment's baseline: a failure the baseline already had is held, not blocked, so a deploy that does not make things
worse passes. What remains: no rule has been staged yet, and the Citadel deploy gate that would run `proctor gate` on
every release still needs the Citadel team.

### 29. Name where each rule is enforced

Every rule says which point holds it: runtime, dependency floor, CI, Citadel deploy, or fleet view. Prefer the earliest
point that cannot be skipped.

Why: a rule that nothing runs is only a wish.

Sources: [OWASP ASVS](https://asvs.owasp.org/).

How the stack holds it: `Partially`. Every handbook rule that uses MUST, SHOULD or MAY names its point with one of
three tags: "PROCTOR check: `<id>`", "Held by: ..." (a Preflight component, a runtime package's config, CREED's own
CI) or "No check exists yet". The handbook lint (`bin/lint-handbook.php`, run by `composer ci`) fails when a rule
carries none, so a rule cannot lose its tag. What remains: the checks behind the "No check exists yet" tags, and
rules written without an RFC 2119 capital, which the lint does not see.

## 6. Writing the handbook

### 30. Cite the decision behind every rule

Each handbook rule links to the ADR that decided it. Where nothing is decided, the chapter lists the point under a
"Not decided here" heading and states no rule.

Why: readers can trust a rule, and can find who to ask about the rest.

Sources: [Architectural Decision Records](https://adr.github.io/).

How the stack holds it: `Documented`. Every chapter in `handbook/` follows it, including `accessibility.md` and
`runtime-and-headers.md`.

### 31. Record open questions with an owner

Undecided matters go in `DECISIONS.md` with who can answer, and become an ADR when decided.

Why: an open question with no owner stays open.

Sources: [Architectural Decision Records](https://adr.github.io/).

How the stack holds it: `Documented`. The stack's open questions are recorded with an owner, and become ADRs when
decided.

### 32. Link each rule to the command that measures it

Write the rule, then the PROCTOR check ID that tests it, or say plainly that none exists.

Why: a standard is a command that fails, not a sentence in a README.

Sources: [OWASP ASVS](https://asvs.owasp.org/) (requirements written to be verified).

How the stack holds it: `Partially`. Every handbook rule that uses an RFC 2119 capital names, beside the rule, the
PROCTOR check that enforces it, the other point that holds it ("Held by:"), or the plain words "No check exists yet",
and the handbook lint fails when one names none (practice 29). What remains: the checks behind the "No check exists
yet" tags.

### 33. Judge with deterministic checks only

No gate depends on a language model's opinion. A model may explain a failure; it does not decide pass or fail.

Why: a gate must give the same answer twice.

Sources: none outside the stack; this is the stack's rule on language models.

How the stack holds it: `Documented`. The stack's rule on language models and `ci.md` state it. The checks are
deterministic by design.

### 34. Keep every developer tool usable on Windows, macOS and Linux

Scripts ship as a `.ps1` and `.sh` pair, or as one PHP command. Every repo carries `.gitattributes` with
`* text=auto eol=lf`.

Why: developers work on all three, and Citadel needs LF.

Sources: none outside the stack; this is the stack's every-OS design.

How the stack holds it: `Partially`. The stack's every-OS design and `every-os.md` state it, and CREED has the
`.gitattributes`. PROCTOR 0.10.0's `proctor/crlf-deploy-script` fails when a deploy script (`build.sh`, `release.sh`, or
a `.sh` file under `deploy/`, `deployments/`, `deploy_scripts/` or `scripts/`) has Windows line endings, and warns
when the app has a deploy script and `.gitattributes` does not pin `*.sh` to LF. PROCTOR 0.9.0's `case-clash` fails
when two paths differ only by letter case. PROCTOR main's `gitattributes-eol`, which ships in PROCTOR's next release,
reads `.gitattributes` the way Git applies it and warns when the app's `*.sh`, `*.conf`, `Dockerfile` or
`*.Dockerfile` files are not pinned to `eol=lf` (`proctor/gitattributes-eol/not-pinned`), and when a Dockerfile or
`.conf` under `deploy/` or `.squire/` has Windows line endings (`proctor/gitattributes-eol/crlf`). No check asks for
the whole `* text=auto eol=lf` line or for the `.ps1` and `.sh` pair yet.

### 35. Run the test suite on every supported PHP version

CI runs the unit tests on 8.3, 8.4 and 8.5, from CREED's own floor up.

Why: a library that works only on the newest PHP excludes half its users.

Sources: [PHP supported versions](https://www.php.net/supported-versions.php),
[Laravel 13 database docs](https://laravel.com/docs/13.x/database) (second pass: its MySQL config picks
`\Pdo\Mysql::ATTR_SSL_CA` on PHP 8.5 and `PDO::MYSQL_ATTR_SSL_CA` before it, the kind of version difference a matrix
catches).

How the stack holds it: `Checked`. The `php-test` component runs a matrix, and CREED's pipeline includes it.

### 36. Recommend a git.wayne.edu instance runner

CI is a gate only if something runs it. Every product states the recommendation.

Why: a gate that nothing runs protects nobody.

Sources: [GitLab Code Quality docs](https://docs.gitlab.com/ci/testing/code_quality/) (the report is produced by a
pipeline job, so it needs a runner).

How the stack holds it: `Documented`. The README and `ci.md` state it. git.wayne.edu now has an instance runner and
CREED's CI runs on it; nothing checks that another product's pipeline does.

## 7. Patching and the supply chain (second pass)

### 37. Track the PHP patch release, not only the branch

A supported branch is not a patched one. On 2026-10-21 the PHP project released 8.5.12, 8.4.27, 8.3.36 and 8.2.35 to
fix CVE-2026-109618 and CVE-2026-109619. Both are rated CVSS v4.0 9.2 and can allow unauthenticated remote code
execution. php.net says `mod_php` carries the highest risk, that updating is the only complete fix, and that the partial
mitigations it lists (`session.upload_progress.enabled=0`, `memory_limit=-1`) do not make an installation safe. Two
follow-ups matter too. On 2026-10-23 php.net recommended `session.upload_progress.enabled=0` for CVE-2026-115560 (it needs
an unusual session setup). On 2026-10-26 it released Windows rebuilds (the `-1` builds) with Brotli 1.2.1 for
CVE-2026-111497, which matters for Windows developer machines.

Why: the branch table says "supported" both before and after a critical fix is applied. Only the patch number tells the
two apart.

Sources: [PHP 2026 news archive](https://www.php.net/archive/2026.php),
[endoflife.date PHP API](https://endoflife.date/api/v1/products/php/) (lists the latest patch per branch, all dated
2026-10-21).

How the stack holds it: `Documented`. `policy.json` now carries a `latest_patch` and a `latest_patch_checked` date for
each supported PHP version (and for Laravel and Node; EOL PHP 8.1 has none), and `bin/refresh-patches.php` keeps them current from php.net, Packagist,
nodejs.org and endoflife.date (handbook: `versions.md`, "Latest patches"). It is informational: a version's state is
still derived from the dates alone, and no PROCTOR check fails an image that is behind the latest patch yet. This pass
did not verify whether Citadel's PHP images were rebuilt after 2026-10-21.

### 38. Patch the framework inside a stated window, not only to a supported branch

Laravel published a critical advisory on 2026-10-19 (GHSA-f9x3-7vq9-cph6, CVE-2026-114509, CVSS v4 9.2): the token-based
API guard can be bypassed. On MySQL and MariaDB a crafted non-string credential can match a different user record, so an
unauthenticated caller can act as an existing user on any route behind that guard. It affects Laravel below 13.29.0 and
below 12.68.0. Laravel's advisory list shows five advisories published in 2026, including a high-severity CRLF injection
in the default email rule (2026-06-01).

Why: Laravel 12 and 13 are both inside their security windows (practice 15), and both were affected. The stack uses MySQL,
the database the advisory names.

Sources: [Laravel GHSA-f9x3-7vq9-cph6](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6),
[Laravel framework advisories](https://github.com/laravel/framework/security/advisories).

How the stack holds it: `Not yet`. Detection is practice 20 and is `Checked` when the advisory reaches the feed
`composer audit` reads (this pass did not check how fast that happened). The response window is missing: `rules` in
`policy.json` has no "fix within N days" per severity, and the number is for the maintainers to decide.

### 39. Keep the Composer client patched, and let it block insecure installs

Composer's own tooling had flaws in 2026. On 2026-04-14 Composer 2.9.6 and 2.2.27 (LTS) fixed two command injection
flaws in the Perforce driver, CVE-2026-40176 and CVE-2026-40261. Composer runs that code even when Perforce is not
installed. Packagist stopped publishing Perforce source metadata on 2026-04-10. The advised mitigation for
CVE-2026-40261 is to avoid source installs (`--prefer-dist` or `preferred-install: dist`). Composer's releases page lists
more security fixes in 2.10.1 to 2.10.3 (it shows no year on those dates). Composer 2.9 also turned on
`audit.block-insecure` by default, so `update`, `require` and `remove` refuse versions with known advisories.

Why: CI runs Composer with the pipeline's credentials, on code and metadata the team did not write.

Sources: [Packagist: Composer 2.9.6 Perforce fixes](https://blog.packagist.com/composer-2-9-6-perforce-driver-command-injection-vulnerabilities/),
[Composer 2.9 release post](https://blog.packagist.com/composer-2-9/),
[Composer releases](https://github.com/composer/composer/releases),
[Composer config reference](https://getcomposer.org/doc/06-config.md#audit).

How the stack holds it: `Checked`. PROCTOR's `composer-version` check fails a Composer below the higher of its CVE
floors and CREED's policy minimum (`tools.composer.minimum`, 2.8.0), naming the policy in its message. CREED's own
pipeline runs the `composer:2.11.1` image, pinned by digest.

### 40. Treat a brand-new package version as untrusted for a short time

Attackers now publish through accounts and tokens they took over. Packagist's 2026-05-27 post names the compromise of
`intercom/intercom-php` (2026-04-30) and `laravel-lang` (2026-05-22), and says attackers took over GitHub accounts and used
stolen tokens to publish new tags. Packagist responded with malware warnings imported from Aikido (since March 2026), a
public transparency log, and tag immutability for stable versions. It also warns that `composer update` refreshes lock
entries, so a lock file alone does not protect against a re-tagged version. A cooldown policy (`policy.cooldown.period`,
for example `"7 days"`) is documented on Composer's config page; the releases page lists it in 2.11.0-RC1, a pre-release,
while 2.10.3 is the stable release shown. Dependabot's cooldown supports Composer and defaults to 3 days, so it is a
usable control now. On the front-end side, the Shai-Hulud worm (GitHub was notified on 2025-09-14)
spread through post-install scripts, and GitHub removed more than 500 compromised packages.

Why: Packagist says malicious versions are often caught within hours, so a short delay can remove the window. It does not
remove it every time: one report this pass read says hijacked npm packages in a later variant lived for 3 to 12 hours.

Sources: [Packagist supply chain security update](https://blog.packagist.com/an-update-on-composer-packagist-supply-chain-security),
[Composer config, `policy`](https://getcomposer.org/doc/06-config.md#policy),
[Composer releases](https://github.com/composer/composer/releases),
[Dependabot options reference](https://docs.github.com/en/code-security/dependabot/working-with-dependabot/dependabot-options-reference),
[GitHub: plan for a more secure npm supply chain](https://github.blog/security/supply-chain-security/our-plan-for-a-more-secure-npm-supply-chain/),
[OWASP Top 10:2025](https://top10.owasp.org/2025) (A03, Software Supply Chain Failures).

How the stack holds it: `Not yet`. No setting in `policy.json` or in CI delays new versions. Decided 2026-11-03: a 3 day
cooldown once Composer 2.11 is stable and in the base images, with Sourdough apps setting it and PROCTOR warning when it
is missing (PROCTOR plan 4.11). Composer 2.11.0 shipped on 2026-10-29 (2.11.1 on 2026-10-30) with
`policy.cooldown.period`, but nothing sets it yet: the stack's images and CI still pin Composer 2.10.3, and moving them
to 2.11 is the next step. The lock file
(practice 18) is necessary but, per Packagist, not enough.

### 41. Set the front-end install options, and map severity words

For Node builds, npm's v11 config page documents `min-release-age` (a number of days), `ignore-scripts` (default `false`)
and `audit-level` (`info`, `low`, `moderate`, `high`, `critical` or `none`). The page does not say which npm version added
`min-release-age`. Test the build before turning on `ignore-scripts`, because some packages run build steps on install.
Note the vocabulary gap: npm says `moderate` where Composer's policy page says `medium`. A single
`audit_severity_threshold` string in `policy.json` needs a mapping before more than one tool reads it.

Why: build tools run with your credentials and your source (practice 16), and the worm above relied on install scripts.

Sources: [npm config](https://docs.npmjs.com/cli/v11/using-npm/config),
[Composer config, `policy`](https://getcomposer.org/doc/06-config.md#policy).

How the stack holds it: `Partially`. The install options are built into the release: Squire's release script defaults
`SQUIRE_NPM_IGNORE_SCRIPTS=1` (deploy/scripts/release.sh:166), takes `--ignore-scripts` from it (:412), installs with
`npm ci --prefer-offline --no-audit --no-fund` plus the flag from the committed lock file (:445), fails a `package.json`
without a lock file (:462), and allows install scripts back by package name through `SQUIRE_NPM_REBUILD` (:448). The Vue
layer's `.npmrc` sets `min-release-age=1` (templates/vue/.npmrc:11), and `squire doctor` rule S57 reads the script's code
and the file and passes or warns on both (src/Doctor/Check/ReleaseScriptCheck.php:53-59, BEST_PRACTICES.md:31). Sourdough
ships the script unchanged into every generated app (sourdough BEST_PRACTICES.md:203,
templates/squire/laravel/scripts/release.sh:419,436). The generated pipelines install the same way: Sourdough's Vue
templates run `npm ci --ignore-scripts --no-audit --no-fund` and then `npm audit --audit-level=high --omit=dev`, which
fails the job on a high or critical advisory in a runtime dependency (sourdough
templates/laravel-blade-vue/.gitlab-ci.yml:112-114, templates/laravel-inertia/.gitlab-ci.yml:113-115,
templates/raw-vue/.gitlab-ci.yml:104-106). The rest is written down only: WELD's own CI runs
`npm ci --no-audit --no-fund` without `--ignore-scripts` and with no audit step (weld .gitlab-ci.yml:111),
Preflight 0.4.0 has no component that audits `package-lock.json` (its thirteen templates are a11y, composer-audit,
eslint, package-publish, php-test, phpstan, pint, portability, proctor, renovate, sbom, secret-detection and
squire-kit), and the `audit_severity_threshold` rule is read by the Composer audit alone (proctor README.md:111), so the
mapping between npm's `moderate` and Composer's `medium` is unwritten. (Sourdough grades the same release install
`Documented` from its own seat, sourdough BEST_PRACTICES.md:203; the tag here counts the built code in the stack.)

### 42. Gate on tools that work on every GitLab tier

GitLab's docs list dependency scanning and secret push protection as Ultimate. GitLab's security policies (scan execution,
merge request approval, pipeline execution, vulnerability management, dependency firewall) are Ultimate-only, so CREED does
not depend on them. Pipeline secret detection is listed for
Free, Premium and Ultimate, but its findings in merge request reports and the vulnerability report are Ultimate only. GitLab
accessibility testing (Pa11y) is listed for Free, Premium and Ultimate. Both of the stack's GitLab instances (nougat and
git.wayne.edu) run GitLab 19.5.1 Community Edition, which is the Free tier.

Why: a gate that needs a license the instance may not have is a gate that never runs.

Sources: [GitLab dependency scanning](https://docs.gitlab.com/user/application_security/dependency_scanning/),
[GitLab security policies](https://docs.gitlab.com/user/application_security/policies/),
[GitLab secret detection](https://docs.gitlab.com/user/application_security/secret_detection/),
[pipeline secret detection](https://docs.gitlab.com/user/application_security/secret_detection/pipeline/),
[secret push protection](https://docs.gitlab.com/user/application_security/secret_detection/secret_push_protection/).

How the stack holds it: `Documented`. The rule is written in `handbook/code.md`: the stack's GitLab instances run the
Free tier, so nothing relies on GitLab SAST. Both instances, nougat and git.wayne.edu, are GitLab 19.5.1 Community
Edition (the Free tier). The three components CREED
uses run PHPUnit (`php-test`), `composer audit` (`composer-audit`) and PROCTOR (`proctor`), not a GitLab scanner
(their Preflight 0.3.0 templates were read on 2026-11-08), so they fit it.

### 43. Treat `APP_KEY` as a code-execution key

GitGuardian's research with Synacktiv found that Laravel's `decrypt()` deserializes its output, so a leaked `APP_KEY`
plus known PHP gadget chains can give remote code execution. GitGuardian reports that about 260,000 `APP_KEY` values were
extracted from GitHub and that this led to roughly 600 trivially compromisable apps; 63 percent of the exposures came from
`.env` files. It also found that 50 keys deleted from repositories were still valid in production. Press coverage quotes
larger numbers than GitGuardian's own page does; use the page. It lists `SESSION_DRIVER=cookie` as a risk factor (it cites
CVE-2024-55556).

Why: deleting a commit does not rotate a key.

Sources: [GitGuardian, exploiting public APP_KEY leaks](https://blog.gitguardian.com/exploiting-public-app_key-leaks/),
[Gitleaks](https://github.com/gitleaks/gitleaks) (MIT license; its page says it is feature complete and will get security
patches only. It is one option, not an endorsement. Windows support is not stated on the page, so check it before
adopting).

How the stack holds it: `Checked`. PROCTOR main's `proctor/app-key` warns when a real env file is committed
(`committed-env`), when a committed env file or `config/app.php` carries a real `APP_KEY` or `APP_PREVIOUS_KEYS`
(`committed-key`; the key is never printed), and when the session driver is `cookie` (`cookie-sessions`). An
uncommitted `.env` on a laptop is never graded. Rotating a key that leaked stays a process: the check names
`php artisan key:generate` but cannot see whether it ran.

### 44. Pin base images and rebuild them on a schedule

Docker's best-practices page says tags are mutable, that pinning a digest guarantees the same image, and that pinning also
means you opt out of automatic fixes. It tells teams to rebuild regularly with `--pull` (and `--no-cache` for a full
refresh). Practice 37 shows why: a PHP image built last month still contains last month's PHP.

Why: pinning without rebuilding freezes the bugs; rebuilding without pinning changes the app without a commit.

Sources: [Docker build best practices](https://docs.docker.com/build/building/best-practices/).

How the stack holds it: `Partially`. `policy.json` fills `images.docker` for PHP 8.3 to 8.5 with the official images,
and the rebuild rule is written in `handbook/versions.md`; `tests/PolicyImagesTest.php` keeps the entries valid. The
`citadel` names wait for Citadel to publish its image names.

### 45. Treat the CI host as part of the supply chain

A pipeline that holds deploy credentials is only as safe as the GitLab server and runners behind it. BleepingComputer
(2026-10-22) reports CVE-2026-110264, an integer overflow in GitLab's regular expression compiler that could let an
unauthenticated user run code on the server. It lists fixes in 19.5.1, 19.4.3, 19.3.6, 19.2.9, 19.1.9, 19.0.10 and
18.11.13, and says an earlier CVSS 10 flaw in the CI/CD regex compiler is CVE-2026-97229. This is a secondary source: GitLab's
own patch pages could not be read for details in this pass, so confirm on GitLab's site. GitLab publishes a patch-release
RSS feed.

Why: a compromised CI host defeats every other gate in this guide.

Sources: [BleepingComputer on CVE-2026-110264](https://www.bleepingcomputer.com/news/security/gitlab-warns-of-new-max-severity-code-execution-vulnerability/),
[GitLab releases](https://docs.gitlab.com/releases/patches/).

How the stack holds it: `Documented`. The patch level is no longer unknown to the guides: PROCTOR's best practices
record that the stack's own instances run GitLab 19.5.1 CE, next to the 18.7 floor (proctor BEST_PRACTICES.md:855-859,
added 2026-11-07), and 19.5.1 is among the releases the BleepingComputer report names as fixing CVE-2026-110264 (a
secondary source, as before). It stays out of reach of the code: PROCTOR cannot see the server version (proctor
BEST_PRACTICES.md:871-873), no check reads it, and the runners' patch level is still not stated (CREED
handbook/ci.md names the instance runner but not its patch level). Listed so that the owners of the instance see it, and
so the line gets re-checked when a patch release lands.

## 8. Platform and database (second pass)

### 46. Check the framework's database minimum against the platform database

Laravel 13's database page lists first-party support for MySQL 5.7+, MariaDB 10.3+, PostgreSQL 10.0+, SQLite 3.26.0+ and
SQL Server 2017+. The stack's shared MySQL is 5.6, which `policy.json` marks as past its security window since 2021-02-28
and which is below that line. This pass read the Laravel 13 page only, not the 12 page.

Why: the version table in practice 14 pairs Laravel with PHP but never with the database, and the database is the part
the platform owns.

Sources: [Laravel 13 database docs](https://laravel.com/docs/13.x/database), [endoflife.date MySQL](https://endoflife.date/mysql).

How the stack holds it: `Partially`. `policy.json` records the minimum MySQL and MariaDB for each Laravel release
(Laravel 10 to 13), and the reader exposes them through `requires()`; `tests/PolicyRequiresTest.php` pins the values.
PROCTOR 0.10.0's `mysql-minimum` warns when an app's declared MySQL or MariaDB image (in a compose file,
`.env.squire.example` or `.env.example`) is below its locked Laravel line's minimum; it warns only and is not in the deploy gate. The platform's
MySQL timeline is still an open question.

### 47. Plan the MySQL move around authentication and LTS lines

`mysql_native_password` is deprecated as of MySQL 8.0.34, disabled by default in 8.4, and removed as of 9.0.0 (it can be
re-enabled on 8.4 with `--mysql-native-password=ON`). Any account or client still using it must change before 9.7. Per a
search summary of the 9.7 manual, upgrading to the next LTS series is supported and skipping an LTS series is not; that
line was not read on the page itself. endoflife.date also lists short-lived non-LTS lines (9.6, ended 2026-04-21; 26.7,
ended 2026-10-20; 26.10, released 2026-10-20 with no end date set). Oracle's own wording for those lines was not read.

Why: the move off 5.6 will touch accounts, drivers and the upgrade path in one go.

Sources: [MySQL 9.7: native pluggable authentication](https://dev.mysql.com/doc/refman/9.7/en/native-pluggable-authentication.html),
[endoflife.date MySQL](https://endoflife.date/mysql).

How the stack holds it: `Documented`. `policy.json` lists the LTS lines 8.4 and 9.7 and not the short-lived ones, and
`handbook/databases.md` writes down the rule and the authentication-plugin step.

### 48. Say how fast the platform adds a new PHP, and how it announces a drop

University of Wisconsin-Madison's web hosting says it typically supports a new PHP version within one month of release.
MediaWiki drops a PHP version only in a major release, announces it at least two weeks before the change merges, and
requires each major release to support a PHP that upstream supports for the whole cycle, plus one from Debian LTS and one
from Ubuntu LTS.

Why: owners can plan only against numbers that were promised.

Sources: [UW-Madison web hosting, PHP supported versions](https://kb.wisc.edu/webhosting/page.php?id=135335),
[MediaWiki support policy for PHP](https://www.mediawiki.org/wiki/Support_policy_for_PHP).

How the stack holds it: `Not yet`. The handbook gives no target for Citadel adding a new PHP image, and no notice period
for retiring one. PHP 8.5 was released on 2025-11-20.

## 9. Policy data (second pass)

### 49. Model Node's new yearly schedule before Node 27 arrives

The Node release repo describes a new schedule starting with Node 27: one new line a year, an Alpha phase before the
first Current release, every line promoted to LTS, 30 months of Active LTS, and no Maintenance phase. The repo lists
27.x with Alpha from 2026-10-28, Current from 2027-04-22, LTS from 2027-10-20 and end of life on 2030-04-30, and says the
dates may change. Node 26 follows the old model.

Why: `policy.json` stores Node's maintenance start in `active_until`. For 27 and later there is no maintenance start, so
the current mapping has nothing to copy. The maintainers must pick a rule and write it down.

Sources: [Node.js release schedule](https://github.com/nodejs/Release).

How the stack holds it: `Documented`. Node 26 is in `policy.json` correctly. Decided 2026-11-03 and written in
`handbook/versions.md`: policy lists only even Node majors, which are the LTS lines; odd majors are never a target and
are not listed, so no mapping rule is needed for 27.

### 50. Design the freshness check around a real feed

endoflife.date serves JSON at `/api/v1/products/<product>/`. Each release has `releaseDate`, `isLts`, `eoasFrom`, `eolFrom`,
`isMaintained` and a `latest` patch with its date. For PHP, `eoasFrom` and `eolFrom` equal php.net's two columns for 8.2 to
8.5. For MySQL the labels object holds `eoas`, `discontinued`, `eol` and `eoes`; the exact strings were not re-read this
pass. For MySQL, the job should write `eoasFrom` (end of Premier support) to `active_until` and `eolFrom` (end of
Extended support) to `security_until`; `policy.json` has no Premier or Extended labels of its own. The job should filter out lines the policy
deliberately omits, and can also read `latest` to feed the patch floor in practice 37.

Why: practice 8 says "compare with a feed" but not what to compare or what to ignore.

Sources: [endoflife.date PHP API](https://endoflife.date/api/v1/products/php/), [endoflife.date MySQL API](https://endoflife.date/api/v1/products/mysql/).

How the stack holds it: `Partially`. `bin/refresh-policy.php` calls the API and classifies every change before writing
it, and the `policy-bot` job runs it (on the robot's schedule and from the web); without `CREED_BOT_TOKEN` the job
prints a notice and stays green. endoflife.date is a community aggregator, so the job reports differences and a human confirms them against the
vendor page (practice 6).

### 51. Decide whether the schema is strict or tolerant, and test both readers

See practice 10. Strict validation catches typos. Tolerant validation lets an old tool read a new file. The schema is
strict today and `schema_version` is pinned to `1`.

Why: "add fields, never rename" fails in practice if the strict schema is the thing older tools run.

Sources: [Semantic Versioning 2.0.0](https://semver.org/),
[JSON Schema introduction](https://json-schema.org/understanding-json-schema/about).

How the stack holds it: `Enforced`. Decided 2026-11-03: strict where the file is published, tolerant where it is read.
The schema forbids unknown keys, and `tests/PolicySchemaTest.php` rejects a stray key in CI;
`tests/OlderReaderContractTest.php` loads a future-shaped policy file with the reader and asserts every known line
still answers the same.

## 10. Accessibility and the legal floor (second pass)

### 52. Know the legal floor before writing the accessibility chapter

The ADA.gov Title II web rule page names WCAG 2.1 Level AA as the technical standard for state and local government
web content and mobile apps. An April 2026 interim final rule moved the compliance dates to 2027-04-26 for entities with
a population of 50,000 or more, and to 2028-04-26 for smaller entities and special districts. Search summaries say the
rule covers public colleges and universities and that the interim rule was published on 2026-04-20; one vendor blog says
a court challenge to the extension is pending, which is single-sourced and was not verified. Whether and when the rule
applies to a given WSU unit is for WSU's counsel and accessibility office. This guide does not decide it.

Why: the chapter currently says "To be decided"; the floor it must meet is already published.

Sources: [ADA.gov, Title II web rule first steps](https://www.ada.gov/resources/web-rule-first-steps/).

How the stack holds it: `Not yet`. `handbook/accessibility.md` states the floor and leaves the Title II dates open.

### 53. Run an automated accessibility check in CI

GitLab's CI accessibility testing uses Pa11y, an open source tool, and is listed for Free, Premium and Ultimate on
self-managed GitLab. An automated tool catches regressions; it is not proof of conformance.

Why: accessibility is a runnable gate for WayneUI (the stack's interface layer), and app pages built on it need the same.
WayneUI's own gate is outside CREED and was not reviewed in this pass.

Sources: [GitLab accessibility testing](https://docs.gitlab.com/ci/testing/accessibility_testing/).

How the stack holds it: `Checked`. Preflight 0.4.0's `a11y` component opens the app's pages in Chromium (Playwright),
in light and dark at 320 and 1280 px, runs axe-core and fails the pipeline on any violation; CREED's pipeline pins
the component to that tag and asks it for WCAG 2.2 AA (practice 54). It is axe-core, not GitLab's Pa11y job. CREED
runs that same checker on its own site: `scripts/a11y.sh` and `scripts/a11y.ps1` drive the copy vendored in
`tools/a11y/check.mjs`, pinned to the same Preflight tag by `tests/A11yCheckerPinTest.php`, so a page that passes
locally passes CI.

### 54. Aim at WCAG 2.2 AA

W3C's WCAG 2.2 Recommendation (the edition read is dated 2024-12-12) states that content that conforms to 2.2 also
conforms to 2.1 and 2.0. Aiming at 2.2 AA therefore meets the 2.1 AA floor in practice 52.

Why: it avoids a second migration.

Sources: [WCAG 2.2](https://www.w3.org/TR/WCAG22/).

How the stack holds it: `Documented`. The target is written in `handbook/accessibility.md`.

## 11. WSU developer standards (2024)

These practices come from the university's 2024 development standards. They were compared with CREED on 2026-11-02 and
are now in the handbook; the full comparison is internal.

### 55. Keep credentials and diagnostic output out of version control, the web root and the logs

Credentials (database and service passwords, API keys, tokens) are never committed to version control, because history
keeps them forever. Commit `.env.example` with placeholders instead. Credentials and sensitive data live outside any
folder the web server serves. Logs and diagnostic output carry no credentials and no personally identifiable
information, and that includes logged exceptions. A credential that was ever committed is treated as exposed and
rotated.

Why: a repository is copied to many machines, the web root is reachable by anyone, and a log outlives the session that
wrote it. A leaked password is a bad encore: everyone remembers it, and nobody asked for a second showing.

Sources: the WSU developer standards, 2024 (an internal WSU document), [OWASP ASVS](https://asvs.owasp.org/).

How the stack holds it: `Partially`. `handbook/secrets.md` writes the rule. PROCTOR main warns on committed env files and
APP_KEYs (`proctor/app-key`, practice 43), on tokens in package source URLs (`proctor/repo-url-credentials`), and on
dumps, backups, logs and env files in the served folder (`proctor/served-folder`, practice 59), and Preflight 0.4.0's
`secret-detection` component fails a pipeline on a committed secret Gitleaks recognizes. What a log or an
exception carries at runtime is not something a static check can see.

### 56. Validate every input and treat uploaded files as hostile

All input (forms, API calls, headers, cookies, and data read from a database you do not control) is validated on the
server. SQL uses bound parameters, and output is escaped for where it lands. Files from untrusted sources are checked
against the expected type, are never passed to include, class-loading or reflection calls, are stored outside the
served folder with limited permissions, and are never executable.

Why: unvalidated input is how SQL injection and cross-site scripting happen, and an uploaded file that runs is a back
door.

Sources: the WSU developer standards, 2024 (an internal WSU document), [OWASP ASVS](https://asvs.owasp.org/).

How the stack holds it: `Checked`. `handbook/code.md` writes the rule, and Pinstripe 0.3.0 bans SQL built by joining
strings in every cut (`pinstripe.banned.joinedSql`): a variable concatenated, interpolated or `sprintf()`'d into the
SQL given to PDO, mysqli, `oci_parse()` or Laravel's `DB` methods fails PHPStan.

### 57. Check authentication first on every request, fail closed, and time out idle sessions

Authentication is checked before any content is shown or any script function runs, on every request. Routes are
protected unless listed as public, and an unauthenticated person is sent to sign in. When authentication or an
authorization lookup fails, the result is fail closed: access is withheld. Idle sessions time out after a configurable
period of inactivity. For APIs and other stateless requests, the standards ask for signed, short-lived bearer tokens,
which matches the signed assertion the `gateway` driver accepts.

Why: a page that shows content before checking who is asking shows it to everyone.

Sources: the WSU developer standards, 2024 (an internal WSU document), [OWASP ASVS](https://asvs.owasp.org/).

How the stack holds it: `Documented`. `handbook/auth.md` writes the rule, and its `gateway` driver takes only a signed
assertion.

### 58. Keep two environments, isolate each deployment, and write down backup and restore

Keep at least a pre-production and a production environment, each with its own working copy of every piece, database
and supporting services included. Each deployment is isolated from the others (one container per app), so one
compromise does not become many. Back up anything kept outside source control, databases included, and say in the
README how to restore one app.

Why: a staging copy is where a broken deploy is noticed, and an isolated box is where a compromise stays.

Sources: the WSU developer standards, 2024 (an internal WSU document).

How the stack holds it: `Documented`. `handbook/production.md` writes the rule.

### 59. Run as non-root, never use mode 777, and keep debug files out of the served folder

The app and its scripts run as a non-root, non-administrator user with the least access they need. File permissions
are set per path, write access for the web server goes to specific files or folders only, and mode `777` is never
used. Debugging and diagnostic files, scripts, dumps and backups do not sit in the served folder.

Why: root and world-writable files turn a small mistake into a full compromise, and debug files in the served folder
are read by strangers.

Sources: the WSU developer standards, 2024 (an internal WSU document).

How the stack holds it: `Partially`. `handbook/production.md` writes the rule. PROCTOR main's `proctor/served-folder`
warns when a committed file under `public/` is a dump, a backup or editor copy, a log, an env file or `adminer*.php`, or
calls `phpinfo()`, and `proctor/world-writable` warns on `chmod 777` in scripts, Dockerfiles, CI and composer or npm
scripts, and on a PHP `chmod()` with `0777`. Whether the app runs as a non-root user is a fact of the running
container, which no static check can see.

### 60. Monitor `/up`, rotate logs and keep an audit trail

A monitor calls `/up` on a schedule and alerts someone when the app fails. Logs are rotated or removed at a set
interval, temporary files are cleaned up, and uploaded files have a retention rule. Where it is practical, the app
records an audit trail of changes to data and of successful and failed access to sensitive data.

Why: a failure nobody hears about is a failure that lasts, and a log that grows forever fills the disk it was meant to
protect.

Sources: the WSU developer standards, 2024 (an internal WSU document), [OWASP ASVS](https://asvs.owasp.org/).

How the stack holds it: `Documented`. `handbook/production.md` writes the rule.

### 61. Keep the project readable: README, a Help Desk overview, reviewed merge requests, removed dead code

Every repo's README says how to install, configure and deploy the code for local development, on every OS, and how to
update an existing deployment. If the Help Desk might be contacted about the project, the repo keeps an overview: what
it does, who uses it, known common issues and who to call for an incident. Every project has a defined,
well-understood change-control process with change requests; review and approval of merge requests is change
control only for simple projects, and the process is followed strictly. The change-request procedure on Citadel
is currently broken and needs fixing there; until it is fixed, changes are recorded through reviewed merge
requests. Code that no longer runs is removed, not commented out.

Why: the next person to touch the code is the reader, and a project nobody can read is a project nobody can fix.

Sources: the WSU developer standards, 2024 (an internal WSU document).

How the stack holds it: `Documented`. `handbook/code.md` and `handbook/ci.md` write the rule.

## Checks against the web

These are places where a source disagrees with the repository or with an earlier note.

- **Laravel 13, Node 26 and MySQL 9.7 were missing from `policy.json` when the first pass was written; they are there
  now.** Second pass: all three entries match the vendor pages. Laravel 13: released 2026-03-17, security fixes to
  2028-03-17 (the page shows bug fixes as "Q3 2027"; the policy holds 2027-09-30, recorded from endoflife.date on
  2026-10-29; see the resolved note below). Node 26: LTS from 2026-10-28, maintenance from 2027-10-20, end of life 2029-04-30.
  endoflife.date shows 2027-10-27 for the same field ([endoflife.date Node API](https://endoflife.date/api/v1/products/nodejs/)).
  Settled 2026-11-02: the primary source, `schedule.json` in nodejs/Release, lists `"maintenance": "2027-10-20"` for v26, so the policy value is right and the aggregator differs.
  MySQL 9.7: Premier to 2031-04-30 and Extended to 2034-04-30 on endoflife.date. The stack's version policy says
"Q1 2028" for Laravel 13;
  the page gives the day.
- **Resolved: Laravel 13 `active_until` is 2027-09-30 in `policy.json`.** The Laravel release page gives "Q3 2027" for
  bug fixes and no day. The date came from another source: the refresh of 2026-10-29 recorded 2027-09-30 from
  endoflife.date in `policy/sources.json`, so the date now carries its source (practice 6).
- **PHP dates match.** 8.1 to 8.5 agree with php.net and the EOL table. Second pass: 8.2 to 8.5 were re-read on
  php.net's supported-versions page and match `policy.json`; the endoflife.date PHP API agrees.
- **Dates re-checked this pass:** Laravel 10 to 12 (release, bug-fix and security dates) match the Laravel table. Node 22,
  24 and 26 match the Node repo. MySQL 5.6, 5.7, 8.0, 8.4 and 9.7 match endoflife.date. Node 20 and the Vue 2 date were
  not re-checked.
- **Vue 3 is listed as a current line with no dates** (resolved after the second pass: `"current": true`). Vue 2 is
  correct (EOL 2023-12-31). Second pass: settled. The vuejs/core releases page shows 3.5.44 as the latest stable release
  and 3.6.0 only as release candidates (rc.6 to rc.13). A secondary article that said 3.6 shipped on 2026-07-18 does not
  match the official page; ignore it.
- **Composer audit exit codes: the sources differ because they come from different eras.** The 2.8.4 changelog says
  "1 for vulnerabilities and 2 for abandoned, 3 for both". The current CLI reference lists only 0 (no issues) and 1
  (found packages matching dependency policies, or missing required packages). The config page now describes a `policy`
  system that replaced the old `audit` keys. Which behavior a pipeline gets depends on the Composer version. Pin the
  Composer version in CI and test the behavior (practice 39).
- **Policy "active" for Node and MySQL is a mapping.** `active_until` holds Node's maintenance start and MySQL's Premier
  end. This is a reasonable mapping and is not the vendors' own wording.

## Gaps for the backlog

1. Done in the repository after the first pass: Laravel 13, Node 26 and MySQL 9.7 are in `policy.json` (practice 7). The
   process gap remains (items 19 and 22).
2. Done: a scheduled job checks the latest patch levels against the upstream feeds and fails when they are stale;
   the policy robot runs the full refresher every Monday, pushes to `main` when `composer ci` is green and opens a
   merge request when it is red; its first refresh landed on 2026-11-08 (practice 8).
3. Done: the schema allows `"current": true` lines with no end date, and Vue 3, Inertia 2 and Inertia 3 are listed (practice 9).
4. Done: `policy/sources.json` records each source with its first_seen and last_seen, and `policy.json` keeps `latest_patch_checked` (practice 6).
5. Done: policy.json records the supported PHP range per Laravel release, and PROCTOR main's `laravel-php` compares an app's `require.php` with it (practice 14).
6. Done: PROCTOR main's `laravel-eol` reads the Laravel line from `composer.lock` and grades it through the policy (practice 15).
7. Done: PROCTOR main's `node-eol` grades the Node version in `.nvmrc`, `.node-version`, `volta.node`, CI and
   Dockerfile images, and `engines` (practice 16).
8. Partly done: PROCTOR main's `mysql-eol` grades the MySQL an app names, warning only; a platform report waits for the
   platform timeline (practice 17).
9. Done: `rules.abandoned_packages` is `fail`, and PROCTOR main's `composer-audit` applies it (practice 21).
10. Done: Preflight 0.2.0's `secret-detection` component runs Gitleaks and fails on a match (practice 24).
11. Done: PROCTOR main's `env-outside-config` warns on `env()` outside `config/` (practice 25). Larastan's own rule in
    Pinstripe would catch it earlier, in the editor.
12. Done: the runtime and headers chapter is written (practice 26), PROCTOR's `app-debug` warns on a committed
    production template, deploy file or CI pipeline that sets `APP_DEBUG` to true, and HELMET warns at boot when it is
    on in production; `app-debug` shipped in PROCTOR 0.10.0.
13. Partly done: every handbook rule that uses an RFC 2119 capital carries a tag (a PROCTOR check ID, "Held by:" or
    "No check exists yet"), and the handbook lint fails on an untagged one; rules phrased without a capital are still
    to walk (practices 29 and 32).
14. Done: `tests/OlderReaderContractTest.php` loads a future-shaped policy file with the reader and asserts every
    known line still answers the same (practice 10).
15. Done: the policy's `gate` block carries per-check stages (`report`, `warn`, `block`) with `from` dates, in the
    schema and the reader, and `proctor gate` (PROCTOR 0.9.0) applies them; no rule is staged yet (practice 28).
16. Partly done: PROCTOR's `crlf-deploy-script` fails on CRLF in deploy scripts and warns when `.gitattributes` does
    not pin them to LF, PROCTOR 0.9.0's `case-clash` fails on file-name case collisions, and PROCTOR main's
    `gitattributes-eol` (in its next release) warns when shell scripts, `.conf` files and Dockerfiles are not pinned to
    LF and on a CRLF Dockerfile or `.conf` under `deploy/` or `.squire/`; a check for the `.ps1` and `.sh` pair is still
    to add (practice 34).
17. Partly done: PROCTOR's `preflight-pinned` warns when a Preflight include is not pinned to a full release tag (such
    as `@0.4.0`) or a 40-hex commit SHA; components from outside Preflight are not checked (practice 23).
18. Partly done: the accessibility chapter is written, with the WCAG 2.2 AA target (practices 30 and 54); the Title
    II dates stay open (practice 52).

Added in the second pass (2026-10-27):

19. Partly done: `policy.json` carries an optional `minimum_patch` per PHP line, read by `Policy::minimumPatch()` and
    checked by `bin/validate-policy.php`; no values are set yet, and the PROCTOR check that compares the value with
    the image or runtime waits (practice 37).
20. Add a response window per severity to `rules` (for example "fix within N days"; the maintainers choose N), and a
    check that applies it to `composer audit` output (practice 38).
21. Done: `config.policy` it is. Practice 21 is `Checked`, PROCTOR's `composer-audit` reads the `config.policy` block
    (with the legacy `audit` keys as a fallback), and the Composer the stack pins, 2.10.3, supports it (practices 21
    and 39).
22. Done: decided 2026-11-03: a 3 day cooldown once Composer 2.11 is stable and in the base images; Sourdough apps set it and PROCTOR warns when it is missing (PROCTOR plan 4.11). Composer 2.11.0 shipped on 2026-10-29 (2.11.1 on 2026-10-30) with `policy.cooldown.period`; it is not set anywhere yet, because the stack's images and CI still pin 2.10.3, and moving them is the next step (practice 40).
23. Add an npm check (`audit-level`, `ignore-scripts`, `min-release-age`) to Preflight, with a severity word map between
    Composer and npm (practice 41).
24. Done: git.wayne.edu and nougat both run GitLab 19.5.1 Community Edition, the Free tier (the rule is written in
    `handbook/code.md`; practice 42).
25. Partly done: PROCTOR main's `app-key` warns on a committed `.env` or `APP_KEY` and the cookie session driver, on
    every OS (practice 43). The secrets chapter names the check; the general secret scan is item 10.
26. Partly done: `images.docker` is filled for PHP 8.3 to 8.5 and the rebuild rule is in `handbook/versions.md`;
    `citadel` waits for Citadel's image names.
27. Ask the owners of git.wayne.edu to publish its GitLab patch level and a patch routine (practice 45).
28. Done: policy.json records each Laravel release's minimum database (practice 46), and PROCTOR's `mysql-minimum`
    warns when an app's declared database image is below it; it shipped in PROCTOR 0.10.0.
29. Done: `handbook/databases.md` writes down the MySQL auth plugin step and the rule "list LTS lines only" (practice 47).
30. State a target for adding a new PHP image, per target (Citadel and elsewhere), and a notice period for retiring
    one (practice 48).
31. Done: decided 2026-11-03: policy lists only even Node majors, which are the LTS lines; odd majors are never a target and are not listed. A new even major is added from Node's release schedule when it is announced (practice 49).
32. Partially: `bin/refresh-policy.php` calls the API with the filter rules in practice 50 and classifies every change,
    and the `policy-bot` job runs it; a human still confirms an aggregator difference against the vendor page (extends item 2).
33. Done: decided 2026-11-03: strict where the file is published, tolerant where it is read. The schema forbids unknown keys, so CREED's own CI fails on a stray key; readers ignore unknown keys, so an older reader keeps working on a newer file (`tests/OlderReaderContractTest.php`; practice 51, extends item 14).
34. Ask WSU's accessibility office and counsel which Title II dates apply, then write the chapter's target level
    (practices 52 and 54).
35. Done: Preflight 0.2.0's `a11y` component runs axe-core in Playwright and fails on a violation (practice 53).
36. Done: the Laravel 13 `active_until` date (2027-09-30) carries its source, endoflife.date, in `policy/sources.json` (Checks against the web).
37. Done: the schema requires `security_until` on every line that is not `"current": true` (`policy/policy.schema.json`),
    and `tests/PolicySchemaTest.php` rejects a line with neither (practice 2).

Added from the WSU developer standards (2026-11-02):

38. Done: PROCTOR main's `served-folder` warns on `phpinfo()`, dumps, backups, logs, env files and `adminer*.php`
    committed under `public/` (practice 59).
39. Partly done: PROCTOR main's `world-writable` warns on `chmod 777` in scripts and PHP; web-writable paths outside an
    allowed list need the running container (practice 59).
40. Done: a committed-secret scan in Preflight, the `secret-detection` component (0.2.0); extends items 10 and 25 and practice 55.
41. Done: `policy.json` records the idle limits (`sessions`), and the handbook's auth chapter states them and points at
    the policy (practice 57).
42. Deferred past 1.0: a schema version 2 with `platform_components` per target. It would be a breaking schema change and nothing needs it yet.

## Sources

All checked on 2026-10-27. Sources first used in the second pass follow the first-pass list.

- PHP, supported versions: https://www.php.net/supported-versions.php
- PHP, unsupported branches: https://www.php.net/eol.php
- Laravel 13 release notes and support policy: https://laravel.com/docs/13.x/releases
- Laravel 13 configuration: https://laravel.com/docs/13.x/configuration
- Node.js release schedule: https://github.com/nodejs/Release
- endoflife.date, MySQL: https://endoflife.date/mysql
- Vue release process: https://vuejs.org/about/releases
- Vue 2 end of life: https://v2.vuejs.org/eol/
- Composer, basic usage: https://getcomposer.org/doc/01-basic-usage.md
- Composer, versions and constraints: https://getcomposer.org/doc/articles/versions.md
- Composer, command-line reference (`audit`, `validate`): https://getcomposer.org/doc/03-cli.md
- Composer 2.8.4 changelog: https://getcomposer.org/changelog/2.8.4
- GOV.UK Publishing manual, keeping software current (page says it may be out of date; last updated 2025-03-19):
  https://docs.publishing.service.gov.uk/manual/keeping-software-current.html
- University of Oregon, systems and applications lifecycle procedure (read from a search summary, not the page):
  https://service.uoregon.edu/TDClient/2030/Portal/KB/PrintArticle?ID=141020
- OWASP Secrets Management Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html
- OWASP Application Security Verification Standard (current version 5.0.0, 2025-05-30): https://asvs.owasp.org/
- Semantic Versioning 2.0.0: https://semver.org/
- JSON Schema, introduction: https://json-schema.org/understanding-json-schema/about
- Architectural Decision Records: https://adr.github.io/
- GitLab Code Quality: https://docs.gitlab.com/ci/testing/code_quality/
- Added in the second pass, all checked 2026-10-27 (primary source unless marked):
  - PHP 2026 news archive (security releases 2026-10-21, notes of 2026-10-23 and 2026-10-26): https://www.php.net/archive/2026.php
  - endoflife.date PHP API (aggregator): https://endoflife.date/api/v1/products/php/
  - endoflife.date MySQL API (aggregator): https://endoflife.date/api/v1/products/mysql/
  - Laravel advisory GHSA-f9x3-7vq9-cph6: https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6
  - Laravel framework advisory list: https://github.com/laravel/framework/security/advisories
  - Laravel 13 database docs: https://laravel.com/docs/13.x/database
  - Composer config reference (`audit`, `policy`): https://getcomposer.org/doc/06-config.md
  - Composer releases: https://github.com/composer/composer/releases
  - Composer 2.9 release post (Packagist): https://blog.packagist.com/composer-2-9/
  - Composer 2.9.6 Perforce fixes (Packagist): https://blog.packagist.com/composer-2-9-6-perforce-driver-command-injection-vulnerabilities/
  - Packagist supply chain security update (2026-05-27): https://blog.packagist.com/an-update-on-composer-packagist-supply-chain-security
  - GitHub, plan for a more secure npm supply chain: https://github.blog/security/supply-chain-security/our-plan-for-a-more-secure-npm-supply-chain/
  - npm config reference: https://docs.npmjs.com/cli/v11/using-npm/config
  - GitGuardian, exploiting public APP_KEY leaks: https://blog.gitguardian.com/exploiting-public-app_key-leaks/
  - Gitleaks: https://github.com/gitleaks/gitleaks
  - Docker build best practices: https://docs.docker.com/build/building/best-practices/
  - GitLab CI/CD components: https://docs.gitlab.com/ci/components/
  - GitLab dependency scanning: https://docs.gitlab.com/user/application_security/dependency_scanning/
  - GitLab secret detection (overview, pipeline, push protection): https://docs.gitlab.com/user/application_security/secret_detection/
  - GitLab accessibility testing: https://docs.gitlab.com/ci/testing/accessibility_testing/
  - GitLab patch releases index: https://docs.gitlab.com/releases/patches/ (listing only; no detail read)
  - BleepingComputer on GitLab CVE-2026-110264 (secondary): https://www.bleepingcomputer.com/news/security/gitlab-warns-of-new-max-severity-code-execution-vulnerability/
  - MySQL 9.7, native pluggable authentication: https://dev.mysql.com/doc/refman/9.7/en/native-pluggable-authentication.html
  - Node.js release schedule, schedule changes from 27: https://github.com/nodejs/Release
  - Vue core releases: https://github.com/vuejs/core/releases
  - Inertia 3 upgrade guide: https://inertiajs.com/docs/v3/getting-started/upgrade-guide
  - OWASP Top 10:2025: https://top10.owasp.org/2025
  - ADA.gov, Title II web rule first steps: https://www.ada.gov/resources/web-rule-first-steps/
  - W3C, WCAG 2.2: https://www.w3.org/TR/WCAG22/
  - University of Wisconsin-Madison web hosting, PHP supported versions: https://kb.wisc.edu/webhosting/page.php?id=135335
  - MediaWiki, support policy for PHP: https://www.mediawiki.org/wiki/Support_policy_for_PHP
- Checked in the second pass but not relied on: NIST SP 800-218 Rev. 1 (SSDF 1.2) initial public draft, 2025-12-17
  (seen in search results only; final status not confirmed), and the PHP-FIG PSR list (no new accepted PSR confirmed).

## Second pass (2026-10-27)

A second pass the same day re-read the repository (`policy.json`, the schema, the reader, tests, handbook, CI file) and
ran fresh searches. Web pages were treated as data. No page contained instructions aimed at the reader.

Added (18 practices, 37 to 54): PHP patch level (37), framework patch window (38), Composer client patching (39),
cooldown for new package versions (40), front-end install options (41), tier-independent gates (42), `APP_KEY` as a
code-execution key (43), base image pinning and rebuilds (44), the CI host as supply chain (45), framework database
minimum (46), MySQL auth and LTS lines (47), platform adoption and notice targets (48), Node 27 schedule (49), freshness
check design (50), strict or tolerant schema (51), the accessibility legal floor (52), a CI accessibility check (53) and
WCAG 2.2 AA (54). Tags: all `Not yet`. Nothing new is `Enforced` or `Checked`.
Also added 19 backlog items (numbers 19 to 37) and 31 sources.

Corrected or changed:

1. Practice 7, 15, 16, 17 and the "Checks against the web" list: Laravel 13, Node 26 and MySQL 9.7 are now in
   `policy.json`. The first pass called them missing.
2. Vue 3.6 status settled from the official releases page: not stable; 3.5.44 is the latest stable release.
3. Composer exit codes: the conflict is explained by Composer version, and the current docs list only 0 and 1.
4. GOV.UK citations: removed from practices 27 and 28 (the page does not say what was claimed) and softened in practice 4.
5. Practice 2: noted that the schema does not require `security_until`; only the reader refuses a missing one.
   Superseded: the schema now requires `security_until` on every line that is not `"current": true`.
6. Practice 10: noted that the strict schema conflicts with "old tools can read new files" (practice 51).
7. Practice 20, 21, 23, 35: added Composer 2.9 blocking, the `audit.abandoned` default, GitLab's SHA-pinning advice and a
   PHP 8.5 PDO difference.
8. Practice 11: the ASVS citation is general, and OWASP Top 10:2025 A03 is added as the closer fit.
9. New open item: the Laravel 13 `active_until` date in `policy.json` is not on the vendor page, which says "Q3 2027".
   Resolved: the date is 2027-09-30 and carries its source, endoflife.date, in `policy/sources.json` (Checks against the
   web).

Not verified, and said so in the text: whether Citadel images were rebuilt after 2026-10-21; the git.wayne.edu GitLab tier
and patch level; how fast the 2026-10-19 Laravel advisory reached the Composer advisory feed; the GitLab CVEs beyond one
secondary report; the 9.7 manual's LTS upgrade-path line; whether the Title II rule applies to a given WSU unit; and the
court challenge to the Title II extension.
