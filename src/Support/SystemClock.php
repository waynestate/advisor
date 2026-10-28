<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Support;

use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}
