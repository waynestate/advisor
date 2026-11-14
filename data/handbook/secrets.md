# Secrets and debugging

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## Credentials

- Credentials (database and service passwords, API keys, tokens) MUST NOT be committed to version control, because history keeps them forever. Commit `.env.example` with placeholders instead. A credential that was ever committed is treated as exposed and rotated (WSU developer standards, 2024). PROCTOR checks: `app-key` (a committed `.env` or `APP_KEY`) and `repo-url-credentials` (a token in a package source URL). The general secret scan is Preflight's `secret-detection` component (Preflight 0.9.0), which runs Gitleaks over the tree and fails the pipeline on a match.
- Credentials and sensitive data MUST live outside any folder the web server serves. Laravel's `public/` is the only served folder; raw PHP apps serve one `public/` folder the same way (WSU developer standards, 2024). PROCTOR check: `served-folder` (dumps, backups, logs and env files in `public/`).
- The Laravel `APP_KEY` is a credential: whoever holds it can forge encrypted payloads, which can mean code execution. It MUST NOT be committed, a key that leaked MUST be rotated with `php artisan key:generate` on every environment that used it, and the session driver SHOULD NOT be `cookie`, which seals every session with the key (GitGuardian, exploiting public APP_KEY leaks). PROCTOR check: `app-key`.
- An app MUST use its own dedicated application ID, never a person's employee or student AccessID, to reach another server or service, with the fewest permissions the task needs (WSU developer standards, 2024). No check exists yet.
- Real values MUST come from the environment at deploy time (the Citadel dashboard or CI variables), never from a file in the repo (WSU developer standards, 2024). No check exists yet.

## Errors and debug output

- `APP_DEBUG` MUST be `false` in production (WSU developer standards, 2024). The Laravel adapter writes one warning `helmet.debug_enabled_in_production` when it is true in the production environment. PROCTOR check: `app-debug` (since PROCTOR 0.10.0), which warns when a committed production env template, deploy file or CI pipeline sets it to true.
- Public-facing code MUST NOT display default interpreter or framework error pages to the user; show a controlled message and log the detail (WSU developer standards, 2024). Held by: HELMET, when the app turns it on. Raw PHP apps get a fixed 500 page with no exception text from the opt-in error handler (`helmet_boot()` with `error_handler` enabled) and a fixed 4xx page from `helmet_client_error()`; Laravel apps set `HELMET_ERROR_PAGES=true` for the same 4xx pages and leave 5xx to Laravel's handler, which shows no detail with `APP_DEBUG` false; JSON clients keep Laravel's JSON answer. The 4xx pages are on HELMET main and ship in its next release. Nothing checks that an app turned them on.
- Logs and diagnostic output MUST NOT contain credentials or personally identifiable information such as Social Security numbers. That includes logged exceptions (WSU developer standards, 2024). No check exists yet.

A leaked password is a bad encore: everyone remembers it, and nobody asked for a second showing.

## Not decided here

- The HELMET header rules and runtime refusals are written in `runtime-and-headers.md`.
