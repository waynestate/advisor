<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Command;

use Mcp\Server\Transport\StdioTransport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Waynestate\Advisor\Config;
use Waynestate\Advisor\Server\Runtime;
use Waynestate\Advisor\Server\SdkServer;
use Waynestate\Advisor\Server\StderrLogger;
use Waynestate\Advisor\Server\StdoutGuard;

/**
 * `php vendor/bin/advisor mcp`: serve ADVISOR over stdio until the client
 * closes its input. Nothing but protocol messages may reach stdout, so this
 * command never writes to $output; every message goes to stderr.
 */
#[AsCommand(name: 'mcp', description: 'Serve ADVISOR to an MCP client over stdio (started by the client, not by hand).')]
final class McpCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stderr = STDERR;
        try {
            $config = Config::fromEnv();
        } catch (Throwable $e) {
            fwrite($stderr, '[advisor error] ' . $e->getMessage() . "\n");

            return 2;
        }

        $refusal = $config->refusal();
        if ($refusal !== null) {
            fwrite($stderr, '[advisor error] ' . $refusal . "\n");

            return 2;
        }

        StdoutGuard::install();
        $logger = new StderrLogger($config->logLevel);

        try {
            $booted = Runtime::boot($config, $logger);
            $server = SdkServer::build($booted, $logger);
            $server->run(new StdioTransport(logger: $logger));
        } catch (Throwable $e) {
            fwrite($stderr, '[advisor error] ' . $e->getMessage() . "\n");

            return 1;
        }

        return 0;
    }
}
