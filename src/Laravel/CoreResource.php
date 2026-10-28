<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Laravel;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Waynestate\Advisor\Module\ResourceDefinition;
use Waynestate\Advisor\Support\Invisible;

final class CoreResource extends Resource
{
    public function __construct(private readonly ResourceDefinition $definition)
    {
        $this->uri = $definition->uri;
        $this->name = $definition->name;
        $this->title = $definition->title;
        $this->description = $definition->description;
        $this->mimeType = $definition->mimeType;
    }

    public function handle(Request $request): Response
    {
        return Response::text(Invisible::strip(($this->definition->read)()));
    }
}
