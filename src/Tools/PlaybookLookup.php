<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;

/** Shared values from Playbook, with the environment winning the argument. */
final class PlaybookLookup
{
    public static function definition(ModuleContext $context): ToolDefinition
    {
        $settings = $context->settings;

        return new ToolDefinition(
            name: 'playbook_lookup',
            title: 'Look up a shared value',
            description: 'Answers "what is the value of X?" for WSU shared values: an environment variable of the same name wins, then PLAYBOOK (waynestate/playbook) supplies the rest. Pass a key such as CAS_URL for one answer (the value plus its source: environment, playbook or unset), or nothing for every key with its value and source. Read-only; keys that look like secrets (ending in SECRET, PASSWORD, TOKEN or KEY) are refused. PLAYBOOK is optional; without it the tool says how to get it instead of failing.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Optional shared-value key, for example CAS_URL.'],
                ],
                'additionalProperties' => false,
            ],
            handler: static function (array $args) use ($settings): array {
                if (!$settings->available()) {
                    return ['available' => false, 'how_to_get' => $settings->howToGet()];
                }

                $key = isset($args['key']) ? trim((string) $args['key']) : null;
                if ($key !== null && $key === '') {
                    throw new ToolInputError('Give a key to look up, or call playbook_lookup without arguments for every key.');
                }

                if ($key === null) {
                    $entries = [];
                    foreach ($settings->names() as $name) {
                        $setting = $settings->resolve($name);
                        $entries[] = ['key' => $name, 'value' => $setting->value, 'source' => $setting->source];
                    }

                    return ['available' => true, 'entries' => $entries];
                }

                $setting = $settings->resolve($key);

                return ['available' => true, 'key' => $key, 'value' => $setting->value, 'source' => $setting->source];
            },
        );
    }
}
