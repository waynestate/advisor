# ADVISOR README art kit

Cuts the README images under `docs/readme/` from this repo's own front page
(`site/`), brand files and source. Nothing is drawn freehand: the hero and the
gallery are screenshots of the real page, the cast is the existing marks
resized, the badges read their facts from `composer.json`, and the route map is
plotted from the data the page itself uses.

## Run

Windows PowerShell and Linux/macOS sh, side by side:

| Windows PowerShell | Linux / macOS sh |
|---|---|
| `npm --prefix scripts/readme-art ci` | `npm --prefix scripts/readme-art ci` |
| `npx --prefix scripts/readme-art playwright install chromium` | `npx --prefix scripts/readme-art playwright install chromium` |
| `node scripts/readme-art/build.mjs` | `node scripts/readme-art/build.mjs` |

The script resolves every path from its own location, so it works from any
folder on any OS. It opens `site/index.html` as a `file://` URL, aborts any
request that tries to leave the machine, and is deterministic: same inputs,
same outputs.

## What it makes

- `docs/readme/hero.jpg`: the hero, with the sticky sheet header hidden, at 1280 px.
- `docs/readme/route.svg`: the route map. Three real questions from the front
  page are plotted from your app, through the advising office, past numbered
  control points (ADVISOR's own tools and prompts, with the arguments the page
  sends) to the stack project that answers. The places and their positions, the
  questions, the steps and the destinations are all read out of
  `site/assets/advisor.js` and `site/index.html`, then checked: every tool
  against `src/Tools`, every prompt against `src/Module/CoreModule.php`, every
  handbook chapter against `data/handbook`, every project against
  `data/projects.json`. If the map and the source disagree, the build fails.
  Pure SVG and CSS, no script. Under `prefers-reduced-motion` it is the
  finished map, standing still.
- `docs/readme/gallery.jpg`: three phone crops from `site/screenshots/sections/`.
- `docs/readme/board.jpg`: the brand board, re-encoded at 1400 px.
- `docs/readme/cast/*.png`: one 96x96 mark per stack part, WayneUI on its
  green tile first.
- `docs/readme/badges/*.svg`: `php`, `licence` and `mcp sdk`, made locally by
  `badge-maker`.

Every file must stay at or under 300 KB, and no captured text may name an
internal host; the script prints each size and exits 1 if either rule breaks.

A good advisor keeps no secrets from the map: it reads the source, not
whatever the model remembers.
