---
title: "Releasing | ADVISOR docs"
description: "How ADVISOR is released."
url: https://advisor.apps.wayne.edu/docs/deploying/releasing.html
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

# Releasing[#](https://advisor.apps.wayne.edu/docs/deploying/releasing.html#releasing)

This page gives the numbered steps for releasing ADVISOR, from the README's "Releasing" section and MAINTAINING.md.

ADVISOR is public, so its bundle must not carry internal names. The public-posture test always checks generic shapes; private strings are loaded from a denylist file named by `ADVISOR_PRIVATE_DENYLIST`, kept outside the repository (in CI, a masked file-type variable). See "Releasing" in the README.

Warning

A release needs a maintainer's approval. Never move or delete a published tag.

## Steps[#](https://advisor.apps.wayne.edu/docs/deploying/releasing.html#steps)

1. Move the lines under `## [Unreleased]` in `CHANGELOG.md` (Keep a Changelog) to a new `## [X.Y.Z] - YYYY-MM-DD` heading.
2. With the denylist file present, regenerate: `php bin/advisor content:generate --sources=<stack folder>`, then `composer ci` (the public-posture test must pass).
3. Both pipelines green for that commit. Run a secrets scan of the history.
4. Tag `X.Y.Z` (no `v`) on nougat. The mirror carries the tag to GitHub, and Packagist picks it up from there.
5. A release needs a maintainer's approval. Never move or delete a published tag.

## The denylist[#](https://advisor.apps.wayne.edu/docs/deploying/releasing.html#the-denylist)

The public-posture test always checks generic shapes (ADR numbers, open-question numbers, design-doc paths, the coordination repo). Private strings (hostnames, people) are loaded from the file named by `ADVISOR_PRIVATE_DENYLIST` and checked only when the file is present. Set it to a file kept outside the repository (one literal or `regex:` pattern per line, `#` comments); in GitLab CI, the file comes from a masked file-type CI variable. The file is never committed. The bundle is regenerated with the file present before every release.

## Related[#](https://advisor.apps.wayne.edu/docs/deploying/releasing.html#related)

- [Proof](https://advisor.apps.wayne.edu/docs/reference/proof.html)
- [Refreshing the content](https://advisor.apps.wayne.edu/docs/deploying/refreshing-the-content.html)
- [Changelog](https://advisor.apps.wayne.edu/docs/changelog.html)
