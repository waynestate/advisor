#!/usr/bin/env node
/* ============================================================================
   ADVISOR accessibility gate.

   Loads every HTML page under site/ (except site/assets/) in a real browser
   and fails on any axe-core violation, in light and dark themes, at phone and
   desktop width, plus one reduced-motion pass. Follows WayneUI's gate
   (@axe-core/playwright), kept small.

   Run from anywhere (every path resolves from this file's own location):

     npm --prefix scripts/a11y install --no-audit --no-fund
     npx --prefix scripts/a11y playwright install chromium
     node scripts/a11y/check.mjs

   Exits 0 when every page passes, 1 on any violation or failed assertion.
   Prints one line per page and mode; never prints page text.
   ========================================================================== */

import http from "node:http";
import { readFile } from "node:fs/promises";
import { readdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { chromium } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDir, "..", "..");
const siteDir = path.join(repoRoot, "site");

/* ------------------------------------------------------------ static server */

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".woff2": "font/woff2",
  ".json": "application/json; charset=utf-8",
  ".txt": "text/plain; charset=utf-8",
  ".md": "text/markdown; charset=utf-8",
  ".xml": "application/xml; charset=utf-8",
  ".ico": "image/x-icon",
};

// A tiny static file server on an ephemeral port, serving site/ only.
function startServer(root) {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      let pathname;
      try {
        pathname = decodeURIComponent(new URL(req.url, "http://localhost").pathname);
      } catch {
        res.writeHead(400).end();
        return;
      }
      // Reject any traversal out of the served folder.
      if (pathname.split("/").some((seg) => seg === "..")) {
        res.writeHead(400).end();
        return;
      }
      if (pathname === "/") pathname = "/index.html";
      const filePath = path.normalize(path.join(root, pathname));
      if (filePath !== root && !filePath.startsWith(root + path.sep)) {
        res.writeHead(400).end();
        return;
      }
      readFile(filePath).then(
        (data) => {
          const ext = path.extname(filePath).toLowerCase();
          res.writeHead(200, { "content-type": MIME[ext] ?? "application/octet-stream" });
          res.end(data);
        },
        () => res.writeHead(404).end(),
      );
    });
    server.on("error", reject);
    server.listen(0, "127.0.0.1", () => resolve({ server, port: server.address().port }));
  });
}

/* ------------------------------------------------------------------ pages */

// Every *.html under site/, except anything under site/assets/.
function findHtmlPages(dir, out = []) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      const rel = path.relative(siteDir, full);
      if (rel === "assets" || rel.startsWith("assets" + path.sep)) continue;
      findHtmlPages(full, out);
    } else if (entry.name.toLowerCase().endsWith(".html")) {
      out.push(full);
    }
  }
  return out;
}

/* ------------------------------------------------------------------- modes */

// WCAG tags only; best-practice is deliberately left out.
const AXE_TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

const MODES = [
  { label: "phone-light", width: 390, height: 844, colorScheme: "light", reducedMotion: "no-preference" },
  { label: "phone-dark", width: 390, height: 844, colorScheme: "dark", reducedMotion: "no-preference" },
  { label: "desktop-light", width: 1440, height: 900, colorScheme: "light", reducedMotion: "no-preference" },
  { label: "desktop-dark", width: 1440, height: 900, colorScheme: "dark", reducedMotion: "no-preference" },
  { label: "desktop-light-reduced-motion", width: 1440, height: 900, colorScheme: "light", reducedMotion: "reduce" },
];

/* -------------------------------------------------------------------- main */

async function main() {
  const pages = findHtmlPages(siteDir).sort();
  if (pages.length === 0) {
    console.error(`FAIL: no HTML pages found under ${siteDir}`);
    process.exit(1);
  }

  const { server, port } = await startServer(siteDir);
  const base = `http://127.0.0.1:${port}`;
  const browser = await chromium.launch();

  let checks = 0;
  let failures = 0;

  try {
    for (const pagePath of pages) {
      const rel = path.relative(siteDir, pagePath).split(path.sep).join("/");
      const urlPath = "/" + path.relative(siteDir, pagePath).split(path.sep).map(encodeURIComponent).join("/");
      const url = `${base}${urlPath}`;

      for (let i = 0; i < MODES.length; i++) {
        const mode = MODES[i];
        const context = await browser.newContext({
          viewport: { width: mode.width, height: mode.height },
          colorScheme: mode.colorScheme,
          reducedMotion: mode.reducedMotion,
        });
        const page = await context.newPage();
        await page.goto(url, { waitUntil: "load" });
        await page.waitForTimeout(300);
        // Open every disclosure (the route card's field book loads its readouts on open), so what
        // hides behind a click is checked too, at both widths and in both themes.
        const disclosures = await page.evaluate(() => {
          const all = document.querySelectorAll("details:not([hidden])");
          all.forEach((d) => { d.open = true; });
          return all.length;
        });
        if (disclosures > 0) await page.waitForTimeout(700);
        checks++;

        const problems = [];

        // Per-page assertions (mode-independent ones checked once, on the first pass).
        const dom = await page.evaluate(() => ({
          scrollWidth: document.documentElement.scrollWidth,
          innerWidth: window.innerWidth,
          h1: document.querySelectorAll("h1").length,
          lang: document.documentElement.getAttribute("lang"),
          title: document.title,
        }));
        if (mode.width === 390 && dom.scrollWidth > dom.innerWidth) {
          problems.push("horizontal-scroll");
        }
        if (i === 0) {
          if (dom.h1 !== 1) problems.push(`h1-count(${dom.h1})`);
          if (!dom.lang) problems.push("missing-lang");
          if (!dom.title || !dom.title.trim()) problems.push("empty-title");
        }

        // axe, WCAG tags only.
        const results = await new AxeBuilder({ page }).withTags(AXE_TAGS).analyze();
        const byRule = {};
        for (const v of results.violations) {
          byRule[v.id] = (byRule[v.id] ?? 0) + v.nodes.length;
        }
        for (const [id, n] of Object.entries(byRule)) {
          problems.push(`${id} (${n})`);
        }

        if (problems.length > 0) {
          failures++;
          console.log(`FAIL ${rel} ${mode.label}: ${problems.join(", ")}`);
        } else {
          console.log(`ok ${rel} ${mode.label}`);
        }

        await context.close();
      }
    }
  } finally {
    await browser.close();
    await new Promise((r) => server.close(r));
  }

  console.log("");
  console.log(`${pages.length} pages, ${checks} checks, ${failures} failing`);
  console.log(failures > 0 ? "FAIL" : "ok");
  process.exit(failures > 0 ? 1 : 0);
}

main().catch((err) => {
  console.error(err && err.message ? err.message : String(err));
  process.exit(1);
});
