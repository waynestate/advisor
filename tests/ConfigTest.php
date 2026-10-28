<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Config;

final class ConfigTest extends TestCase
{
    public function testIsOffByDefault(): void
    {
        $config = Config::fromEnv([], '/work');

        $this->assertFalse($config->enabled);
        $this->assertStringContainsString('ADVISOR_ENABLED=1', (string) $config->refusal());
    }

    public function testEnabledInLocalStarts(): void
    {
        $config = Config::fromEnv(['ADVISOR_ENABLED' => 'true'], '/work');

        $this->assertNull($config->refusal());
        $this->assertSame('/work', $config->projectRoot);
        $this->assertTrue($config->readOnly);
    }

    public function testRefusesProductionWhateverElseIsSet(): void
    {
        foreach (['production', 'PRODUCTION', 'prod'] as $name) {
            $config = Config::fromEnv(['ADVISOR_ENABLED' => '1', 'APP_ENV' => $name, 'APP_DEBUG' => 'true'], '/work');

            $this->assertStringContainsString('production', (string) $config->refusal(), $name);
        }
    }

    public function testAdvisorEnvBeatsAppEnv(): void
    {
        $config = Config::fromEnv(['ADVISOR_ENABLED' => '1', 'APP_ENV' => 'production', 'ADVISOR_ENV' => 'local'], '/work');

        $this->assertNull($config->refusal());
    }

    public function testReadOnlyTurnsOffOnlyWhenSaidPlainly(): void
    {
        $this->assertTrue(Config::fromEnv(['ADVISOR_READ_ONLY' => 'maybe'], '/w')->readOnly);
        $this->assertTrue(Config::fromEnv(['ADVISOR_READ_ONLY' => ''], '/w')->readOnly);
        $this->assertFalse(Config::fromEnv(['ADVISOR_READ_ONLY' => 'false'], '/w')->readOnly);
    }

    public function testParsesGroupsAndNumbers(): void
    {
        $config = Config::fromEnv(['ADVISOR_GROUPS' => 'stack, proctor ,', 'ADVISOR_PROCTOR_TIMEOUT' => '30', 'ADVISOR_MAX_CHARS' => '5000'], '/w');

        $this->assertSame(['stack', 'proctor'], $config->groups);
        $this->assertSame(30, $config->proctorTimeout);
        $this->assertSame(5000, $config->maxChars);
    }

    public function testRejectsNonsenseNumbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Config::fromEnv(['ADVISOR_PROCTOR_TIMEOUT' => '30s'], '/w');
    }

    public function testRejectsOutOfRangeLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Config::fromEnv(['ADVISOR_MAX_CHARS' => '999999'], '/w');
    }
}
