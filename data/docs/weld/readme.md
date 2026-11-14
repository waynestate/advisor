

# WELD

<p>

</p>

**W**ired, **E**verything, **L**inked, **D**eployed. Many parts, one piece.

WELD is the whole Wayne State app stack joined into one application. Install it, deploy it, and every part is already
hooked up: sign-in, security headers, checks, coding standards, CI and the WayneUI interface. Think of it as the
Megazord, except it comes with a deploy script and nobody has to shout to make it combine.

**Stack members:** every part of the stack publishes agent docs at `llms.txt` on its own site, so an agent can read the member before it reads WELD's pages:

- WayneUI: https://wayneui.apps.wayne.edu/llms.txt
- CREED: https://creed.apps.wayne.edu/llms.txt
- Pinstripe: https://pinstripe.apps.wayne.edu/llms.txt
- Preflight: https://preflight.apps.wayne.edu/llms.txt
- PROCTOR: https://proctor.apps.wayne.edu/llms.txt
- SESAME: https://sesame.apps.wayne.edu/llms.txt
- HELMET: https://helmet.apps.wayne.edu/llms.txt
- ADVISOR: https://advisor.apps.wayne.edu/llms.txt
- Squire: https://squire.apps.wayne.edu/llms.txt
- Parity: https://parity.apps.wayne.edu/llms.txt
- Sourdough: https://sourdough.apps.wayne.edu/llms.txt
- Booster: https://booster.apps.wayne.edu/llms.txt
- Rollcall: https://rollcall.apps.wayne.edu/llms.txt
- Drawbridge: https://drawbridge.apps.wayne.edu/llms.txt

**Current versions** (checked 2026-11-14): CREED 0.1.11, PROCTOR 0.16.0, Preflight 0.9.0, SESAME 0.7.5, HELMET 1.4.1, Squire 0.11.1, Pinstripe 0.4.0, ADVISOR 0.3.1, WayneUI 2.0.8. These are the latest releases, not what this repo pins: CI and `deploy/.squire.json` hold those.

ADVISOR's `policy_lookup` answers what is current at any time; the list above is a snapshot for agents that cannot reach it.

## Documentation

The docs live at [site/docs/index.html](site/docs/index.html) and ship to https://weld.apps.wayne.edu/docs/. They are
built from `docs/` by the shared docs builder (`composer docs`) and checked in `composer ci`, so the page you read is
the page that ships: same source, same bead. The docs wear the front page's look: the header is
`docs/_header.html` and the theme is `docs/theme/weld.css` (named in `docs/docs.json`). Change the look there, never
in the builder's kit files (`site/assets/docs.css`, `site/assets/docs.js`). In dark mode the docs are the shop after
hours: steel, cream type and gold, with green kept for the helmet and the footer, not a green wallpaper.

On Citadel the front page and docs are served by the raw-PHP deploy layer in [`deploy/`](deploy/): a Squire kit whose
front controller serves `site/` behind HELMET's security headers. For local development, keep serving `site/` directly.

## Where the code lives

