# Best practices

How Pinstripe keeps its own promises: every preset is enforced by a test that runs the real tool against a
fixture, so a preset that stops working fails `composer ci` here instead of failing a developer's app. A preset
without a test is a suit without a mirror: you cannot tell when it has gone wrinkled.

A preset is **Enforced** when a test asserts both directions: a clean fixture passes, and a deliberately broken
one is caught. It is **Checked** when a test runs the real tool in one direction only: the clean side passes, or the
broken side is caught, but not both yet. The PHP presets run under `composer ci` (PHPUnit); the JavaScript presets
run under `npm test`.
Both run in CI on GitLab and GitHub, so a preset that stops working fails the pipeline before it reaches an app.

| Preset | Tool | Status | Enforced by |
| --- | --- | --- | --- |
| `pint/per.json` | Pint | Enforced | `PresetsTest::testPintPerPresetAcceptsTheCleanFixture`, `PresetsTest::testPintPerPresetRejectsTheMessyFixture` |
| `Pint GitLab output` | Pint | Enforced | `PresetsTest::testPintGitlabFormatReportsTheMessyFixtureAsCodeQualityJson`, `PresetsTest::testPintGitlabFormatIsAnEmptyArrayForTheCleanFixture` |
| `PHPStan GitLab output` | PHPStan | Enforced | `PresetsTest::testPhpStanGitlabFormatReportsTheNullBugAsCodeQualityJson`, `PresetsTest::testPhpStanGitlabFormatIsAnEmptyArrayForTheCleanFixture` |
| `ESLint GitLab output` | ESLint | Enforced | `tests/js/gitlab-formatter.test.mjs` (`npm test`: the bad fixture gives findings, the clean one an empty report) |
| `pint/laravel.json` | Pint | Enforced | `PresetsTest::testPintLaravelPresetAcceptsTheLaravelAppFixture`, `PresetsTest::testPintLaravelPresetRejectsTheMessyFixture` |
| `pint/per-strict.json` | Pint | Enforced | `PresetsTest::testPintPerStrictPresetAcceptsTheStrictTypesCleanFixture`, `PresetsTest::testPintPerStrictPresetRejectsTheStrictTypesMissingFixture` |
| `pint/laravel-strict.json` | Pint | Enforced | `PresetsTest::testPintLaravelStrictPresetAcceptsTheStrictTypesLaravelCleanFixture`, `PresetsTest::testPintLaravelStrictPresetRejectsTheStrictTypesMissingFixture` |
| `phpstan/raw.neon` | PHPStan | Enforced | `PresetsTest::testPhpStanRawConfigAcceptsTheCleanFixture`, `PresetsTest::testPhpStanRawConfigReportsTheNullBug` |
| `phpstan/strict.neon` | PHPStan + strict-rules | Enforced | `PresetsTest::testPhpStanStrictConfigAcceptsTheCleanFixture`, `PresetsTest::testPhpStanStrictConfigReportsTheLooseComparison` |
| `phpstan/disallowed.neon` (banned calls) | PHPStan + disallowed-calls | Enforced | `PresetsTest::testPhpStanReportsEveryBannedCall`, `PresetsTest::testPhpStanAcceptsMethodsThatShareTheBannedNames`, `PresetsTest::testPhpStanRawConfigAcceptsABannedCallKeptWithAReason`, `PresetsTest::testLaravelPresetReportsTlsVerificationTurnedOff` |
| `phpstan/laravel.neon` | PHPStan + Larastan | Enforced | `PresetsTest::testLaravelPresetLoadsLarastanThroughTheExtensionInstaller`, `PresetsTest::testPhpStanLaravelConfigAcceptsTheLaravelAppFixture`, `PresetsTest::testPhpStanLaravelConfigReportsTheUndefinedEloquentProperty` |
| `Larastan env() rule` | PHPStan + Larastan | Enforced | `PresetsTest::testLaravelPresetReportsAnEnvCallOutsideConfig`, `PresetsTest::testLaravelPresetAcceptsAConfigCallOutsideConfig` |
| `PHPStan ignore comments` | PHPStan | Enforced | `PresetsTest::testPhpStanRawConfigReportsAnIgnoreWithoutAComment`, `PresetsTest::testPhpStanRawConfigReportsAnIgnoreThatMatchesNothing`, `PresetsTest::testPhpStanRawConfigAcceptsAnIgnoreWithAComment` |
| `Laravel dd() and dump() methods banned` | PHPStan + Larastan | Enforced | `PresetsTest::testLaravelPresetReportsDdAndDumpMethodsLeftIn`, `PresetsTest::testLaravelPresetAcceptsASchemaDump` |
| `TLS checks turned off banned` | PHPStan (+ Pinstripe's curl rule) | Enforced | `PresetsTest::testPhpStanReportsTlsChecksTurnedOffInRawPhp`, `PresetsTest::testPhpStanAcceptsTlsChecksLeftOnInRawPhp`, `PresetsTest::testLaravelPresetReportsTheVerifyOptionSetToFalse`, `PresetsTest::testLaravelPresetAcceptsVerifyPointedAtACaBundle`, `PresetsTest::testPhpStanLaravelConfigReportsCurlVerifyOffInTheLaravelAppFixture` |
| `SQL joined from strings banned` | PHPStan (`literal-string`) + disallowed-calls | Enforced | `PresetsTest::testPhpStanReportsSqlJoinedFromStringsInRawPhp`, `PresetsTest::testPhpStanAcceptsLiteralSqlWithBoundValuesInRawPhp`, `PresetsTest::testPhpStanAcceptsSqlReadFromFilesThroughATypedWrapper`, `PresetsTest::testPhpStanReportsSqlJoinedThroughAWrapperAtTheCaller`, `PresetsTest::testLaravelPresetReportsSqlJoinedFromStrings`, `PresetsTest::testLaravelPresetAcceptsLiteralSqlWithBindings` |
| `Banned call tips link to the CREED rule` | PHPStan | Enforced | `tests/BannedCallLinksTest.php` (`BannedCallLinksTest::testEveryBanCarriesAnIdentifierAndATip` makes sure no entry goes without) |
| `rector/base.php` | Rector | Enforced | `PresetsTest::testRectorPresetAcceptsTheCleanFixture`, `PresetsTest::testRectorPresetReportsChangesOnTheOldFixture` |
| `rector/strict-types.php` | Rector | Enforced | `PresetsTest::testRectorStrictTypesPresetDeclaresOnlyTheSafeFile`, `PresetsTest::testRectorStrictTypesPresetLeavesTheCoercingFileAlone` |
| `rector/levels.php` | Rector | Enforced | `PresetsTest::testRectorLevelsPresetStartsWithTheFirstDeadCodeRuleOnly`, `PresetsTest::testRectorLevelsPresetRaisingTheLevelAddsTheNextRules`, `PresetsTest::testRectorLevelsPresetKeepsTheFamiliesApart`, `PresetsTest::testRectorLevelsPresetRefusesToRunWithNoFamilyNamed` |
| `eslint/javascript.js` | ESLint | Enforced | `tests/js/presets.test.mjs` (`npm test`) |
| `eslint/typescript.js` | ESLint | Enforced | `tests/js/presets.test.mjs` (`npm test`) |
| `prettier/index.json` | Prettier | Enforced | `tests/js/prettier.test.mjs` (`npm test`) |
| README "Tool floors" | composer.lock + package-lock.json | Enforced | `ToolFloorsTest::testToolFloorsMatchTheLockFiles` |
| This page | PHPUnit | Enforced | `BestPracticesTest::testEveryTestNamedHereExists`, `BestPracticesTest::testEveryFileNamedHereExists`, `BestPracticesTest::testEveryRowHasAKnownStatusAndAnEnforcedRowNamesItsTests`, `BestPracticesTest::testTheDocsPageCarriesTheSameTable`, `BestPracticesTest::testTheTallyCountsTheTable` |

Tally: 25 rows, 25 Enforced, 0 Checked.
