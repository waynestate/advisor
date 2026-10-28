<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Exception;

use RuntimeException;

/** The bundled content is missing, changed, or fails a check. ADVISOR fails closed rather than serve it. */
final class ContentException extends RuntimeException {}
