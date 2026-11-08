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

    private const BOOSTER_SCRIPT = '/app/vendor/bin/booster';

    /**
     * @return list<string>
     */
    private static function goodBooster(): array
    {
        return [self::PHP, self::BOOSTER_SCRIPT, 'plan', '--path=/app', '--format=json', '--policy=/adv/data/policy.json'];
    }

    public function testAcceptsABoosterPlanWithFromPhp(): void
    {
        CommandAllowlist::assertBooster([...self::goodBooster(), '--from-php=7.4'], self::PHP, self::BOOSTER_SCRIPT);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function badBooster(): array
    {
        $good = self::goodBooster();

        return [
            'another subcommand' => [[$good[0], $good[1], 'run', ...array_slice($good, 3)]],
            'from php with a shell' => [[...$good, '--from-php=7.4;rm']],
            'from php not a version' => [[...$good, '--from-php=abc']],
            'extra flag' => [[...$good, '--output=/etc/cron.d/x']],
        ];
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('badBooster')]
    public function testRefusesAnythingElseOnBooster(array $argv): void
    {
        $this->expectException(ToolInputError::class);
        CommandAllowlist::assertBooster($argv, self::PHP, self::BOOSTER_SCRIPT);
    }
}
