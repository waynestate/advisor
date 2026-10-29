---
title: "ADVISOR: What applies, and what's next."
description: "ADVISOR is the Wayne State app stack's own MCP server: it orients your coding agent and gives it read-only tools into the stack's standards, versions, docs and checks."
url: https://advisor.apps.wayne.edu/
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

The Wayne State app stack, surveyed

# ADVISOR

ADVISOR stands for Answers, Directions, Versions, Integrations, Standards, Onboarding and Resources.

What applies, and what's next.

The stack's own MCP server, like Laravel Boost for Wayne State. It orients your coding agent and hands it read-only tools into every stack project: the standards, the supported versions, the docs and the checks. Ask “which PHP can I build on?” and it answers from the policy file, with the date and the source, instead of from whatever the model remembers.

`$ composer require --dev waynestate/advisor`

PHP 8.3 or newer. ADVISOR is on Packagist: `composer require --dev waynestate/advisor:^0.1`.

[Ask the map](https://advisor.apps.wayne.edu/#ask) [Get directions](https://advisor.apps.wayne.edu/#directions)

The ADVISOR mark: a speech bubble holding a gold compass needle.

The magic trick, minus the magic

## Ask the map

Type the kind of question you would ask your coding agent. The map plots the route ADVISOR takes: from your app, through the advising office, past the tool that answers, to the stack project the answer comes from.

An illustration of how your agent uses ADVISOR's tools. This page calls nothing; the real server runs on your machine, beside your app.

Control descriptions

### Route card

“Which PHP can I build on?”

1. **Start:** your app, where your coding agent is working
2. **ADVISOR** takes the question
3. Tool `policy_lookup` with `component: php`
4. **Finish:** CREED, `policy.json`

Answer, as reference data

PHP 8.4 and 8.5 are in active support, 8.2 and 8.3 get security fixes only, and 8.1 is past its end of life.

Source: CREED `policy.json`, worked out for today.

Climb: 0 m. Everything on this route is read-only.

Read this before you set off

## Legend

Six tools, two prompts and a shelf of resources. Every tool is read-only and every answer says where it came from.

### Tools

- **`list_projects`**: The stack's projects, WayneUI first, each marked built or proposed.
- **`get_project_status`**: One project: its state, package, PHP constraint, its floor under today's policy, and its next step.
- **`read_project_docs`**: A project's README or best practices, paged. For WayneUI, its markup contract too.
- **`policy_lookup`**: Support state for PHP, Laravel, Node, MySQL, Vue, Inertia or Composer from CREED, as of today.
- **`list_stack_rules`**: CREED handbook rules with their file and line, filtered by your app's shape and by chapter.
- **`run_proctor_check`**: Runs your app's own `vendor/bin/proctor check` against the bundled policy and returns the findings.

### Prompts

- `orient_me` a first walk round the stack, ending in three next steps
- `plan_upgrade` the way off an old version, via the policy and PROCTOR

### Resources

- `advisor://policy` CREED's policy file
- `advisor://creed/<chapter>` one per handbook chapter

### Map symbols

- your app
- a tool call
- where the answer lives

**Boost knows Laravel; ADVISOR knows Wayne State.** They do not overlap, so run both. In a Laravel app the same server also runs as `php artisan mcp:start advisor`, right beside Boost.

Turn-by-turn

## Directions

Three turns from a fresh clone to an agent that knows the stack. It is one PHP command, so it is the same on Windows, macOS and Linux: no shell script to pick.

1. ### Add it to your app
   As a dev dependency. It refuses to run in production anyway.
   ```
   composer require --dev waynestate/advisor
   ```
2. ### Print the client config, and read it
   Nothing is written yet. Pick your client: `claude`, `cursor` or `vscode`. For `codex` it prints TOML to paste into `~/.codex/config.toml`.
   ```
   php vendor/bin/advisor install --client=claude
   ```
   ```
   {"mcpServers":{"advisor":{"command":"php","args":["vendor/bin/advisor","mcp"],"env":{"ADVISOR_ENABLED":"1"}}}}
   ```
3. ### Write it, then approve it by name
   Open the project in your client and approve the `advisor` server when it asks. Keep the generated file out of git: it starts a program on whoever opens the project, and `install` warns you if it is not ignored.
   ```
   php vendor/bin/advisor install --client=claude --write
   ```

Where `install --write` puts it

- claude`.mcp.json`
- cursor`.cursor/mcp.json`
- vscode`.vscode/mcp.json`
- codex`printed only (TOML)`

Every one starts the same fixed command, `php vendor/bin/advisor mcp`. Run your client from the folder that holds `vendor/`, or set `ADVISOR_PROJECT_ROOT`.

### Other commands

```
# serve over stdio (your client starts this)
php vendor/bin/advisor mcp

# check the bundled content against its manifest
php vendor/bin/advisor content:verify

# a check of the install and the client config
php vendor/bin/advisor doctor

# every check, in the advisor repo itself
composer ci
```

### Laravel apps

The adapter on `laravel/mcp` is auto-discovered. Publish the config if you want it in the app:

```
php artisan vendor:publish --tag=advisor-config
```

Read out at the start line

## Briefings for your agent

Your coding agent can set ADVISOR up itself. Brief it: copy a briefing, or run it straight in OpenCode, Claude Code, Codex or ChatGPT. Each one names the controls in order, what is out of bounds, and tells the agent to ask before it guesses.

### Get started with ADVISOR

From composer require to a connected server: the config shown before it is written, and the first question asked.

A prompt for your coding agent. Copy it as it stands:

```text
You are a coding agent helping a Wayne State University developer connect ADVISOR to the app in this folder. ADVISOR is the MCP server of WELD, the Wayne State application stack: read-only tools that answer from CREED's policy and the stack's own docs, with the date and the source, instead of from memory.

Read first:
- https://advisor.apps.wayne.edu/llms.txt, the index of ADVISOR's pages, each one as Markdown. Work from them, not from memory.
- This app's composer.json and its .gitignore.
- Which coding agent you are. ADVISOR writes the config for claude, warriorgpt, cursor and vscode, and prints TOML for codex. For any other client (OpenCode, for one), print the claude config, translate it by hand, and show me before you write it.

Task:
1. Add ADVISOR as a dev dependency with `composer require --dev waynestate/advisor` (PHP 8.3 or newer).
2. Print the client config and show it to me: `php vendor/bin/advisor install --client=claude`, with your client's name in place of claude. Write nothing yet.
3. When I say yes, run it again with --write, and check that the file it wrote is in .gitignore.
4. If the server does not show up, run `php vendor/bin/advisor doctor` and tell me what it found.
5. Once it is connected, call list_projects, then policy_lookup for PHP, and tell me which PHP this app can build on today, with the date and the source.

Constraints:
- ADVISOR is a dev dependency and refuses to run in production. Never move it into require, and never set ADVISOR_ENV to production.
- Never commit a generated client config: it starts a program on whoever opens the project.
- The command it registers is always php vendor/bin/advisor mcp. Do not change it.
- Give separate commands for Windows (PowerShell), macOS and Linux wherever they differ.
- Ask me before you guess, and before you pass --force.

Output: the config you propose, the commands you ran, the doctor result if you ran it, then your questions.
```

### Or take the Laravel route

### ADVISOR in a Laravel app, beside Boost

The adapter on laravel/mcp, the published config, and both servers in one client: Boost knows Laravel, ADVISOR knows WSU.

A prompt for your coding agent. Copy it as it stands:

```text
You are a coding agent adding ADVISOR to a Laravel app at Wayne State University, beside Laravel Boost. ADVISOR is the MCP server of WELD, the Wayne State application stack. Boost knows Laravel; ADVISOR knows WSU. Run both.

Read first:
- https://advisor.apps.wayne.edu/llms.txt, then the README it links: Install, Quick start, Laravel apps and Configuration.
- composer.json, composer.lock, config/, .gitignore, .env.example, and any MCP client config already in the project (Boost writes one).

Task:
1. Add ADVISOR with `composer require --dev waynestate/advisor`. Its service provider is auto-discovered, and its Laravel adapter runs on laravel/mcp.
2. If I want the settings in the app, publish them with `php artisan vendor:publish --tag=advisor-config`. They land in config/advisor.php and read the same ADVISOR_* variables.
3. Choose with me how the client starts it: `php vendor/bin/advisor install` for my client, or the Laravel way, `php artisan mcp:start advisor`. Either way it needs ADVISOR_ENABLED=1 in its environment.
4. Add it beside Boost's entry without touching Boost's. Then ask ADVISOR policy_lookup for PHP and Laravel, and list_stack_rules with the permutation that fits this app (laravel-blade, laravel-blade-vue or laravel-inertia-vue).

Constraints:
- Keep Boost. ADVISOR does not replace it.
- Off unless ADVISOR_ENABLED is set, and never in production: keep it in require-dev.
- Never commit a generated client config, and never pass --force over an entry you did not write.
- Give separate commands for Windows (PowerShell), macOS and Linux wherever they differ.
- Ask me before you guess, and before you change anything Boost owns.

Output: the client config with both servers in it, the commands you ran, the rules list_stack_rules returned, then your questions.
```

The agent reads the same sheet you do: [llms.txt](https://advisor.apps.wayne.edu/llms.txt) lists the pages, [llms-full.txt](https://advisor.apps.wayne.edu/llms-full.txt) holds them in one file, and this page has a [Markdown twin](https://advisor.apps.wayne.edu/index.md). Nothing here phones home; the buttons only copy text or open the tool you picked. The route is read-only, and so is the advisor.

Hatched on the map, for a reason

## Out of bounds

An advisor can tell you what's required. It cannot sit the exam for you, and this one cannot touch your files.

- **Off unless you turn it on.** No `ADVISOR_ENABLED`, no server. `production` always refuses.
- **Read-only.** No tool takes a command, a URL or a free path. None writes a file in 0.1.
- **No network, no model calls.** It serves a bundled copy of the stack's docs, so it works offline.
- **Verified content.** Every bundled file is checked against a SHA-256 manifest at start-up; a mismatch stops the server.
- **Answers are data.** Every result is labelled as reference text and cleaned of invisible characters. PROCTOR in CI decides pass or fail, not ADVISOR.
- **One safe program.** `run_proctor_check` starts only your app's own PROCTOR, with an argument array, an allowlisted environment, a timeout, and no shell.

Adjoining sheets

## The neighbourhood

ADVISOR serves what every part of the stack publishes. Here is who lives where.

- [WayneUI](https://wayneui.apps.wayne.edu/)the interface, project 0
- [CREED](https://creed.apps.wayne.edu/)the standards and policy
- [PROCTOR](https://proctor.apps.wayne.edu/)checks an app
- [Preflight](https://preflight.apps.wayne.edu/)shared CI
- [Pinstripe](https://pinstripe.apps.wayne.edu/)the coding standard
- [SESAME](https://sesame.apps.wayne.edu/)sign-in
- [HELMET](https://helmet.apps.wayne.edu/)the runtime baseline
- [Sourdough](https://sourdough.apps.wayne.edu/)the starters
- [WELD](https://weld.apps.wayne.edu/)the stack as one app
- ADVISORyou are here
- [Booster](https://booster.apps.wayne.edu/)upgrades, one hop at a time
- [Rollcall](https://rollcall.apps.wayne.edu/)the fleet census
- [Drawbridge](https://drawbridge.apps.wayne.edu/)the auth gateway
- [Squire](https://squire.apps.wayne.edu/)the deploy kit

Here be roadmaps

## Not yet surveyed

Honest edges of the map. None of these exist in 0.1 except where marked, so none of them gets a command.

- Coming soon
  ### Ask ADVISOR on the web
  A chat page. It needs a model, a data decision and an accessibility pass of its own.
- Coming soon
  ### Tools that write
  Scaffold with Sourdough, apply a Booster step. The read-only switch and tool groups are built so they can arrive safely.
- Coming soon
  ### Modules from each project
  The loader exists and is tested; projects ship their own tools through a manifest when they are ready.
- Coming soon
  ### HTTP transport
  stdio first. Remote needs a threat model before it needs a port.
