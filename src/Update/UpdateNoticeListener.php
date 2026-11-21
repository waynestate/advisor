<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Update;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Throwable;

/**
 * The console glue for the update notice: a TERMINATE listener that prints
 * the line after any one-shot command, through the event's output for
 * --quiet/-q, and stays silent for the mcp server command, whose stderr is
 * the protocol log. It never throws, never touches stdout, and never
 * changes the exit code.
 */
final class UpdateNoticeListener
{
    /** The long-running server command whose stderr is a protocol log. */
    private const SERVER_COMMAND = 'mcp';

    /**
     * @param list<string>                     $argv   the command line, as argv
     * @param array<string, string>            $env    the process environment, read once at startup and injected so tests need no real environment
     * @param callable(string): void|resource  $stderr where the line goes
     */
    public function __construct(
        private readonly UpdateNotice $notice,
        private readonly string $version,
        private readonly array $argv,
        private readonly array $env,
        private readonly callable|resource $stderr,
    ) {}

    public function __invoke(ConsoleTerminateEvent $event): void
    {
        try {
            $command = $event->getCommand();
            if ($command instanceof Command && self::SERVER_COMMAND === $command->getName()) {
                return;
            }
            if ($event->getOutput()->isQuiet()) {
                return;
            }

            $this->notice->run($this->env, $this->argv, $this->version, $this->stderr);
        } catch (Throwable) {
            // The notice never gets in the way.
        }
    }
}
