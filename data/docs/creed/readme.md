# CREED

[![CI](https://github.com/waynestate/creed/actions/workflows/ci.yml/badge.svg)](https://github.com/waynestate/creed/actions/workflows/ci.yml)
[![GitHub Actions](https://github.com/waynestate/creed/actions/workflows/ci.yml/badge.svg)](https://github.com/waynestate/creed/actions/workflows/ci.yml)

Checks: `composer ci` (the same command runs on GitLab and GitHub).

Front page: [`site/index.html`](site/index.html), a static page you can open in a browser or serve from `site/`.

One command, two clouds, zero excuses — higher.

![Singer of the band Creed in a white tank top and black leather pants on a stage with a red backdrop, one arm stretched out over a cheering crowd](https://media.giphy.com/media/BsQ4hGk3leUxGVCS5X/giphy.gif)
<sub>GIF via [Giphy](https://giphy.com/gifs/creed-higher-BsQ4hGk3leUxGVCS5X)</sub>

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

### With Arms Wide Open

The support window stays open until `security_until`. A version is still
`Security` on that day, moves to `Eol` the day after, and stays in `Eol` for
`eol_grace_days` (90) more days before it is `EolGraceExpired`. The boundary
is inclusive, and the reader never guesses.

### Higher

CREED itself requires PHP `^8.3`, like every stack package, and CI tests it on
8.3, 8.4 and 8.5. The policy's floor for apps is a different number: `^8.2`
until PHP 8.2 reaches end of life on 2026-12-31, then `^8.3`. Creed has always
been one version ahead of everyone else, at least by his own account.

## The handbook

The rules, chapter by chapter, live in [`handbook/`](handbook/README.md).

## Keeping the patches fresh

`bin/refresh-patches.php` re-reads the upstream feeds and rewrites the
`latest_patch` fields in `policy/policy.json`. A scheduled CI job runs it every
Monday and fails the pipeline when the policy is stale; a person then runs the
tool and commits the result. CI never pushes — it only raises its hand.

The schedule is a GitLab project setting, not a file in this repo: someone
creates it under CI/CD > Schedules (weekly, Monday). The GitHub mirror runs the
same check on a cron and on demand.

## Tests

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

![Creed Bratton from The Office in a dark suit and blue shirt with a wide, wild-eyed grin](https://media.giphy.com/media/sg6OczNgfp7Jm/giphy.gif)
<sub>GIF via [Giphy](https://giphy.com/gifs/creed-sg6OczNgfp7Jm)</sub>

Creed Bratton, whose quality assurance work we all admire. His past is a
mystery. Ours is in `policy/policy.json`, where it belongs.
