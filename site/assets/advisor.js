/* ============================================================================
   ADVISOR front page: the field map.

   1. compass rose ticks       4. routes: which question goes where
   2. copy buttons, toast      5. plotting a route (the magic)
   3. the map's places         6. reveals and small delights

   No framework, no build. With prefers-reduced-motion the same code runs, but
   routes appear already drawn: html.motion is missing, so no transitions.
   ========================================================================== */

(function () {
  "use strict";

  var SVGNS = "http://www.w3.org/2000/svg";
  var motion = document.documentElement.classList.contains("motion");

  function svgEl(name, attrs, parent) {
    var el = document.createElementNS(SVGNS, name);
    for (var k in attrs) if (Object.prototype.hasOwnProperty.call(attrs, k)) el.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(el);
    return el;
  }

  function el(name, cls, text) {
    var e = document.createElement(name);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  /* ------------------------------------------------- 1. compass rose ticks */

  var ticks = document.querySelector(".rose__ticks");
  if (ticks) {
    for (var t = 0; t < 72; t++) {
      var a = (t / 72) * Math.PI * 2;
      var major = t % 9 === 0;
      var r1 = major ? 118 : 124, r2 = 132;
      svgEl("line", {
        x1: (Math.sin(a) * r1).toFixed(1), y1: (-Math.cos(a) * r1).toFixed(1),
        x2: (Math.sin(a) * r2).toFixed(1), y2: (-Math.cos(a) * r2).toFixed(1),
        "class": major ? "major" : ""
      }, ticks);
    }
  }

  /* ------------------------------------------------- 2. copy buttons, toast */

  var toast = document.querySelector(".toast");
  var toastTimer = 0;
  function say(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add("is-on");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.classList.remove("is-on"); }, 2600);
  }

  document.querySelectorAll("[data-copy]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var text = btn.getAttribute("data-copy");
      var label = btn.querySelector(".copy-btn__label");
      var done = function () {
        if (label) label.textContent = "Copied";
        say("Copied. Your advisor has added it to your transcript.");
        setTimeout(function () { if (label) label.textContent = "Copy"; }, 1800);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () { say("Copy blocked here; select the command instead."); });
      } else {
        say("Copy blocked here; select the command instead.");
      }
    });
  });

  /* ---------------------------------------------------- 3. the map's places */

  // Positions are in the map's own units (viewBox 1200 x 700). Every place is
  // a stack project from ADVISOR's data/projects.json; planned ones are the
  // projects it lists as proposed.
  var START = { x: 110, y: 612 };
  var HUB = { x: 560, y: 360 };
  var PLACES = {
    "wayne-ui":  { name: "WayneUI",    alias: "Front Quad",       x: 560,  y: 92,  label: "r", mark: "assets/marks/wayneui.svg", tile: true },
    creed:       { name: "CREED",      alias: "Handbook Library", x: 800,  y: 150, label: "r", mark: "assets/marks/creed.png" },
    proctor:     { name: "PROCTOR",    alias: "Exam Hall",        x: 1010, y: 255, label: "r", mark: "assets/marks/proctor.png" },
    preflight:   { name: "PREFLIGHT",  alias: "The Runway",       x: 1060, y: 440, label: "b", mark: "assets/marks/preflight.png" },
    pinstripe:   { name: "Pinstripe",  alias: "Tailor's Row",     x: 880,  y: 590, label: "r", mark: "assets/marks/pinstripe.png" },
    sesame:      { name: "SESAME",     alias: "The Front Door",   x: 680,  y: 615, label: "r", mark: "assets/marks/sesame.png" },
    helmet:      { name: "HELMET",     alias: "The Gatehouse",    x: 420,  y: 625, label: "r", mark: "assets/marks/helmet.png" },
    weld:        { name: "WELD",       alias: "The Workshop",     x: 330,  y: 118, label: "r", mark: "assets/marks/weld.png" },
    sourdough:   { name: "Sourdough",  alias: "The Bakery",       x: 150,  y: 300, label: "r", mark: "assets/marks/sourdough.png" },
    booster:     { name: "BOOSTER",    alias: "Launch Pad",       x: 1120, y: 110, label: "b", mark: "assets/marks/booster.png", planned: true },
    rollcall:    { name: "ROLLCALL",   alias: "The Registrar",    x: 290,  y: 388, label: "r", mark: "assets/marks/rollcall.png", planned: true },
    drawbridge:  { name: "DRAWBRIDGE", alias: "Castle Gate",      x: 120,  y: 470, label: "r", mark: "assets/marks/drawbridge.png", planned: true },
    squire:      { name: "SQUIRE",     alias: "The Stables",      x: 1120, y: 580, label: "b", mark: "assets/marks/squire.png", planned: true }
  };

  var map = document.getElementById("map");
  var placesLayer = document.getElementById("map-places");
  var routesLayer = document.getElementById("map-routes");
  var hubNode = null;

  function labelAt(g, p, name, alias) {
    var lg = svgEl("g", { "class": "place__label" }, g);
    var x, y, anchor;
    if (p.label === "b") { x = 0; y = 50; anchor = "middle"; }
    else if (p.label === "l") { x = -40; y = -2; anchor = "end"; }
    else { x = 40; y = -2; anchor = "start"; }
    var plate = svgEl("rect", { "class": "place__plate", rx: 4 }, lg);
    var t1 = svgEl("text", { "class": "place__name", x: x, y: y, "text-anchor": anchor }, lg);
    t1.textContent = name;
    var t2 = svgEl("text", { "class": "place__alias", x: x, y: y + 18, "text-anchor": anchor }, lg);
    t2.textContent = alias;
    // Knock the label out of the terrain, as a printed map would.
    requestAnimationFrame(function () {
      try {
        var b1 = t1.getBBox(), b2 = t2.getBBox();
        var x0 = Math.min(b1.x, b2.x) - 5, y0 = b1.y - 3;
        var w = Math.max(b1.x + b1.width, b2.x + b2.width) - x0 + 5;
        plate.setAttribute("x", x0); plate.setAttribute("y", y0);
        plate.setAttribute("width", w); plate.setAttribute("height", b2.y + b2.height - y0 + 3);
      } catch (e) { /* getBBox can fail while hidden; the label still reads. */ }
    });
  }

  if (map && placesLayer) {
    Object.keys(PLACES).forEach(function (id) {
      var p = PLACES[id];
      var g = svgEl("g", { "class": "place" + (p.planned ? " place--planned" : ""), "data-place": id, transform: "translate(" + p.x + " " + p.y + ")" }, placesLayer);
      svgEl("circle", { "class": "place__finish", r: 38 }, g);
      var bob = svgEl("g", { "class": "place__bob" }, g);
      svgEl("circle", { "class": "place__ring", r: 30 }, bob);
      if (p.tile) svgEl("rect", { x: -23, y: -23, width: 46, height: 46, rx: 11, fill: "#0c5449" }, bob);
      var size = p.tile ? 28 : 46;
      svgEl("image", { href: p.mark, x: -size / 2, y: -size / 2, width: size, height: size, "clip-path": p.tile ? "" : "url(#clip-mark)" }, bob);
      labelAt(g, p, p.name, p.alias + (p.planned ? " (planned)" : ""));
    });

    // The advising office, with its door open.
    hubNode = svgEl("g", { "class": "hub", transform: "translate(" + HUB.x + " " + HUB.y + ")" }, placesLayer);
    svgEl("circle", { "class": "hub__halo", r: 54 }, hubNode);
    svgEl("circle", { "class": "hub__flare", r: 48 }, hubNode);
    svgEl("circle", { "class": "hub__ring", r: 47 }, hubNode);
    svgEl("image", { href: "brand/advisor-avatar-256.png", x: -38, y: -38, width: 76, height: 76, "clip-path": "url(#clip-hub)" }, hubNode);
    var hl = svgEl("g", {}, hubNode);
    var plate = svgEl("rect", { "class": "place__plate", x: -84, y: 54, width: 168, height: 46, rx: 4 }, hl);
    plate.setAttribute("class", "place__plate");
    svgEl("text", { "class": "hub__name", x: 0, y: 76, "text-anchor": "middle" }, hl).textContent = "ADVISOR";
    svgEl("text", { "class": "place__alias place__label", x: 0, y: 94, "text-anchor": "middle" }, hl).textContent = "Advising Office, door open";

    // You are here: an orienteering start triangle, with a gold dot.
    var st = svgEl("g", { "class": "start", transform: "translate(" + START.x + " " + START.y + ")" }, placesLayer);
    svgEl("circle", { "class": "start__pulse", r: 20 }, st);
    svgEl("path", { "class": "start__tri", d: "M0 -24 L21 13 L-21 13 Z" }, st);
    svgEl("circle", { "class": "start__dot", r: 6 }, st);
    svgEl("text", { "class": "start__label", x: 32, y: 34 }, st).textContent = "You are here";
  }

  /* ------------------------------------------- 4. routes: questions to places */

  // CREED's policy.json as bundled in ADVISOR's data/ (active_until,
  // security_until). The states below are worked out the same way ADVISOR's
  // PolicyReader does, for the visitor's today, with the 90-day grace.
  var POLICY = {
    php: { "8.1": [null, "2025-12-31"], "8.2": ["2024-12-31", "2026-12-31"], "8.3": ["2025-12-31", "2027-12-31"], "8.4": ["2026-12-31", "2028-12-31"], "8.5": ["2027-12-31", "2029-12-31"] },
    laravel: { "10": ["2024-08-06", "2025-02-04"], "11": ["2025-09-03", "2026-03-12"], "12": ["2026-08-13", "2027-02-24"], "13": ["2027-09-17", "2028-03-17"] },
    node: { "20": ["2024-10-22", "2026-04-30"], "22": ["2025-10-21", "2027-04-30"], "24": ["2026-10-20", "2028-04-30"], "26": ["2027-10-20", "2029-04-30"] },
    mysql: { "5.7": ["2020-10-31", "2023-10-31"], "8.0": ["2025-04-30", "2026-04-30"], "8.4": ["2029-04-30", "2032-04-30"], "9.7": ["2031-04-30", "2034-04-30"] }
  };
  var GRACE_DAYS = 90;
  var NAMES = { php: "PHP", laravel: "Laravel", node: "Node", mysql: "MySQL" };

  function isoToday() {
    var d = new Date();
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }
  function addDays(iso, n) {
    var d = new Date(iso + "T00:00:00Z");
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
  }
  function stateOf(entry, day) {
    if (entry[0] && day <= entry[0]) return "active";
    if (day <= entry[1]) return "security";
    return day <= addDays(entry[1], GRACE_DAYS) ? "eol" : "eol_grace_expired";
  }
  var STATE_WORDS = { active: "active", security: "security fixes only", eol: "end of life (grace period)", eol_grace_expired: "end of life" };

  function policyAnswer(component, extra) {
    var day = isoToday();
    var versions = POLICY[component];
    var groups = { active: [], security: [], eol: [] };
    var list = el("ul", "answer__versions");
    Object.keys(versions).forEach(function (v) {
      var s = stateOf(versions[v], day);
      (s === "active" ? groups.active : s === "security" ? groups.security : groups.eol).push(v);
      var li = el("li", "v--" + s, v);
      li.setAttribute("title", STATE_WORDS[s]);
      var sr = el("span", "wsu-sr-only", ", " + STATE_WORDS[s]);
      li.appendChild(sr);
      list.appendChild(li);
    });
    var n = NAMES[component];
    var parts = [];
    if (groups.active.length) parts.push(n + " " + join(groups.active) + (groups.active.length > 1 ? " are" : " is") + " in active support");
    if (groups.security.length) parts.push(join(groups.security) + " " + (groups.security.length > 1 ? "get" : "gets") + " security fixes only");
    if (groups.eol.length) parts.push(join(groups.eol) + (groups.eol.length > 1 ? " are" : " is") + " past end of life");
    return {
      text: sentence(parts) + (extra ? " " + extra : ""),
      list: list,
      src: "Source: CREED policy.json, worked out for " + day + "."
    };
  }
  function join(a) { return a.length < 2 ? a.join("") : a.slice(0, -1).join(", ") + " and " + a[a.length - 1]; }
  function sentence(parts) {
    if (!parts.length) return "";
    var s = parts.length < 2 ? parts[0] : parts.slice(0, -1).join(", ") + ", and " + parts[parts.length - 1];
    return s + ".";
  }

  function componentIn(q) {
    var m = q.match(/\b(laravel|node|mysql)\b/);
    return m ? m[1] : "php";
  }

  // Each route: keywords, the steps in order (tool or prompt, with the
  // arguments a model would send), the destination and the reply's shape.
  var ROUTES = {
    policy: {
      keys: ["php", "version", "versions", "supported", "support", "8.3", "8.4", "8.5", "laravel", "node", "mysql", "runtime", "end of life", "build on"],
      steps: function (q) { return [{ tool: "policy_lookup", args: "component: " + componentIn(q) }]; },
      dest: "creed", detail: "policy.json",
      answer: function (q) { return policyAnswer(componentIn(q)); }
    },
    upgrade: {
      keys: ["upgrade", "8.1", "8.2", "off php", "old php", "migrate", "outdated", "booster", "get off", "move to"],
      steps: function () { return [{ prompt: "plan_upgrade" }, { tool: "policy_lookup", args: "component: php" }, { tool: "run_proctor_check" }]; },
      dest: "booster", detail: "planned; the prompt points at the policy and PROCTOR meanwhile",
      answer: function () { return policyAnswer("php", "BOOSTER, the upgrade tool, is still planned, so plan_upgrade walks the policy and your PROCTOR findings instead."); }
    },
    proctor: {
      keys: ["pass", "check", "standards", "standard", "proctor", "meet", "audit", "finding", "findings", "comply", "compliant"],
      steps: function () { return [{ tool: "run_proctor_check", args: "path: ." }]; },
      dest: "proctor", detail: "your app's own vendor/bin/proctor",
      answer: function () { return { text: "ADVISOR runs your app's own vendor/bin/proctor check against the bundled policy and hands back the findings, with secrets redacted. It explains them; PROCTOR in CI decides pass or fail.", src: "Source: PROCTOR, run inside your project root." }; }
    },
    markup: {
      keys: ["markup", "button", "component", "components", "html", "css", "wayneui", "wayne ui", "accessib", "a11y", "contrast", "layout", "design", "form", "template"],
      steps: function () { return [{ tool: "read_project_docs", args: "project: wayne-ui, doc: contract" }]; },
      dest: "wayne-ui", detail: "the markup contract",
      answer: function () { return { text: "WayneUI's markup contract, paged: the structure its components expect, so hand-written markup agrees with the Blade, Vue and raw-PHP components and passes the accessibility gate.", src: "Source: WayneUI's contract, bundled and hash-checked." }; }
    },
    signin: {
      keys: ["sign", "login", "log in", "cas", "auth", "saml", "oauth", "sso", "users", "identity"],
      steps: function () { return [{ tool: "read_project_docs", args: "project: sesame, doc: readme" }]; },
      dest: "sesame", detail: "its README",
      answer: function () { return { text: "SESAME is protocol-neutral sign-in, with drivers for CAS, a signed gateway assertion, SAML and OAuth2. It returns a verified identity and leaves the session to your app. Pick the driver your platform supports first.", src: "Source: SESAME's README, as bundled." }; }
    },
    headers: {
      keys: ["header", "headers", "csp", "hsts", "security", "hardening", "health", "proxy", "proxies", "cookie", "session"],
      steps: function () { return [{ tool: "list_stack_rules", args: "chapter: runtime-and-headers" }, { tool: "read_project_docs", args: "project: helmet" }]; },
      dest: "helmet", detail: "its README and presets",
      answer: function () { return { text: "The CREED rules on runtime and headers, each with its file and line, then HELMET: security headers, CSP, session hardening, trusted proxies, a health check and a version warning. Start from its presets.", src: "Source: CREED handbook and HELMET's README." }; }
    },
    ci: {
      keys: ["ci", "pipeline", "pipelines", "gitlab", "github", "actions", "workflow"],
      steps: function () { return [{ tool: "list_stack_rules", args: "chapter: ci" }, { tool: "read_project_docs", args: "project: preflight" }]; },
      dest: "preflight", detail: "its CI templates",
      answer: function () { return { text: "The CREED chapter on CI, rule by rule with citations, then PREFLIGHT's shared templates for GitLab and GitHub, which run the same checks on both.", src: "Source: CREED handbook and PREFLIGHT's README." }; }
    },
    style: {
      keys: ["lint", "pint", "phpstan", "eslint", "prettier", "style", "format", "formatting", "coding standard"],
      steps: function () { return [{ tool: "read_project_docs", args: "project: pinstripe, doc: best-practices" }]; },
      dest: "pinstripe", detail: "its best practices",
      answer: function () { return { text: "Pinstripe: Pint, PHPStan, ESLint and Prettier with the settings the whole stack shares. Pressed, starched, and the same in every repo.", src: "Source: Pinstripe's best practices." }; }
    },
    deploy: {
      keys: ["deploy", "deploying", "release", "ship", "hosting", "server"],
      steps: function () { return [{ tool: "get_project_status", args: "project: squire" }]; },
      dest: "squire", detail: "planned",
      answer: function () { return { text: "SQUIRE, the deploy kit, is still proposed. get_project_status says so plainly and gives its next step, instead of inventing a deploy command that does not exist yet.", src: "Source: ADVISOR's project list." }; }
    },
    census: {
      keys: ["fleet", "census", "inventory", "rollcall", "which apps"],
      steps: function () { return [{ tool: "get_project_status", args: "project: rollcall" }]; },
      dest: "rollcall", detail: "planned",
      answer: function () { return { text: "ROLLCALL, the fleet census, is proposed. Its status and next step come back as written; nothing is made up to fill the gap.", src: "Source: ADVISOR's project list." }; }
    },
    gateway: {
      keys: ["gateway", "drawbridge"],
      steps: function () { return [{ tool: "get_project_status", args: "project: drawbridge" }]; },
      dest: "drawbridge", detail: "planned",
      answer: function () { return { text: "DRAWBRIDGE, the auth gateway, is proposed. Until it is lowered, SESAME's drivers are how an app signs people in.", src: "Source: ADVISOR's project list." }; }
    },
    stack: {
      keys: ["projects", "stack", "parts", "list", "what's in", "what is in", "starter", "sourdough"],
      steps: function () { return [{ tool: "list_projects" }]; },
      dest: "wayne-ui", detail: "first on the list, always",
      answer: function () { return { text: "Fourteen projects, WayneUI first, each marked built or proposed: nine built today, five proposed. ADVISOR modestly lists itself as proposed until its own data is regenerated.", src: "Source: ADVISOR's data/projects.json." }; }
    },
    start: {
      keys: ["new", "start", "begin", "orient", "where", "help", "first", "onboard", "onboarding", "hello"],
      steps: function () { return [{ prompt: "orient_me" }, { tool: "list_projects" }, { tool: "list_stack_rules", args: "permutation: laravel-inertia-vue" }]; },
      dest: "weld", detail: "the stack as one app",
      answer: function () { return { text: "orient_me walks your agent round the stack: what exists, which rules apply to your app's shape, which versions are supported today, and PROCTOR's findings if there is an app here. It ends with three next steps, each tied to a project.", src: "Source: ADVISOR's orient_me prompt." }; }
    }
  };

  function score(q, keys) {
    var s = 0;
    keys.forEach(function (k) {
      if (k.length <= 3 && /^[a-z]+$/.test(k)) {
        if (new RegExp("\\b" + k + "\\b").test(q)) s++;
      } else if (q.indexOf(k) !== -1) s++;
    });
    return s;
  }

  function pick(question) {
    var q = question.toLowerCase();
    var best = null, bestScore = 0;
    Object.keys(ROUTES).forEach(function (id) {
      var s = score(q, ROUTES[id].keys);
      if (s > bestScore) { best = id; bestScore = s; }
    });
    return { id: best || "start", guessed: !best };
  }

  /* ----------------------------------------------- 5. plotting a route */

  var timers = [];
  var plotCount = 0;
  function clearTimers() { timers.forEach(clearTimeout); timers = []; }
  function later(fn, ms) { timers.push(setTimeout(fn, ms)); }

  function bendPoint(a, b, bend) {
    var mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
    var dx = b.x - a.x, dy = b.y - a.y;
    return { x: mx - dy * bend, y: my + dx * bend };
  }
  function toward(p, q, dist) {
    var dx = q.x - p.x, dy = q.y - p.y, len = Math.hypot(dx, dy) || 1;
    return { x: p.x + (dx / len) * dist, y: p.y + (dy / len) * dist };
  }
  // A quadratic curve from a to b, trimmed so it starts and ends in the gap
  // outside each symbol: routes never run over or under an icon.
  function segPath(a, b, ra, rb, bend) {
    var c = bendPoint(a, b, bend);
    var s = toward(a, c, ra), e = toward(b, c, rb);
    return "M" + s.x.toFixed(1) + " " + s.y.toFixed(1) + " Q" + c.x.toFixed(1) + " " + c.y.toFixed(1) + " " + e.x.toFixed(1) + " " + e.y.toFixed(1);
  }

  function controlsAlong(dest, n) {
    var pts = [];
    var dx = dest.x - HUB.x, dy = dest.y - HUB.y;
    for (var i = 0; i < n; i++) {
      var t = (i + 1) / (n + 1);
      var off = (i % 2 === 0 ? 1 : -1) * 0.11;
      pts.push({ x: HUB.x + dx * t - dy * off, y: HUB.y + dy * t + dx * off });
    }
    return pts;
  }

  function plot(question, opts) {
    opts = opts || {};
    var chosen = pick(question);
    var route = ROUTES[chosen.id];
    var steps = route.steps(question.toLowerCase());
    var destP = PLACES[route.dest];
    clearTimers();
    plotCount++;

    // --- the map
    if (routesLayer) {
      while (routesLayer.firstChild) routesLayer.removeChild(routesLayer.firstChild);
      document.querySelectorAll(".place--dest").forEach(function (n) { n.classList.remove("place--dest"); });
      if (hubNode) hubNode.classList.remove("is-lit");

      var defs = map.querySelector("defs");
      defs.querySelectorAll(".route-mask-def").forEach(function (n) { n.remove(); });

      var ctrls = controlsAlong(destP, steps.length);
      var nodes = [{ p: START, r: 30 }, { p: HUB, r: 58 }];
      ctrls.forEach(function (c) { nodes.push({ p: c, r: 24 }); });
      nodes.push({ p: destP, r: 46 });

      var segs = [];
      for (var i = 0; i < nodes.length - 1; i++) {
        var d = segPath(nodes[i].p, nodes[i + 1].p, nodes[i].r, nodes[i + 1].r, i === 0 ? -0.12 : (i % 2 ? -0.09 : 0.09));
        var id = "rm-" + plotCount + "-" + i;
        var mask = svgEl("mask", { id: id, "class": "route-mask-def", maskUnits: "userSpaceOnUse", x: 0, y: 0, width: 1200, height: 700 }, defs);
        var mp = svgEl("path", { d: d, "class": "route-mask" }, mask);
        var vis = svgEl("path", { d: d, "class": "route", mask: "url(#" + id + ")" }, routesLayer);
        var len = mp.getTotalLength ? mp.getTotalLength() : 400;
        mp.style.strokeDasharray = len + " " + (len + 20);
        mp.style.strokeDashoffset = motion ? String(len) : "0";
        segs.push({ mask: mp, len: len, vis: vis });
      }

      // Controls: numbered circles with the tool's name on a maroon tag.
      var ctrlEls = ctrls.map(function (c, k) {
        var g = svgEl("g", { "class": "ctrl" + (motion ? "" : " is-lit"), transform: "translate(" + c.x.toFixed(1) + " " + c.y.toFixed(1) + ")" }, routesLayer);
        svgEl("circle", { "class": "ctrl__flare", r: 18 }, g);
        svgEl("circle", { "class": "ctrl__ring", r: 17 }, g);
        svgEl("text", { "class": "ctrl__num", x: 0, y: 1 }, g).textContent = String(k + 2);
        var name = steps[k].tool || steps[k].prompt;
        var tag = svgEl("g", { "class": "ctrl__tag" }, g);
        var w = name.length * 8.6 + 18;
        var left = c.x + 26 + w > 1190;
        var tx = left ? -26 - w : 26;
        svgEl("rect", { x: tx, y: -14, width: w, height: 28, rx: 14 }, tag);
        svgEl("text", { x: tx + 9, y: 1 }, tag).textContent = name;
        if (motion) tag.style.display = "none";
        return { g: g, tag: tag };
      });

      var destNode = placesLayer.querySelector('[data-place="' + route.dest + '"]');
      // Bring the destination to the top so its finish ring is never hidden.
      if (destNode) placesLayer.appendChild(destNode);

      if (!motion || opts.instant) {
        segs.forEach(function (s) { s.mask.style.transition = "none"; s.mask.style.strokeDashoffset = "0"; });
        ctrlEls.forEach(function (c) { c.g.classList.add("is-lit"); c.tag.style.display = ""; });
        if (hubNode) hubNode.classList.add("is-lit");
        if (destNode) destNode.classList.add("place--dest");
        litLegs(Infinity);
      } else {
        // Draw leg by leg; each waypoint lights as the pencil reaches it.
        var at = 120;
        litLegs(0);
        segs.forEach(function (s, k) {
          var dur = Math.max(420, Math.min(1100, s.len * 2.1));
          later(function () {
            s.mask.style.setProperty("--dur", dur + "ms");
            s.mask.getBoundingClientRect();
            s.mask.style.strokeDashoffset = "0";
          }, at);
          at += dur;
          later(function () {
            if (k === 0 && hubNode) hubNode.classList.add("is-lit");
            else if (k - 1 < ctrlEls.length) { ctrlEls[k - 1].g.classList.add("is-lit"); ctrlEls[k - 1].tag.style.display = ""; }
            if (k === segs.length - 1 && destNode) destNode.classList.add("place--dest");
            litLegs(k + 1);
          }, at);
          at += 140;
        });
      }
    }

    // --- the card (the whole answer in words; announced politely)
    renderCard(question, chosen, route, steps, destP);
  }

  function litLegs(n) {
    document.querySelectorAll("#card-body .leg").forEach(function (li, i) {
      li.classList.toggle("is-lit", i <= n);
    });
  }

  function renderCard(question, chosen, route, steps, destP) {
    var body = document.getElementById("card-body");
    if (!body) return;
    var frag = document.createDocumentFragment();
    frag.appendChild(el("p", "card__q", "“" + question + "”"));
    var ol = el("ol", "legs");
    function leg(symCls, symText, html) {
      var li = el("li", "leg");
      var s = el("span", "leg__sym" + (symCls ? " " + symCls : ""), symText);
      s.setAttribute("aria-hidden", "true");
      var t = el("span", "leg__text");
      html(t);
      li.appendChild(s); li.appendChild(t); ol.appendChild(li);
    }
    function strong(t, s) { t.appendChild(el("strong", "", s)); }
    function code(t, s) { t.appendChild(el("code", "", s)); }
    function txt(t, s) { t.appendChild(document.createTextNode(s)); }

    leg("leg__sym--start", "", function (t) { strong(t, "Start:"); txt(t, " your app, where your coding agent is working"); });
    leg("", "1", function (t) { strong(t, "ADVISOR"); txt(t, chosen.guessed ? " finds no exact route, so it starts with a walk round the stack" : " takes the question"); });
    steps.forEach(function (st, i) {
      leg("", String(i + 2), function (t) {
        txt(t, st.tool ? "Tool " : "Prompt ");
        code(t, st.tool || st.prompt);
        if (st.args) { txt(t, " with "); code(t, st.args); }
      });
    });
    leg("leg__sym--finish", "", function (t) {
      strong(t, "Finish:"); txt(t, " " + destP.name + (destP.planned ? " (planned)" : "") + ", " + route.detail);
    });
    frag.appendChild(ol);

    var a = route.answer(question.toLowerCase());
    var box = el("div", "answer");
    box.appendChild(el("p", "answer__label", "Answer, as reference data"));
    box.appendChild(el("p", "answer__text", a.text));
    if (a.list) box.appendChild(a.list);
    box.appendChild(el("p", "answer__src", a.src));
    frag.appendChild(box);

    body.textContent = "";
    body.appendChild(frag);
    var foot = document.querySelector(".card__foot");
    if (foot) foot.textContent = "Distance: " + steps.length + " tool call" + (steps.length > 1 ? "s" : "") + ". Climb: 0 m. Everything on this route is read-only.";
  }

  var form = document.getElementById("ask-form");
  var input = document.getElementById("ask-input");
  var chips = document.querySelectorAll(".chip-q");
  function setPressed(btn) {
    chips.forEach(function (c) { c.setAttribute("aria-pressed", c === btn ? "true" : "false"); });
  }
  if (form && input) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var q = input.value.trim();
      if (!q) { say("Even advisors need a question. Try one of the suggestions."); input.focus(); return; }
      setPressed(null);
      plot(q);
    });
    chips.forEach(function (c) {
      c.setAttribute("aria-pressed", "false");
      c.addEventListener("click", function () {
        input.value = c.getAttribute("data-q");
        setPressed(c);
        plot(input.value);
      });
    });
    // First route: drawn instantly at load, plotted with motion when the map
    // first scrolls into view.
    plot(input.value, { instant: true });
    if (motion && "IntersectionObserver" in window) {
      var mapSeen = false;
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting && !mapSeen) { mapSeen = true; plot(input.value); io.disconnect(); }
        });
      }, { threshold: 0.45 });
      io.observe(map);
    }
  }

  /* --------------------------------------------- 6. reveals, small delights */

  if (motion && "IntersectionObserver" in window) {
    var targets = document.querySelectorAll(".section__head, .legend-sheet, .turn, .dir-box, .bounds, .hood__item, .uncharted, .unch li");
    var counters = new Map();
    targets.forEach(function (t) {
      var parent = t.parentElement;
      var n = counters.get(parent) || 0;
      counters.set(parent, n + 1);
      t.style.setProperty("--d", String(n));
      t.setAttribute("data-reveal", "");
    });
    var rio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          var d = parseInt(en.target.style.getPropertyValue("--d") || "0", 10);
          en.target.style.transitionDelay = Math.min(d, 8) * 60 + "ms";
          en.target.classList.add("is-in");
          rio.unobserve(en.target);
          // Hover springs should not wait on the reveal stagger.
          setTimeout(function () { en.target.style.transitionDelay = ""; }, 900 + d * 60);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    targets.forEach(function (t) { rio.observe(t); });
  }

  // Click the compass: it spins and still finds north.
  var bezel = document.querySelector(".rose__bezel");
  var mark = document.querySelector(".hero__mark");
  var spins = 0;
  if (mark && bezel) {
    mark.addEventListener("click", function () {
      spins++;
      if (motion) {
        bezel.classList.remove("is-spun");
        bezel.getBoundingClientRect();
        bezel.classList.add("is-spun");
      }
      say(spins < 3 ? "Still north. Your advisor checked." : "Spun " + spins + " times. Still north. Still on PHP 8.4, we hope.");
    });
  }
})();
