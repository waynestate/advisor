

# Drawbridge

**Comes down for signed-in people.**

Checks: `composer ci` (the same command runs on GitLab and GitHub). The container rig has its own script, see "Run the checks".

> The one way into the Citadel. It only comes down for people who signed in.

Drawbridge is the sign-in gateway for the apps on Citadel, Wayne State's container platform. It is Apache with
mod_auth_openidc, set up as one OpenID Connect client for Microsoft Entra ID. It is not live yet: its home will be
`auth.apps.wayne.edu`, in front of every app on the platform, once the Citadel routing for it exists.
The latest release, 0.3.0, is tagged. It builds the gateway image, which will run as a service on Citadel. As a reverse proxy it
checks that the visitor has a session, drops any identity header the visitor sent, and passes the app a
short-lived signed assertion that SESAME's `gateway` driver verifies. Which paths need a login, and which need an
Entra authentication context on top, is declared per app in a config file. Nothing else decides it.

Why a moat and not a lock on every door: each app doing its own OpenID Connect is another chance to get a nonce,
a state check or a token signature wrong. We'd rather get that right once, in a maintained module
(mod_auth_openidc). Each app then checks one small signed token.

## Documentation

This page summarizes the gateway; the [docs site](site/docs/index.html) holds the full version, from [getting started](site/docs/install.html) and the guides through the [configuration](site/docs/reference/configuration.html) and [commands](site/docs/reference/commands.html) reference to [deploying](site/docs/deploying/getting-the-image.html) and releasing.

## Where the code lives

