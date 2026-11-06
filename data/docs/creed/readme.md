

# CREED

**The standard everything rests on.** The Wayne State App Stack Code: one policy file, one reader, and an
inspector who stamps what he finds.

**Not to be confused with:** CREED is the rules; PROCTOR checks an app against them.

Checks: `composer ci` (the same command runs on GitLab and GitHub).

Front page: [`site/index.html`](site/index.html), a static page you can open in a browser or serve from `site/`.
On Citadel it is served by the raw-PHP deploy layer in [`deploy/`](deploy/): a Squire kit whose front controller serves `site/` behind HELMET's security headers. For local development, keep serving `site/` directly.

One command, two clouds, zero excuses. Higher.

CREED is the Wayne State app stack's version policy, as data. One file,
`policy/policy.json`, lists the support dates for PHP, Laravel, Node, MySQL,
Vue and Inertia, plus the rules every check runs against. A small
framework-free PHP reader, `Waynestate\Creed\Policy`, answers the one question
every tool needs: what state is this version in, on this date?

## Documentation

The documentation site lives at [`site/docs/index.html`](site/docs/index.html) and deploys to [https://creed.apps.wayne.edu/docs/](https://creed.apps.wayne.edu/docs/). It is built from `docs/` by Squire's shared docs builder, and `composer ci` fails when the committed pages go stale. Creed Bratton keeps his records in a filing cabinet nobody can find; ours are in `docs/`, where anyone can.

The docs pages are bound into the same code book as the front page: the running head and main menu, the cream stock and green cloth spine, Source Serif and IBM Plex Mono, stamped callouts and the green colophon. Dark mode is the same book read after hours (warm graphite, cream type, gold foil), never a green wallpaper. The cover's gold ribbon marks the page you are reading, and each callout's stamp is pressed as it scrolls in, for readers who allow motion. That look lives in two files, `docs/_header.html` and `docs/theme/creed.css`, named by the `theme` key in `docs/docs.json`; edit those, never the kit's `site/assets/docs.css`.

## Where the code lives

The canonical repository is on nougat: [enterprise-apps/creed](https://nougat.snu.gs/enterprise-apps/creed). git.wayne.edu and GitHub ([waynestate/creed](https://github.com/waynestate/creed)) are read-only mirrors of it.
Open issues and merge requests on nougat, not on the mirrors.
This moves to git.wayne.edu once it has CI runners. Creed Bratton would say the code lives wherever he last left it. Ours lives on nougat.

No other code in the stack decides a version's state. The rules live in one
place, and every tool reads them from here.

## Install

```
composer require waynestate/creed
```

### Develop on it

The same commands work in PowerShell and sh:

- Windows (PowerShell): `composer install`, then `composer ci` for everything CI runs, or `composer test` for just the tests.
- Linux and macOS (sh): the very same three commands, `composer install`, `composer ci`, `composer test`.

With only Docker (the same line works in PowerShell and sh):

```
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 sh -c "composer install --no-interaction && composer ci"
docker run --rm -v "${PWD}:/app" -w /app php:8.5-cli vendor/bin/phpunit
```

The whole band fits in three commands, and nobody needs a solo.

## Usage

```php
use Waynestate\Creed\Policy;
use Waynestate\Creed\State;

$policy = Policy::bundled();

// What state is PHP 8.2 in, on this date?
$state = $policy->stateOf('php', '8.2', new DateTimeImmutable('2026-10-27'));
// State::Security

// What is the oldest PHP an app may declare?
$floor = $policy->oldestSupported('php', new DateTimeImmutable('2026-10-27'));
// '8.2'
```

### Reading the policy from PHP

The reader's public members, one line each:

- `Policy::bundled()`, `Policy::fromFile($path)`, `Policy::fromArray($data)`: load a policy; `origin()` says where it came from.
- `stateOf($component, $version, $on)`: the state, or an exception; `tryStateOf()` is the same answer as `null` when it cannot be answered.
- `oldestSupported($component, $on)`: the lowest version still Active or Security; `supported()` lists all of them, oldest first.
- `latestPatch($component, $version)`: the current patch in that line; `latestPatchChecked()` says when it was last checked.
- `line($component, $version)`: the raw line, with `active_until`, `security_until`, `latest_patch`, `latest_patch_checked` (each a string or null) and `current` (true or null).
- `activeUntil()`, `securityUntil()`: those dates as `DateTimeImmutable`, or null.
- `daysToEol($component, $version, $on)`: whole days until `security_until`, signed; `isPastGrace()` says whether the grace period is over.
- `resolveVersion($component, $version)`: the policy line a full version like `8.3.12` belongs to.
- `isAheadOfPolicy($component, $version)`: whether a version sorts above every line the policy lists.
- `belowOldestLineReason($component, $version)`: the reason a version is `Eol` for sorting below every listed line, or null.
- `hasComponent()`, `components()`, `versions($component)`, `tools()`, `toolMinimum($tool)`, `rules()`: the shape of the policy itself.

One last time: the reader answers, it does not guess.

### With Arms Wide Open

The support window stays open until `security_until`. A version is still
`Security` on that day, moves to `Eol` the day after, and stays in `Eol` for
`eol_grace_days` (90) more days before it is `EolGraceExpired`. The boundary
is inclusive, and the reader never guesses.


<div align="center"><sub>Form CR-1, as the front page fills it in: four real rows of <code>policy/policy.json</code>, the same answers <code>Policy::stateOf</code> gives, stamped.</sub></div>

<details>
<summary><b>Read the rest of the book</b>: the contents, Table 2-1 and the errata slip, as printed on a phone</summary>
<br>

</details>

### Versions CREED hasn't heard of

The reader refuses what it does not know, except the newest thing and the
oldest. A version that sorts above every line the policy lists is `Active`,
and `isAheadOfPolicy()` says so. A version that parses and sorts below every
listed line is `Eol`: it is older than the oldest listed line, and that is
end of life by definition. `belowOldestLineReason()` gives the reason,
`older than the oldest listed line (8.1)`. A version that falls between
listed lines, or that does not parse, is still unknown, and still throws.
Current versions are never refused.

A line marked `"current": true` is `Active` on every date and carries no
`security_until`: the policy says the line is current, and the reader does not
guess when it stops being so.

### Higher

CREED itself requires PHP `^8.3`, like every stack package, and CI tests it on
8.3, 8.4 and 8.5. The policy's floor for apps is a different number: `^8.2`
until PHP 8.2 reaches end of life on 2026-12-31, then `^8.3`. Creed has always
been one version ahead of everyone else, at least by his own account.

## Proof

Proof runs against real Wayne State applications are on a private
site, [proof.apps.wayne.edu/creed](https://proof.apps.wayne.edu/creed/), behind a Wayne State employee sign-in.

## The handbook

The rules, chapter by chapter, live in [`handbook/`](handbook/README.md).

## Keeping the patches fresh

`bin/refresh-patches.php` re-reads the upstream feeds, rewrites the
`latest_patch` fields in `policy/policy.json`, and proposes new Vue lines from
endoflife.date. A scheduled CI job runs the refresh and is check-only: when
the policy changed, the tool exits 3 and the job fails on the diff.

The policy robot (`bin/policy-bot.php run`) is the committing path, and it
runs every Monday. It runs the fuller `bin/refresh-policy.php` (below), commits
`policy/policy.json` and `policy/sources.json` on `auto/policy-refresh`, and
force-pushes a merge request titled "policy: refresh from upstream sources",
labeled `auto-data`. After a refresher exit 3 the merge request merges itself
once its pipeline passes; after an exit 4 (something held or refused) it waits
for a person. Each held or refused item, each disagreement between sources the
policy cannot settle, and each listed line within 60 days of its
`security_until` also gets one GitLab issue labeled `auto-alert`, noted on
later runs and closed when it clears. When `refreshed_on` is a week old and
nothing moved, the robot stamps it anyway (the heartbeat). It never tags or
releases, and a missing CI variable stops it before any push. To review a
robot merge request, read its body (one line per change, with its class),
check any date against its source, and never commit to `auto/policy-refresh`:
the next run overwrites it. If its pipeline goes red for an unrelated reason,
fix `main` and rebase the merge request. The [policy robot
guide](https://creed.apps.wayne.edu/docs/guides/policy-bot.html) has the
details.

The `policy-bot` job in `.gitlab-ci.yml` runs the robot on the schedule that
carries `CREED_JOB=policy-bot` and by hand from the web. Without the token the
job prints "CREED_BOT_TOKEN not set; skipping the policy robot" and stays
green. The setup, all on the CREED project on nougat (the canonical
repository):

1. A project access token (Settings > Access tokens): role Maintainer
   (Developer works only if Developers may merge to the default branch),
   scopes `api` and `write_repository`, an expiry of 90 days, then rotate.
2. A CI/CD variable `CREED_BOT_TOKEN` holding the token: masked and hidden,
   protected, not a file. The schedule runs on `main`, which is a protected
   branch, so the protected variable is available there.
3. A pipeline schedule on `main` (Build > Pipeline schedules): weekly, Monday,
   with the variable `CREED_JOB` = `policy-bot`.

The branch `auto/policy-refresh` must not be protected, or the robot cannot
push it. The schedules are GitLab project settings, not files in this repo.
The GitHub mirror runs the same check on a cron and on demand.

## Refreshing the policy

`bin/refresh-policy.php` keeps every support date, patch and new line in
`policy/policy.json` tied to a canonical source, so nobody types a date by
hand. PHP dates come from php.net, Node dates from the Node.js release
schedule, and Laravel, MySQL, Vue and Composer from endoflife.date; patches
come from php.net, nodejs.org, Packagist and npm. Each run sorts every change:

| Class | What it is | What happens |
|---|---|---|
| `tighten` | a date moves earlier, or a `current` line gets its first end date | written |
| `neutral` | a new `latest_patch`, or a new line above every listed line | written |
| `loosen-warning` | `active_until` moves later (a warning tier, never a failure) | written |
| `loosen-fail` | `security_until` moves later, or an EOL line would be supported again | held, unless two independent sources agree (php.net and endoflife.date for PHP, schedule.json and endoflife.date for Node) or `policy/sources.json` shows the same value from the same source for 14 days |
| `refused` | removing a line or a date, or any change to `tools` or `rules` | never automatic; reported (a Composer minimum on an EOL line shows up here) |

A source that is down, slow or returns something unreadable changes nothing:
its fields keep their last good value. A line missing from a feed is never
removed. Where a first-party source and endoflife.date disagree, the first
party wins and the disagreement is reported.

```
php bin/refresh-policy.php [--dry-run] [--offline <dir>] [--policy <path>]
    [--sources <path>] [--report <file>] [--today YYYY-MM-DD] [--touch]
```

`--report` writes JSON with `changes`, `held`, `refused`,
`disagreements` and `source_errors`. `--touch` sets `refreshed_on` to today even
when nothing else changed (the weekly heartbeat). `policy/sources.json` records,
per value, which source said it, and the first and last day it was seen.

Exit codes: `0` nothing worth committing (always `0` with `--dry-run`); `1` hard
failure; `3` something was written to `policy/policy.json` or `policy/sources.json`,
including a released `loosen-fail` (the policy robot raises the alert from the
report); `4` something was held or refused (whatever could be written was
written; the held or refused items stay out). The refresher never commits: the
policy robot does.
`bin/refresh-patches.php` still works as before.

## Carrying CREED in another package

PROCTOR, Booster and Rollcall each carry a generated copy of the reader
under their own namespace, so two tools in one app never fight over a class
name (the generator below keeps the copies in step). ADVISOR carries CREED's
policy and handbook as a data bundle instead. The copies began
because CREED was not on Packagist. It is now (`waynestate/creed`, 0.1.6 at this writing), so
each copy can become a real `require`, one consumer at a time; until a
consumer switches, its copy stays. CREED owns the generator and the check:

```
php bin/export.php --namespace=Waynestate\Proctor\Creed --to=vendor/creed-copy
php bin/export.php --check --namespace=Waynestate\Proctor\Creed --to=vendor/creed-copy
```

The copy is the reader with the namespace rewritten and `bundled()` repointed
at its own `policy.json`; `--check` fails when the copy drifts from the source.
Creed Bratton would have a form for this.

## Tests

Maintainers: how to build, test and release this project, and where its data comes from, is in [MAINTAINING.md](MAINTAINING.md).

No PHP on the machine? Fine. Docker runs the suite:

```
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 composer install --no-interaction
docker run --rm -v "${PWD}:/app" -w /app php:8.4-cli vendor/bin/phpunit
docker run --rm -v "${PWD}:/app" -w /app php:8.3-cli vendor/bin/phpunit
```

The policy validates against its schema:

```
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 composer validate-policy
```

## Strongly recommended: a git.wayne.edu instance runner

git.wayne.edu has no runners today. Every product in the stack strongly
recommends a git.wayne.edu instance runner.

Creed Bratton, whose quality assurance work we all admire. His past is a
mystery. Ours is in `policy/policy.json`, where it belongs.

<details>
<summary><b>The brand board</b>: the keystone arch, the lockups, the cloth, the ink and the type</summary>
<br>

</details>

<div align="center"><b>Authorities having jurisdiction</b><br><sub>WayneUI and the stack. Every one of them reads its dates from here.</sub></div>

<div align="center"><sub>Images are regenerated from the front page and the policy by <a href="scripts/readme-art/README.md"><code>scripts/readme-art</code></a>. Inspected, stamped, and filed one version ahead.</sub></div>
