# ADVISOR open questions

Status at the 0.1 build. Each question names who can answer it, what it blocks, and the default that was built so work
could continue.

## Questions

| # | Question | Who answers | Blocks | Default built |
|---|---|---|---|---|
| 1 | Does `mcp/sdk` 0.9.0 serve stdio correctly on native Windows PHP? It has no Windows CI, and there was no Windows PHP on the build machine. | Someone with a Windows PHP (an open stack question) | Telling Windows developers the one-command path is proven | One cross-OS PHP command, `php vendor/bin/advisor mcp`, tested on Linux (PHP 8.5.12) only. `StdoutGuard` keeps stdout clean on every OS by design. |
| 2 | Should the stack publish a CREED reader package so ADVISOR need not mirror its policy arithmetic? | Lead / CREED owner | Dropping `Content/PolicyReader` | A mirrored `PolicyReader` with a test that pins the boundary days. Swap it for the package when Pantry carries one. |
| 3 | `policy.json` holds components and tools but no package choices. Should `policy_lookup` also answer "which package for X"? | CREED owner | A "which package" tool | `policy_lookup` covers runtimes, frameworks and tools only. `list_stack_rules` serves the handbook text for package guidance. |
| 4 | `get_project_status` describes a stack project (state, package, PHP floor under today's policy), not the developer's own app. Is that the intended meaning? | Lead | An "inspect my app" tool | Stack project. For the user's own app, `run_proctor_check` is the tool. |
| 5 | Should Laravel apps register ADVISOR by `php artisan mcp:start advisor` or by `php vendor/bin/advisor mcp`? | Lead | The `install` default for Laravel | `install` always writes the plain PHP command. The artisan route is available through the service provider. |
| 6 | Where is ADVISOR published (Pantry, VCS repository, Packagist mirror), and who creates the GitLab repository and the GitHub mirror? | Lead / platform | `composer require` instructions, real badge URLs | Answered for Composer: ADVISOR is on Packagist as `waynestate/advisor` (`dev-main` until the first tag), and the install text says so. Badge URLs are placeholders. |
| 7 | Is the MCP Inspector wanted in CI (it needs Node)? | Lead | Best practice 6.1 | Not added. The in-process client, the `tools/list` snapshot and the stdio subprocess test cover the protocol with PHP only. |
| 8 | The `advisor` entry in the project list reads "proposed" until the repository holds code; BOOSTER, ROLLCALL, DRAWBRIDGE and SQUIRE are also proposed. Regenerate once their repos merge. | Lead | Accurate state labels | State is computed from whether the sibling repo holds a `composer.json`/`package.json`. Run `content:generate` after each merge. |
| 9 | `applies_to` on each handbook rule (which of the five app permutations it covers) is worked out from keywords. Should CREED tag rules itself? | CREED owner | Trusting the permutation filter | Keyword heuristic, stated in the tool description ("check the cited line before relying on it"). |
| 10 | Will WarriorGPT find `php` on the PATH on Windows when it starts the server? | WarriorGPT owner | The `warriorgpt` client config | Same `php` command as the other clients; `install` prints it, so a different path can be edited by hand. |
| 11 | An open stack question: which data classes may ever go to a model from ADVISOR? | Lead / security | Any future WarriorGPT feature | None. 0.1 makes no model call and no network call. |
| 12 | Should CI also run on Windows and macOS runners? | Lead / platform | Question 1 | GitLab and GitHub files run the Linux matrix (8.3, 8.4, 8.5) only. |
| 13 | Should `ADVISOR_ENABLED=1` live in the committed-or-ignored client config, or only in the developer's shell? | Lead / security | `install` output | `install` puts it in the client config's `env` block and warns when the file is not git-ignored. |
| 14 | Is there a `.ps1` smoke test wanted for Windows, per the one-path-per-OS rule? | Lead | Nothing | Not provided: the single PHP command is the cross-OS path the rule prefers. |

## Missing configuration

Nothing in 0.1 needs a secret, key, certificate, token or host. All of these are optional.

| Name | Source | Placeholder |
|---|---|---|
| `ADVISOR_ENABLED` | The developer, in the MCP client config (`advisor install` writes it) | `1` |
| `ADVISOR_ENV` | The developer, or `APP_ENV` | `local` |
| `ADVISOR_PROJECT_ROOT` | The developer | `/path/to/your/app` |
| `ADVISOR_AUDIT_LOG` | The developer | `/path/to/advisor-audit.log` |
| `ADVISOR_STACK_DIR` | Maintainers running `content:generate` | `/path/to/stack` |
| `ADVISOR_MODULES_FILE` | Maintainers adding modules | `/path/to/modules.json` |
| GitLab and GitHub badge URLs in `README.md` | Whoever creates the repositories | `github.com/waynestate/advisor` |
| GitHub workflow pin | The first PREFLIGHT GitHub release | `waynestate/preflight` commit `65e5248...` (the comment in `ci.yml` says to replace it with a tag) |

See `.env.example`.

## Not built yet

| Item | Why not in 0.1 |
|---|---|
| "Ask ADVISOR" web chat | Needs a model, an open stack question and an accessibility pass of its own. |
| HTTP transport and OAuth | stdio first (best practice 1.7); remote needs a threat model. |
| Tools that write (scaffold with Sourdough, apply a BOOSTER step) | Approved list is read-only; the read-only switch and tool groups are built so they can arrive safely. |
| Per-project modules in their own repositories | The loader and `Module` interface exist and are tested; the other projects have not published modules. |
| WarriorGPT-backed features | No model calls until an open stack question is settled. |
| Plan handles, `requestState` signing and elicitation | No tool needs state or a question to the user yet. |
| Full-capture audit mode | Hashes only by default; an opt-in needs a retention decision. |
| MCP Inspector in CI, native Windows and macOS CI | See questions 7 and 12. |
| BOOSTER upgrade-path tool | BOOSTER is a proposal; `plan_upgrade` is a prompt that points at the policy and PROCTOR meanwhile. |
