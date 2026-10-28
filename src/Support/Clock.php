<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Support;

use DateTimeImmutable;

/** A day-level clock so support dates are testable. Dates only: that is all the policy knows. */
interface Clock
{
    public function today(): DateTimeImmutable;
}
