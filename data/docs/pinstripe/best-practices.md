# Best practices

How Pinstripe keeps its own promises: every preset is enforced by a test that runs the real tool against a
fixture, so a preset that stops working fails `composer ci` here instead of failing a developer's app. A preset
without a test is a suit without a mirror: you cannot tell when it has gone wrinkled.

A preset is **Enforced** when a test asserts both directions: a clean fixture passes, and a deliberately broken
one is caught. The PHP presets run under `composer ci` (PHPUnit); the JavaScript presets run under `npm test`.
Both run in CI on GitLab and GitHub, so a preset that stops working fails the pipeline before it reaches an app.

| Preset | Tool | Status | Enforced by |
| --- | --- | --- | --- |
| `pint/per.json` | Pint | Enforced | `PresetsTest::testPintPerPresetAcceptsTheCleanFixture`, `PresetsTest::testPintPerPresetRejectsTheMessyFixture` |
| `Pint GitLab output` | Pint | Enforced | `PresetsTest::testPintGitlabFormatReportsTheMessyFixtureAsCodeQualityJson`, `PresetsTest::testPintGitlabFormatIsAnEmptyArrayForTheCleanFixture` |
| `ESLint GitLab output` | ESLint | Enforced | `tests/js/gitlab-formatter.test.mjs` |
| `pint/laravel.json` | Pint | Enforced | `PresetsTest::testPintLaravelPresetAcceptsTheLaravelAppFixture` |
| `pint/per-strict.json` | Pint | Enforced | `PresetsTest::testPintPerStrictPresetAcceptsTheStrictTypesCleanFixture`, `PresetsTest::testPintPerStrictPresetRejectsTheStrictTypesMissingFixture` |
| `pint/laravel-strict.json` | Pint | Enforced | `PresetsTest::testPintLaravelStrictPresetRejectsTheStrictTypesMissingFixture` |
| `phpstan/raw.neon` | PHPStan | Enforced | `PresetsTest::testPhpStanRawConfigAcceptsTheCleanFixture`, `PresetsTest::testPhpStanRawConfigReportsTheNullBug` |
| `phpstan/laravel.neon` | PHPStan + Larastan | Enforced | `PresetsTest::testLaravelPresetLoadsLarastanThroughTheExtensionInstaller`, `PresetsTest::testPhpStanLaravelConfigAcceptsTheLaravelAppFixture`, `PresetsTest::testPhpStanLaravelConfigReportsTheUndefinedEloquentProperty` |
| `Larastan env() rule` | PHPStan + Larastan | Enforced | `PresetsTest::testLaravelPresetReportsAnEnvCallOutsideConfig`, `PresetsTest::testLaravelPresetAcceptsAConfigCallOutsideConfig` |
| `PHPStan ignore comments` | PHPStan | Enforced | `PresetsTest::testPhpStanRawConfigReportsAnIgnoreWithoutAComment`, `PresetsTest::testPhpStanRawConfigReportsAnIgnoreThatMatchesNothing`, `PresetsTest::testPhpStanRawConfigAcceptsAnIgnoreWithAComment` |
| `rector/base.php` | Rector | Enforced | `PresetsTest::testRectorPresetAcceptsTheCleanFixture`, `PresetsTest::testRectorPresetReportsChangesOnTheOldFixture` |
| `eslint/javascript.js` | ESLint | Enforced | `tests/js/presets.test.mjs` (`npm test`) |
| `eslint/typescript.js` | ESLint | Enforced | `tests/js/presets.test.mjs` (`npm test`) |
| `prettier/index.json` | Prettier | Enforced | `tests/js/prettier.test.mjs` (`npm test`) |
| README "Tool floors" | composer.lock + package-lock.json | Enforced | `ToolFloorsTest::testToolFloorsMatchTheLockFiles` |
