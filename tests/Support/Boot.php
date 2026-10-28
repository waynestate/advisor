<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Support;

use Psr\Log\NullLogger;
use Waynestate\Advisor\Config;
use Waynestate\Advisor\Security\ProcessRunner;
use Waynestate\Advisor\Server\Booted;
use Waynestate\Advisor\Server\Runtime;
use Waynestate\Advisor\Support\FixedClock;

/** Builds a Booted core the way the CLI does, with a fixed day and no real subprocesses. */
final class Boot
{
    public const TODAY = '2026-10-28';

    /**
     * @param array<string, string> $env
     */
    public static function core(array $env = [], ?string $root = null, ?ProcessRunner $processes = null, ?string $manifest = null): Booted
    {
        $config = Config::fromEnv(['ADVISOR_ENABLED' => '1', ...$env], $root ?? dirname(__DIR__, 2));

        return Runtime::boot(
            $config,
            new NullLogger(),
            $processes ?? new FakeProcessRunner(),
            new FixedClock(self::TODAY),
            null,
            $manifest,
        );
    }
}
