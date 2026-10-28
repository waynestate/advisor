# Pinstripe

The coding standard for the Wayne State app stack: shared presets for Pint, PHPStan (with Larastan for Laravel), Rector,
and ESLint with Prettier. Your app points at them and adds only its own exceptions. Pinstripe is to code what a good
suit is to a job interview: nobody notices it, everybody notices when it's missing.

[![GitHub CI](https://github.com/waynestate/pinstripe/actions/workflows/ci.yml/badge.svg)](https://github.com/waynestate/pinstripe/actions/workflows/ci.yml)
[![GitHub Actions](https://github.com/waynestate/pinstripe/actions/workflows/ci.yml/badge.svg)](https://github.com/waynestate/pinstripe/actions/workflows/ci.yml)

Checks: `composer ci` (the same command runs on GitLab and GitHub).

## Use it in a raw PHP app

Add Pinstripe as a dev dependency:

```
composer require --dev waynestate/pinstripe
```

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

`composer lint` fails on style. `composer fix` fixes it. `composer analyse` runs PHPStan at level 8.

## Use it in a Laravel app

Same, with the Laravel parts:

```
composer require --dev waynestate/pinstripe larastan/larastan
```

Pinstripe pulls in `phpstan/extension-installer`, which registers Larastan for you. When Composer asks, allow the
plugin (`config.allow-plugins` in your `composer.json`). Your `phpstan.neon` includes
`vendor/waynestate/pinstripe/phpstan/laravel.neon` and nothing else: there is no Larastan line to add, and the
scripts point at `vendor/waynestate/pinstripe/pint/laravel.json`.

Proven on Laravel: `composer ci` lints a minimal Laravel 13 app (`tests/fixtures/laravel-app`) with
`pint/laravel.json` and analyses it with `phpstan/laravel.neon` on every run, and fails if the app stops
passing.

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

`npm run lint` fails on style and accessibility. `npm run format` fixes the layout. The preset is the off-the-rack
suit: it fits every app, and you only add your own cufflinks.

## Adopting on an existing app

Generate a baseline with `phpstan analyse --generate-baseline`, include it in your `phpstan.neon`, and only ever
shrink it. New code gets no baseline. A baseline is a tailor's chalk line: it marks the fabric as it is, and every
release you take it in, never let it out.

## Tested with

- Pint 1.32.1
- PHPStan 2.3.3
- PHPStan extension-installer 1.4.3
- Larastan 3.13.2
- ESLint 10.13.0
- Prettier 3.9.10
- eslint-plugin-vue 10.12.0
- eslint-plugin-vuejs-accessibility 2.6.3
- typescript-eslint 8.72.1
- Node 22.13 or newer

## Coming next

Rector, PREFLIGHT jobs, and reading the PHP floor from CREED.
