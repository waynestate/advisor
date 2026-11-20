---
title: Compatibility
description: What ADVISOR promises not to break between releases, and when the promise starts.
---

# Compatibility

This page states what ADVISOR promises not to break between releases. The promise applies from 1.0. Until then it is the intent, and the 0.x rule below governs.

## What is covered

The nine tools and the shape of each answer. For every tool, the table lists its input fields and its output schema fields by name, top level only. These names are what the promise protects.

| Tool | Input fields | Output fields |
| --- | --- | --- |
| `get_project_status` | project | project, name, state, package, version, php_constraint, php_floor, php_floor_state, oldest_supported_php, today, docs, next_step, source, generated_on |
| `list_projects` | none | source, generated_on, note, projects |
| `list_stack_rules` | permutation, chapter, query, offset | source, generated_on, permutation, query, total, next_offset, notice, rules, note |
| `owner_of` | topic | source, generated_on, topic, matches, known_topics, note |
| `playbook_lookup` | key | available, how_to_get, entries, key, value, source |
| `policy_lookup` | component, version | source, generated_on, today, component, kind, minimum_version, eol_grace_days, oldest_supported, versions, answer |
| `read_project_docs` | project, doc, offset | project, doc, source, generated_on, offset, total_chars, next_offset, notice, text |
| `run_proctor_check` | path, checks | tool, proctor_version, app, checks_run, result, findings, notice |
| `upgrade_path` | path, from_php, to_php, to_laravel | tool, app, project, today, policy, target_php, target_laravel, steps, warnings, next_command, notice |

The field names come from the tool list snapshot in `tests/snapshots/tools-list.json`.

## What is not covered

- The wording and values of the bundled content. They change with every resync of the stack repositories.
- The order of list items, unless a tool says it orders them. Five do: `list_projects` returns `projects` in parts-list order, `policy_lookup` returns `versions` oldest first, `run_proctor_check` returns `checks_run` in PROCTOR order, `upgrade_path` returns `steps` as ordered hops, and `owner_of` returns `matches` best match first.
- The text of descriptions: the tool descriptions and the field descriptions.

## Environment variables

The `ADVISOR_*` variables documented in the README and in [Configuration](configuration.md):

- `ADVISOR_AUDIT_LOG`
- `ADVISOR_BOOSTER_BIN`
- `ADVISOR_BOOSTER_TIMEOUT`
- `ADVISOR_ENABLED`
- `ADVISOR_ENV`
- `ADVISOR_GROUPS`
- `ADVISOR_LOG_LEVEL`
- `ADVISOR_MAX_CHARS`
- `ADVISOR_MODULES_FILE`
- `ADVISOR_PRIVATE_DENYLIST`
- `ADVISOR_PROCTOR_TIMEOUT`
- `ADVISOR_PROJECT_ROOT`
- `ADVISOR_READ_ONLY`
- `ADVISOR_STACK_DIR`

A bad value is refused: the server stops with a message on stderr and does not guess. That is the rule both pages state.

## Commands

The advisor commands documented in [Commands](commands.md):

- `bin/advisor doctor`: Check the install: PHP, the PATH, the bundled content, the client config, and the server over stdio.
- `bin/advisor install`: Print (or with --write, write) the MCP client config that starts ADVISOR.
- `bin/advisor mcp`: Serve ADVISOR to an MCP client over stdio (started by the client, not by hand).
- `bin/advisor stack-parts`: Render the stack parts table from the seed; write or check the marked block in a README.
- `bin/advisor content:generate`: Rebuild the bundled content from the stack repositories (--check only compares).
- `bin/advisor content:verify`: Check the bundled content against its manifest.

## Versioning

ADVISOR follows Semantic Versioning.

While the version is 0.x, a minor release may change any of the above. When it does, it says so in the [changelog](../changelog.md) and in [Upgrading](../upgrading.md).

From 1.0, these changes are a major release:

- A removed or renamed tool.
- A removed input field.
- A removed or retyped output field.
- A removed environment variable or command.

Adding a tool, an input field, an output field, an environment variable or a command is a minor release.

A deprecation is announced one minor release before removal.

The promise on this page applies from 1.0. Until then it is the intent, and the 0.x rule above governs.

## Related

- [Configuration](configuration.md)
- [Commands](commands.md)
- [Best practices](best-practices.md)
- [Proof](proof.md)
- [Upgrading](../upgrading.md)
- [Changelog](../changelog.md)
