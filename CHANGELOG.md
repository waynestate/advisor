# Changelog

All notable changes are listed here. The format follows Keep a Changelog; versions follow Semantic Versioning.

## [Unreleased]

### Added

- Added: `advisor doctor` has a `stdio` check: it starts `php bin/advisor mcp` as a child process, the way a client does, asks it to initialize, list its tools and answer one `policy_lookup` call, and passes only when every stdout line is JSON-RPC and the child exits 0. The pass line names the OS and PHP version, so one run on a Windows PHP answers open question 1. A `php-path` check says which `php` on the PATH a client will start, and warns when there is none or it is not the PHP running doctor (open question 10). Doctor still changes nothing and runs nothing from a config file.
- Added: a `native-os` GitHub workflow runs `advisor doctor` (with its new stdio check) and the stdio and doctor tests on Windows and macOS, PHP 8.3 and 8.5, on every push to main and every tag, and writes doctor's output to the run summary. It is separate from `ci.yml`, so the CI badge still means `composer ci`; the full suite stays Linux only.
- Added: `list_stack_rules` takes an optional `query` ("session cookie", or the whole question "what does the stack say about sessions?"): only the rules whose heading or text holds every word come back, matched case-insensitively at word starts, with question words and a plural s ignored. It combines with `permutation` and `chapter`, keeps the handbook order and paging, and says so when nothing matches. Still nine tools.
- Site: Directions gains a fourth turn, "Get a check-up": `php vendor/bin/advisor doctor` and a healthy readout (doctor's own wording, abridged) whose lines ink in one by one, the stdio stamp landing last; with reduced motion the readout is simply there. The intro says "Three steps and a check-up".

### Changed

- Changed: the deploy layer requires PROCTOR ^0.15 (locked 0.15.0, was ^0.12): 0.13 checks the release cooldown against the CREED policy and 0.14 adds `script-pair`; 0.15 adds `mysql56-ci`; all of these only warn, nothing breaks.
- Changed: the GitHub workflow calls Preflight 0.5.0's reusable workflow (pinned to its release commit), the same release as the GitLab pipeline; it was on 0.1.4 because GitHub's copy of Preflight had no later tag.
- Changed: the deploy layer requires PROCTOR ^0.12 (locked 0.12.0, was ^0.11): two new warning-only checks, nothing breaks.
- Site: the front page's stale facts are current (Squire ships an ADVISOR module, `squire_doctor` and `squire_drift`, so "Modules from each project" leaves "Not yet surveyed" for the Legend; the README, the Modules guide and the 0.2.0 upgrade note say so too).
- Changed: the deploy kit is Squire 0.10.7 (was 0.10.6): it ships a `.gitattributes` that pins line endings, so the deploy layer clears PROCTOR's line-ending warnings.
- Changed: the docs, agent-ready and deploy kits are Squire 0.10.6 (docs and agent-ready tools were the 0.9.0 bytes, deploy was 0.10.2): the docs builder and agent-ready generator see every file on Docker Desktop mounts, and `oracle-check.php` loses its D-046 reference.

## [0.3.0] - 2026-11-09

### Added

- Added: Parity is in the parts list (`generator/projects.seed.json`), after Squire: Citadel on your own computer (Citadel's PHP image, its proxy and MySQL 5.6) for any app, with or without Squire, as one Go command line for every OS on Docker or Podman (Podman recommended). `list_projects`, `get_project_status` and `read_project_docs` know it, and the stack-parts table in the README and the "Where it sits" page has fifteen rows.
- Added: `owner_of` answers "local development" (and Parity, run Citadel locally, the parity kit, Podman) with Parity: the stack's local-development answer, which replaces Squire's parity group. The deploy-files row says Squire's old parity group is deprecated, and names Parity as the one that runs `build.sh`, `deployments/` and the health check the way Citadel does.
- Added: the front page puts Parity on the map (the Reflecting Pool, top left) with a route for "Can I run my app like Citadel on my laptop?" (`get_project_status`, then `read_project_docs` for Parity) and a chip that asks it, and in the neighborhood, linked to its repository on git.wayne.edu until its site is live. The neighborhood is five across, three on a tablet and two on a phone with the odd one centered. The README cast and the readme-art kit gain Parity's mark (copied from Parity's own brand files, resized only).

### Changed

- Changed: the generator counts a Go module (`go.mod`) as built, like a Composer or npm package, and no longer reads a `CHANGELOG.md` heading marked unreleased (`## 0.1.0 (unreleased)`) as a published version.
- Changed: Squire's role in the parts list names what goes onto Citadel (build.sh, deployments/, the release script, the health check, the portable image) and points local runs at Parity; the front page's deploy answer does the same.

- Changed: the `wayneui-copies`, `composer-ci` and `content-drift` CI jobs and the documented Docker commands (README, MAINTAINING, Contributing, Docker, Proof) use `composer:2.11.1` pinned by its multi-arch list digest (`sha256:8de2184c...`; was `composer:2.10.3`). 2.10 is no longer maintained, and 2.11.1 fixes GHSA-c968-33g3-wh7p and GHSA-fm2w-xgf2-f9fh.
- Changed: the deploy kit is Squire 0.10.2 (was 0.10.1): the kit's Dockerfiles name every image in full (`docker.io/library/...`), the fallback base takes Composer from a `COMPOSER_IMAGE` stage, `.squire/php.ini` sets `session.cookie_samesite = Lax`, and the portable image drops `node_modules/` after the frontend build. `squire init --check` passes.
- Changed: the front page deploy layer requires CREED ^0.1.8 and HELMET ^1.3 (locked 0.1.8 and 1.3.0; were ^0.1 at 0.1.7 and ^1.0 at 1.0.1) and locks PROCTOR 0.11.1 (was 0.11.0). ADVISOR's dev tools take Pinstripe ^0.3 (locked 0.3.0, was ^0.1 at 0.1.0); before 1.0 a minor step can break, and Pint, PHPStan on Pinstripe's raw preset and the tests pass on it. The GitHub workflow pins Preflight 0.1.4's commit (`79029cc`, was an untagged commit before 0.1.4); the GitHub mirror of Preflight has no newer tag.
- Changed: CI pins the Preflight components to 0.5.0 (was 0.4.0). The composer-audit job drops its own `composer:2@sha256:b6dc...` image input, since 0.5.0's default is `composer:2.11.1` by digest, and the proctor job keeps PROCTOR's JSON report as `proctor.json` for Rollcall. The a11y component is the same at 0.5.0, so `scripts/a11y/` does not change.
- Changed: the proctor job on the front page deploy layer blocks the pipeline (no `allow_failure`). Its stale reason (unbounded constraints, CI files missing in `deploy/`) no longer holds: `vendor/bin/proctor check --format=codequality` in `deploy/` passes on PROCTOR 0.11.1 with warnings only.
- Docs: MAINTAINING's "Who maintains it" names a role, the stack maintainer, instead of a person, and says to reach them with an issue on git.wayne.edu. The CI cache comment says what the internal decision ID stood for.
- data: resync 2026-11-09 evening against each stack repo's main (booster:d0216d2 creed:33ff9bc drawbridge:7b5a2ee helmet:993ac13 pinstripe:a94f979 preflight:89a788f proctor:b0ac5ce rollcall:969622c sesame:70dcab1 sourdough:c99fd24 squire:0567cb3 weld:b168229 wayne-ui:dedc66f, and this branch for ADVISOR's own docs): versions in `projects.json` move to CREED 0.1.8, PROCTOR 0.11.1, SESAME 0.7.2, HELMET 1.3.0, Sourdough 0.3.0, Preflight 0.5.0, Booster 1.14.0, Rollcall 1.1.0, Drawbridge 0.6.0 and Squire 0.10.3, with the readmes, best practices and handbook chapters that moved since the 08:45 pass.
- Changed: data resynced from the stack (2026-11-09 night) against each stack repo's main (booster:c708793 creed:89f4b8c drawbridge:ee81fd7 helmet:fe05f93 pinstripe:a94f979 preflight:9cad07d proctor:be21475 rollcall:74850b8 sesame:033b140 sourdough:b56dd35 squire:0567cb3 weld:8e496d4 wayne-ui:dedc66f, and this branch for ADVISOR's own docs): the handbook's code chapter and its rules say the disabled-TLS and joined-SQL bans are built in Pinstripe 0.3.0 (they said Planned), the docs name Preflight 0.5.0 and SESAME `^0.7`, the accessibility chapter names Preflight's `a11y` component at 0.5.0, and `projects.json` moves to Sourdough 0.4.0 and Booster 1.15.0.
- Docs: `.env.example` says no write tools exist "yet" (it said "in 0.1"), as the README and docs already do.
- data: resync 2026-11-09 late night against each stack repo's main (booster:c708793 creed:6267c2a drawbridge:e45e2a8 helmet:5c7c1f2 pinstripe:bf2726d preflight:62a0c76 proctor:3efbc73 rollcall:0039490 sesame:03f78b1 sourdough:2117d25 squire:c553d29 weld:5168fb1 wayne-ui:dedc66f parity:34a5589, and this branch for ADVISOR's own docs): Parity's README is bundled as `docs/parity/readme.md`, and `projects.json` moves to Sourdough 0.4.1, Squire 0.10.4 and WELD 0.2.0, with the Drawbridge, Pinstripe, Preflight, Rollcall, Sourdough, Squire and WELD docs that moved.
- Docs: the Proof page's "Last run" table records `composer ci` on the 0.2.0 release commit on 2026-11-09 (282 tests, 1535 assertions; the 2026-11-07 run had 249).

