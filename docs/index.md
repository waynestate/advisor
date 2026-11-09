---
title: Overview
description: ADVISOR in one page: what it is, who it is for, and where to go next.
---

# Overview

<div align="center"><b>What applies, and what's next.</b></div>

This page tells you what ADVISOR is, how to run it, and where to go next.

ADVISOR is an MCP server for the Wayne State application stack, a bit like Laravel Boost but for WSU. It orients a developer who is new to the stack and gives their coding agent nine read-only tools into it: the CREED standards and policy, PROCTOR results, the upgrade path, the WayneUI markup contract and migration guide, SESAME and HELMET guidance, and the parts list. Ask it "which PHP can I build on?" and it answers from the policy file, with the date and the source, instead of from whatever the model remembers.

It is read-only in 0.1. It cannot write a file, run a shell command, or call out to the network. The two things it can start are the app's own `vendor/bin/proctor` and Booster's read-only `plan`, both with a fixed argument shape.

> Every WSU student has an advisor. Now every WSU app does too, and this one answers email.

## Install and run

Add it to the app as a dev dependency, then run it:

```bash
composer require --dev waynestate/advisor
php vendor/bin/advisor
```

The command is `php vendor/bin/advisor`. This works the same on Windows, macOS and Linux, because it is one PHP command and not a shell script.

## Pick your path

- [Quick start](guides/quick-start.md): register the `advisor` server with your client.
- [Install](install.md): what it needs and how to add it to an app.
- [What it offers](guides/what-it-offers.md): the nine read-only tools.
- [Configuration](reference/configuration.md): the environment variables.

## Status

Current release 0.1.7. PHP ^8.3, license MIT, MCP SDK 0.9.0. Main carries more since then: `upgrade_path` takes `from_php`, `policy_lookup` answers end of life for a version older than the policy lists, the [threat pages](threats/prompt-injection.md), and a bundle resynced with the stack's 2026-11-09 main branches. The [changelog](changelog.md) lists it under Unreleased. Agent-ready index: [llms.txt](../llms.txt).

## Related

- [Install](install.md)
- [Quick start](guides/quick-start.md)
- [What it offers](guides/what-it-offers.md)
- [Configuration](reference/configuration.md)
