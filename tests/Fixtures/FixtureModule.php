<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Fixtures;

use Waynestate\Advisor\Module\Module;
use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\PromptDefinition;
use Waynestate\Advisor\Module\ResourceDefinition;
use Waynestate\Advisor\Module\ToolDefinition;

/**
 * A stand-in for a per-project module (PROCTOR's own tools, say). It offers
 * one read-only tool and one tool that writes, to prove the read-only switch.
 */
final class FixtureModule implements Module
{
    /** @var list<string> what the write tool was asked to do, so a test can prove it never ran */
    public static array $writes = [];

    public function __construct(private readonly ModuleContext $context) {}

    public function id(): string
    {
        return 'fixture';
    }

    public function tools(): array
    {
        $schema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'maxLength' => 20]], 'required' => ['name'], 'additionalProperties' => false];

        return [
            new ToolDefinition(
                name: 'fixture_greet',
                title: 'Greet',
                description: 'Greets by name. Read-only fixture.',
                inputSchema: $schema,
                handler: static fn(array $args): array => ['greeting' => 'hello ' . $args['name']],
                group: 'fixture',
            ),
            new ToolDefinition(
                name: 'fixture_write',
                title: 'Write',
                description: 'Pretends to write a file. Fixture only.',
                inputSchema: $schema,
                handler: static function (array $args): array {
                    self::$writes[] = (string) $args['name'];

                    return ['written' => true];
                },
                group: 'fixture',
                readOnly: false,
                idempotent: false,
            ),
        ];
    }

    public function resources(): array
    {
        return [new ResourceDefinition('advisor://fixture/note', 'fixture-note', 'Fixture note', 'A note.', 'text/plain', static fn(): string => 'note')];
    }

    public function prompts(): array
    {
        return [new PromptDefinition('fixture_prompt', 'Fixture prompt', 'A prompt.', [], static fn(array $a): string => 'do the fixture thing')];
    }
}
