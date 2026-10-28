<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Module;

use Closure;

/**
 * A repeatable task a client can show as a slash command. Prompt arguments
 * are all optional strings, as the protocol sends them.
 */
final class PromptDefinition
{
    /**
     * @param list<array{name: string, description: string, required: bool}> $arguments
     * @param Closure(array<string, string>): string                         $render
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly array $arguments,
        public readonly Closure $render,
    ) {}
}