## [0.2.0] - 2026-11-09

### Added

- Added: docs/threats/ explains four threats (prompt injection, path traversal, command injection, data exfiltration), where each applies, the code and tests that stop it, and what risk is left (backlog row 16).
- Added: upgrade_path takes from_php, so a project with no composer.json or no php constraint still gets a plan (Booster's --from-php); the 2026-11-08 proof run found 71 such refusals.

### Changed

- Changed: the deploy kit is Squire 0.10.1 (was 0.10.0): `.squire/base-fallback.Dockerfile` copies Composer 2.11.1 (was 2.10.3, no longer maintained), and the deploy layer requires PROCTOR ^0.11 (locked 0.11.0, was ^0.8 at 0.8.0).
- Changed: the deploy kit is Squire 0.10.0 (was 0.7.1): release.sh keeps Composer and npm download caches in the app folder and the gate takes SQUIRE_PHP_RUNTIME; the compose file, health.php and .env.squire.example follow the template, and the portable image files are added.
- Changed: ADVISOR reads the policy through CREED's own reader (waynestate/creed ^0.1.8) instead of a mirrored copy, so policy_lookup and get_project_status answer exactly what PROCTOR decides; a line marked current (vue 3, inertia 2 and 3) now reads as active, where the mirror gave no state. A version the policy does not list keeps ADVISOR's answers: older than every line is end of life with a note, newer is refused with the same message.
- Docs: the README, MAINTAINING and the contributing page list everything `composer ci` runs (the agent-ready and docs site checks were missing); "What it offers" says `policy_lookup` answers end of life for a version older than the policy lists and names `upgrade_path`'s `from_php` input, as the README does; the docs overview says what main carries since 0.1.7; and the front page footer links the docs, git.wayne.edu and the GitHub mirror.
- Changed: CI pins the Preflight components to 0.4.0 (the proctor job clones the full history, so the override-approver check can read git blame).
- Changed: the deploy layer runs Squire's kit 0.7.1 (was 0.6.0): `.squire/php.ini` no longer sets the deprecated `session.sid_*` lines, web requests get a `disable_functions` list and Apache hardening (`.squire/php-web.ini`, `.squire/apache-hardening.conf`), and opcache gets production settings with `SQUIRE_OPCACHE_VALIDATE` as the one switch.
- Changed: the front page deploy layer requires HELMET ^1.0 (lock at 1.0.1, was 0.4.0), like the rest of the stack; its shared front controller already runs on HELMET 1.0 in Squire, Sourdough and Pinstripe.
- Changed: policy_lookup answers end of life (with a note) for a version older than every one the policy lists, instead of refusing; the 2026-11-08 proof run found 13 such refusals for PHP 5.5 to 7.3.
- Changed: CI's accessibility gate is Preflight's a11y component (light and dark, 320 and 1280 px, and the WCAG 1.4.10 reflow check); ADVISOR's own page checks (one h1, lang, title, reduced motion) stay as the a11y-extra job.
- Docs: BEST_PRACTICES gains a threat model: eleven threats, each mapped to the rules that answer it and to OWASP Top 10:2025, OWASP ASVS 5.0 or NIST SSDF, plus what is out of scope (ADVISOR-16).
- Changed: CI pins the Preflight components to 0.3.1 (fixed cache keys, and caches saved even when a job fails; D-072).
- Changed: CI saves its Composer and npm caches even when a job fails, so a retry after a dropped download fetches only what is missing (D-072).
- Changed: CI caches Composer and npm downloads under a fixed key, so a lock change downloads only what changed (D-072).
- Fixed: OPEN_QUESTIONS.md names the Preflight commit the GitHub workflow really pins and says the README badges are real.
- Fixed: the docs menu closes with Escape and hands focus back to its button (docs kit synced from Squire).
- Changed: the deploy layer requires PROCTOR ^0.8 (locked 0.8.0).
- Changed: the pipeline pins every Preflight CI component to 0.3.0 (preflight@0.3.0); component image inputs are plain image names, since Preflight adds the Dependency Proxy prefix itself.
- data: resync 2026-11-08 morning (booster:9d76fee drawbridge:829f465 helmet:e96f7f6 sesame:aea6871 proctor:181bb7e rollcall:2a053b9 creed:e7818c4 sourdough:2182baf weld:bf9c2e4 squire:5276918 preflight:3aa18d2 pinstripe:b56f077 advisor:e82d901 wayne-ui:dedc66f): HELMET's README matches its code (the presets set proxies only, so the app picks its log file; a relative CSP report path is valid), and Drawbridge's third-party-cookie sign-out practice now runs in a real browser engine.
- data: resync 2026-11-08 (booster:b8b5e5a drawbridge:a7c42fb helmet:b8196b9 sesame:7195b63 proctor:97c2005 rollcall:091bd8b creed:e7818c4 sourdough:2182baf weld:bf9c2e4 squire:34bd199 preflight:3aa18d2 pinstripe:54790ca advisor:fcc5974 wayne-ui:dedc66f): the policy gains CREED's `sessions` block (SESAME and Drawbridge session lifetimes), and PROCTOR's README gains its ADVISOR module section.
- data: resync 2026-11-07 late (booster:4fbf3ed drawbridge:a7c42fb helmet:b8196b9 sesame:2302725 proctor:e00dda4 rollcall:45b0837 creed:be2a0b2 sourdough:2182baf weld:bf9c2e4 squire:55b89c7 preflight:3aa18d2 pinstripe:54790ca advisor:4d8f41a wayne-ui:dedc66f): the word-name sweep (Rollcall, Squire, Preflight, Drawbridge in prose), WELD 0.1.5, PROCTOR's crlf-deploy-script check.
- Changed: the GitLab pipeline pins the Preflight components to 0.2.0 (was 0.1.0 and 0.1.2).
- The front page's deploy layer (`deploy/`) runs Squire's kit 0.6.0 (was 0.5.0): `scripts/release.sh` asks PROCTOR's deploy gate for a go or no-go on each release (a report, not a stop, unless `SQUIRE_PROCTOR=enforce`), and `.env.squire.example` lists the new setting. The layer's pins move to HELMET `^0.4` (locked 0.4.0) and PROCTOR `^0.7` (locked 0.7.0).
- CI caches Composer and npm downloads per lock file (`.composer-cache`, keyed on `composer.lock` and `deploy/composer.lock`, and `.npm-cache`), so a pipeline stops fetching every package from Packagist and npm on every run. Images already come through the Dependency Proxy.
- data: resync 2026-11-07 evening (booster:d99b049 creed:39fa8c3 drawbridge:27cc793 helmet:902b522 pinstripe:cdabcb0 preflight:f26584b proctor:e9ede7e rollcall:06dcfda sesame:b5a0c09 sourdough:181b884 squire:dba0686 weld:2746423 wayne-ui:dedc66f advisor:45c8379): README and best-practices updates from Drawbridge, Preflight, PROCTOR, Rollcall, SESAME and Sourdough, and ADVISOR's own README. Versions in `projects.json` are unchanged.
- data: Sourdough's entry lists all five shapes, `--raw --vue` included (it said that shape was not available yet), and its next step names `sourdough feed`, which ships (it said feed was a later release).
- Changed: the a11y-extra job runs Playwright 1.65.0 and axe-core 4.14.0 on the image digest Preflight 0.4.0 pins (was Playwright 1.49.1 and axe-core 4.10), so ADVISOR's own page checks and the stack's a11y gate use one engine.
- data: resyncs from 2026-11-08 03:40 through 2026-11-09 08:45 against each stack repo's main: Preflight 0.4.0 pins and its must-fail fixtures, PROCTOR 0.9.0 (the entrypoint-migrate, gitattributes, preflight-gates and mysql-minimum rules), Squire 0.9.0, SESAME 0.6.2 (strict-mode sessions, CAS 2.0, the Eloquent refresh fix), HELMET 1.0.1 and its ribbon list, Rollcall 1.0.0, Pinstripe 0.3.0, Booster 1.12.0, Drawbridge 0.4.0, CREED handbook tags and WELD's regen gate.

### Fixed

- Fixed: a docs truth pass (2026-11-09). The install page lists `waynestate/creed` and the Symfony `^7.4 || ^8.0` range; "Where it sits" names all four Preflight components; the best-practices and contributing pages stop listing the threat model as not built; the commands page gives doctor's exit code 2; the threat pages cite the lines the code is on today; OPEN_QUESTIONS lists every optional setting. The front page's ask box says Drawbridge is an OpenID Connect client for Entra ID (it said SAML), names SESAME's OpenID Connect driver, carries Laravel 13's real active-support date and MySQL 5.6 from the policy, and writes Booster, Preflight, Squire, Rollcall and Drawbridge as words.
- Fixed: the docs and the front page keep their Docs link in the header on a phone (it used to vanish below 900px with the section links), the docs sidebar opens scrolled to the page you are on with a thin scrollbar, Troubleshooting's symptoms are h2 headings under the h1 (they skipped to h3), and the Overview's tagline sits under its title.
- Fixed: the docs kit's copy button no longer covers the first line of code when it says Copied or Failed: over the code it keeps its resting width and shows a check or a cross when the word does not fit (docs kit synced from Squire).
- Fixed: the front page's version chip shows the full version the docs show (Sheet 0.1.7, was Sheet 0.1). It carries `data-docs-version` now, so the docs builder (synced from Squire) rewrites it at every release and `composer docs:check` fails while it is stale.
- Fixed: the docs overview's tagline (What applies, and what's next.) has a paragraph's room above and below, in the map's display face, instead of sitting tight on the text under it.
- The pipeline's PROCTOR jobs no longer pass `--policy`, which PROCTOR 0.7 refuses in CI; PROCTOR checks against the CREED policy it bundles.
- The Docker commands in the README, MAINTAINING.md and the contributing and proof docs mount the shared Composer cache (`-v stack-composer-cache:/tmp/cache`), and the accessibility gate's command mounts the npm cache, so a local run does not download every package again.
- The front page's parts list says Sourdough starts all five app shapes; it said "the raw PHP starter".

