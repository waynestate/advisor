<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Playbook;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Playbook\FilePlaybook;
use Waynestate\Advisor\Playbook\PackagePlaybook;
use Waynestate\Advisor\Playbook\PlaybookSource;
use Waynestate\Advisor\Playbook\Settings;
use Waynestate\Advisor\Tests\Support\TempDir;

/**
 * The resolver every ADVISOR tool asks before guessing: the environment
 * wins, then the first available source, else nothing (ADR-0018).
 */
final class SettingsTest extends TestCase
{
    use TempDir;

    /**
     * @param list<PlaybookSource> $sources
     * @param array<string, string> $env
     */
    private function settings(array $sources, array $env = []): Settings
    {
        return new Settings($sources, static fn(string $name): ?string => $env[$name] ?? null);
    }

    /** @param array<string, string> $values */
    private function source(string $name, array $values, bool $available = true): FakeSource
    {
        return new FakeSource($name, $values, $available);
    }

    public function testEnvironmentWinsOverSources(): void
    {
        $settings = $this->settings(
            [$this->source('playbook', ['CAS_URL' => 'https://cas.example.test/login'])],
            ['CAS_URL' => 'https://env.example.test/login'],
        );

        $setting = $settings->resolve('CAS_URL');

        $this->assertSame('CAS_URL', $setting->name);
        $this->assertSame('https://env.example.test/login', $setting->value);
        $this->assertSame('environment', $setting->source);
    }

    public function testEmptyEnvironmentValueDoesNotWin(): void
    {
        $settings = $this->settings(
            [$this->source('playbook', ['CAS_URL' => 'https://cas.example.test/login'])],
            ['CAS_URL' => ''],
        );

        $setting = $settings->resolve('CAS_URL');

        $this->assertSame('https://cas.example.test/login', $setting->value);
        $this->assertSame('playbook', $setting->source);
    }

    public function testFirstAvailableSourceWins(): void
    {
        $settings = $this->settings([
            $this->source('playbook', ['WSU_DOMAIN' => 'first.example.test']),
            $this->source('env.playbook', ['WSU_DOMAIN' => 'second.example.test']),
        ]);

        $setting = $settings->resolve('WSU_DOMAIN');

        $this->assertSame('first.example.test', $setting->value);
        $this->assertSame('playbook', $setting->source);
    }

    public function testUnavailableSourceIsSkipped(): void
    {
        $settings = $this->settings([
            $this->source('playbook', ['WSU_DOMAIN' => 'first.example.test'], false),
            $this->source('env.playbook', ['WSU_DOMAIN' => 'second.example.test']),
        ]);

        $setting = $settings->resolve('WSU_DOMAIN');

        $this->assertTrue($settings->available(), 'one available source is enough');
        $this->assertSame('second.example.test', $setting->value);
        $this->assertSame('env.playbook', $setting->source);
    }

    public function testUnsetKeyIsNullWithSourceUnset(): void
    {
        $settings = $this->settings([$this->source('playbook', ['WSU_DOMAIN' => 'example.test'])]);

        $setting = $settings->resolve('NOT_SET_ANYWHERE');

        $this->assertNull($setting->value);
        $this->assertSame('unset', $setting->source);
    }

    public function testSecretLookingNamesAreRefusedByResolve(): void
    {
        $settings = $this->settings([$this->source('playbook', ['DB_PASSWORD' => 'not-a-real-password'])]);

        foreach (['DB_PASSWORD', 'API_TOKEN', 'SSH_KEY', 'MY_SECRET'] as $name) {
            try {
                $settings->resolve($name);
                $this->fail("{$name} should have been refused");
            } catch (ToolInputError $e) {
                $this->assertStringContainsString('secret', strtolower($e->getMessage()), $name);
            }
        }
    }

    public function testNamesAreSortedDeduplicatedAndWithoutSecrets(): void
    {
        $settings = $this->settings([
            $this->source('playbook', [
                'WSU_DOMAIN' => 'example.test',
                'DB_PASSWORD' => 'not-a-real-password',
                'CAS_URL' => 'https://cas.example.test/login',
            ]),
            $this->source('env.playbook', [
                'CAS_URL' => 'https://file.example.test/login',
                'ZETA_HOST' => 'zeta.example.test',
                'API_TOKEN' => 'not-a-real-token',
            ]),
        ]);

        $this->assertSame(['CAS_URL', 'WSU_DOMAIN', 'ZETA_HOST'], $settings->names());
    }

