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
- To be decided: the gateway's key distribution and rotation, and the SAML
  library choice if a `saml` driver is built (the stack's auth design).
