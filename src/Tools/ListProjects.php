<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;

/** The parts list: every stack project, its state and its package. */
final class ListProjects
{
    public static function definition(ModuleContext $context): ToolDefinition
    {
        return new ToolDefinition(
            name: 'list_projects',
            title: 'List the stack projects',
            description: 'Lists every project of the Wayne State app stack (WayneUI, CREED, PROCTOR, SESAME, HELMET, Sourdough, PREFLIGHT, Pinstripe, BOOSTER, ROLLCALL, DRAWBRIDGE, SQUIRE, Parity, ADVISOR, WELD) with its role, whether it is built or only proposed, its Composer package and its version when it publishes one. Read-only. Use it first to learn what exists, then read_project_docs for detail.',
            inputSchema: ['type' => 'object', 'additionalProperties' => false],
            outputSchema: self::outputSchema(),
            handler: static function () use ($context): array {
                $projects = [];
                foreach ($context->content->projects() as $project) {
                    $projects[] = [
                        'id' => $project['id'],
                        'name' => $project['name'],
                        'kind' => $project['kind'],
                        'state' => $project['state'],
                        'package' => $project['package'],
                        'version' => $project['version'] ?? null,
                        'role' => $project['role'],
                        'docs' => $project['docs'],
                    ];
                }

                return [
                    'source' => 'ADVISOR bundled content',
                    'generated_on' => $context->content->generatedOn(),
                    'note' => 'Reference data from WSU repositories. State is built when the repository holds code, proposed when it holds only a plan.',
                    'projects' => $projects,
                ];
            },
        );
    }

    /**
     * What list_projects returns: the parts list, its origin and its date.
     *
     * @return array<string, mixed>
     */
    private static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'source' => ['type' => 'string', 'description' => 'Where the parts list came from.'],
                'generated_on' => ['type' => 'string', 'description' => 'The date the bundled content was generated, YYYY-MM-DD.'],
                'note' => ['type' => 'string', 'description' => 'What built and proposed mean in the state field.'],
                'projects' => [
                    'type' => 'array',
                    'description' => 'Every stack project, in parts-list order.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'description' => 'The project id, as passed to the other tools.'],
                            'name' => ['type' => 'string', 'description' => 'The project name.'],
                            'kind' => ['type' => 'string', 'description' => 'The part of the stack the project is.'],
                            'state' => ['type' => 'string', 'description' => 'built when the repository holds code, proposed when it holds only a plan.'],
                            'package' => ['type' => ['string', 'null'], 'description' => 'The Composer package, or null for projects that are not one.'],
                            'version' => ['type' => ['string', 'null'], 'description' => 'The version the project publishes, or null when it publishes none.'],
                            'role' => ['type' => 'string', 'description' => 'What the project does in the stack.'],
                            'docs' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The document kinds bundled for the project.'],
                        ],
                    ],
                ],
            ],
            'required' => ['source', 'generated_on', 'note', 'projects'],
        ];
    }
}
