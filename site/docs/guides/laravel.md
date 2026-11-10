---
title: "Laravel apps | ADVISOR docs"
description: "How ADVISOR works in a Laravel app."
url: https://advisor.apps.wayne.edu/docs/guides/laravel.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Laravel apps [#](https://advisor.apps.wayne.edu/docs/guides/laravel.html#laravel-apps)

This page shows how ADVISOR runs inside a Laravel app, beside Laravel Boost.

## The Laravel adapter [#](https://advisor.apps.wayne.edu/docs/guides/laravel.html#the-laravel-adapter)

ADVISOR ships a Laravel adapter on `laravel/mcp`, auto-discovered by the service provider. With it, the same server runs as `php artisan mcp:start advisor`, beside Boost.

Tip

Boost knows the framework, ADVISOR knows WSU. They do not overlap, so run both.

## Publish the config [#](https://advisor.apps.wayne.edu/docs/guides/laravel.html#publish-the-config)

Publish the config if you want it in the app:

```
php artisan vendor:publish --tag=advisor-config
```

## Settings [#](https://advisor.apps.wayne.edu/docs/guides/laravel.html#settings)

Settings come from the same `ADVISOR_*` variables, read through `config/advisor.php`.

Warning

The same rules apply: off unless `ADVISOR_ENABLED` is set, and never in production.

## Registering the server [#](https://advisor.apps.wayne.edu/docs/guides/laravel.html#registering-the-server)

`advisor install` always writes the plain PHP command, `php vendor/bin/advisor mcp`. The artisan route, `php artisan mcp:start advisor`, is available through the service provider.

## Related [#](https://advisor.apps.wayne.edu/docs/guides/laravel.html#related)

- [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html)
- [Configuration](https://advisor.apps.wayne.edu/docs/reference/configuration.html)
- [Quick start](https://advisor.apps.wayne.edu/docs/guides/quick-start.html)
- [Safety](https://advisor.apps.wayne.edu/docs/guides/safety.html)