The canonical repository is [enterprise-apps/drawbridge](https://git.wayne.edu/enterprise-apps/drawbridge) on git.wayne.edu, cloned over HTTPS (`git clone https://git.wayne.edu/enterprise-apps/drawbridge.git`) or SSH (`git clone git@git.wayne.edu:enterprise-apps/drawbridge.git`).
Open issues and merge requests there. Tags are made there, by the maintainer, with an approver.
Drawbridge is not on GitHub: git.wayne.edu is its only home. The drawbridge is down at the castle; there is no mirror across the moat.

## Where it fits in the stack

Drawbridge is one part of the Wayne State application stack: one contract, several paths that agree, accessibility
as a gate, and decisions written down. WELD is the Laravel starter app with the rest of it already set up.

| Part | Job |
|---|---|
| WayneUI | the interface layer (project 0 of the stack) |
| CREED | the shared rules and policy format |
| PROCTOR | checks an app against the version policy |
| Preflight | the shared CI components |
| SESAME | sign-in inside the app; its `gateway` driver verifies what Drawbridge signs |
| HELMET | security headers and CSP reporting |
| Pinstripe | the coding standard presets (Pint, PHPStan, ESLint, Rector) |
| Sourdough | the starter that keeps the stack fed |
| **Drawbridge** | **the one OpenID Connect front door for the Citadel** |

## How it works

```
browser --> Apache httpd (Drawbridge) --> app container
              |  mod_auth_openidc : OpenID Connect client (Entra ID), one session cookie per app host
              |  mod_lua          : strips identity headers on every request, before anything else
              |  mod_rewrite      : asks the PHP signer for an assertion (prg: map)
              |  mod_proxy        : forwards to the app
              `- PHP component    : validates the config, renders the Apache config, signs assertions (ES256)
```

- A request for a **protected** path without a session goes to Entra to sign in (authorization code flow with PKCE)
  and comes back. For an **api** path it gets a plain 401, so a script never receives an HTML login page. A
  **public** path passes through with no identity attached. A **deny** path never reaches the app: the gateway
  answers 403 with its own page, signed in or not. `default: deny` makes every unlisted path a deny path, so only
  the paths an app lists exist at all. The most specific rule wins, whichever list it is in.
- A protected or api rule can name **groups** (`groups: [app1-admins]`, read from the ID token claim
  `oidc.groups_claim`, `wsu_roles` at WSU): a signed-in person who holds none of them gets 403. It is a coarse gate
  in front of the app; the app still decides what each person may do.
- mod_auth_openidc checks the ID token (signature, issuer, audience, nonce, expiry) and keeps the session. The
  claims reach the gateway in the request environment only, never as headers, and no Entra token is passed to an app.
- The assertion is a compact JWT with `typ: drawbridge+jwt` and a `kid`. Claims: `iss` (Drawbridge, never Entra),
  `aud` (the app's host), `sub` (the configured user claim, `accessid` in the example config and `preferred_username`
  if unset), `iat`, `nbf`, `exp`
  (60 seconds by default), `jti`; `auth_time`, `amr` and `acrs` when the ID token carries them; `email`, `name` and
   `affiliations` when configured; and any `extra_claims` (for example `wsu_roles`). A new one is signed for every
   request, and only from an ID token whose `iss` is the configured provider.
- If the signer cannot sign, the answer is 503 and the request does not reach the app. There is no
  unsigned mode: an app gets the signed assertion or nothing (the header modes were removed for 1.0).
- Header stripping: the guard removes every inbound `X-Authenticated-*`, `X-Sesame-*`, `X-Drawbridge-*`,
  `OIDC_CLAIM_*`, `OIDC_access_token` (and the module's other token names), `Remote-User`, `X-Forwarded-*`,
  `Forwarded` and similar header, in any hyphen or underscore spelling, on every request, before authentication
  runs. Every header under an owned prefix goes, whatever follows the prefix. It also trims `Connection` to `close`,
  `keep-alive` and `upgrade`, so a client cannot ask the proxy to drop a header the gateway set.

### Step-up with an authentication context

A path rule can name an Entra authentication context (`c1` to `c99`):

```yaml
protected:
  - /admin/
  - path: /admin/payroll/
    auth_context: c1
```

On that path the gateway asks Entra for the context with the OpenID Connect `claims` request parameter
(`{"id_token":{"acrs":{"essential":true,"value":"c1"}}}`) and lets the request through only when the ID token's
`acrs` array lists `c1`. A visitor already signed in without it is sent back to Entra to step up (mod_auth_openidc
answers with a short page that forwards the browser); an `api` path answers 401. What `c1` actually demands (MFA,
a compliant device, a fresh sign-in) is a Conditional Access policy in Entra, not Drawbridge config. The check is
made twice: by mod_auth_openidc's `Require claim acrs:c1`, and again by the gateway's own stamp step.

### Signing out

An app links to `/_drawbridge/oidc/callback?logout=<URL-encoded address on the same host>`. mod_auth_openidc ends
the gateway session for that host and sends the browser through Entra's sign-out endpoint (named in the provider's
discovery document) back to the address. An address on another host is refused, so the link cannot be used to bounce
visitors elsewhere.

To end a person's sessions from outside, on every app host at once, run `drawbridge revoke <subject>` where the
gateway reads its revocation list (`DRAWBRIDGE_REVOCATION_FILE`). On their next protected request a browser is sent
through the sign-out above and an `api` caller gets 401; a fresh sign-in works as before, so disable the account in
Entra to keep someone out. Entra offers other apps only an iframe front-channel logout, which blocked third-party
cookies break, so Drawbridge uses none: every sign-out hop is a top-level redirect.

Signing in is held the same way: a sign-in started with a `target_link_uri` on another host (written plainly, protocol-relative or with a userinfo trick) never sends the visitor there, and the answer never names that host. The rig proves it (`GatewayTest::testLoginReturnsOnlyToTheSameHost`).

### Error pages

The errors the gateway answers itself get a small page of their own instead of Apache's default English wall: 403
(an unknown host or a refused path), 502 and 504 (the app is down or taking too long) and 503 (the signer cannot
sign). Each page is one self-contained HTML file under `docker/gateway/errors/`: inline CSS, system fonts, no
scripts, no external assets, dark-mode aware, and no hostname or product name in the text. They are served from
`/_drawbridge/errors/`, which the app hosts keep out of the proxy. Two answers stay deliberately plain: an `api`
path keeps its bare 401, because a script must never be handed an HTML login page, and the `/metrics` endpoint keeps
its empty-body 403 (and 404), because a scrape must not look like a page. The endpoint is not routed by the gateway
config, so these pages cannot reach it; if you wire it up as the Metrics section describes, keep the empty body
inside that location. `tests/Unit/ErrorPagesTest.php` also pins their accessibility: WCAG 2.2 AA text contrast in
both color schemes, a viewport that does not block zoom, a visible focus outline of at least 2px, and a body text
size of at least 16px with headings set in rem or em.

### Response headers

Apps send their own security headers through HELMET, so the gateway never overrides one. On every proxied answer it
adds `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin` with `setifempty`, and
an app that sends either keeps its own. When the public scheme is https it also adds
`Strict-Transport-Security: max-age=31536000` the same way, with no `includeSubDomains` and no `preload`: the gateway
serves many hosts and must not decide subdomain or preload policy for them.

Protected and api paths also get `Cache-Control: no-store` with `setifempty`, so a signed-in page an app leaves
uncached cannot be served by a shared cache after the session ends. Public paths are untouched: the gateway adds no
`Cache-Control` there, and an app that wants its protected assets cached sends its own `Cache-Control`, which the
gateway never overrides.

The pages the gateway answers itself are not behind HELMET and carry their own headers, set plainly because no app
header is expected there. The `/_drawbridge/` locations it owns (the OpenID Connect endpoint on each app host, the
`/up` check on the default host) get `Cache-Control: no-store` and `Content-Security-Policy: frame-ancestors 'none'`.
The default catch-all host adds `X-Content-Type-Options: nosniff` and `Cache-Control: no-store` to everything it
answers, including the unknown-host 403 and the error pages.

## Install and quick start

You need Docker and nothing else. The PHP component needs PHP 8.3 or newer and Composer, and these steps run both
inside the `composer:2.10.3` container.

**Windows (PowerShell)**

```powershell
git clone https://git.wayne.edu/enterprise-apps/drawbridge.git; cd drawbridge
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 composer install --no-interaction
Copy-Item .env.example .env
Copy-Item config\drawbridge.example.yaml config\drawbridge.yaml
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 php bin/drawbridge config:validate --config=config/drawbridge.yaml
docker build -f docker/gateway/Dockerfile -t drawbridge-gateway .
```

**Linux and macOS**

```sh
git clone https://git.wayne.edu/enterprise-apps/drawbridge.git && cd drawbridge
docker run --rm -v "$PWD:/app" -w /app composer:2.10.3 composer install --no-interaction
cp .env.example .env
cp config/drawbridge.example.yaml config/drawbridge.yaml
docker run --rm -v "$PWD:/app" -w /app composer:2.10.3 php bin/drawbridge config:validate --config=config/drawbridge.yaml
docker build -f docker/gateway/Dockerfile -t drawbridge-gateway .
```

With PHP installed locally the same commands are `composer install` and `php bin/drawbridge ...`.

Then run the image with the two files it cannot start without, mounted read-only, and the two secrets in its
environment. The entrypoint refuses to start if any is missing, and `render` refuses an unusable secret:

| Item | What it is |
|---|---|
| `drawbridge.yaml` (file) | the per-app config, including the `oidc` block |
| `signing.key` (file) | the ES256 key that signs assertions (`keys:generate` makes one) |
| `DRAWBRIDGE_OIDC_CLIENT_SECRET` (env) | the Entra client secret (the variable name is `oidc.client_secret_env`) |
| `DRAWBRIDGE_OIDC_CRYPTO_PASSPHRASE` (env) | mod_auth_openidc's crypto passphrase, at least 32 characters |

```sh
docker run --rm -p 8080:8080 --env-file .env \
  -v "$PWD/config/drawbridge.yaml:/etc/drawbridge/drawbridge.yaml:ro" \
  -v "$PWD/secrets:/run/drawbridge:ro" drawbridge-gateway
```

The secrets are never written into the rendered Apache config: it says `OIDCClientSecret "${DRAWBRIDGE_OIDC_CLIENT_SECRET}"`
and Apache reads the value from its environment. Keys and secrets are never in git. `OPEN_QUESTIONS.md` lists where
each real one comes from.

### Try it without Entra

The container rig is the local trial: the real gateway image, a mock OpenID Connect provider standing in for
Entra, and a tiny echo app. It signs in synthetic users through the mock and checks protected and public paths,
spoofed headers, forged callbacks, logout and step-up to an authentication context. Nothing in the rig reaches
Entra.

```powershell
rig\run.ps1          # Windows
```

```sh
sh rig/run.sh        # Linux, macOS
```

The `keycloak` argument swaps the mock for a pinned Keycloak started from `rig/keycloak/realm.json` and runs the
sign-in, claims and logout tests against it. Unlike the mock, that provider checks the client secret and PKCE, signs
in only the realm's two synthetic users, and refuses redirect URIs it was not given, so the run proves the round trip
against a strict OpenID Connect implementation.

```powershell
rig\run.ps1 keycloak   # Windows
```

```sh
sh rig/run.sh keycloak # Linux, macOS
```

The `rotation` argument walks a crypto passphrase change through the running gateway without recreating it. It signs
in, saves the session cookie, re-renders the Apache config with the new passphrase while keeping the old one as
mod_auth_openidc's documented previous value, sends Apache a graceful reload, and replays the saved cookie. The run
documents what the pinned module actually does: it accepts the two values and starts, but its shared-memory session
store is rebuilt empty on every reload, so the saved cookie is refused and the browser is sent to sign in again. The
same refusal is then shown with the old passphrase dropped, and after the gateway container is recreated with a fresh
sign-in, so the result is not an accident of the reload.

```powershell
rig\run.ps1 rotation   # Windows
```

```sh
sh rig/run.sh rotation # Linux, macOS
```

When the rig is green, the next step is the first real sign-in: [ENTRA_TEST.md](ENTRA_TEST.md).

### Register Drawbridge in Entra ID

One app registration covers every app host. In the Entra admin center, under App registrations:

1. **New registration.** Single tenant. Platform **Web**.
2. **Redirect URIs.** One per app host: `https://<app host>/_drawbridge/oidc/callback` (for example
   `https://app1.apps.wayne.edu/_drawbridge/oidc/callback`). Each host gets its own session, so each needs its own
   redirect URI; add one whenever an app is added to the config. Leave the implicit grant boxes unticked: the gateway
   uses the authorization code flow with PKCE.
