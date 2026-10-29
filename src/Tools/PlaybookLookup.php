<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;

/** Shared values from PLAYBOOK, with the environment winning the argument. */
final class PlaybookLookup
{
    /** Keys that look like secrets are never looked up or returned. */
    private const SECRET_LIKE = '/(SECRET|PASSWORD|TOKEN|KEY)$/i';

    private const HOW_TO_GET = 'composer require waynestate/playbook (VCS repository https://git.wayne.edu/enterprise-apps/playbook.git). PLAYBOOK is private on git.wayne.edu. If you get a 404, ask the Enterprise Applications team for read access.';

    public static function definition(ModuleContext $context): ToolDefinition
    {
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
            handler: static function (array $args): array {
                $playbook = \Waynestate\Playbook\Playbook::class;
                if (!class_exists($playbook)) {
                    return ['available' => false, 'how_to_get' => self::HOW_TO_GET];
                }

                $key = isset($args['key']) ? trim((string) $args['key']) : null;
                if ($key !== null && $key === '') {
                    throw new ToolInputError('Give a key to look up, or call playbook_lookup without arguments for every key.');
                }
                if ($key !== null && preg_match(self::SECRET_LIKE, $key) === 1) {
                    throw new ToolInputError('Refusing to look up a key that looks like a secret (it ends in SECRET, PASSWORD, TOKEN or KEY). ADVISOR never returns secret-looking values.');
                }

                if ($key === null) {
                    $entries = [];
                    $all = $playbook::all();
                    foreach (is_array($all) ? array_keys($all) : [] as $name) {
                        $name = (string) $name;
                        if (preg_match(self::SECRET_LIKE, $name) === 1) {
                            continue;
                        }
                        $found = self::valueOf($playbook, $name);
                        $entries[] = ['key' => $name, 'value' => $found['value'], 'source' => $found['source']];
                    }

                    return ['available' => true, 'entries' => $entries];
                }

                $found = self::valueOf($playbook, $key);

                return ['available' => true, 'key' => $key, 'value' => $found['value'], 'source' => $found['source']];
            },
        );
    }

    /**
     * One key, environment first, then PLAYBOOK.
     *
     * @param class-string $playbook
     *
     * @return array{value: string|null, source: string}
     */
    private static function valueOf(string $playbook, string $key): array
    {
        $env = getenv($key);
        if (is_string($env) && $env !== '') {
            return ['value' => $env, 'source' => 'environment'];
        }
        $value = $playbook::get($key);
        if (is_string($value) && $value !== '') {
            return ['value' => $value, 'source' => 'playbook'];
        }

        return ['value' => null, 'source' => 'unset'];
    }
}
