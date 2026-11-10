---
title: Modules
description: How ADVISOR modules extend what it can answer.
---

# Modules

This page explains how ADVISOR modules extend what it can answer and the rules that decide which modules load.

## How modules load

Each stack project can ship its own ADVISOR tools, and Squire already does (see [Modules the stack ships](#modules-the-stack-ships)).

> [!NOTE]
> Modules load only from a manifest (`modules.json`), only from the `Waynestate\` namespace, and only if they implement the `Module` interface. Nothing scans a folder.

## The manifest

The repository's own manifest is `modules.json` at the root:

```json
{
    "modules": [
        "Waynestate\\Advisor\\Module\\CoreModule"
    ]
}
```

It lists one module, `Waynestate\Advisor\Module\CoreModule`.

## An extra manifest

| Variable | Default | Meaning |
|---|---|---|
| `ADVISOR_MODULES_FILE` | none | An extra module manifest. |

Set `ADVISOR_MODULES_FILE` to point at an extra module manifest; its default is none. An extra manifest is held to the same rules: manifest only, the `Waynestate\` namespace, and the `Module` interface.

## Modules the stack ships

| Project | Module class | Tools it adds | Since |
|---|---|---|---|
| Squire | `Waynestate\Squire\Advisor\SquireModule` | `squire_doctor`, `squire_drift` (both read-only) | Squire 0.5.0 |

To use one, require the project in your app, list its class in a manifest of your own, and point `ADVISOR_MODULES_FILE` at that manifest. ADVISOR reads its own `modules.json` and that one extra file, nothing else.

## Related

- [What it offers](what-it-offers.md)
- [Configuration](../reference/configuration.md)
- [Safety](safety.md)
- [Quick start](quick-start.md)
