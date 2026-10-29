<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;
use Waynestate\Advisor\Security\BoosterRunner;

/** Plans the app's PHP and Laravel upgrade, one supported hop at a time, via Booster's read-only `plan`. */
final class UpgradePath
{
    public static function definition(ModuleContext $context): ToolDefinition
    {
        $runner = new BoosterRunner(
            $context->guard,
            $context->processes,
            $context->content->policyFile(),
            $context->config->boosterTimeout,
            $context->config->boosterBin,
        );

        return new ToolDefinition(
            name: 'upgrade_path',
            title: 'Plan the upgrade path',
            description: 'Plans how to upgrade the app\'s PHP and/or Laravel, one supported hop at a time, by running BOOSTER\'s read-only `plan` against the bundled CREED policy. It returns the ordered hops (with the Rector sets and manual notes for each), any warnings, and the exact `booster run` command to start. It changes nothing in the project and needs no network. The path must be inside the project. BOOSTER does the work when you run it; this only shows the way.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'maxLength' => 1024, 'description' => 'App folder, inside the project root. Default: the root itself.'],
                    'to_php' => ['type' => 'string', 'pattern' => '^[0-9]{1,2}\\.[0-9]{1,2}$', 'description' => 'Target PHP version, major.minor, for example 8.4. Default: the newest supported one.'],
                    'to_laravel' => ['type' => 'string', 'pattern' => '^[0-9]{1,2}$', 'description' => 'Target Laravel major version, for example 11. Default: the newest supported one.'],
                ],
                'additionalProperties' => false,
            ],
            handler: static function (array $args) use ($runner): array {
                return $runner->plan(
                    (string) ($args['path'] ?? '.'),
                    isset($args['to_php']) ? (string) $args['to_php'] : null,
                    isset($args['to_laravel']) ? (string) $args['to_laravel'] : null,
                );
            },
            group: 'booster',
            readOnly: true,
            idempotent: true,
            openWorld: false,
        );
    }
}
