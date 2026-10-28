<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Server;

use Psr\Log\LoggerInterface;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Config;
use Waynestate\Advisor\Content\ContentBundle;
use Waynestate\Advisor\Module\Catalog;
use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ModuleLoader;
use Waynestate\Advisor\Security\PathGuard;
use Waynestate\Advisor\Security\ProcessRunner;
use Waynestate\Advisor\Security\SymfonyProcessRunner;
use Waynestate\Advisor\Support\Clock;
use Waynestate\Advisor\Support\SystemClock;

/**
 * Wires the framework-free core together. Every adapter (stdio via the PHP
 * SDK, the Laravel server) starts here, so the rules live in one place.
 */
final class Runtime
{
    private function __construct() {}

    public static function boot(
        Config $config,
        LoggerInterface $logger,
        ?ProcessRunner $processes = null,
        ?Clock $clock = null,
        ?string $dataDir = null,
        ?string $manifestFile = null,
    ): Booted {
        $content = new ContentBundle($dataDir ?? Advisor::dataDir());
        $content->verify(); // fail closed before anything is offered

        $context = new ModuleContext(
            $config,
            $content,
            $clock ?? new SystemClock(),
            new PathGuard($config->projectRoot),
            $processes ?? new SymfonyProcessRunner(),
        );

        $manifests = [$manifestFile ?? dirname(__DIR__, 2) . '/modules.json'];
        if ($config->modulesFile !== null) {
            $manifests[] = $config->modulesFile;
        }
        $modules = (new ModuleLoader())->load($manifests, $context);
        $catalog = new Catalog($modules, $config);

        if ($catalog->skipped() !== []) {
            $logger->info('Tools left out by ADVISOR_READ_ONLY or ADVISOR_GROUPS: ' . implode(', ', $catalog->skipped()));
        }

        return new Booted($config, $catalog, new ToolInvoker(new AuditLog($config->auditLog), $logger), $context);
    }
}
