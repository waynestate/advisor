---
title: "Modules | ADVISOR docs"
description: "How ADVISOR modules extend what it can answer."
url: https://advisor.apps.wayne.edu/docs/guides/modules.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Modules[#](https://advisor.apps.wayne.edu/docs/guides/modules.html#modules)

This page explains how ADVISOR modules extend what it can answer and the rules that decide which modules load.

## How modules load[#](https://advisor.apps.wayne.edu/docs/guides/modules.html#how-modules-load)

Each stack project can later ship its own ADVISOR tools.

Note

Modules load only from a manifest (`modules.json`), only from the `Waynestate\` namespace, and only if they implement the `Module` interface. Nothing scans a folder.

## The manifest[#](https://advisor.apps.wayne.edu/docs/guides/modules.html#the-manifest)

The repository's own manifest is `modules.json` at the root:

```json
{
    "modules": [
        "Waynestate\\Advisor\\Module\\CoreModule"
    ]
}
```

It lists one module, `Waynestate\Advisor\Module\CoreModule`.

## An extra manifest[#](https://advisor.apps.wayne.edu/docs/guides/modules.html#an-extra-manifest)

| Variable | Default | Meaning |
| --- | --- | --- |
| `ADVISOR_MODULES_FILE` | none | An extra module manifest. |

Set `ADVISOR_MODULES_FILE` to point at an extra module manifest; its default is none. An extra manifest is held to the same rules: manifest only, the `Waynestate\` namespace, and the `Module` interface.

## Related[#](https://advisor.apps.wayne.edu/docs/guides/modules.html#related)

- [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html)
- [Configuration](https://advisor.apps.wayne.edu/docs/reference/configuration.html)
- [Safety](https://advisor.apps.wayne.edu/docs/guides/safety.html)
- [Quick start](https://advisor.apps.wayne.edu/docs/guides/quick-start.html)
