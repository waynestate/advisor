# Overrides

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- Overrides MUST be named, dated and visible (the stack's version policy).
  PROCTOR check: `overrides` (its file parse requires a name, a date and a
  reason).
- An app MUST keep a `policy-overrides.json` with an expiry date and a reason
  (the stack's version policy). PROCTOR check: `overrides`.
- Check prints every override on every run, and Fleet lists them (the stack's version policy).
- An expired override MUST fail the run (first-repos). PROCTOR check: `overrides`.
- An override MUST run at most `rules.override_max_days` days past today (90
  in the policy); a longer one is a failure and does not apply.
  PROCTOR check: `overrides`.
- An override MUST be approved by someone who did not write it. The merge
  request that adds or changes `policy-overrides.json` is the human
  control: the person named in `approved_by` approves it. In CI, PROCTOR
  compares `approved_by` with who git says wrote the entry and warns (or
  fails, per `rules.override_self_approval`, `warn` in the policy) on a
  match, or when it cannot read who did. PROCTOR check: `override-approver`.
- On the Citadel side, overrides are held by Citadel admins, per app and per
  rule, and MUST carry an expiry, a reason and an approver. There MUST NOT be
  a permanent override (the stack's deploy-gate decision). No check exists yet.
