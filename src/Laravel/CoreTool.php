<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Laravel;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Waynestate\Advisor\Module\ToolDefinition;
use Waynestate\Advisor\Server\ToolInvoker;

/**
 * Wraps one core ToolDefinition as a laravel/mcp tool. It holds no rules:
 * the schema, the annotations and the behaviour all come from the definition.
 */
final class CoreTool extends Tool
{
    public function __construct(
        private readonly ToolDefinition $definition,
        private readonly ToolInvoker $invoker,
    ) {
        $this->name = $definition->name;
        $this->title = $definition->title;
        $this->description = $definition->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        return [
            'readOnlyHint' => $this->definition->readOnly,
            'destructiveHint' => false,
            'idempotentHint' => $this->definition->idempotent,
            'openWorldHint' => $this->definition->openWorld,
        ];
    }

    /**
     * Use the definition's JSON Schema as it is, instead of laravel/mcp's builder.
     *
     * @return array{
     *     name: string,
     *     title?: string|null,
     *     description?: string|null,
     *     inputSchema?: array<string, mixed>,
     *     outputSchema?: array<string, mixed>,
     *     annotations?: array<string, mixed>,
     *     _meta?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $this->definition->inputSchema,
            'outputSchema' => $this->definition->outputSchema,
            'annotations' => ['title' => $this->title(), ...$this->annotations()],
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $outcome = $this->invoker->invoke($this->definition, $request->all());
        if ($outcome->isError) {
            return Response::error($outcome->text);
        }

        return Response::structured($outcome->data);
    }
}
