---
title: "Quick start | ADVISOR docs"
description: "From install to a first question answered, in a few steps."
url: https://advisor.apps.wayne.edu/docs/guides/quick-start.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Quick start[#](https://advisor.apps.wayne.edu/docs/guides/quick-start.html#quick-start)

This page takes you from a fresh install to the `advisor` server registered in your client.

1. Print the config your MCP client needs, and read it:
   ```bash
   php vendor/bin/advisor install --client=claude
   ```
   Expected: it prints the config your MCP client needs.
2. Write it into the project when you are happy with it:
   ```bash
   php vendor/bin/advisor install --client=claude --write
   ```
   Expected: it writes the config into the project.
3. Open the project in your client and approve the `advisor` server by name when it asks.

The command it registers is always the same fixed string, `php vendor/bin/advisor mcp`, with `ADVISOR_ENABLED=1` in its environment. Clients: `claude` (`.mcp.json`), `warriorgpt` (`.mcp.json`), `cursor` (`.cursor/mcp.json`), `vscode` (`.vscode/mcp.json`) and `codex` (TOML, printed only; paste it into `~/.codex/config.toml`).

## What you built[#](https://advisor.apps.wayne.edu/docs/guides/quick-start.html#what-you-built)

The `advisor` server is registered in your client, running `php vendor/bin/advisor mcp` with `ADVISOR_ENABLED=1` in its environment, and it is ready to answer questions about the stack.

## Next steps[#](https://advisor.apps.wayne.edu/docs/guides/quick-start.html#next-steps)

- [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html): the nine read-only tools.
- [Configuration](https://advisor.apps.wayne.edu/docs/reference/configuration.html): the environment variables.
- [One line for Claude Code](https://advisor.apps.wayne.edu/docs/guides/claude-code.html): if you would rather type it than generate it.

## Related[#](https://advisor.apps.wayne.edu/docs/guides/quick-start.html#related)

- [Install](https://advisor.apps.wayne.edu/docs/install.html)
- [Overview](https://advisor.apps.wayne.edu/docs/index.html)
- [What it offers](https://advisor.apps.wayne.edu/docs/guides/what-it-offers.html)
- [Configuration](https://advisor.apps.wayne.edu/docs/reference/configuration.html)
