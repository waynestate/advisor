<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Laravel;

use Illuminate\Container\Container;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Server\Booted;

/**
 * ADVISOR as a laravel/mcp server, so it runs beside Laravel Boost:
 * `php artisan mcp:start advisor`. The tools are the core's, wrapped.
 */
#[Name(Advisor::NAME)]
#[Version(Advisor::VERSION)]
#[Instructions(Advisor::INSTRUCTIONS)]
final class AdvisorServer extends Server
{
    protected function boot(): void
    {
        $booted = Container::getInstance()->make(Booted::class);

        $this->tools = [];
        foreach ($booted->catalog->tools() as $tool) {
            $this->tools[] = new CoreTool($tool, $booted->invoker);
        }
        $this->resources = [];
        foreach ($booted->catalog->resources() as $resource) {
            $this->resources[] = new CoreResource($resource);
        }
        $this->prompts = [];
        foreach ($booted->catalog->prompts() as $prompt) {
            $this->prompts[] = new CorePrompt($prompt);
        }
    }
}
