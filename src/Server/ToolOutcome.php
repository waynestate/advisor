<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Server;

/** What a tool call produced, before an adapter turns it into its framework's result type. */
final class ToolOutcome
{
    /**
     * @param array<string, mixed> $data structured content; empty for an error
     */
    public function __construct(
        public readonly bool $isError,
        public readonly string $text,
        public readonly array $data = [],
    ) {}
}
