---
title: "No PHP? Use Docker | ADVISOR docs"
description: "How to run ADVISOR with Docker when PHP is not on your PATH."
url: https://advisor.apps.wayne.edu/docs/guides/docker.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# No PHP? Use Docker[#](https://advisor.apps.wayne.edu/docs/guides/docker.html#no-php-use-docker)

This page shows how to point your MCP client at Docker to run ADVISOR when PHP is not on your PATH.

## Point the client at Docker[#](https://advisor.apps.wayne.edu/docs/guides/docker.html#point-the-client-at-docker)

If PHP is not on your PATH, point the client at Docker instead. Run it from the project root, the folder that holds `vendor/`, with a hand-written `.mcp.json` like this:

```json
{
  "mcpServers": {
    "advisor": {
      "command": "docker",
      "args": [
        "run",
        "-i",
        "--rm",
        "--network",
        "none",
        "-v",
        ".:/app:ro",
        "-w",
        "/app",
        "-e",
        "ADVISOR_ENABLED=1",
        "-e",
        "ADVISOR_PROJECT_ROOT=/app",
        "-e",
        "ADVISOR_GROUPS=stack",
        "-e",
        "ADVISOR_READ_ONLY=1",
        "composer:2.10.3",
        "php",
        "vendor/bin/advisor",
        "mcp"
      ]
    }
  }
}
```

## What the config does[#](https://advisor.apps.wayne.edu/docs/guides/docker.html#what-the-config-does)

The container has no network, and the project is mounted read-only. `ADVISOR_GROUPS=stack` keeps the tools that start programs (proctor, upgrade_path) off, because those need the app's own PHP. Some clients want an absolute path in place of `.` for the mount; give them one. Advice comes in; nothing goes out.

## Where to run it[#](https://advisor.apps.wayne.edu/docs/guides/docker.html#where-to-run-it)

Run the client from the folder that holds `vendor/`. If it starts somewhere else, set `ADVISOR_PROJECT_ROOT`.

## Keep it out of git[#](https://advisor.apps.wayne.edu/docs/guides/docker.html#keep-it-out-of-git)

This file is written by hand, so keep it out of git like the generated one. `install` never replaces a different existing `advisor` entry unless you pass `--force`, keeps your other servers, and warns when the file is not in `.gitignore`.

Warning

A client config starts a program on whoever opens the project, so do not commit a generated one; this repository ignores `.mcp.json` for that reason.

## Related[#](https://advisor.apps.wayne.edu/docs/guides/docker.html#related)

- [One line for Claude Code](https://advisor.apps.wayne.edu/docs/guides/claude-code.html)
- [Quick start](https://advisor.apps.wayne.edu/docs/guides/quick-start.html)
- [Install](https://advisor.apps.wayne.edu/docs/install.html)
- [Configuration](https://advisor.apps.wayne.edu/docs/reference/configuration.html)
