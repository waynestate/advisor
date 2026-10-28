<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Laravel;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Waynestate\Advisor\Module\PromptDefinition;

final class CorePrompt extends Prompt
{
    public function __construct(private readonly PromptDefinition $definition)
    {
        $this->name = $definition->name;
        $this->title = $definition->title;
        $this->description = $definition->description;
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return array_map(
            static fn (array $a): Argument => new Argument($a['name'], $a['description'], $a['required']),
            $this->definition->arguments,
        );
    }

    public function handle(Request $request): Response
    {
        $strings = [];
        foreach ($request->all() as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $strings[$key] = $value;
            }
        }

        return Response::text(($this->definition->render)($strings));
    }
}
