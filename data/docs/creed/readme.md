<div align="center">
  <a href="site/index.html"><img src="docs/readme/hero.jpg" width="100%" alt="CREED's front page: the title page of the Wayne State App Stack Code, 2026 edition, with clause §0.1 spelling out the name, and a green cloth-bound book stamped with CREED's keystone arch mark."></a>
</div>

# CREED

<div align="center">
  <img src="docs/readme/badges/php.svg" alt="php ^8.3">
  <img src="docs/readme/badges/license.svg" alt="licence MIT">
  <img src="docs/readme/badges/policy.svg" alt="policy schema v1, 6 components">
  <a href="https://github.com/waynestate/creed/actions/workflows/ci.yml"><img src="https://github.com/waynestate/creed/actions/workflows/ci.yml/badge.svg" alt="GitHub CI"></a>
</div>

**The standard everything rests on.** The Wayne State App Stack Code: one policy file, one reader, and an
inspector who stamps what he finds.

Checks: `composer ci` (the same command runs on GitLab and GitHub).

Front page: [`site/index.html`](site/index.html), a static page you can open in a browser or serve from `site/`.
On Citadel it is served by the raw-PHP deploy layer in [`deploy/`](deploy/): a Squire kit whose front controller serves `site/` behind HELMET's security headers. For local development, keep serving `site/` directly.

One command, two clouds, zero excuses. Higher.

![Singer of the band Creed in a white tank top and black leather pants on a stage with a red backdrop, one arm stretched out over a cheering crowd](docs/readme/creed-band.gif)

CREED is the Wayne State app stack's version policy, as data. One file,
`policy/policy.json`, lists the support dates for PHP, Laravel, Node, MySQL,
Vue and Inertia, plus the rules every check runs against. A small
framework-free PHP reader, `Waynestate\Creed\Policy`, answers the one question
every tool needs: what state is this version in, on this date?

This repository is published from Wayne State University's internal GitLab
and mirrored here. Changes land there first; Wayne State developers open
issues and merge requests on the internal project.

No other code in the stack decides a version's state. The rules live in one
place, and every tool reads them from here.

## Install

```
composer require waynestate/creed
```

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

<div align="center"><img src="docs/readme/form-cr1.svg" width="100%" alt="Form CR-1 from the front page, filled in four times from policy.json and stamped: PHP 8.2 on 2026-10-28 is Security only, 64 days left; PHP 8.2 on 2027-01-01 is EOL, in grace until 2027-03-31; PHP 8.1 on 2026-10-28 is past grace; Laravel 13 on 2026-10-28 is Supported."></div>
<div align="center"><sub>Form CR-1, as the front page fills it in: four real rows of <code>policy/policy.json</code>, the same answers <code>Policy::stateOf</code> gives, stamped.</sub></div>

<details>
<summary><b>Read the rest of the book</b>: the contents, Table 2-1 and the errata slip, as printed on a phone</summary>
<br>
<div align="center"><img src="docs/readme/gallery.jpg" width="100%" alt="Three pages of the front page on a phone: the table of contents (ten chapters, 54 practices), Table 2-1 with each PHP edition's dates and state, and the errata slip of open amendments."></div>
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
endoflife.date. A scheduled CI job runs it every
Monday and fails the pipeline when the policy is stale; a person then runs the
tool and commits the result. CI never pushes. It only raises its hand.

The schedule is a GitLab project setting, not a file in this repo: someone
creates it under CI/CD > Schedules (weekly, Monday). The GitHub mirror runs the
same check on a cron and on demand.

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
report); `4` something was held or refused; nothing is committed for that run.
Written changes are always written.
`bin/refresh-patches.php` still works as before.

## Carrying CREED in another package

PROCTOR, Booster, Rollcall and ADVISOR each carry a generated copy of the
reader under their own namespace, so two tools in one app never fight over a
class name (the generator below keeps the copies in step). The copies began
because CREED was not on Packagist. It is now (`waynestate/creed` 0.1.2), so
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
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer install --no-interaction
docker run --rm -v "${PWD}:/app" -w /app php:8.4-cli vendor/bin/phpunit
docker run --rm -v "${PWD}:/app" -w /app php:8.3-cli vendor/bin/phpunit
```

The policy validates against its schema:

```
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer validate-policy
```

## Strongly recommended: a git.wayne.edu instance runner

git.wayne.edu has no runners today. Every product in the stack strongly
recommends a git.wayne.edu instance runner.

![Creed Bratton from The Office in a dark suit and blue shirt with a wide, wild-eyed grin](docs/readme/creed-bratton.gif)

Creed Bratton, whose quality assurance work we all admire. His past is a
mystery. Ours is in `policy/policy.json`, where it belongs.

<details>
<summary><b>The brand board</b>: the keystone arch, the lockups, the cloth, the ink and the type</summary>
<br>
<div align="center"><img src="docs/readme/board.jpg" width="100%" alt="CREED's brand board: the stone arch mark with its gold keystone, lockups, the palette and the type."></div>
</details>

<div align="center"><b>Authorities having jurisdiction</b><br><sub>WayneUI and the stack. Every one of them reads its dates from here.</sub></div>

<div align="center">
  <a href="https://github.com/waynestate/wayne-ui" title="WayneUI"><img src="docs/readme/cast/wayneui.png" width="40" alt="WayneUI"></a>
  <a href="https://github.com/waynestate/creed" title="CREED"><img src="docs/readme/cast/creed.png" width="40" alt="CREED"></a>
  <a href="https://github.com/waynestate/proctor" title="PROCTOR"><img src="docs/readme/cast/proctor.png" width="40" alt="PROCTOR"></a>
  <a href="https://github.com/waynestate/sesame" title="SESAME"><img src="docs/readme/cast/sesame.png" width="40" alt="SESAME"></a>
  <a href="https://github.com/waynestate/helmet" title="HELMET"><img src="docs/readme/cast/helmet.png" width="40" alt="HELMET"></a>
  <a href="https://github.com/waynestate/sourdough" title="Sourdough"><img src="docs/readme/cast/sourdough.png" width="40" alt="Sourdough"></a>
  <a href="https://github.com/waynestate/squire" title="Squire"><img src="docs/readme/cast/squire.png" width="40" alt="Squire"></a>
  <a href="https://github.com/waynestate/preflight" title="Preflight"><img src="docs/readme/cast/preflight.png" width="40" alt="Preflight"></a>
  <a href="https://github.com/waynestate/pinstripe" title="Pinstripe"><img src="docs/readme/cast/pinstripe.png" width="40" alt="Pinstripe"></a>
  <a href="https://github.com/waynestate/booster" title="Booster"><img src="docs/readme/cast/booster.png" width="40" alt="Booster"></a>
  <a href="https://github.com/waynestate/rollcall" title="Rollcall"><img src="docs/readme/cast/rollcall.png" width="40" alt="Rollcall"></a>
  <a href="https://github.com/waynestate/drawbridge" title="Drawbridge"><img src="docs/readme/cast/drawbridge.png" width="40" alt="Drawbridge"></a>
  <a href="https://github.com/waynestate/advisor" title="ADVISOR"><img src="docs/readme/cast/advisor.png" width="40" alt="ADVISOR"></a>
  <a href="https://github.com/waynestate/weld" title="WELD"><img src="docs/readme/cast/weld.png" width="40" alt="WELD"></a>
</div>

<div align="center"><sub>Images are regenerated from the front page and the policy by <a href="scripts/readme-art/README.md"><code>scripts/readme-art</code></a>. Inspected, stamped, and filed one version ahead.</sub></div>
