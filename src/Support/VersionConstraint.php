<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Support;

/**
 * Does a version satisfy a composer-style constraint?
 *
 * ADVISOR's own composer.json is the only constraint it ever checks (the PHP
 * floor), and composer/semver is a dev-only dependency that is not installed
 * when ADVISOR is a --dev app dependency. So this is a small, self-contained
 * checker for the shapes composer.json actually uses: "*", "^X.Y", "~X.Y",
 * ">=X.Y", ">X.Y", "<=X.Y", "<X.Y", "=X.Y", "X.Y.*", and comma (AND) /
 * "||" (OR) separated lists.
 */
final class VersionConstraint
{
    private function __construct() {}

    public static function satisfies(string $constraint, string $version): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*' || $constraint === 'any') {
            return true;
        }
        foreach (explode('||', $constraint) as $group) {
            $group = trim($group);
            if ($group === '') {
                continue;
            }
            $all = true;
            foreach (preg_split('/[\s,]+/', $group) ?: [] as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                if (!self::single($part, $version)) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return true;
            }
        }

        return false;
    }

    private static function single(string $part, string $version): bool
    {
        if ($part === '' || $part === '*' || $part === 'any') {
            return true;
        }
        if (str_starts_with($part, '^')) {
            return self::caret(substr($part, 1), $version);
        }
        if (str_starts_with($part, '~')) {
            return self::tilde(substr($part, 1), $version);
        }
        if (preg_match('/^(>=|<=|>|<|==|=)\s*(.+)$/', $part, $m) === 1) {
            return self::compare($m[1], $m[2], $version);
        }
        if (str_contains($part, '*')) {
            return self::wildcard($part, $version);
        }

        return self::compare('=', $part, $version);
    }

    /** ^X.Y.Z: >=X.Y.Z, below the next major (or next minor when X is 0). */
    private static function caret(string $base, string $version): bool
    {
        $b = self::parts($base);
        $v = self::parts($version);
        if (self::cmp($v, $b) < 0) {
            return false;
        }
        $upper = $b;
        $bumpedAt = null;
        foreach ($upper as $i => $p) {
            if ($p !== 0) {
                $upper[$i] = $p + 1;
                $bumpedAt = $i;
                break;
            }
        }
        if ($bumpedAt === null) {
            $upper[count($upper) - 1] = $upper[count($upper) - 1] + 1;
            $bumpedAt = count($upper) - 1;
        }
        for ($i = $bumpedAt + 1; $i < count($upper); $i++) {
            $upper[$i] = 0;
        }

        /** @var list<int> $upper */
        return self::cmp($v, $upper) < 0;
    }

    /** ~X.Y.Z: >=X.Y.Z <X.(Y+1).0; ~X.Y: >=X.Y <X.(Y+1); ~X: >=X <(X+1). */
    private static function tilde(string $base, string $version): bool
    {
        $b = self::parts($base);
        $v = self::parts($version);
        if (self::cmp($v, $b) < 0) {
            return false;
        }
        $upper = $b;
        $n = count($upper);
        if ($n >= 3) {
            $upper[$n - 2] = $upper[$n - 2] + 1;
            $upper[$n - 1] = 0;
        } else {
            $upper[$n - 1] = $upper[$n - 1] + 1;
        }

        /** @var list<int> $upper */
        return self::cmp($v, $upper) < 0;
    }

    private static function compare(string $op, string $base, string $version): bool
    {
        $c = self::cmp(self::parts($version), self::parts($base));

        return match ($op) {
            '>=' => $c >= 0,
            '<=' => $c <= 0,
            '>' => $c > 0,
            '<' => $c < 0,
            default => $c === 0,
        };
    }

    /** 8.3.*: the leading numeric parts must match, the rest is free. */
    private static function wildcard(string $part, string $version): bool
    {
        $v = self::parts($version);
        foreach (preg_split('/[.*]/', $part) ?: [] as $i => $p) {
            if (!preg_match('/^\d+$/', (string) $p)) {
                break;
            }
            if (($v[$i] ?? 0) !== (int) $p) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private static function parts(string $version): array
    {
        $version = trim($version);
        if (str_starts_with($version, 'v')) {
            $version = substr($version, 1);
        }
        $out = [];
        foreach (preg_split('/[.\-+]/', $version) ?: [] as $p) {
            if (!preg_match('/^\d+$/', (string) $p)) {
                break;
            }
            $out[] = (int) $p;
        }

        return $out === [] ? [0] : $out;
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     */
    private static function cmp(array $a, array $b): int
    {
        $n = max(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $x = $a[$i] ?? 0;
            $y = $b[$i] ?? 0;
            if ($x !== $y) {
                return $x <=> $y;
            }
        }

        return 0;
    }
}
