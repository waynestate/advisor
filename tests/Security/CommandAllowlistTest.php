<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Security\CommandAllowlist;

final class CommandAllowlistTest extends TestCase
{
    private const PHP = '/usr/bin/php';

    private const SCRIPT = '/app/vendor/bin/proctor';

    /**
     * @return list<string>
     */
    private static function good(): array
    {
        return [self::PHP, self::SCRIPT, 'check', '--path=/app', '--format=json', '--policy=/adv/data/policy.json', '--timeout=120'];
    }

    public function testAcceptsTheStandardCommand(): void
    {
        CommandAllowlist::assertProctor(self::good(), self::PHP, self::SCRIPT);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function bad(): array
    {
        $good = self::good();

        return [
            'another program' => [['/bin/sh', ...array_slice($good, 1)]],
            'another script' => [[$good[0], '/tmp/evil.php', ...array_slice($good, 2)]],
            'another subcommand' => [[$good[0], $good[1], 'self-update', ...array_slice($good, 3)]],
            'extra flag' => [[...$good, '--output=/etc/cron.d/x']],
            'a flag in the path slot' => [[...array_slice($good, 0, 3), '--exec=rm', ...array_slice($good, 3)]],
            'bare argument' => [[...$good, 'extra']],
            'format changed' => [[$good[0], $good[1], 'check', '--path=/app', '--format=text', '--policy=/p']],
            'duplicate option' => [[...$good, '--path=/other']],
            'bad timeout' => [[...array_slice($good, 0, 6), '--timeout=1;rm -rf /']],
            'missing policy' => [[$good[0], $good[1], 'check', '--path=/app', '--format=json']],
            'single dash' => [[...array_slice($good, 0, 3), '-p', '/app', '--format=json', '--policy=/p']],
        ];
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('bad')]
    public function testRefusesAnythingElse(array $argv): void
    {
        $this->expectException(ToolInputError::class);
        CommandAllowlist::assertProctor($argv, self::PHP, self::SCRIPT);
    }
}
