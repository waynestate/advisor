<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Module;

use Closure;
use InvalidArgumentException;
use Waynestate\Advisor\Support\Invisible;

/**
 * One tool, described once and served by every adapter.
 *
 * The constructor enforces the rules the contract suite also checks: a
 * client-safe name, a bounded description, no invisible characters, and an
 * input schema that refuses extra properties.
 */
final class ToolDefinition
{
    public const MAX_NAME = 64;

    public const MAX_DESCRIPTION = 1200;

    /**
     * @param array<string, mixed>                      $inputSchema
     * @param array<string, mixed>                      $outputSchema
     * @param Closure(array<string, mixed>): array<string, mixed> $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly Closure $handler,
        public readonly string $group = 'stack',
        public readonly bool $readOnly = true,
        public readonly bool $idempotent = true,
        public readonly bool $openWorld = false,
        public readonly array $outputSchema = ['type' => 'object'],
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{0,' . (self::MAX_NAME - 1) . '}$/', $name)) {
            throw new InvalidArgumentException("Tool name {$name} must be lowercase letters, digits and underscores, at most 64 characters.");
        }
        if ($description === '' || strlen($description) > self::MAX_DESCRIPTION) {
            throw new InvalidArgumentException("Tool {$name} needs a description of 1 to " . self::MAX_DESCRIPTION . ' characters.');
        }
        if (($inputSchema['type'] ?? null) !== 'object' || ($inputSchema['additionalProperties'] ?? null) !== false) {
            throw new InvalidArgumentException("Tool {$name} must declare an object input schema with additionalProperties false.");
        }
        $metadata = $name . $title . $description . json_encode($inputSchema) . json_encode($outputSchema);
        if (Invisible::find($metadata) !== []) {
            throw new InvalidArgumentException("Tool {$name} metadata contains invisible characters.");
        }
        if (!preg_match('/^[a-z][a-z0-9-]{0,31}$/', $group)) {
            throw new InvalidArgumentException("Tool {$name} has an invalid group name.");
        }
    }
}