## [0.1.7] - 2026-11-07

### Changed

- The front page's deploy layer (`deploy/`) completes Squire's kit 0.5.0: `oracle-check.php`, which the 0.5.0 `squire update` did not add, is written and recorded, so `squire init --check` passes. `site/assets/stack-env.js` takes the stack's one copy, with bindingless catch blocks.
- The canonical repository is git.wayne.edu (ADR-0021); docs, links and package metadata no longer point at the archived nougat copy.

### Added

- `owner_of` answers from an ownership table rebuilt from the stack's decisions, the one-owner-per-rule table first (version policy, lock file, `composer audit`, MySQL 5.6, `/up`, deploy files, CI contents, the parts list), then app wiring, sign-in and the coding standard. Every match carries `decision`, the decision it comes from, named by title and date. Rows gain `aliases` ("composer.lock", ".gitlab-ci.yml", "/up"), question words are ignored ("who owns composer audit" works), words match at word starts, and exact topic or alias matches come first. `content:verify` requires the decision and refuses an alias that names two rows.
- `read_project_docs` trims image boilerplate again at read time, so docs bundled before a trim rule existed are served clean without editing `data/docs`. The trim (now `Content\ImageTrim`, shared with the generator) also drops linked hero images (an image Markdown wrapped in a link) and rows of nothing but badges, which the generator used to let through (Sourdough's README hero and badge row).

- The Proof page records its last run.
- data: resync 2026-11-06 night (booster:8c57a15 drawbridge:a60990a helmet:8a79b46 sesame:2505033 proctor:038d632 rollcall:aed8dd9 creed:0f6ab20 sourdough:b2644b3 weld:baf8e60 squire:88fd2ec preflight:a5ed45a pinstripe:eb03078 advisor:c3964c5 wayne-ui:9e1e762): Booster, CREED handbook and best practices, PROCTOR, Preflight and SESAME docs, the abandoned_packages policy, and the released versions of Booster, PROCTOR, Sourdough and others.
- data: resync for the 2026-11-07 releases (Booster 1.10.0, CREED 0.1.7, Drawbridge 0.3.0, HELMET 0.4.0, Preflight 0.2.0, PROCTOR 0.7.0, Rollcall 0.1.10, SESAME 0.5.0, Sourdough 0.1.7, Squire 0.6.0, WELD 0.1.4; Pinstripe stays 0.1.4).

## [0.1.6] - 2026-11-06

### Added

- data: resync 2026-11-05 evening (booster:eaf2758 drawbridge:aa403f3 helmet:36cb918 sesame:ae2fb9e proctor:33c329a rollcall:fc4cc43 creed:d919161 sourdough:bdb831d weld:6da94b9 squire:335c4e4 preflight:7913c45 pinstripe:347a34e wayne-ui:66d5539 advisor:4fdc7b0)
- data: resync 2026-11-05 late (booster:5b25715 drawbridge:19698ea helmet:417129a sesame:ae2fb9e proctor:33c329a rollcall:fc4cc43 creed:d919161 sourdough:bdb831d weld:6da94b9 squire:9384c1d preflight:7913c45 pinstripe:347a34e wayne-ui:66d5539 advisor:ba8b7e1)
- data: resync 2026-11-06 morning (booster:fc8e42e drawbridge:265c57d helmet:bca4c25 sesame:67a7fae proctor:33c329a rollcall:a8aaac5 creed:15cc9cc sourdough:387773e weld:a88d703 squire:6a6a352 preflight:734728d pinstripe:7d71088 wayne-ui:7c237a2 advisor:31544c6): Booster 1.9.0, WELD 0.1.3, the CREED and ROLLCALL docs brand pass, proof pages for Preflight, Pinstripe, ROLLCALL and WELD.
- data: resync 2026-11-06 afternoon (booster:bf1bd59 drawbridge:131ea28 helmet:8718bbf sesame:af9e764 proctor:5a8d5ca rollcall:6e9d4a8 creed:cbf2163 sourdough:7444c3c weld:6eb9c42 squire:8bbc9c7 preflight:f1c56ac pinstripe:e329dd7 wayne-ui:9e1e762 advisor:8d3d2f6): accessibility gates on SESAME, Booster, CREED, Preflight, PROCTOR and HELMET, and the Booster dogfood fixes for the front-end hops.
- data: resync 2026-11-06 evening (booster:bf1bd59 drawbridge:d0fa5b4 helmet:8718bbf sesame:af9e764 proctor:5a8d5ca rollcall:6e9d4a8 creed:cbf2163 sourdough:173b14a weld:82ee1a6 squire:8bbc9c7 preflight:f1c56ac pinstripe:e329dd7 advisor:a7d58f2 wayne-ui:9e1e762): accessibility gates on WELD and DRAWBRIDGE, the Sourdough raw PHP + Vue shape.
- Added: a proof run (scripts/proof/ProofRunner.php, bin/proof.php) that walks a corpus of app folders through the real `bin/advisor mcp` server over stdio and keeps one JSONL line per app (pass, flagged, tool-error or not-applicable); dev tooling, not part of the served product.
- Added: an accessibility gate (scripts/a11y/check.mjs) that loads every HTML page under site/ in a real browser and fails on any axe-core WCAG violation, in light and dark themes at phone and desktop width, plus a reduced-motion pass; runs as the a11y job in GitLab CI.
- Added: a docs site (site/docs/) built by the shared docs builder from docs/, checked in composer ci.

### Changed

- Changed: resynced the bundled content (data/) with the stack as of 2026-11-05, including Booster 1.8.2 and 1.8.3, DRAWBRIDGE (session store, secret files, GitHub references removed), HELMET (H21 partly enforced, warn_broad_proxies), SESAME (CAS login bound to its browser), PROCTOR, ROLLCALL, CREED handbook chapters, Sourdough (--sail, --docker, Pest 4) and the WELD and SQUIRE guides; the Sourdough role now lists every shape that generates; the docs builder and kit follow SQUIRE 97744a4, which keeps the changelog page h1 itself.
- Changed: the docs pages wear the front page's look: the same sheet head and main menu (Docs marked current), Fraunces titles with a plotted maroon route, the legend's mono labels, commands on the dark code ground with the gold Copy tab, callouts drawn as map marks (note, tip, out of bounds), the green footer band, and a dark mode derived from the Directions band. Motion only without a reduced-motion preference. Source: `docs/_header.html` and `docs/theme/advisor.css` through the builder's `theme` key. The front page site owes a Quick Update.
- Changed: the bundled data is resynced with the stack's main branches of 2026-11-03 (afternoon): SESAME S45 and the DRAWBRIDGE module floor (from branch heads awaiting main), HELMET H42 4xx pages, H43, H53 and H41 notes; SESAME S4, S41, S42 and S27; plus the Booster, Preflight, PROCTOR and Squire docs that moved.
- Changed: the bundled data is resynced again (evening): PROCTOR composer-audit-off, not-audited, scoped overrides and the ignore-entries count; Pinstripe Node 22/24/26 matrix, the Laravel + Vue fixture and ADR-0001; plus the other projects docs that moved on main.
- Changed: the bundled data is resynced with the stack's main branches (late evening of 2026-11-03): Sourdough (Playbook required by default, released HELMET/SESAME/PROCTOR pins in the templates, Preflight pinned to a SHA), Squire (release workflow, docs builder gates), WELD, HELMET 0.3, SESAME 0.4, Preflight, Booster and Drawbridge docs; ADVISOR's own version reads 0.1.5.
- Changed: the bundled data is resynced again (night of 2026-11-03): the Pinstripe and Squire readmes moved; Booster, ROLLCALL and WELD docs were already current.
- Changed: the bundled data is resynced again (late night of 2026-11-03): Sourdough (`composer playbook` script), HELMET, Preflight and Squire readmes moved; WELD docs were already current.
- Changed: symfony/console and symfony/process accept ^8.0 as well as ^7.4, so apps on Pest 5 / PHPUnit 13 can install ADVISOR.
- Changed: the docs builder and kit are resynced from Squire's unified builder (theme hook, callouts, code tabs, task lists, copy button fix).

### Fixed

- Fixed: the proof runner keeps the tool binary paths out of the error text: each of the proctor, booster and advisor binary paths is shortened to its file name before the scratch and corpus folders are masked as /app.
- Fixed: front-page scroll reveals start hidden from first paint (plain CSS under `html.motion`, set in `<head>` before the body parses) instead of fading out after load, so unscrolled below-the-fold content sits at opacity 0 rather than being caught mid-fade; the motion class is only set where IntersectionObserver exists, so a page that hides content can always bring it back.
- Fixed: docs code blocks that scroll sideways are keyboard focusable (`tabindex="0"`), satisfying the scrollable-region-focusable rule. Since the resync with Squire's unified docs builder, the kit's docs.js does this for every block that overflows, tabs included.
- Fixed: the changelog docs page keeps its `# Changelog` heading, so the page has exactly one level-1 heading (a comment now sits above the heading in CHANGELOG.md, so the shared builder keeps it).
- Fixed: table-of-contents sub-links carry the base `docs-toc__item` class again, so they get the block display and padding that keep them at or above the 24px target size (and their hover color).

### Security

- The front-page deploy layer moves to Symfony 7.4.23 (symfony/http-foundation and symfony/yaml advisories, including CVE-2026-118514).

## [0.1.5] - 2026-11-03

- Changed: the deploy layer requires HELMET ^0.3 and PROCTOR ^0.4 (locked at 0.3.0 and 0.4.0) and sets minimum-stability to stable, which clears PROCTOR 0.4's new minimum-stability warning. HELMET 0.3 reads an empty `HELMET_PROXIES=` as trust nobody; the front page sets only the `citadel` preset, so nothing changes for it. The front page site owes a Quick Update.
- Changed: the deploy layer's Squire kit is refreshed to 0.5.0 (updated `base-fallback.Dockerfile`, `scripts/release.sh` and `scripts/parity.ps1`). The front page site owes a Quick Update.
- Changed: the bundled data is resynced with the 2026-11-03 releases: Booster 1.8.0, Preflight 0.1.4, PROCTOR 0.4.0, Pinstripe 0.1.2, CREED 0.1.6, ROLLCALL 0.1.9, HELMET 0.3.0, SESAME 0.4.0, Drawbridge 0.1.1, Sourdough 0.1.5, Squire 0.5.0 and WELD 0.1.2 (versions in `projects.json`), and with each project's main since the last pass (PROCTOR's new `composer-config` and `composer-audit` rule ids, Booster's newcomer-friendly install and run notes, Preflight's `job_name` inputs, and updates to the Drawbridge, Pinstripe, ROLLCALL, SESAME, HELMET, Squire and Sourdough docs).
- Added: one Playbook settings resolver (`Waynestate\Advisor\Playbook\Settings`, ADR-0018). A name resolves from the real environment first, then the Playbook package, then a `.env.playbook` file in the project root; secret-looking names are still refused. `playbook_lookup` now uses it, so it answers from `.env.playbook` when the package is not installed (same output shape), and the "how to get it" text now includes the `.env.playbook` download for Linux, macOS and Windows.
- Changed: `.env.playbook` is git-ignored; the agent-ready files (`site/llms.txt`, `site/llms-full.txt`) are regenerated.

