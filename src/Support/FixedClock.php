<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Support;

use DateTimeImmutable;

final class FixedClock implements Clock
{
    private readonly DateTimeImmutable $day;

    public function __construct(string $date)
    {
        $this->day = new DateTimeImmutable($date);
    }

    public function today(): DateTimeImmutable
    {
        return $this->day;
    }
}
