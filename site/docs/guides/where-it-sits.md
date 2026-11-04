---
title: "Where it sits in WELD | ADVISOR docs"
description: "Where ADVISOR sits among the stack's parts and in WELD."
url: https://advisor.apps.wayne.edu/docs/guides/where-it-sits.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Where it sits in WELD[#](https://advisor.apps.wayne.edu/docs/guides/where-it-sits.html#where-it-sits-in-weld)

This page shows where ADVISOR sits among the parts of the Wayne State application stack and in WELD.

## The stack and WELD[#](https://advisor.apps.wayne.edu/docs/guides/where-it-sits.html#the-stack-and-weld)

The stack is the Wayne State application stack. WELD, Wired, Everything, Linked, Deployed, is its Laravel starter app. You can add ADVISOR to a WELD app or to any other Wayne State PHP app, and in either case it is the part of the stack that talks to your coding agent.

## The parts[#](https://advisor.apps.wayne.edu/docs/guides/where-it-sits.html#the-parts)

| Part | What it is |
| --- | --- |
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

## What each part is[#](https://advisor.apps.wayne.edu/docs/guides/where-it-sits.html#what-each-part-is)

ADVISOR serves a bundled copy of what these projects publish, so it works offline. Each part:

- **WayneUI** is the interface, project 0 of the stack. ADVISOR bundles its markup contract and its 1.x to 2.x migration guide.
- **CREED** is the version policy and the written standards. ADVISOR bundles its policy file and handbook.
- **PROCTOR** checks an app.
- **SESAME** handles sign-in inside the app.
- **HELMET** is the runtime baseline.
- **Sourdough** is the starter.
- **Preflight** provides the CI components (php-test and composer-audit).
- **Pinstripe** is the coding standard; PHPStan includes its raw preset.
- **Booster** does the upgrades.
- **Rollcall** is the fleet census.
- **Drawbridge** is the sign-in gateway.
- **Squire** is the deploy kit; the front page's deploy layer is generated with it.
- **ADVISOR** is the MCP server, the part of the stack that talks to your coding agent.
- **WELD** is the Laravel starter app: Wired, Everything, Linked, Deployed.

Note

ADVISOR does not replace Laravel Boost. Boost knows Laravel; ADVISOR knows WSU. Run both.

## Related[#](https://advisor.apps.wayne.edu/docs/guides/where-it-sits.html#related)

- [Served content](https://advisor.apps.wayne.edu/docs/guides/served-content.html)
- [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html)
- [Quick start](https://advisor.apps.wayne.edu/docs/guides/quick-start.html)
- [Install](https://advisor.apps.wayne.edu/docs/install.html)