    public function testDescribeSkipsSecretLookingDeclaredNames(): void
    {
        $settings = $this->settings([$this->source('playbook', [
            'CAS_URL' => 'https://cas.example.test/login',
            'DB_PASSWORD' => 'not-a-real-password',
        ])]);

        $result = $settings->describe([
            ['name' => 'CAS_URL', 'description' => 'Where sign-in lives.'],
            ['name' => 'DB_PASSWORD', 'description' => 'Never shown.'],
        ]);

        $this->assertSame(['CAS_URL'], array_column($result['entries'], 'name'));
    }

    public function testFilePlaybookParsesCommentsBlanksQuotesAndJunk(): void
    {
        $dir = $this->tempDir();
        $lines = [
            '# Comments and blank lines are ignored',
            '',
            'WSU_DOMAIN=example.test',
            'CAS_URL="https://cas.example.test/login"',
            "SINGLE_QUOTED='single.example.test'",
            'MIXED_QUOTES="single.example.test\'',
            'NO_VALUE=',
            '=missing-key',
            'just words without an equals',
            'SPACED = spaced.example.test',
        ];
        $this->put($dir . '/.env.playbook', implode("\n", $lines) . "\n");
        $file = new FilePlaybook($dir);

        $this->assertTrue($file->available());
        $this->assertSame('env.playbook', $file->name());
        $this->assertSame('example.test', $file->get('WSU_DOMAIN'));
        $this->assertSame('https://cas.example.test/login', $file->get('CAS_URL'));
        $this->assertSame('single.example.test', $file->get('SINGLE_QUOTED'));
        $this->assertSame("\"single.example.test'", $file->get('MIXED_QUOTES'), 'only one pair of matching quotes is stripped');
        $this->assertNull($file->get('NO_VALUE'), 'an empty value is unset');
        $this->assertNull($file->get('missing-key'));
        $this->assertNull($file->get('just words without an equals'));
        $this->assertSame('spaced.example.test', $file->get('SPACED'));

        $all = $file->all();
        $this->assertSame('', $all['NO_VALUE'], 'all() keeps every parsed pair');
        $this->assertArrayNotHasKey('missing-key', $all);
    }

    public function testMissingEnvPlaybookIsNotAvailable(): void
    {
        $file = new FilePlaybook($this->tempDir());

        $this->assertFalse($file->available());
        $this->assertNull($file->get('WSU_DOMAIN'));
        $this->assertSame([], $file->all());
    }

    public function testFileIsReadOncePerInstance(): void
    {
        $dir = $this->tempDir();
        $this->put($dir . '/.env.playbook', "WSU_DOMAIN=example.test\n");
        $file = new FilePlaybook($dir);
        $this->assertSame('example.test', $file->get('WSU_DOMAIN'));

        $this->put($dir . '/.env.playbook', "WSU_DOMAIN=changed.example.test\n");

        $this->assertSame('example.test', $file->get('WSU_DOMAIN'), 'the file is read once per instance');
    }

    public function testForProjectReadsTheEnvPlaybookOfTheGivenRoot(): void
    {
        $dir = $this->tempDir();
        $this->put($dir . '/.env.playbook', "ZETA_HOST=zeta.example.test\n");

        $setting = Settings::forProject($dir)->resolve('ZETA_HOST');

        $this->assertSame('zeta.example.test', $setting->value);
        $this->assertSame('env.playbook', $setting->source);
    }

    #[RunInSeparateProcess]
    public function testPackagePlaybookIsUnavailableWithoutThePackage(): void
    {
        $this->assertFalse(
            class_exists(\Waynestate\Playbook\Playbook::class, false),
            'the Playbook stub must not be loaded in this process',
        );
        $package = new PackagePlaybook();

        $this->assertSame('playbook', $package->name());
        $this->assertFalse($package->available());
        $this->assertNull($package->get('CAS_URL'));
        $this->assertSame([], $package->all());
    }

    public function testAvailableReflectsAnyAvailableSource(): void
    {
        $this->assertFalse((new Settings())->available());
        $this->assertFalse((new Settings([$this->source('playbook', ['A' => 'a'], false)]))->available());
        $this->assertTrue((new Settings([
            $this->source('playbook', ['A' => 'a'], false),
            $this->source('env.playbook', ['B' => 'b']),
        ]))->available());
    }

