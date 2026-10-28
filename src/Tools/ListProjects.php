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
            description: 'Lists every project of the Wayne State app stack (WayneUI, CREED, PROCTOR, SESAME, HELMET, Sourdough, PREFLIGHT, Pinstripe, BOOSTER, ROLLCALL, DRAWBRIDGE, SQUIRE, ADVISOR, WELD) with its role, whether it is built or only proposed, and its Composer package. Read-only. Use it first to learn what exists, then read_project_docs for detail.',
            inputSchema: ['type' => 'object', 'additionalProperties' => false],
            handler: static function () use ($context): array {
                $projects = [];
                foreach ($context->content->projects() as $project) {
                    $projects[] = [
                        'id' => $project['id'],
                        'name' => $project['name'],
                        'kind' => $project['kind'],
                        'state' => $project['state'],
                        'package' => $project['package'],
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
}
