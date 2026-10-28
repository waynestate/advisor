<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Support;

use Waynestate\Advisor\Security\ProcessOutput;
use Waynestate\Advisor\Security\ProcessRunner;

/** Never starts a program. Records what it was asked to run and answers from a script. */
final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<array{argv: list<string>, cwd: string, timeout: int}> */
    public array $calls = [];

    public function __construct(private ProcessOutput $answer = new ProcessOutput(0, '{"version":"0.1.0","findings":[]}', '')) {}

    public function answer(ProcessOutput $answer): void
    {
        $this->answer = $answer;
    }

    public function run(array $argv, string $cwd, int $timeoutSeconds): ProcessOutput
    {
        $this->calls[] = ['argv' => $argv, 'cwd' => $cwd, 'timeout' => $timeoutSeconds];

        return $this->answer;
    }
}