- Changed: docs and package metadata name nougat as the canonical repository, with git.wayne.edu and GitHub as read-only mirrors, until git.wayne.edu has CI runners.

## [0.1.4] - 2026-11-03

- Changed: CI pulls its Docker Hub images through the GitLab Dependency Proxy (the group's CI_DEPENDENCY_PROXY_GROUP_IMAGE_PREFIX), so pipelines no longer hit Docker Hub's anonymous pull limit.

## [0.1.3] - 2026-11-02

### Added

- Added: hand-written guides bundled from generator/guides/ and served as advisor://guide/<slug>; first guide: Connect to Oracle on Citadel.
- Added: policy_lookup returns latest_patch; the bundled docs drop hero images, badges and GIFs.
- Added: WayneUI's migration guide (MIGRATING.md) is bundled as the migrating doc, and every project entry carries its version when it publishes one (composer.json, else package.json, else the newest CHANGELOG heading).
- Docs: a one-line `claude mcp add` and a Docker `.mcp.json` for machines without PHP.
- Added: composer content:drift and an optional CI job that fail when data/ no longer matches the sibling repos.

### Changed

- Changed: the deploy layer's Squire kit is refreshed to 0.3.3 (release options can now live in ./.env.squire).
- Changed: the deploy layer's Squire kit is refreshed to 0.3.4 (the local .env.squire file is now git-ignored).

## [0.1.2] - 2026-10-29

### Added

- Added: agent-ready site (prompt blocks, Markdown twins, llms.txt), from Squire's kit.
- `playbook_lookup` tool: answers "what is the value of X?" for WSU shared values. An environment variable of the
  same name wins, then the private `waynestate/playbook` package (Playbook) supplies the rest; without it the tool
  says how to get it instead of failing. Keys that look like secrets (ending in SECRET, PASSWORD, TOKEN or KEY) are
  refused. `waynestate/playbook` is suggested, never required.

### Changed

- Changed: docs copies resynced; proof results now live on proof.apps.wayne.edu.
- Changed: project names written as words except acronyms.

### Fixed

- Fixed: stale capability claims in the site and docs.

## [0.1.1] - 2026-10-29

### Added

- `owner_of` tool: answers "which project owns X?" from a new bundled `data/ownership.json` table (sourced from
  `generator/ownership.json`, verified by `content:verify`). Fuzzy match: every word of the topic must appear in a
  row's topic or role; a no-match answer lists every known topic.

### Changed

- `upgrade_path` moves from the `stack` group to a new `booster` group, so `ADVISOR_GROUPS=stack` offers reference
  tools only and never starts a program. `ADVISOR_GROUPS` now accepts `stack`, `proctor` and `booster`.
- PHPStan includes Pinstripe's `raw.neon` preset (`waynestate/pinstripe` 0.1.0 as a dev dependency) instead of a hand
  copy of its settings; two docblocks were tightened to satisfy the preset's method-signature checks. Pint already
  used the `per` preset, so `pint.json` is unchanged.

## [0.1.0] - 2026-10-29

First build, aimed at 0.1.0.

### Added

- Read-only MCP server over stdio (`php vendor/bin/advisor mcp`) on the official `mcp/sdk` 0.9.0, speaking both the
  legacy `initialize` handshake and the 2026-07-28 per-request form.
- Seven tools: `list_projects`, `get_project_status`, `read_project_docs`, `policy_lookup`, `list_stack_rules`,
  `run_proctor_check`, `upgrade_path`. Resources `advisor://policy` and `advisor://creed/<chapter>`. Prompts
  `orient_me` and `plan_upgrade`.
- `upgrade_path`: plans the app's PHP and/or Laravel upgrade, one supported hop at a time, by running BOOSTER's
  read-only `plan` (never `run`) against the bundled policy. New settings `ADVISOR_BOOSTER_TIMEOUT` (default 60)
  and `ADVISOR_BOOSTER_BIN` (fallback when the app has no `vendor/bin/booster` of its own).
- Bundled, hash-verified content (CREED handbook and policy, project docs, the WayneUI markup contract) with a
  generator, `content:generate --check` and `content:verify`.
- `advisor install` for Claude, WarriorGPT, Cursor, VS Code (written) and Codex (printed).
- Laravel adapter on `laravel/mcp` (`php artisan mcp:start advisor`), so ADVISOR runs beside Laravel Boost.
- Safety: off unless enabled, refuses production, read-only by default, path confinement, allowlisted child
  environment, redaction, invisible-character stripping, manifest-only module loading, argument-hash audit log.
- `composer ci`: validate, content verify, PHPUnit, Pint, PHPStan level 8 (Pinstripe settings), audit. CI files for
  GitLab (PREFLIGHT 0.1.0) and GitHub.
