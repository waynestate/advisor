---
title: Laravel apps
description: How ADVISOR works in a Laravel app.
---

# Laravel apps

This page shows how ADVISOR runs inside a Laravel app, beside Laravel Boost.

## The Laravel adapter

ADVISOR ships a Laravel adapter on `laravel/mcp`, auto-discovered by the service provider. With it, the same server runs as `php artisan mcp:start advisor`, beside Boost.

> [!TIP]
> Boost knows the framework, ADVISOR knows WSU, so run both. The one place they could overlap is rules; see [Boost's project rules and CREED's rules](#boost-s-project-rules-and-creed-s-rules).

## Publish the config

Publish the config if you want it in the app:

```
php artisan vendor:publish --tag=advisor-config
```

## Settings

Settings come from the same `ADVISOR_*` variables, read through `config/advisor.php`.

> [!WARNING]
> The same rules apply: off unless `ADVISOR_ENABLED` is set, and never in production.

## Registering the server

`advisor install` always writes the plain PHP command, `php vendor/bin/advisor mcp`. The artisan route, `php artisan mcp:start advisor`, is available through the service provider.

## Boost's project rules and CREED's rules

Boost can keep project rules: Markdown files in `.ai/rules`, committed with the app, that an agent files through Boost's `record-rule` tool when you say "remember that...". Boost writes the index, `.ai/rules/index.md`, and its docs say to record rules with the tool, not by hand. They are a good home for one app's own decisions. They are not a home for the stack's rules.

| | CREED rules | Boost project rules |
|---|---|---|
| Scope | Every WSU app on the stack | This one app |
| Written by | The stack's maintainers, in CREED's handbook | This app's team and its agents |
| Where an agent reads them | ADVISOR's `list_stack_rules`, quoted with a citation such as `creed/handbook/ci.md:5` | `.ai/rules`, through Boost's index |
| Changes when | A CREED release, picked up by `composer update` | Someone records a rule |

Three habits keep them from fighting:

- **Do not copy CREED rules into `.ai/rules`.** Two copies drift, and an agent follows whichever one it read last. If your agent offers to "remember" a stack rule, tell it to cite the rule's id from `list_stack_rules` instead.
- **A project rule adds to CREED; it never overrides it.** "Controllers in this app extend `BaseController`" is a project rule. "This app may skip the session cookie flags" is not: an exception to a CREED requirement is a question for CREED's maintainers, not a rule file.
- **When the two seem to disagree, CREED wins** and the project rule is the one to fix. Ask your agent to check a project rule against `list_stack_rules` with a `query` before you rely on it.

> [!NOTE]
> ADVISOR never writes to `.ai/rules` (all nine tools are read-only), and it does not read them either: they are your app's, and Boost already serves them. Boost's `BOOST_RULES_ENABLED=false` turns project rules off entirely; you do not need to for ADVISOR's sake.

## Related

- [What it offers](what-it-offers.md)
- [Configuration](../reference/configuration.md)
- [Quick start](quick-start.md)
- [Safety](safety.md)
