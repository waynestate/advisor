# Best practices

The rules Drawbridge holds itself to. Each one has a test.

1. **Strip first.** Remove every inbound identity header, in every spelling, on every request, before authentication
   runs, including anything that looks like mod_auth_openidc's own `OIDC_*` output, whether the client sends a header
   once or twice. Test: `tests/E2E/SpoofingTest.php`; the duplicated lines are pinned by
   `SpoofingTest::testADuplicatedIdentityHeaderNeverReachesTheApp`.
2. **Fail closed.** No session, no signature, a refused path, a missing key or a bad config means no access. A signer
   that cannot sign gives 503, never an unsigned request. A config error stops the container from starting. A session
   that the gateway has ended opens nothing: on an api path the answer is a bare 401, not a redirect to sign in.
   Test: `GatewayTest::testAnApiPathAnswers401ForADeadSession`.
3. **Public is a decision, not a default.** A path with no rule gets `login` unless the app says otherwise. Public
   paths carry no identity at all.
4. **Match the normalized path, never the raw one.** Encoded slashes, backslashes, semicolons, control characters and
   bad UTF-8 are treated as protected. Query strings never choose a rule. Test: `PathAttackTest`, `PathNormalizerTest`.
5. **Exact or prefix, nothing clever.** No wildcards, no regular expressions in config. The Apache pattern is built
   from the literal, never typed by hand.
6. **Sign narrowly and briefly.** ES256, a pinned `typ`, one audience per app, 60 seconds by default, a fresh `jti` on
   every request. The key never leaves the signer.
7. **Let the module do OpenID Connect.** Drawbridge writes no OAuth client: mod_auth_openidc runs the code flow with
   PKCE, checks state, nonce, issuer, audience and the token signature, and keeps the session. The gateway only reads
   the validated claims, refuses an ID token from any other issuer, and asks for an authentication context with the
   module's own per-path parameters. Forged callbacks and missing contexts are refused. Test: `OidcTest`,
   `PathAttackTest`.
8. **One session per app host.** Each host encrypts its sessions with a passphrase of its own, so a session cookie
   carried to another host opens nothing there. The session and state cookies are removed before the request is
   proxied, and no provider token (ID, access or refresh) ever reaches an app. Test:
   `GatewayTest::testTheSessionCookieIsHttpOnlyAndLaxAtCallback` pins the flags on the cookie the gateway sets:
   HttpOnly, SameSite=Lax, path `/`, and no Domain attribute beyond the host.
9. **Overwrite forwarded headers.** `X-Forwarded-*` come from the gateway, never from the client, and `Connection` is
   trimmed to `close`, `keep-alive` and `upgrade`.
10. **Keep secrets out of git and out of logs.** Keys are mounted files; the client secret and crypto passphrase are
    environment variables that the rendered config names but never holds, and the gateway refuses to start without
    them. The signer logs a reason code, never a subject, an attribute or a token. Test keys are generated when the
    tests run, and the rig's secrets are throwaway values. Test: `RuntimeSecretsTest`.
11. **Do not trust the network.** The app verifies the signature even if only the gateway can reach it. Network
    isolation is a second control.
12. **Run small and boring.** Non-root, port above 1024, state under `/tmp`, logs to stdout and stderr, no host paths
    in the image. It runs the same on Citadel, OpenShift, Coolify or plain Docker.
13. **Bounded dependencies.** No `*` and no `dev-master`; `composer audit` is part of `composer ci`.
14. **Add headers, never overwrite.** The gateway's own response headers ride on proxied answers with `setifempty`,
    so an app that sends its own (through HELMET) keeps it; only the pages the gateway answers itself carry plain
    `set`. Test: `RenderTest`.