    public function testDescribeWithNoSourcesSaysHowToGet(): void
    {
        $settings = new Settings([], static fn(string $name): ?string => null);

        $result = $settings->describe([
            ['name' => 'CAS_URL', 'description' => 'Where sign-in lives.'],
            ['name' => 'WSU_DOMAIN', 'description' => 'The domain everything hangs off.'],
        ]);

        $this->assertFalse($result['available']);
        $this->assertNotNull($result['how_to_get']);
        $this->assertCount(2, $result['entries']);
        foreach ($result['entries'] as $entry) {
            $this->assertNull($entry['value']);
            $this->assertSame('unset', $entry['source']);
        }
    }

    public function testDescribeWithEverythingResolvedHasNoHowToGet(): void
    {
        $settings = $this->settings([$this->source('playbook', [
            'CAS_URL' => 'https://cas.example.test/login',
            'WSU_DOMAIN' => 'example.test',
        ])]);

        $result = $settings->describe([
            ['name' => 'CAS_URL', 'description' => 'Where sign-in lives.'],
            ['name' => 'WSU_DOMAIN', 'description' => 'The domain everything hangs off.'],
        ]);

        $this->assertTrue($result['available']);
        $this->assertNull($result['how_to_get']);
        $byName = [];
        foreach ($result['entries'] as $entry) {
            $byName[$entry['name']] = $entry;
        }
        $this->assertSame('https://cas.example.test/login', $byName['CAS_URL']['value']);
        $this->assertSame('playbook', $byName['CAS_URL']['source']);
        $this->assertSame('example.test', $byName['WSU_DOMAIN']['value']);
    }

    public function testDescribeWithOneMissingValueSaysHowToGet(): void
    {
        $settings = $this->settings([$this->source('playbook', ['CAS_URL' => 'https://cas.example.test/login'])]);

        $result = $settings->describe([
            ['name' => 'CAS_URL', 'description' => 'Where sign-in lives.'],
            ['name' => 'WSU_DOMAIN', 'description' => 'The domain everything hangs off.'],
        ]);

        $this->assertTrue($result['available']);
        $this->assertSame($settings->howToGet(), $result['how_to_get']);
        $byName = [];
        foreach ($result['entries'] as $entry) {
            $byName[$entry['name']] = $entry;
        }
        $this->assertNull($byName['WSU_DOMAIN']['value']);
        $this->assertSame('unset', $byName['WSU_DOMAIN']['source']);
    }

    public function testHowToGetKeepsTheFirstParagraphWordForWord(): void
    {
        $text = (new Settings())->howToGet();

        $this->assertStringStartsWith(
            'composer require waynestate/playbook (VCS repository https://git.wayne.edu/enterprise-apps/playbook.git). PLAYBOOK is private on git.wayne.edu. If you get a 404, ask the Enterprise Applications team for read access.',
            $text,
        );
    }

    public function testHowToGetNamesBothOsAndTheCommitWarning(): void
    {
        $text = (new Settings())->howToGet();

        $this->assertStringContainsString('Linux or macOS: curl --fail -H "PRIVATE-TOKEN: $GITLAB_TOKEN"', $text);
        $this->assertStringContainsString(
            'Windows (PowerShell): Invoke-WebRequest -Headers @{ \'PRIVATE-TOKEN\' = $env:GITLAB_TOKEN } -OutFile .env.playbook',
            $text,
        );
        $this->assertStringContainsString('Never commit .env.playbook.', $text);
    }

    public function testHowToGetKeepsTheGitlabTokenLiteral(): void
    {
        $text = (new Settings())->howToGet();

        $this->assertStringContainsString('$GITLAB_TOKEN', $text);
        $this->assertStringContainsString('$env:GITLAB_TOKEN', $text);
    }
}

/** A PlaybookSource backed by a plain array, for tests. */
final class FakeSource implements PlaybookSource
{
    /** @param array<string, string> $values */
    public function __construct(
        private readonly string $name,
        private readonly array $values,
        private readonly bool $available = true,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function get(string $key): ?string
    {
        if (!$this->available) {
            return null;
        }
        $value = $this->values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->available ? $this->values : [];
    }
}
