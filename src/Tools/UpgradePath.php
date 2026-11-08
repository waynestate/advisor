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
            description: 'Plans how to upgrade the app\'s PHP and/or Laravel, one supported hop at a time, by running BOOSTER\'s read-only `plan` against the bundled CREED policy. It returns the ordered hops (with the Rector sets and manual notes for each), any warnings, and the exact `booster run` command to start. It changes nothing in the project and needs no network. The path must be inside the project. BOOSTER does the work when you run it; this only shows the way. If BOOSTER refuses because the project names no PHP version, call again with from_php set to the version the app runs (run_proctor_check reports it).',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'maxLength' => 1024, 'description' => 'App folder, inside the project root. Default: the root itself.'],
                    'from_php' => ['type' => 'string', 'pattern' => '^[0-9]{1,2}\\.[0-9]{1,2}$', 'description' => 'PHP version the app runs today, major.minor, for example 7.4. Only needed when the project names none (no composer.json, or no php constraint in it); BOOSTER then plans from this version.'],
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
                    isset($args['from_php']) ? (string) $args['from_php'] : null,
                );
            },
            group: 'booster',
            readOnly: true,
            idempotent: true,
            openWorld: false,
        );
    }
}
