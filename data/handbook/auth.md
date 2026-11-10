# Auth

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- One protocol-neutral library, named for what it does, not how. It returns a
  verified identity and leaves the session to the app (the stack's auth design).
- The app MUST own its session. Every driver ends in the app's
  `findOrImportUser(identity)`, and the protocol never holds the session
  (the stack's auth design). Held by: SESAME, whose drivers end in the app's
  `findOrImportUser` and hold no session of their own.
- The `gateway` driver MUST accept only a signed assertion, never a bare header:
  user, attributes, issued-at, audience, an expiry, and a signature the
  gateway and app share (the stack's auth design). Drawbridge issues a
  60 second assertion by default (`assertion_ttl_seconds`, 5 to 300), and
  SESAME refuses any assertion that lives longer than `gateway.max_lifetime`
  (300 seconds unless the app sets it). Held by: SESAME, which refuses a bare
  header and an assertion past `gateway.max_lifetime`.
- Drivers: `cas` (the default today), `gateway`, `oidc`, `oauth2`, `saml`
  (opt-in), and `fake` for local development and tests (the stack's auth design).
- Apps MUST use non-EOL PHP only, with no exceptions (the stack's auth design).
  PROCTOR check: `php-floor`.
- The gateway's public keys reach apps as one key set file (JWKS) that holds
  the current key and the next one. Drawbridge writes it (`keys:jwks`),
  Playbook carries WSU's copy (public keys only), and the app points SESAME's
  `gateway.jwks_file` (`SESAME_GATEWAY_JWKS_FILE`) at it. An app SHOULD read
  that file rather than copy keys into config values, and the `gateway` driver
  never fetches keys over the network at sign-in (the stack's signing-key
  decision).
  Held by: SESAME config, which checks the file at boot: a missing file,
  private key material or a repeated `kid` stops the app.
- The gateway's signing key MUST be rotated every 12 months, and at once when
  a private key could have been exposed. A retired key MUST stay in the key set
  for at least 5 minutes after the switch, so assertions signed just before it
  still verify (the stack's signing-key decision). No check exists yet. Still
  open: where the private keys live on Citadel and who runs the yearly
  rotation.
- The `saml` driver is built on `onelogin/php-saml`, is opt-in, sets no
  default WSU identity provider, and requires the provider to sign its
  assertions (the stack's SAML decision). SESAME config: the `saml` block.
- API and other stateless requests MUST be authenticated with signed, short-lived bearer tokens in an `Authorization` header, verified on every request. A token is signed, not merely encrypted; JSON Web Tokens are a good fit (WSU developer standards, 2024). No check exists yet.
- Authentication MUST be checked before any content is shown or any script function runs, on every request. Deny by default: routes are protected unless listed as public, and an unauthenticated person is sent to sign in (WSU developer standards, 2024). No check exists yet.
- When authentication or an authorization lookup fails, the app MUST fail closed: access is withheld (WSU developer standards, 2024). No check exists yet.
- Administrative access MUST be limited to named users or roles, and access that is due to end is revoked on a regular schedule (WSU developer standards, 2024). No check exists yet.
- Access that is due to end when a person's status changes (for example an ended employment) MUST be removed by an automated process, not by memory (WSU developer standards, 2024). No check exists yet.
- Idle sessions MUST time out after a configurable period of inactivity (WSU developer standards, 2024). The stack's numbers are in `policy/policy.json`, under `sessions`, which is the source for them: SESAME signs a user out after 120 minutes idle and 720 minutes in all (`sessions.sesame`), and Drawbridge after 3600 seconds idle and 28800 seconds in all (`sessions.gateway`). Held by: SESAME config, `session.idle_minutes` (`SESAME_SESSION_IDLE_MINUTES`) and `session.absolute_minutes` (`SESAME_SESSION_ABSOLUTE_MINUTES`), with those defaults. An app that does not use SESAME sets its own idle limit; that is documented only.
