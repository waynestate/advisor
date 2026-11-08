# Runtime and headers

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## Enforcement

- The runtime (Baseline) is enforcement point (a): it warns, and refuses only for unsafe config
  (the stack's enforcement design).

## Headers

- Every response carries the same headers from HELMET (the stack's runtime and headers design):
  - Content-Security-Policy: the WayneUI default policy plus a per-request nonce and `strict-dynamic`, with
    `frame-ancestors 'self'`, `base-uri 'self'`, `object-src 'none'` and `form-action 'self'` added.
  - X-Content-Type-Options `nosniff`.
  - X-Frame-Options `SAMEORIGIN`.
  - Referrer-Policy `strict-origin-when-cross-origin`.
  - Permissions-Policy, with camera, geolocation, microphone, payment and usb denied by default.
  - Cross-Origin-Opener-Policy `same-origin`.
  - Strict-Transport-Security `max-age=31536000; includeSubDomains`, on HTTPS responses only.
- Cross-Origin-Resource-Policy is off by default and opt-in, because a blanket `same-site` can break an app whose
  assets are read cross-origin (the stack's runtime and headers design). HELMET 1.0.0 ships that default: `corp` (raw
  PHP) or `HELMET_CORP` (Laravel) set to `same-site`, `same-origin` or `cross-origin` sends it.
- An app SHOULD NOT weaken the default Content-Security-Policy with `'unsafe-inline'` for scripts; it uses the nonce
  instead (the stack's runtime and headers design). No check exists yet.

The headers are the setlist: the same one on every response. The HSTS encore plays only when the show is really on
HTTPS.

## Proxy trust

- Behind a proxy the app sees the proxy's address, so `X-Forwarded-Proto` is honored only from a trusted proxy (the
  stack's runtime and headers design).
- A platform preset (`citadel` or `docker`) names the trusted proxies; off the platform the app passes its own list.
  An unknown preset throws at boot (the stack's runtime and headers design).
- No Strict-Transport-Security is sent for a request that was not really secure (the stack's runtime and headers
  design).

## Sessions

- HELMET hardens the PHP session settings before the session starts (the stack's runtime and headers design).
- The request-forgery guard (Sec-Fetch-Site, then Origin) is optional and off by default; a rejected request gets 403
  `Forbidden` (the stack's runtime and headers design).

## Health

- `/up` is the health endpoint HELMET answers for raw PHP apps (the stack's runtime and headers design).

## Metrics

- HELMET's metrics endpoint speaks the Prometheus text format and is off by default. It stays off until a Prometheus
  server scrapes the app; then the app turns it on with `HELMET_METRICS_ENABLED=true` (the stack's metrics decision).
  HELMET: with neither `HELMET_METRICS_TOKEN` nor `HELMET_METRICS_ALLOW` set, the endpoint answers 404 and logs a
  warning.
- The scrape token MUST NOT be committed. On Citadel it goes in the app's `.env` through Citadel's configuration files
  step, which writes the file at deploy time (the stack's metrics decision). PROCTOR check: `app-key`
  (`proctor/app-key/committed-env` flags a committed `.env`).
- On Citadel, request counts use the `sqlite` store for now (`HELMET_METRICS_STORAGE=sqlite`, with
  `HELMET_METRICS_SQLITE_PATH` on a file in the app's writable storage); the final store is picked when the stack's
  Prometheus server exists (the stack's metrics decision). Documented only.

## Errors in production

- PHP `display_errors` MUST be `Off` and `log_errors` `On` in production (Squire's deploy kit ships that php.ini;
  HELMET neither sets nor checks it). No check exists yet.
- Laravel `APP_DEBUG` MUST be `false` in production (WSU developer standards, 2024). The Laravel adapter writes one
  warning `helmet.debug_enabled_in_production` when it is true in the production environment.

A PROCTOR check for this is proposed, not built.

## Not decided here

- The Content-Security-Policy report endpoints.
