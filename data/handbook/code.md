# Code

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## PHP style

- Laravel apps use Pint's `laravel` preset (the stack's coding standard).
- Raw PHP apps and the stack's own packages use the `per` preset, PER Coding
  Style 3.1 (the stack's coding standard).
- One formatter per repo, never two (the stack's coding standard).
- An app points Pint at the preset in `vendor/waynestate/pinstripe/pint/`
  with `--config` (the stack's coding standard).
- Files are UTF-8 without a BOM, with LF line endings (the stack's
  every-OS design).

## Reading the code

- Comment where it helps: complex logic, areas that could improve, business rules that would otherwise be a mystery (SHOULD). Do not keep version history in comments; git is the change log (WSU developer standards, 2024). No check exists yet.
- Code that no longer runs MUST be removed, not commented out; git keeps it (WSU developer standards, 2024). No check exists yet.
- Variable, class and database names SHOULD be complete and descriptive; avoid ambiguous abbreviations (WSU developer standards, 2024). No check exists yet.

## Source control and documentation

- All source is kept in git. Access to a repo MUST be removed when someone leaves the project or the university (WSU developer standards, 2024). No check exists yet.
- The stack's code has its primary home on git.wayne.edu; the standards name git.wayne.edu as the preferred home.
- Register the project in the catalog Enterprise Apps names, and keep its environments current. Stack apps also register with Rollcall, the stack's census (WSU developer standards, 2024).
- Every repo's README MUST say how to install, configure and deploy the code for local development, on every OS (see `every-os.md`), and how to update an existing deployment (WSU developer standards, 2024). No check exists yet.
- If the Help Desk might be contacted about the project, keep an overview in the repo: what it does, who uses it, known common issues and who to call for an incident; deliver changes to the Help Desk when it changes (WSU developer standards, 2024).

## Static analysis

- PHPStan level 8 for new code: the stack's own packages and every app
  Sourdough generates (the stack's coding standard).
- Larastan loads only where Laravel is present (the stack's coding standard).
- Every inline ignore carries an identifier and a reason; configs set
  `reportIgnoresWithoutComments` (the stack's coding standard).
- PHPStan's `phpVersion` follows the policy's PHP floor (the stack's coding standard).
- Existing apps start at the highest level they pass, with a committed
  baseline that MUST NOT grow, then raise the level one step at a time
  (the stack's coding standard). No check exists yet. Like the band says,
  the only way is Higher.
- New packages have no baseline (the stack's coding standard).
- Two reasons for level 8:
  - The starters begin clean, so new apps pass from day one (the stack's coding standard).
  - Null handling is the class of bug level 8 adds; in the stack's auth and
    header code, a null bug is a security bug (the stack's coding standard).

## Input and files

- All input (forms, API calls, headers, cookies, and data read from a database you do not control) MUST be validated on the server; SQL uses bound parameters, and output is escaped for where it lands. That guards against SQL injection and cross-site scripting (WSU developer standards, 2024). No check exists yet.
- Files from untrusted sources MUST be checked against the expected type, MUST NOT be passed to include, class-loading or reflection calls, MUST be stored outside the served folder with limited permissions, and MUST NOT be executable (WSU developer standards, 2024). No check exists yet.

## Banned patterns

Each ban becomes a PHPStan rule: Larastan's where it has one, `phpstan-disallowed-calls`
for plain bans, and a custom rule only when neither fits.

- `env()` only in `config/` files, from Larastan's
  `NoEnvCallsOutsideOfConfig` rule. PROCTOR check: `env-outside-config`
  (a warning per call); Pinstripe's Laravel cut turns Larastan's rule on
  (`noEnvCallsOutsideOfConfig`, Pinstripe 0.1.4).
- No disabled TLS verification, such as `withoutVerifying()` or
  `CURLOPT_SSL_VERIFYPEER` set to false (OWASP ASVS).
  `withoutVerifying()` is banned by `pinstripe.banned.withoutVerifying`
  (Pinstripe 0.2.0). Since Pinstripe 0.3.0 the rest are banned too:
  `CURLOPT_SSL_VERIFYPEER` set to false or `CURLOPT_SSL_VERIFYHOST` set to 0
  (`pinstripe.banned.curlVerifyOff`), a stream context that skips the peer
  check (`pinstripe.banned.streamVerifyOff`) and Guzzle's `verify` set to
  false (`pinstripe.banned.verifyFalse`).
- No SQL built by joining strings; use bindings (OWASP SQL Injection
  Prevention Cheat Sheet). Banned since Pinstripe 0.3.0 as
  `pinstripe.banned.joinedSql`: a variable joined into the SQL given to PDO,
  mysqli, `oci_parse()` (and the old `ociparse()`) or Laravel's `DB::` calls
  fails; literal SQL with bindings passes.
- No debug leftovers or dynamic code: `eval()`, `extract()`, `var_dump()`,
  `dd()` and `dump()`. `phpstan-disallowed-calls` bans them as
  `pinstripe.banned.eval`, `pinstripe.banned.extract`,
  `pinstripe.banned.varDump`, `pinstripe.banned.dd` and
  `pinstripe.banned.dump` (Pinstripe 0.2.0,
  https://pinstripe.apps.wayne.edu/docs/reference/banned-calls.html).

## Vue and JavaScript

- ESLint flat config (the stack's coding standard).
- `eslint-plugin-vue` `flat/recommended` (the stack's coding standard).
- Prettier for formatting (the stack's coding standard).
- `eslint-plugin-vuejs-accessibility` (the stack's coding standard).
- Both JavaScript and TypeScript are linted (the stack's coding standard).
- The stack's Vue starters use TypeScript; plain JavaScript stays fully
  supported (the stack's coding standard).
- Node 22.13 or newer for the lint tools (the stack's coding standard).

## Security analysis

- The stack's GitLab instances run the Free tier,
  so nothing relies on GitLab SAST (the stack's coding standard).
- The open tools instead: Larastan's env rule, `phpstan-disallowed-calls`,
  and a Psalm taint-analysis pilot on one app (the stack's coding standard).

## How it is enforced

- Pint, PHPStan, Rector dry-runs and ESLint run in CI through Preflight
  components, not in the deploy gate (the stack's coding standard). Preflight 0.5.0
  ships the `pint`, `phpstan` and `eslint` components; a Rector dry-run component
  is Planned.
- New rules arrive as warnings and become errors on a stated date, the same
  staging as PHP versions (the stack's enforcement design).
- PROCTOR reports whether an app uses the presets (the stack's coding standard). The PROCTOR
  report is Planned.

## Which preset enforces each rule

| Rule | Preset file | Status |
|---|---|---|
| PHP style, Laravel apps | `pint/laravel.json` | Built (Pinstripe 0.1.4) |
| PHP style, raw PHP apps and stack packages | `pint/per.json` | Built (Pinstripe 0.1.4) |
| Static analysis, level 8 | `phpstan/base.neon` | Built (Pinstripe 0.1.4) |
| Static analysis, raw apps | `phpstan/raw.neon` | Built (Pinstripe 0.1.4) |
| Static analysis, Laravel apps | `phpstan/laravel.neon` | Built (Pinstripe 0.1.4) |
| Banned patterns | `phpstan/disallowed.neon` | Built (Pinstripe 0.2.0; the joined SQL and TLS-off bans in 0.3.0) |
| Vue and JavaScript | `eslint/javascript.js` and `eslint/typescript.js` (npm `@waynestate/pinstripe`) | Built (Pinstripe 0.1.4) |
| Security analysis | `phpstan/laravel.neon` (the env rule) and `phpstan/disallowed.neon` (the banned calls) are built; the Psalm taint pilot is not yet | Planned |

## Not adopted

- Mago and PHP_CodeSniffer 4 are not adopted; Pint is the only formatter
  (the stack's coding standard).
- Mago is looked at again before Pinstripe 1.0 (the stack's coding standard).

## To be decided

- `declare(strict_types=1)` in every file. The sources recommend it, but
  the stack's coding standard does not decide it.
- `phpstan-strict-rules` for the stack's packages (the stack's coding standard).
- Where the ESLint package is published (the stack's coding standard).

## Sources

- PER Coding Style 3.1: https://www.php-fig.org/per/coding-style/
- PSR-1 Basic Coding Standard: https://www.php-fig.org/psr/psr-1/
- Laravel Pint, 13.x docs: https://laravel.com/docs/13.x/pint
- PHPStan, rule levels: https://phpstan.org/user-guide/rule-levels
- PHPStan, baseline: https://phpstan.org/user-guide/baseline
- PHPStan, configuration reference: https://phpstan.org/config-reference
- PHPStan, ignoring errors: https://phpstan.org/user-guide/ignoring-errors
- Larastan: https://github.com/larastan/larastan
- Larastan, Laravel-specific rules:
  https://github.com/larastan/larastan/blob/3.x/docs/rules.md
- spaze/phpstan-disallowed-calls:
  https://github.com/spaze/phpstan-disallowed-calls
- ESLint, configuration files:
  https://eslint.org/docs/latest/use/configure/configuration-files
- eslint-plugin-vue user guide: https://eslint.vuejs.org/user-guide/
- Prettier rationale: https://prettier.io/docs/rationale
- eslint-plugin-vuejs-accessibility docs:
  https://vue-a11y.github.io/eslint-plugin-vuejs-accessibility/
- Node.js release schedule: https://github.com/nodejs/Release
- Psalm, security analysis: https://psalm.dev/docs/security_analysis/
- GitLab SAST: https://docs.gitlab.com/user/application_security/sast/
- OWASP SQL Injection Prevention Cheat Sheet:
  https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html
- GitLab Code Quality: https://docs.gitlab.com/ci/testing/code_quality/
- OWASP ASVS: https://asvs.owasp.org/
