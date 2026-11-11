

# Pinstripe

<p>

</p>

**Sharp code. Every line.**

The coding standard for the Wayne State app stack: shared presets for Pint, PHPStan (with Larastan for Laravel), Rector,
and ESLint with Prettier. Your app points at them and adds only its own exceptions. Pinstripe is to code what a good
suit is to a job interview: nobody notices it, everybody notices when it's missing.

**Not to be confused with:** Pinstripe is the code style settings you run locally; Preflight runs them, and PROCTOR, in CI.
Pinstripe's Rector set modernises code within the app's current PHP version, while Booster upgrades the app to a newer
PHP version.

Checks: `composer ci` (the same command runs on GitLab and GitHub).

Documentation: the docs site is at [`site/docs/index.html`](site/docs/index.html), built from `docs/` by Squire's shared builder and kept current by `composer ci`. The pages wear the front page's look through `docs/_header.html` and `docs/theme/pinstripe.css`: edit those two files, never the vendored kit.

## Where the code lives

The canonical repository is [enterprise-apps/pinstripe](https://git.wayne.edu/enterprise-apps/pinstripe) on git.wayne.edu: clone over SSH (`git@git.wayne.edu:enterprise-apps/pinstripe.git`) or HTTPS (`https://git.wayne.edu/enterprise-apps/pinstripe.git`). Open issues and merge requests there, and tags are made there, by the maintainer, with an approver.
GitHub ([waynestate/pinstripe](https://github.com/waynestate/pinstripe)) is a read-only push mirror of it, for the published packages only: nobody pushes to it, and Packagist and npm read from it.
The pinstripes are sewn on git.wayne.edu; the GitHub mirror is the off-the-rack copy.

Front page: [`site/index.html`](site/index.html), a static tailor's shop you can open in a browser or serve from `site/`.
On Citadel it is served by the raw-PHP deploy layer in [`deploy/`](deploy/): a Squire kit whose front controller serves `site/` behind HELMET's security headers. For local development, keep serving `site/` directly.


<div align="center"><sub>Seven pieces, one tape. Every mark is read from the preset files in this repo; with reduced motion on, the tape stays unrolled.</sub></div>

<details>
<summary><b>Try it on</b>: three cuts from <code>site/</code> at phone width</summary>
<br>

</details>

<details>
<summary><b>The pattern book</b>: the brand board</summary>
<br>

</details>

## Use it in a raw PHP app

Add Pinstripe as a dev dependency:

```
composer config allow-plugins.phpstan/extension-installer true
composer require --dev waynestate/pinstripe:^0.4
```

Pinstripe loads its PHPStan rules through `phpstan/extension-installer`, and Composer will not ask when it cannot prompt.
If Composer stops because a package your app already locks (often an older PHPStan) does not fit, run the same `composer require` again with `-W`, as Composer suggests, so it may update that package too.

`^0.4` gets you 0.4.0, the current release, with every [banned call](#banned-calls) below. Packagist reads the GitHub
mirror, which can trail a release by a while. If Composer says it cannot find a 0.4 version, point it at the
canonical repository first (it asks for your git.wayne.edu sign-in), then run the `composer require` again:

```
composer config repositories.pinstripe vcs https://git.wayne.edu/enterprise-apps/pinstripe.git
```

The npm package, `@waynestate/pinstripe`, is published by hand after each tag; `npm view @waynestate/pinstripe version`
shows where it is. The ESLint and Prettier presets have not changed since 0.1.4, so any version from 0.1.4 up is
today's cut.

Point PHPStan at the raw config. `phpstan.neon`:

```neon
includes:
    - vendor/waynestate/pinstripe/phpstan/raw.neon

parameters:
    paths:
        - app
```

Add Composer scripts that run on every OS:

```json
{
    "scripts": {
        "lint": "pint --test --config vendor/waynestate/pinstripe/pint/per.json",
        "fix": "pint --config vendor/waynestate/pinstripe/pint/per.json",
        "analyse": "phpstan analyse"
    }
}
```

### Show Pint findings in the merge request

Add a second script that writes the report to a file:

```json
{
    "scripts": {
        "lint:gitlab": "pint --test --config vendor/waynestate/pinstripe/pint/per.json --format=gitlab > pint-codequality.json"
    }
}
```

Point GitLab at the artifact, and keep it when the job fails, which is exactly when it has something to show:

```yaml
artifacts:
    when: always
    reports:
        codequality: pint-codequality.json
```

`--format=gitlab` writes GitLab's Code Quality JSON and `[]` when the code is clean, and Pinstripe tests both. Chalk marks wash out; these findings stay pinned to the merge request.

### Show PHPStan findings in the merge request

PHPStan writes the same report with its own `gitlab` error format:

```json
{
    "scripts": {
        "analyse:gitlab": "phpstan analyse --no-progress --error-format=gitlab > phpstan-codequality.json"
    }
}
```

```yaml
artifacts:
    when: always
    reports:
        codequality: phpstan-codequality.json
```

Each finding carries its identifier (`pinstripe.banned.joinedSql`, `argument.type`) as the check name. Pinstripe tests
both sides: the raw preset turns the null bug fixture into a non-empty report and the clean fixture into `[]`. A
fitting note, pinned where the reviewer is already looking.

`composer lint` fails on style. `composer fix` fixes it. Pint's `per` preset is the PER Coding Style, and `tests/fixtures/per` keeps a clean and a messy proof.
`composer analyse` runs PHPStan at level 8, with deprecation checks too.
Pinstripe ships `phpstan/phpstan-deprecation-rules`, loaded automatically by
`phpstan/extension-installer`. Calling a `@deprecated` method fails the build.
The tailor flags the old cut before the fabric is retired.

## Require strict types

Opt-in. Point `composer lint` and `composer fix` at the strict preset instead of `per.json`:

```json
{
    "scripts": {
        "lint": "pint --test --config vendor/waynestate/pinstripe/pint/per-strict.json",
        "fix": "pint --config vendor/waynestate/pinstripe/pint/per-strict.json"
    }
}
```

`composer fix` adds `declare(strict_types=1);` to every file it touches, so run your tests first and commit the change on its own. Strict types leave no loose threads.

### The careful route on a big app

A file that passes `'3'` to an `int` parameter works today and throws a `TypeError` the day it turns strict. On an old
app, let Rector declare strict types only where nothing changes: `rector/strict-types.php` runs Rector's
`SafeDeclareStrictTypesRector` and nothing else, so it skips every file whose calls, returns or property assignments
lean on PHP's type juggling. Keep it in a config of its own, so the diff is only declarations:

```php
<?php

// rector-strict-types.php
$strict = require __DIR__ . '/vendor/waynestate/pinstripe/rector/strict-types.php';

return $strict->withPaths([__DIR__ . '/src']);
```

`vendor/bin/rector process --dry-run -c rector-strict-types.php` lists the files that are ready, and the same command
without `--dry-run` declares them. Run it again after other fixes add types, and the list grows. Switch `composer lint`
to `per-strict.json` once every file is declared, so new files cannot slip in without it. Proven on fixtures: the
preset must declare `tests/fixtures/rector-strict-types/src/Measure.php` and leave `Hem.php`, which passes a string to
`str_repeat()`, alone. Measure twice, declare once.

## Use it in a Laravel app

Same, with the Laravel parts:

```
composer config allow-plugins.phpstan/extension-installer true
composer require --dev waynestate/pinstripe:^0.4 larastan/larastan
```

Pinstripe loads its PHPStan rules through `phpstan/extension-installer`, and Composer will not ask when it cannot prompt.
If Composer stops because a package your app already locks (often an older PHPStan) does not fit, run the same `composer require` again with `-W`, as Composer suggests, so it may update that package too.
Pinstripe pulls in `phpstan/extension-installer`, which registers Larastan for you. When Composer asks, allow the
plugin (`config.allow-plugins` in your `composer.json`). Your `phpstan.neon` includes
`vendor/waynestate/pinstripe/phpstan/laravel.neon` and nothing else: there is no Larastan line to add, and the
scripts point at `vendor/waynestate/pinstripe/pint/laravel.json`.

The Laravel preset also rejects `env()` outside `config/`: once the config is cached, `env()` returns null, so
read config with `config()` instead. The rule is Larastan's, and `phpstan/laravel.neon` pins it on. The
environment is not a hand-me-down: take the config out of the wardrobe.

Proven on Laravel: `composer ci` lints a minimal Laravel 13 app (`tests/fixtures/laravel-app`) with
`pint/laravel.json` and analyzes it with `phpstan/laravel.neon` on every run, and fails if the app stops
passing. A smaller fixture (`tests/fixtures/laravel`) proves the preset really loads Larastan: it plants an
undefined Eloquent property that only Larastan reports, and a clean twin it leaves alone. The combined fixture
`tests/fixtures/laravel-vue` is one app tree with both PHP and Vue files, with `resources/js` beside `app/`.
`composer ci` lints its PHP side with Pint and PHPStan, and `npm test` lints its Vue side with ESLint and Prettier,
on every run, with one planted PHP error and two planted Vue defects that each preset must report.
`tests/fixtures/raw-vue` is the same proof for a plain PHP app with a Vue front end and no Laravel, with one
planted PHP error for the raw preset and the same two planted Vue defects.

`tests/fixtures/laravel-inertia` is the same proof for WELD's shape, Laravel with Inertia and Vue: an Inertia
controller and a page under `resources/js/Pages`, with the same planted PHP error and Vue defects.

## Use it for Vue

Add Pinstripe as a dev dependency:

```
npm install --save-dev @waynestate/pinstripe eslint prettier
```

Create `eslint.config.js` and spread the preset. For TypeScript:

```js
import pinstripe from '@waynestate/pinstripe/eslint/typescript'

export default [...pinstripe]
```

For plain JavaScript, import `@waynestate/pinstripe/eslint/javascript` instead.

The TypeScript preset lints with types (typescript-eslint's `recommendedTypeChecked`, through `projectService`), so
it catches what the syntax alone cannot, such as a promise nobody awaits: `save()` called without `await` fails lint
with `@typescript-eslint/no-floating-promises`, in a `.ts` file and in a `.vue` file alike. It reads the app's
`tsconfig.json`, so that file must include the `.ts` and `.vue` files you lint. Typed linting is slower than syntax
linting, so run it in CI rather than on every save. Proven on fixtures: `tests/js/typed` holds a clean `.ts` file
that must pass and a floating promise in `.ts` and in `.vue` that must each fail (`npm test`).

Point Prettier at the preset in `package.json`:

```json
{
    "prettier": "@waynestate/pinstripe/prettier"
}
```

Add scripts that run on every OS:

```json
{
    "scripts": {
        "lint": "eslint .",
        "format": "prettier --write ."
    }
}
```

`npm run lint` fails on style and accessibility, and on every Vue and accessibility finding: where the plugins'
recommended configs report a rule as a warning, Pinstripe reports it as an error, so the editor, a local run and CI
all agree on what fails. `npm run format` fixes the layout. The preset is the off-the-rack suit: it fits every app,
and you only add your own cufflinks. If an app adopts Oxlint for speed, ESLint stays in the toolchain for `.vue`
files, because Pinstripe's Vue and accessibility rules (eslint-plugin-vue and eslint-plugin-vuejs-accessibility) run
only in ESLint. Page components under `Pages/` or `pages/` (Inertia) may have one-word names such as `Index`;
everywhere else `vue/multi-word-component-names` still applies.

### Adopt gradually with the -warn presets

An app that cannot fix every finding in one cut keeps the old behavior with the `-warn` twin of the preset: the same
rules with the plugins' original severities, so findings that were warnings stay warnings.

```js
import pinstripe from '@waynestate/pinstripe/eslint/javascript-warn'

export default [...pinstripe]
```

For TypeScript, import `@waynestate/pinstripe/eslint/typescript-warn` instead. Once the codebase is clean, switch
the import back to the strict preset, and every Vue and accessibility finding fails lint.

### Show ESLint findings in the merge request

Add a second script that uses the gitlab formatter:

```json
{
    "scripts": {
        "lint:gitlab": "eslint . --format gitlab"
    }
}
```

Point GitLab at the artifact, and keep it when the job fails:

```yaml
artifacts:
    when: always
    reports:
        codequality: gl-codequality.json
```

`--format gitlab` comes from `eslint-formatter-gitlab`, a dependency of Pinstripe, so it resolves from the app's own
`node_modules` without anything extra to install. In GitLab CI the formatter reads the job's predefined variables:
`CI_JOB_NAME` picks the job, `CI_PROJECT_DIR` keeps the report's paths relative to the project root, and the report
path itself is read from that job's `artifacts: reports: codequality:` line in the CI config. When the config lives
somewhere else (`CI_CONFIG_PATH` is empty or a URL), set `ESLINT_CODE_QUALITY_REPORT` to name the file instead.
Pinstripe tests the formatter end to end: the bad Vue fixture becomes a non-empty Code Quality report. The chalk
marks move into the merge request.

## Format Blade views

Pinstripe's Prettier preset formats Blade templates too. It loads `prettier-plugin-blade`, a third-party Prettier
plugin that Pinstripe depends on and pins to an exact version (3.3.3 today), so the Blade rules travel with the preset
and cannot drift. An app that points Prettier at the preset gets Blade formatting with no extra install: Prettier
resolves the `plugins` entry relative to the config file's package, and the plugin is a dependency of
`@waynestate/pinstripe`, so the bare name resolves from any app that extends this config.

The preset adds an override for `*.blade.php` that sets the `blade` parser and a 4-space tab width. The first time you
run Prettier over an app, every Blade file changes once: the indent becomes 4 spaces, a space goes after directive
names (`@vite (` instead of `@vite(`), and void tags self-close (`<input>` becomes `<input />`). That is a one-time
reformat, not a recurring change.

To reformat an existing app's views in one go:

```
npx prettier --write "resources/views/**/*.blade.php"
```

### Adopting

Do the reformat as its own commit, with nothing else in it, so the churn is easy to review and easy to back out. Then
list that commit's hash in `.git-blame-ignore-revs` so `git blame` skips the reformat and stays useful:

```
# .git-blame-ignore-revs
# One hash per line; blank lines and # comments are ignored.
a1b2c3d4e5f60718293a4b5c6d7e8f9012345678
```

and tell git to read it:

```
git config blame.ignoreRevsFile .git-blame-ignore-revs
```

With that in place, blame points at the person who last changed a line's meaning, not at the reformat.

## Use it for Rector

Rector modernises code for the PHP version your app already supports. Add it as a dev dependency:

```
composer require --dev rector/rector
```

Create `rector.php` and use the Pinstripe preset, adding only your own paths:

```php
<?php

$base = require __DIR__ . '/vendor/waynestate/pinstripe/rector/base.php';

return $base->withPaths([__DIR__ . '/src']);
```

The preset reads the PHP floor from your app's own `composer.json`, so it cannot drift out of step with the version
the app actually runs on. Check with `vendor/bin/rector process --dry-run` and apply with `vendor/bin/rector process`.
CREED's PHP floor reaches your app through PROCTOR: its `php-floor` check fails an app whose `require.php` still allows an end-of-life PHP, Booster moves the constraint, and Rector and PHPStan follow with no edit to the preset.

The preset also turns on Rector's composer-based Laravel sets (`withComposerBased(laravel: true)`). Add
`driftingly/rector-laravel` as a dev dependency and it applies the Laravel upgrade rules that match your app's
installed `laravel/framework` version; without it the call is a no-op, so an app that does not want Laravel rules
sees no change.

Proven on fixtures: `composer ci` runs the preset against a modern app (`tests/fixtures/rector-clean`) and an
old-style one (`tests/fixtures/rector-old`) on every run, and fails if the clean one changes or the old one is left
alone. Rector is the tailor's press: it smooths old code to the standard without changing the cut.

### One level at a time, on a big old app

On an app that has never met Rector, the base preset's four whole sets make one diff nobody can review. The opt-in
`rector/levels.php` returns a function instead: name a rule family and how far up it goes, and Rector applies only
that family's first rules, safest first.

```php
<?php

$levels = require __DIR__ . '/vendor/waynestate/pinstripe/rector/levels.php';

return $levels(deadCode: 0)->withPaths([__DIR__ . '/src']);
```

The families are `deadCode`, `typeCoverage`, `codeQuality` and `php` (the PHP-version rules, still capped at your
`require.php`), and each one stays off until you name it. Level 0 is the first rule, not "off". Dry-run, review,
apply, commit, then raise the number by one in the next merge request; when every family runs whole, switch to
`rector/base.php`. Three fixture configs in `tests/fixtures/rector-levels` prove that level 0 makes only the first
change, level 2 adds the next ones, and one family never applies another's rules. A suit is altered one fitting at a
time.

## Banned calls

Every cut (raw, Laravel and strict) fails the build on a short list of calls no Wayne State app should ship. The list
is `phpstan/disallowed.neon`, included from `base.neon`, and the rules are
[spaze/phpstan-disallowed-calls](https://github.com/spaze/phpstan-disallowed-calls), which Pinstripe requires and the
extension installer loads, so your `phpstan.neon` needs no new line. A `dd()` left in after debugging now turns the
pipeline red, the same as any other PHPStan error.

| Call | Why it is banned | Use instead | OWASP Top 10:2025 | ASVS 5.0 |
|---|---|---|---|---|
| `eval()` | Runs a string as PHP, so one untrusted value is remote code execution. | Write the code out, or pick a callable with `match`. | A05 Injection | V1.3.2 |
| `extract()` | Turns array keys into variables, so request data can overwrite any variable in scope. | Read the keys you need by name, or destructure the array. | A05 Injection | V15.3.3 (closest: mass assignment) |
| `var_dump()` | Debug output left in ships paths, config and secrets to the browser. | A PSR-3 logger (`Log::debug()` in Laravel), or Xdebug. | A02 Security Misconfiguration | V13.4.2 |
| `dd()` | Dumps internals to the browser and halts the request in production. | The same. | A02 Security Misconfiguration | V13.4.2 |
| `dump()` | The same leak as `var_dump()`, Symfony and Laravel flavored. | The same. | A02 Security Misconfiguration | V13.4.2 |
| Laravel's `->dd()` and `->dump()` methods | The same leaks, called on a collection, query, request or HTTP response (`$users->dd()`, `->dumpRawSql()`, `Http::get($url)->dd()`). | The same. | A02 Security Misconfiguration | V13.4.2 |
| `withoutVerifying()` | Turns off TLS certificate checks in Laravel's HTTP client (`PendingRequest`), so anyone on the path can read or change the traffic. | Keep verification on; for a private CA, pass its bundle through `withOptions` and the `verify` option. | A02 Security Misconfiguration | V12.3.2 |
| `'verify' => false` | The same hole through Guzzle's `verify` option: Laravel's `withOptions()`, `new Client([...])`, and the options of `request()`, `send()`, `get()`, `post()` and the other verbs. | Set `verify` to the path of the CA bundle, or leave it out. | A02 Security Misconfiguration | V12.3.2 |
| `curl_setopt()` and `curl_setopt_array()` with `CURLOPT_SSL_VERIFYPEER` false or `CURLOPT_SSL_VERIFYHOST` 0 | Turns off curl's certificate or host name check in raw PHP. | Keep both on; for a private CA, point `CURLOPT_CAINFO` at its bundle. | A02 Security Misconfiguration | V12.3.2 |
| `stream_context_create()` with `ssl` `verify_peer` or `verify_peer_name` false, or `allow_self_signed` true | Turns off the same checks for `file_get_contents()` and the other stream functions. | Keep them on; for a private CA, set the `ssl` `cafile` option. | A02 Security Misconfiguration | V12.3.2 |
| SQL joined from strings: `PDO::query()`, `prepare()` and `exec()`, mysqli (`query()`, `prepare()`, `mysqli_query()` and the rest), `oci_parse()` and its old alias `ociparse()`, and Laravel's `DB::select()`, `insert()`, `update()`, `delete()`, `statement()`, `scalar()`, `cursor()` and `fromQuery()` | A variable joined into the SQL (`'... id = ' . $id`, `"... {$id}"`, `sprintf()`) lets one untrusted value rewrite the query. | Keep the SQL a literal and bind every value (`?` or `:name` placeholders, `oci_bind_by_name()`). | A05 Injection | V1.2.4 |

The ASVS column names the requirement each ban enforces in [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en). ASVS has no requirement for `extract()` by name; mass assignment (V15.3.3) is the closest.

Each ban has a stable identifier: `pinstripe.banned.eval`, `pinstripe.banned.extract`, `pinstripe.banned.varDump`,
`pinstripe.banned.dd`, `pinstripe.banned.dump`, `pinstripe.banned.withoutVerifying`, `pinstripe.banned.verifyFalse`,
`pinstripe.banned.curlVerifyOff`, `pinstripe.banned.streamVerifyOff` and `pinstripe.banned.joinedSql`.

The SQL ban stands on PHPStan's own `literal-string` type: literal pieces joined together, a class constant, a literal
`.=` on a literal, and placeholders built with `implode()` from literals all stay literal and pass, while anything
with a variable in it does not (an `int` too: bind it). Laravel already types `DB::raw()`, `DB::unprepared()` and the
query builder's `whereRaw()`, `selectRaw()`, `orderByRaw()` and other raw methods as `literal-string`, so PHPStan
reports a joined string there by itself (`argument.type`); Pinstripe covers the connection calls Laravel leaves as
plain `string`. SQL that is safe but not literal (read from a `.sql` file, or a helper's return value) can say so: type
the helper `@return literal-string`, or keep the call with a reasoned ignore. Apps that keep their queries in `.sql`
files clear every report with one typed reader; the
[Banned calls](https://pinstripe.apps.wayne.edu/docs/reference/banned-calls.html#sql-kept-in-sql-files) page has it.
A wrapper that takes the whole SQL as a plain `string` is reported once, inside, and never at its callers, so type its
parameter `literal-string` and PHPStan checks each caller; never baseline a wrapper. The PDO and mysqli methods are
matched by the connection's type, so a connection PHPStan cannot type (a `global $db` from an old include) hides
every joined query made on it: name the type (`/** @var \PDO $db */`, or better a typed property) and they are
reported. Postgres's `pg_query()` is not
covered, since its connection argument is optional and the SQL has no fixed position.

Only the off switch is banned. Setting `CURLOPT_SSL_VERIFYPEER` to true, `verify` to a CA bundle, or `false` on an
option that has nothing to do with TLS passes. The `curl_setopt()` ban is the one Pinstripe writes itself
(`src/PHPStan/CurlVerifyOffRule.php`), because it needs the option and the value together, which
spaze/phpstan-disallowed-calls cannot match; it reads and links like the others.

Laravel's methods share the functions' identifiers: `dd()`, `ddRawSql()`, `ddHeaders()`, `ddBody()`, `ddJson()` and
`ddSession()` report as `pinstripe.banned.dd`, and `dump()`, `dumpRawSql()`, `dumpHeaders()` and `dumpSession()` as
`pinstripe.banned.dump`. Only methods Laravel itself declares are caught, so your own `dump()` method is fine, and
so is the schema dump behind `php artisan schema:dump`.

The `withoutVerifying()` ban catches both `Http::withoutVerifying()` and the method partway down a chain such as
`Http::acceptJson()->withoutVerifying()`. Each message ends with its OWASP tag and carries a link to the
[Banned calls](https://pinstripe.apps.wayne.edu/docs/reference/banned-calls.html) page.

**Keeping one on purpose.** Use the ban's identifier and say why, as with any other ignore:

```php
// @phpstan-ignore pinstripe.banned.varDump (CLI-only diagnostics; output goes to the operator's terminal)
var_dump($report);
```

Fix the ones an existing app already has rather than baselining them: each is usually one line, and a baseline
would hide the next one too. Joined SQL is the one an older app may have dozens of: bind what you can, and
`pinstripe-baseline` can hold the rest while they come out (a baseline only shrinks, so no new one gets in).

**Adding your own.** List more bans under the same parameters (`disallowedFunctionCalls`, `disallowedMethodCalls`,
`disallowedStaticCalls`) in your app's `phpstan.neon`. NEON appends lists, so yours join Pinstripe's.

**Not covered yet.** An off switch PHPStan cannot pin to a value: `'verify' => $verify`, or a curl option read from
config. Those pass, so keep the value a literal where you can.

Proven on fixtures: `tests/fixtures/raw-disallowed` makes each banned call once and must be reported for each, by
identifier and with its OWASP tag, in both the raw and strict cuts; its clean twin defines methods named `dump()` and
`dd()` and must stay green; `tests/fixtures/laravel/src/LeftoverDebugMethods.php` leaves seven of Laravel's `dd()`
and `dump()` methods in and must be reported for each, while its twin `SchemaDump.php` stays green;
`tests/fixtures/laravel/src/InsecureHttp.php` turns TLS checks off both ways; `InsecureTransport.php` (raw curl and
streams, eight ways) and `VerifyFalse.php` (Laravel and Guzzle, five ways) must be reported for each, while their
twins `SecureTransport.php` and `VerifiedHttp.php` stay green; `JoinedSql.php` (PDO, mysqli and oci8, nine ways)
and `JoinedQueries.php` (Laravel, six of Pinstripe's and three of Laravel's own) must be reported for each, while
`BoundSql.php` and `BoundQueries.php` bind their values and stay green; `SqlFiles.php` reads its SQL from `.sql`
files through a typed reader and wrapper and stays green, while `JoinedThroughAWrapper.php` must be reported at each
joined caller; `UntypedConnection.php` must pass a joined query on an untyped `global` connection and report the same
query once the connection is typed; and the
Laravel app fixture installs Pinstripe through Composer and must fail on a leftover `dd()` and on curl told to skip
the certificate check, which proves the curl rule loads from `vendor/`. A suit with a `dd()` in
the pocket does not leave the shop.

### Not banned yet

`md5()`, `sha1()`, `rand()` and `mt_rand()` are weak for passwords, tokens and signatures, but they are fine for cache
keys, checksums and other non-security uses, and apps across the fleet use them that way. Whether Pinstripe bans
them (and how it would allow the safe uses) is an open call, so for now they pass.

## The strict cut

New apps can include `vendor/waynestate/pinstripe/phpstan/strict.neon` instead of `raw.neon` or `laravel.neon`. It keeps the base cut and turns on `phpstan/phpstan-strict-rules`, so loose comparisons like `==` fail the build. Existing apps stay on the base cut and baseline their way over.

PHPStan checks phpdoc method signatures from level 3 up, and `phpstan/phpstan-strict-rules` forces the two switches behind them on for every run it is installed for. The base cut pins `reportMaybesInMethodSignatures` and `reportStaticMethodSignatures` off so it stays exactly as documented; the strict cut keeps them on, so it also reports maybe-mismatched overrides and static method overrides.

`phpstan/phpstan-strict-rules` forces eight more switches on for every run as well. Each one is decided below: low noise and real bugs stay on in every cut, noisy ones are pinned off in the base cut and turned on only in `strict.neon`.

| Parameter | Raw and Laravel cuts | Strict cut | Why |
|---|---|---|---|
| `checkExplicitMixedMissingReturn` | on | on | A function declared to return `mixed` that can fall off the end is a real bug, and rare in practice. |
| `checkFunctionNameCase` | on | on | A wrongly cased function name works on Windows and macOS and breaks on Linux. |
| `checkInternalClassCaseSensitivity` | on | on | The same case bug for PHP's own classes. |
| `checkStrictPrintfPlaceholderTypes` | on | on | `%d` fed a string is a real formatting bug, and rare. |
| `checkDynamicProperties` | off | on | Floods legacy code with undeclared properties. |
| `reportMaybesInPropertyPhpDocTypes` | off | on | Reports "maybe" phpdoc mismatches that legacy docblocks trip constantly. |
| `reportWrongPhpDocTypeInVarTag` | off | on | Old `@var` tags are often loose; fixing them is cleanup, not bug hunting. |
| `reportNonIntStringArrayKey` | off | on | Bleeding-edge check that is noisy on loosely keyed arrays. |

Strict is the tailor's chalk line drawn before the first stitch.

## Adopting on an existing app

Generate a baseline with `phpstan analyse --generate-baseline`, include it in your `phpstan.neon`, and only ever
shrink it. New code gets no baseline. A baseline is a tailor's chalk line: it marks the fabric as it is, and every
release you take it in, never let it out.

Some errors cannot go into a baseline (PHPStan says so, and the run stays red): fix how PHPStan finds that code
first. When the app bundles a third-party library, point Pint at your own folders instead of the whole app. The
[Proof](https://pinstripe.apps.wayne.edu/docs/reference/proof.html) page shows both on 82 Wayne State apps.

### Ignore comments must say why

Every cut sets `reportIgnoresWithoutComments` and `reportUnmatchedIgnoredErrors`, so the preset reports an
`@phpstan-ignore` that has no comment in parentheses and one whose identifier no longer matches an error on that
line. The correct form keeps the reason and the exit plan on the same line:

```php
public function name(?string $v): string
{
    // @phpstan-ignore return.type (why this is safe, and the plan to remove it)
    return $v;
}
```

Pinstripe tests all three cases: the bare ignore fails, the unmatched ignore fails, and the commented one passes.
An ignore without a reason is a pin left in the cushion: nobody remembers why it was there, and it pokes the next
tailor.

## Baselines only shrink

Pinstripe ships `pinstripe-baseline`, one PHP command that runs on every OS, to keep that promise. It reads both
the NEON baseline and the PHP format (`phpstan-baseline.php`).

A baseline is only for apps adopting Pinstripe late; new apps never set it. The app opts in from its own
`composer.json`:

```json
{
    "extra": {
        "pinstripe": {
            "legacy": true
        }
    }
}
```

Every command checks the flag before it reads the baseline. Without it, `pinstripe-baseline` exits 2 with:

```
composer.json: a baseline is only for apps adopting Pinstripe late. Add "extra": { "pinstripe": { "legacy": true } } to composer.json to opt in; new apps never set it.
```

In a monorepo, point the command at another composer.json with `--composer=<path>` (default: `composer.json` in the
current working directory).

```
vendor/bin/pinstripe-baseline count phpstan-baseline.neon
```

prints how many errors the baseline ignores: the sum of each entry's `count`, 1 when `count` is absent.

```
vendor/bin/pinstripe-baseline check --max=42 phpstan-baseline.neon
```

exits 1 with `baseline grew: N errors, the most allowed is M` when the count is above the limit, 0 otherwise. To
compare with the target branch instead of a fixed number, pass a git ref:

```
vendor/bin/pinstripe-baseline check --against=origin/main phpstan-baseline.neon
```

In GitLab CI the merge request's base SHA is already in the environment:

```
vendor/bin/pinstripe-baseline check --against=$CI_MERGE_REQUEST_DIFF_BASE_SHA phpstan-baseline.neon
```

A baseline is a tailor's chalk line, and this command is the tape measure: it fails the build the moment the line
gets longer. The policy behind this section is recorded in [docs/adr/0001-phpstan-level-and-baseline-policy.md](docs/adr/0001-phpstan-level-and-baseline-policy.md).

## Tested with

Maintainers: how to build, test and release this project, and where its data comes from, is in [MAINTAINING.md](MAINTAINING.md).

- Pint 1.32.1
- PHPStan 2.3.3
- PHPStan extension-installer 1.4.3
- PHPStan strict-rules 2.1.0
- Larastan 3.13.2
- Rector 2.8.2
- PHPStan disallowed-calls 4.15.0
- ESLint 10.13.0
- Prettier 3.9.10
- eslint-plugin-vue 10.12.0
- eslint-plugin-vuejs-accessibility 2.6.3
- typescript-eslint 8.72.1
- Node 22, 24 and 26 (the JS presets run in CI on all three; the floor stays 22.13)

## Tool floors

Can your app run Pinstripe's tools? Each row shows the floor the locked package itself declares, read verbatim from `composer.lock` and `package-lock.json` today and checked against them by `tests/ToolFloorsTest.php`, so a dependency bump forces a README update in the same change.

PHP tools:

| Tool | Locked version | Needs |
| --- | --- | --- |
| `laravel/pint` | v1.32.1 | `^8.3.0` |
| `phpstan/phpstan` | 2.3.3 | `^7.4\|^8.0` |
| `phpstan/phpstan-strict-rules` | 2.1.0 | `^7.4 \|\| ^8.0` |
| `phpstan/phpstan-deprecation-rules` | 2.0.5 | `^7.4 \|\| ^8.0` |
| `larastan/larastan` | v3.13.2 | `^8.2` |
| `rector/rector` | 2.8.2 | `^7.4\|^8.0` |
| `spaze/phpstan-disallowed-calls` | v4.15.0 | `^7.4 \|\| ^8.0` |

Node tools:

| Tool | Locked version | Needs |
| --- | --- | --- |
| `eslint` | 10.13.0 | `^20.19.0 \|\| ^22.13.0 \|\| >=24` |
| `eslint-plugin-vue` | 10.12.0 | `^18.18.0 \|\| ^20.9.0 \|\| >=21.1.0` |
| `typescript-eslint` | 8.72.1 | `^18.18.0 \|\| ^20.9.0 \|\| >=21.1.0` |
| `prettier` | 3.9.10 | `>=14` |

- Pinstripe itself needs PHP 8.3 or newer and Node 22.13 or newer, so the Pint floor is the binding PHP floor.
- Pint only reads and rewrites files, so an app still on an older PHP can run Pint with a PHP 8.3 or newer binary (for example a container) while the app itself keeps its runtime.
- ESLint 10 is what sets the Node floor: Node 20 before 20.19 is out.

Measure twice, cut once: these floors are cut from the lock files, and the test keeps the hem honest.

## Supply chain

Pinstripe's own installs are hardened in the repo's `.npmrc`, and apps should copy both lines into their own `.npmrc`:

```
ignore-scripts=true
min-release-age=3
```

`ignore-scripts=true` stops npm from running any lifecycle script (`preinstall`, `install`, `postinstall`) while installing dependencies, so a package cannot execute code merely by being installed. `min-release-age=3` makes npm refuse to install a release published fewer than 3 days ago, leaving time to pull a compromised publish before it reaches a lockfile. The stack's Renovate `minimumReleaseAge` rule waits the same 3 days before updating, so the two guards agree. New cloth rests on the rail for three days before it is cut.

The guard is proven, not claimed: `tests/js/npm-hardening.test.mjs` installs a local package whose `postinstall` drops a marker file, once with the flag and once without, and the marker appears only without it. The lock gets the same care: `eslint-config-prettier` is floored at `^10.1.8`, past the releases hit by CVE-2025-54313.

## Coming next

Nothing on the cutting table right now: the banned calls link to their CREED rule, and the PHP floor comes from CREED through PROCTOR. Preflight's pint, phpstan and eslint jobs already run your app's Pinstripe config.

## The cast

<div align="center"><sub>Fifteen parts, one fitting room. The README art is cut from the front page by
<a href="scripts/readme-art/README.md"><code>scripts/readme-art</code></a>; no suit was harmed, and every hem was pinned first.</sub></div>
