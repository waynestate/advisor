<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Security;

/**
 * The environment a child process gets: an allowlist, not a blocklist
 * (best practice 2.6). The app's .env values, tokens and cloud keys are not
 * on it, so a tool cannot leak them by running a program.
 *
 * Windows needs its own names (SystemRoot above all: without it PHP cannot
 * start sockets), which is why the list holds both families.
 */
final class EnvAllowlist
{
    /** @var list<string> */
    public const NAMES = [
        'PATH', 'HOME', 'USER', 'LOGNAME', 'LANG', 'LC_ALL', 'TMPDIR', 'TZ',
        'PHPRC', 'PHP_INI_SCAN_DIR',
        'COMPOSER_HOME', 'COMPOSER_CACHE_DIR',
        'SYSTEMROOT', 'SYSTEMDRIVE', 'WINDIR', 'COMSPEC', 'PATHEXT', 'TEMP', 'TMP',
        'USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'PROGRAMDATA', 'PROGRAMFILES', 'NUMBER_OF_PROCESSORS',
    ];

    /**
     * What to hand Symfony Process: allowed names keep their value, everything
     * else is set to false, which removes it from the inherited environment.
     *
     * @param array<string, string|false|null> $current
     *
     * @return array<string, string|false>
     */
    public static function forProcess(array $current): array
    {
        $allowed = array_map('strtoupper', self::NAMES);
        $result = [];
        foreach ($current as $name => $value) {
            $name = (string) $name;
            if (in_array(strtoupper($name), $allowed, true) && is_string($value)) {
                $result[$name] = $value;
            } else {
                $result[$name] = false;
            }
        }

        return $result;
    }

    /**
     * @param array<string, string|false|null> $current
     *
     * @return array<string, string>
     */
    public static function allowedOnly(array $current): array
    {
        return array_filter(
            self::forProcess($current),
            static fn (string|false $value): bool => $value !== false,
        );
    }
}
