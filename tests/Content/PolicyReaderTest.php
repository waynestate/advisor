<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Content;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Content\PolicyReader;
use Waynestate\Advisor\Content\State;

/**
 * Pins the boundary days of CREED's reader (waynestate/creed Policy::stateOf):
 * a version is Active through active_until, Security through security_until,
 * Eol for eol_grace_days after that, then EolGraceExpired. If CREED changes
 * those rules this test is where ADVISOR notices.
 */
final class PolicyReaderTest extends TestCase
{
    private function reader(): PolicyReader
    {
        return PolicyReader::fromArray([
            'schema_version' => 1,
            'components' => [
                'php' => ['versions' => [
                    '8.1' => ['security_until' => '2025-12-31'],
                    '8.2' => ['active_until' => '2024-12-31', 'security_until' => '2026-12-31'],
                    '8.3' => ['active_until' => '2025-12-31', 'security_until' => '2027-12-31'],
                    '9.0' => ['active_until' => '2030-01-01'],
                ]],
                'laravel' => ['versions' => ['13' => ['active_until' => '2027-09-17', 'security_until' => '2028-03-17']]],
            ],
            'tools' => ['composer' => ['minimum' => '2.8.0']],
            'rules' => ['eol_grace_days' => 90],
        ]);
    }

    private function on(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }

    public function testBoundaryDaysAreInclusive(): void
    {
        $r = $this->reader();

        $this->assertSame(State::Active, $r->stateOf('php', '8.2', $this->on('2024-12-31')));
        $this->assertSame(State::Security, $r->stateOf('php', '8.2', $this->on('2025-01-01')));
        $this->assertSame(State::Security, $r->stateOf('php', '8.2', $this->on('2026-12-31')));
        $this->assertSame(State::Eol, $r->stateOf('php', '8.2', $this->on('2027-01-01')));
        $this->assertSame(State::Eol, $r->stateOf('php', '8.2', $this->on('2027-03-31')), '90 days after 2026-12-31');
        $this->assertSame(State::EolGraceExpired, $r->stateOf('php', '8.2', $this->on('2027-04-01')));
    }

    public function testAVersionWithNoActiveDateStartsInSecurity(): void
    {
        $this->assertSame(State::Security, $this->reader()->stateOf('php', '8.1', $this->on('2025-06-01')));
    }

    public function testDoesNotGuessWhenSecurityUntilIsMissing(): void
    {
        $this->assertNull($this->reader()->stateOf('php', '9.0', $this->on('2026-01-01')));
        $this->assertNull($this->reader()->stateOf('php', '1.0', $this->on('2026-01-01')));
    }

    public function testOldestSupportedSkipsEolVersions(): void
    {
        $r = $this->reader();

        $this->assertSame('8.2', $r->oldestSupported('php', $this->on('2026-10-28')));
        $this->assertSame('8.3', $r->oldestSupported('php', $this->on('2027-01-01')));
        $this->assertNull($r->oldestSupported('laravel', $this->on('2031-01-01')));
    }

    public function testResolvesPatchVersionsToPolicyKeys(): void
    {
        $r = $this->reader();

        $this->assertSame('8.3', $r->resolveVersion('php', '8.3.12'));
        $this->assertSame('8.3', $r->resolveVersion('php', '8.3'));
        $this->assertSame('13', $r->resolveVersion('laravel', '13.2.1'));
        $this->assertNull($r->resolveVersion('php', '7.4'));
    }

    public function testToolMinimums(): void
    {
        $this->assertSame('2.8.0', $this->reader()->toolMinimum('composer'));
        $this->assertNull($this->reader()->toolMinimum('npm'));
    }

    public function testRefusesASchemaItDoesNotKnow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PolicyReader::fromArray(['schema_version' => 2]);
    }

    public function testAgreesWithTheBundledPolicyOnPhp(): void
    {
        $data = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/data/policy.json'), true, 512, JSON_THROW_ON_ERROR);
        $r = PolicyReader::fromArray($data);

        $this->assertContains('php', $r->components());
        $this->assertSame('2.8.0', $r->toolMinimum('composer'));
    }
}
