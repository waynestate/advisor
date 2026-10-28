#!/usr/bin/env node
/* ============================================================================
   ADVISOR README art kit.

   Renders the README images from this repo's own static front page (site/),
   brand files and source, and writes them under docs/readme/. Nothing is
   drawn by hand: the hero and the gallery are screenshots of the real page,
   the cast is the existing marks resized (WayneUI composed on its green
   tile), the badges read their facts from composer.json, and the route map
   (docs/readme/route.svg) reads its places, questions, tools, arguments and
   destinations out of the front page's own route table (site/assets/advisor.js),
   then checks every tool against src/Tools, every chapter against data/handbook
   and every project against data/projects.json. If the map and the source
   disagree, the build fails. Ask the map; it does not make things up.

   Run from any folder (every path resolves from this file's own location):

     npm --prefix scripts/readme-art ci
     npx --prefix scripts/readme-art playwright install chromium
     node scripts/readme-art/build.mjs

   The page is opened as a file:// URL: no server, no network.
   ========================================================================== */

import { chromium } from "playwright";
import { makeBadge } from "badge-maker";
import sharp from "sharp";
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

/* ------------------------------------------------------------- paths, limits */

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDir, "..", "..");
const siteDir = path.join(repoRoot, "site");
const outDir = path.join(repoRoot, "docs", "readme");

const MAX_BYTES = 300 * 1024; // every file in docs/readme/ stays at or under this
const FORBIDDEN = /GitLab|gitlab\.example|citadel/i; // internal names never ship in art

// The page's own palette (site/assets/advisor.css :root).
const C = {
  green: "#0c5449",
  greenDeep: "#073a32",
  gold: "#ffcc33",
  goldSoft: "#ffe28a",
  maroon: "#8b2145",
  paper: "#f4f1ea",
  paperDeep: "#ebe5d8",
  paperHi: "#fbf9f4",
  ink: "#1c2725",
  inkMuted: "#4a5552",
};

const outputs = [];

function record(rel, buf) {
  const abs = path.join(outDir, rel);
  mkdirSync(path.dirname(abs), { recursive: true });
  writeFileSync(abs, buf);
  outputs.push({ rel, bytes: buf.length });
  console.log(`  ${rel}  ${buf.length} bytes`);
}

function fail(msg) {
  console.error(`FAIL: ${msg}`);
  process.exit(1);
}

function assertTextClean(text, what) {
  if (FORBIDDEN.test(text)) fail(`forbidden text matched ${FORBIDDEN} in ${what}`);
}

