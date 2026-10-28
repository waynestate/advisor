<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Server;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\ResourceDefinition as SdkResource;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\PromptHandlerInterface;
use Mcp\Server\Handler\ResourceHandlerInterface;
use Mcp\Server\Handler\ToolHandlerInterface;
use Psr\Log\LoggerInterface;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Module\PromptDefinition;
use Waynestate\Advisor\Module\ResourceDefinition;
use Waynestate\Advisor\Module\ToolDefinition;

/**
 * The adapter between ADVISOR's core and the official PHP MCP SDK.
 *
 * It holds no rules: it copies definitions into the SDK's value objects and
 * routes every call through ToolInvoker.
 */
final class SdkServer
{
    private function __construct() {}

    public static function build(Booted $booted, LoggerInterface $logger): Server
    {
        $builder = Server::builder()
            ->setServerInfo(Advisor::NAME, Advisor::VERSION, 'Wayne State app stack MCP server', null, null, 'ADVISOR')
            ->setInstructions(Advisor::INSTRUCTIONS)
            ->setLogger($logger);

        foreach ($booted->catalog->tools() as $tool) {
            $builder->add(self::tool($tool), new SdkToolHandler($tool, $booted->invoker));
        }
        foreach ($booted->catalog->resources() as $resource) {
            $builder->add(
                new SdkResource($resource->uri, $resource->name, $resource->title, $resource->description, $resource->mimeType),
                new SdkResourceHandler($resource),
            );
        }
        foreach ($booted->catalog->prompts() as $prompt) {
            $builder->add(self::prompt($prompt), new SdkPromptHandler($prompt));
        }

        return $builder->build();
    }

    private static function tool(ToolDefinition $tool): Tool
    {
        return new Tool(
            name: $tool->name,
            title: $tool->title,
            inputSchema: $tool->inputSchema,
            description: $tool->description,
            // Every hint is set on purpose: a missing hint is read as "may be destructive".
            annotations: new ToolAnnotations(
                title: $tool->title,
                readOnlyHint: $tool->readOnly,
                destructiveHint: false,
                idempotentHint: $tool->idempotent,
                openWorldHint: $tool->openWorld,
            ),
            outputSchema: $tool->outputSchema,
        );
    }

    private static function prompt(PromptDefinition $prompt): Prompt
    {
        return new Prompt(
            $prompt->name,
            $prompt->title,
            $prompt->description,
            array_map(
                static fn(array $a): PromptArgument => new PromptArgument($a['name'], $a['description'], $a['required']),
                $prompt->arguments,
            ),
        );
    }
}

/** @internal */
final class SdkToolHandler implements ToolHandlerInterface
{
    public function __construct(private readonly ToolDefinition $tool, private readonly ToolInvoker $invoker) {}

    public function execute(array $arguments, ClientGateway $gateway): CallToolResult
    {
        $outcome = $this->invoker->invoke($this->tool, $arguments);
        if ($outcome->isError) {
            return CallToolResult::error([new TextContent($outcome->text)]);
        }

        return new CallToolResult([new TextContent($outcome->text)], false, $outcome->data);
    }
}

/** @internal */
final class SdkResourceHandler implements ResourceHandlerInterface
{
    public function __construct(private readonly ResourceDefinition $resource) {}

    public function read(string $uri, ClientGateway $gateway): string
    {
        return \Waynestate\Advisor\Support\Invisible::strip(($this->resource->read)());
    }
}

/** @internal */
final class SdkPromptHandler implements PromptHandlerInterface
{
    public function __construct(private readonly PromptDefinition $prompt) {}

    /**
     * @return array<string, string>
     */
    public function get(array $arguments, ClientGateway $gateway): array
    {
        $strings = [];
        foreach ($arguments as $key => $value) {
            if (is_string($value)) {
                $strings[$key] = $value;
            }
        }

        // The SDK wants messages, not bare text; one user message carries the prompt.
        return ['user' => ($this->prompt->render)($strings)];
    }
}
