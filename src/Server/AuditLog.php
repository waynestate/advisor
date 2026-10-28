<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Server;

/**
 * One JSON line per tool call: time, tool, a hash of the arguments, outcome
 * and duration (best practice 4.9). Arguments are hashed, never written, so
 * the log cannot hold a secret a model was handed. Off unless
 * ADVISOR_AUDIT_LOG names a file.
 */
final class AuditLog
{
    public function __construct(private readonly ?string $file) {}

    /**
     * @param array<string, mixed> $arguments
     */
    public function record(string $tool, array $arguments, string $outcome, float $milliseconds): void
    {
        if ($this->file === null) {
            return;
        }
        $line = json_encode([
            'time' => gmdate('c'),
            'tool' => $tool,
            'args_sha256' => hash('sha256', json_encode($arguments, JSON_UNESCAPED_SLASHES) ?: ''),
            'outcome' => $outcome,
            'ms' => (int) round($milliseconds),
        ], JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            @file_put_contents($this->file, $line . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}
