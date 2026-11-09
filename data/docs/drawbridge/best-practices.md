# Drawbridge best practices

The sources were checked on 2026-10-27. The tags were checked against the code on 2026-11-07.

## What this covers

Drawbridge is the sign-in gateway in front of Citadel apps: Apache with mod_auth_openidc as one OpenID Connect client
of Microsoft Entra ID, a guard and a policy in mod_lua, and a small PHP signer. It removes every identity header a
client sent, decides per path whether a visitor must sign in, and hands the app a short-lived signed assertion that
SESAME's `gateway` driver verifies. This list is for the people who run Drawbridge and for app developers who sit
behind it. It says what good practice looks like, where it comes from, and whether Drawbridge holds it today.

The numbers (D1 to D61) are stable. D1 to D53 come from the research done before the gateway was built, so a reader
can follow an item from that research to the code. D54 onward were added with the code. The research was written for
a SAML service provider; Drawbridge became an OpenID Connect client instead, so the SAML-only items are listed, not
kept, under "Retired with SAML" at the end.

### How to read the tags

Each practice ends with exactly one tag.

| Tag | Meaning |
| --- | --- |
| `Enforced` | The gateway's code or rendered config does it, or refuses to start without it, and a test under `tests/` names it. Tests under `tests/E2E/` run in the container rig (`rig/run.sh` or `rig/run.ps1`); `RealSesameTest` runs when `SESAME_PATH` points at a SESAME checkout. |
| `Checked` | A build or CI step fails without it: `composer ci` or the image build. |
| `Documented` | Written down in the README or the docs, with no code here behind it. Often it is the app's or the platform's job. |
| `Not yet` | A gap, or a decision still open. |

`tests/Unit/BestPracticesTest.php` keeps this file honest: every practice has one tag, every `Enforced` practice names
its tests, and every test it names exists.

---

## 1. Identity delivery