3. **Certificates & secrets.** Make a client secret and put it in the deployment's secret store as
   `DRAWBRIDGE_OIDC_CLIENT_SECRET`. Note its expiry date: Entra secrets expire, and the gateway cannot sign anyone in
   once it has (see `OPEN_QUESTIONS.md`, secret rotation).
4. **Token configuration.** Add the optional ID token claims the config reads: `accessid` (the AccessID, transformed
   from the user principal name with `ExtractMailPrefix`; this is what the example config uses for `oidc.user_claim`),
   `email`, `auth_time`, and `acrs` if any path uses an authentication context. Add `wsu_roles` as a mapped claim
   (the source attribute is `user.wsuRoles`, an on-premises AD attribute; the emitted claim is `wsu_roles`) and set
   `acceptMappedClaims: true` in the app manifest, so no custom signing key is needed and the metadata URL stays
   plain; list it in `oidc.extra_claims`. A tenant that does not send `accessid` leaves `oidc.user_claim` at
   `preferred_username`.
5. **Overview.** Copy the Directory (tenant) ID into `oidc.issuer`
   (`https://login.microsoftonline.com/<tenant-id>/v2.0`) and the Application (client) ID into `oidc.client_id`.
   Neither is a secret, but neither belongs in this repository either. The issuer is in Playbook as
   `SESAME_OAUTH2_ISSUER`; see the WSU values (Playbook) section below.
