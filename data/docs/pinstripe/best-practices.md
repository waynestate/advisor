# Best practices

How Pinstripe keeps its own promises: every preset is enforced by a test that runs the real tool against a
fixture, so a preset that stops working fails `composer ci` here instead of failing a developer's app. A preset
without a test is a suit without a mirror: you cannot tell when it has gone wrinkled.

A preset is **Enforced** when a test asserts both directions: a clean fixture passes, and a deliberately broken
one is caught. The PHP presets run under `composer ci` (PHPUnit); the JavaScript presets run under `npm test`.

| Preset | Tool | Status | Enforced by |
| --- | --- | --- | --- |
| `pint/per.json` | Pint | Enforced | `PresetsTest::testPintPerPresetAcceptsTheCleanFixture`, `PresetsTest::testPintPerPresetRejectsTheMessyFixture` |
| `pint/laravel.json` | Pint | Enforced | `PresetsTest::testPintLaravelPresetAcceptsTheLaravelAppFixture` |
| `phpstan/raw.neon` | PHPStan | Enforced | `PresetsTest::testPhpStanRawConfigAcceptsTheCleanFixture`, `PresetsTest::testPhpStanRawConfigReportsTheNullBug` |
| `phpstan/laravel.neon` | PHPStan + Larastan | Enforced | `PresetsTest::testLaravelPresetLoadsLarastanThroughTheExtensionInstaller`, `PresetsTest::testPhpStanLaravelConfigAcceptsTheLaravelAppFixture`, `PresetsTest::testPhpStanLaravelConfigReportsTheUndefinedEloquentProperty` |
| `eslint/javascript.js` | ESLint | Enforced | `tests/js/presets.test.mjs` (`npm test`) |
| `eslint/typescript.js` | ESLint | Enforced | `tests/js/presets.test.mjs` (`npm test`) |
| `prettier/index.json` | Prettier | Draft | no test runs it yet |
