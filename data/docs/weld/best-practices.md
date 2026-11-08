# WELD best practices

Checked against the code on 2026-11-02. The Laravel base has landed since the research draft was written, so the tags below reflect what the repository does today, not what the draft planned.

## What this covers

WELD is the whole Wayne State app stack joined into one Laravel 13 + Inertia/Vue application. It covers five areas: app structure, the container contract, queues and scheduling, security, and build and supply chain. This guide is for WSU developers who start from WELD and for the people who maintain it. It lists what good practice looks like in each area, what the research says, and whether the stack holds each practice today.

WELD wires SESAME (sign-in), HELMET (headers and the runtime baseline), PROCTOR (checks), Preflight (CI), Pinstripe (coding standard) and WayneUI (interface). It must run on Citadel and on OpenShift, Coolify or raw Docker.

### How to read the tags

Each practice ends with exactly one tag.

| Tag | Meaning |
| --- | --- |
| `Enforced` | WELD's code does it or refuses to run without it, and a test covers it. |
| `Checked` | PROCTOR or CI warns or fails. |
| `Documented` | Written down in the README or a config file, with no check behind it. |
| `Not yet` | A gap. |

The tags describe the repo as it stands on 2026-11-02. Since 2026-10-28 the Squire deploy kit in `deploy/` (refreshed to 0.4.0) brought a Dockerfile, an entrypoint and an idempotent release script for the Citadel path, Oracle wiring arrived (`yajra/laravel-oci8`, `php artisan oracle:check`, `compose.oracle.yaml`), PROCTOR moved to `^0.3` with the strict WayneUI copies gate, and the suite gained `StackIntegrationTest` and `ProctorCheckTest`. The Laravel base has landed: the starter kit is in, the password auth (register, login, password reset, two-factor) is removed on purpose, CI runs on both GitLab and GitHub from the same `composer ci` contract, and the stack defaults (PHP floor, stderr logging, database sessions and cache, the 191-character index cap) are set. The stack packages are partly wired in: SESAME and HELMET sit in `require`, CREED and PROCTOR in `require-dev`, WayneUI is an npm dependency (W2, `Checked` since 2026-11-05), and Preflight and Pinstripe are not yet Composer dependencies, so the practices that depend on those two are `Not yet` and say which package they wait on. The `Stack:` line on each entry says what the tag should become and which tool owns it.

---

## 1. App structure

