<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Exception;

use RuntimeException;

/**
 * A problem the model can fix: a bad argument, a missing install, a path
 * outside the project. The message is shown to the model, so it says how to
 * correct the call and never quotes secrets or absolute host paths.
 */
final class ToolInputError extends RuntimeException {}
