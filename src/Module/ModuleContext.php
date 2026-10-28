<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Module;

use Waynestate\Advisor\Config;
use Waynestate\Advisor\Content\ContentBundle;
use Waynestate\Advisor\Security\PathGuard;
use Waynestate\Advisor\Security\ProcessRunner;
use Waynestate\Advisor\Support\Clock;

/** The services a module may use. Modules get nothing else, and no network client. */
final class ModuleContext
{
    public function __construct(
        public readonly Config $config,
        public readonly ContentBundle $content,
        public readonly Clock $clock,
        public readonly PathGuard $guard,
        public readonly ProcessRunner $processes,
    ) {}
}
