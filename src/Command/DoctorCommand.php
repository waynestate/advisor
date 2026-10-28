<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Command;

use Mcp\Server\Transport\StdioTransport;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Config;
use Waynestate\Advisor\Content\ContentBundle;
use Waynestate\Advisor\Server\Runtime;
use Waynestate\Advisor\Server\SdkServer;
use Waynestate\Advisor\Support\Json;
use Waynestate\Advisor\Support\VersionConstraint;

/**
 * The one command to run when ADVISOR "doesn't show up" in the editor.
 *
 * It checks, in plain words, the four things that can keep the server from
 * appearing: the PHP version, the bundled content, the client's MCP config,
 * and the server itself (started in-process and asked for its tools). It
 * never changes anything.
 */
#[AsCommand(name: 'doctor', description: 'Check the install: PHP, the bundled content, the client config, and the server.')]
final class DoctorCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Print the findings as JSON.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = (bool) $input->getOption('json');
        try {
            $findings = $this->runChecks();
        } catch (Throwable $e) {
            $this->render($output, $json, [$this->finding('doctor', 'fail', 'doctor could not run: ' . $e->getMessage())]);

            return 2;
        }
        $this->render($output, $json, $findings);

        return $this->exitCode($findings);
    }

    /**
     * @return list<array{check: string, status: string, message: string}>
     */
    private function runChecks(): array
    {
        $findings = [
            $this->phpCheck(),
            $this->contentCheck(),
        ];
        foreach (array_keys(ClientConfig::CLIENTS) as $client) {
            $findings[] = $this->clientCheck($client);
        }
        $findings[] = $this->mcpCheck();

        return $findings;
    }

    /** @return array{check: string, status: string, message: string} */
    private function phpCheck(): array
    {
        try {
            $composerPath = dirname(__DIR__, 2) . '/composer.json';
            if (!is_file($composerPath)) {
                return $this->finding('php', 'fail', 'ADVISOR composer.json not found at ' . $composerPath);
            }
            $composer = Json::decodeFile($composerPath);
            $constraint = $composer['require']['php'] ?? null;
            if (!is_string($constraint) || $constraint === '') {
                return $this->finding('php', 'fail', 'ADVISOR composer.json has no require.php constraint.');
            }
            if (VersionConstraint::satisfies($constraint, PHP_VERSION)) {
                return $this->finding('php', 'pass', 'PHP ' . PHP_VERSION . ' satisfies ' . $constraint);
            }

            return $this->finding('php', 'fail', 'PHP ' . PHP_VERSION . ' does not satisfy ' . $constraint);
        } catch (Throwable $e) {
            return $this->finding('php', 'fail', 'Could not check PHP: ' . $e->getMessage());
        }
    }

    /** @return array{check: string, status: string, message: string} */
    private function contentCheck(): array
    {
        try {
            $problems = (new ContentBundle(Advisor::dataDir()))->problems();
            if ($problems === []) {
                return $this->finding('content', 'pass', 'data/ matches its manifest.');
            }

            return $this->finding('content', 'fail', 'data/ failed its check: ' . implode('; ', $problems));
        } catch (Throwable $e) {
            return $this->finding('content', 'fail', 'Could not check data/: ' . $e->getMessage());
        }
    }

    /** @return array{check: string, status: string, message: string} */
    private function clientCheck(string $client): array
    {
        try {
            $clientConfig = ClientConfig::CLIENTS[$client] ?? throw new \LogicException('Unknown client: ' . $client);
            $file = $clientConfig[0];
            $key = $clientConfig[1];
            $path = $this->projectRoot() . '/' . $file;
            if (!is_file($path)) {
                return $this->finding('client:' . $client, 'warn', "{$file} is not present, so {$client} will not start ADVISOR.");
            }
            $data = Json::decodeFile($path);
            $servers = $data[$key] ?? null;
            if (!is_array($servers)) {
                return $this->finding('client:' . $client, 'fail', "{$file} has no {$key} object.");
            }
            $entry = $servers[ClientConfig::SERVER_NAME] ?? null;
            if (!is_array($entry)) {
                return $this->finding('client:' . $client, 'fail', "{$file} has no \"advisor\" entry under {$key}.");
            }
            $args = $entry['args'] ?? null;
            if (is_array($args) && isset($args[0]) && is_string($args[0]) && $args[0] !== '' && !self::isAbsolute($args[0])) {
                if (!is_file($this->projectRoot() . '/' . $args[0])) {
                    return $this->finding('client:' . $client, 'fail', "{$file} points at {$args[0]}, which is not in the project.");
                }
            }

            return $this->finding('client:' . $client, 'pass', "{$client} is configured to start ADVISOR.");
        } catch (Throwable $e) {
            return $this->finding('client:' . $client, 'fail', 'Could not check ' . $client . ': ' . $e->getMessage());
        }
    }

    /** @return array{check: string, status: string, message: string} */
    private function mcpCheck(): array
    {
        try {
            $config = Config::fromEnv(['ADVISOR_ENABLED' => '1']);
            $refusal = $config->refusal();
            if ($refusal !== null) {
                return $this->finding('mcp', 'fail', 'The server would refuse to start: ' . $refusal);
            }
            $logger = new NullLogger();
            $booted = Runtime::boot($config, $logger);
            $server = SdkServer::build($booted, $logger);

            $input = fopen('php://temp', 'w+');
            $outFile = tempnam(sys_get_temp_dir(), 'advisor-doctor');
            if ($input === false || $outFile === false) {
                return $this->finding('mcp', 'fail', 'Could not open memory streams for the handshake.');
            }
            $output = fopen($outFile, 'w+');
            if ($output === false) {
                @unlink($outFile);
                return $this->finding('mcp', 'fail', 'Could not open memory streams for the handshake.');
            }
            $lines = [
                json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'advisor-doctor', 'version' => '1']]]),
                json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']),
                json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new \stdClass()]),
            ];
            fwrite($input, implode("\n", $lines) . "\n");
            rewind($input);
            $server->run(new StdioTransport($input, $output, $logger));
            $raw = (string) file_get_contents($outFile);
            @unlink($outFile);

            $responses = [];
            foreach (explode("\n", trim($raw)) as $line) {
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && isset($decoded['id']) && is_int($decoded['id'])) {
                    $responses[$decoded['id']] = $decoded;
                }
            }
            $name = $responses[1]['result']['serverInfo']['name'] ?? null;
            if ($name !== Advisor::NAME) {
                return $this->finding('mcp', 'fail', 'The server did not identify as ' . Advisor::NAME . '.');
            }
            $tools = $responses[2]['result']['tools'] ?? null;
            if (!is_array($tools) || $tools === []) {
                return $this->finding('mcp', 'fail', 'The server offered no tools.');
            }

            return $this->finding('mcp', 'pass', 'The server starts and offers ' . count($tools) . ' tools.');
        } catch (Throwable $e) {
            return $this->finding('mcp', 'fail', 'Could not start the server: ' . $e->getMessage());
        }
    }

    /**
     * @param list<array{check: string, status: string, message: string}> $findings
     */
    private function render(OutputInterface $output, bool $json, array $findings): void
    {
        if ($json) {
            $output->write(Json::encode(['findings' => $findings]));

            return;
        }
        foreach ($findings as $finding) {
            $output->writeln(sprintf('  %s  %s: %s', $this->tag($finding['status']), $finding['check'], $finding['message']));
        }
    }

    /**
     * @param list<array{check: string, status: string, message: string}> $findings
     */
    private function exitCode(array $findings): int
    {
        foreach ($findings as $finding) {
            if ($finding['status'] === 'fail') {
                return 1;
            }
        }

        return 0;
    }

    /**
     * @return array{check: string, status: string, message: string}
     */
    private function finding(string $check, string $status, string $message): array
    {
        return ['check' => $check, 'status' => $status, 'message' => $message];
    }

    private function tag(string $status): string
    {
        return match ($status) {
            'pass' => '<info>pass</info>',
            'warn' => '<comment>warn</comment>',
            default => '<error>fail</error>',
        };
    }

    private function projectRoot(): string
    {
        $root = getenv('ADVISOR_PROJECT_ROOT');
        if (is_string($root) && $root !== '') {
            return $root;
        }

        return getcwd() ?: '.';
    }

    private static function isAbsolute(string $path): bool
    {
        return $path[0] === '/' || $path[0] === '\\' || (bool) preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    }
}
