# WELD best practices

Checked against the code on 2026-10-28. The Laravel base has landed since the research draft was written, so the tags below reflect what the repository does today, not what the draft planned.

## What this covers

WELD is the whole Wayne State app stack joined into one Laravel 13 + Inertia/Vue application. It covers five areas: app structure, the container contract, queues and scheduling, security, and build and supply chain. This guide is for WSU developers who start from WELD and for the people who maintain it. It lists what good practice looks like in each area, what the research says, and whether the stack holds each practice today.

WELD wires SESAME (sign-in), HELMET (headers and the runtime baseline), PROCTOR (checks), PREFLIGHT (CI), Pinstripe (coding standard) and WayneUI (interface). It must run on Citadel and on OpenShift, Coolify or raw Docker.

### How to read the tags

Each practice ends with exactly one tag.

| Tag | Meaning |
| --- | --- |
| `Enforced` | WELD's code does it or refuses to run without it, and a test covers it. |
| `Checked` | PROCTOR or CI warns or fails. |
| `Documented` | Written down in the README or a config file, with no check behind it. |
| `Not yet` | A gap. |

The tags describe the repo as it stands on 2026-10-28. The Laravel base has landed: the starter kit is in, the password auth (register, login, password reset, two-factor) is removed on purpose, CI runs on both GitLab and GitHub from the same `composer ci` contract, and the stack defaults (PHP floor, stderr logging, database sessions and cache, the 191-character index cap) are set. The stack packages (SESAME, HELMET, PROCTOR, PREFLIGHT, Pinstripe, CREED and WayneUI) are not yet Composer or npm dependencies, so the practices that depend on them are `Not yet` and say which package they wait on. The `Stack:` line on each entry says what the tag should become and which tool owns it.

---

## 1. App structure

