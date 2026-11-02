# Auth

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- One protocol-neutral library, named for what it does, not how. It returns a
  verified identity and leaves the session to the app (the stack's auth design).
- The app always owns its session. Every driver ends in the app's
  `findOrImportUser(identity)`, and the protocol never holds the session
  (the stack's auth design).
- The `gateway` driver accepts only a signed assertion, never a bare header:
  user, attributes, issued-at, audience, an expiry of 60 seconds or less, and
  a signature the gateway and app share (the stack's auth design).
- Drivers: `cas` (the default today), `gateway`, `oidc`, `oauth2`, `saml` (not
  built yet), and `fake` for local development and tests (the stack's auth design).
- Non-EOL PHP only, with no exceptions (the stack's auth design).
  PROCTOR check: `php-floor`.
- To be decided: the gateway's key distribution and rotation, and the SAML
  library choice if a `saml` driver is built (the stack's auth design).
- API and other stateless requests are authenticated with signed, short-lived bearer tokens in an `Authorization` header, verified on every request. A token is signed, not merely encrypted; JSON Web Tokens are a good fit (WSU developer standards, 2024).
- Authentication is checked before any content is shown or any script function runs, on every request. Deny by default: routes are protected unless listed as public, and an unauthenticated person is sent to sign in (WSU developer standards, 2024).
- When authentication or an authorization lookup fails, the result is fail closed: access is withheld (WSU developer standards, 2024).
- Administrative access MUST be limited to named users or roles, and access that is due to end is revoked on a regular schedule (WSU developer standards, 2024).
- Access that is due to end when a person's status changes (for example an ended employment) MUST be removed by an automated process, not by memory (WSU developer standards, 2024).
- Idle sessions MUST time out after a configurable period of inactivity. The stack sets no number yet; Laravel's default session lifetime applies until it does (WSU developer standards, 2024).