The canonical repository is [enterprise-apps/weld](https://git.wayne.edu/enterprise-apps/weld) on git.wayne.edu. Open issues and merge requests there; tags are made there, by the maintainer, with an approver.
GitHub ([waynestate/weld](https://github.com/waynestate/weld)) is a read-only push mirror of it: nobody pushes to it, and Packagist reads from it.
The weld is made on git.wayne.edu; the mirrors only get the polished bead.

**Not to be confused with:** `weld:doctor` checks local setup only; standards checks are PROCTOR's. [Which command when](#which-command-when) puts every look-alike side by side.

## Get it

WELD is an app you start from, not a library you require. The Megazord ships in a repo, not a box.
Composer makes you a fresh copy, writes `.env`, generates the app key and runs the first migration:

```
composer create-project waynestate/weld my-app
cd my-app
npm ci
```

`create-project` installs the newest tagged release, and `main` can be ahead of it (the [CHANGELOG](CHANGELOG.md)
lists what is unreleased). The newest tag is 0.2.1; 0.1.3 (2026-11-06) was the first where WayneUI draws the app.
Clone when you want `main`.

Or clone it, if you want the history too, and it is yours to rename:

```
git clone https://github.com/waynestate/weld.git my-app
cd my-app
composer install
php vendor/bin/advisor install --client=claude --write
npm ci
# Windows (PowerShell):
Copy-Item .env.example .env
# Linux/macOS:
cp .env.example .env
php artisan key:generate
```

The `advisor install` line writes `.mcp.json`, the MCP config that points Claude Code at ADVISOR's server; the file is git-ignored, because a committed `.mcp.json` would run a program on whoever opens the project. Set `ADVISOR_ENABLED=1` in `.env` to switch the advisor on. No PHP on your host PATH? Run the same line in the app container, `docker compose run --rm app php vendor/bin/advisor install --client=claude --write`, as [Run it locally](#run-it-locally) does with the other installs; see ADVISOR's README for the details.

Laravel Boost is in `require-dev` too: run `php artisan boost:install` once per checkout to wire it into your agent. It writes `CLAUDE.md`, `AGENTS.md`, `boost.json` and the agent's skills, all git-ignored, and folds in the committed team guidance from `.ai/guidelines/` and `.ai/rules/`. `composer update` refreshes it through `composer boost-update`. Boost knows Laravel, ADVISOR knows the stack, and neither reaches production: the `no-dev-install` CI job fails if a `--no-dev` install brings Boost along. Its MCP server runs queries, so keep the development `.env` on a local database.

## Clone it and rename

Want an app that is yours instead of a copy of WELD? Clone it, rename the four things that still say WELD, give it its own key, and re-run the checks.

Clone into a folder named for the new app:

```
git clone https://github.com/waynestate/weld.git my-app
cd my-app
```

Rename these four things:

| What | Where | Change |
| --- | --- | --- |
| Composer name | `composer.json`, the `"name"` field | `waynestate/weld` becomes your package, e.g. `wayne-state/my-app` |
| App name | `.env.example` (and `.env` once you have one) | `APP_NAME=WELD` becomes your app name; `VITE_APP_NAME` follows it through `${APP_NAME}` |
| Site title | `site/index.html`, the `<title>` tag | `WELD: Many parts, one piece.` becomes yours; the front page copy is about WELD itself, so rewrite it for your app |
| Git remote | repository config | point `origin` at your own repository |

The `App\` namespace stays as it is; nothing in the app, the tests or the welded packages needs it renamed.

Then give the copy its own key and remote. Skip the copy if `up` already made `.env`. Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
git remote set-url origin https://git.example.com/you/my-app.git
```

macOS and Linux (sh):

```sh
cp .env.example .env
php artisan key:generate
git remote set-url origin https://git.example.com/you/my-app.git
```

Then re-run the checks the way CI does: `.\bin\weld.ps1 test` (or `bin/weld.sh test`), which runs `composer ci` in the app container, plus `composer proctor` for the standards check, and `parity test` against the app Parity is running. A photocopy is not a weld yet: when the checks pass under your name, the seam holds.

Want a smaller starting point than a full Laravel app? Sourdough is the starter tool for every shape of Wayne State app. It bakes five shapes: raw PHP, raw PHP with a Vue SPA, Laravel with Blade, Laravel with Blade plus Vue islands, and Laravel with Inertia and Vue. WELD is the Inertia and Vue shape, already assembled with every part wired and ready to clone.

## What is welded in

| Part | What it does in WELD |
|---|---|
| WayneUI | The interface: Wayne State's design system, 2.0.8 from npm, pinned exactly. Its app bar, sidebar drawer, cards, forms and dialog draw every page, in light and dark, and ESLint keeps any other component kit out |
| CREED | The written standards and `policy.json`: supported PHP, Laravel and Node versions |
| Pinstripe | The coding standard, 0.3 in `require-dev`: PHPStan includes its Laravel preset (level 8 with Larastan, and the banned calls, SQL joined from strings among them) and Pint runs its Laravel preset. ESLint and Prettier run on WELD's own config until its npm half moves in |
| Preflight | The CI pipeline, from GitLab CI/CD components pinned at 0.6.0: tests on PHP 8.3, 8.4 and 8.5, audit, PROCTOR, accessibility, SBOM, secret detection, the MySQL 5.6 migrate job and portability |
| PROCTOR | The checks, run in CI and as the deploy gate |
| SESAME | Sign-in: CAS, Entra ID (OpenID Connect) and the auth gateway driver, and app-native sessions |
| HELMET | The baseline runtime: security headers and CSP, sessions, trusted proxies, `/up` and the `/ready` readiness route |
| ADVISOR | Dev only: the stack's read-only MCP server, so your coding agent knows the stack's rules and versions |
| Squire | The deploy kit in `deploy/`, at 0.10.8: the Citadel image, an idempotent release script and one portable image for other hosts |
| Laravel Boost | Dev only: the MCP server, guidelines and skills that teach your coding agent Laravel, with WELD's conventions in `.ai/` |
| Ziggy | Route names as URLs: the route list ships inside the Vite bundle, so `route()` works in Vue templates and scripts without an inline script |


<div align="center"><sub>One request, six stops, one seam. The bead runs the length of it; with reduced motion on, it stays put.</sub></div>

<details>
<summary><b>See the front page</b>: three cuts from <code>site/</code> at phone width</summary>
<br>

</details>

## Status

Every part is welded in: HELMET and SESAME are in `require`; CREED, Pinstripe, PROCTOR and ADVISOR are in
`require-dev`; WayneUI 2.0.8 comes from npm; CI pins Preflight 0.8.0; the deploy layer runs Squire's kit 0.10.8.
Pinstripe's PHP presets are installed; its ESLint and Prettier presets move in once `@waynestate/pinstripe` 0.3.0 is
on npm (the [status page](docs/reference/status.md#pinstripe-s-npm-half) has the steps). The bead is closed on the PHP side.
What is on the bench today:

- `composer.json` is `waynestate/weld`, requires PHP `^8.3`, and the lock file resolves for 8.3.0
- `.env.example` sets `APP_NAME=WELD`, `LOG_CHANNEL=stderr` and `SESSION_DRIVER=database`
- `Schema::defaultStringLength(191)` keeps index keys under Citadel's MySQL 5.6 767-byte cap
- `.gitattributes` sets `* text=auto eol=lf`
- `composer ci` is the CI contract: it runs validate, the tests with Pest, Pint, PHPStan level 8, the agent-ready check, the
  practices sync check, the docs check and audit, the same command on GitLab and GitHub
- `phpstan.neon` includes `vendor/waynestate/pinstripe/phpstan/laravel.neon` and adds only WELD's paths, so the
  level, the ignore rules and the banned calls (`eval()`, `dd()`, TLS checks turned off, SQL joined from strings)
  come from Pinstripe 0.3. `pint.json` is Pinstripe's `pint/laravel.json` plus two recorded excludes, Squire's
  `deploy/` kit and its vendored `bin/docs-site.php` (Pint cannot extend another config file), and
  `tests/Unit/PinstripeWiringTest.php` fails if either file drifts from that. In CI, `composer-ci` also saves Pint's
  findings as a Code Quality report for the merge request (`composer lint:gitlab`)
- HELMET runs its security headers and CSP middleware in the global chain, so every response carries the baseline;
  the Vite build takes the per-request nonce; trusted proxies come from `TRUSTED_PROXIES` (`bootstrap/app.php`); `/up` stays
  Laravel's built-in health route, and `/ready` (HELMET's readiness route, outside the web group, so no session
  or cookie) answers 503 while the database, the cache or the scheduler heartbeat fails
- Every queued job under `app/Jobs` declares `#[Tries]` and a `#[Timeout]` below the queue's `retry_after`
  (`tests/Feature/JobLimitsTest.php` fails the suite otherwise), and `php artisan make:job` writes both
  (`stubs/`: three tries, 45 seconds each)
- SESAME owns sign-in at `/login` and `/logout`; the fake driver stands in for the identity provider locally, and
  CAS is an env change away
- PROCTOR measures WELD against CREED's `policy.json` as a CI job (`composer proctor`): warnings pass, failures
  block
- The starter kit's password auth (register, login, password reset, two-factor) is removed on purpose; the door is
  SESAME's
- The license is MIT

**Testing.** Write new tests in Pest. Use an `it('...', function () { ... });` closure under `tests/Feature` or `tests/Unit`; the existing
PHPUnit class tests keep working and are converted only when you touch them. `composer test` runs both, and `tests/Pest.php` binds the
Feature tests to the Laravel app and a fresh database. The suite reads `.env` when it exists, otherwise `.env.example`
(`tests/Support/SkipEnvFile.php`, the same file Sourdough ships), and runs on a fixed dummy `APP_KEY` set in `phpunit.xml`, so it boots
with no `.env`; the app refuses that key in production, so a copied test config cannot ship. CI's `php-test` matrix runs `vendor/bin/pest`.
A deprecation your code raises or causes fails the suite (`failOnDeprecation` in `phpunit.xml`), so the matrix's newest PHP finds it
before an upgrade does; `tests/TestCase.php` hands deprecations to PHPUnit (`tests/Support/ReportsDeprecationsToPhpUnit.php`, from
Sourdough), because Laravel's own handler drops them while testing.
The accessibility gate runs Preflight's own checker (vendored in `tools/a11y/check.mjs`, pinned to the CI tag
0.8.0 by `tests/Unit/A11yCheckerPinTest.php`) over every page under `site/` with axe-core 4.14.0 (WCAG 2.2 AA,
light and dark, at 320 and 1280 px, plus the WCAG 1.4.10 reflow check), so a page that passes locally passes
CI. It runs in Docker: `scripts/a11y.sh` from Git Bash, Linux or macOS, `scripts/a11y.ps1` on Windows.

**The app's own gates.** The pipeline carries the three gates every Sourdough app carries, with the jobs copied
from the Inertia shape. The `a11y-pages` job boots WELD with SESAME's fake driver and saves `/` signed out and
`/dashboard` and both settings pages signed in into `a11y-site/`. The `a11y-app` job checks them with the same
checker, blocking. Add each page you build to `A11Y_PATHS` and to the `a11y-app` include's `pages`;
`tests/Feature/A11yPagesTest.php` fails when the two lists differ or when no path draws an Inertia page. Automated checks find only part of the
problems, so try each page with a keyboard and a screen reader too. Preflight's `sbom` job keeps `sbom.cdx.json`
(Composer) and the `frontend` job keeps `sbom-npm.cdx.json` (npm), each for 30 days. Preflight's `secret-detection`
job runs Gitleaks over the repository. Preflight's `portability` job builds the portable image
(`deploy/.squire/portable.Dockerfile`) with Buildah, on the kit's fallback base built in the same job, and boots it
twice as a uid with no name in group 0, the way OpenShift would: `/up` must answer an IP `Host`, nothing may be baked
in from `.env` or a config cache, forged forwarded headers must change nothing, logs must reach stderr, writes must
stay in `storage/` and `bootstrap/cache/`, and `SIGTERM` must stop it cleanly (BEST_PRACTICES W10, W13, W14, W16).
It runs on `main` and on tags and is manual on a merge request, and where it runs, a failed check fails the pipeline.
WELD deploys by Quick Update, so its pipeline holds no deploy credential. A
deploy job added later extends `.deploy`, which never runs for a merge request and runs only on a protected branch
or tag. The project settings a file cannot hold (protected `main` and tags, protected and masked variables, the job
token allowlist) are listed under "Pipeline" in the README Sourdough bakes.

Best practices for building, running and deploying WELD live in [BEST_PRACTICES.md](BEST_PRACTICES.md).

## Run it locally

Start with [Parity](https://parity.apps.wayne.edu/): it runs every stack app locally (the stack's an open stack question), on Citadel's
own machinery. Citadel's PHP image with its `php.ini` and `vhost.conf`, Citadel's proxy in front adding
`X-Forwarded-For` and `X-Forwarded-Proto`, and MySQL 5.6, the version of Citadel's shared database server. Parity is
one program for Windows, macOS and Linux. It needs no PHP on your computer and adds one file to the app,
`parity.json`. One command strikes the spark; the containers do the welding.

You need Docker Engine with the Compose v2 plugin (Docker Desktop includes it): `docker compose version` must answer,
because Parity runs that exact command. On a Linux host with Docker 27 or older and no plugin, install the
`docker-compose-plugin` package. PHP and Node run in containers, so the host needs neither.

### Run it like Citadel

Install Parity once from [its site](https://parity.apps.wayne.edu/), with no administrator needed. On Windows, in
PowerShell:

```powershell
irm https://parity.apps.wayne.edu/install.ps1 | iex
```

On macOS and Linux:

```sh
curl -fsSL https://parity.apps.wayne.edu/install.sh | sh
```

Prepare the folder once: a `.env`, the PHP and Node dependencies, the app key, and the front end built, because
Citadel serves built assets. These run in the stock Composer and Node containers from `compose.yaml`. Windows
PowerShell:

```powershell
Copy-Item .env.example .env
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm vite sh -c 'npm ci && npm run build'
```

macOS and Linux:

```sh
cp .env.example .env
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm vite sh -c 'npm ci && npm run build'
```

Then check this computer and write the app's Citadel settings, the same on every OS:

```
parity check
parity init --mysql
```

`bin/weld.sh up` and `.\bin\weld.ps1 up` print `parity init --mysql (first time only)`.

Add one line to `parity.json`, under `"mode"`, so the health check asks Laravel's `/up`, as Squire's `health.php`
does on Citadel. Parity's default asks the front page, which needs the sessions table, and that table arrives with
the migration below:

```json
"health_check": "curl -fsS -o /dev/null http://127.0.0.1/up",
```

Then bring it up, migrate as Citadel would, and test it:

```
parity up
parity shell --user citadel php artisan migrate
parity test
```

`parity check` says what to fix on this computer, one finding per line. `parity init` writes `parity.json`, your
app's Citadel settings (`--mysql` is Citadel's "MySQL: yes"). `parity up` builds and starts the app behind the proxy and
prints its address, http://localhost:8080 unless you pick a port. Your folder is mounted where Citadel mounts it, so an
edit shows on the next request, and the app gets Laravel's `DB_*` settings for the local MySQL from Parity, so `.env`
keeps its own database settings. The migration runs as the `citadel` user, the way Citadel runs a release.
`parity test` asks the running app what Citadel's users would. After a change, run `parity up` again; `parity logs -f`
follows the logs, `parity down` stops the app with the data kept, and `parity down --volumes` empties the database.

On a fresh database the front page answers 500 until the migration runs, so the first `parity up` ends on a warning
that says so; on Windows its proxy step can time out on that page instead. Carry on with the migration: `parity
test` is the word that counts. A folder on the Windows disk is slow inside the container: in the 2026-11-09 proof run
a page took about 10 seconds, and `php artisan --version` 9 seconds against a quarter of a second from the
container's own disk. Slow, but it holds.

No Citadel image on this computer yet? `parity image get` downloads it with your own git.wayne.edu access and checks
it byte for byte, and `parity up --fallback` builds a labeled stand-in from the official PHP image meanwhile.
[Parity's docs](https://parity.apps.wayne.edu/docs/) have the rest. Same app, same folder, now in Citadel's own image:
the bead looks identical under the hood, because it is the same hood.

### The bin/weld subcommands

`bin/weld.ps1` and `bin/weld.sh` are thin pointers for one release (the stack's an open stack question), and the next release deletes
them:

| Command | What it does |
|---|---|
| `test` | Run `composer ci` in the app container |
| `doctor` | Run `php artisan weld:doctor` in the app container |
| `up`, `down`, `logs` | Print the Parity commands that replace them (`parity up`, `parity down`, `parity logs -f`) and exit 2 |

Want Vite reloading as you type instead? Sail and plain Docker Compose still work: `compose.yaml` has the app on
http://localhost:8000 and Vite. After the prepare step, create `database/database.sqlite`, run
`docker compose run --rm app php artisan migrate`, then `docker compose up -d`. Stop it with `docker compose down`
and delete `public/hot`, the note Vite's dev server leaves so pages load from it, before you go back to Parity: left
behind, it gives you a blank page there.

Deploying somewhere other than a laptop? [Running WELD off Citadel](docs/running-off-citadel.md) covers OpenShift,
Coolify and plain Docker, with Citadel for comparison. Off Citadel, WELD ships as one image from Squire's kit
(`deploy/.squire/portable.Dockerfile`) that runs as `web`, `worker`, `scheduler` or `migrate`, as any UID in group 0
on port 8080: the same bead, four passes.

Does it hold? [Proof](docs/reference/proof.md) is the bend test: on 2026-11-06 a new app went from an empty folder to
a signed-in WayneUI dashboard in about four and a half minutes and to every CI gate green in about eight, with 0
accessibility violations in 16 page checks, and one small Wayne State app was ported onto it. The run found a blank
first page and a Windows `up` that emptied the local database; both are fixed, and the open findings are listed there.

## Oracle

WELD speaks Oracle through `yajra/laravel-oci8`, and the wiring is already in: an `oracle` connection in
`config/database.php`, a check command, and plain-language sentences for the common errors. Until you set the
Oracle settings it stays dormant: nothing connects and nothing errors.

Fill in one set of names in `.env` (the commented block in `.env.example`): `ORACLE_USERNAME`, `ORACLE_PASSWORD`,
and either `ORACLE_TNS` (an Easy Connect string, `host:port/service`, or a full descriptor) or `ORACLE_HOST`,
`ORACLE_PORT` and `ORACLE_SERVICE_NAME`. On a server, set them in the app's environment settings, never in a file
in git.

Check the settings and the connection in plain language, one line per finding:

```
php artisan oracle:check
```

The local `app` container runs the stock Composer image, which has no Oracle driver. When you need a real
connection from your machine, the Oracle-ready image is opt-in through `compose.oracle.yaml`:

```
# Windows (PowerShell):
$env:WELD_ORACLE_IMAGE = "squire/oracle-parity:8.3"
docker compose -f compose.yaml -f compose.oracle.yaml up
# macOS / Linux:
WELD_ORACLE_IMAGE=squire/oracle-parity:8.3 docker compose -f compose.yaml -f compose.oracle.yaml up
```

The overlay swaps only the app's image and passes the `ORACLE_*` settings through from `.env`; the ports stay
bound to `127.0.0.1`. It fails closed when `WELD_ORACLE_IMAGE` is unset.

`squire/oracle-parity:8.3` is the name Squire documents for its parity image; build it once with Squire's
`scripts/oracle-image-build.sh --flavour parity --php 8.3` (the name is a placeholder until a registry is chosen).

Or skip the overlay: Citadel's own PHP image carries `oci8`, so [Run it like Citadel](#run-it-like-citadel) with
Parity reaches Oracle the way the deployed app will (Parity's `--fallback` stand-in leaves `oci8` out).

## Check your setup

Maintainers: how to build, test and release this project, and where its data comes from, is in [MAINTAINING.md](MAINTAINING.md).

`weld:doctor` strikes an arc across the local setup and reports every joint: the app key,
writable storage, the database and pending migrations. One line per check, PASS or WARN or FAIL;
exit code 1 when anything FAILs, so it works as a gate. The standards checks (PHP version, debug
in production, secure session cookies) belong to PROCTOR: run `composer proctor`, and the doctor
report says so. A good bead is a quiet one: all PASS and nothing to say.

- Windows (PowerShell): `.\bin\weld.ps1 doctor`
- macOS / Linux: `bin/weld.sh doctor`
- Straight to the container: `docker compose run --rm app php artisan weld:doctor`

Add `--json` to any of them for a machine-readable report.

## Which command when

<!-- which-command-when: canonical copy in WELD docs/reference/which-command.md; edit there first, then copy word for word -->
Several stack commands sound alike (`weld:doctor`, `squire doctor`, `proctor check`) and do different jobs. Find your question in the second column and run the command beside it. The doctors look at setup, the proctor grades against policy, and the gate decides deploys: same waiting room, different appointments.

| Command | What it checks | When to run it | Where it runs |
|---|---|---|---|
| `composer ci` | **Will my pipeline pass?** The repo's own CI contract, the same command the pipeline runs: validation, the tests, Pint, PHPStan, `composer audit` and whatever checks the repo adds. | Before every push. | App repo, CI |
| `bin/weld.sh doctor` or `.\bin\weld.ps1 doctor` (both run `php artisan weld:doctor`) | **Does my WELD app hold together?** The app key, writable `storage` and `bootstrap/cache`, the database connection and pending migrations. One PASS, WARN or FAIL line each; exit 1 on a FAIL. Standards are PROCTOR's. | After `up`, when the app will not start, and after every deploy off Citadel. | App repo (in the app container), deploy host (in the app container) |
| `vendor/bin/squire doctor` | **Is my Citadel release setup right?** `build.sh`, the release scripts, the health check, script hygiene, line endings and secrets kept out of git, against Squire's rules; for the deprecated parity kit it says one thing: local runs are [Parity](https://parity.apps.wayne.edu/)'s job now (`parity check`). Exit 1 on a failed rule. The lock file, audit and MySQL 5.6 checks are handed to PROCTOR. | After `squire init` or `squire update`, and before the first deploy. | App repo, CI |
| `vendor/bin/squire init --check` | **Have the files Squire wrote drifted from its templates?** Missing, outdated or edited copies. Writes nothing; exit 1 on drift. | After you upgrade Squire (then run `squire update`), and in CI. | App repo, CI |
| `vendor/bin/squire doctor oracle`, or `php artisan oracle:check` in WELD and Sourdough's Laravel apps | **Can my app reach Oracle?** The `ORACLE_*` settings, the host, the port, the `oci8` driver and the login, in plain language, printing setting names and never values. Silent when the app has no Oracle settings. | After you fill in the `ORACLE_*` settings, or when a connection fails. Squire's `release.sh` runs the same check on every release, as a report. | App repo, deploy host |
| `vendor/bin/proctor check` (`composer proctor` in WELD and Sourdough's apps) | **Does my app meet CREED's policy?** The PHP floor, Laravel, Node and MySQL end of life, the committed lock, `composer audit`, open-ended constraints, portability, MySQL 5.6 safety, overrides and the rest of its checks. Exit 1 on a FAIL. | Before you merge; the pipeline runs it too. | App repo, CI |
| `vendor/bin/proctor gate` | **May this commit deploy?** The deploy-time subset of `proctor check`, ratcheted against the last release's verdict, so a deploy that makes nothing worse goes. Exit 1 is a no-go. | At deploy time. Squire's `release.sh` calls it for you. | Deploy host |
| `sourdough adopt <dir> --shape <shape>` | **Can Sourdough keep an app it did not make current?** Bakes the shape in a scratch folder, compares each template path the app has, and writes `.sourdough/manifest.json` and nothing else: a matching file becomes managed, a file that differs is recorded as yours and never overwritten, a seeded file stays yours, as in every Sourdough app, and a missing one arrives with the next feed. `--dry-run` writes nothing. Exit 1 on a refusal (a dirty tree, a manifest already there), 2 on a tool error. | Once, on a clean commit of an existing app, before its first `sourdough feed`. | App repo |
| `sourdough manifest --check` | **Which managed files did I change since Sourdough baked my app?** Managed files that changed or are missing against `.sourdough/manifest.json`; seeded and yours files are never reported: the app owns them. Exit 1 on a change. | Before `sourdough feed`. | App repo, CI |
| `sourdough feed --dry-run` | **Is my app behind its Sourdough template?** The plan a feed would apply, with nothing written. Exit 1 when feeding would change files. | After you install a new Sourdough, and in CI to catch an app falling behind. | App repo, CI |
| `booster plan` | **What does my upgrade take?** The ordered PHP, Laravel and front-end hops, the dependencies that block them, and advisory PHPCompatibility and PHPStan scans. Changes nothing. | Before an upgrade, and when PROCTOR says your PHP or Laravel is past support. | App repo |
| `booster replay check` | **Does my app still answer the same after a change?** Replays the requests in `booster-replay.json` and compares the answers with the ones `booster replay record` saved. Exit 1 on a changed answer. | After an upgrade hop or a hand fix, once you have a recording. | App repo (in Docker) |
| `booster init --check` | **Are Booster's coding-tab playbooks current?** The playbooks in `.agents/skills`. Changes nothing; exit 1 when one is missing or out of date. | After you upgrade Booster, and in CI. | App repo, CI |
| `php bin/regen-check.php --generated <dir>` | **Is WELD still Sourdough's Inertia shape?** WELD, file by file, against a fresh `sourdough new --inertia`; fails only on drift that `.sourdough/regen-baseline.json` does not already accept. | When Sourdough's Inertia shape or WELD changes. | WELD repo, CI |
| `rollcall report` | **Where does the whole fleet stand?** The latest scan's totals against CREED's policy: PHP, Laravel, the auth package, PROCTOR results and the end-of-life burn-down. Exit 0, or 2 on a tool error (no scan yet, a bad option, an unreadable policy); it never gates. | After `rollcall scan`, on the scan's schedule. | CI (a scheduled scan job) |
<!-- /which-command-when -->

This table is the canonical one, kept in the docs at [Which command when](docs/reference/which-command.md); the other READMEs copy it word for word.

## WELD and Sourdough

Sourdough is the starter tool for every shape of Wayne State app. It bakes five shapes: raw PHP, raw PHP with a Vue SPA, Laravel with Blade, Laravel with Blade plus Vue islands, and Laravel with Inertia and Vue.
WELD is one of those shapes, Laravel with Inertia and Vue, with every part already joined and proved to work together.
Pick Sourdough when you want a starter you shape yourself. Pick WELD when you want the finished piece.

## Is WELD still Sourdough's Inertia shape?

Sourdough writes a manifest of every file it generates (`.sourdough/manifest.json`: each path with its SHA-256).
`bin/regen-check.php` compares that manifest with WELD file by file, skipping the product files WELD adds on top
(listed in `.sourdough/product-files.json`) and any path with a `vendor` or `node_modules` segment. Each remaining
path is reported as IDENTICAL, DIFFERS, MISSING_IN_WELD or RELOCATED.

Sourdough keeps Squire's deploy kit at the app root, and WELD keeps it under `deploy/`; the gate reports those
files as relocated, not drift, when the `deploy/` copy is byte-identical. `php artisan oracle:check` finds the
check file in either place.

The gate is a ratchet: `.sourdough/regen-baseline.json` records the drift already known and accepted, and the check
fails only on new drift. CI runs it against a fresh `sourdough new --inertia` generation from Sourdough's main;
locally, point it at your own generation:

- Run the check: `php bin/regen-check.php --generated <dir>` (or `composer regen-check -- --generated <dir>`)
- Record the current drift as accepted: add `--write-baseline`

## The cast

<div align="center"><sub>Fifteen parts, one bead. The README art is cut from the front page by
<a href="scripts/readme-art/README.md"><code>scripts/readme-art</code></a>; no sparks were drawn in its making.</sub></div>
