<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;

/**
 * Where a stack project stands: built or proposed, which PHP it requires, and
 * whether that PHP floor is still inside security support today.
 */
final class GetProjectStatus
{
    public static function definition(ModuleContext $context): ToolDefinition
    {
        return new ToolDefinition(
            name: 'get_project_status',
            title: 'Get a project\'s status',
            description: 'Reports one stack project\'s state (built or proposed), its Composer package, the PHP version it requires and whether that PHP floor is in security support today according to the bundled CREED policy, plus the suggested next step. Read-only. It describes the stack project, not your app; use run_proctor_check to measure your app.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'project' => ['type' => 'string', 'enum' => $context->content->projectIds(), 'description' => 'Project id from list_projects.'],
                ],
                'required' => ['project'],
                'additionalProperties' => false,
            ],
            handler: static function (array $args) use ($context): array {
                $project = $context->content->project((string) ($args['project'] ?? ''));
                if ($project === null) {
                    throw new ToolInputError('Unknown project. Call list_projects for the ids.');
                }

                $policy = $context->content->policy();
                $today = $context->clock->today();
                $constraint = is_string($project['php'] ?? null) ? $project['php'] : null;
                $floor = $constraint === null ? null : self::floor($constraint);
                $floorKey = $floor === null ? null : $policy->resolveVersion('php', $floor);
                $floorState = $floorKey === null ? null : $policy->stateOf('php', $floorKey, $today);

                return [
                    'project' => $project['id'],
                    'name' => $project['name'],
                    'state' => $project['state'],
                    'package' => $project['package'],
                    'php_constraint' => $constraint,
                    'php_floor' => $floor,
                    'php_floor_state' => $floorState?->value,
                    'oldest_supported_php' => $policy->oldestSupported('php', $today),
                    'today' => $today->format('Y-m-d'),
                    'docs' => $project['docs'],
                    'next_step' => $project['next_step'],
                    'source' => 'ADVISOR bundled content and creed/policy/policy.json',
                    'generated_on' => $context->content->generatedOn(),
                ];
            },
        );
    }

    /** The lowest major.minor a Composer PHP constraint allows, or null when none can be read. */
    public static function floor(string $constraint): ?string
    {
        if (!preg_match_all('/(\d+)\.(\d+)/', $constraint, $matches, PREG_SET_ORDER)) {
            return null;
        }
        $lowest = null;
        foreach ($matches as $match) {
            $version = $match[1] . '.' . $match[2];
            if ($lowest === null || version_compare($version, $lowest, '<')) {
                $lowest = $version;
            }
        }

        return $lowest;
    }
}
