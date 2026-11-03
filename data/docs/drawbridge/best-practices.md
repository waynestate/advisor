# Best practices

The rules Drawbridge holds itself to. Every rule names the tests that prove it, or says plainly that it has none yet.

1. **Strip first.** Remove every inbound identity header, in every spelling, on every request, before authentication
   runs, including anything that looks like mod_auth_openidc's own `OIDC_*` output, whether the client sends a header
   once or twice. Test: `tests/E2E/SpoofingTest.php`; the duplicated lines are pinned by
   `SpoofingTest::testADuplicatedIdentityHeaderNeverReachesTheApp`.
2. **Fail closed.** No session, no signature, a refused path, a missing key or a bad config means no access. A signer
   that cannot sign gives 503, never an unsigned request. A config error stops the container from starting. A session
   that the gateway has ended opens nothing: on an api path the answer is a bare 401, not a redirect to sign in.
   Test: `GatewayTest::testAnApiPathAnswers401ForADeadSession`.
3. **Public is a decision, not a default.** A path with no rule gets `login` unless the app says otherwise. Public
   paths carry no identity at all. Tests: the safe default is pinned by `ProtectionTest::testProtectionFor` and
   `GatewayTest::testAProtectedPathSendsAnAnonymousVisitorToSignIn`, and public paths carrying no identity by
   `GatewayTest::testAPublicPathNeedsNoLoginAndCarriesNoIdentity` and
   `GatewayTest::testASignedInVisitorSeesPublicPathsWithoutIdentity`.
4. **Match the normalized path, never the raw one.** Encoded slashes, backslashes, semicolons, control characters and
   bad UTF-8 are treated as protected. Query strings never choose a rule. Test: `PathAttackTest`, `PathNormalizerTest`.
5. **Exact or prefix, nothing clever.** No wildcards, no regular expressions in config. The Apache pattern is built
   from the literal, never typed by hand. Tests: `ConfigLoaderTest::testABrokenConfigIsRefusedWithAClearMessage`
   refuses a regex or a wildcard in a path, `ProtectionTest::testApachePatternsAreBuiltFromLiterals` builds the
   pattern from the literal, and `RenderTest::testRulesBecomeLiteralPatternsLeastSpecificFirst` shows them rendered.
6. **Sign narrowly and briefly.** ES256, a pinned `typ`, one audience per app, 60 seconds by default, a fresh `jti` on
   every request. The key never leaves the signer. Tests: `AssertionSignerTest::testTheTokenCarriesTheDocumentedClaims`
   pins the algorithm, `typ`, audience, lifetime and `jti` shape,
   `AssertionSignerTest::testTheAudienceIsTheAppNotWhateverTheRequestSays` pins the audience,
   `AssertionSignerTest::testEveryTokenHasItsOwnJti` and `GatewayTest::testEveryRequestGetsAFreshAssertionWithItsOwnJti`
   pin the fresh `jti`. The mounted key itself is exercised by the rig: every end-to-end sign-in fails if the signer
   cannot read it.
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
   trimmed to `close`, `keep-alive` and `upgrade`. Tests: `SpoofingTest::testForwardedForIsTheGatewaysViewNotTheClients`
   proves the app sees the gateway's view, `HeaderPolicyTest::testShouldStrip` pins the forwarded headers being
   stripped from the inbound request, and `HeaderPolicyTest::testFilterConnection` pins the `Connection` trimming.
10. **Keep secrets out of git and out of logs.** Keys are mounted files; the client secret and crypto passphrase are
    environment variables that the rendered config names but never holds, and the gateway refuses to start without
    them. The signer logs a reason code, never a subject, an attribute or a token. Test keys are generated when the
    tests run, and the rig's secrets are throwaway values. Tests: `RuntimeSecretsTest` pins the startup refusal, and
    `RenderTest::testTheProviderBlockNamesTheSecretsButNeverHoldsThem` pins the rendered config. The signer's log
    discipline is pinned by `SignerMapTest::testTheLogCarriesAReasonCodeAndNoSubjectOrToken` and
    `SignerMapTest::testAFailureLogsOnlyTheExceptionClass`.
11. **Do not trust the network.** The app verifies the signature even if only the gateway can reach it. Network
    isolation is a second control. Not tested: the app verifies the signature; that lives in SESAME, see its gateway
    tests.
12. **Run small and boring.** Non-root, port above 1024, state under `/tmp`, logs to stdout and stderr, no host paths
    in the image. It runs the same on Citadel, OpenShift, Coolify or plain Docker. Not tested: image properties are
    checked by the rig build, not by a unit test.
13. **Bounded dependencies.** No `*` and no `dev-master`; `composer audit` is part of `composer ci`. The gateway
    image refuses to build when Debian serves apache2 or mod_auth_openidc below the tested versions: the Dockerfile
    checks the installed versions with `dpkg --compare-versions` after the install and fails the build with a clear
    message. Test: `DockerfileFloorTest::testTheDockerfileEnforcesAModuleFloor` pins the floor in the Dockerfile.
    Not tested: nothing under `tests/` reads `composer.json`; the composer bound is enforced by `composer ci`,
    which ends in `composer audit`.
14. **Add headers, never overwrite.** The gateway's own response headers ride on proxied answers with `setifempty`,
    so an app that sends its own (through HELMET) keeps it; only the pages the gateway answers itself carry plain
    `set`. Tests: `RenderTest::testEveryAppHostAddsTheTwoSetIfEmptyHeaders` and
    `RenderTest::testNoAppHostOverridesAHeaderAnAppCouldSend` pin the `setifempty` side, and
    `RenderTest::testGatewayAnsweredLocationsCarryTheirOwnHeaders` pins the plain `set` on the locations the gateway
    answers itself.
