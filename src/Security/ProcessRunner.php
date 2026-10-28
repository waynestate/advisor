<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Security;

/**
 * The one way ADVISOR starts another program. Tests replace it with a fake so
 * no test ever runs PROCTOR or touches the network.
 */
interface ProcessRunner
{
    /**
     * @param list<string> $argv argument array, never a shell string
     */
    public function run(array $argv, string $cwd, int $timeoutSeconds): ProcessOutput;
}
