<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Content;

/**
 * What state a version is in. The values are the words CREED, PROCTOR and
 * HELMET use in reports and gates.
 */
enum State: string
{
    case Active = 'active';
    case Security = 'security';
    case Eol = 'eol';
    case EolGraceExpired = 'eol_grace_expired';
}
