<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Server\Booted;
use Waynestate\Advisor\Server\ToolOutcome;
use Waynestate\Advisor\Tests\Support\Boot;

/** playbook_lookup against the PLAYBOOK stub (tests/Fixtures/playbook-stub.php). */
final class PlaybookLookupTest extends TestCase
{
    /** @var string|false */
    private $savedCasUrl = false;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../Fixtures/playbook-stub.php';
    }

    protected function setUp(): void
    {
        $this->savedCasUrl = getenv('CAS_URL');
        putenv('CAS_URL');
    }

    protected function tearDown(): void
    {
        if ($this->savedCasUrl === false) {
            putenv('CAS_URL');
        } else {
            putenv('CAS_URL=' . $this->savedCasUrl);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function call(Booted $booted, array $args = []): ToolOutcome
    {
        return $booted->invoker->invoke($booted->catalog->tools()['playbook_lookup'], $args);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(ToolOutcome $outcome): array
    {
        $this->assertFalse($outcome->isError, $outcome->text);

        return $outcome->data;
    }

    public function testEnvironmentWinsOverPlaybook(): void
    {
        putenv('CAS_URL=https://env.example.test/login');
        $data = $this->data($this->call(Boot::core(), ['key' => 'CAS_URL']));

        $this->assertTrue($data['available']);
        $this->assertSame('CAS_URL', $data['key']);
        $this->assertSame('https://env.example.test/login', $data['value']);
        $this->assertSame('environment', $data['source']);
    }

    public function testKeyFromPlaybook(): void
    {
        $data = $this->data($this->call(Boot::core(), ['key' => 'WSU_DOMAIN']));

        $this->assertTrue($data['available']);
        $this->assertSame('example.test', $data['value']);
        $this->assertSame('playbook', $data['source']);
    }

    public function testUnsetKeyIsNullWithSourceUnset(): void
    {
        $data = $this->data($this->call(Boot::core(), ['key' => 'NOT_IN_PLAYBOOK']));

        $this->assertTrue($data['available']);
        $this->assertNull($data['value']);
        $this->assertSame('unset', $data['source']);
    }

    public function testSecretLookingKeysAreRefused(): void
    {
        foreach (['DB_PASSWORD', 'API_TOKEN', 'SSH_KEY', 'MY_SECRET'] as $key) {
            $outcome = $this->call(Boot::core(), ['key' => $key]);
            $this->assertTrue($outcome->isError, $key);
            $this->assertStringContainsString('secret', strtolower($outcome->text), $key);
        }
    }

    public function testListModeShowsEveryKeyWithItsSource(): void
    {
        putenv('CAS_URL=https://env.example.test/login');
        $data = $this->data($this->call(Boot::core()));

        $this->assertTrue($data['available']);
        $byKey = [];
        foreach ($data['entries'] as $entry) {
            $byKey[$entry['key']] = $entry;
        }
        $this->assertSame('https://env.example.test/login', $byKey['CAS_URL']['value']);
        $this->assertSame('environment', $byKey['CAS_URL']['source']);
        $this->assertSame('example.test', $byKey['WSU_DOMAIN']['value']);
        $this->assertSame('playbook', $byKey['WSU_DOMAIN']['source']);
        $this->assertArrayNotHasKey('DB_PASSWORD', $byKey, 'secret-looking keys are never listed');
    }
}
