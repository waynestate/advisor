# Overrides

- Overrides are named, dated and visible (ADR-0003).
- An app keeps a `policy-overrides.json` with an expiry date and a reason
  (ADR-0003).
- Check prints every override on every run, and Fleet lists them (ADR-0003).
- An expired override is a failure (first-repos).
- On the Citadel side, overrides are held by Citadel admins, per app and per
  rule, with an expiry, a reason and an approver. There is no permanent
  override (ADR-0006).
