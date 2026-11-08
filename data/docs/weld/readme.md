

# WELD

<p>

</p>

**W**ired, **E**verything, **L**inked, **D**eployed. Many parts, one piece.

WELD is the whole Wayne State app stack joined into one application. Install it, deploy it, and every part is already
hooked up: sign-in, security headers, checks, coding standards, CI and the WayneUI interface. Think of it as the
Megazord, except it comes with a deploy script and nobody has to shout to make it combine.

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

**Not to be confused with:** `weld:doctor` checks local setup only; standards checks are PROCTOR's.

## Get it

WELD is an app you start from, not a library you require. The Megazord ships in a repo, not a box.
Composer makes you a fresh copy, writes `.env`, generates the app key and runs the first migration:

```
composer create-project waynestate/weld my-app
cd my-app
npm ci
```

`create-project` installs the newest tagged release, and `main` can be ahead of it (the [CHANGELOG](CHANGELOG.md)
lists what is unreleased). 0.1.3 (2026-11-06) is the first tag where WayneUI draws the app; clone when you want
`main`.

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

The `advisor install` line writes `.mcp.json`, the MCP config that points Claude Code at ADVISOR's server; the file is git-ignored, because a committed `.mcp.json` would run a program on whoever opens the project. Set `ADVISOR_ENABLED=1` in `.env` to switch the advisor on. Running WELD through Docker instead? PHP is not on your host PATH, so `bin/weld.ps1 up` and `bin/weld.sh up` run the same install inside the app container for you (skip it with `--no-advisor`, or `-NoAdvisor` on Windows); see ADVISOR's README for the details.

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

Then re-run the checks the way CI does: `.\bin\weld.ps1 test` (or `bin/weld.sh test`), which runs `composer ci` in the container, plus `composer proctor` for the standards check. A photocopy is not a weld yet: when the checks pass under your name, the seam holds.

Want a smaller starting point than a full Laravel app? Sourdough is the starter tool for every shape of Wayne State app. It bakes five shapes: raw PHP, raw PHP with a Vue SPA, Laravel with Blade, Laravel with Blade plus Vue islands, and Laravel with Inertia and Vue. WELD is the Inertia and Vue shape, already assembled with every part wired and ready to clone.

## What is welded in

| Part | What it does in WELD |
|---|---|
| WayneUI | The interface: Wayne State's design system, 2.0.8 from npm, pinned exactly. Its app bar, sidebar drawer, cards, forms and dialog draw every page, in light and dark, and ESLint keeps any other component kit out |
| CREED | The written standards and `policy.json`: supported PHP, Laravel and Node versions |
| Pinstripe | The coding standard: Pint, PHPStan level 8, ESLint and Prettier |
| Preflight | The CI pipeline, from GitLab CI/CD components |
| PROCTOR | The checks, run in CI and as the deploy gate |
| SESAME | Sign-in: CAS, Entra ID (OpenID Connect) and the auth gateway driver, and app-native sessions |
| HELMET | The baseline runtime: security headers and CSP, sessions, trusted proxies, `/up` |
| Ziggy | Route names as URLs: the route list ships inside the Vite bundle, so `route()` works in Vue templates and scripts without an inline script |


<div align="center"><sub>One request, six stops, one seam. The bead runs the length of it; with reduced motion on, it stays put.</sub></div>

<details>
<summary><b>See the front page</b>: three cuts from <code>site/</code> at phone width</summary>
<br>

</details>

## Status

HELMET, SESAME and PROCTOR are welded in from Packagist; the rest of the cast is on Packagist too and gets welded on
in turn.
What is on the bench today:

- `composer.json` is `waynestate/weld`, requires PHP `^8.3`, and the lock file resolves for 8.3.0
- `.env.example` sets `APP_NAME=WELD`, `LOG_CHANNEL=stderr` and `SESSION_DRIVER=database`
- `Schema::defaultStringLength(191)` keeps index keys under Citadel's MySQL 5.6 767-byte cap
- `.gitattributes` sets `* text=auto eol=lf`
- `composer ci` is the CI contract: it runs validate, the tests with Pest, Pint, PHPStan level 8, the agent-ready check and audit, the
  same command on GitLab and GitHub
- `pint.json` and `phpstan.neon` still copy Pinstripe's 0.1 Laravel presets; Pinstripe 0.1.4 is on Packagist
  (tagged), and they switch to `includes: vendor/waynestate/pinstripe/...` when WELD requires it
- HELMET runs its security headers and CSP middleware in the global chain, so every response carries the baseline;
  the Vite build takes the per-request nonce; trusted proxies are a literal list in `bootstrap/app.php`; `/up` stays
  Laravel's built-in health route
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
The accessibility gate checks every committed HTML page under `site/` with axe-core (WCAG 2.2 AA, light and dark,
at 360 px and 1280 px) in Docker: `scripts/a11y.sh` from Git Bash, Linux or macOS, `scripts/a11y.ps1` on Windows.

Best practices for building, running and deploying WELD live in [BEST_PRACTICES.md](BEST_PRACTICES.md).

## Run it locally

One command strikes the spark; the containers do the welding. Docker is the only
prerequisite: PHP and Node run in the containers, so the host needs neither.

- Windows (PowerShell 5.1 or 7): `.\bin\weld.ps1 up`
- macOS / Linux: `bin/weld.sh up`

`up` copies `.env.example` to `.env` if it is missing, installs the PHP and Node
dependencies in the containers, starts the app and Vite, generates the app key if
it is not set, and runs the migrations. Then open http://localhost:8000.

The same subcommands work on every OS:

| Command | What it does |
|---|---|
| `up` | Bring the whole stack up, as above |
| `down` | Stop and remove the containers |
| `test` | Run `composer ci` in the app container |
| `doctor` | Run `php artisan weld:doctor` in the app container |
| `logs` | Follow the container logs |

Deploying somewhere other than a laptop? [Running WELD off Citadel](docs/running-off-citadel.md) covers OpenShift,
Coolify and plain Docker, with Citadel for comparison.

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

<div align="center"><sub>Fourteen parts, one bead. The README art is cut from the front page by
<a href="scripts/readme-art/README.md"><code>scripts/readme-art</code></a>; no sparks were drawn in its making.</sub></p>