### W1. One owner of authentication: SESAME
- **Practice:** Remove the Laravel starter kit's Fortify routes and actions (`/login`, `/register`, `/forgot-password`, 2FA). SESAME's `findOrImportUser(Identity)` is the only way in.
- **Why:** Two login systems mean two session rules and two audit trails, and the kit's Fortify registers its routes automatically.
- **Sources:** [Laravel starter kits](https://laravel.com/docs/13.x/starter-kits) ("All starter kits use Laravel Fortify").
- **Stack:** `Not yet`. The Fortify removal is already in place and guarded: `routes/auth.php:17` answers `/login` with a 503 placeholder, and `tests/Feature/AuthRoutesTest.php` fails if WELD ever grows its own auth routes (register, password reset, two-factor, email verification, password confirmation) or if a Fortify controller sneaks back in. What is missing is SESAME itself: it is not a dependency yet, so `/login`, `/logout` and `/cas/*` are not owned by it. Target `Checked`: a PROCTOR check and a feature test over `php artisan route:list --json` fail on any auth route not owned by SESAME.

### W2. WayneUI is the only component source
- **Practice:** Pages use WayneUI components and tokens. Do not keep the kit's shadcn-vue components or Tailwind theme as a second design system.
- **Why:** The stack's promise is one interface contract on every consumer path; a parallel component set breaks the accessibility gate.
- **Sources:** [Laravel starter kits](https://laravel.com/docs/13.x/starter-kits) (Vue kit uses Tailwind and shadcn-vue).
- **Stack:** `Not yet`. The shadcn-vue components and their npm dependencies are gone, but WayneUI is not wired in: the Vue components carry `TODO(wayneui)` stand-ins (for example `resources/js/layouts/settings/Layout.vue:27` and `resources/js/pages/settings/Profile.vue:49`) because WELD does not load WayneUI's stylesheet yet, and the Tailwind theme (`tailwind.config.js`) plus `cn()`/`clsx`/`tailwind-merge` (`resources/js/lib/utils.ts`, `package.json`) remain. Target `Checked`: an ESLint rule (Pinstripe) bans imports from the kit's `components/ui` path, and a check fails when `components.json` exists.

### W3. Own the app code; take stack changes through `sourdough feed`
- **Practice:** The kit gives you the code ("full ownership"), with no kit upgrade path. WELD's wiring files are marked and refreshed by `sourdough feed`, not hand-edited.
- **Why:** Without a feed the app forks from the stack on day one and never gets fixes.
- **Sources:** [Laravel starter kits FAQ](https://laravel.com/docs/13.x/starter-kits) ("there is no need to update the starter kit itself").
- **Stack:** `Not yet`. Sourdough is not a dependency yet, so there is no feed to run. Target `Checked`: WELD's CI runs `sourdough feed` against each stack release and fails on drift.

### W4. Inertia 3 only; migrate off the removed APIs
- **Practice:** Use `@inertiajs/vue3` ^3 and `inertiajs/inertia-laravel` ^3. Do not import Axios, call `Inertia::lazy()` (use `Inertia::optional()`), read `data-page`, or `require()` Inertia packages.
- **Why:** Inertia 3 removed all of these; code copied from older tutorials fails or half-works.
- **Sources:** [Inertia v3 upgrade guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide.md).
- **Stack:** `Not yet`. WELD is on Inertia 2, not 3: `composer.json:13` pins `inertiajs/inertia-laravel: ^2.0` and `package.json:26` pins `@inertiajs/vue3: ^2.0.0-beta.3`. The removed APIs are not used (no `axios` dependency, no `Inertia::lazy`, no `data-page`), so the migration is a version bump, not a rewrite. Target `Checked`: ESLint `no-restricted-imports` for `axios`, a grep check for `Inertia::lazy`, and Composer constraints.

### W5. Commit `.ai/rules`; ignore what Boost regenerates
- **Practice:** Laravel Boost is a `require-dev` package. Commit `.ai/rules/` (project rules). Ignore `.mcp.json`, `boost.json` and the generated block of `CLAUDE.md`/`AGENTS.md`. WELD's own conventions go in `.ai/guidelines/` and each stack package may ship `resources/boost/guidelines/core.blade.php` and `resources/boost/skills/*/SKILL.md`.
- **Why:** Rules are shared team memory; generated files change with every `boost:update`.
- **Sources:** [Laravel Boost](https://laravel.com/docs/13.x/boost) (project rules "should be committed"; generated files may be gitignored).
- **Stack:** `Not yet`. Boost is not a dependency and there is no `.ai/` directory yet. Target `Checked`: PROCTOR confirms the `.gitignore` entries and that `.ai/rules/` exists.

### W6. Sail is local development only
- **Practice:** Sail's own description is "Laravel's default Docker development environment". It is not a production image and WELD never deploys it.
- **Why:** Sail's runtime images carry dev tooling and a bash-script CLI.
- **Sources:** [Laravel Sail](https://laravel.com/docs/13.x/sail).
- **Stack:** `Documented`. `composer.json:23` keeps `laravel/sail` in `require-dev`, so it is a local tool, not a runtime dependency. Target `Checked`: PROCTOR flags `laravel/sail` outside `require-dev` and any `vendor/laravel/sail` path in a production Dockerfile.

### W7. The supported dev path is Compose driven by native scripts, not Sail
- **Practice:** Sail runs on Windows "via WSL2". The stack rule is that Windows needs no bash and no WSL. Ship `weld.ps1` and `weld.sh` (or one PHP command) over a plain `compose.yaml`; Sail stays an unsupported convenience.
- **Why:** Every developer OS gets its own native path.
- **Sources:** [Laravel Sail](https://laravel.com/docs/13.x/sail).
- **Stack:** `Not yet`. There is no `compose.yaml` and no `weld.ps1`/`weld.sh` yet. Target `Checked`: a pair-parity test (same subcommands in both scripts).

### W8. One image, several process types
- **Practice:** Build one image. Run it as `web`, `worker` and `scheduler` by changing the command only (plus `ssr` when Inertia SSR is on). On Compose these are services; on OpenShift a Deployment, a Deployment and a CronJob.
- **Why:** One build means one tested artifact, and the process types stay identical in code.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues), [scheduling](https://laravel.com/docs/13.x/scheduling), [Inertia SSR](https://laravel.com/docs/13.x/starter-kits).
- **Stack:** `Not yet`. There is no Dockerfile yet. Target `Checked`: PREFLIGHT portability boots the image with each command.

## 2. The container contract

### W9. Config comes from the environment only
- **Practice:** No `.env` in the image. Call `env()` only inside `config/*.php`.
- **Why:** After `config:cache` the `.env` file is not loaded and `env()` returns `null` elsewhere.
- **Sources:** [Laravel deployment](https://laravel.com/docs/13.x/deployment).
- **Stack:** `Documented`. Every `env()` call in the app is inside `config/*.php`; none is in `app/`, `routes/` or `bootstrap/`. Target `Checked`: Pinstripe/PROCTOR ban `env(` outside `config/`; the portability job boots without `.env`.

### W10. `config:cache` runs at container start, never at image build
- **Practice:** Build time may run `route:cache`, `view:cache`, `event:cache`. `php artisan optimize` at start is acceptable if the build did not freeze config.
- **Why:** A build-time `config:cache` freezes the build machine's environment into the image.
- **Sources:** [Laravel deployment](https://laravel.com/docs/13.x/deployment).
- **Stack:** `Not yet`. There is no Dockerfile or entrypoint yet. Target `Checked`: PROCTOR scans the Dockerfile.

### W11. `/up` is the liveness endpoint, and it never checks the database
- **Practice:** Keep Laravel's `/up` (200 when the app boots). It must answer for any `Host`, over plain HTTP, before trusted hosts, redirects, sessions, CSP and auth. Put database and cache checks on a separate `/ready` endpoint, through the `DiagnosingHealth` event or a package.
- **Why:** A database blip must not make the orchestrator kill every web container.
- **Sources:** [Laravel deployment: the health route](https://laravel.com/docs/13.x/deployment).
- **Stack:** `Documented`. `bootstrap/app.php:13` sets the health route to `/up`, which answers 200 when the framework boots and touches no database. The `/ready` endpoint is not built yet. Target `Checked`: the portability job requests `/up` with `Host: 10.1.2.3:8080` and no forwarded headers, and again with the database stopped.

### W12. Image health check matches the platform
- **Practice:** Put a `HEALTHCHECK` in the Dockerfile that calls `/up` with a tool the image has (`curl` or `wget`). On Coolify a Dockerfile `HEALTHCHECK` takes precedence over the dashboard check, and a dashboard check needs `curl` or `wget` inside the container.
- **Why:** Traefik will not route to an unhealthy container, and a missing tool makes a healthy app look dead.
- **Sources:** [Coolify health checks](https://coolify.io/docs/knowledge-base/health-checks).
- **Stack:** `Not yet`. There is no Dockerfile yet. Target `Checked`: PREFLIGHT portability runs `docker inspect` for a health status of `healthy`.

### W13. Non-root, arbitrary UID in group 0, listen on 8080
- **Practice:** Declare a non-root `USER`. Make writable paths group 0 and group-writable (`chgrp -R 0`, `chmod -R g=u`) for `storage/` and `bootstrap/cache/`. Do not depend on a `www-data` entry. Honour `PORT`.
- **Why:** OpenShift runs a random UID in group 0 with all capabilities dropped.
- **Sources:** [serversideup/php](https://serversideup.net/open-source/docker-php) (8080 and non-root). The OpenShift arbitrary-UID text on a primary Red Hat page was not verified in this pass.
- **Stack:** `Not yet`. There is no Dockerfile yet. Target `Checked`: PREFLIGHT portability starts the image with a UID that has no `/etc/passwd` entry, `cap_drop: ALL`, `no-new-privileges` and a read-only root filesystem.

### W14. Logs go to stderr; the app writes only where it may
- **Practice:** Set `LOG_CHANNEL=stderr`. Nothing under `storage/logs`. Writes go to `storage/`, `bootstrap/cache/`, `/tmp` and declared volumes. Uploads go to object storage.
- **Why:** Platforms collect stdout/stderr; local files vanish with the container.
- **Sources:** [Laravel deployment](https://laravel.com/docs/13.x/deployment) (directory permissions).
- **Stack:** `Documented`. `.env.example:18` sets `LOG_CHANNEL=stderr`. Target `Checked`: the portability job fails when `storage/logs` is non-empty.

### W15. Sessions and cache live in a shared store
- **Practice:** Use `SESSION_DRIVER=database` (or Redis) and a shared cache, even for one replica. Every host overlaps old and new containers during a deploy.
- **Why:** A login must survive a hop between replicas.
- **Sources:** [Laravel scheduling](https://laravel.com/docs/13.x/scheduling) (`onOneServer` needs a shared cache).
- **Stack:** `Documented`. `.env.example:30` sets `SESSION_DRIVER=database` and `.env.example:40` sets `CACHE_STORE=database`; `config/session.php:21` and `config/cache.php:18` default to the same. Target `Checked`: the portability job logs in on replica A and reads the session on replica B.

### W16. Stop cleanly on SIGTERM
- **Practice:** Run PHP as PID 1 under a small init, or an entrypoint that `exec`s the server. Queue workers finish the current job on `SIGTERM`. Set the platform's grace period above the longest job `#[Timeout]`.
- **Why:** A hard kill mid-job double-runs it after `retry_after`.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues) ("the worker will finish its current job before exiting").
- **Stack:** `Not yet`. There is no Dockerfile or entrypoint yet. Target `Checked`: the portability job sends `SIGTERM` and requires exit 0 within the grace period.

### W17. Trust proxies by preset, never `*`
- **Practice:** Select a HELMET preset (`citadel` or `docker`), or list the front proxy in `HELMET_PROXIES` on other platforms. Do not paste `trustProxies(at: '*')` from tutorials. Set `APP_URL` and trusted hosts; Laravel trusts subdomains of `APP_URL` unless `subdomains: false`.
- **Why:** An app that trusts all proxies lets a client forge `X-Forwarded-*` and the host used in callbacks.
- **Sources:** [Laravel requests: trusted proxies and hosts](https://laravel.com/docs/13.x/requests).
- **Stack:** `Enforced` by HELMET 0.2 (required `^0.2`): it trusts only the preset or listed proxies and drops `X-Forwarded-Port`. Target `Checked`: a portability probe sends forged `X-Forwarded-Host` and `-Proto` from an untrusted peer.

## 3. Queues and scheduling without Citadel workers

### W18. Database queue by default; the worker is a process type
- **Practice:** `QUEUE_CONNECTION=database`; run `php artisan queue:work --max-time=3600 --tries=3` as the `worker` process where the platform allows one. Use `#[Tries]`, `#[Timeout]` and `#[Backoff]` on jobs.
- **Why:** It needs no extra service, and `--max-time` recycles memory.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues) (`--max-time`, job attributes, jobs table migration).
- **Stack:** `Documented`. `.env.example:38` sets `QUEUE_CONNECTION=database`, `config/queue.php:16` defaults to it, and the jobs tables are migrated by `database/migrations/0001_01_01_000002_create_jobs_table.php:14`. No job classes exist yet, so the `#[Tries]`/`#[Timeout]`/`#[Backoff]` attributes are not in use. Target `Checked`: a test that every job class declares `#[Tries]` and `#[Timeout]`.

### W19. Where no worker can run, run the queue from the scheduler
- **Practice:** On Citadel (no queue workers), schedule `queue:work --stop-when-empty --max-time=50 --tries=3` every minute with `withoutOverlapping` and a short expiry. Select it with `WELD_QUEUE_MODE=scheduled`.
- **Why:** `--stop-when-empty` is documented for Docker use ("shutdown the container after the queue is empty"), and the scheduler is the one cron entry Citadel can run. This exact pattern is an inference, not a documented recipe.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues), [scheduling](https://laravel.com/docs/13.x/scheduling).
- **Stack:** `Not yet`. `routes/console.php` has no scheduler entries yet (only the `inspire` command). Target `Checked`: a test that `schedule:list` shows the entry under the `citadel` preset and not under the others.

### W20. Timeout below `retry_after`
- **Practice:** Set each job timeout lower than the connection's `retry_after`. Timeouts need the PCNTL extension.
- **Why:** Otherwise the job is attempted again before it has finished.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues).
- **Stack:** `Not yet`. No job classes exist yet. Target `Checked`: a config test comparing the two values; the image installs `pcntl`.

### W21. One scheduler entry; one run per task
- **Practice:** Run `php artisan schedule:run` every minute (cron, or a CronJob), or `schedule:work` for a single long-lived container. With more than one scheduler, mark tasks `onOneServer()` (shared cache) and name closures.
- **Why:** Three schedulers run a nightly report three times.
- **Sources:** [Laravel scheduling](https://laravel.com/docs/13.x/scheduling).
- **Stack:** `Not yet`. `routes/console.php` has no scheduler entries yet. Target `Checked`: a test fails on any scheduled closure without `->name()`.

### W22. Interrupt and restart long-lived processes at deploy
- **Practice:** After a release run `php artisan queue:restart` (or `php artisan reload`), and `schedule:interrupt` when sub-minute tasks exist.
- **Why:** Workers and a minute-long `schedule:run` keep the old code until they exit.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues), [deployment](https://laravel.com/docs/13.x/deployment), [scheduling](https://laravel.com/docs/13.x/scheduling).
- **Stack:** `Not yet`. There is no release script yet. Target `Checked`: the release script lists the three commands; PROCTOR checks the file.

### W23. Make a silent scheduler visible
- **Practice:** Record a heartbeat on every scheduler minute and fail `/ready` (not `/up`) when it is older than a few minutes. Export the age as a Prometheus gauge (off by default).
- **Why:** With no worker supervisor, a stopped scheduler is otherwise invisible.
- **Sources:** [Laravel scheduling events](https://laravel.com/docs/13.x/scheduling) (`ScheduledTaskFinished`).
- **Stack:** `Not yet`. There is no scheduler and no `/ready` endpoint yet. Target `Checked`: a test advances a fake clock and asserts `/ready` fails.

### W24. Run migrations exactly once per release
- **Practice:** Use a Job, a pre-deploy hook (Coolify "Pre-deployment Command"), or `migrate --isolated`. Never run `migrate` in a per-replica entrypoint.
- **Why:** Parallel replicas race on schema changes.
- **Sources:** [Coolify](https://coolify.io/docs/knowledge-base/health-checks) (rolling update overlap).
- **Stack:** `Not yet`. There is no release script or entrypoint yet. Target `Checked`: PROCTOR flags `migrate` in an entrypoint.

## 4. Security

### W25. Keep the framework above the published security floor
- **Practice:** Require `laravel/framework` at or above the latest patched release, run `composer audit` in CI, and let CREED's `policy.json` name the minimum patch level. A critical TokenGuard authentication bypass was published on 2026-10-19 (fixed in 13.29.0 and 12.68.0).
- **Why:** Framework advisories arrive several times a year.
- **Sources:** [laravel/framework advisories](https://github.com/laravel/framework/security/advisories), [GHSA-f9x3-7vq9-cph6](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6).
- **Stack:** `Checked`. `composer.json:14` pins `laravel/framework: ^13.29` (at or above the patched release), `composer.json:51` runs `@composer audit` inside the `ci` script, and `.gitlab-ci.yml:9` includes the PREFLIGHT `composer-audit` component, so the audit runs in CI. CREED's `policy.json` is not a dependency yet, so the named floor is not read from it.

### W26. Debug is off outside local
- **Practice:** `APP_DEBUG=false` in production. The debug page has had an XSS advisory (Low, 2026-09-10).
- **Why:** Debug pages expose configuration values.
- **Sources:** [Laravel deployment: debug mode](https://laravel.com/docs/13.x/deployment), and the framework advisories in W25.
- **Stack:** `Not yet`. `.env.example:4` sets `APP_DEBUG=true` for the local default, and there is no boot assertion that fails when `APP_ENV=production` and `APP_DEBUG=true`. Target `Enforced`: a boot assertion fails when `APP_ENV=production` and `APP_DEBUG=true`.

### W27. CSRF uses the `PreventRequestForgery` middleware
- **Practice:** Laravel 13 formalised request-forgery protection as `PreventRequestForgery` (origin-aware, still token-compatible). Any custom middleware stack or HELMET code must reference the new name.
- **Why:** Code that removes or reorders the old name can silently drop protection.
- **Sources:** [Laravel 13 release notes](https://laravel.com/docs/13.x/releases). Whether the old class name remains as an alias is not confirmed.
- **Stack:** `Documented`. `bootstrap/app.php:19` appends to the default `web` middleware group, which carries `PreventRequestForgery` in Laravel 13, so the protection is in place by default. There is no feature test that posts without a token and expects 419. Target `Checked`: a feature test posts without a token and expects 419.

### W28. CSP works with Inertia 3 and Vite
- **Practice:** HELMET sets the CSP header and calls `Vite::useCspNonce()`; Laravel then adds the nonce to the tags `@vite` emits. Inertia 3 puts initial page data in a JSON script element. Run the policy in report-only first.
- **Why:** Laravel has no first-party CSP config, and a policy that blocks the app's own bundle is worse than none.
- **Sources:** [Inertia v3 upgrade guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide.md) (JSON script element); the nonce behaviour is from search results, not the Laravel page, so treat as unverified.
- **Stack:** `Not yet`. HELMET is not a dependency and no CSP is sent yet. Target `Checked`: a browser test loads three Inertia pages under an enforced CSP and fails on any violation.

### W29. Agent tooling stays out of production
- **Practice:** Laravel Boost is `require-dev`. Its MCP server can write rules to disk and run database queries; its docs tool sends queries to a hosted service. Production images are built with `composer install --no-dev`; guard any `post-update-cmd` that calls `boost:update`.
- **Why:** A dev tool with write and query abilities is attack surface.
- **Sources:** [Laravel Boost](https://laravel.com/docs/13.x/boost).
- **Stack:** `Not yet`. Boost is not a dependency yet. Target `Checked`: the portability job fails when `vendor/laravel/boost` exists in the image.

## 5. Build and supply chain

### W30. Support only PHP versions that are not end of life
- **Practice:** `composer.json` requires `php: ^8.3` with `config.platform.php` `8.3.0`. Tests run on 8.3, 8.4 and 8.5. Laravel 13 needs 8.3 or newer.
- **Why:** The stack's floor and the framework agree.
- **Sources:** [Laravel 13 releases](https://laravel.com/docs/13.x/releases).
- **Stack:** `Checked`. `composer.json:12` requires `php: ^8.3`, `composer.json:88` sets `config.platform.php` to `8.3.0`, and the CI matrix runs on 8.3, 8.4 and 8.5 (`.gitlab-ci.yml:8` and `.github/workflows/ci.yml:18`).

### W31. Keep dev tools inside the PHP floor
- **Practice:** Pest 5 requires PHP `^8.4`. On a `^8.3` floor use Pest 4 or PHPUnit and cap the constraint.
- **Why:** An unconstrained `composer require pestphp/pest` breaks installs on 8.3.
- **Sources:** [Pest on Packagist](https://repo.packagist.org/p2/pestphp/pest.json); Pint 1.32.1 requires `^8.3` ([Pint](https://repo.packagist.org/p2/laravel/pint.json)).
- **Stack:** `Checked`. WELD uses PHPUnit, not Pest: `composer.json:27` pins `phpunit/phpunit: ^12.0`, and the CI matrix starts at 8.3 (`.gitlab-ci.yml:8`), so `composer install` runs on 8.3 and would fail if a dev tool required 8.4 or newer.

### W32. Pinstripe owns style and static analysis
- **Practice:** Use Pinstripe's Pint preset, PHPStan level 8 through Larastan, ESLint (flat config) and Prettier. No local overrides without a recorded reason.
- **Why:** One standard across all stack repos.
- **Sources:** [Larastan](https://repo.packagist.org/p2/larastan/larastan.json).
- **Stack:** `Checked`, for the PHP side. `composer.json:43` runs `pint --test`, `composer.json:45` runs `phpstan analyse`, and both are in the `ci` script (`composer.json:46-52`), which CI runs; `pint.json:2` uses the `laravel` preset and `phpstan.neon:8` sets `level: 8`. The JS side is configured but not yet in the CI pipeline: `eslint.config.js` (flat config) and `.prettierrc` exist, but no CI job runs `npm run lint` or `npm run format:check`. Pinstripe is not a dependency yet, so the presets are copied, not included.

### W33. Pin shared CI components to a commit
- **Practice:** PREFLIGHT components are referenced by commit SHA or a fixed tag, not a moving branch.
- **Why:** A moved branch changes your pipeline without a review.
- **Sources:** [GitLab CI/CD include](https://docs.gitlab.com/ci/yaml/include/).
- **Stack:** `Documented`. `.gitlab-ci.yml:6` pins the `php-test` component to `@0.1.0` and `.github/workflows/ci.yml:16` pins the reusable workflow to a commit SHA, with a comment "Pinned to PREFLIGHT 0.1.0. Never use ~latest." There is no check that flags an unpinned include. Target `Checked`: PROCTOR flags `include:` entries without a SHA.

## Gaps for the backlog

The Laravel base has landed; the gaps below are the stack packages and the deploy path that have not.

- The stack packages are dependencies now: SESAME and HELMET in `require`, CREED and PROCTOR in `require-dev`, all from Packagist. The items that waited on them (SESAME W1, HELMET W17 and W28, PROCTOR's `Checked` targets, CREED W25) can be checked against the installed packages. PREFLIGHT portability (W8, W10, W12, W13, W16, W24), Pinstripe (W2, W32) and WayneUI (W2) are not wired into WELD yet.
- There is no Dockerfile, entrypoint or release script yet, so the whole container contract (W8, W10, W12, W13, W16, W22, W24) and the deploy-time queue/scheduler work (W19, W21, W23) are plans.
- The JS side of the coding standard (W32) is configured but not in CI.
- Unverified: OpenShift arbitrary-UID text on a primary Red Hat page; `serversideup/php` behaviour under a random UID; Pest 4's PHP floor; `spatie/laravel-health` scheduler and queue checks; whether Laravel's health route runs before `TrustHosts`; whether the old CSRF middleware name is still an alias.
- Decision needed: queue mode on Citadel (W19). The scheduled-worker pattern is a stopgap. Confirm with the deploy kit project whether Citadel can run a cron-driven `schedule:run` at all.
- Decision needed: `spatie/laravel-health` or our own `/ready` (W11, W23).
- Decision needed: whether WELD keeps Fortify for a non-SSO mode.
- Decision needed: SSR on by default or off.

## Sources

Checked on 2026-10-28.

- Laravel, [Release notes 13.x](https://laravel.com/docs/13.x/releases)
- Laravel, [Starter kits](https://laravel.com/docs/13.x/starter-kits)
- Laravel, [Deployment](https://laravel.com/docs/13.x/deployment)
- Laravel, [Task scheduling](https://laravel.com/docs/13.x/scheduling)
- Laravel, [Queues](https://laravel.com/docs/13.x/queues)
- Laravel, [Sail](https://laravel.com/docs/13.x/sail)
- Laravel, [Boost](https://laravel.com/docs/13.x/boost)
- Laravel, [HTTP requests: proxies and hosts](https://laravel.com/docs/13.x/requests)
- Inertia, [v3 upgrade guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide.md)
- GitHub, [laravel/framework security advisories](https://github.com/laravel/framework/security/advisories)
- Coolify, [Health checks](https://coolify.io/docs/knowledge-base/health-checks)
- serversideup, [docker-php](https://serversideup.net/open-source/docker-php)
- GitLab, [CI/CD include](https://docs.gitlab.com/ci/yaml/include/)
- Packagist feeds: [pest](https://repo.packagist.org/p2/pestphp/pest.json), [pint](https://repo.packagist.org/p2/laravel/pint.json), [larastan](https://repo.packagist.org/p2/larastan/larastan.json)
