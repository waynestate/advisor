<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Module;

use InvalidArgumentException;
use Waynestate\Advisor\Config;

/**
 * Every tool, resource and prompt the server will offer, after the switches
 * are applied. Read-only wins over everything: with it on (the default), a
 * tool that is not read-only is never registered, whatever the groups say.
 */
final class Catalog
{
    /** @var array<string, ToolDefinition> */
    private array $tools = [];

    /** @var array<string, ResourceDefinition> */
    private array $resources = [];

    /** @var array<string, PromptDefinition> */
    private array $prompts = [];

    /** @var list<string> names of tools left out by the switches, for the start-up log */
    private array $skipped = [];

    /**
     * @param list<Module> $modules
     */
    public function __construct(array $modules, Config $config)
    {
        foreach ($modules as $module) {
            foreach ($module->tools() as $tool) {
                if (isset($this->tools[$tool->name])) {
                    throw new InvalidArgumentException("Two modules define the tool {$tool->name}.");
                }
                if (($config->readOnly && !$tool->readOnly) || ($config->groups !== [] && !in_array($tool->group, $config->groups, true))) {
                    $this->skipped[] = $tool->name;
                    continue;
                }
                $this->tools[$tool->name] = $tool;
            }
            foreach ($module->resources() as $resource) {
                $this->resources[$resource->uri] = $resource;
            }
            foreach ($module->prompts() as $prompt) {
                $this->prompts[$prompt->name] = $prompt;
            }
        }
        ksort($this->tools);
        ksort($this->resources);
        ksort($this->prompts);
    }

    /**
     * Sorted by name so tools/list comes back in the same order every time.
     *
     * @return array<string, ToolDefinition>
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function resources(): array
    {
        return $this->resources;
    }

    /**
     * @return array<string, PromptDefinition>
     */
    public function prompts(): array
    {
        return $this->prompts;
    }

    /**
     * @return list<string>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
