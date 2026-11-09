---
title: Where it sits in WELD
description: Where ADVISOR sits among the stack's parts and in WELD.
---

# Where it sits in WELD

This page shows where ADVISOR sits among the parts of the Wayne State application stack and in WELD.

## The stack and WELD

The stack is the Wayne State application stack. WELD, Wired, Everything, Linked, Deployed, is its Laravel starter app. You can add ADVISOR to a WELD app or to any other Wayne State PHP app, and in either case it is the part of the stack that talks to your coding agent.

## The parts

| Part | What it is |
|---|---|
| **WayneUI** | interface (project 0) |
| **CREED** | version policy and written standards |
| **PROCTOR** | checks an app |
| **SESAME** | sign-in inside the app |
| **HELMET** | runtime baseline |
| **Sourdough** | starter |
| **Preflight** | CI components |
| **Pinstripe** | coding standard |
| **Booster** | upgrades |
| **Rollcall** | fleet census |
| **Drawbridge** | sign-in gateway |
| **Squire** | deploy kit |
| **ADVISOR** | MCP server |
| **WELD** | the Laravel starter app |

## What each part is

ADVISOR serves a bundled copy of what these projects publish, so it works offline. Each part:

- **WayneUI** is the interface, project 0 of the stack. ADVISOR bundles its markup contract and its 1.x to 2.x migration guide.
- **CREED** is the version policy and the written standards. ADVISOR bundles its policy file and handbook, and reads the policy through CREED's own reader (`waynestate/creed`).
- **PROCTOR** checks an app.
- **SESAME** handles sign-in inside the app.
- **HELMET** is the runtime baseline.
- **Sourdough** is the starter.
- **Preflight** provides the CI components (php-test, composer-audit, proctor and a11y).
- **Pinstripe** is the coding standard; PHPStan includes its raw preset.
- **Booster** does the upgrades.
- **Rollcall** is the fleet census.
- **Drawbridge** is the sign-in gateway.
- **Squire** is the deploy kit; the front page's deploy layer is generated with it.
- **ADVISOR** is the MCP server, the part of the stack that talks to your coding agent.
- **WELD** is the Laravel starter app: Wired, Everything, Linked, Deployed.

> [!NOTE]
> ADVISOR does not replace Laravel Boost. Boost knows Laravel; ADVISOR knows WSU. Run both.

## Related

- [Served content](served-content.md)
- [What it offers](what-it-offers.md)
- [Quick start](quick-start.md)
- [Install](../install.md)
