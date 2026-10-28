<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Fixtures;

/** Lives in an allowed namespace but does not implement Module. */
final class NotAModule
{
    public function __construct(mixed $context = null) {}
}
