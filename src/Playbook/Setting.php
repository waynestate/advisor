<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Playbook;

/**
 * One named setting and where its value came from: one of "environment",
 * "playbook", "env.playbook" or "unset".
 */
final class Setting
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $value,
        public readonly string $source,
    ) {}
}
