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
            ['php', 'php-path', 'content', 'client:claude', 'client:warriorgpt', 'client:cursor', 'client:vscode', 'mcp', 'stdio'],
            array_column($data['findings'], 'check'),
        );
        foreach ($data['findings'] as $finding) {
            $this->assertArrayHasKey('status', $finding);
            $this->assertArrayHasKey('message', $finding);
        }
        $statuses = array_column($data['findings'], 'status', 'check');
        $this->assertSame('pass', $statuses['mcp'] ?? null);
        $this->assertSame('pass', $statuses['stdio'] ?? null);
    }

    public function testTheStdioCheckStartsTheServerAsAChildProcess(): void
    {
        $this->putenv('ADVISOR_PROJECT_ROOT', $this->tempDir());
        $tester = $this->tester();

        $this->assertSame(0, $tester->execute(['--json' => true]));

        $messages = array_column(json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['findings'], 'message', 'check');
        $stdio = $messages['stdio'] ?? '';
        $this->assertStringContainsString('`php bin/advisor mcp` runs as a child process on ' . (PHP_OS_FAMILY === 'Darwin' ? 'macOS' : PHP_OS_FAMILY), $stdio);
        $this->assertStringContainsString('PHP ' . PHP_VERSION, $stdio);
        $this->assertStringContainsString('policy_lookup answered, exit 0', $stdio);
        // The child offers what the in-process server offers.
        $this->assertSame(1, preg_match('/offers (\d+) tools/', $messages['mcp'] ?? '', $inProcess));
        $this->assertStringContainsString(', ' . ($inProcess[1] ?? '?') . ' tools,', $stdio);
    }

    public function testNoPhpOnThePathIsAWarningThatNamesTheFix(): void
    {
        $this->putenv('ADVISOR_PROJECT_ROOT', $this->tempDir());
        $tester = new CommandTester(new DoctorCommand(static fn(): ?string => null));

        $this->assertSame(0, $tester->execute([]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('php-path: No `php` on the PATH', $display);
        $this->assertStringContainsString(PHP_BINARY, $display);
    }

    public function testAnotherPhpOnThePathIsAWarning(): void
    {
        $dir = $this->tempDir();
        $this->put($dir . '/other-php', '');
        $this->putenv('ADVISOR_PROJECT_ROOT', $dir);
        $tester = new CommandTester(new DoctorCommand(static fn(): string => $dir . '/other-php'));

        $this->assertSame(0, $tester->execute([]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('php-path: Clients will start ' . $dir . '/other-php, but this check runs under ' . PHP_BINARY, $display);
    }

    public function testTheRunningPhpOnThePathPasses(): void
    {
        $this->putenv('ADVISOR_PROJECT_ROOT', $this->tempDir());
        $tester = new CommandTester(new DoctorCommand(static fn(): string => PHP_BINARY));

        $this->assertSame(0, $tester->execute([]));

        $this->assertStringContainsString('php-path: Clients will start ' . PHP_BINARY . ', the PHP running this check.', $tester->getDisplay());
    }

    private function putenv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
    }
}