### D1. Pass a signed assertion, never a bare header
The gateway gives the app a signed token that names the user. The app trusts the signature, not the header name or the sender's address.
Why: any header can be forged by a client or by another container unless a signature proves where it came from.
Sources: [Google IAP signed headers](https://docs.cloud.google.com/iap/docs/signed-headers-howto), [Cloudflare Access JWT validation](https://developers.cloudflare.com/cloudflare-one/identity/authorization-cookie/validating-json/), [Pomerium identity verification](https://www.pomerium.com/docs/capabilities/getting-users-identity).
How the stack holds it: `Enforced`. Every request on a protected or api path gets a fresh `X-Sesame-Assertion`, and SESAME's real verifier accepts it: `AssertionSignerTest::testTheTokenSatisfiesWhatSesameChecks`, `GatewayTest::testLoginDeliversASignedAssertionThatSesameWouldAccept`, `RealSesameTest::testAGoodTokenPassesWithTheRightUser`.

### D2. Remove inbound identity headers at the edge
Delete every `X-Authenticated-*`, `X-Drawbridge-*`, `X-Sesame-*` and `OIDC-Claim-*` header from the client request, sent once or twice, before authentication runs.
Why: a client must not be able to supply the identity the app will read.
Sources: [oauth2-proxy configuration (header stripping)](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/), [Shibboleth SP SpoofChecking](https://shibboleth.atlassian.net/wiki/spaces/SP3/pages/2065335299).
How the stack holds it: `Enforced`. The Lua guard removes them on every request, public paths included: `SpoofingTest::testASpoofedHeaderNeverReachesAPublicPath`, `SpoofingTest::testASpoofedHeaderNeverReachesAProtectedPathForASignedInUser`, `SpoofingTest::testADuplicatedIdentityHeaderNeverReachesTheApp`, `SpoofingTest::testGarbageInTheAssertionHeaderOnAProtectedPathIsReplacedNotForwarded`.

### D3. Strip header look-alikes too
Remove header names that differ only by case, hyphen or underscore, such as `X_Authenticated_User`, and anything shaped like mod_auth_openidc's own `OIDC_*` output.
Why: PHP and other CGI-style backends map both spellings to the same variable, so a client can send the look-alike and slip past a rule that names only the hyphen form. oauth2-proxy missed these variants until 7.13.0 (CVE-2025-64484, rated High).
Sources: [Traefik: header underscores](https://doc.traefik.io/traefik/v3.6/security/header-underscores/), [Apache environment variables](https://httpd.apache.org/docs/2.4/env.html), [oauth2-proxy advisory GHSA-vjrc-mh2v-45x6](https://github.com/oauth2-proxy/oauth2-proxy/security/advisories/GHSA-vjrc-mh2v-45x6).
How the stack holds it: `Enforced`. The policy compares names after folding case and reading underscores as hyphens, and the guard removes every such spelling of the gateway's own headers: `HeaderPolicyTest::testShouldStrip`, `HeaderPolicyTest::testTheNamesTheGuardRemovesCoverEverySpellingOfTheGatewayOwnHeaders`, `RenderTest::testThePolicyStripsOidcHeadersAnAttackerMightSend`.

### D4. Put the right claims in the assertion
Include the user, the attributes the app needs, an issued-at time, the app's host as audience, and an expiry of 60 seconds or less.
Why: a short life and a named audience limit what a stolen or copied assertion can do.
Sources: [RFC 8725 (JWT best practices)](https://www.rfc-editor.org/rfc/rfc8725.html), [ASVS 5.0 V9.2.1 to V9.2.4](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x18-V9-Self-contained-Tokens.md), [Google IAP signed headers](https://docs.cloud.google.com/iap/docs/signed-headers-howto).
How the stack holds it: `Enforced`. 60 seconds by default; a claim the ID token did not carry is left out, not guessed: `AssertionSignerTest::testTheTokenCarriesTheDocumentedClaims`, `AssertionSignerTest::testNoAuthTimeAmrOrAcrsWhenTheIdTokenDidNotSayAnything`, `AssertionSignerTest::testAnExtraClaimThatIsAnObjectIsDropped`.

### D5. Bind each assertion to one app
Each assertion names one app as audience, and the app's verifier rejects any other audience.
Why: an app container can probably reach another app directly, so an assertion for one app must be useless against another.
Sources: [RFC 8725 section 3.9](https://www.rfc-editor.org/rfc/rfc8725.html), [ASVS 5.0 V9.2.3 and V9.2.4](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x18-V9-Self-contained-Tokens.md), [NIST SP 800-63C-4 (audience restriction)](https://pages.nist.gov/800-63-4/sp800-63c.html).
How the stack holds it: `Enforced`. The audience is the configured app host, never what the request says: `AssertionSignerTest::testTheAudienceIsTheAppNotWhateverTheRequestSays`, `GatewayTest::testTheAssertionIsBoundToTheAppItWasMintedFor`, `RealSesameTest::testAWrongAudienceFails`.

### D6. Choose a signing scheme that one app cannot abuse against another
Sign asymmetrically: the gateway holds the private key, and apps hold only public keys.
Why: with one shared HMAC secret, any app that holds it could forge an assertion for any other app.
Sources: [RFC 8725 sections 3.1 and 3.2](https://www.rfc-editor.org/rfc/rfc8725.html), [ASVS 5.0 V9.1.2 and V9.1.3](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x18-V9-Self-contained-Tokens.md), [Pomerium](https://www.pomerium.com/docs/capabilities/getting-users-identity) and [Cloudflare](https://developers.cloudflare.com/cloudflare-one/identity/authorization-cookie/validating-json/) (both publish public keys).
How the stack holds it: `Enforced`. ES256 only; the key set apps read can never hold a private key: `JwksTest::testRefusesAPrivateKey`, `JwksTest::testTheCommandRefusesAPrivateKeyFile`, `JwksTest::testRefusesAnRsaKey`.

### D7. Pin the algorithm and publish keys with an ID and an overlap
Fix one algorithm per key, never accept `none`, and give each key an ID. Publish the next key before it is used, and keep the old one valid through an overlap.
Why: pinned algorithms stop downgrade attacks, and an overlap means rotation does not break sign-ins.
Sources: [RFC 8725 sections 3.1 and 3.2](https://www.rfc-editor.org/rfc/rfc8725.html), [Cloudflare Access JWT validation (rotation about every six weeks, old key valid for seven days)](https://developers.cloudflare.com/cloudflare-one/identity/authorization-cookie/validating-json/), [Google IAP signed headers](https://docs.cloud.google.com/iap/docs/signed-headers-howto).
How the stack holds it: `Enforced`. Each token names its `kid` (`DRAWBRIDGE_SIGNING_KEY_ID`), and `keys:jwks` writes the current and the next public key for apps: `JwksTest::testWritesTheCurrentAndTheNextKeyInOrder`, `RealSesameTest::testTheKeySetFileVerifiesTheCurrentAndTheNextKey`, `RealSesameTest::testAKeySetWithoutTheTokensKidFails`. The steps are in the guide Rotating the signing key.

### D8. Mark the assertion type so it cannot be reused as another token
Give the gateway assertion its own `typ`, distinct from the OAuth2 bearer tokens SESAME's `oauth2` driver accepts.
Why: two token kinds from related issuers can be swapped for each other if nothing tells them apart.
Sources: [RFC 8725 sections 3.11 and 3.12](https://www.rfc-editor.org/rfc/rfc8725.html), [ASVS 5.0 V9.2.2](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x18-V9-Self-contained-Tokens.md).
How the stack holds it: `Enforced`. `typ: drawbridge+jwt`, pinned by `AssertionSignerTest::testTheTokenCarriesTheDocumentedClaims`.

### D9. Verify in one tested library, not in each app
Apps verify the assertion through SESAME's `gateway` driver. They do not parse the header themselves.
Why: a hundred hand-written verifiers will include mistakes, and one shared one can be tested and fixed once.
Sources: [oauth2-proxy configuration](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/) (the upstream must trust only what the proxy sends), [Cloudflare Access JWT validation](https://developers.cloudflare.com/cloudflare-one/identity/authorization-cookie/validating-json/) (use a maintained library).
How the stack holds it: `Documented`. The guide Using the assertion in an app sends every app to SESAME. The gateway cannot make an app use it, but `RealSesameTest` proves the pair: SESAME's own verifier refuses an expired, tampered, replayed or wrongly signed token from this gateway. D36 asks PROCTOR to flag apps that read the header themselves.

### D10. Say who trusts what, and keep authorization in the app
The gateway proves who the user is. The app still decides whether that user may do the thing, through `findOrImportUser`.
Why: a WSU sign-in does not mean a person should reach every app or every record.
Sources: [NIST SP 800-63C-4 (the relying party maps the assertion to its own account)](https://pages.nist.gov/800-63-4/sp800-63c.html).
How the stack holds it: `Documented`. The README and the guide Protecting paths say so. Group rules (D55) are a coarse gate in front of the app, not the app's authorization.

## 2. Network position and bypass

### D11. Make the signature the control, and the network a second one
Assume a request can reach an app without passing through the gateway, and verify the signature anyway.
Why: if the app is reachable another way, nothing else confirms the request came through the gateway.
Sources: [Google IAP signed headers](https://docs.cloud.google.com/iap/docs/signed-headers-howto), [AWS ALB user authentication (verify the signature before any authorization, and restrict targets to the load balancer)](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/listener-authenticate-users.html), [oauth2-proxy configuration](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/).
How the stack holds it: `Documented`. The signature check lives in SESAME, in the app. SESAME also checks that the request came from a configured proxy peer, as the second control (`RealSesameTest::testANonProxyPeerFails`).

### D12. Close the direct path to app containers
Put each app on its own network, or make the shared network internal, so only the gateway can reach an app's port.
Why: it removes the easy bypass and limits what one compromised container can do.
Sources: [oauth2-proxy configuration (prevent clients reaching the proxied service directly)](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/), [AWS ALB user authentication](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/listener-authenticate-users.html), [CIS Docker Benchmark item 2.2](https://www.tenable.com/audits/items/CIS_Docker_v1.8.0_L1_OS_Linux.audit:5cd5ee336e7cfb70cfcb346673c3eed3).
How the stack holds it: `Not yet`. This is the platform's network, not the gateway's. D11 keeps apps safe while it stays open.

### D13. Do not use the source address as proof
Trust a signed assertion, not "the request came from the gateway's address".
Why: on a shared subnet the attacker is another container with a nearby address.
Sources: [oauth2-proxy configuration (trusted IP and trusted proxy settings)](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/).
How the stack holds it: `Documented`. The README and the guide How it works make the signature the trust basis; the peer check in D11 only narrows who may try.

### D14. Overwrite forwarded headers; trust them only from known proxies
The gateway sets `X-Forwarded-For`, `X-Forwarded-Proto` and `X-Forwarded-Host` itself and takes the client's address only from configured proxies.
Why: appended headers let a client put its own value first, and wrong host or scheme values change redirect targets and the audience.
Sources: [oauth2-proxy configuration (trusted proxy list, client IP rules)](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/).
How the stack holds it: `Enforced`. `SpoofingTest::testForwardedForIsTheGatewaysViewNotTheClients`, `HeaderPolicyTest::testShouldStrip`, `RenderTest::testTrustedProxiesAreOnlyTheConfiguredOnes`, `RenderTest::testBadTrustedProxyIsRefused`.

### D15. Run the gateway on supported software
The web server and the OpenID Connect module must be maintained and patched.
Why: the gateway is the single point that protects every app, so a flaw there reaches all of them.
Sources: [HAProxy branch status (1.5 listed as unmaintained, end of life 2020-01-10)](https://www.haproxy.org/).
How the stack holds it: `Checked`. The image build fails when Debian serves apache2 below 2.4.69-1~deb13u1 or mod_auth_openidc below 2.4.17-1+deb13u1 (Debian's fix for CVE-2026-54789, raised when the advisory watch in D37 found it), and `DockerfileFloorTest::testTheDockerfileEnforcesAModuleFloor` and `DockerfileFloorTest::testTheFloorCheckFailsTheBuild` keep that check in the Dockerfile.

### D38. Treat a missing forwarded header as a reason to refuse, and drop `Connection` tricks
Never let a missing header turn into a weaker check. Drop `Connection` tokens that name headers the gateway adds.
Why: a client can list a header in `Connection`, and some proxies then remove it as hop-by-hop before the backend sees it (Fabio, CVE-2025-48865; Apache mod_proxy before 2.4.54, CVE-2022-31813).
Sources: [CVE-2025-48865 (Fabio)](https://cveawg.mitre.org/api/cve/CVE-2025-48865), [CVE-2022-31813 (Apache mod_proxy)](https://cveawg.mitre.org/api/cve/CVE-2022-31813).
How the stack holds it: `Enforced`. `Connection` is trimmed to `close`, `keep-alive` and `upgrade`: `HeaderPolicyTest::testFilterConnection`, `SpoofingTest::testListingAGatewayHeaderInConnectionCannotMakeTheProxyDropIt`. A protected request with no assertion is never proxied (D33).

## 3. Per-path protection

### D16. Decide the default for unlisted paths on purpose
Choose whether an unlisted path needs a sign-in, is refused or is public, write the choice down, and make the safe case the default. A new path should never become public by accident.
Why: a list that names only what is protected fails open for every route someone adds later.
Sources: [oauth2-proxy configuration (skip rules are explicit exceptions)](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/).
How the stack holds it: `Enforced`. With no `default`, an unlisted path needs a sign-in; `default: deny` refuses it; `default: public` must be written. An unknown host gets nothing at all. Public paths carry no identity: `ProtectionTest::testProtectionFor`, `GatewayTest::testAProtectedPathSendsAnAnonymousVisitorToSignIn`, `PathRulesTest::testADenyDefaultAppRefusesEveryUnlistedPath`, `GatewayTest::testAnUnknownHostGetsNothing`, `GatewayTest::testAPublicPathNeedsNoLoginAndCarriesNoIdentity`, `GatewayTest::testASignedInVisitorSeesPublicPathsWithoutIdentity`.

### D17. Match paths after normalization
Decode and normalize the path before matching. Give no exemption to paths with `..`, repeated slashes, encoded slashes, backslashes, semicolons, control characters or bad UTF-8.
Why: one route can be written many ways, and an attacker picks the form your rule does not match.
Sources: [oauth2-proxy configuration (skip-auth-route matching)](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/), [oauth2-proxy advisory GHSA-7rh7-c77v-6434](https://github.com/oauth2-proxy/oauth2-proxy/security/advisories/GHSA-7rh7-c77v-6434).
How the stack holds it: `Enforced`. A path that cannot be normalized safely is treated as protected: `PathNormalizerTest::testNormalize`, `PathAttackTest::testAnAnonymousVisitorNeverReachesTheApp`, `ExplainCommandTest::testExplainUnsafePathIsTreatedAsLoginRequired`.

### D46. Match the path only, never the query string, and prefer exact paths to patterns
Run rules on the decoded path. Use exact or prefix matches, not regular expressions or wildcards.
Why: oauth2-proxy's `skip_auth_routes` ran its regex against the whole URI, so a query string could make a protected path match an exemption (CVE-2025-54576, rated Critical).
Sources: [oauth2-proxy advisory GHSA-7rh7-c77v-6434](https://github.com/oauth2-proxy/oauth2-proxy/security/advisories/GHSA-7rh7-c77v-6434).
How the stack holds it: `Enforced`. The config refuses a regex or a wildcard, the Apache pattern is built from the literal, and the most specific rule wins: `PathAttackTest::testAQueryStringDoesNotChangeWhichRuleApplies`, `ConfigLoaderTest::testABrokenConfigIsRefusedWithAClearMessage`, `ProtectionTest::testApachePatternsAreBuiltFromLiterals`, `RenderTest::testRulesBecomeLiteralPatternsLeastSpecificFirst`, `GatewayTest::testTheMostSpecificRuleWins`.

### D18. Treat gateway rules as a gate, not as authorization
Per-path rules say "must be signed in", "must hold one of these groups" or "nobody". Roles inside the app and per-record access stay in the app.
Why: a path list cannot express who may see which record, and apps that rely on it will have holes.
Sources: [NIST SP 800-63C-4](https://pages.nist.gov/800-63-4/sp800-63c.html).
How the stack holds it: `Documented`. The guide Protecting paths says what a group rule is for and what it is not.

### D19. Make unauthenticated API calls fail, not redirect
Requests from scripts, APIs and Vue front ends get a 401 or 403 when not signed in, not a redirect to a sign-in page, and the same for an expired session.
Why: a script cannot follow an interactive sign-in, and an HTML page returned to a JSON client breaks the client.
Sources: [AWS ALB user authentication (the `deny` option returns 401 to calls with no authentication)](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/listener-authenticate-users.html).
How the stack holds it: `Enforced`. An `api` rule says so per path: `GatewayTest::testAnApiPathAnswers401InsteadOfARedirect`, `GatewayTest::testAnApiPathAnswers401ForADeadSession`, `OidcTest::testAnApiContextPathAnswers401ToASessionWithoutTheContext`, `PathRulesTest::testAGroupApiPathAnswers403ToASignedInCallerWithoutTheGroup`, `RevocationTest::testARevokedApiCallerGets401`.

### D54. Shut paths that nobody should reach
Let an app list paths the gateway refuses for everyone, signed in or not, without asking anyone to sign in.
Why: debug consoles, framework dashboards and leftover tools are safer shut at the edge than trusted to an app setting that can be flipped by mistake.
Sources: [OWASP Logging (record access control failures)](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html) and the default-deny reasoning in D16.
How the stack holds it: `Enforced`. A `deny` rule is an Apache `Require all denied` block in rule order, answered 403 with the gateway's page: `RenderTest::testADenyRuleIsADenyBlockInRuleOrder`, `PathRulesTest::testADenyPathIsRefusedWithoutAskingAnyoneToSignIn`, `PathRulesTest::testADenyPathStaysShutForASignedInPerson`, `PathRulesTest::testTheErrorPagesStayReadableUnderADenyDefault`.

### D55. Read group membership only from the verified token
A group rule lets a path through only for a person whose verified ID token lists one of the rule's groups. Nothing the client sends can add a group.
Why: a group read from a header, or from a flattened claim a value can smuggle a comma into, is a group anyone can claim.
Sources: [OWASP Authorization Cheat Sheet (deny by default, validate permissions on every request)](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html).
How the stack holds it: `Enforced`. The policy reads the claim named by `oidc.groups_claim` from the validated ID token payload, as whole JSON strings, and a token without the claim gets nothing: `PathRulesTest::testAGroupPathOpensForAnyOneOfItsGroups`, `PathRulesTest::testAGroupPathRefusesAPersonWithoutTheGroup`, `PathRulesTest::testAGroupPathRefusesATokenWithNoGroupsClaim`, `PathRulesTest::testAGroupPathRefusesALookAlikeGroup`, `PathRulesTest::testAClientCannotClaimAGroupWithAHeader`, `RenderTest::testAGroupRuleHandsItsGroupsToTheStamp`.

### D56. Make the deciding rule easy to see
An operator can ask which rule decides a path, and why, without reading the rendered config.
Why: a rule set nobody can read is a rule set nobody can review, and a wrong guess about which rule wins is how a path ends up public.
Sources: [oauth2-proxy configuration](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/) (exceptions are explicit and listed).
How the stack holds it: `Enforced`. `drawbridge explain <host> <path>` prints the protection, the groups, the context and the rule that matched, or that the app default decided: `ExplainCommandTest::testExplainSaysWhenTheAppDefaultDecides`, `ExplainCommandTest::testExplainGroups`, `ExplainCommandTest::testExplainDeny`.

## 4. The sign-in protocol

### D20. Use a maintained OpenID Connect module; write no protocol code
Run an established relying party module, not code of your own.
Why: protocol bugs (state, nonce, signature and issuer checks) are subtle, and a module used widely is tested by everyone who runs it.
Sources: [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html), [RFC 8725](https://www.rfc-editor.org/rfc/rfc8725.html).
How the stack holds it: `Enforced`. mod_auth_openidc runs the code flow with PKCE and keeps the session; Drawbridge writes no OAuth client and only reads the validated claims: `OidcTest::testAnAnonymousVisitorIsSentToSignInWithTheContextAskedFor`, `OidcTest::testTheUserComesFromTheTokenNotFromAClaimHeader`, `KeycloakTest::testSignInWithAliceLandsBackOnTheEchoAppWithHerClaims`.

### D21. Check every field of what the provider sends
Require a valid signature from the provider's own keys, and check the issuer, the audience, the state and the nonce.
Why: these checks are what stop a forged or redirected sign-in from working.
Sources: [ASVS 5.0 V6.8.2](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x15-V6-Authentication.md), [RFC 8725 section 3.8](https://www.rfc-editor.org/rfc/rfc8725.html).
How the stack holds it: `Enforced`. The module checks them, and the signer also refuses an ID token from any issuer but the configured one, or one without a well-formed user claim: `AssertionSignerTest::testAnIdTokenFromAnotherIssuerIsNotSigned`, `AssertionSignerTest::testAnIdTokenWithoutTheUserClaimIsNotSigned`, `AssertionSignerTest::testAUserClaimWithAControlCharacterIsNotSigned`.

### D22. Accept each sign-in response once
A sign-in response is good for the one sign-in that asked for it.
Why: a copied response should not give a second session.
Sources: [ASVS 5.0 V6.8.3](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x15-V6-Authentication.md), [NIST SP 800-63C-4](https://pages.nist.gov/800-63-4/sp800-63c.html).
How the stack holds it: `Enforced`. mod_auth_openidc ties each callback to the state and nonce it set when that sign-in began, and the authorization code is single use at the provider: `OidcTest::testAUsedCallbackIsNotAcceptedAgain`.

### D23. Start sign-ins at the gateway only
Accept a callback only for a sign-in the gateway started.
Why: a sign-in the app never asked for can be forced onto a visitor.
Sources: [NIST SP 800-63C-4 (injection protection)](https://pages.nist.gov/800-63-4/sp800-63c.html).
How the stack holds it: `Enforced`. A forged or bare callback signs nobody in and sets no session cookie: `PathAttackTest::testAForgedCallbackSignsNobodyIn`, `PathAttackTest::testTheGatewaysOwnPathsAreNotProxiedToTheApp`.

### D24. Allowlist the return address after sign-in and sign-out
The return target is an address on the same app host, never a free-form URL.
Why: the gateway otherwise becomes an open redirect on a trusted hostname.
Sources: [OWASP Unvalidated Redirects and Forwards](https://cheatsheetseries.owasp.org/cheatsheets/Unvalidated_Redirects_and_Forwards_Cheat_Sheet.html), [oauth2-proxy configuration (allowed redirect domains)](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/).
How the stack holds it: `Enforced`. `GatewayTest::testLoginReturnsOnlyToTheSameHost`, `GatewayTest::testLogoutOnlyReturnsToTheSameHost`, `LogoutTest::testTheSignOutReturnsToTheAppHostOnly`.

### D26. Plan secret and key rotation
Know how each secret changes and how old and new overlap: the session passphrase, the signing key and the client secret.
Why: a secret that cannot be changed without signing everyone out never gets changed, and an expired one stops every app at once.
Sources: [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html), [Cloudflare Access JWT validation](https://developers.cloudflare.com/cloudflare-one/identity/authorization-cookie/validating-json/).
How the stack holds it: `Enforced`. A previous session passphrase is accepted beside the new one, and the rig walks the change: `RenderTest::testAPreviousPassphraseIsRenderedSecondOnBothLines`, `RotationTest::testPhase2TwoPassphrasesAreAcceptedButTheReloadStillWipesTheStore`, `RotationTest::testPhase3TheSessionIsRefusedOnceOnlyTheNewPassphraseRemains`. The signing key overlap is D7. The client secret's expiry is set in Entra, and the guide Register in Entra says to note it.

### D27. Keep provider claims inside the gateway
mod_auth_openidc's claims stay in the server's environment, where the signer reads them. No claim, ID token, access token or refresh token is sent to the app as a header.
Why: a client cannot set a server variable, but can set a header, and a provider token in an app's hands can be replayed elsewhere.
Sources: [Shibboleth SP SpoofChecking](https://shibboleth.atlassian.net/wiki/spaces/SP3/pages/2065335299), [RFC 8725](https://www.rfc-editor.org/rfc/rfc8725.html).
How the stack holds it: `Enforced`. `GatewayTest::testNoOidcHeaderOrTokenEverReachesTheApp`, `GatewayTest::testTheGatewaySessionCookieNeverReachesTheApp`, `RenderTest::testThePolicyScrubsTheSessionAndStateCookies`.

## 5. Sessions at the gateway

### D28. Harden the gateway session cookie
Set `Secure`, `HttpOnly` and `SameSite`, no `Domain`, and use the `__Host-` prefix.
Why: the gateway cookie guards every path of an app, and a sibling subdomain must not be able to overwrite it.
Sources: [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html), [Cookie prefixes in the cookie spec draft](https://datatracker.ietf.org/doc/draft-ietf-httpbis-rfc6265bis/).
How the stack holds it: `Enforced`. `RenderTest::testSecureCookiesGetTheHostPrefix`, `RenderTest::testSecureCookiesAreTheDefault`, `RenderTest::testTheSessionCookieIsLaxAndHttpOnlyWithSecureCookies`, `GatewayTest::testTheSessionCookieIsHttpOnlyAndLaxAtCallback`.

### D61. One session per app host
Each app host keeps sessions of its own, so a session cookie carried to another host opens nothing there.
Why: one app's session must not be valid for another, and a host-only cookie cannot be shared across `*.apps.wayne.edu` anyway.
Sources: [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).
How the stack holds it: `Enforced`. Each host encrypts its sessions with a passphrase of its own: `RenderTest::testEveryAppHostHasItsOwnCryptoPassphrase`, `GatewayTest::testEachAppHasItsOwnSession`.

### D29. Set idle and absolute lifetimes explicitly
Do not accept product defaults.
Why: a session with no idle limit stays open on a shared computer.
Sources: [ASVS 5.0 V7.3.1 and V7.3.2](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x16-V7-Session-Management.md), [NIST SP 800-63B-4 (AAL2 limits)](https://pages.nist.gov/800-63-4/sp800-63b.html).
How the stack holds it: `Enforced`. 8 hours total and 1 hour idle unless the config says otherwise: `RenderTest::testHardenedSessionLifetimesDefaults`, `RenderTest::testHardenedSessionLifetimesConfigured`.

### D30. Document how the gateway, app and provider sessions relate
Write down each session's lifetime, who ends it, and what forces a new sign-in.
Why: with three layers, an app session can outlive the sign-in that created it, and nobody knows.
Sources: [ASVS 5.0 V7.1.3 and V7.6.1](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x16-V7-Session-Management.md), [NIST SP 800-63C-4](https://pages.nist.gov/800-63-4/sp800-63c.html).
How the stack holds it: `Documented`. The README table "Three sessions, three clocks" and the guide Sessions.

### D31. Define sign-out across layers
App sign-out ends the app session, then the gateway session, then asks the provider to end its own.
Why: signing out of one layer only leaves the person signed in through the others.
Sources: [ASVS 5.0 V7.4.1](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x16-V7-Session-Management.md), [OpenID Connect at Microsoft identity platform (sign-out)](https://learn.microsoft.com/azure/active-directory/develop/v2-protocols-oidc).
How the stack holds it: `Enforced`. The app calls `sesame_logout()` and links to `/_drawbridge/oidc/callback?logout=<address>`; the gateway ends its session and sends the browser to the provider's end-session endpoint: `GatewayTest::testLogoutEndsTheGatewaySession`, `KeycloakTest::testLogoutEndsTheGatewaySession`, `LogoutTest::testEverySignOutHopIsATopLevelRedirectAndNoneEmbedsTheProvider`. Other app hosts' sessions end on their own clocks, or at once with D32. The guide Signing out says why there is no front-channel logout.

### D32. End sessions when an account is disabled
There is a way to end every gateway session of one person, on every app host, at once.
Why: a 60-second assertion limits new requests, but the gateway session behind it can live for hours.
Sources: [ASVS 5.0 V7.4.2 and V7.4.5](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x16-V7-Session-Management.md).
How the stack holds it: `Enforced`. `drawbridge revoke <subject>` writes the time to the revocation file; any session that began at or before it is refused on every host, a browser is signed out and an api caller gets 401: `RevocationTest::testARevokedSessionEndsOnEveryAppHost`, `RevocationTest::testARevokedSessionIsNotSignedEvenOnAGroupOrContextPath`, `RevocationTest::testRevokingOnePersonLeavesEveryoneElseAlone`, `RevocationTest::testASessionIssuedAtOrBeforeTheRevocationIsRevoked`. With more than one gateway instance, each must read the same file.

### D50. Make sign-out work with third-party cookies blocked
Sign out with top-level redirects only, and test it with a browser that holds only first-party cookies.
Why: a sign-out that loads another site in a hidden frame gets no cookies there when the browser blocks third-party cookies, so it silently does nothing.
Sources: [REFEDS, Why are third party cookies relevant to single logout](https://wiki.refeds.org/display/GROUPS/Why+are+third+party+cookies+relevant+to+single+logout).
How the stack holds it: `Enforced`. The rig walks every hop with a browser that holds only the app host's cookies: no frame, no provider image, no navigating script, the session dead even with no provider cookie at all: `LogoutTest::testTheGatewaySessionIsDeadWithNoProviderCookieAtAll`, `LogoutTest::testTheAppsLogoutLinkCanBeAPlainLink`, `KeycloakTest::testLogoutWithThirdPartyCookiesBlocked`. A real browser engine with its cookie rules on runs the same round trip in `tools/a11y/signout.mjs` against the rig's keycloak profile. Offline, `composer ci` holds every piece of the flow that lives in the repository: each app host answers `?logout=` itself, uncached and unframeable; the session cookie is `SameSite=Lax`; nothing widens the return or turns on front-channel logout; the strip hook leaves the endpoint its cookies; a refused sign-out page embeds nothing; and the browser check drives the same link (`SignOutFlowTest::testEveryAppHostAnswersTheSignOutItself`, `SignOutFlowTest::testTheSessionCookieRidesTheTopLevelReturnAndNothingElse`, `SignOutFlowTest::testNothingWidensWhereASignOutMayReturnOrFramesIt`, `SignOutFlowTest::testTheBrowserCheckDrivesTheSameLinkTheDocsName`).

### D57. Keep sessions across a reload when the store allows it
Offer a session store that outlives one process, and say which store loses sessions on a reload.
Why: an operator who does not know a reload signs everyone out either stops reloading or surprises people.
Sources: [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).
How the stack holds it: `Enforced`. `gateway.session_store: redis` keeps sessions, still encrypted, across a reload; the default shared memory store starts empty, as the README says: `RenderTest::testRedisStoreRendersTheServerAndKeepsEncryption`, `RedisTest::testSessionSurvivesAGatewayReload`, `ConfigLoaderTest::testRedisPasswordCannotBeInTheConfigFile`. Over that store a passphrase change with the previous value kept signs nobody out, and a session sealed under a dropped value is sent to sign in, not shown an error: `RedisRotationTest::testPhase2TheOldSessionSurvivesAndANewSignInWorksUnderTheNewPassphrase`, `RedisRotationTest::testPhase3TheOldSessionIsRefusedCleanlyOnceThePreviousPassphraseIsGone`, `RedisRotationTest::testRestartTheSessionSurvivesAGatewayRecreatedFromItsEnvironment`.

## 6. Operations

### D33. Fail closed
When the provider, the signer, a secret or the config is wrong or missing, protected paths return an error. They never pass through unsigned.
Why: an outage must not become an open door.
Sources: [Cloudflare Access JWT validation](https://developers.cloudflare.com/cloudflare-one/identity/authorization-cookie/validating-json/), [Google IAP signed headers](https://docs.cloud.google.com/iap/docs/signed-headers-howto).
How the stack holds it: `Enforced`. A signer that cannot sign gives 503; a bad config or a missing secret stops the container: `RenderTest::testProtectedSectionsRequireASessionAndSignFailClosed`, `RenderTest::testRefusedSessionsAreAnsweredBeforeAnythingIsSigned`, `RuntimeSecretsTest::testAMissingClientSecretStopsTheGateway`, `ConfigLoaderTest::testEveryProblemIsReportedTogether`.

### D34. Log authentication events, but never secrets
Record sign-ins, failed sign-ins, sign-outs, refusals and revoked sessions. Never log assertions, tokens, cookies or keys.
Why: you need a trail to investigate an incident, and a logged secret is a leaked secret.
Sources: [OWASP Logging](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html).
How the stack holds it: `Enforced`. One JSON line per event in the event log, the path without its query string, the subject and nothing else of the token; the signer logs reason codes only: `EventLogFormatTest::testTheFormatCarriesNoQueryCookieHeaderOrToken`, `EventLogTest::testASignInIsLoggedWithTheUserAndNoSecrets`, `EventLogTest::testRefusalsAreLoggedWithTheirReason`, `EventLogTest::testOrdinaryRequestsLogNoEvent`, `SignerMapTest::testTheLogCarriesAReasonCodeAndNoSubjectOrToken`. The reference page Log events lists every field.

### D35. Test the header, path and assertion attacks
Test a client-sent identity header, its look-alikes, duplicates, a `Connection` header that names a gateway header, a query string that matches another rule, an expired, tampered, replayed or wrongly signed assertion, a wrong audience and a wrong issuer.
Why: these are the failures that turn a gateway into an open door, and they stay fixed only if a test fails when they return.
Sources: [Traefik: header underscores](https://doc.traefik.io/traefik/v3.6/security/header-underscores/), [RFC 8725](https://www.rfc-editor.org/rfc/rfc8725.html).
How the stack holds it: `Enforced`. `SpoofingTest`, `PathAttackTest`, and SESAME's verifier against this gateway's tokens in `RealSesameTest::testAnExpiredTokenFails`, `RealSesameTest::testATamperedTokenFails`, `RealSesameTest::testAReplayedTokenFails`, `RealSesameTest::testAWrongKeyFails`.

### D36. Flag apps that read raw identity headers
A PROCTOR rule flags app code that reads an identity header or a server variable directly instead of going through SESAME.
Why: one app that trusts a bare header reopens the whole hole for that app.
Sources: [oauth2-proxy configuration](https://oauth2-proxy.github.io/oauth2-proxy/configuration/overview/) (an upstream must trust only what the proxy sends).
How the stack holds it: `Documented`. PROCTOR holds it, not the gateway: its `raw-identity-header` rule warns when app code reads `X-Sesame-Assertion`, `REMOTE_USER`, `X-Authenticated-*`, `X-Drawbridge-*`, `OIDC_CLAIM_*` or a like header itself instead of taking the user from SESAME (`src/Check/RawIdentityHeaderCheck.php` in PROCTOR).

### D37. Track advisories for the gateway's software
Watch security notices for Apache httpd and mod_auth_openidc, and raise the floor in D15 when one lands.
Why: a gateway flaw is a flaw in every protected app.
Sources: [Apache HTTP Server security reports](https://httpd.apache.org/security/vulnerabilities_24.html), [mod_auth_openidc security advisories](https://github.com/OpenIDC/mod_auth_openidc/security/advisories), [OSV, Debian advisories](https://osv.dev/list?ecosystem=Debian) and [Debian security tracker](https://security-tracker.debian.org/tracker/) (the feed the watch reads).
How the stack holds it: `Checked`. `composer advisories` reads the floors and the Debian release from the Dockerfile, asks OSV.dev (which republishes Debian's security tracker, so Apache httpd and mod_auth_openidc advisories arrive as the Debian packages the image installs) which advisories still affect each floor, and fails when Debian has a fixed build above one; the GitLab `advisories` job runs it from a pipeline schedule or by hand, never on a merge request (`FloorWatchTest::testAFixedAdvisoryFailsTheWatchAndNamesTheFix`, `FloorWatchTest::testThePipelineRunsTheWatchOnASchedule`). Its first run raised the mod_auth_openidc floor for CVE-2026-54789. Gap: the job runs on a schedule only once a project owner creates one in GitLab; until then it runs by hand. Raising the floor is still a reviewed change, not automatic.

### D39. Let no header, flag or debug switch turn verification off
Nothing a client can send makes the gateway skip the guard, the session check or the signer, and no config mode passes identity unsigned.
Why: Next.js trusted an internal header, `x-middleware-subrequest`, to skip its middleware, so a client that sent it skipped authorization (CVE-2025-29927).
Sources: [Vercel postmortem on the Next.js middleware bypass](https://vercel.com/blog/postmortem-on-next-js-middleware-bypass).
How the stack holds it: `Enforced`. The unsigned `headers` mode is refused at load time, and a client cannot claim a context or a group: `ConfigLoaderTest::testHeadersModeIsRefused`, `ConfigLoaderTest::testBothModeIsRefused`, `OidcTest::testAClientCannotClaimTheContextItself`, `PathRulesTest::testAClientCannotClaimAGroupWithAHeader`.

### D40. Send no unsigned identity header beside the signed one, and pin who signed it
The gateway sends the signed assertion and nothing else that names the user. The verifier checks the issuer as well as the signature.
Why: AWS's load balancer signs one header but still sends two unsigned identity headers, and tells apps to check the signer before using any claim.
Sources: [AWS ALB user authentication](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/listener-authenticate-users.html), [Google IAP signed headers (check `iss`, then use the signed claims)](https://docs.cloud.google.com/iap/docs/signed-headers-howto).
How the stack holds it: `Enforced`. `SpoofingTest::testAssertionAppsNeverSeeAClientsIdentityHeaders`, `GatewayTest::testNoOidcHeaderOrTokenEverReachesTheApp`, `RealSesameTest::testAWrongIssuerFails`.

### D41. Put an issuer and a unique ID in the assertion
Add `iss` (the gateway, never the provider) and a unique `jti`. The verifier checks `iss` and remembers IDs until they expire.
Why: a replay check needs an ID to remember, and an issuer check needs an issuer to pin.
Sources: [RFC 8725 section 3.8](https://www.rfc-editor.org/rfc/rfc8725.html), [Google IAP signed headers](https://docs.cloud.google.com/iap/docs/signed-headers-howto).
How the stack holds it: `Enforced`. `AssertionSignerTest::testEveryTokenHasItsOwnJti`, `GatewayTest::testEveryRequestGetsAFreshAssertionWithItsOwnJti`, `RealSesameTest::testAReplayedTokenFails`.

### D42. Keep clocks in sync and allow only a small, fixed skew
Sync time on the gateway and app hosts, and allow a skew of about 30 seconds, not minutes.
Why: 60-second assertions fail on drifting clocks, and a wide tolerance undoes the short life.
Sources: [Google IAP signed headers (30 seconds of skew on `exp` and `iat`)](https://docs.cloud.google.com/iap/docs/signed-headers-howto).
How the stack holds it: `Not yet`. The signer drops an `auth_time` in the future (`AssertionSignerTest::testAnAuthTimeInTheFutureIsDropped`), but time sync on the hosts is the platform's, and it is not confirmed.

### D47. Patch the web server, and keep rewrite rules out of access decisions
Use Apache 2.4.60 or later, and do not let `mod_rewrite` be the only thing that decides who gets in.
Why: the 2024 "Confusion Attacks" research found ambiguity in how Apache applies rewrite substitutions (CVE-2024-38475, fixed in 2.4.60).
Sources: [Akamai write-up of the Confusion Attacks research](https://www.akamai.com/blog/security-research/2024/aug/apache-waf-proactive-collaboration-orange-tsai-devcore).
How the stack holds it: `Enforced`. The image floor is 2.4.69 (D15). Sign-in is decided by mod_auth_openidc and the Lua authorization provider, and a `deny` rule is `Require all denied`. Rewrite rules only answer a refusal the provider already marked, and a marked request gets nothing to sign, so a lost rewrite still ends in 503: `RenderTest::testRefusedSessionsAreAnsweredBeforeAnythingIsSigned`, `DockerfileFloorTest::testTheDockerfileEnforcesAModuleFloor`.

### D48. Keep shared caches from storing signed-in responses
Send `Cache-Control: no-store` or `private` on responses that depend on the user.
Why: a cache in front of an authenticating layer can serve a signed-in response after the session has ended.
Sources: [AWS ALB user authentication](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/listener-authenticate-users.html), [OWASP HTTP Headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html).
How the stack holds it: `Enforced`. Protected and api paths get `no-store` unless the app sends its own `Cache-Control`; public paths get none: `RenderTest::testProtectedAndApiPathsAddANoStoreDefault`, `RenderTest::testPublicPathsAddNoCacheHeaderAndSetTheFlag`, `GatewayTest::testProtectedAndApiAnswersCarryNoStoreByDefault`.

### D49. Set security headers on the gateway's answers
Send HSTS on HTTPS, `frame-ancestors` and `nosniff`, and an explicit `Referrer-Policy`, without overwriting what an app sends.
Why: a sign-in gateway is a framing and downgrade target, and its redirects carry return addresses. An app that sets its own headers (through HELMET) must keep them.
Sources: [OWASP HTTP Headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html).
How the stack holds it: `Enforced`. Proxied answers get the gateway's headers with `setifempty`; pages the gateway answers itself use plain `set`: `RenderTest::testHstsIsAddedOnlyWhenThePublicSchemeIsHttps`, `RenderTest::testHardenedDefaultHostReferrerAndFraming`, `RenderTest::testEveryAppHostAddsTheTwoSetIfEmptyHeaders`, `RenderTest::testNoAppHostOverridesAHeaderAnAppCouldSend`, `RenderTest::testGatewayAnsweredLocationsCarryTheirOwnHeaders`. HSTS without `includeSubDomains`: adding it for `apps.wayne.edu` would reach every app, which is the platform's call.

### D51. Make the error pages accessible
The gateway's own pages meet WCAG 2.1 AA, and the stack aims for 2.2 AA.
Why: a person who is refused or signed out must be able to read why, and the Department of Justice's Title II rule requires WCAG 2.1 AA.
Sources: [W3C, Understanding 3.3.8 Accessible Authentication (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/accessible-authentication-minimum.html), [ADA.gov, Title II web rule first steps](https://www.ada.gov/resources/web-rule-first-steps/).
How the stack holds it: `Enforced`. The gateway shows no sign-in form (Entra does). Its error pages are plain, self-contained HTML, pass text contrast in light and dark, allow zoom and keep a visible focus style: `ErrorPagesTest::testErrorPagesMeetTextContrastInBothSchemes`, `ErrorPagesTest::testErrorPagesDoNotBlockZoom`, `ErrorPagesTest::testErrorPagesKeepAVisibleFocusStyle`. These are checks of the markup, not a full audit.

### D58. Keep secrets out of git, out of the rendered config and out of logs
Keys are mounted files. The client secret and the session passphrase are environment variables or files the rendered config names but never holds, and the gateway refuses to start without them.
Why: a secret in git or in a rendered file is copied everywhere that file goes.
Sources: [OWASP Secrets Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html).
How the stack holds it: `Enforced`. `RenderTest::testTheProviderBlockNamesTheSecretsButNeverHoldsThem`, `RuntimeSecretsTest::testTheMessagesNeverIncludeTheValue`, `RuntimeSecretsTest::testASecretCanComeFromAFile`, `SignerMapTest::testAFailureLogsOnlyTheExceptionClass`. Test keys are made when the tests run, and the rig's secrets are throwaway values.

### D59. Keep metrics closed unless switched on
The metrics endpoint answers only a bearer token or an allowed address, and carries no subject, path or other high-cardinality label.
Why: an open metrics page tells an attacker how the gateway is doing, and a label per user is a log in disguise.
Sources: [Prometheus instrumentation practices (labels)](https://prometheus.io/docs/practices/instrumentation/).
How the stack holds it: `Enforced`. `MetricsGateTest::testDisabledIs404`, `MetricsGateTest::testWrongTokenIsDenied`, `MetricsTest::testAssertionErrorCounterCarriesLowCardinalityReason`, `MetricsTest::testUnknownReasonIsFoldedToOther`, `MetricsTest::testEveryAppHostRefusesMetricsWithoutTheToken`.

### D60. Run small, portable and bounded
Run as a non-root user on a port above 1024, keep state under `/tmp`, log to stdout and stderr, and keep no host paths in the image, so it runs the same on Citadel, OpenShift, Coolify or plain Docker. Bound every dependency and audit them.
Why: a gateway that needs root or a host path is a gateway one platform can run and the next cannot, and an unbounded dependency is an untested one.
Sources: [CIS Docker Benchmark](https://www.cisecurity.org/benchmark/docker), [Composer audit](https://getcomposer.org/doc/03-cli.md#audit).
How the stack holds it: `Checked`. The image runs as `USER 1001:0` on port 8080; `composer ci` runs `composer audit`; the CI image jobs run unprivileged and never mount the Docker socket (`GitlabCiTest::testNoJobRunsPrivileged`, `GitlabCiTest::testNoJobMountsTheDockerSocket`). `DockerfileFloorTest::testTheImageRunsNonRootOnAPortAbove1024` fails when the image's last `USER` is not `1001:0` or it stops exposing 8080.

## Retired with SAML

The research behind D1 to D53 was written when Drawbridge was planned as a SAML service provider. It is an OpenID
Connect client of Entra ID instead, so these items have nothing to hold. They come back if SAML ever does.

- D25. Register apps separately with the provider. Drawbridge has one Entra app registration with one redirect URI per app host (the guide Register in Entra), and D5 binds each assertion to one app.
- D43. Parse SAML once, validate the schema, and read the identity from the signed element.
- D44. Limit SAML message size and XML depth before parsing.
- D45. Enable only the SAML bindings you need.
- D52. Choose SP software with a supported future. For the OpenID Connect module, D15 and D37 cover the same ground.
- D53. Meet a federation's baseline for service providers. Entra is not a federation.
