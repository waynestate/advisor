<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;
use Waynestate\Advisor\Security\ProctorRunner;

/** Runs PROCTOR's read-only check on an app inside the project root and returns the report. */
final class RunProctorCheck
{
    public static function definition(ModuleContext $context): ToolDefinition
    {
        $runner = new ProctorRunner(
            $context->guard,
            $context->processes,
            $context->content->policyFile(),
            $context->config->proctorTimeout,
        );

        return new ToolDefinition(
            name: 'run_proctor_check',
            title: 'Run PROCTOR on an app',
            description: 'Runs the app\'s own `vendor/bin/proctor check` against the bundled CREED policy and returns its findings (php-floor, unbounded-constraints, lock-committed, portability, mysql56, composer-audit, overrides). It changes nothing in the project, but composer-audit contacts the advisory database, so leave that check out when offline. The path must be inside the project. It reports only: PROCTOR in CI decides pass or fail.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'maxLength' => 1024, 'description' => 'App folder, inside the project root. Default: the root itself.'],
                    'checks' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ProctorRunner::CHECKS],
                        'uniqueItems' => true,
                        'maxItems' => 7,
                        'description' => 'Only report these checks. Default: all.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            outputSchema: self::outputSchema(),
            handler: static function (array $args) use ($runner): array {
                $checks = array_values(array_map('strval', is_array($args['checks'] ?? null) ? $args['checks'] : []));

                return $runner->run((string) ($args['path'] ?? '.'), $checks);
            },
            group: 'proctor',
            openWorld: true,
        );
    }

    /**
     * What run_proctor_check returns: PROCTOR's report, shaped by ProctorRunner.
     *
     * @return array<string, mixed>
     */
    private static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'tool' => ['type' => 'string', 'const' => 'proctor', 'description' => 'The tool that produced the report.'],
                'proctor_version' => ['type' => 'string', 'description' => 'The PROCTOR version that ran the check.'],
                'app' => ['type' => 'string', 'description' => 'The app folder the check ran on, relative to the project root.'],
                'checks_run' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The checks the report covers, in PROCTOR order.'],
                'result' => ['type' => 'string', 'enum' => ['pass', 'fail'], 'description' => 'pass when no check failed, fail when at least one did.'],
                'findings' => [
                    'type' => 'array',
                    'description' => 'One entry per reported finding.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'check' => ['type' => 'string', 'description' => 'The check id.'],
                            'status' => ['type' => 'string', 'description' => 'The check status PROCTOR reported.'],
                            'message' => ['type' => 'string', 'description' => 'The finding message, quoted from the project.'],
                            'path' => ['type' => ['string', 'null'], 'description' => 'The file the finding points at, or null when there is none.'],
                            'line' => ['type' => ['integer', 'null'], 'description' => 'The line the finding points at, or null when there is none.'],
                        ],
                    ],
                ],
                'notice' => ['type' => 'string', 'description' => 'A reminder that PROCTOR in CI decides pass or fail.'],
            ],
            'required' => ['tool', 'proctor_version', 'app', 'checks_run', 'result', 'findings', 'notice'],
        ];
    }
}