### W1. One owner of authentication: SESAME
- **Practice:** Remove the Laravel starter kit's Fortify routes and actions (`/login`, `/register`, `/forgot-password`, 2FA). SESAME's `findOrImportUser(Identity)` is the only way in.
- **Why:** Two login systems mean two session rules and two audit trails, and the kit's Fortify registers its routes automatically.
- **Sources:** [Laravel starter kits](https://laravel.com/docs/13.x/starter-kits) ("All starter kits use Laravel Fortify").
- **Stack:** `Checked`. SESAME is a dependency (`composer.json:24`), the starter kit's Fortify routes are removed, and `tests/Feature/AuthRoutesTest.php` fails if WELD grows its own auth routes: no register, password, two-factor, forgot, reset, verify or confirm URIs (`:17-30`), no `Laravel\Fortify` actions (`:32-43`), and `/login` and `/logout` owned by SESAME exactly once each (`:45-64`). Open: a PROCTOR check over `php artisan route:list --json`.

### W2. WayneUI is the only component source
- **Practice:** Pages use WayneUI components and tokens. Do not keep the kit's shadcn-vue components or Tailwind theme as a second design system.
- **Why:** The stack's promise is one interface contract on every consumer path; a parallel component set breaks the accessibility gate.
- **Sources:** [Laravel starter kits](https://laravel.com/docs/13.x/starter-kits) (Vue kit uses Tailwind and shadcn-vue).
- **Stack:** `Checked`. WayneUI 2.0.8 is the interface: `package.json` pins `@waynestate/wayne-ui-css` and `@waynestate/wayne-ui-vue` exactly (the lock's sha512 integrity is the checksum), `resources/css/app.css` imports the layered `dist/wayne-ui.css`, and the pages and layouts use WayneUI's Vue components (`AppShell`, `AppBar`, `Sidebar`, `Card`, `Input`, `Dialog`, `RadioGroup`). The Tailwind theme, `cn()`, `clsx`, `tailwind-merge`, `@headlessui/vue` and `lucide-vue-next` are gone. ESLint's `no-restricted-imports` rule in `eslint.config.js` bans the kit's `components/ui` path and those helpers, `tests/Feature/WayneUiOnlyTest.php` fails when `components.json`, a `components/ui` folder, a Tailwind dependency or a loose WayneUI pin appears, and the `frontend` job in `.gitlab-ci.yml` and `.github/workflows/ci.yml` runs lint, Prettier, vue-tsc and the build. Where WayneUI has no component (the account menu), a small local one is built on its tokens and says so in a comment.

### W3. Own the app code; take stack changes through `sourdough feed`
- **Practice:** The kit gives you the code ("full ownership"), with no kit upgrade path. WELD's wiring files are marked and refreshed by `sourdough feed`, not hand-edited.
- **Why:** Without a feed the app forks from the stack on day one and never gets fixes.
- **Sources:** [Laravel starter kits FAQ](https://laravel.com/docs/13.x/starter-kits) ("there is no need to update the starter kit itself").
- **Stack:** `Checked`. Sourdough is still not a Composer dependency, but a drift gate runs: `bin/regen-check.php` compares WELD file by file by SHA-256 against a fresh `sourdough new --inertia` generation, skips the product files in `.sourdough/product-files.json`, and fails only on new drift versus `.sourdough/regen-baseline.json`. The `regen-gate` jobs in `.gitlab-ci.yml:129-137` and `.github/workflows/ci.yml:42-59` block on new drift. Open: the gate clones Sourdough's `main` branch, not a pinned stack release.

### W4. Inertia 3 only; migrate off the removed APIs
- **Practice:** Use `@inertiajs/vue3` ^3 and `inertiajs/inertia-laravel` ^3. Do not import Axios, call `Inertia::lazy()` (use `Inertia::optional()`), read `data-page`, or `require()` Inertia packages.
- **Why:** Inertia 3 removed all of these; code copied from older tutorials fails or half-works.
- **Sources:** [Inertia v3 upgrade guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide.md).
- **Stack:** `Checked`. WELD is on Inertia 3: `composer.json:19` requires `inertiajs/inertia-laravel: ^3.0` and `package.json:26` requires `@inertiajs/vue3: ^3.0`. `tests/Feature/CspScriptTagsTest.php:53-60` pins the Inertia 3 JSON page island, so a regression to the Inertia 2 layout fails the suite. Open: an ESLint restriction on `axios` and a grep guard for `Inertia::lazy`.

### W5. Commit `.ai/rules`; ignore what Boost regenerates
- **Practice:** Laravel Boost is a `require-dev` package. Commit `.ai/`: project rules in `.ai/rules/` (with Boost's `index.md`) and WELD's own conventions in `.ai/guidelines/`. Ignore what `boost:install` and `boost:update` write: `.mcp.json`, `boost.json`, `CLAUDE.md`, `AGENTS.md`, `.claude/skills/` and `.junie/`. Git cannot ignore only the generated block inside `CLAUDE.md`, so the whole file is ignored and team guidance goes in `.ai/guidelines/`, which Boost folds into every agent's file. `.agents/` stays tracked (Booster's playbooks live there). Each stack package may ship `resources/boost/guidelines/core.blade.php` and `resources/boost/skills/*/SKILL.md`.
- **Why:** Rules are shared team memory; generated files change with every `boost:update`, and `boost.json` holds each developer's own agent picks.
- **Sources:** [Laravel Boost](https://laravel.com/docs/13.x/boost) (project rules "should be committed"; generated files may be gitignored).
- **Stack:** `Checked`. `composer.json` requires `laravel/boost: ^2.10` in `require-dev`; `.ai/guidelines/wayne-state.md` and `.ai/rules/` (`index.md`, `migrations.md`, `routes.md`) are committed as Sourdough's Inertia template ships them, and the `.gitignore` carries Sourdough's Boost block. `tests/Feature/BoostDevOnlyTest.php` "ignores what Boost regenerates and keeps what the team shares" fails CI when an ignore line or a committed `.ai` file goes missing, and the regen gate keeps the `.ai` files equal to Sourdough's. Open: a PROCTOR check for apps Sourdough did not bake.

### W6. Sail is local development only
- **Practice:** Sail's own description is "Laravel's default Docker development environment". It is not a production image and WELD never deploys it.
- **Why:** Sail's runtime images carry dev tooling and a bash-script CLI.
- **Sources:** [Laravel Sail](https://laravel.com/docs/13.x/sail).
- **Stack:** `Documented`. `composer.json:32` keeps `laravel/sail` in `require-dev`, so it is a local tool, not a runtime dependency. Target `Checked`: PROCTOR flags `laravel/sail` outside `require-dev` and any `vendor/laravel/sail` path in a production Dockerfile.

### W7. The supported dev path is Compose driven by native scripts, not Sail
- **Practice:** Sail runs on Windows "via WSL2". The stack rule is that Windows needs no bash and no WSL. Ship `weld.ps1` and `weld.sh` (or one PHP command) over a plain `compose.yaml`; Sail stays an unsupported convenience.
- **Why:** Every developer OS gets its own native path.
- **Sources:** [Laravel Sail](https://laravel.com/docs/13.x/sail).
- **Stack:** `Checked`. `compose.yaml` and the `bin/weld.ps1` / `bin/weld.sh` pair exist, and `tests/Unit/LocalStackTest.php:10-47` pins the pair's shared behaviour: the SQLite file is created only when missing and never truncated, and the Vite service writes a reachable listen address.

### W8. One image, several process types
- **Practice:** Build one image. Run it as `web`, `worker` and `scheduler` by changing the command only (plus `ssr` when Inertia SSR is on). On Compose these are services; on OpenShift a Deployment, a Deployment and a CronJob.
- **Why:** One build means one tested artifact, and the process types stay identical in code.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues), [scheduling](https://laravel.com/docs/13.x/scheduling), [Inertia SSR](https://laravel.com/docs/13.x/starter-kits).
- **Stack:** `Checked`, for the portable path. Squire's portable image (`deploy/.squire/portable.Dockerfile`, kit Unreleased) bakes the code in and runs as `web`, `worker` (`queue:work --tries=3 --max-time=3600 --sleep=3`), `scheduler` (`schedule:work`) or `migrate` by `SQUIRE_ROLE` or the first argument (`deploy/.squire/portable/squire-entrypoint.sh`), and the `portable` profile in `deploy/docker-compose.squire.yml` runs the four as services of one image, the scheduler with `QUEUE_WORKER_MODE: none`. `tests/Feature/PortableImageTest.php` fails when a role, the shared image or that switch goes, and Squire's `PortableImageTest` (`testThePortableProfileRunsOneImageAsFourRoles`, `testTheRolePicksTheProcess`) runs the entrypoint itself. Built and run for real on 2026-11-08: `migrate` exited 0, then `web`, `worker` and `scheduler` came up healthy and the worker ran a queued job. The Citadel mirror (`deploy/.squire/Dockerfile`) still runs Apache only, because Citadel has no worker process (W19). Open: no `ssr` role (SSR is off); Preflight portability does not boot the image in CI yet.

## 2. The container contract

### W9. Config comes from the environment only
- **Practice:** No `.env` in the image. Call `env()` only inside `config/*.php`.
- **Why:** After `config:cache` the `.env` file is not loaded and `env()` returns `null` elsewhere.
- **Sources:** [Laravel deployment](https://laravel.com/docs/13.x/deployment).
- **Stack:** `Checked`. Every `env()` call is inside `config/*.php` except one documented exception: `bootstrap/app.php:27` reads `TRUSTED_PROXIES` because the middleware closure runs before config is loaded, and carries a `@phpstan-ignore` for `larastan.noEnvCallsOutsideOfConfig`. That rule is live (`phpstan.neon:10` sets `reportUnmatchedIgnoredErrors: true`), and `phpstan analyse` runs in the `ci` script (`composer.json:60`, `:64-72`), so a new `env()` outside `config/` fails CI.

### W10. `config:cache` runs at container start, never at image build
- **Practice:** Build time may run `route:cache`, `view:cache`, `event:cache`. `php artisan optimize` at start is acceptable if the build did not freeze config.
- **Why:** A build-time `config:cache` freezes the build machine's environment into the image.
- **Sources:** [Laravel deployment](https://laravel.com/docs/13.x/deployment).
- **Stack:** `Documented`. The image build runs no artisan commands: `deploy/build.sh:49-51` fails if app source is present at build time, and the release step runs `php artisan optimize` once the environment is in place (`deploy/scripts/release.sh:453-459`), so config is cached at deploy, never at build. Target `Checked`: PROCTOR scans the Dockerfile.

### W11. `/up` is the liveness endpoint, and it never checks the database
- **Practice:** Keep Laravel's `/up` (200 when the app boots). It must answer for any `Host`, over plain HTTP, before trusted hosts, redirects, sessions, CSP and auth. Put database and cache checks on a separate `/ready` endpoint, through the `DiagnosingHealth` event or a package.
- **Why:** A database blip must not make the orchestrator kill every web container.
- **Sources:** [Laravel deployment: the health route](https://laravel.com/docs/13.x/deployment).
- **Stack:** `Checked`. `bootstrap/app.php:16` sets the health route to `/up`, and `tests/Feature/HelmetHeadersTest.php:41-51` fails the suite when `/up` stops answering 200. `/ready` is a separate route (HELMET's `Route::helmetReady()`, `routes/web.php:11`) that checks only the scheduler heartbeat (W23); it runs no database or cache checks yet.

### W12. Image health check matches the platform
- **Practice:** Put a `HEALTHCHECK` in the Dockerfile that calls `/up` with a tool the image has (`curl` or `wget`). On Coolify a Dockerfile `HEALTHCHECK` takes precedence over the dashboard check, and a dashboard check needs `curl` or `wget` inside the container.
- **Why:** Traefik will not route to an unhealthy container, and a missing tool makes a healthy app look dead.
- **Sources:** [Coolify health checks](https://coolify.io/docs/knowledge-base/health-checks).
- **Stack:** `Documented`. The Dockerfile carries a `HEALTHCHECK` (`deploy/.squire/Dockerfile:47`) whose script asks the local web server for `/up` by default and reads no database (`deploy/health.php:27`, `deploy/health.php:13`). Target `Checked`: Preflight portability runs `docker inspect` for a health status of `healthy`.

### W13. Non-root, arbitrary UID in group 0, listen on 8080
- **Practice:** Declare a non-root `USER`. Make writable paths group 0 and group-writable (`chgrp -R 0`, `chmod -R g=u`) for `storage/` and `bootstrap/cache/`. Do not depend on a `www-data` entry. Honor `PORT`.
- **Why:** OpenShift runs a random UID in group 0 with all capabilities dropped.
- **Sources:** [serversideup/php](https://serversideup.net/open-source/docker-php) (8080 and non-root), [OKD: support arbitrary user IDs](https://docs.okd.io/latest/openshift_images/create-images.html#use-uid_create-images) (a random UID in group 0; `chgrp -R 0` and `chmod -R g=u`; no ports below 1024).
- **Stack:** `Checked`, for the portable path. `deploy/.squire/portable.Dockerfile` declares `USER 10001:0` (no `/etc/passwd` entry, no `www-data`), hands Apache's run, lock and log folders and `storage/` and `bootstrap/cache/` to group 0 with `chmod -R g=u`, and Apache listens on `SQUIRE_PORT`, else `PORT`, else 8080 (`deploy/.squire/portable/ports.conf`, `deploy/.squire/portable/vhost.conf`); the entrypoint refuses a port below 1024 for a user that is not root. The compose profile runs it with `cap_drop: [ALL]` and `no-new-privileges`. `tests/Feature/PortableImageTest.php` holds the user, the group-0 folders and the port in WELD's copy, and Squire's `PortableImageTest` (`testThePortableImageRunsAsANumericNonRootUserInGroupZeroOn8080`, `testABadSettingStopsTheContainerWithAPlainMessage`) checks the kit. Run for real on 2026-11-08 as `--user 1000123:0`, the way OpenShift would: `/up` answered 200 on 8080 and `storage/` was writable. The Citadel image is not this image: it starts as root and drops to its own user, and its vhost stays on 80 (`deploy/.squire/vhost.conf:2`). Open: the base image's inherited `EXPOSE 80` still shows in the metadata; a read-only root filesystem is untested; Preflight portability does not run the image in CI yet.

### W14. Logs go to stderr; the app writes only where it may
- **Practice:** Set `LOG_CHANNEL=stderr`. Nothing under `storage/logs`. Writes go to `storage/`, `bootstrap/cache/`, `/tmp` and declared volumes. Uploads go to object storage.
- **Why:** Platforms collect stdout/stderr; local files vanish with the container.
- **Sources:** [Laravel deployment](https://laravel.com/docs/13.x/deployment) (directory permissions).
- **Stack:** `Documented`. `.env.example:18` sets `LOG_CHANNEL=stderr`. Target `Checked`: the portability job fails when `storage/logs` is non-empty.

### W15. Sessions and cache live in a shared store
- **Practice:** Use `SESSION_DRIVER=database` (or Redis) and a shared cache, even for one replica. Every host overlaps old and new containers during a deploy.
- **Why:** A login must survive a hop between replicas.
- **Sources:** [Laravel scheduling](https://laravel.com/docs/13.x/scheduling) (`onOneServer` needs a shared cache).
- **Stack:** `Documented`. `.env.example:38` sets `SESSION_DRIVER=database` and `.env.example:69` sets `CACHE_STORE=database`; `config/session.php:21` and `config/cache.php:18` default to the same. Target `Checked`: the portability job logs in on replica A and reads the session on replica B.

### W16. Stop cleanly on SIGTERM
- **Practice:** Run PHP as PID 1 under a small init, or an entrypoint that `exec`s the server. Queue workers finish the current job on `SIGTERM`. Set the platform's grace period above the longest job `#[Timeout]`.
- **Why:** A hard kill mid-job double-runs it after `retry_after`.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues) ("the worker will finish its current job before exiting").
- **Stack:** `Documented`. The entrypoint replaces the shell with the server (`deploy/.squire/fallback/citadel-apache-entrypoint:58`), so `SIGTERM` reaches the foreground process directly. On the portable path the image sets `STOPSIGNAL SIGTERM`: the `web` role turns it into Apache's graceful stop and the `worker` and `scheduler` roles exec PHP, which finishes the current job (Squire's `testSigtermIsAGracefulStopForApacheAndItsStatusIsTheContainers`; on 2026-11-08 `docker stop` ended all three in about two seconds with exit 0). The grace period is a platform setting. Target `Checked`: the portability job sends `SIGTERM` and requires exit 0 within the grace period.

### W17. Trust proxies by preset, never `*`
- **Practice:** Select a HELMET preset (`citadel` or `docker`), or list the front proxy in `HELMET_PROXIES` on other platforms. Do not paste `trustProxies(at: '*')` from tutorials. Set `APP_URL` and trusted hosts; Laravel trusts subdomains of `APP_URL` unless `subdomains: false`.
- **Why:** An app that trusts all proxies lets a client forge `X-Forwarded-*` and the host used in callbacks.
- **Sources:** [Laravel requests: trusted proxies and hosts](https://laravel.com/docs/13.x/requests).
- **Stack:** `Enforced` by HELMET 1.0 (required `^1.0`): it trusts only the preset or listed proxies and drops `X-Forwarded-Port`. Target `Checked`: a portability probe sends forged `X-Forwarded-Host` and `-Proto` from an untrusted peer.

## 3. Queues and scheduling without Citadel workers

### W18. Database queue by default; the worker is a process type
- **Practice:** `QUEUE_CONNECTION=database`; run `php artisan queue:work --max-time=3600 --tries=3` as the `worker` process where the platform allows one. Use `#[Tries]`, `#[Timeout]` and `#[Backoff]` on jobs.
- **Why:** It needs no extra service, and `--max-time` recycles memory.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues) (`--max-time`, job attributes, jobs table migration).
- **Stack:** `Documented`. `.env.example:67` sets `QUEUE_CONNECTION=database`, `config/queue.php:16` defaults to it, and the jobs tables are migrated by `database/migrations/0001_01_01_000002_create_jobs_table.php:14`. No job classes exist yet, so the `#[Tries]`/`#[Timeout]`/`#[Backoff]` attributes are not in use. Target `Checked`: a test that every job class declares `#[Tries]` and `#[Timeout]`.

### W19. Where no worker can run, run the queue from the scheduler
- **Practice:** On Citadel (no queue workers), schedule `queue:work --stop-when-empty --max-time=50 --timeout=45 --tries=3` every minute with `withoutOverlapping` and a short expiry. Select it with `QUEUE_WORKER_MODE=scheduler` (`none` leaves the queue to a supervisor).
- **Why:** `--stop-when-empty` is documented for Docker use ("shutdown the container after the queue is empty"), and the scheduler is the one cron entry Citadel can run. This exact pattern is an inference, not a documented recipe.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues), [scheduling](https://laravel.com/docs/13.x/scheduling).
- **Stack:** `Checked`. `routes/console.php` schedules `queue:work --stop-when-empty --max-time=50 --timeout=45 --tries=3` every minute with `withoutOverlapping(10)` when `config('worker.mode')` is `scheduler` (`config/worker.php`, env `QUEUE_WORKER_MODE`, default `scheduler` in `.env.example`); `tests/Feature/QueueWorkerScheduleTest.php` checks both modes. Byte for byte from Sourdough, held by the regen gate.

### W20. Timeout below `retry_after`
- **Practice:** Set each job timeout lower than the connection's `retry_after`. Timeouts need the PCNTL extension.
- **Why:** Otherwise the job is attempted again before it has finished.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues).
- **Stack:** `Checked` for the scheduled worker. `routes/console.php` passes `--timeout=45`, and `tests/Feature/QueueWorkerScheduleTest.php` fails when that is not below the default connection's `retry_after` (or, for `sync`, below every connection's; Laravel's default is 90). The deploy image installs `pcntl` (`deploy/.squire/base-fallback.Dockerfile`). No job classes exist yet: a job's own `#[Timeout]` must stay below `retry_after` too, and nothing checks that until one exists. Byte for byte from Sourdough, held by the regen gate.

### W21. One scheduler entry; one run per task
- **Practice:** Run `php artisan schedule:run` every minute (cron, or a CronJob), or `schedule:work` for a single long-lived container. With more than one scheduler, mark tasks `onOneServer()` (shared cache) and name closures.
- **Why:** Three schedulers run a nightly report three times.
- **Sources:** [Laravel scheduling](https://laravel.com/docs/13.x/scheduling).
- **Stack:** `Checked`. `tests/Feature/SchedulerNamingTest.php` (Sourdough's copy) fails the suite on any scheduled closure without `->name()`, and its second case proves the guard catches one.

### W22. Interrupt and restart long-lived processes at deploy
- **Practice:** After a release run `php artisan queue:restart` (or `php artisan reload`), and `schedule:interrupt` when sub-minute tasks exist.
- **Why:** Workers and a minute-long `schedule:run` keep the old code until they exit.
- **Sources:** [Laravel queues](https://laravel.com/docs/13.x/queues), [deployment](https://laravel.com/docs/13.x/deployment), [scheduling](https://laravel.com/docs/13.x/scheduling).
- **Stack:** `Checked`. The Squire 0.7.0 release step "laravel restart long-lived processes" (`deploy/scripts/release.sh:461`) runs `php artisan reload` where Laravel has it, otherwise `queue:restart` and `schedule:interrupt`, each when its own `--help` answers; report only, and `SQUIRE_ARTISAN_RESTART=0` turns the step off. `tests/Feature/ReleaseRestartTest.php` holds the commands and the switch in place.

### W23. Make a silent scheduler visible
- **Practice:** Record a heartbeat on every scheduler minute and fail `/ready` (not `/up`) when it is older than a few minutes. Export the age as a Prometheus gauge (off by default).
- **Why:** With no worker supervisor, a stopped scheduler is otherwise invisible.
- **Sources:** [Laravel scheduling events](https://laravel.com/docs/13.x/scheduling) (`ScheduledTaskFinished`).
- **Stack:** `Checked` for the heartbeat. `routes/console.php:17-19` stamps the cache every minute in a closure named `scheduler-heartbeat`, and `routes/web.php:11` registers HELMET's `/ready` with `App\Health\SchedulerHeartbeat`, which answers 503 while the stamp is missing or older than `worker.heartbeat_max_age` (`config/worker.php:31`, env `WORKER_HEARTBEAT_MAX_AGE`, default 180 seconds; 0 turns the check off for an app that runs no scheduler). `tests/Feature/SchedulerHeartbeatTest.php` proves it: "answers 503 on /ready when the scheduler has not written a heartbeat", "answers 200 on /ready right after the heartbeat closure runs", "answers 503 on /ready again once the clock moves past the max age" (the fake clock), "answers 200 on /ready with no heartbeat when the check is turned off" and "schedules the heartbeat every minute under its own name". Byte for byte from Sourdough, held by the regen gate. Open: the Prometheus gauge is not built, and nothing probes `/ready` yet: Squire's `health.php` (Citadel's Health Check and the compose healthcheck) asks `/up`, so only point a traffic probe at `/ready` where `schedule:run` really runs.

### W24. Run migrations exactly once per release
- **Practice:** Use a Job, a pre-deploy hook (Coolify "Pre-deployment Command"), or `migrate --isolated`. Never run `migrate` in a per-replica entrypoint.
- **Why:** Parallel replicas race on schema changes.
- **Sources:** [Coolify](https://coolify.io/docs/knowledge-base/health-checks) (rolling update overlap).
- **Stack:** `Documented`. The release step runs `php artisan migrate --force` (`deploy/scripts/release.sh:448`) once per release, from the first-release hook (`deploy/deployments/001-first-release.sh:25`) and the Quick Update post-script (`deploy/scripts/release.sh:7`); the entrypoint itself runs no artisan commands (`deploy/.squire/fallback/citadel-apache-entrypoint`). Target `Checked`: PROCTOR flags `migrate` in an entrypoint.

## 4. Security

### W25. Keep the framework above the published security floor
- **Practice:** Require `laravel/framework` at or above the latest patched release, run `composer audit` in CI, and let CREED's `policy.json` name the minimum patch level. A critical TokenGuard authentication bypass was published on 2026-10-19 (fixed in 13.29.0 and 12.68.0).
- **Why:** Framework advisories arrive several times a year.
- **Sources:** [laravel/framework advisories](https://github.com/laravel/framework/security/advisories), [GHSA-f9x3-7vq9-cph6](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6).
- **Stack:** `Checked`. `composer.json:20` pins `laravel/framework: ^13.29` (at or above the patched release), `composer.json:71` runs `@composer audit` inside the `ci` script, and `.gitlab-ci.yml:14` includes the Preflight `composer-audit` component, so the audit runs in CI. CREED's `policy.json` is a dependency and PROCTOR measures against it, now also inside the test suite (`tests/Feature/ProctorCheckTest.php`).

### W26. Debug is off outside local
- **Practice:** `APP_DEBUG=false` in production. The debug page has had an XSS advisory (Low, 2026-09-10).
- **Why:** Debug pages expose configuration values.
- **Sources:** [Laravel deployment: debug mode](https://laravel.com/docs/13.x/deployment), and the framework advisories in W25.
- **Stack:** `Enforced`. `.env.example:4` sets `APP_DEBUG=true` for the local default, and `app/Support/ProductionDebug.php:23-30` refuses to boot when the environment is production and debug is on; `app/Providers/AppServiceProvider.php:29` calls it in `boot()`, and `tests/Feature/ProductionDebugTest.php:17-30` covers both the refusal and the pass.

### W27. CSRF uses the `PreventRequestForgery` middleware
- **Practice:** Laravel 13 formalised request-forgery protection as `PreventRequestForgery` (origin-aware, still token-compatible). Any custom middleware stack or HELMET code must reference the new name.
- **Why:** Code that removes or reorders the old name can silently drop protection.
- **Sources:** [Laravel 13 release notes](https://laravel.com/docs/13.x/releases). Whether the old class name remains as an alias is not confirmed.
- **Stack:** `Checked`. `bootstrap/app.php:31-34` appends to the default `web` middleware group. `tests/Feature/CsrfTest.php:15-19` posts without a token and expects 419, `:21-29` lets the session token through, and `:31-37` lets a same-origin `Sec-Fetch-Site` request through.

### W28. CSP works with Inertia 3 and Vite
- **Practice:** HELMET sets the CSP header and calls `Vite::useCspNonce()`; Laravel then adds the nonce to the tags `@vite` emits. Inertia 3 puts initial page data in a JSON script element. Run the policy in report-only first.
- **Why:** Laravel has no first-party CSP config, and a policy that blocks the app's own bundle is worse than none.
- **Sources:** [Inertia v3 upgrade guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide.md) (JSON script element); the nonce behavior is from search results, not the Laravel page, so treat as unverified.
- **Stack:** `Checked`. HELMET `^1.0` is a dependency (`composer.json:23`) and its security headers and CSP run in the global chain: HELMET's own config defaults the scope to `global` (`vendor/waynestate/helmet/config/helmet.php:315`; WELD's `config/helmet.php` overrides only `policy_path` and the package merges the rest), `resources/views/app.blade.php:8` reads the per-request nonce and `:32` hands it to `Vite::useCspNonce()`, and `tests/Feature/HelmetHeadersTest.php:14-34` plus `tests/Feature/CspScriptTagsTest.php:23-60` fail the suite on header, nonce or `strict-dynamic` regression. The policy is enforced, not report-only (`vendor/waynestate/helmet/config/helmet.php:336`). Open: a real browser test under the enforced CSP.

### W29. Agent tooling stays out of production
- **Practice:** Laravel Boost is `require-dev`. Its MCP server can write rules to disk and run database queries; its docs tool sends queries to a hosted service. Production images are built with `composer install --no-dev`; guard any `post-update-cmd` that calls `boost:update`.
- **Why:** A dev tool with write and query abilities is attack surface.
- **Sources:** [Laravel Boost](https://laravel.com/docs/13.x/boost).
- **Stack:** `Checked`. Boost is in `require-dev` only and `composer.lock` lists it under `packages-dev`; `tests/Feature/BoostDevOnlyTest.php` "keeps Boost in require-dev only" and "locks Boost as a dev package, so composer install --no-dev leaves it out" fail the suite otherwise. The `no-dev-install` CI job (GitLab and GitHub) installs with `composer install --no-dev`, as Squire's `release.sh` does, fails when `vendor/laravel/boost` exists, and boots `php artisan`. The `post-update-cmd` hook calls `composer boost-update`, which runs `boost:update` only when `vendor/laravel/boost` is installed and `boost.json` names an agent ("skips the refresh where Boost is not set up", "runs boost:update and keeps its exit code where Boost is set up"). The portable image is held too: its build context leaves `vendor/` out (`portable.Dockerfile.dockerignore`) and its only Composer install is `scripts/release.sh`'s `--no-dev` one, so Boost cannot reach the image; `tests/Feature/PortableImageTest.php` "builds vendor/ without dev packages, so Boost never reaches the image (W29)" fails the suite if either of those facts drifts.

## 5. Build and supply chain

### W30. Support only PHP versions that are not end of life
- **Practice:** `composer.json` requires `php: ^8.3` with `config.platform.php` `8.3.0`. Tests run on 8.3, 8.4 and 8.5. Laravel 13 needs 8.3 or newer.
- **Why:** The stack's floor and the framework agree.
- **Sources:** [Laravel 13 releases](https://laravel.com/docs/13.x/releases).
- **Stack:** `Checked`. `composer.json:18` requires `php: ^8.3`, `composer.json:122` sets `config.platform.php` to `8.3.0`, and the CI matrix runs on 8.3, 8.4 and 8.5 (`.gitlab-ci.yml:9` and `.github/workflows/ci.yml:18`).

### W31. Keep dev tools inside the PHP floor
- **Practice:** Pest 5 requires PHP `^8.4`. On a `^8.3` floor use Pest 4 or PHPUnit and cap the constraint.
- **Why:** An unconstrained `composer require pestphp/pest` breaks installs on 8.3.
- **Sources:** [Pest on Packagist](https://repo.packagist.org/p2/pestphp/pest.json); Pint 1.32.1 requires `^8.3` ([Pint](https://repo.packagist.org/p2/laravel/pint.json)).
- **Stack:** `Checked`. WELD adopted Pest 4 on 2026-11-03: `composer.json` requires `pestphp/pest ^4` (Pest 5 needs PHP `^8.4`, and the CI matrix starts at 8.3, `.gitlab-ci.yml:9`), the PHPUnit tests still run unchanged, and new tests are written in Pest.

### W32. Pinstripe owns style and static analysis
- **Practice:** Use Pinstripe's Pint preset, PHPStan level 8 through Larastan, ESLint (flat config) and Prettier. No local overrides without a recorded reason.
- **Why:** One standard across all stack repos.
- **Sources:** [Larastan](https://repo.packagist.org/p2/larastan/larastan.json).
- **Stack:** `Checked`, for the PHP side. `composer.json:58` runs `pint --test`, `composer.json:60` runs `phpstan analyse`, and both are in the `ci` script (`composer.json:64-72`), which CI runs; `pint.json:2` uses the `laravel` preset and `phpstan.neon:8` sets `level: 8`. The JS side runs in CI too: the `frontend` job (`.gitlab-ci.yml`, `.github/workflows/ci.yml`) runs `npm run lint` (`eslint.config.js`, flat config) and `npm run format:check` (`.prettierrc`). Pinstripe is not a dependency yet, so the presets are copied, not included.

### W33. Pin shared CI components to a commit
- **Practice:** Preflight components are referenced by commit SHA or a fixed tag, not a moving branch.
- **Why:** A moved branch changes your pipeline without a review.
- **Sources:** [GitLab CI/CD include](https://docs.gitlab.com/ci/yaml/include/).
- **Stack:** `Checked`. `.gitlab-ci.yml` pins every Preflight component to the 0.3.1 release tag (`@0.3.1` at `.gitlab-ci.yml:7`, `:14`, `:19`, `:28`, `:37`, `:47`, `:51`), and `.github/workflows/ci.yml:16` pins the reusable workflow to Preflight 0.1.4's commit SHA until that mirror carries a release tag.

## Gaps for the backlog

The Laravel base has landed; the gaps below are the stack packages and the deploy path that have not.

- The stack packages are dependencies now: SESAME and HELMET in `require`, CREED and PROCTOR in `require-dev`, all from Packagist. The items that waited on them (SESAME W1, HELMET W17 and W28, PROCTOR's `Checked` targets, CREED W25) can be checked against the installed packages. Preflight portability (W8, W10, W12, W13, W16, W24), Pinstripe (W32) is not wired into WELD yet; WayneUI (W2) is, at 2.0.8 from npm.
- The Squire kit in `deploy/` now carries the Citadel deploy path: a Dockerfile mirroring the platform image, an entrypoint and an idempotent release script, so W10, W12, W16 and W24 hold for that path. It also carries the portable path: one image for the web, worker, scheduler and migrate process types (W8) that runs as any UID in group 0 on 8080 (W13). Still plans: Preflight portability booting that image in CI, and the deploy-time scheduler work (W23).
- Unverified: `serversideup/php` behavior under a random UID; Pest 4's PHP floor; `spatie/laravel-health` scheduler and queue checks; whether Laravel's health route runs before `TrustHosts`; whether the old CSRF middleware name is still an alias.
- Decision needed: queue mode on Citadel (W19). The scheduled-worker pattern is a stopgap. Confirm with the deploy kit project whether Citadel can run a cron-driven `schedule:run` at all.
- Decided: `/ready` is HELMET's `Route::helmetReady()` with app checks as invokable classes (the scheduler heartbeat today, W23); database and cache checks (W11) can join it the same way.
- Decision needed: whether WELD keeps Fortify for a non-SSO mode.
- Decision needed: SSR on by default or off.

## Sources

Checked on 2026-11-02.

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
