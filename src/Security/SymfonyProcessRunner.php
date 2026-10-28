<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Security;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Argument array, timeout, allowlisted environment, capped output, no stdin.
 * Symfony Process is the maintained answer to proc_open on every OS, so we do
 * not write our own.
 */
final class SymfonyProcessRunner implements ProcessRunner
{
    private const MAX_OUTPUT_BYTES = 2_000_000;

    public function run(array $argv, string $cwd, int $timeoutSeconds): ProcessOutput
    {
        $current = [];
        foreach (getenv() as $name => $value) {
            $current[(string) $name] = $value;
        }
        foreach (array_keys($_SERVER) as $name) {
            // $_SERVER also carries inherited variables; blank any that are not on the list.
            $current[(string) $name] ??= false;
        }

        $process = new Process($argv, $cwd, EnvAllowlist::forProcess($current), null, (float) $timeoutSeconds);
        $process->setInput(null);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return new ProcessOutput(-1, '', 'The process timed out and was stopped.', true);
        }

        return new ProcessOutput(
            (int) $process->getExitCode(),
            substr($process->getOutput(), 0, self::MAX_OUTPUT_BYTES),
            substr($process->getErrorOutput(), 0, self::MAX_OUTPUT_BYTES),
        );
    }
}
