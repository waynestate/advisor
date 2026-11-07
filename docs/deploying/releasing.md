---
title: Releasing
description: How ADVISOR is released.
---

# Releasing

This page gives the numbered steps for releasing ADVISOR, from the README's "Releasing" section and MAINTAINING.md.

ADVISOR is public, so its bundle must not carry internal names. The public-posture test always checks generic shapes; private strings are loaded from a denylist file named by `ADVISOR_PRIVATE_DENYLIST`, kept outside the repository (in CI, a masked file-type variable). See "Releasing" in the README.

> [!WARNING]
> A release needs a maintainer's approval. Never move or delete a published tag.

## Steps

1. Move the lines under `## [Unreleased]` in `CHANGELOG.md` (Keep a Changelog) to a new `## [X.Y.Z] - YYYY-MM-DD` heading.
2. With the denylist file present, regenerate: `php bin/advisor content:generate --sources=<stack folder>`, then `composer ci` (the public-posture test must pass).
3. Both pipelines green for that commit. Run a secrets scan of the history.
4. Tag `X.Y.Z` (no `v`) on git.wayne.edu. Its push mirror carries the tag to GitHub, and Packagist picks it up from there.
5. A release needs a maintainer's approval. Never move or delete a published tag.

## The denylist

The public-posture test always checks generic shapes (ADR numbers, open-question numbers, design-doc paths, the coordination repo). Private strings (hostnames, people) are loaded from the file named by `ADVISOR_PRIVATE_DENYLIST` and checked only when the file is present. Set it to a file kept outside the repository (one literal or `regex:` pattern per line, `#` comments); in GitLab CI, the file comes from a masked file-type CI variable. The file is never committed. The bundle is regenerated with the file present before every release.

## Related

- [Proof](../reference/proof.md)
- [Refreshing the content](refreshing-the-content.md)
- [Changelog](../changelog.md)
