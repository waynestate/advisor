---
title: Refreshing the content
description: How the bundled content is regenerated, verified and checked for drift.
---

# Refreshing the content

This page shows how to keep ADVISOR's bundled content in `data/` current with the stack: regenerate it, verify it, and check for drift.

## What is generated

The handbook, policy, project docs, the WayneUI markup contract and WayneUI's 1.x to 2.x migration guide in `data/` are generated from the sibling stack repositories by `content:generate` and verified at start-up against `data/manifest.json` (a SHA-256 for every file). If a file does not match, the server refuses to start rather than serve it. Files are cleaned on the way in: line endings are normalized and invisible characters (the TAG block, zero-width and bidirectional controls) are stripped. Each project entry also carries its version when the project publishes one (composer.json, else package.json, else the newest CHANGELOG heading).

Beside the generated docs, hand-written guides are kept by hand in `generator/guides/` (one Markdown file per slug), the same way the ownership table is kept. Each is bundled into `data/guides/` and served as `advisor://guide/<slug>`; the first is "Connect to Oracle on Citadel".

## Regenerate

With the stack repos checked out side by side:

```bash
php vendor/bin/advisor content:generate --sources=/path/to/stack
```

`--sources` can also come from `ADVISOR_STACK_DIR`. Run `content:generate` after any change that lands in CREED's policy or handbook, a project's README or best practices, an extra doc, or the projects seed. Commit the regenerated `data/` with the source commits in the message.

The generator reads each project's folder from beside the other repos, so a stale clone gives stale docs. WayneUI's development happens on its GitLab repository and the checked-out copy is only a mirror of it: fast-forward the local wayne-ui clone to that repository's main before a resync.

To add a project to the parts list, add its `name` and `tagline` to `generator/projects.seed.json`, then regenerate and run `php bin/advisor stack-parts --write=README.md`.

Beyond a project's README and best practices, a seed may bundle further files under `extra_docs` (a map of slug to path in the project); each lands in `data/docs/<id>/<slug>.md` and in the project's docs list, and a missing file is skipped, not an error. WayneUI's entry bundles its MIGRATING.md as the `migrating` doc.

## Verify

CI cannot see the sibling repositories, so `composer ci` runs `content:verify`, which needs none of them:

```bash
php vendor/bin/advisor content:verify
```

It checks the bundled `data/` against `data/manifest.json` (a SHA-256 for every file).

## Check for drift

`composer content:drift` compares `data/` with a checkout of the stack repos and exits 1 when they no longer agree. It takes the folder from `ADVISOR_STACK_DIR`; without it, it prints how to run it and exits 1:

```bash
ADVISOR_STACK_DIR=<folder holding the stack repos> composer content:drift
```

It writes nothing. On drift it lists the files that moved; regenerate with `composer content:generate` and review the diff before committing. You can also pass `--check` to `content:generate` directly:

```bash
php vendor/bin/advisor content:generate --sources=/path/to/stack --check
```

That exits 1 on drift.

The GitLab job `content-drift` does the same in CI: it clones the repos listed in `data/projects.json` with the job token, puts them in one folder, sets `ADVISOR_STACK_DIR` and runs the check. It is manual or scheduled and allowed to fail, so it cannot block main. Run the drift check before every tag: a release must carry docs that still match the repos it advises on.

## The private denylist

ADVISOR is a public repository, so the bundle must not carry internal names. The public-posture test always checks generic shapes (ADR numbers, open-question numbers, design-doc paths, the coordination repo). Private strings (hostnames, people) are loaded from the file named by `ADVISOR_PRIVATE_DENYLIST` and checked only when the file is present.

To release with the denylist:

1. Set `ADVISOR_PRIVATE_DENYLIST` to a file kept outside the repository (one literal or `regex:` pattern per line, `#` comments).
2. In GitLab CI, the file comes from a masked file-type CI variable.
3. Regenerate the bundle with the file present: `php vendor/bin/advisor content:generate --sources=/path/to/stack`.
4. Run `composer ci` to confirm the public-posture test passes.

> [!WARNING]
> The denylist file is never committed. The bundle is regenerated with the file present before every release.

## Related

- [Releasing](releasing.md)
- [Served content](../guides/served-content.md)
- [Commands](../reference/commands.md)
- [Configuration](../reference/configuration.md)
- [Troubleshooting](../troubleshooting.md)
