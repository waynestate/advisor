# Accessibility

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## Target

- Every public page of a stack app targets WCAG 2.2 Level AA (the stack's accessibility design).
- W3C says content conforming to 2.2 also conforms to 2.1 and 2.0, so the target meets the WCAG 2.1 AA floor named in
  the ADA Title II web rule (the stack's accessibility design).

## Components

- WayneUI is the stack's component library; pages built from its components start from markup and tokens already
  checked by WayneUI's own runnable accessibility gate (the stack's accessibility design).
- An app MUST NOT fork or restyle a WayneUI component in a way that removes focus indicators, labels or contrast
  (the stack's accessibility design). PROCTOR check: `wayneui-forked-views` catches forked views.

## Checks

- An automated accessibility check SHOULD run in CI on the app's key pages (the stack's accessibility design).
  Held by: Preflight's `a11y` component, below.
- An automated tool catches regressions; it is not proof of conformance, and it never replaces a manual keyboard and
  screen reader pass before launch (the stack's accessibility design).
- Preflight's `a11y` component (0.4.0) is the stack's CI check: it opens the app's pages in Chromium, runs axe-core
  in light and dark at 320 and 1280 px, and fails the pipeline on any violation. Add `wcag22aa` to its tags for the
  WCAG 2.2 AA target above.

## Not decided here

- Whether and when the ADA Title II web rule applies to a given WSU unit. The rule applies WCAG 2.1 AA to public
  entities on dates that depend on the entity's size; the answer is for WSU's counsel and accessibility office, and
  this chapter does not decide it.
- Which pages need a manual audit.