function esc(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

const read = (...p) => readFileSync(path.join(repoRoot, ...p), "utf8").replace(/\r\n/g, "\n");
const b64 = (buf) => Buffer.from(buf).toString("base64");

/* ------------------------------------------------------------- 1. cast strip */

// One 96x96 PNG per stack part (shown at 40 px in the README). The marks are
// copied and resized only, never redrawn. WayneUI is composed instead: its
// reverse mark (white frame, gold tab) set on a Wayne State green tile.
const CAST = [
  "creed",
  "proctor",
  "sesame",
  "helmet",
  "sourdough",
  "squire",
  "preflight",
  "pinstripe",
  "booster",
  "rollcall",
  "drawbridge",
  "advisor",
  "weld",
];

const castPng = {}; // name -> Buffer, reused by the route map

function castSource(name) {
  // ADVISOR's own mark is not in the shared marks folder; its avatar export is.
  if (name === "advisor") return path.join(siteDir, "brand", "advisor-avatar-256.png");
  return path.join(siteDir, "assets", "marks", `${name}.png`);
}

// The WayneUI tile: the reverse mark, traced from WayneUI's approved artwork,
// set on a green rounded tile. The traced paths are placed unchanged.
function wayneuiTileSvg() {
  const raw = readFileSync(path.join(scriptDir, "src", "wayneui-mark-reverse.svg"), "utf8");
  const open = raw.indexOf("<svg");
  const openEnd = raw.indexOf(">", open) + 1;
  const close = raw.lastIndexOf("</svg>");
  if (open < 0 || openEnd < 2 || close < openEnd) fail("wayneui-mark-reverse.svg: no <svg> element");
  const inner = raw.slice(openEnd, close).replace(/^\r?\n/, "");
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512" role="img" aria-label="WayneUI">
  <rect width="512" height="512" rx="80" ry="80" fill="#0C5449"/>
  <svg x="31.3" y="30.3" width="449.4" height="451.4" viewBox="0 0 1980 1989">
${inner}</svg>
</svg>
`;
}

async function buildCast() {
  console.log("cast:");
  const tile = Buffer.from(wayneuiTileSvg(), "utf8");
  const tilePng = await sharp(tile, { density: 300 })
    .resize(96, 96, { fit: "contain", background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .png({ palette: true, quality: 85, compressionLevel: 9 })
    .toBuffer();
  record("cast/wayneui.png", tilePng);
  for (const name of CAST) {
    const src = castSource(name);
    if (!existsSync(src)) fail(`missing cast source ${path.relative(repoRoot, src)}`);
    const png = await sharp(readFileSync(src))
      .resize(96, 96, { fit: "contain", background: { r: 0, g: 0, b: 0, alpha: 0 } })
      .png({ palette: true, quality: 85, compressionLevel: 9 })
      .toBuffer();
    castPng[name] = png;
    record(`cast/${name}.png`, png);
  }
}

/* ---------------------------------------------------------------- 2. badges */

// Facts are read from composer.json, never typed: a badge that lies sends
// someone down the wrong trail.
function buildBadges() {
  console.log("badges:");
  const composer = JSON.parse(read("composer.json"));
  const defs = [
    { key: "php", label: "php", message: composer.require.php },
    { key: "license", label: "licence", message: composer.license },
    { key: "mcp-sdk", label: "mcp sdk", message: composer.require["mcp/sdk"] },
  ];
  for (const d of defs) {
    if (!d.message) fail(`composer.json has no fact to badge for "${d.key}"`);
    const svg = makeBadge({
      label: d.label,
      message: d.message,
      labelColor: C.greenDeep,
      color: C.gold,
      style: "flat-square",
    });
    record(`badges/${d.key}.svg`, Buffer.from(svg, "utf8"));
  }
}

/* ------------------------------------- 3. the route table, from the page JS */

// The front page's own data: PLACES (positions of every stack project on the
// map) and ROUTES (which question goes through which tools to which project),
// read out of site/assets/advisor.js. The ROUTES block is evaluated in an
// isolated function with no access to anything else, so what the page would
// send is what the map shows.
function readRoutes() {
  const js = read("site", "assets", "advisor.js");

  // Places: `id: { name: "X", alias: "Y", x: 1, y: 2, label: "r", ...}`.
  const places = {};
  const placeRe = /^\s*"?([a-z-]+)"?:\s*\{\s*name:\s*"([^"]+)",\s*alias:\s*"([^"]+)",\s*x:\s*(\d+),\s*y:\s*(\d+),\s*label:\s*"([lrb])"/gm;
  for (const m of js.matchAll(placeRe)) {
    places[m[1]] = { name: m[2], alias: m[3], x: +m[4], y: +m[5], label: m[6] };
  }
  if (Object.keys(places).length !== 13) fail(`expected 13 places in advisor.js, read ${Object.keys(places).length}`);
  const start = js.match(/var START = \{ x: (\d+), y: (\d+) \}/);
  const hub = js.match(/var HUB = \{ x: (\d+), y: (\d+) \}/);
  if (!start || !hub) fail("advisor.js: cannot read START or HUB");

  // Routes: from `function componentIn` through the end of `pick`.
  const from = js.indexOf("function componentIn");
  const pickAt = js.indexOf("function pick(");
  const pickEnd = js.indexOf("\n  }\n", pickAt) + 5;
  if (from < 0 || pickAt < 0 || pickEnd < pickAt) fail("advisor.js: cannot find the route table");
  const block = js.slice(from, pickEnd);
  // eslint-disable-next-line no-new-func
  const make = new Function("policyAnswer", `${block}\nreturn { ROUTES, pick };`);
  const { ROUTES, pick } = make(() => ({}));

  // The three questions on the map are chips on the front page itself.
  const html = read("site", "index.html");
  const chips = [...html.matchAll(/data-q="([^"]+)"/g)].map((m) => m[1]);
  const wanted = [
    ["policy", "Which PHP can I build on?"],
    ["headers", "Which security headers do I need?"],
    ["upgrade", "How do I get off PHP 8.2?"],
  ];
  const routes = [];
  for (const [id, question] of wanted) {
    const inPage = html.includes(`value="${question}"`) || chips.includes(question);
    if (!inPage) fail(`the front page no longer offers the question "${question}"`);
    const picked = pick(question).id;
    if (picked !== id) fail(`the page sends "${question}" to route ${picked}, not ${id}`);
    const r = ROUTES[id];
    const steps = r.steps(question.toLowerCase());
    routes.push({ id, question, steps, dest: r.dest, detail: r.detail });
  }
  return { places, start: { x: +start[1], y: +start[2] }, hub: { x: +hub[1], y: +hub[2] }, routes, html };
}

// Every tool, prompt, chapter and project on the map must exist in the repo.
function checkRoutes({ routes, places }) {
  const toolNames = new Set();
  for (const f of readdirSync(path.join(repoRoot, "src", "Tools"))) {
    for (const m of read("src", "Tools", f).matchAll(/name:\s*'([a-z_]+)'/g)) toolNames.add(m[1]);
  }
  const core = read("src", "Module", "CoreModule.php");
  const promptNames = new Set([...core.matchAll(/new PromptDefinition\(\s*name:\s*'([a-z_]+)'/g)].map((m) => m[1]));
  const projects = new Set(JSON.parse(read("data", "projects.json")).projects.map((p) => p.id));
  const chapters = new Set(
    readdirSync(path.join(repoRoot, "data", "handbook"))
      .filter((f) => f.endsWith(".md"))
      .map((f) => f.replace(/\.md$/, "")),
  );
  for (const r of routes) {
    if (!projects.has(r.dest) || !places[r.dest]) fail(`route ${r.id}: unknown destination ${r.dest}`);
    for (const s of r.steps) {
      if (s.tool && !toolNames.has(s.tool)) fail(`route ${r.id}: tool ${s.tool} is not in src/Tools`);
      if (s.prompt && !promptNames.has(s.prompt)) fail(`route ${r.id}: prompt ${s.prompt} is not in CoreModule`);
      const proj = s.args && s.args.match(/project:\s*([a-z-]+)/);
      if (proj && !projects.has(proj[1])) fail(`route ${r.id}: project ${proj[1]} is not in data/projects.json`);
      const chap = s.args && s.args.match(/chapter:\s*([a-z-]+)/);
      if (chap && !chapters.has(chap[1])) fail(`route ${r.id}: chapter ${chap[1]} is not in data/handbook`);
    }
  }
}

/* ---------------------------------------------------------- 4. the route SVG */

const FONT_ITALIC = readFileSync(path.join(siteDir, "assets", "fonts", "fraunces-latin-600-italic.woff2"));
const FONT_BOLD = readFileSync(path.join(siteDir, "assets", "fonts", "fraunces-latin-800-normal.woff2"));
const MONO = `ui-monospace, "Cascadia Mono", "SF Mono", Menlo, Consolas, "Courier New", monospace`;

const quad = (p0, c, p2, t) => ({
  x: (1 - t) ** 2 * p0.x + 2 * t * (1 - t) * c.x + t ** 2 * p2.x,
  y: (1 - t) ** 2 * p0.y + 2 * t * (1 - t) * c.y + t ** 2 * p2.y,
});
const r1 = (n) => Math.round(n * 10) / 10;

// How each route leaves the advising office. These are layout choices only;
// every name, argument and destination comes from the page's route table.
const LAYOUT = {
  policy: { ctrl: { x: 600, y: 215 }, ts: [0.5], labels: [{ dx: -34, dy: -2, side: "l" }] },
  headers: {
    ctrl: { x: 570, y: 520 },
    ts: [0.33, 0.68],
    labels: [
      { dx: 40, dy: 16, side: "r" },
      { dx: 40, dy: 10, side: "r" },
    ],
  },
  upgrade: {
    ctrl: { x: 860, y: 340 },
    ts: [0.17, 0.38, 0.6, 0.8],
    labels: [
      { dx: -22, dy: 56, side: "r" },
      { dx: -2, dy: 74, side: "r" },
      { dx: -12, dy: 62, side: "r" },
      { dx: -28, dy: 16, side: "l" },
    ],
  },
};

const CYCLE = 32; // seconds per loop
// When each route starts drawing (seconds into the loop) and how long it takes.
const TIMING = { policy: [1.2, 3.2], headers: [10.5, 4], upgrade: [19.5, 6.5] };

// Long arguments wrap at their commas, so a plate stays narrow.
function wrapArgs(text, max = 26, words = false) {
  if (words && text.length > max && !text.includes(", ")) {
    const words = text.split(" ");
    const out = [];
    let c = "";
    for (const w of words) {
      if (c && (c + " " + w).length > max) {
        out.push(c);
        c = w;
      } else c = c ? c + " " + w : w;
    }
    out.push(c);
    return out;
  }
  if (text.length <= max) return [text];
  const parts = text.split(", ");
  const lines = [];
  let cur = "";
  for (const p of parts) {
    const next = cur ? cur + ", " + p : p;
    if (cur && next.length > max) {
      lines.push(cur + ",");
      cur = p;
    } else cur = next;
  }
  if (cur) lines.push(cur);
  return lines;
}

function stepLabel(s) {
  if (s.prompt) return { head: s.prompt, sub: ["prompt"] };
  return { head: s.tool, sub: s.args ? wrapArgs(s.args) : [] };
}

function buildRouteSvg({ places, start, hub, routes, html }) {
  const W = 1200;
  const MAPH = 700;
  const STRIP = 178;
  const H = MAPH + STRIP;

  const terrain = readFileSync(path.join(siteDir, "assets", "terrain-map.svg"));

  const defs = [];
  const layers = { terrain: [], places: [], routes: [], marks: [], controls: [], strip: [] };
  const css = [];
  const pct = (sec) => r1((sec / CYCLE) * 100);

  // Keyframes: hidden until `at`, in by `at+len`, held, then the whole map fades
  // out near the end of the loop and the loop starts again. (The global fade
  // below hides the reset.)
  function reveal(name, at, len, extra = {}) {
    const a = pct(at);
    const b = pct(at + len);
    const from = Object.entries(extra.from || { opacity: 0 }).map(([k, v]) => `${k}:${v}`).join(";");
    const to = Object.entries(extra.to || { opacity: 1 }).map(([k, v]) => `${k}:${v}`).join(";");
    css.push(`@keyframes ${name}{0%,${a}%{${from}}${b}%,100%{${to}}}`);
  }
  const anim = (cls, name) => css.push(`.${cls}{animation:${name} ${CYCLE}s linear infinite both}`);

  // Terrain: the page's own contours, behind everything, at full strength.
  layers.terrain.push(
    `<image href="data:image/svg+xml;base64,${b64(terrain)}" x="0" y="0" width="${W}" height="${MAPH}" preserveAspectRatio="none"/>`,
  );
  // Names on the sheet's water and hills, as the page letters them.
  for (const m of html.matchAll(/<text class="map__(water|hill)" x="(\d+)" y="(\d+)" text-anchor="middle">([^<]+)<\/text>/g)) {
    layers.terrain.push(
      `<text class="geo geo-${m[1]}" x="${m[2]}" y="${m[3]}" text-anchor="middle">${esc(m[4])}</text>`,
    );
  }

  // The destinations, and where each route touches them.
  const dests = new Set(routes.map((r) => r.dest));
  const markFor = { creed: "creed", helmet: "helmet", booster: "booster" };

  // Quiet places: every other project is a small dot and a name.
  for (const [id, p] of Object.entries(places)) {
    if (dests.has(id)) continue;
    const anchor = p.label === "l" ? "end" : p.label === "b" ? "middle" : "start";
    const tx = p.label === "l" ? p.x - 14 : p.label === "b" ? p.x : p.x + 14;
    const ty = p.label === "b" ? p.y + 30 : p.y + 5;
    layers.places.push(
      `<g class="quiet"><circle cx="${p.x}" cy="${p.y}" r="7"/><text x="${tx}" y="${ty}" text-anchor="${anchor}">${esc(p.name)}</text></g>`,
    );
  }

  // The advising office. The mark is ADVISOR's own avatar, placed unchanged.
  layers.marks.push(
    `<g transform="translate(${hub.x} ${hub.y})">
      <circle r="54" fill="${C.paperHi}" fill-opacity=".72"/>
      <circle r="47" fill="none" stroke="${C.green}" stroke-width="3"/>
      <clipPath id="clip-hub"><rect x="-38" y="-38" width="76" height="76" rx="18"/></clipPath>
      <image href="data:image/png;base64,${b64(castPng.advisor)}" x="-38" y="-38" width="76" height="76" clip-path="url(#clip-hub)"/>
      <g class="plate" transform="translate(-60 -4)"><rect x="-150" y="-24" width="150" height="52" rx="5"/>
        <text class="p-name" x="-10" y="-2" text-anchor="end">ADVISOR</text>
        <text class="p-sub" x="-10" y="18" text-anchor="end">Advising Office</text></g>
    </g>`,
  );

  // Start: an orienteering triangle (the page's own geometry) and a gold dot.
  layers.marks.push(
    `<g transform="translate(${start.x} ${start.y})">
      <circle r="22" fill="${C.paperHi}" fill-opacity=".7"/>
      <path d="M0 -24 L21 13 L-21 13 Z" fill="none" stroke="${C.maroon}" stroke-width="4" stroke-linejoin="round"/>
      <circle r="6" fill="${C.gold}" stroke="${C.maroon}" stroke-width="2"/>
      <g class="plate" transform="translate(30 8)"><rect x="0" y="-22" width="170" height="52" rx="5"/>
        <text class="p-sub" x="12" y="-2">Start</text>
        <text class="p-name" x="12" y="20">your app</text></g>
    </g>`,
  );

  // North arrow and scale, the two things every sheet owes you (the page's own paths).
  layers.places.push(
    `<g transform="translate(52 66)" class="furn"><circle r="26"/><path d="M0 -22 L7 4 L0 0 L-7 4 Z"/><text y="-32" text-anchor="middle">N</text></g>
     <g transform="translate(880 44)" class="furn"><path d="M0 0 H200 M0 -6 V6 M100 -4 V4 M200 -6 V6" fill="none"/>
       <text x="0" y="-14">0</text><text x="100" y="-14" text-anchor="middle">1</text><text x="200" y="-14" text-anchor="end">2 tool calls</text></g>`,
  );

  // Routes.
  const questionCols = [];
  routes.forEach((r, ri) => {
    const lay = LAYOUT[r.id];
    const dest = places[r.dest];
    const [t0, dur] = TIMING[r.id];
    const bend = { x: 360, y: 540 };
    const leg1 = `M${start.x} ${start.y} Q${bend.x} ${bend.y} ${hub.x} ${hub.y}`;
    const leg2 = `M${hub.x} ${hub.y} Q${lay.ctrl.x} ${lay.ctrl.y} ${dest.x} ${dest.y}`;
    // The whole route as one path, so the dots keep an even rhythm and the
    // reveal is a single stroke.
    const d = `${leg1} Q${lay.ctrl.x} ${lay.ctrl.y} ${dest.x} ${dest.y}`.replace(/ Q(\d+) (\d+) (\d+) (\d+) Q/, " Q$1 $2 $3 $4 Q");
    const full = `${leg1.replace(/^M/, "M")} ${leg2.replace(/^M\d+ \d+ /, "")}`;
    const maskId = `mk-${r.id}`;
    defs.push(
      `<mask id="${maskId}" maskUnits="userSpaceOnUse" x="0" y="0" width="${W}" height="${MAPH}"><path class="mask-line m-${r.id}" d="${full}" pathLength="1"/></mask>`,
    );
    reveal(`kf-m-${r.id}`, t0, dur, { from: { "stroke-dashoffset": 1 }, to: { "stroke-dashoffset": 0 } });
    anim(`m-${r.id}`, `kf-m-${r.id}`);
    layers.routes.push(`<path class="route" d="${full}" mask="url(#${maskId})"/>`);

    // Controls: 1 is the advising office; each step after it is a control
    // point on the way to the answer.
    const ctrls = r.steps.map((s, si) => {
      const t = lay.ts[si];
      const pt = quad(hub, lay.ctrl, dest, t);
      return { n: si + 2, s, pt: { x: r1(pt.x), y: r1(pt.y) }, lab: lay.labels[si] };
    });
    if (ctrls.length !== lay.ts.length) fail(`layout for ${r.id} has ${lay.ts.length} controls, route has ${ctrls.length}`);

    ctrls.forEach((c, ci) => {
      const cls = `c-${r.id}-${ci}`;
      const frac = 0.28 + (0.62 * (ci + 1)) / (ctrls.length + 1);
      const at = t0 + dur * frac;
      reveal(`kf-${cls}`, at, 0.7, { from: { opacity: 0, transform: "scale(.4)" }, to: { opacity: 1, transform: "scale(1)" } });
      anim(cls, `kf-${cls}`);
      const lab = stepLabel(c.s);
      const w1 = lab.head.length * 9 + 22;
      const w2 = Math.max(0, ...lab.sub.map((l) => l.length * 7.9 + 22));
      const w = Math.max(w1, w2, 70);
      const hgt = 14 + 18 + lab.sub.length * 17 + (lab.sub.length ? 2 : -14);
      const lx = c.lab.side === "l" ? c.pt.x + c.lab.dx - w : c.pt.x + c.lab.dx;
      const ly = c.pt.y + c.lab.dy - hgt / 2;
      layers.controls.push(
        `<g class="${cls}" style="transform-box:fill-box;transform-origin:center">
          <circle cx="${c.pt.x}" cy="${c.pt.y}" r="19" class="cring"/>
          <text x="${c.pt.x}" y="${c.pt.y + 6}" text-anchor="middle" class="cnum">${c.n}</text>
          <rect class="lplate" x="${r1(lx)}" y="${r1(ly)}" width="${r1(w)}" height="${hgt}" rx="5"/>
          <text class="tool" x="${r1(lx + 11)}" y="${r1(ly + 21)}">${esc(lab.head)}</text>
          ${lab.sub.map((l, i) => `<text class="arg" x="${r1(lx + 11)}" y="${r1(ly + 40 + i * 17)}">${esc(l)}</text>`).join("")}
        </g>`,
      );
    });

    // Finish: the double circle round the destination, its mark, and its label.
    const fin = `f-${r.id}`;
    reveal(`kf-${fin}`, t0 + dur * 0.92, 0.9, { from: { opacity: 0 }, to: { opacity: 1 } });
    anim(fin, `kf-${fin}`);
    const dp = places[r.dest];
    const dn = dp.name;
    const lside = dp.label;
    const tx = lside === "b" ? dp.x : lside === "l" ? dp.x - 44 : dp.x + 44;
    const anchor = lside === "b" ? "middle" : lside === "l" ? "end" : "start";
    const ty = lside === "b" ? dp.y + 64 : dp.y - 3;
    layers.controls.push(
      `<g class="${fin}"><circle cx="${dp.x}" cy="${dp.y}" r="38" class="finish"/><circle cx="${dp.x}" cy="${dp.y}" r="30" class="finish"/>
        <text class="d-name" x="${tx}" y="${ty}" text-anchor="${anchor}">${esc(dn)}</text>
        ${wrapArgs(r.detail, 22, true).map((l, i) => `<text class="d-alias" x="${tx}" y="${ty + 19 + i * 19}" text-anchor="${anchor}">${esc(l.replace(/,$/, "") + (i < wrapArgs(r.detail, 22, true).length - 1 ? "," : ""))}</text>`).join("")}</g>`,
    );
    if (!markFor[r.dest]) fail(`no mark for destination ${r.dest}`);
    const cid = `clip-d-${r.dest}`;
    defs.push(`<clipPath id="${cid}"><rect x="${dp.x - 23}" y="${dp.y - 23}" width="46" height="46" rx="11"/></clipPath>`);
    layers.marks.push(
      `<g><circle cx="${dp.x}" cy="${dp.y}" r="30" fill="${C.paperHi}" fill-opacity=".8" stroke="${C.green}" stroke-width="2"/>
        <image href="data:image/png;base64,${b64(castPng[markFor[r.dest]])}" x="${dp.x - 23}" y="${dp.y - 23}" width="46" height="46" clip-path="url(#${cid})"/></g>`,
    );

    // The question, in the control-card strip under the map.
    const qcls = `q-${r.id}`;
    reveal(`kf-${qcls}`, t0 - 0.2, 0.8, { from: { opacity: 0 }, to: { opacity: 1 } });
    anim(qcls, `kf-${qcls}`);
    const colW = W / 3;
    const cx0 = ri * colW + 30;
    const viaLines = [];
    let cur = "";
    for (const s of r.steps.map((s) => s.tool || s.prompt)) {
      const next = cur ? cur + " > " + s : s;
      if (cur && next.length > 40) {
        viaLines.push(cur + " >");
        cur = s;
      } else cur = next;
    }
    viaLines.push(cur);
    questionCols.push(
      `<g class="${qcls}"><text class="q-num" x="${cx0}" y="${MAPH + 40}">Route ${ri + 1}</text>
        <text class="q-text" x="${cx0}" y="${MAPH + 72}">“${esc(r.question)}”</text>
        ${viaLines.map((l, i) => `<text class="q-via" x="${cx0}" y="${MAPH + 98 + i * 17}">${esc(l)}</text>`).join("")}
        ${wrapArgs(`answer from ${dn}: ${r.detail}`, 44, true).map((l, i) => `<text class="q-to" x="${cx0}" y="${MAPH + 146 + i * 20}">${esc(l)}</text>`).join("")}</g>`,
    );
    void d;
  });
  layers.strip.push(...questionCols);

  // The whole map fades out near the end of each loop, so the reset is hidden.
  css.push(`@keyframes kf-all{0%{opacity:0}1.5%,90%{opacity:1}96%,100%{opacity:0}}`);
  css.push(`.live{animation:kf-all ${CYCLE}s linear infinite both}`);
  css.push(`@media (prefers-reduced-motion: reduce){.live,.live *,.mask-line{animation:none !important}}`);

  const style = `
@font-face{font-family:"Fraunces";font-style:italic;font-weight:600;src:url(data:font/woff2;base64,${b64(FONT_ITALIC)}) format("woff2")}
@font-face{font-family:"Fraunces";font-style:normal;font-weight:800;src:url(data:font/woff2;base64,${b64(FONT_BOLD)}) format("woff2")}
.f{font-family:Fraunces,"Iowan Old Style",Georgia,serif}
text{font-family:Fraunces,"Iowan Old Style",Georgia,serif}
.geo{font-style:italic;font-weight:600;fill:${C.green};fill-opacity:.55;font-size:19px}
.geo-hill{letter-spacing:.06em}
.quiet circle{fill:${C.paperHi};stroke:${C.green};stroke-width:2.5}
.quiet text{font-style:italic;font-weight:600;font-size:17px;fill:${C.ink};paint-order:stroke;stroke:${C.paperHi};stroke-width:4px;stroke-linejoin:round}
.furn circle,.furn path{stroke:${C.green};stroke-width:2.5;fill:none}
.furn path:nth-child(2){fill:${C.green}}
.furn text{font-style:italic;font-weight:600;font-size:15px;fill:${C.green}}
.plate rect{fill:${C.paperHi};fill-opacity:.94;stroke:${C.green};stroke-opacity:.5}
.p-name{font-weight:800;font-size:22px;fill:${C.ink}}
.p-sub{font-style:italic;font-weight:600;font-size:16px;fill:${C.inkMuted}}
.route{fill:none;stroke:${C.maroon};stroke-width:5.5;stroke-linecap:round;stroke-dasharray:.1 12}
.mask-line{fill:none;stroke:#fff;stroke-width:20;stroke-linecap:round;stroke-dasharray:1;stroke-dashoffset:0}
.cring{fill:${C.paperHi};fill-opacity:.9;stroke:${C.maroon};stroke-width:4}
.cnum{font-weight:800;font-size:19px;fill:${C.maroon}}
.lplate{fill:${C.paperHi};fill-opacity:.96;stroke:${C.maroon};stroke-opacity:.55;stroke-width:1.5}
.tool{font-family:${MONO};font-weight:700;font-size:15px;fill:${C.ink}}
.arg{font-family:${MONO};font-size:13px;fill:${C.inkMuted}}
.finish{fill:none;stroke:${C.maroon};stroke-width:4}
.d-name{font-weight:800;font-size:22px;fill:${C.ink};paint-order:stroke;stroke:${C.paperHi};stroke-width:6px;stroke-linejoin:round}
.d-alias{font-style:italic;font-weight:600;font-size:16px;fill:${C.maroon};paint-order:stroke;stroke:${C.paperHi};stroke-width:5px;stroke-linejoin:round}
.q-num{font-style:italic;font-weight:600;font-size:16px;fill:${C.goldSoft};letter-spacing:.04em}
.q-text{font-weight:800;font-size:20px;fill:#fff}
.q-via{font-family:${MONO};font-size:13px;fill:${C.goldSoft}}
.q-to{font-style:italic;font-weight:600;font-size:16px;fill:#cfe0da}
${css.join("\n")}`;

  const title = "ADVISOR plots a route from your app to the stack project that answers";
  const desc =
    "A field map of the Wayne State app stack. Three questions are plotted from your app, through the ADVISOR advising office, past numbered control points that are ADVISOR's real tools and prompts, to the project the answer comes from. " +
    routes
      .map(
        (r, i) =>
          `Route ${i + 1}: “${r.question}” goes through ${r.steps.map((s) => (s.tool ? `${s.tool}${s.args ? " (" + s.args + ")" : ""}` : `the ${s.prompt} prompt`)).join(", then ")}, and finishes at ${places[r.dest].name}.`,
      )
      .join(" ");

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="${W}" height="${H}" role="img" aria-labelledby="t d">
<title id="t">${esc(title)}</title>
<desc id="d">${esc(desc)}</desc>
<style>${style}</style>
<defs>
<clipPath id="card"><rect width="${W}" height="${H}" rx="26"/></clipPath>
${defs.join("\n")}
</defs>
<g clip-path="url(#card)">
<rect width="${W}" height="${H}" fill="${C.paper}"/>
${layers.terrain.join("\n")}
<rect y="${MAPH}" width="${W}" height="${STRIP}" fill="${C.greenDeep}"/>
<rect y="${MAPH}" width="${W}" height="4" fill="${C.gold}"/>
${layers.places.join("\n")}
<g class="live">
${layers.routes.join("\n")}
</g>
${layers.marks.join("\n")}
<g class="live">
${layers.controls.join("\n")}
${layers.strip.join("\n")}
</g>
</g>
<rect x="1" y="1" width="${W - 2}" height="${H - 2}" rx="25" fill="none" stroke="${C.green}" stroke-opacity=".5" stroke-width="2"/>
</svg>
`;
  assertTextClean(svg.replace(/base64,[A-Za-z0-9+/=]+/g, ""), "route.svg");
  if (/<script|href="http|url\(http/i.test(svg.replace(/xmlns="[^"]*"/g, ""))) fail("route.svg has a script or an external reference");
  record("route.svg", Buffer.from(svg, "utf8"));
}

/* ------------------------------------------------------------- 5. the page */

async function openPage(browser) {
  const context = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    deviceScaleFactor: 2,
    reducedMotion: "reduce", // the page is already drawn: nothing mid-reveal
  });
  // Anything that is not a local file is refused: the build makes no network calls.
  await context.route(/^(?!file:|data:)/, (route) => route.abort());
  const page = await context.newPage();
  await page.goto(pathToFileURL(path.join(siteDir, "index.html")).href, { waitUntil: "networkidle" });
  await page.evaluate(() => document.fonts.ready);
  return { context, page };
}

async function buildHero(page) {
  console.log("hero:");
  await page.addStyleTag({ content: ".sheet-head, .toast { display: none !important; }" });
  assertTextClean(await page.locator(".hero").innerText(), "hero");
  const png = await page.locator(".hero").screenshot();
  const jpg = await sharp(png).resize({ width: 1280 }).jpeg({ quality: 82, mozjpeg: true }).toBuffer();
  record("hero.jpg", jpg);
}

// A contact sheet of three existing mobile crops, set on the page's paper.
async function buildGallery(page) {
  console.log("gallery:");
  const crops = ["mobile-03-ask.png", "mobile-05-legend.png", "mobile-07-directions.png"];
  for (const c of crops) {
    if (!existsSync(path.join(siteDir, "screenshots", "sections", c))) fail(`missing crop ${c}`);
  }
  const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  html, body { margin: 0; padding: 0; }
  .sheet { width: fit-content; background: ${C.paperDeep}; padding: 40px; display: flex; gap: 32px; }
  img {
    box-sizing: border-box;
    display: block;
    width: 360px;
    height: 640px;
    object-fit: cover;
    object-position: top;
    border-radius: 14px;
    border: 1px solid rgba(12, 84, 73, .35);
  }
</style>
</head>
<body>
<div class="sheet">
${crops.map((c) => `  <img src="${pathToFileURL(path.join(siteDir, "screenshots", "sections", c)).href}" alt="">`).join("\n")}
</div>
</body>
</html>`;
  await page.setContent(html, { waitUntil: "networkidle" });
  const png = await page.locator(".sheet").screenshot();
  const jpg = await sharp(png).resize({ width: 1280 }).jpeg({ quality: 82, mozjpeg: true }).toBuffer();
  record("gallery.jpg", jpg);
}

async function buildBoard() {
  console.log("board:");
  const src = path.join(siteDir, "brand", "advisor-board.png");
  if (!existsSync(src)) fail("site/brand/advisor-board.png is missing");
  let q = 82;
  let jpg = await sharp(src).resize({ width: 1400 }).jpeg({ quality: q, mozjpeg: true }).toBuffer();
  while (jpg.length > MAX_BYTES && q > 50) {
    q -= 6;
    jpg = await sharp(src).resize({ width: 1400 }).jpeg({ quality: q, mozjpeg: true }).toBuffer();
  }
  record("board.jpg", jpg);
}

/* -------------------------------------------------------------------- main */

async function main() {
  const data = readRoutes();
  checkRoutes(data);
  await buildCast();
  buildBadges();
  await buildBoard();
  buildRouteSvg(data);

  const browser = await chromium.launch();
  try {
    const { context, page } = await openPage(browser);
    await buildHero(page);
    await buildGallery(page);
    await context.close();
  } finally {
    await browser.close();
  }

  let over = false;
  for (const { rel, bytes } of outputs) {
    if (bytes > MAX_BYTES) {
      console.error(`FAIL: ${rel} is ${bytes} bytes (limit ${MAX_BYTES})`);
      over = true;
    }
  }
  if (over) process.exit(1);
  console.log(`done: ${outputs.length} files, all at or under ${MAX_BYTES} bytes`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
