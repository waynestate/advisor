<?php

declare(strict_types=1);

namespace Waynestate\Playbook;

/**
 * Test double for the private waynestate/playbook package: the same shape
 * (static get and all) with example.test values only. Required on demand by
 * the playbook_lookup tests; the unavailable test runs in its own process,
 * where this file is never loaded.
 */
final class Playbook
{
    /** @var array<string, string> */
    private const VALUES = [
        'CAS_URL' => 'https://cas.example.test/login',
        'WSU_DOMAIN' => 'example.test',
        'DB_PASSWORD' => 'not-a-real-password',
    ];

    public static function get(string $key): ?string
    {
        return self::VALUES[$key] ?? null;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::VALUES;
    }
}
