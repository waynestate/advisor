<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Waynestate\Advisor\Command\ClientConfig;
use Waynestate\Advisor\Command\DoctorCommand;
use Waynestate\Advisor\Tests\Support\TempDir;

/** The doctor command: plain-words findings, the right exit code, and a JSON shape. */
final class DoctorTest extends TestCase
{
    use TempDir;

    private function tester(): CommandTester
    {
        return new CommandTester(new DoctorCommand());
    }

    protected function tearDown(): void
    {
        putenv('ADVISOR_PROJECT_ROOT');
        parent::tearDown();
    }

    public function testMissingClientConfigsAreWarningsAndExitZero(): void
    {
        $this->putenv('ADVISOR_PROJECT_ROOT', $this->tempDir());
        $tester = $this->tester();

        $this->assertSame(0, $tester->execute([]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('client:claude: .mcp.json is not present', $display);
        $this->assertStringContainsString('client:vscode: .vscode/mcp.json is not present', $display);
    }

    public function testAConfiguredClientPasses(): void
    {
        $dir = $this->tempDir();
        $this->put($dir . '/.mcp.json', ClientConfig::render('claude'));
        $this->put($dir . '/vendor/bin/advisor', '#!/usr/bin/env php');
        $this->putenv('ADVISOR_PROJECT_ROOT', $dir);
        $tester = $this->tester();

        $this->assertSame(0, $tester->execute([]));

        $this->assertStringContainsString('client:claude: claude is configured to start ADVISOR.', $tester->getDisplay());
    }

    public function testAConfigWithoutAnAdvisorEntryFails(): void
    {
        $dir = $this->tempDir();
        $this->put($dir . '/.mcp.json', '{"mcpServers":{"other":{"command":"php","args":["x"]}}}');
        $this->putenv('ADVISOR_PROJECT_ROOT', $dir);
        $tester = $this->tester();

        $this->assertSame(1, $tester->execute([]));

        $this->assertStringContainsString('no "advisor" entry', $tester->getDisplay());
    }

    public function testAConfigPointingAtAMissingScriptFails(): void
    {
        $dir = $this->tempDir();
        $this->put($dir . '/.mcp.json', ClientConfig::render('claude'));
        $this->putenv('ADVISOR_PROJECT_ROOT', $dir);
        $tester = $this->tester();

        $this->assertSame(1, $tester->execute([]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('vendor/bin/advisor', $display);
        $this->assertStringContainsString('not in the project', $display);
    }

    public function testJsonOutputHasTheFindingsShape(): void
    {
        $this->putenv('ADVISOR_PROJECT_ROOT', $this->tempDir());
        $tester = $this->tester();

        $this->assertSame(0, $tester->execute(['--json' => true]));

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('findings', $data);
        $this->assertSame(
            ['php', 'content', 'client:claude', 'client:warriorgpt', 'client:cursor', 'client:vscode', 'mcp'],
            array_column($data['findings'], 'check'),
        );
        foreach ($data['findings'] as $finding) {
            $this->assertArrayHasKey('status', $finding);
            $this->assertArrayHasKey('message', $finding);
        }
        $mcp = array_column($data['findings'], 'status', 'check')['mcp'] ?? null;
        $this->assertSame('pass', $mcp);
    }

    private function putenv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
    }
}
