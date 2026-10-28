<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Server;

use Waynestate\Advisor\Config;
use Waynestate\Advisor\Module\Catalog;
use Waynestate\Advisor\Module\ModuleContext;

/** Everything an adapter needs to serve ADVISOR, built once. */
final class Booted
{
    public function __construct(
        public readonly Config $config,
        public readonly Catalog $catalog,
        public readonly ToolInvoker $invoker,
        public readonly ModuleContext $context,
    ) {}
}
