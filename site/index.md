---
title: "ADVISOR: What applies, and what's next."
description: "ADVISOR is an MCP server for the Wayne State app stack. Your coding agent asks it about our standards, supported versions, docs and checks, and it answers read-only from the stack's own files."
url: https://advisor.apps.wayne.edu/
llms: https://advisor.apps.wayne.edu/llms.txt
generator: Squire agent-ready
---

The Wayne State app stack, surveyed

# ADVISOR

ADVISOR stands for Answers, Directions, Versions, Integrations, Standards, Onboarding and Resources.

What applies, and what's next.

An MCP server for the stack, a bit like Laravel Boost but for Wayne State. Your coding agent connects to it and gets nine read-only tools: CREED's rules and support dates, each project's docs, PROCTOR and Booster's upgrade plan. Ask it “which PHP can I build on?” and it answers from CREED's policy file, with the date and the source, instead of from whatever the model remembers.

`$ composer require --dev waynestate/advisor`

PHP 8.3 or newer. ADVISOR is on Packagist: `composer require --dev waynestate/advisor:^0.1`.

**Works alongside [WELD](https://weld.apps.wayne.edu/).** You can add ADVISOR to a WELD app or to any other Wayne State PHP app.

[Ask the map](https://advisor.apps.wayne.edu/#ask) [Get directions](https://advisor.apps.wayne.edu/#directions)

The ADVISOR mark: a speech bubble holding a gold compass needle.

The magic trick, minus the magic

## Ask the map

Type a question you'd ask your coding agent. The map draws the route ADVISOR takes to answer it: which tools it calls, and which project the answer comes from.

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

ADVISOR 0.1 has nine tools. These six are the ones you'll use most; the others are `owner_of`, `playbook_lookup` and `upgrade_path`. Every tool is read-only, and every answer names its source.

### Tools

- **`list_projects`**: The stack's projects, WayneUI first, each marked built or proposed.
- **`get_project_status`**: One project: its state, package, PHP constraint, its floor under today's policy, and its next step.
- **`read_project_docs`**: A project's README or best practices, paged. For WayneUI, its markup contract too.
- **`policy_lookup`**: Whether a PHP, Laravel, Node, MySQL, Vue or Inertia version is supported today, from CREED. For Composer, the minimum version.
- **`list_stack_rules`**: CREED handbook rules with their file and line, filtered by your app's shape and by chapter.
- **`run_proctor_check`**: Runs your app's own `vendor/bin/proctor check` against the bundled policy and returns the findings.

### Prompts

- `orient_me` a first walk round the stack, ending in three next steps
- `plan_upgrade` plans the next upgrade hop from the policy, PROCTOR and Booster's plan

### Resources

- `advisor://policy` CREED's policy file
- `advisor://creed/<chapter>` one per handbook chapter

### Map symbols

- your app
- a tool call
- where the answer lives

**Run it next to Laravel Boost.** Boost knows Laravel and ADVISOR knows Wayne State, so they don't overlap. In a Laravel app the same server also runs as `php artisan mcp:start advisor`.

Turn-by-turn

## Directions

Three steps, and they're the same on Windows, macOS and Linux, because ADVISOR is one PHP command rather than a shell script.

1. ### Add it to your app
   Add it as a dev dependency. It won't start in production anyway.
   ```
   composer require --dev waynestate/advisor
   ```
2. ### Print the client config, and read it
   This writes nothing yet. Use your client's name: `claude`, `warriorgpt`, `cursor` or `vscode`. For `codex` it prints TOML to paste into `~/.codex/config.toml`.
   ```
   php vendor/bin/advisor install --client=claude
   ```
   ```
   {"mcpServers":{"advisor":{"command":"php","args":["vendor/bin/advisor","mcp"],"env":{"ADVISOR_ENABLED":"1"}}}}
   ```
3. ### Write it, then approve it by name
   Then open the project in your client and approve the `advisor` server when it asks. Don't commit the file it writes. It starts a program for anyone who opens the project, so `install` warns you if it isn't in `.gitignore`.
   ```
   php vendor/bin/advisor install --client=claude --write
   ```

Where `install --write` puts it

- claude`.mcp.json`
- cursor`.cursor/mcp.json`
- vscode`.vscode/mcp.json`
- codex`printed only (TOML)`
- warriorgpt`.mcp.json`

Every one starts the same fixed command, `php vendor/bin/advisor mcp`. Run your client from the folder that holds `vendor/`, or set `ADVISOR_PROJECT_ROOT`.

### Other commands

```
# serve over stdio (your client starts this)
php vendor/bin/advisor mcp

# check the bundled content against its manifest
php vendor/bin/advisor content:verify

# when the server doesn't show up in your client
php vendor/bin/advisor doctor

# every check, in the advisor repo itself
composer ci
```

### Laravel apps

The Laravel adapter runs on `laravel/mcp` and is auto-discovered. If you want the settings in your app, publish the config:

```
php artisan vendor:publish --tag=advisor-config
```

Read out at the start line

## Briefings for your agent

Your coding agent can set ADVISOR up for you. Copy a briefing, or open it directly in OpenCode, Claude Code, Codex or ChatGPT. Each one lists the steps and the limits, and tells the agent to ask you before it guesses.

### Get started with ADVISOR

The agent installs ADVISOR, shows you the client config before writing it, and asks its first question once it's connected.

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

Uses the laravel/mcp adapter and adds ADVISOR to your client next to Boost, without touching Boost's entry.

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

Your agent can read this page too. [llms.txt](https://advisor.apps.wayne.edu/llms.txt) lists the pages, [llms-full.txt](https://advisor.apps.wayne.edu/llms-full.txt) has all of them in one file, and this page has a [Markdown twin](https://advisor.apps.wayne.edu/index.md). The buttons only copy text or open the tool you picked. This page sends nothing back to us.

Hatched on the map, for a reason

## Out of bounds

An advisor can tell you what's required. It cannot sit the exam for you, and this one cannot touch your files.

- **Off unless you turn it on.** No `ADVISOR_ENABLED`, no server. `production` always refuses.
- **Read-only.** No tool takes a command, a URL or a free path, and none of them writes a file in 0.1.
- **No network, no model calls.** It serves a bundled copy of the stack's docs, so it works offline.
- **Verified content.** At start-up, every bundled file is checked against a SHA-256 manifest. If one doesn't match, the server won't start.
- **Answers are data.** Every result is labelled as reference text and cleaned of invisible characters. Pass or fail is PROCTOR's call in CI.
- **Two fixed programs.** `run_proctor_check` starts only your app's own PROCTOR, with an allowlisted environment. `upgrade_path` starts only Booster's read-only `plan`, never `run`. Both get an argument array and a timeout, and neither goes through a shell.

Adjoining sheets

## The neighbourhood

ADVISOR answers from a bundled copy of what these projects publish. Here is who lives where.

- [WayneUI](https://wayneui.apps.wayne.edu/)the interface, project 0
- [CREED](https://creed.apps.wayne.edu/)the standards and policy
- [PROCTOR](https://proctor.apps.wayne.edu/)checks an app
- [Preflight](https://preflight.apps.wayne.edu/)shared CI
- [Pinstripe](https://pinstripe.apps.wayne.edu/)the coding standard
- [SESAME](https://sesame.apps.wayne.edu/)sign-in
- [HELMET](https://helmet.apps.wayne.edu/)the runtime baseline
- [Sourdough](https://sourdough.apps.wayne.edu/)the raw PHP starter
- [WELD](https://weld.apps.wayne.edu/)the Laravel starter app
- ADVISORyou are here
- [Booster](https://booster.apps.wayne.edu/)upgrades, one hop at a time
- [Rollcall](https://rollcall.apps.wayne.edu/)the fleet census
- [Drawbridge](https://drawbridge.apps.wayne.edu/)the sign-in gateway
- [Squire](https://squire.apps.wayne.edu/)the deploy kit

Here be roadmaps

## Not yet surveyed

The edges of the map. None of these is in 0.1, so none of them gets a command here.

- Coming soon
  ### Ask ADVISOR on the web
  A chat page. First we need to pick a model and decide what data it may see, and it will need its own accessibility review.
- Coming soon
  ### Tools that write
  Things like starting an app with Sourdough or applying a Booster hop. When they come, they stay off until you set `ADVISOR_READ_ONLY=0`.
- Coming soon
  ### Modules from each project
  The loader is built and tested. It only loads what `modules.json` lists, and no project ships a module yet.
- Coming soon
  ### HTTP transport
  stdio first. Remote needs a threat model before it needs a port.
