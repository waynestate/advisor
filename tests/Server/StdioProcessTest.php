<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Server;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** The real thing: `php bin/advisor mcp` as a child process, the way a client starts it. */
final class StdioProcessTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * @param array<string, string> $env
     */
    private function start(array $env): Process
    {
        $base = array_filter(['PATH' => getenv('PATH') ?: '', 'SystemRoot' => getenv('SystemRoot') ?: ''], static fn(string $v): bool => $v !== '');
        $process = new Process([PHP_BINARY, 'bin/advisor', 'mcp'], self::ROOT, $env + $base);
        $process->setTimeout(60);

        return $process;
    }

    public function testServesToolsOverStdioWithCleanStdout(): void
    {
        $process = $this->start(['ADVISOR_ENABLED' => '1', 'ADVISOR_ENV' => 'local']);
        $process->setInput(
            json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 't', 'version' => '1']]]) . "\n"
            . json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']) . "\n"
            . json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new \stdClass()]) . "\n"
            . "not json at all\n",
        );

        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $lines = array_filter(explode("\n", trim($process->getOutput())));
        $this->assertGreaterThanOrEqual(2, count($lines));
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded, 'stdout held something that is not JSON-RPC: ' . $line);
            $this->assertSame('2.0', $decoded['jsonrpc']);
        }
        $this->assertStringContainsString('list_projects', $process->getOutput());
    }

    public function testRefusesToStartUnlessEnabled(): void
    {
        $process = $this->start([]);
        $process->run();

        $this->assertSame(2, $process->getExitCode());
        $this->assertSame('', $process->getOutput());
        $this->assertStringContainsString('ADVISOR_ENABLED', $process->getErrorOutput());
    }

    public function testRefusesToStartInProduction(): void
    {
        foreach (['production', 'prod'] as $environment) {
            $process = $this->start(['ADVISOR_ENABLED' => '1', 'ADVISOR_ENV' => $environment]);
            $process->run();

            $this->assertSame(2, $process->getExitCode(), $environment);
            $this->assertSame('', $process->getOutput(), $environment);
            $this->assertStringContainsString('production', strtolower($process->getErrorOutput()));
        }
    }

    public function testAnInvalidSettingStopsTheServerWithAMessage(): void
    {
        $process = $this->start(['ADVISOR_ENABLED' => '1', 'ADVISOR_MAX_CHARS' => 'lots']);
        $process->run();

        $this->assertSame(2, $process->getExitCode());
        $this->assertSame('', $process->getOutput());
        $this->assertStringContainsString('ADVISOR_MAX_CHARS', $process->getErrorOutput());
    }

    public function testStrayOutputAndWarningsNeverReachStdout(): void
    {
        $script = dirname(__DIR__) . '/Fixtures/stray-output.php';
        $process = new Process([PHP_BINARY, $script], self::ROOT);
        $process->run();

        $this->assertSame('{"ok":true}' . "\n", $process->getOutput(), 'Only the deliberate protocol line may reach stdout.');
        $this->assertStringContainsString('stray echo', $process->getErrorOutput());
    }

    public function testContentCommandsRun(): void
    {
        $process = new Process([PHP_BINARY, 'bin/advisor', 'content:verify'], self::ROOT);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
    }
}
