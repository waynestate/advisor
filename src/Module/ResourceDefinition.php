<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Module;

use Closure;

final class ResourceDefinition
{
    /**
     * @param Closure(): string $read returns the resource text
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly string $mimeType,
        public readonly Closure $read,
    ) {}
}
