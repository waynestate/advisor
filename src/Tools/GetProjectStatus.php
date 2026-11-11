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
            description: 'Reports one stack project\'s state (built or proposed), its Composer package, its version when it publishes one, the PHP version it requires and whether that PHP floor is in security support today according to the bundled CREED policy, plus the suggested next step. Read-only. It describes the stack project, not your app; use run_proctor_check to measure your app.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'project' => ['type' => 'string', 'enum' => $context->content->projectIds(), 'description' => 'Project id from list_projects.'],
                ],
                'required' => ['project'],
                'additionalProperties' => false,
            ],
            outputSchema: self::outputSchema(),
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
                $floorState = $floorKey === null ? null : $policy->tryStateOf('php', $floorKey, $today);

                return [
                    'project' => $project['id'],
                    'name' => $project['name'],
                    'state' => $project['state'],
                    'package' => $project['package'],
                    'version' => $project['version'] ?? null,
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

    /**
     * What get_project_status returns: one project's row plus its PHP floor's state today.
     *
     * @return array<string, mixed>
     */
    private static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'project' => ['type' => 'string', 'description' => 'The project id.'],
                'name' => ['type' => 'string', 'description' => 'The project name.'],
                'state' => ['type' => 'string', 'description' => 'built when the repository holds code, proposed when it holds only a plan.'],
                'package' => ['type' => ['string', 'null'], 'description' => 'The Composer package, or null for projects that are not one.'],
                'version' => ['type' => ['string', 'null'], 'description' => 'The version the project publishes, or null when it publishes none.'],
                'php_constraint' => ['type' => ['string', 'null'], 'description' => 'The PHP constraint the project names, or null when it names none.'],
                'php_floor' => ['type' => ['string', 'null'], 'description' => 'The lowest major.minor the constraint allows, or null when none can be read.'],
                'php_floor_state' => ['type' => ['string', 'null'], 'description' => 'The floor state today per the CREED policy (active, security, eol or eol_grace_expired), or null without a floor.'],
                'oldest_supported_php' => ['type' => ['string', 'null'], 'description' => 'The oldest PHP line still in support today, or null when none is.'],
                'today' => ['type' => 'string', 'description' => 'The date the states were worked out, YYYY-MM-DD.'],
                'docs' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The document kinds bundled for the project.'],
                'next_step' => ['type' => 'string', 'description' => 'The suggested next step for the project.'],
                'source' => ['type' => 'string', 'description' => 'Where the answer came from.'],
                'generated_on' => ['type' => 'string', 'description' => 'The date the bundled content was generated, YYYY-MM-DD.'],
            ],
            'required' => ['project', 'name', 'state', 'package', 'version', 'php_constraint', 'php_floor', 'php_floor_state', 'oldest_supported_php', 'today', 'docs', 'next_step', 'source', 'generated_on'],
        ];
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
