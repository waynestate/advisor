# Overrides

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- Overrides are named, dated and visible (the stack's version policy).
- An app keeps a `policy-overrides.json` with an expiry date and a reason
  (the stack's version policy).
- Check prints every override on every run, and Fleet lists them (the stack's version policy).
- An expired override is a failure (first-repos).
- On the Citadel side, overrides are held by Citadel admins, per app and per
  rule, with an expiry, a reason and an approver. There is no permanent
  override (the stack's deploy-gate decision).
