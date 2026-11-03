<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Playbook;

/**
 * One place ADVISOR can read shared values from by name: the private
 * Playbook package, or the .env.playbook file a developer downloads.
 */
interface PlaybookSource
{
    /** The label this source shows up as in an answer's "source". */
    public function name(): string;

    public function available(): bool;

    public function get(string $key): ?string;

    /** @return array<string, string> */
    public function all(): array;
}