6. **Authentication contexts** (only if a path uses `auth_context`): in Conditional Access, create the context
   (`c1` ...), and a policy that targets it with whatever it should demand.

Before the first real sign-in, walk [ENTRA_TEST.md](ENTRA_TEST.md): every setting by name, and what to check for
each open question E-1 to E-8.

## Getting the image

The pipeline builds the gateway image on every merge request and on the default branch, and publishes it to the stack's image registry (the group's `images` project) when a tag is pushed. The image is at `<registry>/enterprise-apps/images/drawbridge-gateway:<tag>` (for example `<registry>/enterprise-apps/images/drawbridge-gateway:0.3.0`).
`<registry>` is the stack's container registry; Enterprise Applications has its address. There is no `latest` tag: pin a release.

```sh
docker pull <registry>/enterprise-apps/images/drawbridge-gateway:0.3.0
```

The publish job prints the digest it pushed. Verify the image you pulled against it:

```sh
docker inspect --format='{{index .RepoDigests 0}}' <registry>/enterprise-apps/images/drawbridge-gateway:0.3.0
```

The image carries OCI labels (`org.opencontainers.image.source`, `.revision`, `.version`, `.licenses`) set from the
pipeline's commit; a local `docker build` leaves them empty.

The image also refuses to build if Debian serves apache2 or mod_auth_openidc below the versions the rig tested:
the Dockerfile checks the installed versions with `dpkg --compare-versions` right after the install (apt-get has
no `>=` syntax) and stops the build with a clear message when one is lower. The floor is apache2 2.4.69-1~deb13u1
and mod_auth_openidc 2.4.17-1, checked 2026-11-03 against debian:trixie-slim. To raise it, bump the two versions
in the Dockerfile's install step and in `tests/Unit/DockerfileFloorTest.php`, which pins them.

## WSU values (Playbook)

