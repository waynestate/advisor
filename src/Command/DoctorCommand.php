<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Command;

use Closure;
use Composer\Semver\Semver;
use Mcp\Server\Transport\StdioTransport;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Config;
use Waynestate\Advisor\Content\ContentBundle;
use Waynestate\Advisor\Security\EnvAllowlist;
use Waynestate\Advisor\Server\Runtime;
use Waynestate\Advisor\Server\SdkServer;
use Waynestate\Advisor\Support\Json;

/**
 * The one command to run when ADVISOR "doesn't show up" in the editor.
 *
 * It checks, in plain words, the things that can keep the server from
 * appearing: the PHP version, which `php` a client will find on the PATH,
 * the bundled content, the client's MCP config, and the server itself, both
 * in-process and as a real child process over stdio, the way a client starts
 * it. It never changes anything.
 *
 * Doctor will see you now. Please take a seat; the stethoscope is cold.
 */
#[AsCommand(name: 'doctor', description: 'Check the install: PHP, the PATH, the bundled content, the client config, and the server over stdio.')]
final class DoctorCommand extends Command
{
    /** Seconds the stdio child may take to answer three requests. */
    private const STDIO_TIMEOUT = 60;

    /** @var Closure(): ?string */
    private readonly Closure $findPhp;

    /**
     * @param (Closure(): ?string)|null $findPhp where `php` resolves on the PATH; tests pass a fake
     */
    public function __construct(?Closure $findPhp = null)
    {
        $this->findPhp = $findPhp ?? static fn(): ?string => (new ExecutableFinder())->find(ClientConfig::COMMAND);
        parent::__construct();
    }

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
            $this->phpPathCheck(),
            $this->contentCheck(),
        ];
        foreach (array_keys(ClientConfig::CLIENTS) as $client) {
            $findings[] = $this->clientCheck($client);
        }
        $findings[] = $this->mcpCheck();
        $findings[] = $this->stdioCheck();

        return $findings;
    }

    /**
     * Clients start `php` by name (ClientConfig::COMMAND), so the `php` they
     * find is whichever comes first on the PATH, not necessarily the one
     * running doctor. This only looks; it never runs what it finds.
     *
     * @return array{check: string, status: string, message: string}
     */
    private function phpPathCheck(): array
    {
        try {
            $found = ($this->findPhp)();
            if ($found === null || $found === '') {
                return $this->finding('php-path', 'warn', 'No `php` on the PATH, so a client that starts `php` will not find it. Put the folder that holds ' . PHP_BINARY . ' on the PATH, or replace "php" in the client config with that full path.');
            }
            $foundReal = realpath($found) ?: $found;
            $runningReal = realpath(PHP_BINARY) ?: PHP_BINARY;
            if (self::samePath($foundReal, $runningReal)) {
                return $this->finding('php-path', 'pass', 'Clients will start ' . $found . ', the PHP running this check.');
            }

            return $this->finding('php-path', 'warn', 'Clients will start ' . $found . ', but this check runs under ' . PHP_BINARY . '. Run `' . $found . ' vendor/bin/advisor doctor` to check the one your client uses.');
        } catch (Throwable $e) {
            return $this->finding('php-path', 'warn', 'Could not look for `php` on the PATH: ' . $e->getMessage());
        }
    }

    /**
     * The real thing, the way a client does it: `php bin/advisor mcp` as a
     * child process over pipes, asked to initialize, list its tools and answer
     * one policy_lookup call. Every stdout line must be JSON-RPC (best
     * practice 9), and the child must exit 0 when its stdin closes. This is
     * the check that proves the stdio path on the machine it runs on,
     * Windows included (open question 1).
     *
     * The program is fixed (this PHP, this package's bin/advisor) and the
     * environment is the allowlist plus ADVISOR's own switches (best
     * practice 8); nothing from a config file is run.
     *
     * @return array{check: string, status: string, message: string}
     */
    private function stdioCheck(): array
    {
        try {
            $script = dirname(__DIR__, 2) . '/bin/advisor';
            if (!is_file($script)) {
                return $this->finding('stdio', 'fail', 'bin/advisor not found at ' . $script . '.');
            }
            $current = [];
            foreach (getenv() as $name => $value) {
                $current[(string) $name] = $value;
            }
            // The allowlist drops APP_ENV and every ADVISOR_ setting, so the child
            // gets what a client config gives it (ClientConfig::ENV) and runs as local.
            $env = ClientConfig::ENV + EnvAllowlist::forProcess($current);
            $env['ADVISOR_PROJECT_ROOT'] = $this->projectRoot();
            $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            $requests = [
                ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'advisor-doctor', 'version' => '1']]],
                ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
                ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new \stdClass()],
                ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'policy_lookup', 'arguments' => ['component' => 'php', 'version' => $version]]],
            ];
            $input = '';
            foreach ($requests as $request) {
                $input .= json_encode($request, JSON_THROW_ON_ERROR) . "\n";
            }

            $process = new Process([PHP_BINARY, $script, 'mcp'], $this->projectRoot(), $env, $input, (float) self::STDIO_TIMEOUT);
            $started = hrtime(true);
            try {
                $process->run();
            } catch (ProcessTimedOutException) {
                return $this->finding('stdio', 'fail', '`php bin/advisor mcp` did not answer within ' . self::STDIO_TIMEOUT . ' seconds.');
            }
            $millis = (int) round((hrtime(true) - $started) / 1_000_000);

            if ($process->getExitCode() !== 0) {
                $stderr = trim(substr($process->getErrorOutput(), 0, 500));

                return $this->finding('stdio', 'fail', '`php bin/advisor mcp` exited ' . $process->getExitCode() . ($stderr !== '' ? ': ' . $stderr : '.'));
            }

            $responses = [];
            foreach (preg_split('/\r?\n/', trim($process->getOutput())) ?: [] as $line) {
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (!is_array($decoded) || ($decoded['jsonrpc'] ?? null) !== '2.0') {
                    return $this->finding('stdio', 'fail', 'stdout held a line that is not JSON-RPC, which breaks the client: ' . substr($line, 0, 120));
                }
                if (isset($decoded['id']) && is_int($decoded['id'])) {
                    $responses[$decoded['id']] = $decoded;
                }
            }
            if (($responses[1]['result']['serverInfo']['name'] ?? null) !== Advisor::NAME) {
                return $this->finding('stdio', 'fail', 'The child process did not identify as ' . Advisor::NAME . '.');
            }
            $tools = $responses[2]['result']['tools'] ?? null;
            if (!is_array($tools) || $tools === []) {
                return $this->finding('stdio', 'fail', 'The child process offered no tools.');
            }
            $call = $responses[3]['result'] ?? null;
            if (!is_array($call) || ($call['isError'] ?? false) === true) {
                return $this->finding('stdio', 'fail', 'The child process did not answer policy_lookup.');
            }

            return $this->finding('stdio', 'pass', sprintf(
                '`php bin/advisor mcp` runs as a child process on %s (PHP %s): clean stdout, %d tools, policy_lookup answered, exit 0 (%d ms).',
                self::osName(),
                PHP_VERSION,
                count($tools),
                $millis,
            ));
        } catch (Throwable $e) {
            return $this->finding('stdio', 'fail', 'Could not run the server as a child process: ' . $e->getMessage());
        }
    }

    private static function osName(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'macOS',
            default => PHP_OS_FAMILY,
        };
    }

    private static function samePath(string $a, string $b): bool
    {
        // Windows paths are case-insensitive; elsewhere they are not.
        return PHP_OS_FAMILY === 'Windows' ? strcasecmp($a, $b) === 0 : $a === $b;
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
            if (Semver::satisfies(PHP_VERSION, $constraint)) {
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
