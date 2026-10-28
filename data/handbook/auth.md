# Auth

- One protocol-neutral library, named for what it does, not how. It returns a
  verified identity and leaves the session to the app (ADR-0004).
- The app always owns its session. Every driver ends in the app's
  `findOrImportUser(identity)`, and the protocol never holds the session
  (ADR-0004).
- The `gateway` driver accepts only a signed assertion, never a bare header:
  user, attributes, issued-at, audience, an expiry of 60 seconds or less, and
  a signature the gateway and app share (ADR-0004).
- Drivers: `cas` (the default today), `gateway`, `saml`, `oauth2`, and `fake`
  for local development and tests (ADR-0004).
- Non-EOL PHP only, with no exceptions (ADR-0004).
- To be decided: the gateway's position and key distribution, and the SAML
  library choice (ADR-0004).
