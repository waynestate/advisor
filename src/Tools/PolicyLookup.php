<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;
use Waynestate\Creed\State;

/** Support dates from CREED's policy.json, with the state worked out for today. */
final class PolicyLookup
{
    public static function definition(ModuleContext $context): ToolDefinition
    {
        $policy = $context->content->policy();
        $names = [...$policy->components(), ...array_values(array_diff($policy->tools(), $policy->components()))];

        return new ToolDefinition(
            name: 'policy_lookup',
            title: 'Look up support dates',
            description: 'Answers "is this version supported?" from the bundled CREED policy.json: for a runtime or framework (php, laravel, node, mysql, vue, inertia) it returns each version\'s active and security-support end dates and its state today (active, security, eol, eol_grace_expired); for a developer tool (composer) it returns the minimum version. Rows carry latest_patch (the newest patch release on that line, with the date it was checked) when the policy has it. Pass a version such as 8.3 or 8.3.12 to get one answer. A version older than every one the policy lists is answered as end of life, with a note. Read-only; every answer names its source and date.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'component' => ['type' => 'string', 'enum' => $names, 'description' => 'Runtime, framework or tool name.'],
                    'version' => ['type' => 'string', 'pattern' => '^[0-9]{1,3}(\.[0-9]{1,3}){0,2}$', 'maxLength' => 12, 'description' => 'Optional version, for example 8.3 or 13.'],
                ],
                'required' => ['component'],
                'additionalProperties' => false,
            ],
            handler: static function (array $args) use ($context): array {
                $policy = $context->content->policy();
                $component = (string) ($args['component'] ?? '');
                $version = isset($args['version']) ? (string) $args['version'] : null;
                $today = $context->clock->today();
                $base = [
                    'source' => $context->content->source('policy.json'),
                    'generated_on' => $context->content->generatedOn(),
                    'today' => $today->format('Y-m-d'),
                    'component' => $component,
                ];

                if (!$policy->hasComponent($component)) {
                    $minimum = $policy->toolMinimum($component);
                    if ($minimum === null) {
                        throw new ToolInputError('Unknown component. Known: ' . implode(', ', [...$policy->components(), ...$policy->tools()]) . '.');
                    }

                    return $base + ['kind' => 'tool', 'minimum_version' => $minimum];
                }

                $rows = [];
                foreach ($policy->versions($component) as $key) {
                    $dates = $policy->line($component, $key) ?? [];
                    $rows[] = [
                        'version' => $key,
                        'active_until' => $dates['active_until'] ?? null,
                        'security_until' => $dates['security_until'] ?? null,
                        'latest_patch' => $dates['latest_patch'] ?? null,
                        'latest_patch_checked' => $dates['latest_patch_checked'] ?? null,
                        'state' => $policy->tryStateOf($component, $key, $today)?->value,
                    ];
                }

                $answer = $base + [
                    'kind' => 'runtime',
                    'eol_grace_days' => $policy->rules()['eol_grace_days'] ?? null,
                    'oldest_supported' => $policy->oldestSupported($component, $today),
                    'versions' => $rows,
                ];

                if ($version !== null) {
                    $key = $policy->resolveVersion($component, $version);
                    if ($key === null) {
                        $oldest = $policy->versions($component)[0] ?? null; // CREED lists them oldest first
                        $askedMajorMinor = implode('.', array_slice(explode('.', $version), 0, 2));
                        if ($oldest !== null && version_compare($askedMajorMinor, $oldest, '<')) {
                            $oldestState = $policy->tryStateOf($component, $oldest, $today);
                            $oldestSecurityUntil = $policy->line($component, $oldest)['security_until'] ?? null;
                            $note = "Older than every {$component} version the policy lists (oldest: {$oldest}"
                                . ($oldestSecurityUntil !== null ? ", security support ended {$oldestSecurityUntil}" : '')
                                . '), so it is past its end of life.';
                            $answer['answer'] = [
                                'asked' => $version,
                                'matched' => null,
                                'state' => $oldestState?->value === State::EolGraceExpired->value ? 'eol_grace_expired' : 'eol',
                                'supported' => false,
                                'latest_patch' => null,
                                'latest_patch_checked' => null,
                                'note' => $note,
                            ];

                            return $answer;
                        }

                        throw new ToolInputError("The policy has no entry for {$component} {$version}. Call policy_lookup without a version to see the versions it covers.");
                    }
                    $state = $policy->tryStateOf($component, $key, $today);
                    $matched = $policy->line($component, $key) ?? [];
                    $answer['answer'] = [
                        'asked' => $version,
                        'matched' => $key,
                        'state' => $state?->value,
                        'supported' => $state !== null && in_array($state->value, ['active', 'security'], true),
                        'latest_patch' => $matched['latest_patch'] ?? null,
                        'latest_patch_checked' => $matched['latest_patch_checked'] ?? null,
                    ];
                }

                return $answer;
            },
        );
    }
}
