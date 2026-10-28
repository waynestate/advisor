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
| `Documented` | Written down only. |
| `Not yet` | A gap. |

Tags describe the repository and design documents as they stand on 2026-10-27. PROCTOR v0.1 is specified in
`first-repos.md` and its checks are named below as specified. CREED's own pipeline currently includes the
`php-test` and `composer-audit` components only, because the `proctor` component waits for a tagged CREED release.

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
check. Both run in the `php-test` component.

Second pass: the schema does not list `security_until` as required, so a version without it passes the schema. Only the
reader refuses it (practice 4, fixture `no-security-until.json`). The two checks together cover the case, but the schema
alone does not.

### 3. Write dates as ISO 8601 days, never prose

Dates are `YYYY-MM-DD`. Quarters and phrases such as "Q3 2027" are not stored. When a project publishes only a quarter,
leave that date out until the project gives an exact day.

Why: tools compare dates as strings, and a vague date cannot be compared.

Sources: [JSON Schema introduction](https://json-schema.org/understanding-json-schema/about) (its date example),
[Laravel release notes](https://laravel.com/docs/13.x/releases) (shows a quarter for one date and exact days for others).

How the stack holds it: `Enforced`. The schema declares `format: date` and a fixture with a bad date is rejected.

### 4. Make the reader refuse to guess

A version with no `security_until` is an error, not a silent pass. An unknown component or version is an error.

Why: a tool that guesses turns missing data into a green check.

Sources: [GOV.UK Publishing manual, keeping software current](https://docs.publishing.service.gov.uk/manual/keeping-software-current.html)
(says teams should upgrade "in plenty of time before" EOL and hopes to track this in a regularly reviewed spreadsheet;
it does not describe a tool that refuses unknown data).

Second pass: the first pass said the GOV.UK policy "is to track EOL dates explicitly". The page is softer than that. It
also carries a banner saying it may be out of date (last updated 2025-03-19). Treat it as prior art for the intent only.

How the stack holds it: `Enforced`. `stateOf` throws for unknown components, unknown versions and a missing
`security_until`; tests cover each case.

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
process is still manual and nothing alerts when the next release ships. The gap was real: Laravel 13 (released
2026-03-17) and MySQL 9.7 (released 2026-04-21) took six to seven months to land. See practices 49 and 50 for what comes
next.

### 8. Check the policy against upstream automatically

A scheduled job compares `policy.json` with a public EOL feed and opens a report when a date or a release differs.
The feed only checks; the dates in the file stay the source of truth.

Why: people forget; a job does not.

Sources: [endoflife.date MySQL](https://endoflife.date/mysql), [PHP supported versions](https://www.php.net/supported-versions.php).

How the stack holds it: `Not yet`. The prior-art note proposes using the endoflife.date API as a staleness check, but
nothing runs it.

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

How the stack holds it: `Not yet`. `policy.json` lists only Vue 2 (EOL 2023-12-31) and an empty `inertia` list. The
reader requires `security_until`, so Vue 3 cannot be added as it stands.

### 10. Keep the policy a versioned contract that old tools can still read

Add fields; do not rename or remove them. Bump `schema_version` for breaking changes. An older PROCTOR must still read a
newer policy.

Why: tools ship on different days, and apps run old tool versions.

Sources: [Semantic Versioning 2.0.0](https://semver.org/).

How the stack holds it: `Documented`. The stack's version policy and `versions.md` state it. The schema pins
`schema_version` to `1` and
the reader ignores unknown keys, but no test runs an older reader against a newer file.

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

How the stack holds it: `Checked`. `php-floor` reports a WARN for a security-only floor. The runtime ribbon and log
line in the stack's version policy wait for HELMET.

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

How the stack holds it: `Not yet`. The policy does not record which PHP versions each Laravel release supports, and no
check compares them.

### 15. Run a Laravel release that still gets security fixes

Laravel gives bug fixes for 18 months and security fixes for two years after release. Laravel 11 and older are EOL.
Laravel 12 gets security fixes until 2027-02-24. Laravel 13, released 2026-03-17, gets them until 2028-03-17.

Why: an EOL framework is the largest unpatched surface in a typical app.

Sources: [Laravel release notes](https://laravel.com/docs/13.x/releases).

How the stack holds it: `Not yet`. The reader can classify Laravel 10, 11, 12 and 13 (second pass: 13 was added after
the first pass), but no PROCTOR v0.1 check reads `composer.lock` for the framework. The planned deploy gate will
check it. A supported branch is not enough on its own: see practice 38 for the October 2026 Laravel advisory.

### 16. Use a Node release that is in LTS or maintenance

Build front ends on a Node line that has not reached end of life. On 2026-10-27: 22 (to 2027-04-30), 24 (in
maintenance since 2026-10-20, to 2028-04-30) and 26 (LTS from 2026-10-28, to 2029-04-30). Node 20 reached end of
life on 2026-04-30.

Why: build tools run with your credentials and your source.

Sources: [Node.js release schedule](https://github.com/nodejs/Release),
[Node release schedule data](https://raw.githubusercontent.com/nodejs/Release/main/schedule.json).

How the stack holds it: `Not yet`. The dates for 20, 22, 24 and 26 are in `policy.json` (second pass: 26 was added after
the first pass, and its dates match the Node schedule). No check reads an app's Node version. Node 27 changes the
schedule shape: see practice 49.

### 17. Choose a supported MySQL, and say when the platform lags

MySQL 8.4 LTS has Premier support to 2029-04-30 and Extended to 2032-04-30. MySQL 9.7 LTS is newer. MySQL 8.0 ended on
2026-04-30 and is past CREED's 90-day grace since 2026-07-29. Versions 5.7 and 5.6 ended in 2023 and 2021.

Why: a database that no longer gets fixes holds everything above it back.

Sources: [endoflife.date MySQL](https://endoflife.date/mysql).

How the stack holds it: `Not yet`. `policy.json` lists MySQL 5.6, 5.7, 8.0, 8.4 and 9.7 (second pass: 9.7 was added after
the first pass), and the platform's own MySQL timeline is still an open question. Nothing checks it. The shared MySQL
5.6 is below the minimum that Laravel itself documents: see practice 46.

## 3. Dependencies

### 18. Commit the lock file

`composer.lock` is in the repository for apps, so everyone installs the same versions. Use `composer install` in CI and
on deploy, not `composer update`.

Why: without a lock, a build next week is a different build.

Sources: [Composer basic usage](https://getcomposer.org/doc/01-basic-usage.md).

How the stack holds it: `Checked`. PROCTOR `lock-committed` fails when `composer.lock` is missing. The deploy gate
adds a check that the lock matches `composer.json` at deploy time, which is not built.

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

How the stack holds it: `Checked`. The PREFLIGHT `composer-audit` component runs in CI and PROCTOR `composer-audit`
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
of `fail` (since Composer 2.7; it was `report` in 2.6), and says the whole `audit` section is deprecated in favour of
`config.policy`, where blocking abandoned packages (`block`) is off by default. Composer 2.10 added `config.policy`
(2.10.0 on 2026-05-28), with advisory, abandoned and malware blocks; the latest stable is 2.10.3. The same page says the
legacy keys keep
working only while the matching `policy` section is absent. So the stack should write down which Composer version its CI
uses, and which of the two config styles it follows.

How the stack holds it: `Not yet`. `policy.json` rules have no abandoned-package setting.

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

How the stack holds it: `Documented`. `ci.md` states it, and CREED's `.gitlab-ci.yml` pins both components to a SHA.
No check scans other repos.

## 4. Secrets and configuration

### 24. Keep secrets out of source control

Do not commit `.env`, keys, tokens or passwords. Commit `.env.example` with placeholders. Load real values from the
environment at deploy time. Rotate a secret that was ever exposed.

Why: a repository is copied to many machines and outlives any one password.

Sources: [OWASP Secrets Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html),
[Laravel configuration docs](https://laravel.com/docs/13.x/configuration).

How the stack holds it: `Not yet`. No handbook chapter covers secrets, and no check scans for committed secrets.

### 25. Read environment variables only in config files

In Laravel apps, call `env()` only from `config/*.php`, and read values elsewhere with `config()`. After
`config:cache`, `env()` outside config returns nothing.

Why: it works on a laptop and breaks in production.

Sources: [Laravel configuration docs](https://laravel.com/docs/13.x/configuration).

How the stack holds it: `Not yet`. The rule is named in design notes as a PHPStan rule planned for PROCTOR v0.2. It
belongs in Pinstripe and the handbook, and neither has it yet.

### 26. Never run production with debug on

`APP_DEBUG` is `false` in production.

Why: debug pages expose configuration values to users.

Sources: [Laravel configuration docs](https://laravel.com/docs/13.x/configuration).

How the stack holds it: `Not yet`. The runtime and headers chapter says "To be decided". HELMET's ribbon and refusal
rules are not built.

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

How the stack holds it: `Checked`. PROCTOR `overrides` prints every override and fails an expired one. ROLLCALL is
meant to list them across apps and is not built.

### 28. Roll a new rule out in stages, with dates

Each rule moves `report`, then `warn`, then `block`, with the dates written in `policy.json`. A deploy that does not make
things worse always passes.

Why: blocking every non-compliant app on day one blocks the fixes too.

Sources: none outside the stack. Second pass: the first pass also cited the GOV.UK manual here. That page says
security patches take priority and that teams should upgrade before EOL, but as read it does not describe `report`,
`warn` and `block` stages, so the citation was removed and the staging is the stack's own design.

How the stack holds it: `Documented`. The deploy gate is proposed and needs the Citadel team. The schema has no field
for stage dates.

### 29. Name where each rule is enforced

Every rule says which point holds it: runtime, dependency floor, CI, Citadel deploy, or fleet view. Prefer the earliest
point that cannot be skipped.

Why: a rule that nothing runs is only a wish.

Sources: [OWASP ASVS](https://asvs.owasp.org/).

How the stack holds it: `Documented`. The stack's enforcement design defines the five points. The handbook chapters do
not tag each rule
with its point or its PROCTOR check name.

## 6. Writing the handbook

### 30. Cite the decision behind every rule

Each handbook rule links to the ADR that decided it. Where nothing is decided, the chapter says "To be decided" and
states no rule.

Why: readers can trust a rule, and can find who to ask about the rest.

Sources: [Architectural Decision Records](https://adr.github.io/).

How the stack holds it: `Documented`. Every chapter in `handbook/` follows it. `accessibility.md` and
`runtime-and-headers.md` are still placeholders.

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

How the stack holds it: `Not yet`. The handbook does not name check IDs.

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

How the stack holds it: `Documented`. The stack's every-OS design and `every-os.md` state it, and CREED has the
`.gitattributes`.
PROCTOR checks for CRLF in server files and case collisions are planned, not in v0.1.

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

How the stack holds it: `Documented`. The README and `ci.md` state it; the runner is not in place.

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
each PHP version (and for Laravel and Node), and `bin/refresh-patches.php` keeps them current from php.net, Packagist,
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

How the stack holds it: `Not yet`. CREED's README tells developers to run the floating `composer:2` image, and no repo
records or pins a Composer version. This pass did not read the PREFLIGHT components, so what they install is unknown.

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

How the stack holds it: `Not yet`. No setting in `policy.json` or in CI delays new versions. The lock file (practice 18)
is necessary but, per Packagist, not enough.

### 41. Set the front-end install options, and map severity words

For Node builds, npm's v11 config page documents `min-release-age` (a number of days), `ignore-scripts` (default `false`)
and `audit-level` (`info`, `low`, `moderate`, `high`, `critical` or `none`). The page does not say which npm version added
`min-release-age`. Test the build before turning on `ignore-scripts`, because some packages run build steps on install.
Note the vocabulary gap: npm says `moderate` where Composer's policy page says `medium`. A single
`audit_severity_threshold` string in `policy.json` needs a mapping before more than one tool reads it.

Why: build tools run with your credentials and your source (practice 16), and the worm above relied on install scripts.

Sources: [npm config](https://docs.npmjs.com/cli/v11/using-npm/config),
[Composer config, `policy`](https://getcomposer.org/doc/06-config.md#policy).

How the stack holds it: `Not yet`. PREFLIGHT has `proctor`, `php-test` and `composer-audit` only, so nothing audits
`package-lock.json`. The `audit_severity_threshold` rule is read by the Composer audit alone.

### 42. Gate on tools that work on every GitLab tier

GitLab's docs list dependency scanning and secret push protection as Ultimate. GitLab's security policies (scan execution,
merge request approval, pipeline execution, vulnerability management, dependency firewall) are Ultimate-only, so CREED does
not depend on them. Pipeline secret detection is listed for
Free, Premium and Ultimate, but its findings in merge request reports and the vulnerability report are Ultimate only. GitLab
accessibility testing (Pa11y) is listed for Free, Premium and Ultimate. The tier of git.wayne.edu was not checked.

Why: a gate that needs a licence the instance may not have is a gate that never runs.

Sources: [GitLab dependency scanning](https://docs.gitlab.com/user/application_security/dependency_scanning/),
[GitLab security policies](https://docs.gitlab.com/user/application_security/policies/),
[GitLab secret detection](https://docs.gitlab.com/user/application_security/secret_detection/),
[pipeline secret detection](https://docs.gitlab.com/user/application_security/secret_detection/pipeline/),
[secret push protection](https://docs.gitlab.com/user/application_security/secret_detection/secret_push_protection/).

How the stack holds it: `Not yet`. The rule is not written. The two components CREED uses run Composer and PHPUnit, not a
GitLab scanner (as the handbook describes them; their internals were not read), so they appear to fit it already.

### 43. Treat `APP_KEY` as a code-execution key

GitGuardian's research with Synacktiv found that Laravel's `decrypt()` deserializes its output, so a leaked `APP_KEY`
plus known PHP gadget chains can give remote code execution. GitGuardian reports that about 260,000 `APP_KEY` values were
extracted from GitHub and that this led to roughly 600 trivially compromisable apps; 63 percent of the exposures came from
`.env` files. It also found that 50 keys deleted from repositories were still valid in production. Press coverage quotes
larger numbers than GitGuardian's own page does; use the page. It lists `SESSION_DRIVER=cookie` as a risk factor (it cites
CVE-2024-55556).

Why: deleting a commit does not rotate a key.

Sources: [GitGuardian, exploiting public APP_KEY leaks](https://blog.gitguardian.com/exploiting-public-app_key-leaks/),
[Gitleaks](https://github.com/gitleaks/gitleaks) (MIT licence; its page says it is feature complete and will get security
patches only. It is one option, not an endorsement. Windows support is not stated on the page, so check it before
adopting).

How the stack holds it: `Not yet`. This extends practice 24: no handbook chapter, no secret scan, and no rule about
rotation or the session driver.

### 44. Pin base images and rebuild them on a schedule

Docker's best-practices page says tags are mutable, that pinning a digest guarantees the same image, and that pinning also
means you opt out of automatic fixes. It tells teams to rebuild regularly with `--pull` (and `--no-cache` for a full
refresh). Practice 37 shows why: a PHP image built last month still contains last month's PHP.

Why: pinning without rebuilding freezes the bugs; rebuilding without pinning changes the app without a commit.

Sources: [Docker build best practices](https://docs.docker.com/build/building/best-practices/).

How the stack holds it: `Not yet`. The schema already allows an optional `images` object on each version, keyed by
target (`citadel`, `docker`, `openshift`, `coolify`), but no entry in `policy.json` uses it, and no rebuild schedule is
written.

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

How the stack holds it: `Not yet`. The patch level of git.wayne.edu and of its runners is not known to this guide, and
CREED cannot enforce it. It is listed so that the owners of the instance see it.

## 8. Platform and database (second pass)

### 46. Check the framework's database minimum against the platform database

Laravel 13's database page lists first-party support for MySQL 5.7+, MariaDB 10.3+, PostgreSQL 10.0+, SQLite 3.26.0+ and
SQL Server 2017+. The stack's shared MySQL is 5.6, which `policy.json` marks as past its security window since 2021-02-28
and which is below that line. This pass read the Laravel 13 page only, not the 12 page.

Why: the version table in practice 14 pairs Laravel with PHP but never with the database, and the database is the part
the platform owns.

Sources: [Laravel 13 database docs](https://laravel.com/docs/13.x/database), [endoflife.date MySQL](https://endoflife.date/mysql).

How the stack holds it: `Not yet`. The policy records no database minimum per Laravel release, and the platform's
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

How the stack holds it: `Not yet`. `policy.json` lists the LTS lines 8.4 and 9.7 and not the short-lived ones, which is
the sensible choice, but the rule "list LTS lines only" is not written down.

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

How the stack holds it: `Not yet`. Node 26 is in `policy.json` correctly; the mapping rule for 27 is undecided.

### 50. Design the freshness check around a real feed

endoflife.date serves JSON at `/api/v1/products/<product>/`. Each release has `releaseDate`, `isLts`, `eoasFrom`, `eolFrom`,
`isMaintained` and a `latest` patch with its date. For PHP, `eoasFrom` and `eolFrom` equal php.net's two columns for 8.2 to
8.5. For MySQL the labels object holds `eoas`, `discontinued`, `eol` and `eoes`; the exact strings were not re-read this
pass. For MySQL, the job should write `eoasFrom` (end of Premier support) to `active_until` and `eolFrom` (end of
Extended support) to `security_until`; `policy.json` has no Premier or Extended labels of its own. The job should filter out lines the policy
deliberately omits, and can also read `latest` to feed the patch floor in practice 37.

Why: practice 8 says "compare with a feed" but not what to compare or what to ignore.

Sources: [endoflife.date PHP API](https://endoflife.date/api/v1/products/php/), [endoflife.date MySQL API](https://endoflife.date/api/v1/products/mysql/).

How the stack holds it: `Not yet`. Nothing calls the API. endoflife.date is a community aggregator, so the job reports
differences and a human confirms them against the vendor page (practice 6).

### 51. Decide whether the schema is strict or tolerant, and test both readers

See practice 10. Strict validation catches typos. Tolerant validation lets an old tool read a new file. The schema is
strict today and `schema_version` is pinned to `1`.

Why: "add fields, never rename" fails in practice if the strict schema is the thing older tools run.

Sources: [Semantic Versioning 2.0.0](https://semver.org/),
[JSON Schema introduction](https://json-schema.org/understanding-json-schema/about).

How the stack holds it: `Not yet`. No test pairs an old schema or reader with a new policy file.

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

How the stack holds it: `Not yet`. `handbook/accessibility.md` states no rules.

### 53. Run an automated accessibility check in CI

GitLab's CI accessibility testing uses Pa11y, an open source tool, and is listed for Free, Premium and Ultimate on
self-managed GitLab. An automated tool catches regressions; it is not proof of conformance.

Why: accessibility is a runnable gate for WayneUI (the stack's interface layer), and app pages built on it need the same.
WayneUI's own gate is outside CREED and was not reviewed in this pass.

Sources: [GitLab accessibility testing](https://docs.gitlab.com/ci/testing/accessibility_testing/).

How the stack holds it: `Not yet`. PREFLIGHT has no accessibility component.

### 54. Aim at WCAG 2.2 AA

W3C's WCAG 2.2 Recommendation (the edition read is dated 2024-12-12) states that content that conforms to 2.2 also
conforms to 2.1 and 2.0. Aiming at 2.2 AA therefore meets the 2.1 AA floor in practice 52.

Why: it avoids a second migration.

Sources: [WCAG 2.2](https://www.w3.org/TR/WCAG22/).

How the stack holds it: `Not yet`. The target level is not written anywhere in the handbook.

## Checks against the web

These are places where a source disagrees with the repository or with an earlier note.

- **Laravel 13, Node 26 and MySQL 9.7 were missing from `policy.json` when the first pass was written; they are there
  now.** Second pass: all three entries match the vendor pages. Laravel 13: released 2026-03-17, security fixes to
  2028-03-17 (the page still shows bug fixes as "Q3 2027", and the policy holds 2027-09-17, which the page does not
  state; see the open item below). Node 26: LTS from 2026-10-28, maintenance from 2027-10-20, end of life 2029-04-30.
  endoflife.date shows 2027-10-27 for the same field ([endoflife.date Node API](https://endoflife.date/api/v1/products/nodejs/)).
  MySQL 9.7: Premier to 2031-04-30 and Extended to 2034-04-30 on endoflife.date. The stack's version policy says
"Q1 2028" for Laravel 13;
  the page gives the day.
- **Open item: Laravel 13 `active_until` is 2027-09-17 in `policy.json`.** The Laravel release page gives "Q3 2027" for
  bug fixes and no day. The first pass said not to store a date a project has not published (practice 3). Either the date
  came from another source, which should be recorded, or it is a guess made from the 18-month rule. This pass could not
  tell which. Check it and write the source down (practice 6).
- **PHP dates match.** 8.1 to 8.5 agree with php.net and the EOL table. Second pass: 8.2 to 8.5 were re-read on
  php.net's supported-versions page and match `policy.json`; the endoflife.date PHP API agrees.
- **Dates re-checked this pass:** Laravel 10 to 12 (release, bug-fix and security dates) match the Laravel table. Node 22,
  24 and 26 match the Node repo. MySQL 5.6, 5.7, 8.0, 8.4 and 9.7 match endoflife.date. Node 20 and the Vue 2 date were
  not re-checked.
- **Vue 3 cannot be listed.** Its release page gives no fixed end dates, and the reader needs `security_until`. Vue 2 is
  correct (EOL 2023-12-31). Second pass: settled. The vuejs/core releases page shows 3.5.44 as the latest stable release
  and 3.6.0 only as release candidates (rc.6 to rc.13). A secondary article that said 3.6 shipped on 2026-07-18 does not
  match the official page; ignore it.
- **Composer audit exit codes: the sources differ because they come from different eras.** The 2.8.4 changelog says
  "1 for vulnerabilities and 2 for abandoned, 3 for both". The current CLI reference lists only 0 (no issues) and 1
  (found packages matching dependency policies, or missing required packages). The config page now describes a `policy`
  system that replaced the old `audit` keys. Which behaviour a pipeline gets depends on the Composer version. Pin the
  Composer version in CI and test the behaviour (practice 39).
- **Policy "active" for Node and MySQL is a mapping.** `active_until` holds Node's maintenance start and MySQL's Premier
  end. This is a reasonable mapping and is not the vendors' own wording.

## Gaps for the backlog

1. Done in the repository after the first pass: Laravel 13, Node 26 and MySQL 9.7 are in `policy.json` (practice 7). The
   process gap remains (items 19 and 22).
2. Add a scheduled freshness check against a public EOL feed (practice 8).
3. Extend the schema for lines with no end date, so Vue 3 and Inertia can be listed (practice 9).
4. Add a `source` and `checked_on` field to each version entry (practice 6).
5. Record the supported PHP range per Laravel release, and add a PROCTOR check for the pair (practice 14).
6. Add a PROCTOR check that reads the Laravel version from `composer.lock` against the policy (practice 15).
7. Add a PROCTOR check for the Node version in `.nvmrc` or `package.json` engines (practice 16).
8. Add a MySQL check or report once the platform timeline is known (practice 17).
9. Add an abandoned-package setting to `rules` and apply it in the audit check (practice 21).
10. Add a secrets chapter, and a secret-scanning job in PREFLIGHT (practice 24).
11. Add a PHPStan rule for `env()` outside `config/` and put the rule in Pinstripe (practice 25).
12. Add a `config` check that `APP_DEBUG` is false in production, and write the runtime and headers chapter (practice 26).
13. Put the PROCTOR check ID next to every handbook rule (practices 29 and 32).
14. Add a test that an older reader accepts a newer policy file (practice 10).
15. Add stage dates (`report`, `warn`, `block`) to the schema (practice 28).
16. Add PROCTOR checks for `.gitattributes`, CRLF in server files and file-name case collisions (practice 34).
17. Add a PROCTOR check that CI includes are pinned to a tag or SHA (practice 23).
18. Write the accessibility chapter once the rules are decided (practice 30).

Added in the second pass (2026-10-27):

19. Add a minimum PHP patch version per branch to `policy.json`, fed by the freshness check, and a PROCTOR check that
    compares it with the image or runtime (practice 37).
20. Add a response window per severity to `rules` (for example "fix within N days"; the maintainers choose N), and a
    check that applies it to `composer audit` output (practice 38).
21. Record a Composer version in CI and in the README commands, and decide between the legacy `audit` keys and
    `config.policy` (practices 21 and 39).
22. Evaluate Composer's cooldown policy once it is in a stable release, and decide the period (practice 40).
23. Add an npm check (`audit-level`, `ignore-scripts`, `min-release-age`) to PREFLIGHT, with a severity word map between
    Composer and npm (practice 41).
24. Write down "gates must run on any GitLab tier" and confirm the tier of git.wayne.edu (practice 42).
25. Add a secrets chapter section on `APP_KEY`: rotation, the session driver, and a scan that works on Windows, macOS and
    Linux (practice 43; extends item 10).
26. Fill `images` for supported versions, per target (Citadel and elsewhere), and write a rebuild schedule for base
    images (practice 44).
27. Ask the owners of git.wayne.edu to publish its GitLab patch level and a patch routine (practice 45).
28. Record each Laravel release's minimum database in the policy, and add a check against the platform database
    (practice 46).
29. Write down the MySQL auth plugin step and the rule "list LTS lines only" (practice 47).
30. State a target for adding a new PHP image, per target (Citadel and elsewhere), and a notice period for retiring
    one (practice 48).
31. Decide how `active_until` is derived for Node 27 and later, which have no Maintenance phase (practice 49).
32. Build the freshness job against the endoflife.date API with the filter rules in practice 50 (extends item 2).
33. Decide strict or tolerant schema validation, and add a test with an old schema and reader against a new file
    (practice 51; extends item 14).
34. Ask WSU's accessibility office and counsel which Title II dates apply, then write the chapter's target level
    (practices 52 and 54).
35. Add an accessibility job to PREFLIGHT, using Pa11y or another deterministic tool (practice 53).
36. Resolve the Laravel 13 `active_until` date (2027-09-17): find its source or remove it (Checks against the web).
37. Make `security_until` required in the schema, so the schema and the reader agree (practice 2). Do this together with
    item 3, because lines with no published end date (Vue 3) need another way to be modelled.

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
6. Practice 10: noted that the strict schema conflicts with "old tools can read new files" (practice 51).
7. Practice 20, 21, 23, 35: added Composer 2.9 blocking, the `audit.abandoned` default, GitLab's SHA-pinning advice and a
   PHP 8.5 PDO difference.
8. Practice 11: the ASVS citation is general, and OWASP Top 10:2025 A03 is added as the closer fit.
9. New open item: the Laravel 13 `active_until` date in `policy.json` (2027-09-17) is not on the vendor page, which says
   "Q3 2027".

Not verified, and said so in the text: whether Citadel images were rebuilt after 2026-10-21; the git.wayne.edu GitLab tier
and patch level; how fast the 2026-10-19 Laravel advisory reached the Composer advisory feed; the GitLab CVEs beyond one
secondary report; the 9.7 manual's LTS upgrade-path line; whether the Title II rule applies to a given WSU unit; and the
court challenge to the Title II extension.