A drawbridge lifts for barges it knows; these two settings tell it who is
coming. Both carry Wayne State's shared values, and both come from
[Playbook](https://git.wayne.edu/enterprise-apps/playbook), the private
repository of the university's shared `KEY=value` lines: never secrets, never
per-app values. Drawbridge does not read Playbook in code; it is where a
person looks up the values to type into `config/drawbridge.yaml` and `.env`.

| Playbook key | Drawbridge setting | Where it goes |
|---|---|---|
| `SESAME_OAUTH2_ISSUER` | `oidc.issuer` | `config/drawbridge.yaml`: the Entra issuer for the Wayne State tenant. Copy it from Playbook; never write a tenant id by hand. |
| `HELMET_PROXIES` | `DRAWBRIDGE_TRUSTED_PROXIES` | `.env`: the front proxy addresses, comma-separated CIDRs. |

**curl (Linux, macOS).**

```
curl --fail -H "PRIVATE-TOKEN: $GITLAB_TOKEN" "https://git.wayne.edu/api/v4/projects/enterprise-apps%2Fplaybook/repository/files/playbook.env/raw?ref=main" -o .env.playbook
```

**PowerShell (Windows).**

```powershell
Invoke-WebRequest -Headers @{ 'PRIVATE-TOKEN' = $env:GITLAB_TOKEN } -Uri "https://git.wayne.edu/api/v4/projects/enterprise-apps%2Fplaybook/repository/files/playbook.env/raw?ref=main" -OutFile .env.playbook
```

**A clone.**

```
git clone https://git.wayne.edu/enterprise-apps/playbook.git
```

The curl and PowerShell commands download the values to `.env.playbook`; copy
the two you need out of it. The secrets (`DRAWBRIDGE_OIDC_CLIENT_SECRET`,
`DRAWBRIDGE_OIDC_CRYPTO_PASSPHRASE`, `DRAWBRIDGE_METRICS_TOKEN`), the client
id and the signing key are never in Playbook. The token is your own: `read_api`
for curl, `read_repository` for clone.

Playbook is private on git.wayne.edu. If you get a 404, ask the Enterprise Applications team for read access.

## Configuration

One YAML (or PHP array) file declares the gateway, the OpenID Connect provider and each app.
`config/drawbridge.example.yaml` is annotated.

```yaml
oidc:
  issuer: https://login.microsoftonline.com/<tenant-id>/v2.0
  client_id: <application-client-id>
  client_secret_env: DRAWBRIDGE_OIDC_CLIENT_SECRET   # the NAME of the variable, never the secret
  user_claim: accessid                               # the AccessID; preferred_username for a tenant that does not send it
  extra_claims: [wsu_roles]                          # copied into the assertion when present

apps:
  - id: app1
    host: app1.apps.wayne.edu
    upstream: http://app1:8080
    mode: assertion          # the only mode
    default: login           # what a path with no rule gets
    public: [/up, /assets/]
    protected: [/admin/, {path: /admin/payroll/, auth_context: c1}]
    api: [/api/]
```

The `oidc` block also takes `metadata_url` (defaults to the issuer's `/.well-known/openid-configuration`),
`attribute_claims` (which claim feeds `email`, `name` and `affiliations`; `null` turns one off) and `amr_map` (Entra
`amr` values to the values SESAME's MFA check reads; the default maps `pwd`, `mfa`, `ngcmfa` as `mfa`, `otp`, `rsa`,
`wia`, and the REFEDS MFA URI). The issuer must be https; plain http is accepted only for a `.test` host, which is what
the local rig uses.

Path rules are exact (`/up`) or prefix (a trailing slash, `/assets/`). There are no wildcards and no regular
expressions. The longest rule wins, and an exact rule beats a prefix of the same length. Query strings are never part
of the match. A path with an encoded slash, a backslash, a semicolon, a control character or bad UTF-8 is treated as
`login` (and the guard answers 400). `/_drawbridge` belongs to the gateway.

### Session lifetimes

The `gateway` block sets how long a sign-in lasts and how long it may sit unused: `session_seconds` (default 28800
seconds, 300 to 604800) and `session_idle_seconds` (default 3600 seconds, 60 to 86400). Each app host renders them as
mod_auth_openidc's `OIDCSessionMaxDuration` and `OIDCSessionInactivityTimeout`.

### Three sessions, three clocks

A signed-in visitor has up to three sessions at once. Each has its own lifetime and its own owner, and ending one does
not end the others.

| Layer | Default lifetime | What ends it | What the visitor sees next |
|---|---|---|---|
| Entra (the identity provider) | set by Entra and its Conditional Access policy, not by Drawbridge | Entra's own policy, or the visitor signing out of Entra | Entra may sign them in again without a prompt, or ask for credentials |
| Gateway (mod_auth_openidc, one per app host) | 8 hours total, 1 hour idle (`session_seconds`, `session_idle_seconds`) | either clock, the `?logout=` link above, or a restart or reload of the gateway (the cache lives in shared memory and starts empty) | a protected path sends them to Entra; an `api` path answers 401 |
| App (SESAME) | 720 minutes total, 120 minutes idle (`session.absolute_minutes`, `session.idle_minutes`) | either clock, or the app calling `sesame_logout()` | the app's login route asks the gateway for a fresh assertion |

How they fit together:

- The assertion is used once, when the app signs the visitor in (it lives 60 seconds and SESAME's replay guard refuses
  a second use). After that the app's session runs on SESAME's clocks; a protected page reads SESAME's session, not the gateway.
- So the app session can outlast the gateway session. With the defaults it can: the app allows 12 hours, the gateway 8.
  The gateway session ending does not sign anyone out of the app; the visitor finds out the next time the app sends
  them through its login route. To make the app never outlive the gateway sign-in, set the app's
  `session.absolute_minutes` to `session_seconds / 60` or less (480 for the default).
- Signing out through the `?logout=` link ends the gateway session and asks Entra to end its own for that browser, not
  the app's. The app ends its own session first (`sesame_logout()`), then sends the browser to the link.
- A gateway restart signs everyone out of the gateway layer only; apps keep their sessions until their own clocks run
  out.

### Session store

The `gateway` block decides where the gateway sessions are kept:

- `shm` (the default): Apache's shared memory, encrypted per host. One instance, zero extra parts; a restart or
  graceful reload rebuilds the store empty, so everyone signs in again. Removing the setting returns to this
  behavior.
- `redis`: the sessions live in a Redis server named by `redis_host` (and `redis_port`, default 6379). Sessions
  then survive a restart and a graceful passphrase rotation, and more than one gateway instance can serve the same
  hosts when every instance points at the same Redis and uses the same passphrase. The password never goes in the
  config file: it comes from `DRAWBRIDGE_REDIS_PASSWORD`.

Redis is not a revocation list: it holds the sessions, but Drawbridge still cannot end one user's sessions from
outside (per-user revocation needs an index that is not built).

## Environment variables

| Variable | Default | Meaning |
|---|---|---|
| `DRAWBRIDGE_CONFIG` | `/etc/drawbridge/drawbridge.yaml` | the per-app config file |
| `DRAWBRIDGE_PORT` | `8080` | port Apache listens on (non-root, so above 1024) |
| `DRAWBRIDGE_PUBLIC_SCHEME` | `https` | how browsers reach the gateway; feeds `ServerName`, `X-Forwarded-Proto`, the redirect URI |
| `DRAWBRIDGE_PUBLIC_PORT` | empty | public port when it is not the scheme default |
| `DRAWBRIDGE_COOKIE_SECURE` | `true` | Secure session cookie; `false` only for a plain-HTTP local rig |
| `DRAWBRIDGE_TRUSTED_PROXIES` | empty | comma-separated CIDRs allowed to set `X-Forwarded-For`; empty trusts nobody |
| `DRAWBRIDGE_OIDC_CLIENT_SECRET` | none, required | the Entra client secret (or whichever name `oidc.client_secret_env` gives) |
| `DRAWBRIDGE_OIDC_CRYPTO_PASSPHRASE` | none, required | encrypts state cookies and sessions; 32+ characters; changing it signs everyone out (the `rotation` rig run documents why a previous passphrase cannot keep them) |
| `DRAWBRIDGE_OIDC_CRYPTO_PASSPHRASE_PREVIOUS` | unset | an old passphrase, rendered as the second value (new first, old second); mod_auth_openidc accepts it, but sessions live in shared memory that every reload or restart empties, so today it keeps nobody signed in (see the `rotation` rig run); it becomes useful once sessions move to a shared store |
| `DRAWBRIDGE_OIDC_CLIENT_SECRET_FILE` | unset | path to a file holding the client secret (Docker secrets, Kubernetes volumes); used only when `DRAWBRIDGE_OIDC_CLIENT_SECRET` is unset or empty |
| `DRAWBRIDGE_OIDC_CRYPTO_PASSPHRASE_FILE` | unset | the same for the crypto passphrase |
| `DRAWBRIDGE_OIDC_CRYPTO_PASSPHRASE_PREVIOUS_FILE` | unset | the same for the previous passphrase |
| `DRAWBRIDGE_SIGNING_KEY_FILE` | `/run/drawbridge/signing.key` | ES256 private key for assertions (mounted) |
| `DRAWBRIDGE_SIGNING_KEY_ID` | `drawbridge-1` | `kid` in each token header; change it when the key changes (name keys by date, for example `drawbridge-2026-11`) |
| `DRAWBRIDGE_SESSION_CACHE_ENTRIES` | `1000` | entries in the shared-memory cache: sessions, nonces, provider metadata (minimum 10; a smaller value or a non-number is refused when the config is rendered) |
| `DRAWBRIDGE_RUN_DIR` | `/tmp/drawbridge` | scratch for rendered config, locks and the session cache |
| `DRAWBRIDGE_LOG_LEVEL` | `warn` | Apache log level |
| `DRAWBRIDGE_EVENT_LOG` | `/dev/stdout` | where the JSON event log goes (sign-in, failed sign-in, sign-out, denied, revoked) |
| `DRAWBRIDGE_METRICS_TOKEN` | none | bearer token that may read `/metrics`; empty turns the endpoint off unless an allow list is set |
| `DRAWBRIDGE_METRICS_ALLOW` | none | comma list of client IPs/CIDRs allowed to read `/metrics`; empty trusts no address |

The file form is for platforms that hand secrets over as files. The gateway image reads the file at start, strips the trailing newline, and stops with a clear message (naming the variable, never the path or the contents) if the file is unreadable or empty. A plain variable always wins when both are set. The file form applies to these three default names only; a custom `oidc.client_secret_env` name keeps working as a plain environment variable.

## Commands

Run as `php bin/drawbridge <command>` (or `vendor/bin/drawbridge` when installed as a dependency).

| Command | Does |
|---|---|
| `config:validate` | checks the config and prints every problem at once, then the provider settings (never the secret, only whether its variable is set); exit 1 if any |
| `explain <host> <path>` | says which rule applies to a request, the authentication context and groups it needs, and whether it is denied |
| `revoke <subject>... [--list]` | ends every gateway session those people hold now, on every app host (the revocation list the gateway rereads each second) |
| `render` | checks the two secrets in the environment, then writes `apps.conf` and `policy.lua` for Apache (the entrypoint runs this) |
| `keys:generate` | makes an ES256 key pair; refuses to overwrite a file |
| `keys:jwks --key=KID=FILE ... [--out=FILE]` | writes the key set file apps verify with: the public keys, current then next, as a JWKS; refuses a private key |
| `signer:map` | the long-running process behind Apache's `prg:` map (not for people) |

## Run the checks

Maintainers: how to build, test and release this project, and where its data comes from, is in [MAINTAINING.md](MAINTAINING.md).

```sh
composer ci          # manifest, unit tests, Pint, PHPStan level 8, composer audit
```

The PHPStan and Pint settings come from Pinstripe, the stack's coding standard: `phpstan.neon` includes the raw
PHP cut, and `pint.json` uses the same `per` preset as Pinstripe's `pint/per.json`. This repository adds only its
own paths, PHP version range and excludes.

In Docker, with no local PHP:

```powershell
docker run --rm -v "${PWD}:/app" -w /app composer:2.10.3 sh -c "composer install --no-interaction && composer ci"
```

The accessibility gate runs axe-core (WCAG 2.2 AA) against the front page and every docs page in a real Chromium browser inside Docker, so no host browser is needed: `sh scripts/a11y.sh` on Linux and macOS, `scripts\a11y.ps1` on Windows.

The unit suite checks the assertion against a mirror of SESAME's `gateway` driver rules
(`tests/Support/SesameContract.php`). To check it against the real driver instead, point `SESAME_PATH`
at a SESAME checkout that has been installed (`composer install` in it) and run the integration suite;
without it the suite skips, so `composer ci` stays green:

```powershell
docker run --rm -v "${PWD}:/app" -v "C:\path\to\sesame:/sesame:ro" -e SESAME_PATH=/sesame -w /app composer:2.10.3 sh -c "composer install --no-interaction && vendor/bin/phpunit --filter RealSesame"
```

The container rig runs the real gateway image with a mock OpenID Connect provider
([mock-oauth2-server](https://github.com/navikt/mock-oauth2-server), pinned) standing in for Entra, and a tiny echo
app. It covers sign-in, public and protected paths, header spoofing (including forged `OIDC_*` headers), path tricks,
forged callbacks, step-up to an authentication context, signature checks against SESAME's `gateway` driver rules, and
logout. The mock signs in anyone with whatever claims the test types into its form, which is exactly what you want
from a stunt double. Nothing in the rig reaches Entra.

```powershell
rig\run.ps1          # Windows
```

```sh
sh rig/run.sh        # Linux, macOS
```

## Using the assertion in an app

Point SESAME's `gateway` driver at Drawbridge: the same issuer, the app's host as the audience, the key set file, and
the header `X-Sesame-Assertion`. Apps do not parse the token themselves.

### Signing keys and rotation

The gateway signs with one ES256 key at a time and names it in the `kid` header. Apps get the public keys as one
key set file (a JWKS) that always holds the **current** key and the **next** one, written by `keys:jwks`, and point
SESAME at it with `SESAME_GATEWAY_JWKS_FILE`. Because every app already trusts the next key, the gateway can switch to
it without any app changing first; the apps pick up a fresh file afterwards. Rotate every 12 months (and at once on a
suspected leak), and remove a retired key no sooner than 5 minutes after the switch. The steps, including the
emergency ones, are in [Rotating the signing key](docs/guides/rotating-the-signing-key.md); the reasoning is
[an ADR](ADR/an ADR-signing-key-distribution-and-rotation.md). Two keys on the ring: the guard can change locks
without anyone waiting outside the gate.

## Log events

Besides the access log, the gateway writes one JSON line for each sign-in, failed sign-in, sign-out, refusal and
revoked session, and nothing for an ordinary request:

```json
{"v":1,"event":"drawbridge.denied","time":"2026-11-07T15:04:05Z","host":"app1.apps.wayne.edu","path":"/admin/reports/q3","status":403,"user":"ab1234","rule":"login","reason":"group","client":"(client address)"}
```

The events are `drawbridge.signin`, `drawbridge.signin_failed`, `drawbridge.signout`, `drawbridge.denied` and
`drawbridge.revoked`, each refusal with a `reason`. The path is logged without its query string, and no cookie,
token or claim but the subject is ever written. `DRAWBRIDGE_EVENT_LOG` picks the file (default `/dev/stdout`).
[Log events](docs/reference/log-events.md) documents every field.

## Metrics

Drawbridge can expose a Prometheus `/metrics` endpoint, off by default. It is on when `DRAWBRIDGE_METRICS_TOKEN`
(a bearer token) or `DRAWBRIDGE_METRICS_ALLOW` (a comma list of client IPs/CIDRs) is set, and a request is served
when it presents the token or comes from an allowed address. A disabled or misconfigured endpoint answers 404 and an
unauthorized request answers 403, so the route is never advertised and never served to a stranger. The client
address is `REMOTE_ADDR` (the real client), never a client-supplied header, so a spoofed `X-Forwarded-For` cannot
grant access, and the token is compared in constant time.

When you wire it up yourself, point a web server at `bin/metrics` for the path. In Apache, a location such as:

```apache
<Location "/metrics">
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
    ScriptAlias /metrics /opt/drawbridge/bin/metrics
</Location>
```

serves it (the `SetEnvIf` passes the bearer token through; without it only the allow list works). In the gateway
image the endpoint is a php-cgi child: `render` maps `^/metrics$` to `bin/metrics` with a virtual-host-level
`ScriptAliasMatch` beside the Location block that stands outside sign-in and signing, and a fixup hook hands the
child the access rules, because Apache passes no server environment to a CGI child.

The endpoint serves only what it can see truthfully:

| Metric | Type | Meaning |
|---|---|---|
| `drawbridge_build_info{version}` | gauge | the gateway is up, and which version |
| `drawbridge_signing_requests_total` | counter | signing requests the assertion signer received |
| `drawbridge_assertions_signed_total` | counter | assertions the signer signed successfully |
| `drawbridge_assertion_errors_total{reason}` | counter | signing requests the signer refused or failed, by reason |

The signing counters cross from the long-running signer to the per-request endpoint through a shared file-backed
store: one JSON file per metric family in a directory (default `/tmp/drawbridge-metrics`, moved by
`DRAWBRIDGE_METRICS_DIR`), written atomically while a per-family lock is held, so a reader always sees a whole file
and two writers never lose an increment. Every process in the gateway container runs as the same UID, so plain file
permissions are enough. Without a shared store the endpoint registers no counters at all, because a counter that is
always zero is not shown.

Apache, not PHP, serves the requests, so request counts by path class are not exposed here either, and public
requests and sign-in failures never reach the PHP side; mod_auth_openidc would need its own status or log scraping
to expose those. Labels carry no identity, claim, or token, and the `reason` label is folded to a fixed set
(refused, failed, other), so it stays low-cardinality.

## Documents

- `BEST_PRACTICES.md`: the practices the gateway holds itself to, each with its sources, one tag and the tests that hold it.
- `ENTRA_TEST.md`: the first real Entra test: what you need to do.
- `OPEN_QUESTIONS.md`: what is unanswered, what is missing, what is not built yet.
- `ADR/`: decisions about Drawbridge itself, one file each (an ADR: signing key distribution and rotation).
- `CHANGELOG.md`: what changed.
- `site/`: the front page, a static sheet set for the gatehouse (`site/index.html`; see `site/README.md`).

On Citadel it is served by the raw-PHP deploy layer in [`deploy/`](deploy/): a Squire kit whose front controller serves `site/` behind HELMET's security headers. For local development, keep serving `site/` directly.

<details>
<summary><b>Sheets</b>: three windows onto the gatehouse</summary>
<br>

</details>

<details>
<summary><b>The brand board</b>: the mark, lockups, palette and type</summary>
<br>

</details>

## The name

It is a drawbridge, so it goes up on strangers and down on friends. If you find a header it should have caught,
raise the issue. The moat is only as good as the people who keep reporting where it is shallow.

## License

MIT. Copyright Wayne State University.

<div align="center"><b>Same castle, every sheet</b><br><sub>WayneUI and the stack. Drawbridge minds the gate.</sub></div>

<div align="center"><sub>Images are regenerated from the front page and the source by <a href="scripts/readme-art/README.md"><code>scripts/readme-art</code></a>. Moat sold separately.</sub></div>
