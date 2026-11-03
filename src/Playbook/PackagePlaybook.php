<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Playbook;

/**
 * The private waynestate/playbook package. Reached only through a plain
 * string, never ::class: it is a suggested dependency CI never installs,
 * so PHPStan must not look the class up.
 */
final class PackagePlaybook implements PlaybookSource
{
    private const CLASS_NAME = 'Waynestate\Playbook\Playbook';

    public function name(): string
    {
        return 'playbook';
    }

    public function available(): bool
    {
        return class_exists(self::CLASS_NAME);
    }

    public function get(string $key): ?string
    {
        if (!class_exists(self::CLASS_NAME)) {
            return null;
        }
        $value = self::CLASS_NAME::get($key);

        return is_string($value) ? $value : null;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if (!class_exists(self::CLASS_NAME)) {
            return [];
        }
        $all = self::CLASS_NAME::all();

        return is_array($all) ? $all : [];
    }
}
