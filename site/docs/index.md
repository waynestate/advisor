---
title: "Overview | ADVISOR docs"
description: "ADVISOR in one page: what it is, who it is for, and where to go next."
url: https://advisor.apps.wayne.edu/docs/
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Overview[#](https://advisor.apps.wayne.edu/docs/#overview)

**What applies, and what's next.**

This page tells you what ADVISOR is, how to run it, and where to go next.

ADVISOR is an MCP server for the Wayne State application stack, a bit like Laravel Boost but for WSU. It orients a developer who is new to the stack and gives their coding agent nine read-only tools into it: the CREED standards and policy, PROCTOR results, the upgrade path, the WayneUI markup contract and migration guide, SESAME and HELMET guidance, and the parts list. Ask it "which PHP can I build on?" and it answers from the policy file, with the date and the source, instead of from whatever the model remembers.

It is read-only. It cannot write a file, run a shell command, or call out to the network. The two things it can start are the app's own `vendor/bin/proctor` and Booster's read-only `plan`, both with a fixed argument shape.

> Every WSU student has an advisor. Now every WSU app does too, and this one answers email.

## Install and run[#](https://advisor.apps.wayne.edu/docs/#install-and-run)

Add it to the app as a dev dependency, then run it:

```bash
composer require --dev waynestate/advisor
php vendor/bin/advisor
```

The command is `php vendor/bin/advisor`. This works the same on Windows, macOS and Linux, because it is one PHP command and not a shell script.

## Pick your path[#](https://advisor.apps.wayne.edu/docs/#pick-your-path)

- [Quick start](https://advisor.apps.wayne.edu/docs/guides/quick-start.html): register the `advisor` server with your client.
- [Install](https://advisor.apps.wayne.edu/docs/install.html): what it needs and how to add it to an app.
- [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html): the nine read-only tools.
- [Configuration](https://advisor.apps.wayne.edu/docs/reference/configuration.html): the environment variables.

## Status[#](https://advisor.apps.wayne.edu/docs/#status)

Current release 0.3.0. PHP ^8.3, license MIT, MCP SDK 0.9.0. New in 0.3.0: Parity, Citadel on your own computer, is the fifteenth part of the stack, so `list_projects`, `get_project_status`, `read_project_docs` and `owner_of` ("local development") know it; the bundle is resynced with the stack's 2026-11-09 night main branches; and CI runs on Composer 2.11.1 and Preflight 0.5.0. The [changelog](https://advisor.apps.wayne.edu/docs/changelog.html) has the full list, and [Upgrading](https://advisor.apps.wayne.edu/docs/upgrading.html) the one step it needs. Agent-ready index: [llms.txt](https://advisor.apps.wayne.edu/llms.txt).

## Related[#](https://advisor.apps.wayne.edu/docs/#related)

- [Install](https://advisor.apps.wayne.edu/docs/install.html)
- [Quick start](https://advisor.apps.wayne.edu/docs/guides/quick-start.html)
- [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html)
- [Configuration](https://advisor.apps.wayne.edu/docs/reference/configuration.html)
