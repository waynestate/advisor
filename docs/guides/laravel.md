---
title: Laravel apps
description: How ADVISOR works in a Laravel app.
---

# Laravel apps

This page shows how ADVISOR runs inside a Laravel app, beside Laravel Boost.

## The Laravel adapter

ADVISOR ships a Laravel adapter on `laravel/mcp`, auto-discovered by the service provider. With it, the same server runs as `php artisan mcp:start advisor`, beside Boost.

> [!TIP]
> Boost knows the framework, ADVISOR knows WSU. They do not overlap, so run both.

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

## Related

- [What it offers](what-it-offers.md)
- [Configuration](../reference/configuration.md)
- [Quick start](quick-start.md)
- [Safety](safety.md)
